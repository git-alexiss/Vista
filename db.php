<?php
// ─── db.php ──────────────────────────────────────────────────────────────────
//  Database connection + all data functions for VISTA-Rizal
//  Single database: vista_rizal_new
// ─────────────────────────────────────────────────────────────────────────────

if (!defined('DB_HOST')) {
    $serverName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $serverName);

    if ($isLocal) {
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'vista_rizal_new');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_PORT', 3307);
    } else {
        define('DB_HOST', 'sql307.infinityfree.com');
        define('DB_NAME', 'if0_41810035_vista_rizal_new');
        define('DB_USER', 'if0_41810035');
        define('DB_PASS', 'MAYeleven23');
        define('DB_PORT', 3307);
    }
}

// ─── PDO Singleton ────────────────────────────────────────────────────────────
function getDB(): PDO {
    static $db = null;
    if ($db !== null) return $db;

    try {
        $db = new PDO(
            'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        die('
        <div style="font-family:Segoe UI,Arial,sans-serif;max-width:520px;margin:60px auto;
                    padding:32px;background:#fff5f5;border:1.5px solid #fcc;
                    border-radius:12px;text-align:center;">
            <h2 style="color:#c53030;">Database Connection Failed</h2>
            <p style="color:#555;margin-bottom:12px;">Please check the following:</p>
            <ul style="text-align:left;color:#555;line-height:2.2;margin:0 auto;max-width:320px;">
                <li>XAMPP → MySQL is <strong>running</strong></li>
                <li>Database name: <strong>vista_rizal_new</strong></li>
                <li><strong>vista_rizal_new.sql</strong> was imported in phpMyAdmin</li>
                <li>DB_USER / DB_PASS in <strong>db.php</strong> are correct</li>
            </ul>
            <p style="margin-top:16px;font-size:.82rem;color:#aaa;">Error: '.htmlspecialchars($e->getMessage()).'</p>
        </div>');
    }

    return $db;
}


// ═══════════════════════════════════════════════════════════════════════════════
//  USER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

function getUserByEmail(string $email): ?array {
    $stmt = getDB()->prepare(
        'SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([strtolower(trim($email))]);
    return $stmt->fetch() ?: null;
}

function getUserById(int $id): ?array {
    $stmt = getDB()->prepare(
        'SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function verifyUserPassword(string $email, string $password): ?array {
    $user = getUserByEmail($email);
    if (!$user)                                               return null;
    if (($user['oauth_provider'] ?? 'local') !== 'local')    return null;
    if (!password_verify($password, $user['password_hash'])) return null;
    return $user;
}

function registerUser(array $data): int|false {
    $db = getDB();

    $check = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->execute([strtolower(trim($data['email']))]);
    if ($check->fetch()) return false;

    $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $db->prepare('
        INSERT INTO users
            (name, email, password_hash, role, nationality, address, birthdate, oauth_provider)
        VALUES (?, ?, ?, ?, ?, ?, ?, "local")
    ');
    $stmt->execute([
        htmlspecialchars(trim($data['name']),                 ENT_QUOTES, 'UTF-8'),
        strtolower(trim($data['email'])),
        $hash,
        $data['role']        ?? 'tourist',
        htmlspecialchars(trim($data['nationality'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
        htmlspecialchars(trim($data['address']     ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
        !empty($data['birthdate']) ? $data['birthdate'] : null,
    ]);

    return (int) $db->lastInsertId();
}

function upsertOAuthUser(
    string $provider, string $oauthId,
    string $name, string $email, ?string $avatarUrl = null
): array {
    $db = getDB();

    $stmt = $db->prepare('SELECT * FROM users WHERE oauth_provider=? AND oauth_id=? LIMIT 1');
    $stmt->execute([$provider, $oauthId]);
    $user = $stmt->fetch();
    if ($user) {
        $db->prepare('UPDATE users SET name=?,avatar_url=?,updated_at=NOW() WHERE id=?')
           ->execute([$name, $avatarUrl, $user['id']]);
        return array_merge($user, ['name'=>$name,'avatar_url'=>$avatarUrl]);
    }

    $stmt = $db->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();
    if ($user) {
        $db->prepare('UPDATE users SET oauth_provider=?,oauth_id=?,avatar_url=?,updated_at=NOW() WHERE id=?')
           ->execute([$provider, $oauthId, $avatarUrl, $user['id']]);
        return array_merge($user, ['oauth_provider'=>$provider,'oauth_id'=>$oauthId]);
    }

    $db->prepare('
        INSERT INTO users (name,email,password_hash,role,nationality,address,oauth_provider,oauth_id,avatar_url)
        VALUES (?,?,"","tourist","N/A","N/A",?,?,?)
    ')->execute([$name, strtolower(trim($email)), $provider, $oauthId, $avatarUrl]);

    return getUserById((int) $db->lastInsertId());
}

function changePassword(int $userId, string $newPassword): bool {
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    return getDB()->prepare(
        'UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?'
    )->execute([$hash, $userId]);
}

function buildSessionUser(array $row): array {
    return [
        'id'          => (int)$row['id'],
        'name'        => $row['name'],
        'email'       => $row['email'],
        'role'        => $row['role'],
        'nationality' => $row['nationality'] ?? 'N/A',
        'address'     => $row['address']     ?? 'N/A',
        'birthdate'   => $row['birthdate']   ?? 'N/A',
        'avatar_url'  => $row['avatar_url']  ?? null,
        'provider'    => $row['oauth_provider'] ?? 'local',
    ];
}


// ═══════════════════════════════════════════════════════════════════════════════
//  ATTRACTION FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

function getAttractions(): array {
    $stmt = getDB()->prepare('
        SELECT a.*, m.name AS municipality
        FROM   attractions a
        JOIN   municipalities m ON a.municipality_id = m.id
        WHERE  a.is_active = 1
        ORDER  BY a.id
    ');
    $stmt->execute();
    return $stmt->fetchAll();
}

function getAttractionById(int $id): ?array {
    $stmt = getDB()->prepare('
        SELECT a.*, m.name AS municipality
        FROM   attractions a
        JOIN   municipalities m ON a.municipality_id = m.id
        WHERE  a.id = ? AND a.is_active = 1 LIMIT 1
    ');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getPopularAttractions(int $limit = 6): array {
    $stmt = getDB()->prepare('
        SELECT a.*, m.name AS municipality
        FROM   attractions a
        JOIN   municipalities m ON a.municipality_id = m.id
        WHERE  a.is_active = 1
        ORDER  BY a.rating DESC
        LIMIT  ?
    ');
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getAttractionRating(int $attractionId): ?float {
    $stmt = getDB()->prepare('
        SELECT ROUND(AVG(rating), 1) AS avg_rating
        FROM   reviews
        WHERE  attraction_id = ?
    ');
    $stmt->execute([$attractionId]);
    $result = $stmt->fetchColumn();
    return ($result !== null && $result !== false) ? (float) $result : null;
}

function getRecommendations(array $viewedIds = [], int $limit = 6): array {
    if (empty($viewedIds)) return getPopularAttractions($limit);

    $placeholders = implode(',', array_fill(0, count($viewedIds), '?'));
    $stmt = getDB()->prepare("
        SELECT a.*, m.name AS municipality
        FROM   attractions a
        JOIN   municipalities m ON a.municipality_id = m.id
        WHERE  a.is_active = 1 AND a.id NOT IN ($placeholders)
        ORDER  BY a.rating DESC
        LIMIT  ?
    ");
    $stmt->execute([...$viewedIds, $limit]);
    return $stmt->fetchAll();
}

function searchAttractions(string $query): array {
    $q = '%' . trim($query) . '%';
    $stmt = getDB()->prepare('
        SELECT a.*, m.name AS municipality
        FROM   attractions a
        JOIN   municipalities m ON a.municipality_id = m.id
        WHERE  a.is_active = 1
          AND (a.name LIKE ? OR m.name LIKE ? OR a.category LIKE ? OR a.fact LIKE ?)
        ORDER  BY a.rating DESC
    ');
    $stmt->execute([$q, $q, $q, $q]);
    return $stmt->fetchAll();
}


// ═══════════════════════════════════════════════════════════════════════════════
//  REVIEW FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

function getReviews(?int $attractionId = null): array {
    if ($attractionId === null) {
        $stmt = getDB()->prepare('
            SELECT r.*, u.name AS user, a.name AS attraction_name
            FROM   reviews r
            JOIN   users u       ON r.user_id       = u.id
            JOIN   attractions a ON r.attraction_id = a.id
            ORDER  BY r.created_at DESC
        ');
        $stmt->execute();
    } else {
        $stmt = getDB()->prepare('
            SELECT r.*, u.name AS user
            FROM   reviews r
            JOIN   users u ON r.user_id = u.id
            WHERE  r.attraction_id = ?
            ORDER  BY r.created_at DESC
        ');
        $stmt->execute([$attractionId]);
    }
    return $stmt->fetchAll();
}

function addReview(int $attractionId, int $userId, int $rating, string $text): bool {
    $sentiment = classifyReview($text);
    $stmt = getDB()->prepare('
        INSERT INTO reviews (attraction_id, user_id, rating, review_text, sentiment)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            rating=VALUES(rating), review_text=VALUES(review_text),
            sentiment=VALUES(sentiment), created_at=NOW()
    ');
    return $stmt->execute([
        $attractionId, $userId, $rating,
        htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
        $sentiment,
    ]);
}


// ═══════════════════════════════════════════════════════════════════════════════
//  TOURIST INSIGHTS FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

function getInsightsMunicipalities(): array {
    $stmt = getDB()->prepare(
        'SELECT * FROM municipalities_insights ORDER BY name'
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function getTouristInsights(?string $location = null, int $limit = 500): array {
    if ($location) {
        $sql  = 'SELECT * FROM tourist_insights WHERE location_name = ? ORDER BY id';
        $sql .= $limit > 0 ? " LIMIT $limit" : '';
        $stmt = getDB()->prepare($sql);
        $stmt->execute([$location]);
    } else {
        $sql  = 'SELECT * FROM tourist_insights ORDER BY id';
        $sql .= $limit > 0 ? " LIMIT $limit" : '';
        $stmt = getDB()->prepare($sql);
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

function getLocationSummary(?string $location = null): array {
    if ($location) {
        $stmt = getDB()->prepare(
            'SELECT * FROM location_summary WHERE location_name = ? LIMIT 1'
        );
        $stmt->execute([$location]);
        return [$stmt->fetch() ?: []];
    }
    $stmt = getDB()->prepare(
        'SELECT * FROM location_summary ORDER BY avg_rating DESC'
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function getRatingDistribution(?string $location = null): array {
    if ($location) {
        $stmt = getDB()->prepare(
            'SELECT rating, COUNT(*) AS count
             FROM   tourist_insights
             WHERE  location_name = ?
             GROUP  BY rating ORDER BY rating DESC'
        );
        $stmt->execute([$location]);
    } else {
        $stmt = getDB()->prepare(
            'SELECT rating, COUNT(*) AS count
             FROM   tourist_insights
             GROUP  BY rating ORDER BY rating DESC'
        );
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

function getSatisfactionBreakdown(?string $location = null): array {
    $labelMap = [1 => 'Satisfied', 2 => 'Unsatisfied'];

    if ($location) {
        $stmt = getDB()->prepare(
            'SELECT satisfaction_label, COUNT(*) AS count
             FROM   tourist_insights
             WHERE  location_name = ?
             GROUP  BY satisfaction_label ORDER BY satisfaction_label'
        );
        $stmt->execute([$location]);
    } else {
        $stmt = getDB()->prepare(
            'SELECT satisfaction_label, COUNT(*) AS count
             FROM   tourist_insights
             GROUP  BY satisfaction_label ORDER BY satisfaction_label'
        );
        $stmt->execute();
    }

    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['label_text'] = $labelMap[$r['satisfaction_label']] ?? 'Unknown';
    }
    return $rows;
}

function getInsightsOverallStats(): array {
    $stmt = getDB()->prepare('
        SELECT
            COUNT(*)                                          AS total,
            ROUND(AVG(rating),          2)                   AS avg_rating,
            ROUND(AVG(sentiment_score), 4)                   AS avg_sentiment,
            ROUND(SUM(satisfaction_label=1)/COUNT(*)*100, 1) AS satisfaction_rate
        FROM tourist_insights
    ');
    $stmt->execute();
    return $stmt->fetch() ?: [];
}


// ═══════════════════════════════════════════════════════════════════════════════
//  RECENTLY VIEWED
// ═══════════════════════════════════════════════════════════════════════════════

function recordView(int $userId, int $attractionId): void {
    getDB()->prepare('
        INSERT INTO recently_viewed (user_id, attraction_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE viewed_at = NOW()
    ')->execute([$userId, $attractionId]);
}

function getRecentlyViewedIds(int $userId, int $limit = 10): array {
    $stmt = getDB()->prepare('
        SELECT attraction_id FROM recently_viewed
        WHERE user_id = ?
        ORDER BY viewed_at DESC
        LIMIT ?
    ');
    $stmt->execute([$userId, $limit]);
    return array_column($stmt->fetchAll(), 'attraction_id');
}


// ═══════════════════════════════════════════════════════════════════════════════
//  LOGIN ATTEMPT TRACKING
// ═══════════════════════════════════════════════════════════════════════════════

function getClientIP(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}

function countRecentAttempts(string $ip, int $minutes = 15): int {
    $stmt = getDB()->prepare('
        SELECT COUNT(*) FROM login_attempts
        WHERE ip_address = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ');
    $stmt->execute([$ip, $minutes]);
    return (int) $stmt->fetchColumn();
}

function recordLoginAttempt(string $ip, string $email = ''): void {
    getDB()->prepare(
        'INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)'
    )->execute([$ip, $email]);
}

function clearOldAttempts(string $ip): void {
    getDB()->prepare(
        'DELETE FROM login_attempts WHERE ip_address = ? AND attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    )->execute([$ip]);
}
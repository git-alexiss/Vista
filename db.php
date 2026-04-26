<?php
// ─── db.php ──────────────────────────────────────────────────────────────────
//  Database connection + all data functions for VISTA-Rizal
//  Requires: vista_rizal.sql imported in phpMyAdmin
// ─────────────────────────────────────────────────────────────────────────────

define('DB_HOST', 'localhost');
define('DB_NAME', 'vista_rizal');
define('DB_USER', 'root');
define('DB_PASS', '');   // XAMPP default — change if you set a password
define('DB_PORT', 3307);

// ─── PDO Singleton ───────────────────────────────────────────────────────────
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
                <li>Database name: <strong>vista_rizal</strong></li>
                <li><strong>vista_rizal.sql</strong> was imported in phpMyAdmin</li>
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

/** Find a user by email. Returns row array or null. */
function getUserByEmail(string $email): ?array {
    $stmt = getDB()->prepare(
        'SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([strtolower(trim($email))]);
    return $stmt->fetch() ?: null;
}

/** Find a user by ID. Returns row array or null. */
function getUserById(int $id): ?array {
    $stmt = getDB()->prepare(
        'SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Verify email + password using password_verify().
 * Returns user array on success, null on failure.
 */
function verifyUserPassword(string $email, string $password): ?array {
    $user = getUserByEmail($email);
    if (!$user)                                          return null;
    if (($user['oauth_provider'] ?? 'local') !== 'local') return null;
    if (!password_verify($password, $user['password_hash'])) return null;
    return $user;
}

/**
 * Register a new local user with a bcrypt-hashed password.
 * Returns new user ID, or false if email already exists.
 */
function registerUser(array $data): int|false {
    $db = getDB();

    $check = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->execute([strtolower(trim($data['email']))]);
    if ($check->fetch()) return false; // duplicate email

    $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $db->prepare('
        INSERT INTO users
            (name, email, password_hash, role, nationality, address, birthdate, oauth_provider)
        VALUES (?, ?, ?, ?, ?, ?, ?, "local")
    ');
    $stmt->execute([
        htmlspecialchars(trim($data['name']),        ENT_QUOTES, 'UTF-8'),
        strtolower(trim($data['email'])),
        $hash,
        $data['role']        ?? 'tourist',
        htmlspecialchars(trim($data['nationality'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
        htmlspecialchars(trim($data['address']     ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
        !empty($data['birthdate']) ? $data['birthdate'] : null,
    ]);

    return (int) $db->lastInsertId();
}

/**
 * Save or update a Google / Facebook OAuth user.
 * Returns full user array.
 */
function upsertOAuthUser(
    string $provider, string $oauthId,
    string $name, string $email, ?string $avatarUrl = null
): array {
    $db = getDB();

    // 1. Find by OAuth provider + ID
    $stmt = $db->prepare('SELECT * FROM users WHERE oauth_provider=? AND oauth_id=? LIMIT 1');
    $stmt->execute([$provider, $oauthId]);
    $user = $stmt->fetch();
    if ($user) {
        $db->prepare('UPDATE users SET name=?,avatar_url=?,updated_at=NOW() WHERE id=?')
           ->execute([$name, $avatarUrl, $user['id']]);
        return array_merge($user, ['name'=>$name,'avatar_url'=>$avatarUrl]);
    }

    // 2. Find by email
    $stmt = $db->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();
    if ($user) {
        $db->prepare('UPDATE users SET oauth_provider=?,oauth_id=?,avatar_url=?,updated_at=NOW() WHERE id=?')
           ->execute([$provider, $oauthId, $avatarUrl, $user['id']]);
        return array_merge($user, ['oauth_provider'=>$provider,'oauth_id'=>$oauthId]);
    }

    // 3. New OAuth user
    $db->prepare('
        INSERT INTO users (name,email,password_hash,role,nationality,address,oauth_provider,oauth_id,avatar_url)
        VALUES (?,?,"","tourist","N/A","N/A",?,?,?)
    ')->execute([$name, strtolower(trim($email)), $provider, $oauthId, $avatarUrl]);

    return getUserById((int) $db->lastInsertId());
}

/** Change a user's password. */
function changePassword(int $userId, string $newPassword): bool {
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    return getDB()->prepare(
        'UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?'
    )->execute([$hash, $userId]);
}

/** Build a clean session array from a DB row. */
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

/** Get all active attractions (with municipality name joined). */
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

/** Get a single attraction by ID. */
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

/** Top-rated attractions (for Popular page). */
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

/**
 * Get the average rating for an attraction from the reviews table.
 * Returns a formatted float (e.g. 4.5) or null if no reviews exist.
 */
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

/** Recommended = top-rated excluding already-viewed. */
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

/** Full-text search across name, category, municipality, fact. */
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

/** Get reviews for a specific attraction (or all if null). */
function getReviews(?int $attractionId = null): array {
    if ($attractionId === null) {
        $stmt = getDB()->prepare('
            SELECT r.*, u.name AS user, a.name AS attraction_name
            FROM   reviews r
            JOIN   users u        ON r.user_id       = u.id
            JOIN   attractions a  ON r.attraction_id = a.id
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

/** Add a review (saves to DB). */
function addReview(int $attractionId, int $userId, int $rating, string $text): bool {
    $sentiment = classifyReview($text);
    $stmt = getDB()->prepare('
        INSERT INTO reviews (attraction_id, user_id, rating, review_text, sentiment)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            rating=VALUES(rating), review_text=VALUES(review_text),
            sentiment=VALUES(sentiment), created_at=NOW()
    ');
    return $stmt->execute([$attractionId, $userId, $rating,
                           htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), $sentiment]);
}

// ═══════════════════════════════════════════════════════════════════════════════
//  RECENTLY VIEWED
// ═══════════════════════════════════════════════════════════════════════════════

/** Record a page view (upsert). */
function recordView(int $userId, int $attractionId): void {
    getDB()->prepare('
        INSERT INTO recently_viewed (user_id, attraction_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE viewed_at = NOW()
    ')->execute([$userId, $attractionId]);
}

/** Get recently viewed attraction IDs for a user. */
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
//  LOGIN ATTEMPT TRACKING (IP-based, stored in DB)
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
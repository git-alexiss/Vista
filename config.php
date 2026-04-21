<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load database layer
require_once __DIR__ . '/db.php';

// ─── Admin Credentials ────────────────────────────────────────────────────────
define('ADMIN_EMAIL',    'admin@vista-rizal.ph');
define('ADMIN_PASSWORD', 'Admin@2025');
define('ADMIN_NAME',     'VISTA Admin');

// ─── Security constants ───────────────────────────────────────────────────────
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES',    15);
define('SESSION_TIMEOUT',    1800);

// ─── Auto-detect Base URL ─────────────────────────────────────────────────────
// Works on localhost/XAMPP, subfolders, and live servers — no hardcoding needed
(function () {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    define('BASE_URL', $scheme . '://' . $host . $dir . '/');
})();

// ─── Google OAuth ─────────────────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     'paste-your-google-client-id.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'paste-your-google-client-secret');
define('GOOGLE_REDIRECT_URI',  BASE_URL . 'google_callback.php');
define('GOOGLE_MAPS_API_KEY',  'paste-your-maps-key-if-any');

// ─── Facebook OAuth ───────────────────────────────────────────────────────────
define('FACEBOOK_APP_ID',      'paste-your-facebook-app-id');
define('FACEBOOK_APP_SECRET',  'paste-your-facebook-app-secret');
define('FACEBOOK_REDIRECT_URI', BASE_URL . 'facebook_callback.php');

// ─── Basic helpers ────────────────────────────────────────────────────────────
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateSatisfactionScore($ratings) {
    if (empty($ratings)) return 0;
    return round(array_sum($ratings) / count($ratings), 1);
}

function classifyReview($text) {
    $positive = ['great','amazing','love','excellent','perfect','beautiful','wonderful','fantastic','awesome','nice','good','enjoyed','stunning'];
    $negative  = ['bad','terrible','poor','awful','disappointing','horrible','worst','hate','dirty','overcrowded','broken'];
    $lower = strtolower($text);
    $pos = $neg = 0;
    foreach ($positive as $w) if (strpos($lower,$w)!==false) $pos++;
    foreach ($negative  as $w) if (strpos($lower,$w)!==false) $neg++;
    if ($pos > $neg) return 'positive';
    if ($neg > $pos) return 'negative';
    return 'neutral';
}

// ─── Brute-force / lockout ────────────────────────────────────────────────────
function isLockedOut(): bool {
    $ip = getClientIP();
    return countRecentAttempts($ip, LOCKOUT_MINUTES) >= MAX_LOGIN_ATTEMPTS;
}
function recordFailedAttempt(string $email = ''): void {
    recordLoginAttempt(getClientIP(), $email);
}
function resetLoginAttempts(): void {
    clearOldAttempts(getClientIP());
}
function remainingLockoutMinutes(): int {
    return LOCKOUT_MINUTES;
}

// ─── OTP ─────────────────────────────────────────────────────────────────────
function generateOTP(): string { return str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT); }
function setOTP(string $otp): void {
    $_SESSION['otp'] = ['code'=>$otp,'expires'=>time()+300,'attempts'=>0];
}
function verifyOTP(string $input): bool {
    if (!isset($_SESSION['otp']))             return false;
    if (time() > $_SESSION['otp']['expires']) return false;
    if ($_SESSION['otp']['attempts'] >= 3)    return false;
    $_SESSION['otp']['attempts']++;
    return hash_equals((string)$_SESSION['otp']['code'], trim($input));
}
function clearOTP(): void { unset($_SESSION['otp']); }

// ─── Session timeout ──────────────────────────────────────────────────────────
function checkSessionTimeout(): void {
    if (isset($_SESSION['last_active']) && (time()-$_SESSION['last_active']) > SESSION_TIMEOUT) {
        session_unset(); session_destroy();
        header('Location: login.php?reason=timeout'); exit;
    }
    $_SESSION['last_active'] = time();
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────
function generateCSRF(): string {
    if (empty($_SESSION['csrf_token']))
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verifyCSRF(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function isOpen($hours): string {
    if (strpos($hours,'All day')!==false) return 'Open 24/7';
    return $hours;
}

function darkModeAttr(): string {
    return !empty($_SESSION['settings']['dark_mode']) ? ' data-theme="dark"' : '';
}

// ─── Navigation ───────────────────────────────────────────────────────────────
function renderNav($activePage='') {
    $user = $_SESSION['user'] ?? [];
    $role = $user['role']     ?? 'tourist';
    $name = htmlspecialchars($user['name'] ?? 'User');

    $pages = [
        'home'        => ['href'=>'index.php',       'label'=>'🏠 Home'],
        'popular'     => ['href'=>'popular.php',     'label'=>'🔥 Popular'],
        'recommended' => ['href'=>'recommended.php', 'label'=>'⭐ Recommended'],
        'attractions' => ['href'=>'attractions.php', 'label'=>'🗺 Attractions'],
        'settings'    => ['href'=>'settings.php',    'label'=>'⚙️ Settings'],
    ];
    if ($role === 'admin') {
        $pages['dashboard'] = ['href'=>'admin_dashboard.php','label'=>'📊 Dashboard'];
        $pages['reports']   = ['href'=>'reports.php',        'label'=>'📈 Reports'];
    }

    ob_start(); ?>
<nav class="main-nav">
  <div class="nav-container">
    <a href="index.php" class="logo">VISTA<span>Rizal</span></a>
    <form class="nav-search" action="search.php" method="GET">
      <input type="text" name="q" placeholder="Search attractions…"
             value="<?= htmlspecialchars($_GET['q']??'') ?>" autocomplete="off">
      <button type="submit" aria-label="Search">&#128269;</button>
    </form>
    <div class="nav-dropdown-wrap" id="navDropdownWrap">
      <button class="hamburger" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <div class="nav-dropdown" id="navDropdown" role="menu">
        <div class="dropdown-user">
          <?php if (!empty($user['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($user['avatar_url']) ?>"
                 alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
          <?php else: ?>
            <div class="dropdown-avatar"><?= strtoupper(substr($name,0,1)) ?></div>
          <?php endif; ?>
          <div><strong><?= $name ?></strong><small><?= ucfirst($role) ?></small></div>
        </div>
        <div class="dropdown-divider"></div>
        <?php foreach($pages as $key=>$p): ?>
          <a href="<?= $p['href'] ?>"
             class="dropdown-item<?= $activePage===$key?' active':'' ?>"
             role="menuitem"><?= $p['label'] ?></a>
        <?php endforeach; ?>
        <div class="dropdown-divider"></div>
        <a href="profile.php" class="dropdown-item" role="menuitem">👤 My Profile</a>
        <a href="logout.php"  class="dropdown-item dropdown-logout" role="menuitem">🚪 Sign Out</a>
      </div>
    </div>
  </div>
</nav>
<script>
(function(){
  const wrap=document.getElementById('navDropdownWrap'),
        btn=document.getElementById('hamburgerBtn'),
        menu=document.getElementById('navDropdown');
  let t;
  const open=()=>{clearTimeout(t);menu.classList.add('open');btn.classList.add('active');btn.setAttribute('aria-expanded','true');};
  const shut=()=>{t=setTimeout(()=>{menu.classList.remove('open');btn.classList.remove('active');btn.setAttribute('aria-expanded','false');},120);};
  wrap.addEventListener('mouseenter',open);
  wrap.addEventListener('mouseleave',shut);
  btn.addEventListener('click',()=>menu.classList.contains('open')?shut():open());
  document.addEventListener('click',e=>{if(!wrap.contains(e.target))shut();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape')shut();});
})();
</script>
<?php return ob_get_clean();
}
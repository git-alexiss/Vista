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

define('RENDER_API_BASE_URL', 'https://vista-rizal.onrender.com');

function callRenderApi(string $path, array $payload = []): array|false {
    $url = rtrim(RENDER_API_BASE_URL, '/') . '/' . ltrim($path, '/');
    $json = json_encode($payload);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $error || $httpCode >= 400) {
        error_log('Render API request failed: ' . ($error ?: "HTTP $httpCode"));
        return false;
    }

    return json_decode($response, true);
}

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

//─── Image Display ────────────────────────────────────────────────────────────
function getAttractionImage(string $name): string {
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));
    return 'CSS\images\attractions/' . $slug . '.jpg';
}

function getMunicipalityImage(string $name): string {
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));
    return 'CSS\images\municipalities/' . $slug . '.jpg';
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
    $settings = $_SESSION['settings'] ?? [];

    if (isset($settings['dark_mode'])) {
        $dark = (bool)$settings['dark_mode'];
    } else {
        // Check cookie as fallback
        $dark = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === '1';
    }
    
    // Always set the session setting from the cookie/preference
    if (!isset($_SESSION['settings'])) {
        $_SESSION['settings'] = [];
    }
    $_SESSION['settings']['dark_mode'] = $dark;

    return $dark ? ' class="dark-mode"' : '';
}

// ─── Navigation ───────────────────────────────────────────────────────────────
function renderNav($activePage='') {
    $user = $_SESSION['user'] ?? [];
    $role = $user['role']     ?? 'tourist';
    $name = htmlspecialchars($user['name'] ?? 'User');

    $pages = [
        'home'        => ['href'=>'index.php',       'label'=>'Home'],
        'popular'     => ['href'=>'popular.php',     'label'=>'Popular'],
        'recommended' => ['href'=>'recommended.php', 'label'=>'Recommended'],
    ];
    if ($role === 'admin') {
        $pages['dashboard'] = ['href'=>'admin_dashboard.php','label'=>'Dashboard'];
        $pages['reports']   = ['href'=>'reports.php',        'label'=>'Reports'];
    }

    ob_start(); ?>
<nav class="main-nav">
  <div class="nav-container">
    <a href="index.php" class="logo">VISTA<span>Rizal</span></a>
    <form class="nav-search" action="search.php" method="GET">
      <input type="text" name="q" placeholder="Search attractions…"
             value="<?= htmlspecialchars($_GET['q']??'') ?>" autocomplete="off">
      <button type="submit" aria-label="Search">⌕</button>
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
        <a href="profile.php" class="dropdown-item" role="menuitem">My Profile</a>
        <button class="dropdown-item" role="menuitem" onclick="openSettingsModal()" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;padding:10px 16px;font-size:.9rem;">Settings</button>
        <a href="logout.php"  class="dropdown-item dropdown-logout" role="menuitem">Sign Out</a>
      </div>
    </div>
  </div>
</nav>
<script>
function previewDarkMode(on) {
  document.body.classList.toggle('dark-mode', on);
  document.cookie = 'dark_mode=' + (on ? '1' : '0') + '; path=/; max-age=31536000; SameSite=Lax';
}
(function(){
  var isDark = <?= !empty(($_SESSION['settings'] ?? [])['dark_mode']) ? 'true' : 'false' ?>;
  if (!isDark) {
    isDark = document.cookie.split(';').some(function(c){ return c.trim() === 'dark_mode=1'; });
  }
  if (isDark) document.body.classList.add('dark-mode');
  
})();
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

<!-- ─── Settings Modal ─────────────────────────────────────────────── -->
<div id="settingsModal" role="dialog" aria-modal="true" aria-label="Settings"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);
            align-items:center;justify-content:center;padding:16px;">
  <div id="settingsModalBox"
       style="background:var(--card-bg,#fff);border-radius:18px;width:100%;max-width:540px;
              max-height:90vh;overflow-y:auto;box-shadow:0 16px 60px rgba(0,0,0,.22);
              position:relative;padding:32px 28px 28px;">

    <!-- Close button -->
    <button onclick="closeSettingsModal()"
            style="position:absolute;top:14px;right:16px;background:none;border:none;
                   font-size:1.4rem;cursor:pointer;color:var(--text-muted,#888);line-height:1;"
            aria-label="Close settings">✕</button>

    <h2 style="margin:0 0 20px;font-size:1.25rem;">Settings</h2>

    <!-- Tab bar -->
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:22px;border-bottom:1px solid var(--border,#e2e8f0);padding-bottom:12px;">
      <?php foreach(['preferences'=>'Preferences','security'=>'Security','privacy'=>'Privacy'] as $tk=>$tl): ?>
        <button class="stab-btn" data-tab="stab-<?= $tk ?>"
                onclick="switchSettingsTab(this)"
                style="padding:7px 14px;border-radius:8px;border:1.5px solid var(--border,#e2e8f0);
                       background:none;cursor:pointer;font-size:.83rem;font-weight:600;
                       color:var(--text-muted,#666);transition:all .15s;">
          <?= $tl ?>
        </button>
      <?php endforeach; ?>
    </div>

    <?php
    $s    = $_SESSION['settings'] ?? [];
    $csrf = generateCSRF();
    $isDark = !empty($s['dark_mode']);
    ?>

    <!-- Preferences tab -->
    <div id="stab-preferences" class="stab-panel">
      <form method="POST" action="settings.php">
        <input type="hidden" name="csrf"   value="<?= $csrf ?>">
        <input type="hidden" name="action" value="save_settings">
        <input type="hidden" name="_modal" value="1">

        <?php foreach([
          ['notifications','Push Notifications','Get alerts about new attractions','notifications',true],
          ['email_alerts','Email Alerts','Receive weekly recommendations by email','email_alerts',false],
          ['show_recent','Show Recently Viewed','Display your recent attraction history on home','show_recent',true],
        ] as [$name,$title,$desc,$key,$def]): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border,#f0f0f0);">
          <div>
            <div style="font-weight:600;font-size:.9rem;"><?= $title ?></div>
            <div style="font-size:.78rem;color:var(--text-muted,#888);margin-top:2px;"><?= $desc ?></div>
          </div>
          <label class="toggle">
            <input type="checkbox" name="<?= $name ?>" <?= ($s[$key]??$def)?'checked':'' ?>>
            <span class="toggle-slider"></span>
          </label>
        </div>
        <?php endforeach; ?>

        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border,#f0f0f0);">
          <div>
            <div style="font-weight:600;font-size:.9rem;">Dark Mode</div>
            <div style="font-size:.78rem;color:var(--text-muted,#888);margin-top:2px;">Use dark theme across the entire app</div>
          </div>
          <label class="toggle">
            <input type="checkbox" name="dark_mode" id="modalDarkToggle"
                   <?= $isDark?'checked':'' ?>
                  onchange="previewDarkMode(this.checked); this.form.submit();">
            <span class="toggle-slider"></span>
          </label>
        </div>

        <div style="margin-top:16px;">
          <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:6px;">Default Category Filter</label>
          <select name="category_filter" style="width:100%;padding:9px 12px;border-radius:8px;border:1.5px solid var(--border,#e2e8f0);font-size:.88rem;background:var(--input-bg,#f9fafb);">
            <?php foreach(['all'=>'All Categories','nature'=>'Nature','cultural'=>'Cultural','adventure'=>'Adventure'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($s['category_filter']??'all')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn-primary"
                style="width:auto;padding:10px 28px;margin-top:18px;">Save Preferences</button>
      </form>
    </div>

    <!-- Security tab -->
    <div id="stab-security" class="stab-panel" style="display:none;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;">
        <?php foreach([
          ['CSRF Protection',      'Active',                    true],
          ['Brute-Force Lockout', 'Active (5 attempts)',        true],
          ['Two-Factor Auth (OTP)','Active on every login',      true],
          ['Session Timeout',       '30 min of inactivity',      true],
          ['Password Hashing',     'bcrypt, cost 12',            true],
          ['Activity Logging',     'Session-based',              true],
        ] as [$label,$status,$ok]): ?>
          <div style="background:var(--bg,#f5f7fa);border-radius:10px;padding:12px;border-left:4px solid <?= $ok?'var(--green,#2d7a4f)':'var(--gold-dark,#b7791f)' ?>;">
            <div style="font-weight:700;font-size:.82rem;"><?= $label ?></div>
            <div style="font-size:.75rem;color:var(--text-muted,#888);margin-top:2px;"><?= $status ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <h3 style="font-size:.95rem;margin-bottom:12px;border-top:1px solid var(--border,#e2e8f0);padding-top:14px;">Change Password</h3>
      <?php if(($_SESSION['user']['provider']??'local')!=='local'): ?>
        <p style="font-size:.85rem;color:var(--text-muted,#888);">OAuth accounts cannot change password here.</p>
      <?php else: ?>
      <form method="POST" action="settings.php">
        <input type="hidden" name="csrf"   value="<?= $csrf ?>">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="_modal" value="1">
        <div class="input-group"><label>Current Password</label><input type="password" name="current_password" required></div>
        <div class="input-group"><label>New Password</label><input type="password" name="new_password" required minlength="6"></div>
        <div class="input-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
        <button type="submit" class="btn-primary" style="width:auto;padding:10px 28px;">Update Password</button>
      </form>
      <?php endif; ?>

      <div style="margin-top:18px;background:var(--bg,#f5f7fa);border-radius:10px;padding:14px;">
        <div style="font-size:.85rem;color:var(--text-muted,#888);line-height:1.7;">
          Signed in as <strong><?= htmlspecialchars($_SESSION['user']['name']??'') ?></strong><br>
          Role: <strong><?= ucfirst($_SESSION['user']['role']??'tourist') ?></strong><br>
          Last active: <strong><?= date('Y-m-d H:i',$_SESSION['last_active']??time()) ?></strong>
        </div>
      </div>
    </div>

    <!-- Privacy tab -->
    <div id="stab-privacy" class="stab-panel" style="display:none;">
      <form method="POST" action="settings.php">
        <input type="hidden" name="csrf"   value="<?= $csrf ?>">
        <input type="hidden" name="action" value="save_privacy">
        <input type="hidden" name="_modal" value="1">

        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border,#f0f0f0);">
          <div>
            <div style="font-weight:600;font-size:.9rem;">Share Browse History</div>
            <div style="font-size:.78rem;color:var(--text-muted,#888);margin-top:2px;">Allow recently viewed to improve recommendations</div>
          </div>
          <label class="toggle">
            <input type="checkbox" name="share_history" <?= ($s['share_history']??true)?'checked':'' ?>>
            <span class="toggle-slider"></span>
          </label>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;">
          <div>
            <div style="font-weight:600;font-size:.9rem;">Public Reviews</div>
            <div style="font-size:.78rem;color:var(--text-muted,#888);margin-top:2px;">Your reviews are visible to other tourists and admins</div>
          </div>
          <label class="toggle"><input type="checkbox" checked disabled><span class="toggle-slider"></span></label>
        </div>

        <button type="submit" class="btn-primary" style="width:auto;padding:10px 28px;margin-top:8px;">Save Privacy Settings</button>
      </form>

      <div style="background:var(--green-pale,#f0faf4);border-radius:10px;padding:14px;margin-top:18px;border-left:4px solid var(--green,#2d7a4f);">
        <h4 style="color:var(--green,#2d7a4f);margin-bottom:8px;font-size:.9rem;">Our Privacy Commitment</h4>
        <ul style="font-size:.82rem;line-height:1.9;padding-left:16px;color:var(--text,#222);">
          <li>Your data is used only for personalized recommendations</li>
          <li>We never sell or share personal data with third parties</li>
          <li>Session data is cleared when you log out</li>
          <li>You can request data deletion at any time</li>
        </ul>
      </div>
    </div>

  </div><!-- /modal box -->
</div><!-- /modal backdrop -->

<style>
.stab-btn.active-stab{background:var(--green,#2d7a4f)!important;color:#fff!important;border-color:var(--green,#2d7a4f)!important;}
#settingsModal.open{display:flex!important;}
@media(max-width:600px){
  #settingsModalBox{padding:24px 16px 20px;border-radius:14px;}
  #settingsModalBox>div[style*="grid-template-columns"]{grid-template-columns:1fr!important;}
}
</style>
<script>
function openSettingsModal(){
  const m=document.getElementById('settingsModal');
  m.style.display='flex';
  requestAnimationFrame(()=>m.classList.add('open'));
  document.body.style.overflow='hidden';
  // activate first tab
  const first=m.querySelector('.stab-btn');
  if(first&&!m.querySelector('.stab-btn.active-stab')) switchSettingsTab(first);
}
function closeSettingsModal(){
  const m=document.getElementById('settingsModal');
  m.style.display='none';
  m.classList.remove('open');
  document.body.style.overflow='';
}
function switchSettingsTab(btn){
  document.querySelectorAll('.stab-btn').forEach(b=>b.classList.remove('active-stab'));
  document.querySelectorAll('.stab-panel').forEach(p=>p.style.display='none');
  btn.classList.add('active-stab');
  const panel=document.getElementById(btn.dataset.tab);
  if(panel) panel.style.display='block';
}
// Close on backdrop click
document.getElementById('settingsModal').addEventListener('click',function(e){
  if(e.target===this) closeSettingsModal();
});
// Close on Escape
document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeSettingsModal(); });
// Auto-open if returning from a settings save (URL param)
if(new URLSearchParams(location.search).get('settings_saved')==='1') openSettingsModal();
</script>
<?php return ob_get_clean();
}

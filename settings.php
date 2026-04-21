<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
checkSessionTimeout();

$csrf = generateCSRF();
$msg  = '';

// Save preferences
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_settings') {
    if (!verifyCSRF($_POST['csrf']??'')) { $msg='error:Invalid request.'; }
    else {
        $_SESSION['settings'] = array_merge($_SESSION['settings']??[], [
            'notifications'   => isset($_POST['notifications']),
            'email_alerts'    => isset($_POST['email_alerts']),
            'show_recent'     => isset($_POST['show_recent']),
            'dark_mode'       => isset($_POST['dark_mode']),
            'category_filter' => in_array($_POST['category_filter']??'',['all','nature','cultural','adventure'])
                                    ? $_POST['category_filter'] : 'all',
        ]);
        // If submitted from modal, redirect back with flag to reopen it
        $back = !empty($_POST['_modal']) ? '?settings_saved=1' : '?tab=preferences&saved=1';
        $_SESSION['notification'] = 'Preferences saved!';
        header('Location: index.php'.$back); exit;
    }
}

// Save privacy
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_privacy') {
    if (!verifyCSRF($_POST['csrf']??'')) { $msg='error:Invalid request.'; }
    else {
        $_SESSION['settings'] = array_merge($_SESSION['settings']??[], [
            'share_history' => isset($_POST['share_history']),
        ]);
        $back = !empty($_POST['_modal']) ? '?settings_saved=1' : '?tab=privacy&saved=1';
        $_SESSION['notification'] = 'Privacy settings saved!';
        header('Location: index.php'.$back); exit;
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='change_password') {
    if (!verifyCSRF($_POST['csrf']??'')) { $msg='error:Invalid request.'; }
    else {
        $userId = $_SESSION['user']['id'] ?? 0;
        $cur    = $_POST['current_password'] ?? '';
        $new    = $_POST['new_password']     ?? '';
        $con    = $_POST['confirm_password'] ?? '';

        if (($_SESSION['user']['provider']??'local') !== 'local') {
            $msg = 'error:OAuth accounts cannot change password here.';
        } elseif (strlen($new) < 6) {
            $msg = 'error:New password must be at least 6 characters.';
        } elseif ($new !== $con) {
            $msg = 'error:Passwords do not match.';
        } else {
            $dbUser = getUserById($userId);
            if (!$dbUser || !password_verify($cur, $dbUser['password_hash'])) {
                $msg = 'error:Current password is incorrect.';
            } else {
                changePassword($userId, $new);
                $back = !empty($_POST['_modal']) ? '?settings_saved=1' : '?tab=security&saved=1';
                $_SESSION['notification'] = 'Password changed successfully!';
                header('Location: index.php'.$back); exit;
            }
        }
    }
}

$s   = $_SESSION['settings'] ?? [];
$tab = $_GET['tab'] ?? 'preferences';
// Only valid tabs (no sandbox)
$tabs = [
    'preferences' => '⚙️ Preferences',
    'security'    => '🔒 Security',
    'privacy'     => '🛡️ Privacy',
];
if (!array_key_exists($tab, $tabs)) $tab = 'preferences';

$isDark = !empty($s['dark_mode']);
?>
<!DOCTYPE html>
<html lang="en"<?= darkModeAttr() ?>>
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Settings – VISTA-Rizal</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?= renderNav('') ?>
<main class="container">
  <h1 style="font-size:1.6rem;margin-bottom:20px;">⚙️ Settings</h1>

  <?php if($msg): ?>
    <?php [$type,$text] = explode(':',$msg,2); ?>
    <div class="notification <?= $type ?> fade-in"><?= htmlspecialchars($text) ?></div>
  <?php endif; ?>
  <?php if(isset($_GET['saved'])): ?>
    <div class="notification success fade-in">✅ Settings saved successfully!</div>
  <?php endif; ?>

  <div class="settings-grid">
    <div class="settings-sidebar">
      <ul>
        <?php foreach($tabs as $key=>$label): ?>
          <li><a href="settings.php?tab=<?= $key ?>" class="<?= $tab===$key?'active':'' ?>"><?= $label ?></a></li>
        <?php endforeach; ?>
        <li style="border-top:1px solid var(--border);margin-top:8px;padding-top:8px;">
          <a href="profile.php">👤 My Profile</a>
        </li>
      </ul>
    </div>

    <div class="settings-panel">

      <?php if($tab==='preferences'): ?>
        <h2>Preferences</h2>
        <form method="POST">
          <input type="hidden" name="csrf"   value="<?= $csrf ?>">
          <input type="hidden" name="action" value="save_settings">

          <div class="toggle-row">
            <div class="toggle-info">
              <strong>Push Notifications</strong>
              <small>Get alerts about new attractions and updates</small>
            </div>
            <label class="toggle">
              <input type="checkbox" name="notifications" <?= ($s['notifications']??true)?'checked':'' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="toggle-row">
            <div class="toggle-info">
              <strong>Email Alerts</strong>
              <small>Receive weekly recommendations by email</small>
            </div>
            <label class="toggle">
              <input type="checkbox" name="email_alerts" <?= ($s['email_alerts']??false)?'checked':'' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="toggle-row">
            <div class="toggle-info">
              <strong>Show Recently Viewed</strong>
              <small>Display your recent attraction history on home</small>
            </div>
            <label class="toggle">
              <input type="checkbox" name="show_recent" <?= ($s['show_recent']??true)?'checked':'' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="toggle-row">
            <div class="toggle-info">
              <strong>Dark Mode</strong>
              <small>Use dark theme across the entire app</small>
            </div>
            <label class="toggle">
              <input type="checkbox" name="dark_mode" id="darkModeToggle"
                     <?= $isDark?'checked':'' ?>
                     onchange="previewDarkMode(this.checked)">
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="input-group" style="margin-top:20px;">
            <label>Default Category Filter</label>
            <select name="category_filter">
              <?php foreach(['all'=>'All Categories','nature'=>'🌿 Nature','cultural'=>'🏛 Cultural','adventure'=>'⛰ Adventure'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= ($s['category_filter']??'all')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
            <small style="color:var(--text-muted);font-size:.78rem;">Default filter on the Explore page.</small>
          </div>

          <button type="submit" class="btn-primary" style="width:auto;padding:10px 28px;margin-top:8px;">
            💾 Save Preferences
          </button>
        </form>

      <?php elseif($tab==='security'): ?>
        <h2>Security</h2>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px;">
          <?php foreach([
            ['🔒 CSRF Protection',      'Active',                    true],
            ['🛡️ Brute-Force Lockout', 'Active (5 attempts)',        true],
            ['📲 Two-Factor Auth (OTP)','Active on every login',      true],
            ['⏱ Session Timeout',       '30 minutes of inactivity',  true],
            ['🔐 Password Hashing',     'bcrypt, cost 12',            true],
            ['📋 Activity Logging',     'Session-based',              true],
          ] as [$label,$status,$ok]): ?>
            <div style="background:var(--bg);border-radius:10px;padding:14px;border-left:4px solid <?= $ok?'var(--green)':'var(--gold-dark)' ?>;">
              <div style="font-weight:700;font-size:.88rem;"><?= $label ?></div>
              <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px;"><?= $status ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <h3 style="margin-bottom:16px;border-top:1px solid var(--border);padding-top:16px;">Change Password</h3>
        <?php if(($_SESSION['user']['provider']??'local')!=='local'): ?>
          <div style="background:var(--bg);border-radius:10px;padding:14px;border-left:4px solid var(--gold-dark);margin-bottom:16px;">
            <p style="font-size:.88rem;color:var(--text-muted);">OAuth accounts cannot change password here.</p>
          </div>
        <?php else: ?>
        <form method="POST">
          <input type="hidden" name="csrf"   value="<?= $csrf ?>">
          <input type="hidden" name="action" value="change_password">
          <div class="input-group"><label>Current Password</label><input type="password" name="current_password" required></div>
          <div class="input-group"><label>New Password</label><input type="password" name="new_password" required minlength="6"></div>
          <div class="input-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
          <button type="submit" class="btn-primary" style="width:auto;padding:10px 28px;">🔑 Update Password</button>
        </form>
        <?php endif; ?>

        <div style="margin-top:24px;background:var(--bg);border-radius:10px;padding:16px;">
          <h4 style="margin-bottom:10px;">Current Session</h4>
          <p style="font-size:.85rem;color:var(--text-muted);">
            Signed in as <strong><?= htmlspecialchars($_SESSION['user']['name']) ?></strong><br>
            Role: <strong><?= ucfirst($_SESSION['user']['role']??'tourist') ?></strong><br>
            Last active: <strong><?= date('Y-m-d H:i', $_SESSION['last_active']??time()) ?></strong><br>
            Auto-logout after: <strong>30 minutes of inactivity</strong>
          </p>
          <a href="logout.php" class="btn-primary btn-sm"
             style="display:inline-block;width:auto;margin-top:10px;padding:8px 20px;background:var(--red,#e53e3e);text-decoration:none;">
            🚪 Sign Out
          </a>
        </div>

      <?php elseif($tab==='privacy'): ?>
        <h2>Privacy Settings</h2>
        <form method="POST">
          <input type="hidden" name="csrf"   value="<?= $csrf ?>">
          <input type="hidden" name="action" value="save_privacy">

          <div class="toggle-row">
            <div class="toggle-info">
              <strong>Share Browse History</strong>
              <small>Allow recently viewed attractions to improve recommendations</small>
            </div>
            <label class="toggle">
              <input type="checkbox" name="share_history" <?= ($s['share_history']??true)?'checked':'' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div class="toggle-row">
            <div class="toggle-info">
              <strong>Public Reviews</strong>
              <small>Your reviews are visible to other tourists and admins</small>
            </div>
            <label class="toggle">
              <input type="checkbox" checked disabled>
              <span class="toggle-slider"></span>
            </label>
          </div>

          <button type="submit" class="btn-primary" style="width:auto;padding:10px 28px;margin-top:8px;">
            💾 Save Privacy Settings
          </button>
        </form>

        <div style="background:var(--green-pale);border-radius:10px;padding:16px;margin-top:20px;border-left:4px solid var(--green);">
          <h4 style="color:var(--green);margin-bottom:8px;">Our Privacy Commitment</h4>
          <ul style="font-size:.85rem;line-height:1.8;padding-left:16px;color:var(--text);">
            <li>Your data is used only for personalized recommendations</li>
            <li>We never sell or share personal data with third parties</li>
            <li>Session data is cleared when you log out</li>
            <li>You can request data deletion at any time</li>
          </ul>
        </div>
        <p style="margin-top:16px;font-size:.85rem;">
          Read our full <a href="consent.php">Terms &amp; Consent</a>.
        </p>
      <?php endif; ?>

    </div>
  </div>
</main>

<script>
function previewDarkMode(on) {
  document.documentElement.setAttribute('data-theme', on ? 'dark' : '');
}
</script>

<style>
[data-theme="dark"] {
  --bg: #1a1a2e; --card-bg: #16213e; --text: #e0e0e0;
  --text-muted: #9a9ab0; --border: #2d2d4e;
  --shadow: 0 2px 12px rgba(0,0,0,.4); --green-pale: rgba(52,211,153,.08);
}
[data-theme="dark"] body { background: var(--bg); color: var(--text); }
[data-theme="dark"] .main-nav { background: var(--card-bg); border-bottom-color: var(--border); }
[data-theme="dark"] .card, [data-theme="dark"] .settings-panel,
[data-theme="dark"] .settings-sidebar { background: var(--card-bg); }
[data-theme="dark"] input, [data-theme="dark"] select, [data-theme="dark"] textarea {
  background: #0f3460; color: var(--text); border-color: var(--border);
}
</style>
</body>
</html>
<?php
include 'config.php';
require_once 'send_otp.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $role = $_SESSION['user']['role'] ?? 'tourist';
    header('Location: '.($role==='admin'?'admin_dashboard.php':'index.php')); exit;
}

$error  = '';
$csrf   = generateCSRF();
$locked = isLockedOut();

// ── Detect whether OAuth credentials are real (not placeholder) ───────────────
$googleReady   = defined('GOOGLE_CLIENT_ID')
              && !str_contains(GOOGLE_CLIENT_ID,  'paste')
              && !str_contains(GOOGLE_CLIENT_ID,  'YOUR');
$facebookReady = defined('FACEBOOK_APP_ID')
              && !str_contains(FACEBOOK_APP_ID,   'paste')
              && !str_contains(FACEBOOK_APP_ID,   'YOUR');

$googleAuthUrl = $googleReady ? 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]) : '#';

$facebookAuthUrl = $facebookReady ? 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
    'client_id'     => FACEBOOK_APP_ID,
    'redirect_uri'  => FACEBOOK_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'email,public_profile',
]) : '#';

// ── Email / password login ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='login') {
    if (!verifyCSRF($_POST['csrf']??'')) {
        $error = 'Invalid request.';
    } elseif ($locked) {
        $error = 'Account temporarily locked. Please wait '.remainingLockoutMinutes().' minute(s).';
    } else {
        $inputEmail = trim($_POST['username'] ?? '');
        $inputPass  = $_POST['password'] ?? '';

        if (empty($inputEmail) || empty($inputPass)) {
            $error = 'Please enter your credentials.';
        } else {
            $user = verifyUserPassword($inputEmail, $inputPass);

            // Fallback: hardcoded admin in case DB row not seeded yet
            if (!$user && $inputEmail === ADMIN_EMAIL && $inputPass === ADMIN_PASSWORD) {
                $user = [
                    'id'=>0,'name'=>ADMIN_NAME,'email'=>ADMIN_EMAIL,'role'=>'admin',
                    'nationality'=>'N/A','address'=>'Admin Office','birthdate'=>null,
                    'avatar_url'=>null,'oauth_provider'=>'local',
                ];
            }

            if ($user) {
                $otp = generateOTP();
                setOTP($otp);
                // buildSessionUser() expects a DB row; admin fallback is already clean
                $_SESSION['pending_login'] = isset($user['password_hash'])
                    ? buildSessionUser($user)
                    : $user;

                $result = sendOTPEmail($user['email'], $user['name'], $otp);
                if (!$result['success']) {
                    // sendOTPEmail() already stores OTP in $_SESSION['demo_otp']
                    error_log('OTP send failed: ' . ($result['error'] ?? 'unknown'));
                }
                header('Location: verify_otp.php'); exit;
            } else {
                recordFailedAttempt($inputEmail);
                $locked = isLockedOut();
                $error  = $locked
                    ? 'Account locked for '.LOCKOUT_MINUTES.' minutes due to too many failed attempts.'
                    : 'Invalid email or password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VISTA-Rizal – Sign In</title>
  <link rel="stylesheet" href="CSS\style.css">
  <style>
    .social-divider{display:flex;align-items:center;gap:10px;margin:18px 0}
    .social-divider::before,.social-divider::after{content:'';flex:1;height:1px;background:var(--border,#e2e8f0)}
    .social-divider span{font-size:.82rem;color:var(--text-muted,#888);white-space:nowrap}
    .social-btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;
                padding:11px 16px;border-radius:8px;font-size:.92rem;font-weight:600;
                border:1.5px solid var(--border,#e2e8f0);background:#fff;
                transition:background .15s,box-shadow .15s,transform .1s;
                margin-bottom:10px;color:var(--text,#222);text-decoration:none}
    .social-btn:hover{background:#f8f9fa;box-shadow:0 2px 8px rgba(0,0,0,.08);transform:translateY(-1px)}
    .social-btn.facebook{border-color:#1877f2;color:#1877f2}
    .social-btn.facebook:hover{background:#e7f0fd}
    .security-badges{display:flex;gap:6px;flex-wrap:wrap;margin-top:14px}
    .sec-badge{background:var(--green-pale,#f0faf4);color:var(--green,#2d7a4f);
               border-radius:20px;padding:3px 10px;font-size:.72rem;font-weight:600}
  </style>
</head>
<body<?= darkModeAttr() ?>>
<div class="login-container">
  <div class="login-form">
    <div class="logo-section">
      <h1>VISTA<span style="color:var(--gold-dark,#b7791f)">Rizal</span></h1>
      <p>Visitor Insights for Selecting Tourist Attractions in Rizal</p>
    </div>

    <?php if (isset($_SESSION['notification'])): ?>
      <div class="notification success">
        <?= htmlspecialchars($_SESSION['notification']); unset($_SESSION['notification']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['reason']) && $_GET['reason']==='timeout'): ?>
      <div class="notification warning"> Session expired. Please sign in again.</div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($locked): ?>
      <div class="notification error">
         Account locked. Please wait <strong><?= remainingLockoutMinutes() ?></strong> minute(s).
      </div>
    <?php endif; ?>

    <?php if ($googleReady || $facebookReady): ?>
      <?php if ($googleReady): ?>
        <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="social-btn google">
          <svg width="20" height="20" viewBox="0 0 48 48">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
          </svg>
          Continue with Google
        </a>
      <?php endif; ?>
      <?php if ($facebookReady): ?>
        <a href="<?= htmlspecialchars($facebookAuthUrl) ?>" class="social-btn facebook">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="#1877f2">
            <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/>
          </svg>
          Continue with Facebook
        </a>
      <?php endif; ?>
      <div class="social-divider"><span>or sign in with email</span></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf"   value="<?= $csrf ?>">
      <input type="hidden" name="action" value="login">
      <div class="input-group">
        <label>Email Address</label>
        <input type="email" name="username" required
               <?= $locked ? 'disabled' : '' ?>
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="input-group">
        <label>Password</label>
        <input type="password" name="password" required <?= $locked ? 'disabled' : '' ?>>
      </div>
      <button type="submit" class="login-btn" <?= $locked ? 'disabled' : '' ?>>
        Sign In →
      </button>
    </form>

    <div class="security-badges">
      <span class="sec-badge"> CSRF</span>
      <span class="sec-badge"> Lockout</span>
      <span class="sec-badge"> OTP</span>
      <span class="sec-badge"> bcrypt</span>
      <span class="sec-badge"> Timeout</span>
    </div>

    <div class="register-link">
      <p>No account yet? <a href="regform.php">Register here</a></p>
    </div>
    <div class="footer">
      <p>Tourist Satisfaction Prediction System · Province of Rizal</p>
    </div>
  </div>
</div>
</body>
</html>
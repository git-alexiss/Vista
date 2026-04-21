<?php
include 'config.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php'); exit;
}

$error = '';
$csrf  = generateCSRF();

// Build real OAuth URLs
$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);
$facebookAuthUrl = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
    'client_id'     => FACEBOOK_APP_ID,
    'redirect_uri'  => FACEBOOK_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'email,public_profile',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name        = trim($_POST['name']        ?? '');
        $email       = trim($_POST['email']       ?? '');
        $password    = $_POST['password']          ?? '';
        $address     = trim($_POST['address']     ?? '');
        $birthdate   = $_POST['birthdate']         ?? '';
        $nationality = $_POST['nationality']       ?? '';
        $role        = $_POST['role']              ?? '';
        $terms       = isset($_POST['terms']);
        $adminCode   = trim($_POST['admin_code']  ?? '');
        $adminEmail  = trim($_POST['admin_email'] ?? '');

        if (empty($name)||empty($email)||empty($password)||empty($address)||empty($birthdate)||empty($nationality)||empty($role))
            $error = 'All fields are required.';
        elseif (!$terms)
            $error = 'You must accept the Terms & Consent.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $error = 'Please enter a valid email address.';
        elseif (strlen($password) < 6)
            $error = 'Password must be at least 6 characters.';
        elseif ($role === 'admin') {
            if ($adminEmail !== ADMIN_EMAIL || $adminCode !== ADMIN_PASSWORD)
                $error = 'Invalid admin credentials.';
        }

        if (!$error) {
            // Save directly to DB with hashed password
            $newId = registerUser([
                'name'        => $name,
                'email'       => $email,
                'password'    => $password,
                'address'     => $address,
                'nationality' => $nationality,
                'birthdate'   => $birthdate,
                'role'        => $role,
            ]);

            if ($newId === false) {
                $error = 'That email is already registered. <a href="login.php">Sign in instead?</a>';
            } else {
                // Store for display.php confirmation page
                $_SESSION['pending_user'] = [
                    'name'        => sanitize($name),
                    'email'       => $email,
                    'password'    => $password,
                    'address'     => sanitize($address),
                    'nationality' => sanitize($nationality),
                    'birthdate'   => $birthdate,
                    'role'        => $role,
                ];
                header('Location: display.php'); exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>VISTA-Rizal – Register</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .social-divider{display:flex;align-items:center;gap:10px;margin:18px 0}
    .social-divider::before,.social-divider::after{content:'';flex:1;height:1px;background:var(--border)}
    .social-divider span{font-size:.82rem;color:var(--text-muted);white-space:nowrap}
    .social-btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:11px 16px;border-radius:8px;font-size:.92rem;font-weight:600;border:1.5px solid var(--border,#e2e8f0);background:#fff;transition:background .15s,box-shadow .15s,transform .1s;margin-bottom:10px;color:var(--text,#222);text-decoration:none;}
    .social-btn:hover{background:#f8f9fa;box-shadow:0 2px 8px rgba(0,0,0,.08);transform:translateY(-1px)}
    .social-btn:active{transform:translateY(0)}
    .social-btn.facebook{border-color:#1877f2;color:#1877f2}
    .social-btn.facebook:hover{background:#e7f0fd}
    .admin-credentials-box{background:#fff8e7;border:1.5px solid var(--gold);border-radius:10px;padding:16px;margin-top:12px;animation:fadeIn .3s ease}
    .admin-credentials-box h4{color:var(--gold-dark);font-size:.9rem;margin-bottom:10px}
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000;align-items:center;justify-content:center;padding:20px}
    .modal-overlay.open{display:flex}
    .modal-box{background:#fff;border-radius:16px;max-width:560px;width:100%;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.3);animation:fadeIn .25s ease}
    .modal-header{padding:20px 24px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
    .modal-header h3{font-size:1.1rem;color:var(--green)}
    .modal-close{background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted)}
    .modal-body{padding:20px 24px;overflow-y:auto;flex:1;font-size:.88rem;line-height:1.75;color:var(--text)}
    .modal-body h4{color:var(--green);margin:16px 0 6px;font-size:.92rem}
    .modal-body h4:first-child{margin-top:0}
    .modal-body ul{padding-left:18px}
    .modal-body li{margin-bottom:4px}
    .modal-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px}
    .terms-link-btn{background:none;border:none;color:var(--green);font-weight:600;font-size:.88rem;cursor:pointer;text-decoration:underline;padding:0}
    .error-message a{color:#c53030;font-weight:600}
  </style>
</head>
<body>
<div class="auth-container">
  <div class="auth-card">
    <div class="logo-section">
      <h1>VISTA<span style="color:var(--gold-dark)">Rizal</span></h1>
      <p>Create your account to start exploring</p>
    </div>

    <!-- Real Google OAuth -->
    <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="social-btn google">
      <svg width="20" height="20" viewBox="0 0 48 48">
        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
      </svg>
      Continue with Google
    </a>

    <!-- Real Facebook OAuth -->
    <a href="<?= htmlspecialchars($facebookAuthUrl) ?>" class="social-btn facebook">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="#1877f2">
        <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/>
      </svg>
      Continue with Facebook
    </a>

    <div class="social-divider"><span>or register with email</span></div>

    <form method="POST" class="auth-form">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">

      <?php if ($error): ?>
        <div class="error-message"><?= $error ?></div>
      <?php endif; ?>

      <div class="input-group">
        <label>Full Name</label>
        <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name']??'') ?>">
      </div>
      <div class="input-group">
        <label>Email Address</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email']??'') ?>">
      </div>
      <div class="input-group">
        <label>Password <small style="color:var(--text-muted)">(min. 6 characters)</small></label>
        <input type="password" name="password" required minlength="6">
      </div>
      <div class="input-group">
        <label>Address</label>
        <input type="text" name="address" required value="<?= htmlspecialchars($_POST['address']??'') ?>">
      </div>
      <div class="input-row">
        <div class="input-group half">
          <label>Birthdate</label>
          <input type="date" name="birthdate" required value="<?= htmlspecialchars($_POST['birthdate']??'') ?>">
        </div>
        <div class="input-group half">
          <label>Nationality</label>
          <select name="nationality" required>
            <option value="">Select</option>
            <option value="Local"    <?= ($_POST['nationality']??'')==='Local'    ?'selected':'' ?>>Local</option>
            <option value="Foreigner"<?= ($_POST['nationality']??'')==='Foreigner'?'selected':'' ?>>Foreigner</option>
          </select>
        </div>
      </div>

      <div class="input-group">
        <label>Account Type</label>
        <div class="radio-group">
          <label class="radio-label">
            <input type="radio" name="role" value="tourist"
                   <?= ($_POST['role']??'tourist')==='tourist'?'checked':'' ?>
                   onchange="toggleAdminBox(false)">
            <span>🧳 Tourist</span>
          </label>
          <label class="radio-label">
            <input type="radio" name="role" value="admin"
                   <?= ($_POST['role']??'')==='admin'?'checked':'' ?>
                   onchange="toggleAdminBox(true)">
            <span>🔐 Admin</span>
          </label>
        </div>
      </div>

      <div class="admin-credentials-box" id="adminBox"
           style="display:<?= ($_POST['role']??'')==='admin'?'block':'none' ?>">
        <h4>🔑 Admin Verification Required</h4>
        <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:12px;">
          Enter the authorized admin email and access code to proceed.
        </p>
        <div class="input-group">
          <label>Admin Email</label>
          <input type="email" name="admin_email" placeholder="admin@vista-rizal.ph"
                 value="<?= htmlspecialchars($_POST['admin_email']??'') ?>">
        </div>
        <div class="input-group">
          <label>Admin Access Code</label>
          <input type="password" name="admin_code" placeholder="Enter access code">
        </div>
      </div>

      <div class="terms-group">
        <label class="terms-label">
          <input type="checkbox" name="terms" id="termsCheck"
                 <?= isset($_POST['terms'])?'checked':'' ?> required>
          <span>I agree to the
            <button type="button" class="terms-link-btn" onclick="openTermsModal()">Terms &amp; Consent</button>
          </span>
        </label>
      </div>

      <button type="submit" class="login-btn">Create Account</button>
    </form>

    <div class="auth-link">
      <p>Already have an account? <a href="login.php">Sign in here</a></p>
    </div>
  </div>
</div>

<!-- Terms Modal -->
<div class="modal-overlay" id="termsModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3>📋 Terms &amp; Consent</h3>
      <button class="modal-close" onclick="closeTermsModal()">✕</button>
    </div>
    <div class="modal-body">
      <h4>1. Account Responsibility</h4>
      <ul>
        <li>Provide accurate and truthful information when signing up</li>
        <li>Keep your login credentials private and secure</li>
        <li>You are responsible for all activity under your account</li>
      </ul>
      <h4>2. Proper Use</h4>
      <ul>
        <li>Post honest and respectful ratings and reviews</li>
        <li>Avoid offensive, harmful, or misleading content</li>
        <li>Do not misuse or attempt to exploit the system</li>
      </ul>
      <h4>3. Data &amp; Privacy</h4>
      <ul>
        <li>Your personal data is stored securely in our database</li>
        <li>Passwords are hashed using bcrypt — never stored in plain text</li>
        <li>Data is used only for recommendations and system features</li>
        <li>We do not sell or share your data with third parties</li>
      </ul>
      <h4>4. Security</h4>
      <ul>
        <li>Two-factor authentication (OTP via email) is required at login</li>
        <li>Brute-force lockout and session timeout protect your account</li>
      </ul>
      <h4>5. Agreement</h4>
      <p>By registering, you confirm that you have read and agree to these terms.</p>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeTermsModal()">Close</button>
      <button class="btn-primary" style="width:auto;padding:10px 24px;"
              onclick="acceptTerms()">✅ I Agree</button>
    </div>
  </div>
</div>

<script>
function toggleAdminBox(show){document.getElementById('adminBox').style.display=show?'block':'none';}
function openTermsModal(){document.getElementById('termsModal').classList.add('open');document.body.style.overflow='hidden';}
function closeTermsModal(){document.getElementById('termsModal').classList.remove('open');document.body.style.overflow='';}
function acceptTerms(){document.getElementById('termsCheck').checked=true;closeTermsModal();}
document.getElementById('termsModal').addEventListener('click',function(e){if(e.target===this)closeTermsModal();});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeTermsModal();});
</script>
</body>
</html>
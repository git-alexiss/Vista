<?php
include 'config.php';

if (!isset($_SESSION['pending_user'])||empty($_SESSION['pending_user']['name'])) {
    header('Location: regform.php'); exit;
}

$error = '';
$csrf  = generateCSRF();

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='confirm_register') {
    if (!verifyCSRF($_POST['csrf']??'')) {
        $error = 'Invalid request.';
    } else {
        $pending = $_SESSION['pending_user'];

        // registerUser() in db.php handles password_hash() internally
        $newId = registerUser($pending);

        if ($newId === false) {
            $error = 'This email address is already registered. Please <a href="login.php">sign in</a>.';
        } else {
            unset($_SESSION['pending_user']);
            $_SESSION['notification'] = 'Account created successfully! Please sign in.';
            header('Location: login.php'); exit;
        }
    }
}

$user = $_SESSION['pending_user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>VISTA-Rizal – Review Registration</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-container">
  <div class="auth-card">
    <div class="logo-section">
      <h1>VISTA<span style="color:var(--gold-dark)">Rizal</span></h1>
      <p>Review your details before confirming</p>
    </div>

    <?php if($error): ?>
      <div class="error-message"><?= $error ?></div>
    <?php endif; ?>

    <!-- Security notice -->
    <div style="background:var(--green-pale);border-radius:10px;padding:12px 16px;
                margin-bottom:20px;font-size:.83rem;color:var(--green);border-left:4px solid var(--green);">
      Your password will be securely hashed using <strong>bcrypt</strong> before being stored in the database.
      It is never saved in plain text.
    </div>

    <div class="profile-card" style="box-shadow:none;padding:0;margin-bottom:24px;">
      <?php foreach([
        'Full Name'    => $user['name'],
        'Email'        => $user['email'],
        'Address'      => $user['address'],
        'Nationality'  => $user['nationality'],
        'Birthdate'    => $user['birthdate'],
        'Account Type' => ucfirst($user['role']),
      ] as $label => $value): ?>
        <div class="profile-item">
          <span class="profile-label"><?= htmlspecialchars($label) ?></span>
          <span class="profile-value"><?= htmlspecialchars($value) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="display:flex;gap:12px;">
      <a href="regform.php" class="btn-secondary" style="flex:1;text-align:center;padding:12px;">
        Edit
      </a>
      <form method="POST" style="flex:1;">
        <input type="hidden" name="csrf"   value="<?= $csrf ?>">
        <input type="hidden" name="action" value="confirm_register">
        <button type="submit" class="login-btn"> Confirm &amp; Register</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
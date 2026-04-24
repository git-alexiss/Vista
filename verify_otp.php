<?php
include 'config.php';
require_once 'send_otp.php';

if (empty($_SESSION['pending_login']) || empty($_SESSION['otp'])) {
    header('Location: login.php'); exit;
}
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $role = $_SESSION['user']['role'] ?? 'tourist';
    header('Location: '.($role==='admin'?'admin_dashboard.php':'index.php')); exit;
}

$error   = '';
$success = '';
$csrf    = generateCSRF();

// Verify OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'verify_otp') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $otp = implode('', array_map(fn($i) => trim($_POST["otp$i"] ?? ''), range(1, 6)));
        if (strlen($otp) !== 6 || !ctype_digit($otp)) {
            $error = 'Please enter all 6 digits.';
        } elseif (verifyOTP($otp)) {
            clearOTP();
            resetLoginAttempts();
            $pending = $_SESSION['pending_login'];
            $_SESSION['logged_in']   = true;
            $_SESSION['user']        = $pending;
            $_SESSION['last_active'] = time();
            unset($_SESSION['pending_login'], $_SESSION['demo_otp'], $_SESSION['otp_send_error']);
            $_SESSION['notification'] = 'Welcome back to VISTA-Rizal!';
            header('Location: '.($pending['role']==='admin'?'admin_dashboard.php':'index.php')); exit;
        } else {
            if (isset($_SESSION['otp']) && time() > $_SESSION['otp']['expires']) {
                $error = 'Your OTP has expired. Please <a href="login.php">sign in again</a> to get a new code.';
            } elseif (isset($_SESSION['otp']) && $_SESSION['otp']['attempts'] >= 3) {
                $error = 'Too many incorrect attempts. Please <a href="login.php">sign in again</a>.';
                unset($_SESSION['pending_login'], $_SESSION['otp'], $_SESSION['demo_otp'], $_SESSION['otp_send_error']);
            } else {
                $left  = max(0, 3 - ($_SESSION['otp']['attempts'] ?? 0));
                $error = "Incorrect OTP. $left attempt(s) remaining.";
            }
        }
    }
}

// Resend OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'resend_otp') {
    if (!verifyCSRF($_POST['csrf'] ?? '')) {
        $error = 'Invalid request.';
    } elseif (!empty($_SESSION['pending_login'])) {
        $newOtp = generateOTP();
        setOTP($newOtp);
        $pending = $_SESSION['pending_login'];
        $result  = sendOTPEmail($pending['email'], $pending['name'], $newOtp);
        if ($result['success']) {
            unset($_SESSION['demo_otp'], $_SESSION['otp_send_error']);
            $success = 'A new OTP has been sent to your email.';
        } else {
            error_log('Brevo resend failed: ' . $result['error']);
            $_SESSION['demo_otp']      = $newOtp;
            $_SESSION['otp_send_error'] = true;
            $success = 'Could not send email. See demo code below.';
        }
    }
}

$pending     = $_SESSION['pending_login'];
$maskedEmail = '';
if (!empty($pending['email'])) {
    [$name, $domain] = explode('@', $pending['email']) + ['', ''];
    $maskedEmail = substr($name, 0, 2).str_repeat('*', max(0, strlen($name)-2)).'@'.$domain;
}
$expiresIn = isset($_SESSION['otp']['expires']) ? max(0, $_SESSION['otp']['expires'] - time()) : 300;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VISTA-Rizal – Verify OTP</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .otp-container{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg,#f5f7fa);padding:20px}
    .otp-card{background:var(--card-bg,#fff);border-radius:20px;padding:40px 36px;max-width:440px;width:100%;box-shadow:0 8px 40px rgba(0,0,0,.10);text-align:center}
    .otp-logo{font-size:1.6rem;font-weight:800;color:var(--blue,#4D7298);margin-bottom:6px;letter-spacing:-.5px}
    .otp-logo span{color:var(--green-dark,#9fd865)}
    .otp-icon{width:72px;height:72px;background:linear-gradient(135deg,#e8f5ec,#c8e6d0);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:20px auto 16px;font-size:2rem}
    .otp-card h2{font-size:1.35rem;color:var(--text,#1a1a1a);margin:0 0 8px}
    .subtitle{font-size:.88rem;color:var(--text-muted,#666);line-height:1.6;margin-bottom:24px}
    .subtitle strong{color:var(--blue,#4D7298)}
    .demo-otp-box{background:#fff8e7;border:1.5px solid var(--green,#D0EFB1);border-radius:10px;padding:14px 18px;margin-bottom:22px;font-size:.84rem;color:#7b5c00}
    .demo-code{font-size:2rem;font-weight:800;letter-spacing:8px;color:var(--blue,#4D7298);display:block;margin:6px 0 4px}
    .send-error{background:#fff5f5;border:1px solid #ffc9c9;color:#c53030;border-radius:8px;padding:10px 14px;font-size:.83rem;margin-bottom:14px}
    .otp-inputs{display:flex;gap:10px;justify-content:center;margin-bottom:22px}
    .otp-inputs input{width:52px;height:60px;text-align:center;font-size:1.5rem;font-weight:700;border:2px solid var(--border,#e2e8f0);border-radius:12px;background:var(--input-bg,#f9fafb);color:var(--text,#1a1a1a);outline:none;transition:border-color .2s,box-shadow .2s;-moz-appearance:textfield}
    .otp-inputs input::-webkit-outer-spin-button,.otp-inputs input::-webkit-inner-spin-button{-webkit-appearance:none}
    .otp-inputs input:focus{border-color:var(--blue,#4D7298);box-shadow:0 0 0 3px rgba(45,122,79,.15);background:#fff}
    .otp-inputs input.filled{border-color:var(--blue,#4D7298);background:#f0faf4}
    .otp-error{background:#fff5f5;border:1px solid #ffc9c9;color:#c53030;border-radius:8px;padding:10px 14px;font-size:.85rem;margin-bottom:16px;text-align:left}
    .otp-error a{color:#c53030;font-weight:600}
    .otp-success{background:#f0faf4;border:1px solid #9ae6b4;color:#276749;border-radius:8px;padding:10px 14px;font-size:.85rem;margin-bottom:16px}
    .otp-btn{width:100%;padding:14px;background:var(--blue,#4D7298);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;transition:background .2s,transform .1s;margin-bottom:16px}
    .otp-btn:hover{background:#235f3d;transform:translateY(-1px)}
    .otp-timer{font-size:.83rem;color:var(--text-muted,#888);margin-bottom:12px}
    #countdown{font-weight:700;color:var(--blue,#4D7298)}
    #countdown.expired{color:#c53030}
    .otp-actions{display:flex;justify-content:space-between;align-items:center;font-size:.84rem;margin-top:8px}
    .otp-actions a,.otp-actions button{color:var(--blue,#4D7298);font-weight:600;background:none;border:none;cursor:pointer;padding:0;font-size:.84rem;text-decoration:none}
    .otp-actions a:hover,.otp-actions button:hover{text-decoration:underline}
    .otp-actions button:disabled{color:var(--text-muted,#aaa);cursor:not-allowed;text-decoration:none}
    .footer-note{margin-top:28px;font-size:.75rem;color:var(--text-muted,#aaa)}
  </style>
</head>
<body>
<div class="otp-container">
  <div class="otp-card">
    <div class="otp-logo">VISTA<span>Rizal</span></div>
    <div class="otp-icon"></div>
    <h2>Two-Factor Verification</h2>
    <p class="subtitle">
      We've sent a 6-digit code to<br>
      <strong><?= htmlspecialchars($maskedEmail) ?></strong><br>
      Enter it below to complete sign-in.
    </p>

    <?php if(!empty($_SESSION['otp_send_error'])): ?>
      <div class="send-error"> Email could not be sent. Use the demo code below instead.</div>
    <?php endif; ?>

    <?php if(isset($_SESSION['demo_otp'])): ?>
      <div class="demo-otp-box">
        <em>Demo mode — your OTP:</em>
        <span class="demo-code"><?= htmlspecialchars($_SESSION['demo_otp']) ?></span>
        <small>In production, this is sent via email.</small>
      </div>
    <?php endif; ?>

    <?php if($error): ?><div class="otp-error"><?= $error ?></div><?php endif; ?>
    <?php if($success): ?><div class="otp-success"> <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST" id="otpForm">
      <input type="hidden" name="csrf"   value="<?= $csrf ?>">
      <input type="hidden" name="action" value="verify_otp">
      <div class="otp-inputs">
        <?php for($i=1;$i<=6;$i++): ?>
          <input type="text" name="otp<?= $i ?>" id="otp<?= $i ?>"
                 maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" required>
        <?php endfor; ?>
      </div>
      <div class="otp-timer">Code expires in <span id="countdown"><?= gmdate('i:s', $expiresIn) ?></span></div>
      <button type="submit" class="otp-btn" id="verifyBtn"> Verify &amp; Sign In</button>
    </form>

    <div class="otp-actions">
      <a href="login.php">← Back to login</a>
      <form method="POST" style="display:inline;">
        <input type="hidden" name="csrf"   value="<?= $csrf ?>">
        <input type="hidden" name="action" value="resend_otp">
        <button type="submit" id="resendBtn" disabled>Resend OTP</button>
      </form>
    </div>

    <div class="footer-note">Tourist Satisfaction Prediction System · Province of Rizal</div>
  </div>
</div>
<script>
(function(){
  const inputs=[...document.querySelectorAll('.otp-inputs input')];
  inputs.forEach((el,i)=>{
    el.addEventListener('input',()=>{
      el.value=el.value.replace(/\D/g,'').slice(0,1);
      if(el.value&&inputs[i+1])inputs[i+1].focus();
      el.classList.toggle('filled',el.value!=='');
      checkFilled();
    });
    el.addEventListener('keydown',e=>{
      if(e.key==='Backspace'&&!el.value&&inputs[i-1]){inputs[i-1].focus();inputs[i-1].value='';inputs[i-1].classList.remove('filled');}
      if(e.key==='ArrowLeft'&&inputs[i-1])inputs[i-1].focus();
      if(e.key==='ArrowRight'&&inputs[i+1])inputs[i+1].focus();
    });
  });
  inputs[0].addEventListener('paste',e=>{
    e.preventDefault();
    const text=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
    text.split('').forEach((ch,i)=>{if(inputs[i]){inputs[i].value=ch;inputs[i].classList.add('filled');}});
    const nx=inputs.findIndex(el=>!el.value);
    (inputs[nx]||inputs[5]).focus();
    checkFilled();
  });
  function checkFilled(){document.getElementById('verifyBtn').style.opacity=inputs.every(el=>el.value)? '1':'0.7';}
  checkFilled();
  inputs[0].focus();
})();

(function(){
  let secs=<?= $expiresIn ?>;
  const display=document.getElementById('countdown');
  const resend=document.getElementById('resendBtn');
  let elapsed=0;
  const t=setInterval(()=>{
    secs--;elapsed++;
    if(secs<=0){clearInterval(t);display.textContent='Expired';display.classList.add('expired');document.getElementById('verifyBtn').disabled=true;resend.disabled=false;return;}
    const m=String(Math.floor(secs/60)).padStart(2,'0');
    const s=String(secs%60).padStart(2,'0');
    display.textContent=m+':'+s;
    if(elapsed>=30)resend.disabled=false;
  },1000);
})();
</script>
</body>
</html>
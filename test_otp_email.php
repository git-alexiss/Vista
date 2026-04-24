<?php
/**
 * test_otp_email.php
 * Run this ONCE to diagnose why emails aren't sending.
 * DELETE THIS FILE from your server after you're done!
 */

// ─── PUT YOUR REAL VALUES HERE ────────────────────────────────────────────────
$BREVO_API_KEY   = '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';   // Your new Brevo API key
$FROM_EMAIL      = 'alexisojascastro95@gmail.com'; // e.g. yourname@gmail.com
$FROM_NAME       = 'Vista Rizal';
$TEST_SEND_TO    = 'ojascastroalexis@gmail.com';        // Where to send the test
// ─────────────────────────────────────────────────────────────────────────────

$results = [];

// CHECK 1: cURL installed?
$results['curl_installed'] = function_exists('curl_init')
    ? '✅ cURL is available'
    : '❌ cURL is NOT installed — contact your hosting provider';

// CHECK 2: API key looks valid?
$results['api_key'] = (strlen($BREVO_API_KEY) > 20 && strpos($BREVO_API_KEY, 'PASTE') === false)
    ? '✅ API key is set'
    : '❌ API key is still a placeholder — paste your real Brevo key above';

// CHECK 3: From email set?
$results['from_email'] = (filter_var($FROM_EMAIL, FILTER_VALIDATE_EMAIL) && strpos($FROM_EMAIL, 'PASTE') === false)
    ? '✅ From email is set: ' . $FROM_EMAIL
    : '❌ From email is still a placeholder — paste your verified sender email';

// CHECK 4: Try sending a real test email via Brevo
$payload = json_encode([
    'sender'      => ['name' => $FROM_NAME, 'email' => $FROM_EMAIL],
    'to'          => [['email' => $TEST_SEND_TO, 'name' => 'Test Recipient']],
    'subject'     => '✅ VISTA-Rizal OTP Test Email',
    'htmlContent' => '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:32px;background:#f0faf4;border-radius:12px;">
            <h2 style="color:#2d7a4f;">✅ Brevo is working!</h2>
            <p>Your VISTA-Rizal OTP email system is connected correctly.</p>
            <div style="background:#fff;border:2px dashed #38a169;border-radius:10px;padding:20px;text-align:center;margin:20px 0;">
                <span style="font-size:.8rem;color:#888;display:block;">Sample OTP</span>
                <span style="font-size:2.5rem;font-weight:800;letter-spacing:10px;color:#2d7a4f;">123456</span>
            </div>
            <p style="font-size:.8rem;color:#999;">Sent from VISTA-Rizal diagnostic tool. Delete test_otp_email.php after confirming.</p>
        </div>',
    'textContent' => 'Brevo is working! Your VISTA-Rizal OTP email system is connected.',
]);

$sendResult  = null;
$httpCode    = null;
$apiResponse = null;

if (function_exists('curl_init') && strpos($BREVO_API_KEY, 'PASTE') === false && filter_var($FROM_EMAIL, FILTER_VALIDATE_EMAIL)) {
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . $BREVO_API_KEY,
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $apiResponse = curl_exec($ch);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr     = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        $sendResult = '❌ cURL error: ' . $curlErr;
    } elseif ($httpCode === 201) {
        $sendResult = '✅ Email sent successfully! Check your inbox at: ' . $TEST_SEND_TO;
    } elseif ($httpCode === 401) {
        $sendResult = '❌ API key is invalid or expired (HTTP 401). Generate a new key in Brevo.';
    } elseif ($httpCode === 400) {
        $decoded = json_decode($apiResponse, true);
        $msg = $decoded['message'] ?? $apiResponse;
        $sendResult = '❌ Bad request (HTTP 400): ' . $msg . ' — Your sender email is likely not verified in Brevo.';
    } elseif ($httpCode === 403) {
        $sendResult = '❌ Forbidden (HTTP 403): Your Brevo account may be suspended or sender not verified.';
    } else {
        $sendResult = "❌ Unexpected response (HTTP $httpCode): " . $apiResponse;
    }
} else {
    $sendResult = '⚠️ Skipped sending — fix the issues above first.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VISTA-Rizal – Brevo Email Diagnostic</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f5f7fa; padding: 40px 20px; }
    .box { max-width: 600px; margin: auto; background: #fff; border-radius: 16px;
           padding: 32px; box-shadow: 0 4px 24px rgba(0,0,0,.1); }
    h2 { color: #4D7298; margin-top: 0; }
    .check { padding: 12px 16px; border-radius: 8px; margin-bottom: 10px;
             font-size: .93rem; border-left: 4px solid #ccc; background: #fafafa; }
    .check.ok  { border-color: #77A6B6; background: #f0faf4; }
    .check.err { border-color: #e53e3e; background: #fff5f5; }
    .check.warn{ border-color: #D0EFB1; background: #fffbea; }
    .send-result { margin-top: 20px; padding: 16px; border-radius: 10px;
                   font-weight: 600; font-size: 1rem; }
    .send-result.ok  { background: #f0faf4; color: #3e5e7d; border: 1.5px solid #9DC3C2; }
    .send-result.err { background: #fff5f5; color: #c53030; border: 1.5px solid #ffc9c9; }
    pre { background: #f1f1f1; padding: 12px; border-radius: 8px; font-size:.8rem;
          overflow-x:auto; margin-top: 10px; }
    .warning-box { background: #fff8e7; border: 1.5px solid #D0EFB1; border-radius: 10px;
                   padding: 14px 18px; margin-top: 24px; font-size: .85rem; color: #9fd865; }
  </style>
</head>
<body>
<div class="box">
  <h2>VISTA-Rizal — Brevo Email Diagnostic</h2>

  <?php foreach ($results as $key => $msg): ?>
    <?php $cls = str_starts_with($msg,'✅') ? 'ok' : (str_starts_with($msg,'⚠️') ? 'warn' : 'err'); ?>
    <div class="check <?= $cls ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endforeach; ?>

  <div class="send-result <?= str_starts_with($sendResult,'✅') ? 'ok' : 'err' ?>">
    <?= htmlspecialchars($sendResult) ?>
  </div>

  <?php if ($apiResponse && $httpCode !== 201): ?>
    <p style="margin-top:16px;font-size:.85rem;color:#666;">Raw API response:</p>
    <pre><?= htmlspecialchars($apiResponse) ?></pre>
  <?php endif; ?>

  <div class="warning-box">
    <strong>Important:</strong> Delete <code>test_otp_email.php</code> from your server immediately after testing!
    This file contains your API key in plain text.
  </div>
</div>
</body>
</html>
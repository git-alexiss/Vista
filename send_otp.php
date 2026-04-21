<?php
/**
 * send_otp.php
 * Sends OTP emails via Brevo (Sendinblue) API.
 * Include this file wherever you need to send an OTP.
 *
 * Usage:
 *   require_once 'send_otp.php';
 *   $result = sendOTPEmail('recipient@email.com', 'Recipient Name', '123456');
 *   if ($result['success']) { ... } else { error_log($result['error']); }
 */

// ─── BREVO CONFIG ──────────────────────────────────────────────────────────────
// !! IMPORTANT: Replace with your NEW key after rotating the exposed one !!
define('BREVO_API_KEY', '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');

// The "From" address must be a verified sender in your Brevo account
define('BREVO_FROM_EMAIL', 'alexisojascastro95@gmail.com');
define('BREVO_FROM_NAME',  'Vista Rizal');


/**
 * Send an OTP email using the Brevo Transactional Email API.
 *
 * @param string $toEmail    Recipient email address
 * @param string $toName     Recipient display name
 * @param string $otp        The 6-digit OTP code
 * @return array             ['success' => bool, 'error' => string|null]
 */
function sendOTPEmail(string $toEmail, string $toName, string $otp): array {
    $htmlBody = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 0; }
    .wrapper { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 16px;
               box-shadow: 0 4px 24px rgba(0,0,0,.08); overflow: hidden; }
    .header { background: linear-gradient(135deg, #2d7a4f, #38a169); padding: 28px 32px; text-align: center; }
    .header h1 { color: #fff; margin: 0; font-size: 22px; letter-spacing: -0.5px; }
    .header p  { color: rgba(255,255,255,.8); margin: 4px 0 0; font-size: 13px; }
    .body { padding: 32px; }
    .body p { color: #444; font-size: 15px; line-height: 1.6; margin: 0 0 16px; }
    .otp-box { background: #f0faf4; border: 2px dashed #38a169; border-radius: 12px;
               text-align: center; padding: 22px; margin: 24px 0; }
    .otp-box .label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px; }
    .otp-box .code  { font-size: 42px; font-weight: 800; letter-spacing: 12px;
                      color: #2d7a4f; display: block; margin: 8px 0 4px; }
    .otp-box .expiry { font-size: 12px; color: #e53e3e; font-weight: 600; }
    .warning { background: #fff8e7; border-left: 4px solid #d69e2e; padding: 12px 16px;
               border-radius: 6px; font-size: 13px; color: #7b5c00; margin-top: 16px; }
    .footer { background: #f9fafb; padding: 18px 32px; text-align: center;
              font-size: 12px; color: #aaa; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>VISTA<span style="color:#f6e05e">Rizal</span></h1>
      <p>Visitor Insights for Selecting Tourist Attractions in Rizal</p>
    </div>
    <div class="body">
      <p>Hi <strong>'.htmlspecialchars($toName).'</strong>,</p>
      <p>You requested to sign in to your VISTA-Rizal account. Use the one-time password (OTP) below to complete your login:</p>
      <div class="otp-box">
        <span class="label">Your One-Time Password</span>
        <span class="code">'.htmlspecialchars($otp).'</span>
        <span class="expiry">⏱ Expires in 5 minutes</span>
      </div>
      <p>If you did not attempt to log in, you can safely ignore this email. Your account remains secure.</p>
      <div class="warning">
        🔒 <strong>Never share this code</strong> with anyone. VISTA-Rizal staff will never ask for your OTP.
      </div>
    </div>
    <div class="footer">
      &copy; '.date('Y').' VISTA-Rizal · Tourist Satisfaction Prediction System · Province of Rizal
    </div>
  </div>
</body>
</html>';

    $textBody = "Hi $toName,\n\nYour VISTA-Rizal login OTP is: $otp\n\nThis code expires in 5 minutes.\n\nIf you did not request this, please ignore this email.";

    $payload = json_encode([
        'sender'      => ['name' => BREVO_FROM_NAME, 'email' => BREVO_FROM_EMAIL],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'subject'     => '🔐 Your VISTA-Rizal Login Code: ' . $otp,
        'htmlContent' => $htmlBody,
        'textContent' => $textBody,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => 'cURL error: ' . $curlError];
    }

    $decoded = json_decode($response, true);

    if ($httpCode === 201 || $httpCode === 200) {
        return ['success' => true, 'error' => null];
    }

    $errMsg = $decoded['message'] ?? $response;
    return ['success' => false, 'error' => "Brevo API error ($httpCode): $errMsg"];
}
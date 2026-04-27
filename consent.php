<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1.0\">
  <title>Terms & Consent – VISTA-Rizal</title>
  <link rel=\"stylesheet\" href=\"CSS\\style.css\">
  <style>
    .terms-container { max-width: 720px; margin: 0 auto; padding: 40px 20px; }
    .terms-container h1 { color: var(--primary); margin-bottom: 8px; }
    .terms-container h2 { color: var(--primary); font-size: 1.05rem; margin: 24px 0 8px; }
    .terms-container p, .terms-container li { line-height: 1.8; color: var(--text); font-size: .93rem; }
    .terms-container ul { padding-left: 20px; }
    .terms-container section { background: var(--card-bg); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); margin-bottom: 16px; }
  </style>
</head>
<body<?= darkModeAttr() ?>>
<div class="terms-container">
  <h1>Terms &amp; Consent</h1>
  <p style="color:var(--text-muted);margin-bottom:24px;">VISTA-Rizal · Tourist Satisfaction Prediction System · Province of Rizal</p>

  <section>
    <h2>1. Account Responsibility</h2>
    <ul>
      <li>Provide accurate and truthful information when signing up</li>
      <li>Keep your login credentials private and secure</li>
      <li>You are responsible for all activity performed under your account</li>
      <li>Notify us immediately if you suspect unauthorized access</li>
    </ul>
  </section>

  <section>
    <h2>2. Proper Use</h2>
    <p>You agree to:</p>
    <ul>
      <li>Post honest and respectful ratings and reviews</li>
      <li>Avoid offensive, harmful, or misleading content</li>
      <li>Not misuse, disrupt, or attempt to exploit the system</li>
      <li>Not impersonate other users or officials</li>
    </ul>
  </section>

  <section>
    <h2>3. Content Policy</h2>
    <ul>
      <li>Reviews and ratings you submit may be visible to other users and administrators</li>
      <li>We reserve the right to remove content that violates these rules</li>
      <li>Repeated violations may result in account suspension</li>
    </ul>
  </section>

  <section>
    <h2>4. Data &amp; Privacy</h2>
    <ul>
      <li>Your personal information is handled securely and used only for app-related purposes</li>
      <li>Data is used to generate personalized attraction recommendations</li>
      <li>We do not sell or share your data with third parties</li>
      <li>Session data is cleared upon logout in this demo environment</li>
    </ul>
  </section>

  <section>
    <h2>5. Security</h2>
    <ul>
      <li>The system uses CSRF protection, brute-force lockout, and session timeout for your safety</li>
      <li>Two-factor authentication (OTP) is required at login</li>
      <li>You may change your password at any time via Settings</li>
    </ul>
  </section>

  <section>
    <h2>6. Account Actions</h2>
    <p>We may suspend or remove accounts that break these rules or engage in fraudulent activity.</p>
  </section>

  <section>
    <h2>7. Agreement</h2>
    <p>By registering, you confirm that you have read, understood, and agree to these terms and the privacy practices described above.</p>
  </section>

  <div style="text-align:center;margin-top:28px;">
    <a href="regform.php" class="btn-primary" style="display:inline-block;width:auto;padding:12px 32px;">
      I Understand – Back to Registration
    </a>
  </div>

  <div class="footer">Tourist Satisfaction Prediction System · Province of Rizal</div>
</div>
</body>
</html>
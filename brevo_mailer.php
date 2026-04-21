<?php
/**
 * brevo_mailer.php — DEPRECATED
 *
 * This file is intentionally left empty.
 * The active OTP mailer is send_otp.php — use that instead.
 *
 * Previously this file caused fatal errors because it:
 *   1. Re-defined BREVO_API_KEY (already defined in send_otp.php)
 *   2. Re-defined sendOTPEmail() (already defined in send_otp.php)
 *   3. Referenced undefined constant BREVO_SENDER_NAME
 *
 * login.php now includes send_otp.php directly.
 */
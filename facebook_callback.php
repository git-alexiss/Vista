<?php
include 'config.php';

$code  = $_GET['code']  ?? '';
$error = $_GET['error'] ?? '';

if ($error || !$code) {
    $_SESSION['notification'] = 'Facebook login was cancelled.';
    header('Location: login.php'); exit;
}

// Exchange code for access token
$tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
    'client_id'     => FACEBOOK_APP_ID,
    'client_secret' => FACEBOOK_APP_SECRET,
    'redirect_uri'  => FACEBOOK_REDIRECT_URI,
    'code'          => $code,
]);

$tokenRes = file_get_contents($tokenUrl);
if (!$tokenRes) {
    $_SESSION['notification'] = 'Facebook authentication failed. Please try again.';
    header('Location: login.php'); exit;
}

$token       = json_decode($tokenRes, true);
$accessToken = $token['access_token'] ?? '';

if (!$accessToken) {
    $_SESSION['notification'] = 'Facebook authentication failed. Please try again.';
    header('Location: login.php'); exit;
}

// Get Facebook user profile (including picture)
$userUrl = 'https://graph.facebook.com/me?fields=id,name,email,picture.type(large)&access_token=' . urlencode($accessToken);
$userRes = file_get_contents($userUrl);

if (!$userRes) {
    $_SESSION['notification'] = 'Could not retrieve Facebook profile. Please try again.';
    header('Location: login.php'); exit;
}

$fbUser = json_decode($userRes, true);

// Save or update user in DB, then build session
$user = upsertOAuthUser(
    'facebook',
    $fbUser['id'],
    $fbUser['name']  ?? 'Facebook User',
    $fbUser['email'] ?? '',
    $fbUser['picture']['data']['url'] ?? null
);

$_SESSION['logged_in']    = true;
$_SESSION['last_active']  = time();
$_SESSION['user']         = buildSessionUser($user);
$_SESSION['notification'] = 'Signed in with Facebook!';
resetLoginAttempts();
header('Location: index.php'); exit;
<?php
include 'config.php';

$code  = $_GET['code']  ?? '';
$error = $_GET['error'] ?? '';

if ($error || !$code) {
    $_SESSION['notification'] = 'Google login was cancelled.';
    header('Location: login.php'); exit;
}

// Exchange code for access token
$tokenRes = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
    ],
]));

if (!$tokenRes) {
    $_SESSION['notification'] = 'Google authentication failed. Please try again.';
    header('Location: login.php'); exit;
}

$token       = json_decode($tokenRes, true);
$accessToken = $token['access_token'] ?? '';

if (!$accessToken) {
    $_SESSION['notification'] = 'Google authentication failed. Please try again.';
    header('Location: login.php'); exit;
}

// Get Google user profile
$userRes = file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, stream_context_create([
    'http' => ['header' => 'Authorization: Bearer ' . $accessToken],
]));

if (!$userRes) {
    $_SESSION['notification'] = 'Could not retrieve Google profile. Please try again.';
    header('Location: login.php'); exit;
}

$gUser = json_decode($userRes, true);

// Save or update user in DB, then build session
$user = upsertOAuthUser(
    'google',
    $gUser['id'],
    $gUser['name']    ?? 'Google User',
    $gUser['email']   ?? '',
    $gUser['picture'] ?? null
);

$_SESSION['logged_in']    = true;
$_SESSION['last_active']  = time();
$_SESSION['user']         = buildSessionUser($user);
$_SESSION['notification'] = 'Signed in with Google!';
resetLoginAttempts();
header('Location: index.php'); exit;
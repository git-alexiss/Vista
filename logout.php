<?php
include 'config.php';
// Preserve registered user so they can log back in
$reg = $_SESSION['registered_user'] ?? null;
session_unset();
session_destroy();
session_start();
if ($reg) $_SESSION['registered_user'] = $reg;
header('Location: login.php');
exit;
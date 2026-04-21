<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
// Clear only demo data, keep auth
unset($_SESSION['reviews'], $_SESSION['recently_viewed'], $_SESSION['settings']);
$_SESSION['notification'] = 'Demo data cleared successfully.';
header('Location: settings.php?tab=sandbox'); exit;
<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
checkSessionTimeout();
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Profile – VISTA-Rizal</title>
  <link rel="stylesheet" href="CSS\style.css">
</head>
<body>
<?= renderNav() ?>
<main class="container">
  <h1 style="font-size:1.6rem;margin-bottom:20px;">My Profile</h1>
  <div class="profile-card">
    <div class="profile-avatar-big"><?= strtoupper(substr($user['name']??'U',0,1)) ?></div>
    <div style="margin-bottom:16px;">
      <h2><?= htmlspecialchars($user['name']??'') ?></h2>
      <span class="badge badge-<?= ($user['role']??'')==='admin'?'positive':'neutral' ?>">
        <?= ucfirst($user['role']??'tourist') ?>
      </span>
    </div>
    <?php foreach([
      'Email'       => $user['email']       ?? 'N/A',
      'Address'     => $user['address']     ?? 'N/A',
      'Nationality' => $user['nationality'] ?? 'N/A',
      'Birthdate'   => $user['birthdate']   ?? 'N/A',
      'Account Role'=> ucfirst($user['role']??'tourist'),
    ] as $label=>$value): ?>
      <div class="profile-item">
        <span class="profile-label"><?= htmlspecialchars($label) ?></span>
        <span class="profile-value"><?= htmlspecialchars($value) ?></span>
      </div>
    <?php endforeach; ?>

    <div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap;">
      <a href="settings.php" class="btn-secondary"> Settings</a>
      <a href="logout.php" class="btn-primary" style="background:var(--red);width:auto;padding:10px 20px;">🚪 Sign Out</a>
    </div>
  </div>

  <?php
  $myReviews = array_filter(getReviews(), fn($r)=>$r['user']===$user['name']);
  if($myReviews): ?>
    <div class="table-card" style="margin-top:24px;">
      <h3>My Reviews (<?= count($myReviews) ?>)</h3>
      <?php foreach(array_reverse($myReviews) as $r):
        $attr = getAttractionById($r['attraction_id']); ?>
        <div class="review-card">
          <div class="rev-header">
            <a href="details.php?id=<?= $r['attraction_id'] ?>" style="font-weight:700;"><?= htmlspecialchars($attr['name']??'Unknown') ?></a>
            <span class="rev-date"><?= htmlspecialchars($r['date']) ?></span>
          </div>
          <div style="margin-bottom:4px;"><?= str_repeat('★',$r['rating']) ?> <span class="badge badge-<?= $r['sentiment'] ?>"><?= ucfirst($r['sentiment']) ?></span></div>
          <p style="font-size:.9rem;"><?= htmlspecialchars($r['text']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
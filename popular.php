<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
checkSessionTimeout();
$popular = getPopularAttractions();
?>
<!DOCTYPE html>
<html lang="en"<?= darkModeAttr() ?>>
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Popular – VISTA-Rizal</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?= renderNav('popular') ?>
<main class="container">
  <div class="hero-section fade-in">
    <h1>🔥 Popular Attractions</h1>
    <p>Top-rated destinations in Rizal Province</p>
  </div>
  <?php if(empty($popular)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
      <div style="font-size:3rem">📭</div>
      <h3 style="margin:12px 0 8px;">No ratings yet</h3>
      <p>Be the first to review an attraction!</p>
      <a href="attractions.php" class="btn-primary" style="width:auto;display:inline-block;margin-top:16px;padding:10px 24px;">Browse Attractions</a>
    </div>
  <?php else: ?>
  <div class="cards-grid">
    <?php foreach($popular as $i=>$a):
      $rating = getAttractionRating($a['id']);
    ?>
      <div class="card">
        <div style="position:relative;">
          <img src="<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['name']) ?>" onerror="this.src='images/placeholder.jpg'">
          <span style="position:absolute;top:10px;left:10px;background:var(--gold);color:var(--green);font-weight:900;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:.85rem;"><?= $i+1 ?></span>
        </div>
        <div class="card-content">
          <span class="category-badge category-<?= $a['category'] ?>"><?= ucfirst($a['category']) ?></span>
          <h3><?= htmlspecialchars($a['name']) ?></h3>
          <div class="rating">⭐ <?= $rating ?>/5</div>
          <span class="location">📍 <?= htmlspecialchars($a['municipality']) ?></span>
          <p style="font-size:.82rem;color:var(--text-muted);margin-top:4px;"><?= htmlspecialchars($a['fact']) ?></p>
          <a href="details.php?id=<?= $a['id'] ?>" class="btn-primary btn-sm">View Details</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
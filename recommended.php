<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
checkSessionTimeout();
$recommended = getRecommendations($_SESSION['recently_viewed']??[]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Recommended – VISTA-Rizal</title>
  <link rel="stylesheet" href="CSS\style.css">
</head>
<body<?= darkModeAttr() ?>>
<?= renderNav('recommended') ?>
<main class="container">
  <div class="hero-section fade-in">
    <h1>Recommended for You</h1>
    <p>Top picks you haven't explored yet</p>
  </div>
  <?php if(empty($recommended)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
      <div style="font-size:3rem"></div>
      <h3 style="margin:12px 0 8px;">You've seen it all!</h3>
      <p>Browse our <a href="attractions.php">complete list</a> to revisit your favorites.</p>
    </div>
  <?php else: ?>
    <div class="cards-grid">
      <?php foreach($recommended as $a):
        $rating = getAttractionRating($a['id']);
      ?>
        <div class="card">
          <img src="<?= htmlspecialchars(getAttractionImage($a['name'])) ?>" alt="<?= htmlspecialchars($a['name']) ?>" onerror="this.src='images/placeholder.jpg'">
          <div class="card-content">
            <span class="category-badge category-<?= $a['category'] ?>"><?= ucfirst($a['category']) ?></span>
            <h3><?= htmlspecialchars($a['name']) ?></h3>
            <div class="rating">
              <?= $rating ? '★'.$rating.'/5' : '<span style="color:var(--text-muted);font-size:.82rem;">No ratings yet</span>' ?>
            </div>
            <span class="location"> <?= htmlspecialchars($a['municipality']) ?></span>
            <p style="font-size:.82rem;color:var(--text-muted);margin-top:4px;"><?= htmlspecialchars($a['fact']) ?></p>
            <a href="details.php?id=<?= $a['id'] ?>" class="btn-primary btn-sm">View Details</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<div class="footer">Tourist Satisfaction Prediction System · Province of Rizal</div>
<script>
window.addEventListener('DOMContentLoaded', function() {
  if (document.body.classList.contains('dark-mode')) {
    document.body.style.opacity = '1';
  }
});
</script>
</body>
</html>
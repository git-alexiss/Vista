<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
checkSessionTimeout();

$muniName = trim($_GET['name'] ?? '');
$all      = getAttractions();
$attractions = array_values(array_filter($all, fn($a) => $a['municipality'] === $muniName));
if (empty($attractions)) { header('Location: index.php'); exit; }

// Compute avg from real reviews
$allRatings = [];
foreach($attractions as $a) {
    $r = getAttractionRating($a['id']);
    if($r!==null) $allRatings[] = $r;
}
$avgRating  = $allRatings ? round(array_sum($allRatings)/count($allRatings),1) : null;
$categories = array_unique(array_column($attractions,'category'));
$cover      = $attractions[0]['image'];

$filterCat  = $_GET['cat'] ?? 'all';
$displayed  = $filterCat === 'all' ? $attractions
    : array_values(array_filter($attractions, fn($a)=>$a['category']===$filterCat));
?>
<!DOCTYPE html>
<html lang="en"<?= darkModeAttr() ?>>
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= htmlspecialchars($muniName) ?> – VISTA-Rizal</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?= renderNav('attractions') ?>
<main class="container">

  <div class="muni-profile-hero">
    <div class="muni-profile-cover">
      <img src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars($muniName) ?>"
           onerror="this.src='images/placeholder.jpg'">
      <div class="muni-profile-cover-overlay"></div>
    </div>
    <div class="muni-profile-info">
      <div class="muni-profile-avatar"><?= strtoupper(substr($muniName,0,1)) ?></div>
      <div class="muni-profile-text">
        <h1><?= htmlspecialchars($muniName) ?></h1>
        <p> Rizal Province &nbsp;·&nbsp;
          <?= $avgRating ? '⭐ '.$avgRating.'/5' : 'No ratings yet' ?>
          &nbsp;·&nbsp; <?= count($attractions) ?> Attractions</p>
        <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
          <?php foreach($categories as $cat): ?>
            <span class="category-badge category-<?= $cat ?>"><?= ucfirst($cat) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="muni-profile-stats">
    <div class="mps-item"><strong><?= count($attractions) ?></strong><span>Attractions</span></div>
    <div class="mps-item"><strong><?= $avgRating ? '⭐ '.$avgRating : '—' ?></strong><span>Avg Rating</span></div>
    <div class="mps-item"><strong><?= count($categories) ?></strong><span>Categories</span></div>
    <div class="mps-item">
      <strong><?= $avgRating ? min(99, round(($avgRating/5)*94)).'%' : '—' ?></strong>
      <span>Satisfaction</span>
    </div>
  </div>

  <div class="muni-filter-tabs">
    <?php foreach(['all'=>'All'] + array_combine($categories,array_map('ucfirst',$categories)) as $val=>$label): ?>
      <a href="municipality.php?name=<?= urlencode($muniName) ?>&cat=<?= $val ?>"
         class="muni-tab <?= $filterCat===$val?'active':'' ?>">
        <?= htmlspecialchars($label) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if(empty($displayed)): ?>
    <p style="color:var(--text-muted);padding:24px 0;">No attractions in this category.</p>
  <?php else: ?>
    <div class="cards-grid" style="margin-top:20px;">
      <?php foreach($displayed as $a):
        $rating = getAttractionRating($a['id']);
      ?>
        <div class="card">
          <img src="<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['name']) ?>"
               onerror="this.src='images/placeholder.jpg'">
          <div class="card-content">
            <span class="category-badge category-<?= $a['category'] ?>"><?= ucfirst($a['category']) ?></span>
            <h3><?= htmlspecialchars($a['name']) ?></h3>
            <div class="rating">
              <?= $rating ? '⭐ '.$rating.'/5' : '<span style="color:var(--text-muted);font-size:.82rem;">No ratings yet</span>' ?>
            </div>
            <p style="font-size:.8rem;color:var(--text-muted);margin-top:4px;"><?= htmlspecialchars($a['fact']) ?></p>
            <a href="details.php?id=<?= $a['id'] ?>" class="btn-primary btn-sm">View Details</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div style="margin-top:24px;">
    <a href="index.php" class="btn-secondary">← Back to Explore</a>
  </div>
</main>
<div class="footer">Tourist Satisfaction Prediction System · Province of Rizal</div>
</body>
</html>
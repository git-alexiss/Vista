<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
checkSessionTimeout();

$cat  = $_GET['cat']??'all';
$all  = getAttractions();
$list = ($cat==='all') ? $all : array_values(array_filter($all,fn($a)=>$a['category']===$cat));
?>
<!DOCTYPE html>
<html lang="en"<?= darkModeAttr() ?>>
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Attractions – VISTA-Rizal</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?= renderNav('attractions') ?>
<main class="container">
  <div class="hero-section fade-in">
    <h1>🗺 All Attractions</h1>
    <p>Explore <?= count($all) ?> destinations across Rizal Province</p>
  </div>

  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
    <?php foreach(['all'=>'All','nature'=>'🌿 Nature','cultural'=>'🏛 Cultural','adventure'=>'⛰ Adventure'] as $val=>$label): ?>
      <a href="attractions.php?cat=<?= $val ?>"
         style="padding:8px 18px;border-radius:20px;font-weight:600;font-size:.85rem;
                background:<?= $cat===$val?'var(--green)':'var(--green-pale)' ?>;
                color:<?= $cat===$val?'#fff':'var(--green)' ?>;
                text-decoration:none;">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>

  <p style="color:var(--text-muted);font-size:.9rem;margin-bottom:12px;">
    Showing <?= count($list) ?> attraction(s)
  </p>

  

  <div class="cards-grid">
    <?php foreach($list as $a):
      $rating = getAttractionRating($a['id']);
    ?>
      <div class="card">
        <img src="<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['name']) ?>" onerror="this.src='images/placeholder.jpg'">
        <div class="card-content">
          <span class="category-badge category-<?= $a['category'] ?>"><?= ucfirst($a['category']) ?></span>
          <h3><?= htmlspecialchars($a['name']) ?></h3>
          <div class="rating">
            <?= $rating ? '⭐ '.$rating.'/5' : '<span style="color:var(--text-muted);font-size:.82rem;">No ratings yet</span>' ?>
          </div>
          <span class="location"><?= htmlspecialchars($a['municipality']) ?></span>
          <span style="font-size:.82rem;font-weight:600;color:var(--green);"><?= htmlspecialchars($a['price']) ?></span>


          <a href="details.php?id=<?= $a['id'] ?>" class="btn-primary btn-sm">View Details</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>
</body>
</html>
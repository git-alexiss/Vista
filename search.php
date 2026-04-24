<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
checkSessionTimeout();
$q       = trim($_GET['q']??'');
$results = $q !== '' ? searchAttractions($q) : [];
$catFilter = $_GET['cat']??'';
if ($catFilter && $catFilter!=='all') {
    $results = array_values(array_filter($results, fn($a)=>$a['category']===$catFilter));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Search – VISTA-Rizal</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?= renderNav() ?>
<main class="container">
  <div class="search-hero">
    <h1>Search Attractions</h1>
    <p style="color:var(--text-muted)">Find places by name, category, or municipality</p>
    <form method="GET" action="search.php">
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="e.g. waterfall, Antipolo, adventure…">
      <button type="submit">Search</button>
    </form>
  </div>

  <?php if($q!==''): ?>
    <!-- Category filter tabs -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
      <?php foreach(['all'=>'All','nature'=>'Nature','cultural'=>'Cultural','adventure'=>'Adventure'] as $val=>$label): ?>
        <a href="search.php?q=<?= urlencode($q) ?>&cat=<?= $val ?>"
           class="badge <?= ($catFilter===$val||($val==='all'&&!$catFilter))?'badge-positive':'' ?>"
           style="padding:6px 14px;font-size:.85rem;">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <p class="results-meta">
      <?= count($results) ?> result(s) for "<strong><?= htmlspecialchars($q) ?></strong>"
    </p>

    <?php if(empty($results)): ?>
      <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
        <div style="font-size:3rem"></div>
        <h3 style="margin:12px 0 8px;">No results found</h3>
        <p>Try a different keyword or browse our <a href="attractions.php">full attractions list</a>.</p>
      </div>
    <?php else: ?>
      <div class="cards-grid">
        <?php foreach($results as $a): ?>
          <div class="card">
            <img src="<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['name']) ?>" onerror="this.src='images/placeholder.jpg'">
            <div class="card-content">
              <span class="category-badge category-<?= $a['category'] ?>"><?= ucfirst($a['category']) ?></span>
              <h3><?= htmlspecialchars($a['name']) ?></h3>
              <div class="rating"> <?= $a['rating'] ?>/5</div>
              <span class="location"> <?= htmlspecialchars($a['municipality']) ?></span>
              <span class="hours"> <?= htmlspecialchars($a['hours']) ?></span>
              <a href="details.php?id=<?= $a['id'] ?>" class="btn-primary btn-sm">View Details</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</main>
</body>
</html>
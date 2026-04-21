<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
checkSessionTimeout();

$all             = getAttractions();
$recommendations = getRecommendations($_SESSION['recently_viewed']??[]);
$popular         = getPopularAttractions();
$user            = $_SESSION['user'];

// Apply saved category filter preference
$defaultCat = $_SESSION['settings']['category_filter'] ?? 'all';

// Build municipality overview
$municipalities = [];
foreach ($all as $a) {
    $m = $a['municipality'];
    if (!isset($municipalities[$m])) {
        $municipalities[$m] = ['name'=>$m,'attractions'=>[],'categories'=>[],'cover'=>$a['image']];
    }
    $municipalities[$m]['attractions'][] = $a;
    $municipalities[$m]['categories'][]  = $a['category'];
}
foreach ($municipalities as $m => $d) {
    $liveRatings = array_filter(array_map(fn($a)=>getAttractionRating($a['id']), $d['attractions']), fn($r)=>$r!==null);
    $municipalities[$m]['avg_rating']   = $liveRatings ? round(array_sum($liveRatings)/count($liveRatings),1) : null;
    $municipalities[$m]['total']        = count($d['attractions']);
    $municipalities[$m]['categories']   = array_unique($d['categories']);
    $municipalities[$m]['satisfaction'] = $municipalities[$m]['avg_rating']
        ? min(99, round(($municipalities[$m]['avg_rating']/5)*100))
        : null;
    $municipalities[$m]['popularity']   = min(99, round(($municipalities[$m]['total']/count($all))*100 + 60));
    $tags = [];
    if ($municipalities[$m]['avg_rating'] >= 4.7) $tags[] = 'Top Rated';
    if ($municipalities[$m]['popularity']  >= 85)  $tags[] = 'Trending';
    foreach ($municipalities[$m]['categories'] as $cat) $tags[] = ucfirst($cat);
    $municipalities[$m]['tags'] = array_unique($tags);
}
ksort($municipalities);

$searchQ   = trim($_GET['q']   ?? '');
$filterCat = trim($_GET['cat'] ?? $defaultCat);
$sortBy    = trim($_GET['sort'] ?? 'rating');

$filteredMunis = $municipalities;
if ($searchQ !== '') {
    $filteredMunis = array_filter($filteredMunis, fn($m) =>
        stripos($m['name'], $searchQ) !== false ||
        !empty(array_filter($m['categories'], fn($c) => stripos($c, $searchQ) !== false))
    );
}
if ($filterCat !== 'all') {
    $filteredMunis = array_filter($filteredMunis, fn($m) =>
        in_array($filterCat, $m['categories'])
    );
}
usort($filteredMunis, fn($a,$b) => $sortBy === 'satisfaction'
    ? ($b['satisfaction']??0) <=> ($a['satisfaction']??0)
    : ($b['avg_rating']??0)   <=> ($a['avg_rating']??0)
);
?>
<!DOCTYPE html>
<html lang="en"<?= darkModeAttr() ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VISTA-Rizal – Explore</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?= renderNav('home') ?>

<main class="container">

  <?php if(isset($_SESSION['notification'])): ?>
    <div class="notification success fade-in">
      <?= htmlspecialchars($_SESSION['notification']); unset($_SESSION['notification']); ?>
    </div>
  <?php endif; ?>

  <section class="hero-section fade-in" style="margin-bottom:28px;">
    <h1>Welcome back, <?= htmlspecialchars($user['name']??'Traveler') ?>! 👋</h1>
    <p>Discover the best of Rizal Province — <?= count($all) ?> attractions across <?= count($municipalities) ?> municipalities.</p>
    <?php if(($user['role']??'')==='admin'): ?>
      <a href="admin_dashboard.php" class="btn-primary"
         style="display:inline-block;width:auto;margin-top:16px;padding:10px 24px;">
        📊 Go to Admin Dashboard
      </a>
    <?php endif; ?>
  </section>

  <form method="GET" action="index.php" class="filters-bar">
    <input type="text" name="q" placeholder="Search attractions or municipalities…"
           value="<?= htmlspecialchars($searchQ) ?>">
    <select name="cat">
      <option value="all"      <?= $filterCat==='all'      ?'selected':'' ?>>All Categories</option>
      <option value="nature"   <?= $filterCat==='nature'   ?'selected':'' ?>>🌿 Nature</option>
      <option value="cultural" <?= $filterCat==='cultural' ?'selected':'' ?>>🏛 Cultural</option>
      <option value="adventure"<?= $filterCat==='adventure'?'selected':'' ?>>⛰ Adventure</option>
    </select>
    <select name="sort">
      <option value="rating"      <?= $sortBy==='rating'      ?'selected':'' ?>>Sort by Rating</option>
      <option value="satisfaction"<?= $sortBy==='satisfaction'?'selected':'' ?>>Sort by Satisfaction</option>
    </select>
    <button type="submit" class="filters-search-btn">🔍 Search</button>
  </form>

  <section style="margin-bottom:36px;">
    <div class="section-header">
      <h2>🗺 Explore Municipalities</h2>
      <?php if($searchQ||$filterCat!=='all'): ?>
        <a href="index.php" style="font-size:.85rem;color:var(--text-muted);">✕ Clear filters</a>
      <?php endif; ?>
    </div>

    <?php if(empty($filteredMunis)): ?>
      <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
        <div style="font-size:3rem;">😕</div>
        <h3 style="margin:12px 0 8px;">No results found</h3>
        <p>Try a different keyword or category.</p>
        <a href="index.php" class="btn-primary" style="width:auto;display:inline-block;margin-top:16px;padding:10px 24px;">Clear Search</a>
      </div>
    <?php else: ?>
      <div class="muni-overview-grid">
        <?php foreach($filteredMunis as $mData):
          $tagColors = ['Nature'=>'tag-nature','Cultural'=>'tag-cultural','Adventure'=>'tag-adventure','Top Rated'=>'tag-top','Trending'=>'tag-trending'];
        ?>
          <a class="muni-overview-card" href="municipality.php?name=<?= urlencode($mData['name']) ?>">
            <div class="moc-cover">
              <img src="<?= htmlspecialchars($mData['cover']) ?>" alt="<?= htmlspecialchars($mData['name']) ?>"
                   onerror="this.src='images/placeholder.jpg'">
              <div class="moc-cover-overlay"></div>
              <div class="moc-cover-title"><?= htmlspecialchars($mData['name']) ?></div>
            </div>
            <div class="moc-header">
              <div>
                <h3 class="moc-name"><?= htmlspecialchars($mData['name']) ?></h3>
                <small class="moc-sub">Overview · <?= $mData['total'] ?> attraction(s)</small>
              </div>
              <button class="moc-more" onclick="return false;">⋮</button>
            </div>
            <div class="moc-meta">
              <?php if($mData['avg_rating']): ?>
                <span>⭐ <?= $mData['avg_rating'] ?></span>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:.8rem;">No ratings yet</span>
              <?php endif; ?>
              <?php if($mData['satisfaction']): ?>
                <span>😊 Satisfaction: <?= $mData['satisfaction'] ?>%</span>
              <?php endif; ?>
              <span>🔥 Popularity: <?= $mData['popularity'] ?>%</span>
            </div>
            <div class="moc-tags">
              <?php foreach(array_slice($mData['tags'],0,4) as $tag): ?>
                <span class="moc-tag <?= $tagColors[$tag]??'tag-default' ?>"><?= htmlspecialchars($tag) ?></span>
              <?php endforeach; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section style="margin-bottom:36px;">
    <div class="section-header">
      <h2>🔥 Popular Right Now</h2>
      <a href="popular.php">See all →</a>
    </div>
    <?php if(empty($popular)): ?>
      <p style="color:var(--text-muted);padding:16px 0;">No rated attractions yet. <a href="attractions.php">Browse and leave the first review!</a></p>
    <?php else: ?>
    <div class="cards-grid">
      <?php foreach(array_slice($popular,0,3) as $a):
        $rating = getAttractionRating($a['id']);
      ?>
        <div class="card">
          <img src="<?= htmlspecialchars($a['image']) ?>" alt="<?= htmlspecialchars($a['name']) ?>"
               onerror="this.src='images/placeholder.jpg'">
          <div class="card-content">
            <span class="category-badge category-<?= $a['category'] ?>"><?= ucfirst($a['category']) ?></span>
            <h3><?= htmlspecialchars($a['name']) ?></h3>
            <div class="rating">⭐ <?= $rating ?>/5</div>
            <span class="location">📍 <?= htmlspecialchars($a['municipality']) ?></span>
            <a href="details.php?id=<?= $a['id'] ?>" class="btn-primary btn-sm">View Details</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section>
    <div class="section-header">
      <h2>⭐ Recommended for You</h2>
      <a href="recommended.php">See all →</a>
    </div>
    <?php if(empty($recommendations)): ?>
      <p style="color:var(--text-muted);padding:20px 0;">You've explored everything! Check back soon.</p>
    <?php else: ?>
      <div class="cards-grid">
        <?php foreach($recommendations as $a):
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
              <span class="location">📍 <?= htmlspecialchars($a['municipality']) ?></span>
              <a href="details.php?id=<?= $a['id'] ?>" class="btn-primary btn-sm">View Details</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</main>
<div class="footer">Tourist Satisfaction Prediction System · Province of Rizal</div>
</body>
</html>
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
        $municipalities[$m] = ['name'=>$m,'attractions'=>[],'categories'=>[],'cover'=>getMunicipalityImage($m)];
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

function loadMunicipalityRankingsFromCsv(string $csvPath): array {
    if (!is_readable($csvPath)) {
        return [];
    }

    $handle = fopen($csvPath, 'r');
    if ($handle === false) {
        return [];
    }

    $headers = fgetcsv($handle);
    if (!is_array($headers)) {
        fclose($handle);
        return [];
    }

    $stats = [];
    while (($row = fgetcsv($handle)) !== false) {
        $row = array_combine($headers, $row);
        if ($row === false) {
            continue;
        }

        $location = trim($row['Location'] ?? '');
        $ratings  = is_numeric($row['Ratings'] ?? null) ? (float)$row['Ratings'] : null;
        $label    = strtolower(trim((string)($row['Satisfaction_Label'] ?? '')));
        $satisfied = $label === '1' || $label === 'satisfied' || $label === 'true';

        if ($location === '' || $ratings === null) {
            continue;
        }

        if (!isset($stats[$location])) {
            $stats[$location] = ['count' => 0, 'sum' => 0.0, 'satisfied' => 0];
        }
        $stats[$location]['count'] += 1;
        $stats[$location]['sum']   += $ratings;
        $stats[$location]['satisfied'] += $satisfied ? 1 : 0;
    }
    fclose($handle);

    $rankings = [];
    foreach ($stats as $location => $data) {
        if ($data['count'] === 0) {
            continue;
        }
        $rankings[] = [
            'Location'       => $location,
            'average_rating' => round($data['sum'] / $data['count'], 3),
            'total_reviews'  => $data['count'],
            'satisfied_pct'  => round($data['satisfied'] / $data['count'], 3),
        ];
    }

    usort($rankings, function($a, $b) {
        if ($b['average_rating'] <=> $a['average_rating']) {
            return $b['average_rating'] <=> $a['average_rating'];
        }
        return $b['total_reviews'] <=> $a['total_reviews'];
    });

    foreach ($rankings as $index => &$entry) {
        $entry['rank'] = $index + 1;
    }
    return $rankings;
}

$predictionCsv = __DIR__ . '/api/tourist_insights_processed.csv';
$predictions   = loadMunicipalityRankingsFromCsv($predictionCsv);

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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VISTA-Rizal – Explore</title>
  <link rel="stylesheet" href="CSS\style.css">
</head>
<body<?= darkModeAttr() ?>>
<?= renderNav('home') ?>

<main class="container">

  <?php if(isset($_SESSION['notification'])): ?>
    <div class="notification success fade-in">
      <?= htmlspecialchars($_SESSION['notification']); unset($_SESSION['notification']); ?>
    </div>
  <?php endif; ?>

  <section class="hero-section fade-in" style="margin-bottom:28px;">
    <h1>Welcome back, <?= htmlspecialchars($user['name']??'Traveler') ?>!</h1>
    <p>Discover the best of Rizal Province — <?= count($all) ?> attractions across <?= count($municipalities) ?> municipalities.</p>
    <?php if(($user['role']??'')==='admin'): ?>
      <a href="admin_dashboard.php" class="btn-primary"
         style="display:inline-block;width:auto;margin-top:16px;padding:10px 24px;">
         Go to Admin Dashboard
      </a>
    <?php endif; ?>
  </section>

  <section class="prediction-section" style="margin-bottom:36px;">
    <div class="section-header">
      <h2>Predicted Top Municipalities</h2>
      <p style="font-size:.94rem;color:var(--text-muted);margin:0;">Ranked by average rating and satisfaction from tourist_insights_processed.csv.</p>
    </div>
    <?php if (empty($predictions)): ?>
      <div style="padding:20px;line-height:1.6;color:var(--text-muted);">
        Unable to load municipality rankings from the CSV dataset. Please ensure <code>api/tourist_insights_processed.csv</code> exists.
      </div>
    <?php else: ?>
      <div class="cards-grid" style="margin-top:16px;">
        <?php foreach (array_slice($predictions, 0, 6) as $m): ?>
          <div class="card" style="min-width:220px;">
            <div class="card-content">
              <h3 style="margin:.2rem 0;">#<?= htmlspecialchars($m['rank']) ?> <?= htmlspecialchars($m['Location']) ?></h3>
              <p style="margin:.2rem 0;">Average Rating: <strong><?= htmlspecialchars($m['average_rating']) ?></strong></p>
              <p style="margin:.2rem 0;">Reviews: <strong><?= htmlspecialchars($m['total_reviews']) ?></strong></p>
              <p style="margin:.2rem 0;">Satisfaction: <strong><?= htmlspecialchars(round($m['satisfied_pct'] * 100, 1)) ?>%</strong></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section style="margin-bottom:36px;">
    <div class="section-header">
      <h2>Explore Municipalities</h2>
      <?php if($searchQ||$filterCat!=='all'): ?>
        <a href="index.php" style="font-size:.85rem;color:var(--text-muted);">✕ Clear filters</a>
      <?php endif; ?>
    </div>

    <?php if(empty($filteredMunis)): ?>
      <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
        <div style="font-size:3rem;"></div>
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
              <img src="<?= htmlspecialchars(getMunicipalityImage($mData['name'])) ?>" onerror="this.src='images/placeholder.jpg'">
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
                <span>★<?= $mData['avg_rating'] ?></span>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:.8rem;">No ratings yet</span>
              <?php endif; ?>
              <?php if($mData['satisfaction']): ?>
                <span> Satisfaction: <?= $mData['satisfaction'] ?>%</span>
              <?php endif; ?>
              <span> Popularity: <?= $mData['popularity'] ?>%</span>
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
      <h2> Popular Right Now</h2>
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
          <img src="<?= htmlspecialchars(getAttractionImage($a['name'])) ?>" alt="<?= htmlspecialchars($a['name']) ?>"
            onerror="this.src='images/placeholder.jpg'">
          <div class="card-content">
            <span class="category-badge category-<?= $a['category'] ?>"><?= ucfirst($a['category']) ?></span>
            <h3><?= htmlspecialchars($a['name']) ?></h3>
            <div class="rating">★<?= $rating ?>/5</div>
            <span class="location"> <?= htmlspecialchars($a['municipality']) ?></span>
            <a href="details.php?id=<?= $a['id'] ?>" class="btn-primary btn-sm">View Details</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section>
    <div class="section-header">
      <h2> Recommended for You</h2>
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
            <img src="<?= htmlspecialchars(getAttractionImage($a['name'])) ?>" alt="<?= htmlspecialchars($a['name']) ?>"
              onerror="this.src='images/placeholder.jpg'">
            <div class="card-content">
              <span class="category-badge category-<?= $a['category'] ?>"><?= ucfirst($a['category']) ?></span>
              <h3><?= htmlspecialchars($a['name']) ?></h3>
              <div class="rating">
                <?= $rating ? '★'.$rating.'/5' : '<span style="color:var(--text-muted);font-size:.82rem;">No ratings yet</span>' ?>
              </div>
              <span class="location"> <?= htmlspecialchars($a['municipality']) ?></span>
              <a href="details.php?id=<?= $a['id'] ?>" class="btn-primary btn-sm">View Details</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</main>
<div class="footer">Tourist Satisfaction Prediction System · Province of Rizal</div>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (document.body.classList.contains('dark-mode')) {
      document.body.style.opacity = '1';
    }
  });
</script>
</body>
</html>
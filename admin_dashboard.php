<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
if (($_SESSION['user']['role']??'')==='tourist') { header('Location: index.php'); exit; }
checkSessionTimeout();

$all     = getAttractions();
$reviews = getReviews();
$avgScore = $reviews ? round(array_sum(array_column($reviews,'rating'))/count($reviews),1) : 0;

$sentiments = array_count_values(array_column($reviews,'sentiment'));
$pos = $sentiments['positive']??0;
$neg = $sentiments['negative']??0;
$neu = $sentiments['neutral'] ??0;

// Build municipalities
$municipalities = [];
foreach ($all as $a) {
    $m = $a['municipality'];
    if (!isset($municipalities[$m])) {
        $municipalities[$m] = ['name'=>$m,'attractions'=>[],'categories'=>[],'cover'=>getMunicipalityImage($m)];
    }
    $municipalities[$m]['attractions'][] = $a;
    $municipalities[$m]['categories'][]  = $a['category'];
}
foreach ($municipalities as $m=>$d) {
    $liveRatings=array_filter(array_map(fn($a)=>getAttractionRating($a['id']),$d['attractions']),fn($r)=>$r!==null);$municipalities[$m]['avg_rating']=$liveRatings?round(array_sum($liveRatings)/count($liveRatings),1):null;
    $municipalities[$m]['categories'] = array_unique($d['categories']);
}
ksort($municipalities);

$top  = $all; usort($top,fn($a,$b)=>$b['rating']<=>$a['rating']); $top=array_slice($top,0,5);
$cats = array_count_values(array_column($all,'category'));

$selectedMuni    = $_GET['muni'] ?? null;
$muniAttractions = ($selectedMuni && isset($municipalities[$selectedMuni]))
                   ? $municipalities[$selectedMuni]['attractions'] : [];

// Load location ranking data for predictions
$locationRankingPath = 'api/location_ranking.csv';
$locationRanking = [];
if (file_exists($locationRankingPath)) {
    if (($handle = fopen($locationRankingPath, 'r')) !== false) {
        fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 4) {
                $locationRanking[] = [
                    'location' => $data[0],
                    'satisfaction' => floatval($data[1]),
                    'review_count' => intval($data[2]),
                    'avg_rating' => floatval($data[3])
                ];
            }
        }
        fclose($handle);
    }
}
usort($locationRanking, fn($a, $b) => $b['satisfaction'] <=> $a['satisfaction']);
$topMuni = array_slice($locationRanking, 0, 8);

// SVG Pie Chart generator
function generatePieChart($data, $width = 300, $height = 300) {
    if (empty($data)) return '';
    
    $total = array_sum(array_map(fn($d) => $d['satisfaction'] * 100, $data));
    if ($total == 0) return '';
    
    $colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];
    $cx = $width / 2;
    $cy = $height / 2;
    $radius = min($cx, $cy) - 30;
    
    $svg = "<svg width='$width' height='$height' viewBox='0 0 $width $height'>";
    $angle = -90;
    
    foreach ($data as $idx => $item) {
        $value = $item['satisfaction'] * 100;
        $sliceAngle = ($value / $total) * 360;
        $endAngle = $angle + $sliceAngle;
        
        $startRad = deg2rad($angle);
        $endRad = deg2rad($endAngle);
        
        $x1 = $cx + $radius * cos($startRad);
        $y1 = $cy + $radius * sin($startRad);
        $x2 = $cx + $radius * cos($endRad);
        $y2 = $cy + $radius * sin($endRad);
        
        $largeArc = $sliceAngle > 180 ? 1 : 0;
        
        $color = $colors[$idx % count($colors)];
        $svg .= "<path d='M $cx $cy L $x1 $y1 A $radius $radius 0 $largeArc 1 $x2 $y2 Z' fill='$color' stroke='var(--card-bg)' stroke-width='2'/>";
        
        $angle = $endAngle;
    }
    
    $svg .= "</svg>";
    return $svg;
}

// SVG Bar Chart generator
function generateBarChart($data, $width = 400, $height = 250) {
    if (empty($data)) return '';
    
    $maxRating = max(array_map(fn($d) => $d['avg_rating'], $data)) ?: 5;
    $maxReviews = max(array_map(fn($d) => $d['review_count'], $data)) ?: 100;
    
    $barWidth = floor(($width - 60) / count($data));
    $chartHeight = $height - 60;
    
    $svg = "<svg width='$width' height='$height' viewBox='0 0 $width $height' style='font-family:sans-serif;font-size:11px;'>";
    
    // Y-axis labels for ratings
    $svg .= "<text x='35' y='15' font-size='10' fill='var(--text-muted)'>Rating</text>";
    for ($i = 0; $i <= 5; $i++) {
        $y = $height - 40 - ($i / 5) * $chartHeight;
        $svg .= "<text x='8' y='" . ($y + 4) . "' text-anchor='end' fill='var(--text-muted)'>$i</text>";
        $svg .= "<line x1='30' y1='$y' x2='$width' y2='$y' stroke='var(--border)' stroke-width='0.5' stroke-dasharray='2,2'/>";
    }
    
    // Y-axis for reviews (right side)
    $svg .= "<text x='" . ($width - 15) . "' y='15' font-size='10' fill='var(--text-muted)' text-anchor='end'>Reviews</text>";
    
    // X-axis
    $svg .= "<line x1='30' y1='" . ($height - 40) . "' x2='$width' y2='" . ($height - 40) . "' stroke='var(--text-muted)' stroke-width='1'/>";
    
    $x = 40;
    foreach ($data as $idx => $item) {
        $ratingHeight = ($item['avg_rating'] / 5) * $chartHeight;
        $reviewHeight = ($item['review_count'] / $maxReviews) * ($chartHeight * 0.7);
        
        // Rating bar (blue)
        $y = $height - 40 - $ratingHeight;
        $svg .= "<rect x='" . ($x + 2) . "' y='$y' width='" . ($barWidth / 2 - 3) . "' height='$ratingHeight' fill='#3b82f6' opacity='0.8'/>";
        
        // Review bar (green)
        $y2 = $height - 40 - $reviewHeight;
        $svg .= "<rect x='" . ($x + $barWidth / 2 + 1) . "' y='$y2' width='" . ($barWidth / 2 - 3) . "' height='$reviewHeight' fill='#10b981' opacity='0.8'/>";
        
        // Label
        $label = substr($item['location'], 0, 3);
        $svg .= "<text x='" . ($x + $barWidth / 2) . "' y='" . ($height - 22) . "' text-anchor='middle' fill='var(--text)'>$label</text>";
        
        $x += $barWidth;
    }
    
    $svg .= "</svg>";
    return $svg;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Admin Dashboard – VISTA-Rizal</title>
  <link rel="stylesheet" href="CSS\style.css">
  <style>
    .muni-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px,1fr));
      gap: 16px; margin-top: 16px;
    }
    .muni-card {
      background: var(--card-bg); border-radius: 14px;
      box-shadow: var(--shadow); overflow: hidden;
      cursor: pointer; transition: transform .2s, box-shadow .2s;
      border: 2px solid transparent; text-decoration: none; color: inherit; display: block;
    }
    .muni-card:hover { transform: translateY(-4px); box-shadow: 0 10px 32px rgba(0,0,0,.14); border-color: var(--pimary-light); }
    .muni-card.active-muni { border-color: var(--primary); }
    .muni-cover { height: 90px; background: var(--primary); position: relative; overflow: hidden; }
    .muni-cover img { width:100%; height:100%; object-fit:cover; filter:brightness(.75); }
    .muni-cover-overlay { position:absolute; inset:0; background:linear-gradient(transparent 40%,rgba(0,0,0,.45)); }
    .muni-avatar-wrap { display:flex; justify-content:center; margin-top:-26px; position:relative; z-index:2; }
    .muni-avatar {
      width:52px; height:52px; border-radius:50%;
      background:var(--primary); color:#fff;
      display:flex; align-items:center; justify-content:center;
      font-size:1.3rem; font-weight:900;
      border:3px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,.18);
    }
    .muni-body { padding:8px 12px 14px; text-align:center; }
    .muni-name { font-size:.92rem; font-weight:800; color:var(--text); margin-bottom:2px; }
    .muni-cats { display:flex; gap:4px; justify-content:center; flex-wrap:wrap; margin-bottom:6px; }
    .muni-cat-dot { width:8px; height:8px; border-radius:50%; display:inline-block; }
    .dot-nature{background:#52b788;} .dot-cultural{background:#f4a261;} .dot-adventure{background:#9b72cf;}
    .muni-stats { display:flex; justify-content:center; gap:14px; border-top:1px solid var(--border); padding-top:8px; margin-top:4px; }
    .muni-stat { text-align:center; }
    .muni-stat strong { display:block; font-size:.88rem; color:var(--primary); }
    .muni-stat span   { font-size:.7rem; color:var(--text-muted); }

    .muni-panel {
      background:var(--card-bg); border-radius:14px; box-shadow:var(--shadow);
      padding:22px; margin-bottom:24px; border-left:4px solid var(--primary);
      animation:fadeIn .3s ease;
    }
    .muni-panel-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
    .muni-panel-header h3 { font-size:1.1rem; color:var(--primary); }
    .close-panel { background:none; border:1px solid var(--border); border-radius:6px; padding:4px 12px; cursor:pointer; font-size:.82rem; color:var(--text-muted); text-decoration:none; }
    .close-panel:hover { background:var(--bg); }
    @media(max-width:768px){ .dashboard-two-col{grid-template-columns:1fr!important;} .muni-grid{grid-template-columns:1fr 1fr;} }
    @media(max-width:480px){ .muni-grid{grid-template-columns:1fr;} }
    
    .chart-container {
      background: var(--card-bg); border-radius: 14px; box-shadow: var(--shadow);
      padding: 22px; margin-bottom: 24px;
    }
    .chart-grid {
      display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;
    }
    .chart-header {
      font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 16px;
    }
    .chart-wrapper {
      display: flex; justify-content: center; align-items: center; min-height: 300px;
    }
    .chart-legend {
      font-size: 0.85rem; color: var(--text-muted); margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);
    }
    .legend-item {
      display: flex; align-items: center; gap: 8px; margin: 4px 0;
    }
    .legend-color {
      width: 16px; height: 16px; border-radius: 2px;
    }
    @media(max-width:1024px){ .chart-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body<?= darkModeAttr() ?>>
<?= renderNav('dashboard') ?>
<main class="container">

  <div style="margin-bottom:24px;">
    <h1 style="font-size:1.6rem;">Admin Dashboard</h1>
    <p style="color:var(--text-muted);">Welcome, <?= htmlspecialchars($_SESSION['user']['name']) ?> · <?= date('l, F j Y') ?></p>
  </div>

  <!-- Stats -->
  <div class="stats-grid" style="margin-bottom:28px;">
    <div class="stat-card"><div class="stat-value"><?= count($all) ?></div><div class="stat-label">Total Attractions</div></div>
    <div class="stat-card gold"><div class="stat-value"><?= count($reviews) ?></div><div class="stat-label">Total Reviews</div></div>
    <div class="stat-card"><div class="stat-value"><?= $avgScore ?></div><div class="stat-label">Avg Satisfaction</div></div>
    <div class="stat-card gold"><div class="stat-value"><?= $pos ?></div><div class="stat-label">Positive Reviews</div></div>
    <div class="stat-card red"><div class="stat-value"><?= $neg ?></div><div class="stat-label">Negative Reviews</div></div>
    <div class="stat-card"><div class="stat-value"><?= count($municipalities) ?></div><div class="stat-label">Municipalities</div></div>
  </div>

  <!-- Prediction Charts Section -->
  <?php if (!empty($topMuni)): ?>
  <div style="margin-bottom: 24px;">
    <h2 style="font-size: 1.2rem; color: var(--text); margin-bottom: 20px;">Municipality Prediction Analytics</h2>
    <div class="chart-grid">
      <!-- Pie Chart -->
      <div class="chart-container">
        <div class="chart-header">Satisfaction Distribution</div>
        <div class="chart-wrapper">
          <?= generatePieChart($topMuni, 320, 300) ?>
        </div>
        <div class="chart-legend">
          <strong>Top Performers:</strong>
          <?php foreach (array_slice($topMuni, 0, 3) as $idx => $m): ?>
            <div class="legend-item">
              <span><?= ($idx + 1) ?>. <?= htmlspecialchars($m['location']) ?> - <?= round($m['satisfaction'] * 100) ?>%</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Bar Chart -->
      <div class="chart-container">
        <div class="chart-header">Ratings vs Review Count</div>
        <div class="chart-wrapper">
          <?= generateBarChart($topMuni, 420, 300) ?>
        </div>
        <div class="chart-legend">
          <div class="legend-item">
            <div class="legend-color" style="background: #3b82f6;"></div>
            <span>Avg Rating (★)</span>
          </div>
          <div class="legend-item">
            <div class="legend-color" style="background: #10b981;"></div>
            <span>Review Count</span>
          </div>
          <div style="margin-top: 8px; font-size: 0.8rem;">Based on <?= count($locationRanking) ?> municipalities</div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Municipalities -->
  <div class="table-card" style="margin-bottom:24px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:8px;">
      <h3> Municipalities</h3>
      <small style="color:var(--text-muted);">Click a card to view its attractions</small>
    </div>
    <div class="muni-grid">
      <?php foreach($municipalities as $mName=>$mData): ?>
        <a class="muni-card <?= $selectedMuni===$mName?'active-muni':'' ?>"
           href="admin_dashboard.php?muni=<?= urlencode($mName) ?>#muni-panel">
          <div class="muni-cover">
            <img src="<?= htmlspecialchars($mData['cover']) ?>" alt="" onerror="this.src='images/placeholder.jpg'">
            <div class="muni-cover-overlay"></div>
          </div>
          <div class="muni-avatar-wrap">
            <div class="muni-avatar"><?= strtoupper(substr($mName,0,1)) ?></div>
          </div>
          <div class="muni-body">
            <div class="muni-name"><?= htmlspecialchars($mName) ?></div>
            <div class="muni-cats">
              <?php foreach($mData['categories'] as $cat): ?>
                <span class="muni-cat-dot dot-<?= $cat ?>" title="<?= ucfirst($cat) ?>"></span>
              <?php endforeach; ?>
            </div>
            <div class="muni-stats">
              <div class="muni-stat"><strong><?= count($mData['attractions']) ?></strong><span>Spots</span></div>
              <div class="muni-stat"><strong><?= $mData['avg_rating'] ? '★'.$mData['avg_rating'] : '—' ?></strong><span>Avg</span></div>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Municipality attractions panel -->
  <?php if($selectedMuni && !empty($muniAttractions)): ?>
  <div class="muni-panel" id="muni-panel">
    <div class="muni-panel-header">
      <h3> <?= htmlspecialchars($selectedMuni) ?> — <?= count($muniAttractions) ?> Attraction(s)</h3>
      <a href="admin_dashboard.php" class="close-panel">✕ Close</a>
    </div>
    <div class="cards-grid">
      <?php foreach($muniAttractions as $a): ?>
        <div class="card">
          <img src="<?= htmlspecialchars(getAttractionImage($a['name'])) ?>" alt="<?= htmlspecialchars($a['name']) ?>" onerror="this.src='images/placeholder.jpg'">
          <div class="card-content">
            <span class="category-badge category-<?= $a['category'] ?>"><?= ucfirst($a['category']) ?></span>
            <h3><?= htmlspecialchars($a['name']) ?></h3>
            <div class="rating">★<?= $a['rating'] ?>/5</div>
            <a href="details.php?id=<?= $a['id'] ?>" class="btn-primary btn-sm">View Details</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Top rated + Sentiment -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;" class="dashboard-two-col">
    <div class="table-card">
      <h3>Top Rated Attractions</h3>
      <div class="bar-chart">
        <?php foreach($top as $a): ?>
          <div class="bar-row">
            <span class="bar-label" title="<?= htmlspecialchars($a['name']) ?>"><?= htmlspecialchars($a['name']) ?></span>
            <div class="bar-track"><div class="bar-fill" style="width:<?= ($a['rating']/5)*100 ?>%"></div></div>
            <span class="bar-val"><?= $a['rating'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="table-card">
      <h3>Review Sentiment</h3>
      <?php if(!$reviews): ?>
        <p style="color:var(--text-muted);font-size:.9rem;">No reviews yet.</p>
      <?php else: ?>
        <div class="bar-chart">
          <?php foreach(['positive'=>[$pos,'var(--green)'],'neutral'=>[$neu,'#aaa'],'negative'=>[$neg,'var(--red)']] as $label=>[$cnt,$clr]): ?>
            <div class="bar-row">
              <span class="bar-label" style="text-transform:capitalize;"><?= $label ?></span>
              <div class="bar-track"><div class="bar-fill" style="width:<?= count($reviews)?($cnt/count($reviews))*100:0 ?>%;background:<?= $clr ?>;"></div></div>
              <span class="bar-val"><?= $cnt ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <p style="margin-top:12px;font-size:.82rem;color:var(--text-muted);">Score: <strong style="color:var(--green);"><?= $avgScore ?>/5</strong></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Reviews table -->
  <?php if($reviews): ?>
  <div class="table-card" style="margin-bottom:24px;">
    <h3>All Reviews</h3>
    <table>
      <thead><tr><th>Attraction</th><th>User</th><th>Rating</th><th>Sentiment</th><th>Comment</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach(array_reverse($reviews) as $r):
          $attr = getAttractionById($r['attraction_id']); ?>
          <tr>
            <td><?= htmlspecialchars($attr['name']??'Unknown') ?></td>
            <td><?= htmlspecialchars($r['user']) ?></td>
            <td><?= str_repeat('★',$r['rating']) ?></td>
            <td><span class="badge badge-<?= $r['sentiment'] ?>"><?= ucfirst($r['sentiment']) ?></span></td>
            <td style="max-width:180px;"><?= htmlspecialchars($r['review_text']) ?></td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div style="text-align:right;">
    <a href="reports.php" class="btn-primary" style="display:inline-block;width:auto;padding:10px 24px;">Full Reports →</a>
  </div>
</main>
</body>
</html>
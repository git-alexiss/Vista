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
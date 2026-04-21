<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
if (($_SESSION['user']['role']??'')==='tourist') { header('Location: index.php'); exit; }
checkSessionTimeout();

$all     = getAttractions();
$reviews = getReviews();

// Sort by rating
$byRating = $all;
usort($byRating, function($a,$b){ return (getAttractionRating($b['id'])??0) <=> (getAttractionRating($a['id'])??0); });
$maxRating = 5;

// Satisfaction scores per attraction (from reviews)
$reviewsByAttr = [];
foreach($reviews as $r) {
    $reviewsByAttr[$r['attraction_id']][] = $r['rating'];
}

// Sentiment
$sentiments = array_count_values(array_column($reviews,'sentiment'));
$total      = count($reviews);

// Municipalities
$muniCount = array_count_values(array_column($all,'municipality'));
arsort($muniCount);

// Category
$catCount = array_count_values(array_column($all,'category'));
$maxCat   = max(array_values($catCount)?:[1]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Reports – VISTA-Rizal</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?= renderNav('reports') ?>
<main class="container">
  <div style="margin-bottom:28px;">
    <h1 style="font-size:1.6rem;">📈 Analytics Reports</h1>
    <p style="color:var(--text-muted);">System-wide performance and visitor satisfaction data · Generated <?= date('F j, Y') ?></p>
  </div>

  <!-- ── Summary cards ── -->
  <div class="stats-grid" style="margin-bottom:32px;">
    <div class="stat-card">
      <div class="stat-value"><?= count($all) ?></div>
      <div class="stat-label">Total Attractions</div>
    </div>
    <div class="stat-card gold">
      <div class="stat-value"><?= $total ?></div>
      <div class="stat-label">Total Reviews Collected</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $total ? round(array_sum(array_column($reviews,'rating'))/$total,1) : 'N/A' ?></div>
      <div class="stat-label">Overall Avg Rating</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $total ? round(($sentiments['positive']??0)/$total*100) : 0 ?>%</div>
      <div class="stat-label">Positive Sentiment Rate</div>
    </div>
  </div>

  <!-- ── Rating leaderboard ── -->
  <div class="report-section">
    <h3>🏆 Attraction Satisfaction Leaderboard</h3>
    <div class="bar-chart">
      <?php foreach($byRating as $a):
        $liveRating  = getAttractionRating($a['id']);
        if($liveRating===null) continue; // skip unrated
        $pct = ($liveRating/$maxRating)*100;
        $reviewCount = count($reviewsByAttr[$a['id']]??[]);
      ?>
        <div class="bar-row" style="align-items:center;">
          <span class="bar-label" title="<?= htmlspecialchars($a['name']) ?>"><?= htmlspecialchars($a['name']) ?></span>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="bar-val"><?= $liveRating ?></span>
          <?php if($reviewCount): ?>
            <span style="font-size:.72rem;color:var(--text-muted);margin-left:4px;">(<?= $reviewCount ?> reviews)</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Sentiment report ── -->
  <div class="report-section">
    <h3>💬 Review Sentiment Analysis</h3>
    <?php if(!$reviews): ?>
      <p style="color:var(--text-muted);">No reviews yet. Sentiment analysis will appear here as tourists submit reviews.</p>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:20px;">
        <?php foreach(['positive'=>['color'=>'var(--green)','icon'=>'😊'],'neutral'=>['color'=>'var(--text-muted)','icon'=>'😐'],'negative'=>['color'=>'var(--red)','icon'=>'😞']] as $s=>$cfg): ?>
          <div style="background:var(--bg);border-radius:10px;padding:16px;text-align:center;border-top:3px solid <?= $cfg['color'] ?>;">
            <div style="font-size:1.8rem;"><?= $cfg['icon'] ?></div>
            <div style="font-size:1.6rem;font-weight:900;color:<?= $cfg['color'] ?>;margin:4px 0;"><?= $sentiments[$s]??0 ?></div>
            <div style="font-size:.8rem;color:var(--text-muted);text-transform:capitalize;"><?= $s ?></div>
            <div style="font-size:.75rem;color:var(--text-muted);"><?= $total ? round(($sentiments[$s]??0)/$total*100) : 0 ?>%</div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="bar-chart">
        <?php foreach(['positive','neutral','negative'] as $s):
          $cnt = $sentiments[$s]??0;
          $pct = $total ? ($cnt/$total)*100 : 0;
          $color = $s==='positive'?'var(--green)':($s==='negative'?'var(--red)':'#aaa');
        ?>
          <div class="bar-row">
            <span class="bar-label" style="text-transform:capitalize;"><?= $s ?></span>
            <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div></div>
            <span class="bar-val"><?= $cnt ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <h4 style="margin:20px 0 12px;">Sentiment per Attraction</h4>
      <table>
        <thead><tr><th>Attraction</th><th>😊 Positive</th><th>😐 Neutral</th><th>😞 Negative</th><th>Avg Rating</th></tr></thead>
        <tbody>
          <?php
          $attrSentiment = [];
          foreach($reviews as $r) {
              $attrSentiment[$r['attraction_id']][$r['sentiment']][] = $r['rating'];
          }
          foreach($all as $a):
            $data = $attrSentiment[$a['id']]??[];
            if(!$data) continue;
            $p = count($data['positive']??[]); $n = count($data['neutral']??[]); $ng = count($data['negative']??[]);
            $allR = array_merge(...array_values($data));
            $avg  = $allR ? round(array_sum($allR)/count($allR),1) : $a['rating'];
          ?>
            <tr>
              <td><?= htmlspecialchars($a['name']) ?></td>
              <td style="color:var(--green);font-weight:700;"><?= $p ?></td>
              <td style="color:var(--text-muted);font-weight:700;"><?= $n ?></td>
              <td style="color:var(--red);font-weight:700;"><?= $ng ?></td>
              <td>⭐ <?= $avg ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- ── Category report ── -->
  <div class="report-section">
    <h3>🗂 Attractions by Category</h3>
    <div class="bar-chart">
      <?php foreach($catCount as $cat=>$count): ?>
        <div class="bar-row">
          <span class="bar-label" style="text-transform:capitalize;"><?= $cat ?></span>
          <div class="bar-track"><div class="bar-fill gold" style="width:<?= ($count/$maxCat)*100 ?>%"></div></div>
          <span class="bar-val"><?= $count ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Municipality report ── -->
  <div class="report-section">
    <h3>📍 Attractions by Municipality</h3>
    <div class="bar-chart">
      <?php $maxMuni=max(array_values($muniCount)?:[1]); foreach($muniCount as $muni=>$count): ?>
        <div class="bar-row">
          <span class="bar-label"><?= htmlspecialchars($muni) ?></span>
          <div class="bar-track"><div class="bar-fill" style="width:<?= ($count/$maxMuni)*100 ?>%"></div></div>
          <span class="bar-val"><?= $count ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="text-align:right;">
    <a href="admin_dashboard.php" class="btn-secondary" style="display:inline-block;">← Back to Dashboard</a>
  </div>
</main>
</body>
</html>
<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
if (($_SESSION['user']['role']??'')==='tourist') { header('Location: index.php'); exit; }
checkSessionTimeout();

// ── vista_rizal data (attractions / reviews) ──────────────────────────────────
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

// ── vista_rizal_new data (tourist insights) ───────────────────────────────────
$insightsStats   = getInsightsOverallStats();          // overall KPIs
$locationSummary = getLocationSummary();               // 14-row leaderboard
$ratingDist      = getRatingDistribution();            // star distribution
$satisfactionDist= getSatisfactionBreakdown();         // satisfied vs unsatisfied

$maxInsightResponses = max(array_column($locationSummary, 'total_responses') ?: [1]);
$maxInsightRating    = 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Reports – VISTA-Rizal</title>
  <link rel="stylesheet" href="CSS\style.css">
</head>
<body<?= darkModeAttr() ?>>
<?= renderNav('reports') ?>
<main class="container">
  <div style="margin-bottom:28px;">
    <h1 style="font-size:1.6rem;">Analytics Reports</h1>
    <p style="color:var(--text-muted);">System-wide performance and visitor satisfaction data · Generated <?= date('F j, Y') ?></p>
  </div>

  <!-- ══════════════════════════════════════════════════════════════
       SECTION A — Classic Attraction / Review data (vista_rizal)
       ══════════════════════════════════════════════════════════════ -->

  <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:14px;border-bottom:2px solid var(--gold);padding-bottom:6px;">
     Attraction &amp; Review Analytics
    <span style="font-size:.75rem;font-weight:400;color:var(--text-muted);margin-left:8px;">Source: vista_rizal</span>
  </h2>

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
    <h3>Attraction Satisfaction Leaderboard</h3>
    <div class="bar-chart">
      <?php foreach($byRating as $a):
        $liveRating  = getAttractionRating($a['id']);
        if($liveRating===null) continue;
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
    <h3>Review Sentiment Analysis</h3>
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
              <td>★<?= $avg ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- ── Category report ── -->
  <div class="report-section">
    <h3>Attractions by Category</h3>
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
    <h3>Attractions by Municipality</h3>
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


  <!-- ══════════════════════════════════════════════════════════════
       SECTION B — Tourist Insights dataset (vista_rizal_new)
       ══════════════════════════════════════════════════════════════ -->

  <h2 style="font-size:1.1rem;font-weight:700;margin:40px 0 14px;border-bottom:2px solid var(--gold);padding-bottom:6px;">
     Tourist Insights Analytics
    <span style="font-size:.75rem;font-weight:400;color:var(--text-muted);margin-left:8px;">Source: vista_rizal_new · <?= number_format($insightsStats['total'] ?? 0) ?> survey responses</span>
  </h2>

  <!-- ── Insights summary cards ── -->
  <div class="stats-grid" style="margin-bottom:32px;">
    <div class="stat-card gold">
      <div class="stat-value"><?= number_format($insightsStats['total'] ?? 0) ?></div>
      <div class="stat-label">Total Survey Responses</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $insightsStats['avg_rating'] ?? 'N/A' ?></div>
      <div class="stat-label">Avg Tourist Rating</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $insightsStats['avg_sentiment'] ?? 'N/A' ?></div>
      <div class="stat-label">Avg Sentiment Score</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $insightsStats['satisfaction_rate'] ?? 0 ?>%</div>
      <div class="stat-label">Overall Satisfaction Rate</div>
    </div>
  </div>

  <!-- ── Location leaderboard (by avg rating) ── -->
  <div class="report-section">
    <h3> Municipality Leaderboard — Avg Tourist Rating</h3>
    <div class="bar-chart">
      <?php foreach($locationSummary as $row):
        $pct = ($row['avg_rating'] / $maxInsightRating) * 100;
      ?>
        <div class="bar-row" style="align-items:center;">
          <span class="bar-label" title="<?= htmlspecialchars($row['location_name']) ?>">
            <?= htmlspecialchars($row['location_name']) ?>
          </span>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="bar-val"><?= $row['avg_rating'] ?></span>
          <span style="font-size:.72rem;color:var(--text-muted);margin-left:4px;">
            (<?= number_format($row['total_responses']) ?> responses)
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Satisfaction breakdown ── -->
  <div class="report-section">
    <h3> Overall Satisfaction Distribution</h3>
    <?php
    $satTotal = array_sum(array_column($satisfactionDist, 'count'));
    $satColors = [1 => 'var(--green)', 2 => 'var(--red)'];
    ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:20px;">
      <?php foreach($satisfactionDist as $s):
        $color = $satColors[$s['satisfaction_label']] ?? '#aaa';
        $icon  = $s['satisfaction_label'] === 1 ? '' : '';
        $pct   = $satTotal ? round($s['count']/$satTotal*100) : 0;
      ?>
        <div style="background:var(--bg);border-radius:10px;padding:16px;text-align:center;border-top:3px solid <?= $color ?>;">
          <div style="font-size:1.8rem;"><?= $icon ?></div>
          <div style="font-size:1.6rem;font-weight:900;color:<?= $color ?>;margin:4px 0;"><?= number_format($s['count']) ?></div>
          <div style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars($s['label_text']) ?></div>
          <div style="font-size:.75rem;color:var(--text-muted);"><?= $pct ?>%</div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="bar-chart">
      <?php foreach($satisfactionDist as $s):
        $color = $satColors[$s['satisfaction_label']] ?? '#aaa';
        $pct   = $satTotal ? ($s['count']/$satTotal)*100 : 0;
      ?>
        <div class="bar-row">
          <span class="bar-label"><?= htmlspecialchars($s['label_text']) ?></span>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div>
          </div>
          <span class="bar-val"><?= number_format($s['count']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Star rating distribution ── -->
  <div class="report-section">
    <h3> Rating Distribution (All Municipalities)</h3>
    <?php
    $ratingTotal = array_sum(array_column($ratingDist,'count'));
    $maxRatingCount = max(array_column($ratingDist,'count') ?: [1]);
    ?>
    <div class="bar-chart">
      <?php foreach($ratingDist as $r):
        $pct = $maxRatingCount ? ($r['count']/$maxRatingCount)*100 : 0;
        $stars = str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5-(int)$r['rating']);
      ?>
        <div class="bar-row" style="align-items:center;">
          <span class="bar-label" style="color:var(--gold);letter-spacing:1px;"><?= $stars ?></span>
          <div class="bar-track">
            <div class="bar-fill gold" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="bar-val"><?= number_format($r['count']) ?></span>
          <span style="font-size:.72rem;color:var(--text-muted);margin-left:4px;">
            (<?= $ratingTotal ? round($r['count']/$ratingTotal*100) : 0 ?>%)
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Per-municipality full table ── -->
  <div class="report-section">
    <h3>📋 Municipality-Level Insights Summary</h3>
    <table>
      <thead>
        <tr>
          <th>Municipality</th>
          <th>Responses</th>
          <th>Avg Rating</th>
          <th>Avg Sentiment</th>
          <th> Satisfied</th>
          <th> Unsatisfied</th>
          <th>Satisfaction Rate</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($locationSummary as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['location_name']) ?></td>
            <td><?= number_format($row['total_responses']) ?></td>
            <td>★<?= $row['avg_rating'] ?></td>
            <td><?= $row['avg_sentiment_score'] ?></td>
            <td style="color:var(--green);font-weight:700;"><?= number_format($row['satisfied_count']) ?></td>
            <td style="color:var(--red);font-weight:700;"><?= number_format($row['unsatisfied_count']) ?></td>
            <td>
              <span style="
                display:inline-block;padding:2px 8px;border-radius:12px;font-size:.8rem;font-weight:700;
                background:<?= $row['satisfaction_rate'] >= 90 ? 'rgba(72,187,120,.15)' : ($row['satisfaction_rate'] >= 75 ? 'rgba(237,137,54,.15)' : 'rgba(245,101,101,.15)') ?>;
                color:<?= $row['satisfaction_rate'] >= 90 ? 'var(--green)' : ($row['satisfaction_rate'] >= 75 ? '#dd6b20' : 'var(--red)') ?>;">
                <?= $row['satisfaction_rate'] ?>%
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div style="text-align:right;">
    <a href="admin_dashboard.php" class="btn-secondary" style="display:inline-block;">← Back to Dashboard</a>
  </div>
</main>
</body>
</html>
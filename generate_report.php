<?php
// ─── generate_report.php ─────────────────────────────────────────────────────
//  Handles report exports for VISTA-Rizal (CSV + printable PDF page)
//  Place in the same directory as config.php / db.php
// ─────────────────────────────────────────────────────────────────────────────

include 'config.php';

// Auth guard
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); exit;
}
if (($_SESSION['user']['role'] ?? '') === 'tourist') {
    header('Location: index.php'); exit;
}
checkSessionTimeout();

// ── Parameters ────────────────────────────────────────────────────────────────
$format  = $_GET['format']  ?? 'pdf';   // 'pdf' | 'csv'
$section = $_GET['section'] ?? 'full';  // 'full' | 'attractions' | 'reviews' | 'sentiment' | 'municipalities'

// ── Fetch data ────────────────────────────────────────────────────────────────
$all        = getAttractions();
$reviews    = getReviews();
$sentiments = array_count_values(array_column($reviews, 'sentiment'));
$total      = count($reviews);
$avgScore   = $total ? round(array_sum(array_column($reviews, 'rating')) / $total, 1) : 0;

// Live ratings per attraction
$attractionRatings = [];
foreach ($all as $a) {
    $attractionRatings[$a['id']] = getAttractionRating($a['id']);
}

// Reviews grouped by attraction
$reviewsByAttr = [];
foreach ($reviews as $r) {
    $reviewsByAttr[$r['attraction_id']][] = $r;
}

// Sentiment per attraction
$attrSentiment = [];
foreach ($reviews as $r) {
    $attrSentiment[$r['attraction_id']][$r['sentiment']][] = $r['rating'];
}

// Municipality counts
$muniCount = array_count_values(array_column($all, 'municipality'));
arsort($muniCount);

// Category counts
$catCount = array_count_values(array_column($all, 'category'));

// Top rated (sorted by live rating)
$byRating = $all;
usort($byRating, fn($a, $b) =>
    ($attractionRatings[$b['id']] ?? 0) <=> ($attractionRatings[$a['id']] ?? 0)
);

$generatedAt = date('F j, Y \a\t g:i A');
$adminName   = htmlspecialchars($_SESSION['user']['name']);

// ═════════════════════════════════════════════════════════════════════════════
//  CSV EXPORT
// ═════════════════════════════════════════════════════════════════════════════
if ($format === 'csv') {

    $filename = 'VISTA-Rizal_Report_' . date('Y-m-d');

    switch ($section) {
        // ── All attractions ──────────────────────────────────────────────────
        case 'attractions':
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '_Attractions.csv"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
            fputcsv($out, ['ID', 'Name', 'Category', 'Municipality', 'Live Avg Rating', 'Review Count']);
            foreach ($byRating as $a) {
                $rc = count($reviewsByAttr[$a['id']] ?? []);
                fputcsv($out, [
                    $a['id'],
                    $a['name'],
                    ucfirst($a['category']),
                    $a['municipality'],
                    $attractionRatings[$a['id']] ?? 'N/A',
                    $rc,
                ]);
            }
            fclose($out);
            exit;

        // ── All reviews ──────────────────────────────────────────────────────
        case 'reviews':
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '_Reviews.csv"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Attraction', 'Reviewer', 'Rating', 'Sentiment', 'Comment', 'Date']);
            foreach (array_reverse($reviews) as $r) {
                $attr = getAttractionById((int) $r['attraction_id']);
                fputcsv($out, [
                    $attr['name'] ?? 'Unknown',
                    $r['user'],
                    $r['rating'],
                    ucfirst($r['sentiment']),
                    $r['text'] ?? $r['review_text'] ?? '',
                    $r['date'] ?? $r['created_at'] ?? '',
                ]);
            }
            fclose($out);
            exit;

        // ── Sentiment summary ────────────────────────────────────────────────
        case 'sentiment':
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '_Sentiment.csv"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Attraction', 'Positive', 'Neutral', 'Negative', 'Avg Rating']);
            foreach ($all as $a) {
                $data = $attrSentiment[$a['id']] ?? [];
                if (!$data) continue;
                $p   = count($data['positive'] ?? []);
                $n   = count($data['neutral']  ?? []);
                $ng  = count($data['negative'] ?? []);
                $allR = array_merge(...array_values($data));
                $avg  = $allR ? round(array_sum($allR) / count($allR), 1) : 'N/A';
                fputcsv($out, [$a['name'], $p, $n, $ng, $avg]);
            }
            fclose($out);
            exit;

        // ── Municipalities ───────────────────────────────────────────────────
        case 'municipalities':
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '_Municipalities.csv"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Municipality', 'Attraction Count']);
            foreach ($muniCount as $muni => $cnt) {
                fputcsv($out, [$muni, $cnt]);
            }
            fclose($out);
            exit;

        // ── Full report (multi-sheet workaround: combined CSV) ───────────────
        default: // 'full'
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '_Full.csv"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Summary
            fputcsv($out, ['VISTA-Rizal Full Report', 'Generated: ' . $generatedAt]);
            fputcsv($out, []);
            fputcsv($out, ['=== SUMMARY ===']);
            fputcsv($out, ['Total Attractions', count($all)]);
            fputcsv($out, ['Total Reviews', $total]);
            fputcsv($out, ['Overall Avg Rating', $avgScore]);
            fputcsv($out, ['Positive Reviews', $sentiments['positive'] ?? 0]);
            fputcsv($out, ['Neutral Reviews',  $sentiments['neutral']  ?? 0]);
            fputcsv($out, ['Negative Reviews', $sentiments['negative'] ?? 0]);
            fputcsv($out, ['Positive Rate %', $total ? round(($sentiments['positive'] ?? 0) / $total * 100) . '%' : '0%']);
            fputcsv($out, []);

            // Attraction leaderboard
            fputcsv($out, ['=== ATTRACTION LEADERBOARD ===']);
            fputcsv($out, ['Rank', 'Name', 'Category', 'Municipality', 'Avg Rating', 'Reviews']);
            $rank = 1;
            foreach ($byRating as $a) {
                if ($attractionRatings[$a['id']] === null) continue;
                fputcsv($out, [
                    $rank++,
                    $a['name'],
                    ucfirst($a['category']),
                    $a['municipality'],
                    $attractionRatings[$a['id']],
                    count($reviewsByAttr[$a['id']] ?? []),
                ]);
            }
            fputcsv($out, []);

            // Sentiment per attraction
            fputcsv($out, ['=== SENTIMENT PER ATTRACTION ===']);
            fputcsv($out, ['Attraction', 'Positive', 'Neutral', 'Negative', 'Avg Rating']);
            foreach ($all as $a) {
                $data = $attrSentiment[$a['id']] ?? [];
                if (!$data) continue;
                $p   = count($data['positive'] ?? []);
                $n   = count($data['neutral']  ?? []);
                $ng  = count($data['negative'] ?? []);
                $allR = array_merge(...array_values($data));
                $avg  = $allR ? round(array_sum($allR) / count($allR), 1) : 'N/A';
                fputcsv($out, [$a['name'], $p, $n, $ng, $avg]);
            }
            fputcsv($out, []);

            // Municipalities
            fputcsv($out, ['=== ATTRACTIONS BY MUNICIPALITY ===']);
            fputcsv($out, ['Municipality', 'Count']);
            foreach ($muniCount as $muni => $cnt) {
                fputcsv($out, [$muni, $cnt]);
            }
            fputcsv($out, []);

            // Categories
            fputcsv($out, ['=== ATTRACTIONS BY CATEGORY ===']);
            fputcsv($out, ['Category', 'Count']);
            foreach ($catCount as $cat => $cnt) {
                fputcsv($out, [ucfirst($cat), $cnt]);
            }

            fclose($out);
            exit;
    }
}

// ═════════════════════════════════════════════════════════════════════════════
//  PDF EXPORT  (printable HTML page — user saves via browser Print → Save PDF)
//  Works on any XAMPP setup with zero extra libraries.
// ═════════════════════════════════════════════════════════════════════════════

// Helper: simple bar as unicode blocks
function barBlock(float $pct, int $width = 20): string {
    $filled = (int) round($pct / 100 * $width);
    return str_repeat('█', $filled) . str_repeat('░', $width - $filled) . ' ' . round($pct, 1) . '%';
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VISTA-Rizal Analytics Report</title>
  <style>
    /* ── Reset & base ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', Arial, sans-serif;
      font-size: 12px;
      color: #1a1a2e;
      background: #fff;
      padding: 0;
    }

    /* ── Print controls (hidden when printing) ── */
    .print-bar {
      position: fixed; top: 0; left: 0; right: 0;
      background: #1a5276; color: #fff;
      padding: 10px 24px;
      display: flex; align-items: center; justify-content: space-between;
      z-index: 1000;
      box-shadow: 0 2px 8px rgba(0,0,0,.25);
    }
    .print-bar h2 { font-size: 1rem; font-weight: 700; }
    .print-bar .btns { display: flex; gap: 10px; }
    .print-bar button, .print-bar a {
      padding: 7px 18px; border-radius: 6px; border: none;
      font-size: .85rem; font-weight: 600; cursor: pointer;
      text-decoration: none; display: inline-block;
    }
    .btn-print  { background: #27ae60; color: #fff; }
    .btn-back   { background: rgba(255,255,255,.18); color: #fff; border: 1px solid rgba(255,255,255,.4); }
    .btn-csv    { background: #f39c12; color: #fff; }

    /* ── Page wrapper ── */
    .page {
      max-width: 900px;
      margin: 70px auto 40px;
      padding: 0 32px;
    }

    /* ── Cover ── */
    .cover {
      text-align: center;
      padding: 60px 0 40px;
      border-bottom: 3px solid #1a5276;
      margin-bottom: 36px;
    }
    .cover .logo-circle {
      width: 72px; height: 72px; border-radius: 50%;
      background: linear-gradient(135deg,#1a5276,#27ae60);
      color: #fff; font-size: 2rem; font-weight: 900;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 16px;
    }
    .cover h1 { font-size: 2rem; color: #1a5276; }
    .cover .subtitle { color: #555; margin-top: 6px; font-size: 1rem; }
    .cover .meta {
      margin-top: 18px; font-size: .82rem; color: #888;
      display: flex; justify-content: center; gap: 24px; flex-wrap: wrap;
    }

    /* ── Sections ── */
    .section {
      margin-bottom: 40px;
      page-break-inside: avoid;
    }
    .section-title {
      font-size: 1.05rem; font-weight: 800;
      color: #1a5276;
      border-left: 5px solid #27ae60;
      padding: 6px 0 6px 14px;
      margin-bottom: 16px;
      background: #f0f9f4;
    }

    /* ── Summary cards ── */
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin-bottom: 8px;
    }
    .sum-card {
      background: #f4f9ff;
      border: 1px solid #d6eaf8;
      border-top: 3px solid #1a5276;
      border-radius: 8px;
      padding: 14px 10px;
      text-align: center;
    }
    .sum-card.gold  { border-top-color: #f39c12; background: #fef9f0; border-color: #fde8b5; }
    .sum-card.green { border-top-color: #27ae60; background: #f0faf5; border-color: #b7e4c7; }
    .sum-card.red   { border-top-color: #e74c3c; background: #fdf0ef; border-color: #f5b7b1; }
    .sum-card .val  { font-size: 1.6rem; font-weight: 900; color: #1a5276; }
    .sum-card.gold  .val { color: #d68910; }
    .sum-card.green .val { color: #1e8449; }
    .sum-card.red   .val { color: #c0392b; }
    .sum-card .lbl  { font-size: .72rem; color: #666; margin-top: 4px; }

    /* ── Tables ── */
    table {
      width: 100%; border-collapse: collapse; font-size: 11.5px;
      margin-top: 4px;
    }
    thead tr { background: #1a5276; color: #fff; }
    thead th { padding: 8px 10px; text-align: left; font-weight: 700; }
    tbody tr:nth-child(even) { background: #f5f8fc; }
    tbody tr:hover { background: #e8f4fd; }
    tbody td { padding: 7px 10px; border-bottom: 1px solid #e8e8e8; vertical-align: top; }
    .badge {
      display: inline-block; padding: 2px 8px; border-radius: 10px;
      font-size: .75rem; font-weight: 700;
    }
    .badge-positive { background: #d5f5e3; color: #1e8449; }
    .badge-neutral  { background: #eaecee; color: #555;    }
    .badge-negative { background: #fadbd8; color: #c0392b; }
    .rank-badge {
      display: inline-flex; align-items: center; justify-content: center;
      width: 22px; height: 22px; border-radius: 50%;
      background: #1a5276; color: #fff;
      font-size: .72rem; font-weight: 900;
    }
    .rank-badge.gold-rank   { background: #f39c12; }
    .rank-badge.silver-rank { background: #95a5a6; }
    .rank-badge.bronze-rank { background: #a04000; }

    /* ── Bar charts ── */
    .bar-section { margin-top: 8px; }
    .bar-row {
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 8px; font-size: 11.5px;
    }
    .bar-label { width: 170px; min-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bar-track { flex: 1; height: 14px; background: #e8e8e8; border-radius: 7px; overflow: hidden; }
    .bar-fill  { height: 100%; background: linear-gradient(90deg,#1a5276,#27ae60); border-radius: 7px; transition: width .3s; }
    .bar-fill.gold-bar { background: linear-gradient(90deg,#f39c12,#f8c471); }
    .bar-fill.green-bar{ background: linear-gradient(90deg,#27ae60,#82e0aa); }
    .bar-fill.red-bar  { background: linear-gradient(90deg,#e74c3c,#f1948a); }
    .bar-fill.gray-bar { background: linear-gradient(90deg,#95a5a6,#bdc3c7); }
    .bar-val  { width: 38px; text-align: right; font-weight: 700; color: #1a5276; }

    /* ── Sentiment pills row ── */
    .sentiment-pills {
      display: flex; gap: 14px; margin: 12px 0 20px; flex-wrap: wrap;
    }
    .s-pill {
      flex: 1; min-width: 120px;
      border-radius: 10px; padding: 14px 10px; text-align: center;
      border-top: 3px solid #ccc;
    }
    .s-pill.positive { border-top-color: #27ae60; background: #f0faf5; }
    .s-pill.neutral  { border-top-color: #95a5a6; background: #f5f6f7; }
    .s-pill.negative { border-top-color: #e74c3c; background: #fdf0ef; }
    .s-pill .s-icon  { font-size: 1.5rem; }
    .s-pill .s-cnt   { font-size: 1.5rem; font-weight: 900; margin: 4px 0; }
    .s-pill.positive .s-cnt { color: #1e8449; }
    .s-pill.neutral  .s-cnt { color: #555;    }
    .s-pill.negative .s-cnt { color: #c0392b; }
    .s-pill .s-lbl   { font-size: .72rem; color: #666; }
    .s-pill .s-pct   { font-size: .78rem; color: #888; margin-top: 2px; }

    /* ── Category dots ── */
    .cat-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 5px; }
    .cat-nature    { background: #27ae60; }
    .cat-cultural  { background: #f39c12; }
    .cat-adventure { background: #8e44ad; }

    /* ── Footer ── */
    .footer {
      border-top: 2px solid #1a5276;
      padding-top: 14px; margin-top: 40px;
      text-align: center; color: #999; font-size: .78rem;
    }

    /* ── Print rules ── */
    @media print {
      .print-bar { display: none !important; }
      .page { margin-top: 0; }
      body { background: #fff; }
      .section { page-break-inside: avoid; }
      .cover { page-break-after: always; }
    }
    @page { margin: 18mm 16mm; }
  </style>
</head>
<body>

<!-- ── Print / export toolbar ── -->
<div class="print-bar">
  <h2>📊 VISTA-Rizal Analytics Report Preview</h2>
  <div class="btns">
    <a href="reports.php" class="btn-back">← Back to Reports</a>
    <a href="generate_report.php?format=csv&section=full" class="btn-csv">⬇ Download CSV</a>
    <button class="btn-print" onclick="window.print()">🖨 Save as PDF</button>
  </div>
</div>

<div class="page">

  <!-- ═══════ COVER PAGE ═══════ -->
  <div class="cover">
    <div class="logo-circle">VR</div>
    <h1>VISTA-Rizal Analytics Report</h1>
    <p class="subtitle">Tourism Destination Management System</p>
    <div class="meta">
      <span>📅 Generated: <?= $generatedAt ?></span>
      <span>👤 Prepared by: <?= $adminName ?></span>
      <span>📍 Rizal Province, Philippines</span>
    </div>
  </div>

  <!-- ═══════ SECTION 1: SUMMARY ═══════ -->
  <div class="section">
    <div class="section-title">1 · Executive Summary</div>
    <div class="summary-grid">
      <div class="sum-card">
        <div class="val"><?= count($all) ?></div>
        <div class="lbl">Total Attractions</div>
      </div>
      <div class="sum-card gold">
        <div class="val"><?= $total ?></div>
        <div class="lbl">Reviews Collected</div>
      </div>
      <div class="sum-card green">
        <div class="val"><?= $avgScore ?></div>
        <div class="lbl">Overall Avg Rating</div>
      </div>
      <div class="sum-card">
        <div class="val"><?= count(array_count_values(array_column($all, 'municipality'))) ?></div>
        <div class="lbl">Municipalities</div>
      </div>
      <div class="sum-card green">
        <div class="val"><?= $sentiments['positive'] ?? 0 ?></div>
        <div class="lbl">Positive Reviews</div>
      </div>
      <div class="sum-card">
        <div class="val"><?= $sentiments['neutral'] ?? 0 ?></div>
        <div class="lbl">Neutral Reviews</div>
      </div>
      <div class="sum-card red">
        <div class="val"><?= $sentiments['negative'] ?? 0 ?></div>
        <div class="lbl">Negative Reviews</div>
      </div>
      <div class="sum-card gold">
        <div class="val"><?= $total ? round(($sentiments['positive'] ?? 0) / $total * 100) : 0 ?>%</div>
        <div class="lbl">Positive Rate</div>
      </div>
    </div>
  </div>

  <!-- ═══════ SECTION 2: ATTRACTION LEADERBOARD ═══════ -->
  <div class="section">
    <div class="section-title">2 · Attraction Satisfaction Leaderboard</div>

    <div class="bar-section" style="margin-bottom:20px;">
      <?php
      $rank = 0;
      foreach ($byRating as $a):
        $lr = $attractionRatings[$a['id']];
        if ($lr === null) continue;
        $rank++;
        if ($rank > 10) break; // show top-10 in bar chart
        $pct = ($lr / 5) * 100;
      ?>
        <div class="bar-row">
          <span class="bar-label" title="<?= htmlspecialchars($a['name']) ?>">
            <?= htmlspecialchars(mb_strimwidth($a['name'], 0, 28, '…')) ?>
          </span>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="bar-val"><?= $lr ?></span>
          <span style="font-size:.72rem;color:#999;">(<?= count($reviewsByAttr[$a['id']] ?? []) ?> reviews)</span>
        </div>
      <?php endforeach; ?>
    </div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Attraction</th>
          <th>Category</th>
          <th>Municipality</th>
          <th>Avg Rating</th>
          <th>Reviews</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $rank = 0;
        foreach ($byRating as $a):
          $lr = $attractionRatings[$a['id']];
          if ($lr === null) continue;
          $rank++;
          $rc = count($reviewsByAttr[$a['id']] ?? []);
          $rankClass = $rank === 1 ? 'gold-rank' : ($rank === 2 ? 'silver-rank' : ($rank === 3 ? 'bronze-rank' : ''));
        ?>
          <tr>
            <td><span class="rank-badge <?= $rankClass ?>"><?= $rank ?></span></td>
            <td><?= htmlspecialchars($a['name']) ?></td>
            <td>
              <span class="cat-dot cat-<?= $a['category'] ?>"></span><?= ucfirst($a['category']) ?>
            </td>
            <td><?= htmlspecialchars($a['municipality']) ?></td>
            <td><strong><?= $lr ?></strong> / 5</td>
            <td><?= $rc ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ═══════ SECTION 3: SENTIMENT ANALYSIS ═══════ -->
  <div class="section">
    <div class="section-title">3 · Review Sentiment Analysis</div>

    <?php if (!$reviews): ?>
      <p style="color:#999;font-style:italic;">No reviews collected yet.</p>
    <?php else: ?>

      <div class="sentiment-pills">
        <?php
        $sPills = [
          'positive' => ['😊', $sentiments['positive'] ?? 0],
          'neutral'  => ['😐', $sentiments['neutral']  ?? 0],
          'negative' => ['😞', $sentiments['negative'] ?? 0],
        ];
        foreach ($sPills as $s => [$icon, $cnt]):
          $pct = $total ? round($cnt / $total * 100) : 0;
        ?>
          <div class="s-pill <?= $s ?>">
            <div class="s-icon"><?= $icon ?></div>
            <div class="s-cnt"><?= $cnt ?></div>
            <div class="s-lbl"><?= ucfirst($s) ?></div>
            <div class="s-pct"><?= $pct ?>% of reviews</div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Sentiment bars -->
      <div class="bar-section" style="margin-bottom:20px;">
        <?php
        $sColors = ['positive' => 'green-bar', 'neutral' => 'gray-bar', 'negative' => 'red-bar'];
        foreach (['positive', 'neutral', 'negative'] as $s):
          $cnt = $sentiments[$s] ?? 0;
          $pct = $total ? ($cnt / $total) * 100 : 0;
        ?>
          <div class="bar-row">
            <span class="bar-label" style="text-transform:capitalize;"><?= $s ?></span>
            <div class="bar-track">
              <div class="bar-fill <?= $sColors[$s] ?>" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="bar-val"><?= $cnt ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Sentiment per attraction table -->
      <table>
        <thead>
          <tr>
            <th>Attraction</th>
            <th>😊 Positive</th>
            <th>😐 Neutral</th>
            <th>😞 Negative</th>
            <th>Avg Rating</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all as $a):
            $data = $attrSentiment[$a['id']] ?? [];
            if (!$data) continue;
            $p    = count($data['positive'] ?? []);
            $n    = count($data['neutral']  ?? []);
            $ng   = count($data['negative'] ?? []);
            $allR = array_merge(...array_values($data));
            $avg  = $allR ? round(array_sum($allR) / count($allR), 1) : '—';
          ?>
            <tr>
              <td><?= htmlspecialchars($a['name']) ?></td>
              <td style="color:#1e8449;font-weight:700;"><?= $p ?></td>
              <td style="color:#555;font-weight:700;"><?= $n ?></td>
              <td style="color:#c0392b;font-weight:700;"><?= $ng ?></td>
              <td>★ <?= $avg ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- ═══════ SECTION 4: CATEGORY BREAKDOWN ═══════ -->
  <div class="section">
    <div class="section-title">4 · Attractions by Category</div>
    <div class="bar-section">
      <?php
      $maxCat = max(array_values($catCount) ?: [1]);
      foreach ($catCount as $cat => $cnt):
        $pct = ($cnt / $maxCat) * 100;
        $colorClass = ['nature' => 'green-bar', 'cultural' => 'gold-bar', 'adventure' => ''][$cat] ?? '';
      ?>
        <div class="bar-row">
          <span class="bar-label">
            <span class="cat-dot cat-<?= $cat ?>"></span><?= ucfirst($cat) ?>
          </span>
          <div class="bar-track">
            <div class="bar-fill <?= $colorClass ?>" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="bar-val"><?= $cnt ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ═══════ SECTION 5: MUNICIPALITIES ═══════ -->
  <div class="section">
    <div class="section-title">5 · Attractions by Municipality</div>
    <div class="bar-section" style="margin-bottom:16px;">
      <?php
      $maxMuni = max(array_values($muniCount) ?: [1]);
      foreach ($muniCount as $muni => $cnt):
        $pct = ($cnt / $maxMuni) * 100;
      ?>
        <div class="bar-row">
          <span class="bar-label"><?= htmlspecialchars($muni) ?></span>
          <div class="bar-track">
            <div class="bar-fill" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="bar-val"><?= $cnt ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <table>
      <thead>
        <tr><th>Municipality</th><th>Attractions</th><th>Share</th></tr>
      </thead>
      <tbody>
        <?php foreach ($muniCount as $muni => $cnt):
          $share = count($all) ? round($cnt / count($all) * 100, 1) : 0;
        ?>
          <tr>
            <td><?= htmlspecialchars($muni) ?></td>
            <td><?= $cnt ?></td>
            <td><?= $share ?>%</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ═══════ SECTION 6: FULL REVIEWS LOG ═══════ -->
  <?php if ($reviews): ?>
  <div class="section">
    <div class="section-title">6 · Full Reviews Log</div>
    <table>
      <thead>
        <tr>
          <th>Attraction</th>
          <th>Reviewer</th>
          <th>Rating</th>
          <th>Sentiment</th>
          <th>Comment</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_reverse($reviews) as $r):
          $attr = getAttractionById((int) $r['attraction_id']);
          $text = $r['text'] ?? $r['review_text'] ?? '';
        ?>
          <tr>
            <td><?= htmlspecialchars($attr['name'] ?? 'Unknown') ?></td>
            <td><?= htmlspecialchars($r['user']) ?></td>
            <td><?= str_repeat('★', (int)$r['rating']) ?><?= str_repeat('☆', 5 - (int)$r['rating']) ?></td>
            <td><span class="badge badge-<?= $r['sentiment'] ?>"><?= ucfirst($r['sentiment']) ?></span></td>
            <td style="max-width:200px;"><?= htmlspecialchars($text) ?></td>
            <td style="white-space:nowrap;"><?= htmlspecialchars($r['date'] ?? $r['created_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Footer -->
  <div class="footer">
    <p>VISTA-Rizal Tourism Information System &nbsp;·&nbsp; Report generated on <?= $generatedAt ?> by <?= $adminName ?></p>
    <p style="margin-top:4px;">Rizal Province, Philippines &nbsp;·&nbsp; Confidential – For Administrative Use Only</p>
  </div>

</div><!-- /.page -->

<script>
  // Auto-trigger print dialog if ?autoprint=1 in URL
  const params = new URLSearchParams(window.location.search);
  if (params.get('autoprint') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 600));
  }
</script>
</body>
</html>

<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
checkSessionTimeout();

$id = (int)($_GET['id']??0);
$a  = getAttractionById($id);
if (!$a) { header('Location: attractions.php'); exit; }

if (!isset($_SESSION['recently_viewed'])) $_SESSION['recently_viewed']=[];
if (!in_array($id,$_SESSION['recently_viewed'])) {
    array_unshift($_SESSION['recently_viewed'],$id);
    $_SESSION['recently_viewed'] = array_slice($_SESSION['recently_viewed'],0,10);
}

$csrf = generateCSRF();
$reviewMsg = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='review') {
    if (!verifyCSRF($_POST['csrf']??'')) { $reviewMsg='error:Invalid request.'; }
    else {
        $rating = (int)($_POST['rating']??0);
        $text   = trim($_POST['review_text']??'');
        if ($rating<1||$rating>5||empty($text)) { $reviewMsg='error:Please select a star rating and write a review.'; }
        else {
               addReview($id, (int)($_SESSION['user']['id'] ?? 0), $rating, $text);
            $reviewMsg='success:Thanks for your review!';
        }
    }
}

$reviews   = getReviews($id);
$avgRating = getAttractionRating($id);
?>
<!DOCTYPE html>
<html lang="en"<?= darkModeAttr() ?>>
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= htmlspecialchars($a['name']) ?> – VISTA-Rizal</title>
  <link rel="stylesheet" href="CSS\style.css">
  <style>
    /* ── Interactive star rating ── */
    .star-rating { display:flex; gap:6px; flex-direction:row-reverse; justify-content:flex-end; }
    .star-rating input { display:none; }
    .star-rating label {
      font-size:2rem; cursor:pointer; color:#ccc;
      transition:color .15s, transform .1s;
      user-select:none;
    }
    .star-rating input:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label {
      color:#f4c542;
    }
    .star-rating label:active { transform:scale(1.2); }

    /* Display-only stars */
    .stars-display { display:inline-flex; gap:2px; }
    .star-filled  { color:#f4c542; font-size:1.1rem; }
    .star-empty   { color:#ddd;    font-size:1.1rem; }
  </style>
</head>
<body>
<?= renderNav('attractions') ?>
<main class="container">

  <div class="detail-hero">
    <img src="<?= htmlspecialchars(getAttractionImage($a['name'])) ?>" alt="<?= htmlspecialchars($a['name']) ?>" onerror="this.src='images/placeholder.jpg'">
    <div class="detail-hero-overlay">
      <span class="category-badge category-<?= $a['category'] ?>"><?= ucfirst($a['category']) ?></span>
      <h1><?= htmlspecialchars($a['name']) ?></h1>
      <p> <?= htmlspecialchars($a['municipality']) ?>, Rizal</p>
    </div>
  </div>

  <!-- Meta -->
  <div class="detail-meta">
    <div class="meta-item">
      <div class="meta-label">Rating</div>
      <div class="meta-value">
        <?php if($avgRating): ?>
          ★<?= $avgRating ?>/5
        <?php else: ?>
          <span style="color:var(--text-muted);font-size:.85rem;">No ratings yet</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Reviews</div>
      <div class="meta-value"><?= count($reviews) ?></div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Category</div>
      <div class="meta-value"> <?= ucfirst($a['category']) ?></div>
    </div>
    <div class="meta-item">
      <div class="meta-label">Municipality</div>
      <div class="meta-value"> <?= htmlspecialchars($a['municipality']) ?></div>
   

  <div class="detail-content">
    <h2 style="margin-bottom:10px;">About this Place</h2>
    <p style="line-height:1.7;"><?= htmlspecialchars($a['fact']) ?></p>
  </div>

  <?php if(!empty($reviewMsg)): ?>
    <?php [$type,$msg] = explode(':',$reviewMsg,2); ?>
    <div class="notification <?= $type==='success'?'success':'error' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Review form with interactive stars -->
  <div class="review-form">
    <h3>Leave a Review</h3>
    <form method="POST" id="reviewForm">
      <input type="hidden" name="csrf"   value="<?= $csrf ?>">
      <input type="hidden" name="action" value="review">

      <div style="margin-bottom:16px;">
        <label style="font-weight:600;font-size:.9rem;display:block;margin-bottom:8px;">Your Rating <span style="color:var(--red)">*</span></label>
        <!-- Reversed order trick: last radio = star 5, rendered RTL via flex-direction:row-reverse -->
        <div class="star-rating" id="starRating">
          <?php for($s=5;$s>=1;$s--): ?>
            <input type="radio" name="rating" id="star<?= $s ?>" value="<?= $s ?>"
                   <?= (($_POST['rating']??0)==$s)?'checked':'' ?>>
            <label for="star<?= $s ?>" title="<?= $s ?> star<?= $s>1?'s':'' ?>">★</label>
          <?php endfor; ?>
        </div>
        <div id="ratingText" style="font-size:.8rem;color:var(--text-muted);margin-top:6px;">Click a star to rate</div>
      </div>

      <div class="input-group">
        <label>Your Review <span style="color:var(--red)">*</span></label>
        <textarea name="review_text" rows="3" required
          placeholder="Share your experience at <?= htmlspecialchars($a['name']) ?>…"
          style="resize:vertical;"><?= htmlspecialchars($_POST['review_text']??'') ?></textarea>
      </div>
      <button type="submit" class="btn-primary btn-sm" style="width:auto;">Submit Review</button>
    </form>
  </div>

  <?php if($reviews): ?>
    <div class="detail-content">
      <h3 style="margin-bottom:16px;">Reviews (<?= count($reviews) ?>)</h3>
      <?php foreach(array_reverse($reviews) as $r): ?>
        <div class="review-card">
          <div class="rev-header">
            <span class="rev-user"><?= htmlspecialchars($r['user']) ?></span>
            <span class="rev-date"><?= htmlspecialchars($r['created_at']) ?></span>
          </div>
          <div style="margin-bottom:6px;display:flex;align-items:center;gap:8px;">
            <span class="stars-display">
              <?php for($s=1;$s<=5;$s++): ?>
                <span class="<?= $s<=$r['rating']?'star-filled':'star-empty' ?>">★</span>
              <?php endfor; ?>
            </span>
            <span class="badge badge-<?= $r['sentiment'] ?>"><?= ucfirst($r['sentiment']) ?></span>
          </div>
          <p style="font-size:.9rem;"><?= htmlspecialchars($r['review_text']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div style="text-align:center;padding:30px 20px;color:var(--text-muted);">
      <div style="font-size:2.5rem;"></div>
      <p style="margin-top:8px;">No reviews yet. Be the first to share your experience!</p>
    </div>
  <?php endif; ?>

  <div style="margin-top:16px;">
    <a href="attractions.php" class="btn-secondary">← Back to Attractions</a>
  </div>
</main>

<script>
const labels = ['','Terrible','Poor','Okay','Good','Excellent'];
const radios  = document.querySelectorAll('.star-rating input');
const ratingText = document.getElementById('ratingText');

radios.forEach(r => {
  r.addEventListener('change', () => {
    ratingText.textContent = labels[r.value] + ' (' + r.value + '/5)';
    ratingText.style.color = 'var(--green)';
    ratingText.style.fontWeight = '600';
  });
});

// Restore label if pre-selected (page reload after error)
const checked = document.querySelector('.star-rating input:checked');
if (checked) {
  ratingText.textContent = labels[checked.value] + ' (' + checked.value + '/5)';
  ratingText.style.color = 'var(--green)';
  ratingText.style.fontWeight = '600';
}
</script>
</body>
</html>
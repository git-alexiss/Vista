<?php
include 'config.php';
if (!isset($_SESSION['logged_in'])||$_SESSION['logged_in']!==true) { header('Location: login.php'); exit; }
checkSessionTimeout();
$user = $_SESSION['user'];
$csrf = generateCSRF();
$msg  = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete_review') {
    if (!verifyCSRF($_POST['csrf']??'')) { $msg='error:Invalid request.'; }
    else {
        $rid = (int)($_POST['review_id']??0);
        $db  = getDB();
        // Make sure the review belongs to this user
        $stmt = $db->prepare('DELETE FROM reviews WHERE id=? AND user_id=?');
        $stmt->execute([$rid, $user['id']]);
        $msg = 'success:Review deleted.';
    }
}

// Handle edit
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='edit_review') {
    if (!verifyCSRF($_POST['csrf']??'')) { $msg='error:Invalid request.'; }
    else {
        $rid    = (int)($_POST['review_id']??0);
        $rating = (int)($_POST['rating']??0);
        $text   = trim($_POST['review_text']??'');
        if ($rating<1||$rating>5||empty($text)) {
            $msg='error:Please select a rating and write a review.';
        } else {
            $sentiment = classifyReview($text);
            $db = getDB();
            $stmt = $db->prepare('UPDATE reviews SET rating=?,review_text=?,sentiment=?,created_at=NOW() WHERE id=? AND user_id=?');
            $stmt->execute([$rating, htmlspecialchars($text,ENT_QUOTES,'UTF-8'), $sentiment, $rid, $user['id']]);
            $msg='success:Review updated!';
        }
    }
}

$editId = (int)($_GET['edit']??0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Profile – VISTA-Rizal</title>
  <link rel="stylesheet" href="CSS\style.css">
  <style>
    .star-rating { display:flex;gap:6px;flex-direction:row-reverse;justify-content:flex-end; }
    .star-rating input { display:none; }
    .star-rating label { font-size:1.6rem;cursor:pointer;color:#ccc;transition:color .15s; }
    .star-rating input:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label { color:#f4c542; }
    .edit-form { background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:16px;margin-top:10px; }
    .rev-actions { display:flex;gap:8px;margin-top:8px; }
    .btn-danger { background:var(--red);color:#fff;border:none;padding:6px 14px;border-radius:8px;cursor:pointer;font-size:.8rem;font-weight:600; }
    .btn-edit   { background:var(--green-pale);color:var(--green);border:none;padding:6px 14px;border-radius:8px;cursor:pointer;font-size:.8rem;font-weight:600; }
  </style>
</head>
<body<?= darkModeAttr() ?>>
<?= renderNav() ?>
<main class="container">
  <h1 style="font-size:1.6rem;margin-bottom:20px;">My Profile</h1>

  <?php if($msg): ?>
    <?php [$type,$text] = explode(':',$msg,2); ?>
    <div class="notification <?= $type==='success'?'success':'error' ?>"><?= htmlspecialchars($text) ?></div>
  <?php endif; ?>

  <div class="profile-card">
    <div class="profile-avatar-big"><?= strtoupper(substr($user['name']??'U',0,1)) ?></div>
    <div style="margin-bottom:16px;">
      <h2><?= htmlspecialchars($user['name']??'') ?></h2>
      <span class="badge badge-<?= ($user['role']??'')==='admin'?'positive':'neutral' ?>">
        <?= ucfirst($user['role']??'tourist') ?>
      </span>
    </div>
    <?php foreach([
      'Email'       => $user['email']       ?? 'N/A',
      'Address'     => $user['address']     ?? 'N/A',
      'Nationality' => $user['nationality'] ?? 'N/A',
      'Birthdate'   => $user['birthdate']   ?? 'N/A',
      'Account Role'=> ucfirst($user['role']??'tourist'),
    ] as $label=>$value): ?>
      <div class="profile-item">
        <span class="profile-label"><?= htmlspecialchars($label) ?></span>
        <span class="profile-value"><?= htmlspecialchars($value) ?></span>
      </div>
    <?php endforeach; ?>

    <div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap;">
      <a href="settings.php" class="btn-secondary">⚙ Settings</a>
      <a href="logout.php" class="btn-primary" style="background:var(--red);width:auto;padding:10px 20px;">🚪 Sign Out</a>
    </div>
  </div>

  <?php
  $myReviews = array_filter(getReviews(), fn($r)=>(int)$r['user_id']===(int)$user['id']);
  if($myReviews): ?>
    <div class="table-card" style="margin-top:24px;">
      <h3>My Reviews (<?= count($myReviews) ?>)</h3>
      <?php foreach(array_reverse(array_values($myReviews)) as $r):
        $attr = getAttractionById($r['attraction_id']);
        $isEditing = ($editId === (int)$r['id']);
      ?>
        <div class="review-card">
          <div class="rev-header">
            <a href="details.php?id=<?= $r['attraction_id'] ?>" style="font-weight:700;">
              <?= htmlspecialchars($attr['name']??'Unknown') ?>
            </a>
            <span class="rev-date"><?= htmlspecialchars(date('M j, Y', strtotime($r['created_at']))) ?></span>
          </div>

          <?php if(!$isEditing): ?>
            <div style="margin-bottom:4px;">
              <?= str_repeat('★',$r['rating']) ?>
              <span class="badge badge-<?= $r['sentiment'] ?>"><?= ucfirst($r['sentiment']) ?></span>
            </div>
            <p style="font-size:.9rem;"><?= htmlspecialchars($r['review_text']) ?></p>

            <div class="rev-actions">
              <a href="profile.php?edit=<?= $r['id'] ?>" class="btn-edit">✏ Edit</a>
              <form method="POST" onsubmit="return confirm('Delete this review?');" style="margin:0;">
                <input type="hidden" name="csrf"      value="<?= $csrf ?>">
                <input type="hidden" name="action"    value="delete_review">
                <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn-danger">🗑 Delete</button>
              </form>
            </div>

          <?php else: ?>
            <!-- Edit form -->
            <form method="POST" class="edit-form">
              <input type="hidden" name="csrf"      value="<?= $csrf ?>">
              <input type="hidden" name="action"    value="edit_review">
              <input type="hidden" name="review_id" value="<?= $r['id'] ?>">

              <label style="font-weight:600;font-size:.9rem;display:block;margin-bottom:6px;">Rating</label>
              <div class="star-rating" style="margin-bottom:12px;">
                <?php for($s=5;$s>=1;$s--): ?>
                  <input type="radio" name="rating" id="estr<?= $r['id'].$s ?>" value="<?= $s ?>"
                         <?= $r['rating']==$s?'checked':'' ?>>
                  <label for="estr<?= $r['id'].$s ?>">★</label>
                <?php endfor; ?>
              </div>

              <div class="input-group">
                <label>Review</label>
                <textarea name="review_text" rows="3" required style="resize:vertical;"><?= htmlspecialchars($r['review_text']) ?></textarea>
              </div>

              <div style="display:flex;gap:8px;margin-top:8px;">
                <button type="submit" class="btn-primary btn-sm" style="width:auto;">Save</button>
                <a href="profile.php" class="btn-secondary btn-sm">Cancel</a>
              </div>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div style="text-align:center;padding:30px;color:var(--text-muted);margin-top:24px;">
      <p>You haven't written any reviews yet.</p>
      <a href="attractions.php" class="btn-primary" style="width:auto;display:inline-block;margin-top:12px;">Explore Attractions</a>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
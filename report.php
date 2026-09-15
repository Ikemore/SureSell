<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

$listingId = filter_input(INPUT_GET, 'listing_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$errors = [];
$done = false;

$listing = null;
if ($listingId) {
    $stmt = db()->prepare('SELECT id, title, user_id FROM listings WHERE id = ? LIMIT 1');
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch();
}

$reasons = ['Suspected scam', 'Fake or misleading listing', 'Offensive content', 'Item already sold but still listed', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (too_many_recent_actions($user['id'], 'submit_report', 5, 60)) {
        $errors[] = 'You have submitted several reports recently. Please wait before submitting another.';
    }

    $reason  = clean_str($_POST['reason'] ?? '');
    $details = clean_str($_POST['details'] ?? '');

    if (!in_array($reason, $reasons, true)) $errors[] = 'Please choose a valid reason.';
    if (mb_strlen($details) > 500) $errors[] = 'Details must be under 500 characters.';

    if (!$errors) {
        db()->prepare('INSERT INTO reports (reporter_id, reported_user_id, reported_listing_id, reason, details)
                        VALUES (?, ?, ?, ?, ?)')
            ->execute([$user['id'], $listing['user_id'] ?? null, $listingId ?: null, $reason, $details ?: null]);
        $done = true;
    }
}

$pageTitle = 'Report a Problem';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="form-card">
    <h1>Report a problem</h1>
    <?php if ($listing): ?><p style="color:#666;">Reporting: <strong><?= e($listing['title']) ?></strong></p><?php endif; ?>

    <?php if ($done): ?>
      <div class="alert alert-success">Thank you — our team will review this report.</div>
    <?php else: ?>
      <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <?php if ($listingId): ?><input type="hidden" name="listing_id" value="<?= (int)$listingId ?>"><?php endif; ?>
        <div class="field">
          <label for="reason">Reason</label>
          <select id="reason" name="reason" required>
            <option value="">Choose a reason</option>
            <?php foreach ($reasons as $r): ?><option value="<?= e($r) ?>"><?= e($r) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="details">Additional details (optional)</label>
          <textarea id="details" name="details" maxlength="500"></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Submit report</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

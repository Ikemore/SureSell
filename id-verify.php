<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

$errors = [];
$stmt = db()->prepare('SELECT id_verified, id_document_path FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$row = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$row['id_verified']) {
    csrf_verify();
    $uploadError = null;
    $path = handle_image_upload($_FILES['id_document'] ?? [], 'ids', $uploadError);
    if ($uploadError) {
        $errors[] = $uploadError;
    } elseif (!$path) {
        $errors[] = 'Please choose a clear photo of your Ghana Card or other valid ID.';
    } else {
        db()->prepare('UPDATE users SET id_document_path = ? WHERE id = ?')->execute([$path, $user['id']]);
        flash_set('success', 'ID submitted. Our team will review it within 48 hours.');
        redirect('dashboard.php');
    }
}

$pageTitle = 'Get ID Verified';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="form-card">
    <h1>Get fully verified</h1>
    <p style="color:#666;">Upload a photo of your Ghana Card (or other government ID). After admin approval, and once your email and phone are verified, you will receive the green "Verified to trade" badge and can sell on SureSell.</p>

    <?php if ($row['id_verified']): ?>
      <div class="alert alert-success">You're already fully verified. Thank you!</div>
    <?php elseif ($row['id_document_path']): ?>
      <div class="alert alert-success">Your ID has been submitted and is awaiting review.</div>
    <?php else: ?>
      <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field">
          <label for="id_document">Photo of ID</label>
          <input type="file" id="id_document" name="id_document" accept="image/jpeg,image/png,image/webp" required>
          <div class="hint">Stored securely and only reviewed by our verification team — never shown publicly.</div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Submit for review</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

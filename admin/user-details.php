<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userId) {
    flash_set('error', 'Choose an account to view.');
    redirect('index.php');
}

$stmt = db()->prepare('SELECT id, full_name, business_name, email, phone, role, town, region, bio, avatar_path,
                               phone_verified, email_verified, id_verified, trust_score, deals_completed, status, created_at, updated_at
                        FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$account = $stmt->fetch();
if (!$account) {
    http_response_code(404);
    die('Account not found.');
}

$counts = db()->prepare('SELECT
    (SELECT COUNT(*) FROM listings WHERE user_id = ?) AS listings,
    (SELECT COUNT(*) FROM reports WHERE reporter_id = ? OR reported_user_id = ?) AS reports');
$counts->execute([$userId, $userId, $userId]);
$counts = $counts->fetch();

$pageTitle = 'Account Details';
require __DIR__ . '/../includes/header.php';
?>
<div class="wrap section">
  <p><a class="text-link" href="index.php">← Back to admin dashboard</a></p>
  <div class="form-card wide" style="margin:20px 0;max-width:760px;">
    <div class="admin-user-heading">
      <?php if ($account['avatar_path']): ?><img src="<?= APP_URL ?>/assets/uploads/<?= e($account['avatar_path']) ?>" alt="Profile picture of <?= e($account['full_name']) ?>">
      <?php else: ?><span><?= e(mb_substr($account['full_name'], 0, 1)) ?></span><?php endif; ?>
      <div><h1><?= e($account['business_name'] ?: $account['full_name']) ?></h1><p><?= e(ucfirst($account['role'])) ?> · <?= e(ucfirst($account['status'])) ?></p></div>
    </div>
    <dl class="admin-user-details">
      <dt>Full name</dt><dd><?= e($account['full_name']) ?></dd>
      <dt>Email</dt><dd><a href="mailto:<?= e($account['email']) ?>"><?= e($account['email']) ?></a></dd>
      <dt>Phone</dt><dd><?= e($account['phone']) ?></dd>
      <dt>Location</dt><dd><?= e($account['town']) ?>, <?= e($account['region']) ?> Region</dd>
      <dt>Verification</dt><dd><?= $account['phone_verified'] ? 'Phone verified' : 'Phone pending' ?> · <?= $account['email_verified'] ? 'Email verified' : 'Email pending' ?> · <?= $account['id_verified'] ? 'ID verified' : 'ID not verified' ?></dd>
      <dt>Activity</dt><dd><?= (int)$counts['listings'] ?> listings · <?= (int)$account['deals_completed'] ?> confirmed deals · <?= (int)$counts['reports'] ?> related reports</dd>
      <dt>Joined</dt><dd><?= e(date('j M Y, g:ia', strtotime($account['created_at']))) ?></dd>
      <?php if ($account['bio']): ?><dt>Bio</dt><dd><?= nl2br(e($account['bio'])) ?></dd><?php endif; ?>
    </dl>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

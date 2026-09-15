<?php
require_once __DIR__ . '/includes/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); die('Trader not found.'); }

$stmt = db()->prepare('SELECT id, full_name, business_name, town, region, bio, avatar_path, phone_verified, id_verified,
                               deals_completed, trust_score, created_at
                        FROM users WHERE id = ? AND status = "active" LIMIT 1');
$stmt->execute([$id]);
$trader = $stmt->fetch();
if (!$trader) { http_response_code(404); die('Trader not found.'); }

$listings = db()->prepare('SELECT l.id, l.title, l.price, l.town, l.created_at,
                                   u.full_name, u.avatar_path, u.phone_verified, u.id_verified,
                                   (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS thumb
                            FROM listings l JOIN users u ON u.id = l.user_id
                            WHERE l.user_id = ? AND l.status = "active" ORDER BY l.created_at DESC');
$listings->execute([$id]);
$listings = $listings->fetchAll();

$avgRating = db()->prepare('SELECT AVG(rating) AS avg_r, COUNT(*) AS cnt FROM reviews WHERE reviewee_id = ?');
$avgRating->execute([$id]);
$avgRating = $avgRating->fetch();

$badgeClass = $trader['id_verified'] ? 'verified' : ($trader['phone_verified'] ? 'phone' : 'unverified');
$badgeText  = $trader['id_verified'] ? 'Fully Verified' : ($trader['phone_verified'] ? 'Phone Verified' : 'Unverified');

$pageTitle = $trader['business_name'] ?: $trader['full_name'];
require __DIR__ . '/includes/header.php';
?>
<div class="wrap" style="margin-top:32px;">
  <div class="seller-card" style="max-width:640px;margin-bottom:40px;">
    <div class="seller-head">
      <?php if (!empty($trader['avatar_path'])): ?>
        <img class="seller-avatar-lg" src="<?= APP_URL ?>/assets/uploads/<?= e($trader['avatar_path']) ?>" alt="">
      <?php else: ?>
        <span class="seller-avatar-lg" style="display:flex;align-items:center;justify-content:center;font-weight:700;color:#a08e6a;font-size:1.4rem;"><?= e(mb_substr($trader['full_name'],0,1)) ?></span>
      <?php endif; ?>
      <div>
        <h1 style="font-size:1.4rem;margin-bottom:4px;"><?= e($trader['business_name'] ?: $trader['full_name']) ?></h1>
        <div class="trust-badge <?= $badgeClass ?>"><?= e($badgeText) ?></div>
      </div>
    </div>
    <div style="font-size:0.9rem;color:#555;margin-bottom:10px;">
      <?= e($trader['town']) ?> · <?= (int)$trader['deals_completed'] ?> confirmed deals · Trading since <?= e(date('M Y', strtotime($trader['created_at']))) ?>
      <?php if ($avgRating['cnt'] > 0): ?> · <?= number_format((float)$avgRating['avg_r'],1) ?>★ (<?= (int)$avgRating['cnt'] ?> reviews)<?php endif; ?>
    </div>
    <?php if ($trader['bio']): ?><p style="color:#555;"><?= e($trader['bio']) ?></p><?php endif; ?>
  </div>

  <h2 style="font-size:1.4rem;">Active listings</h2>
  <?php if (!$listings): ?>
    <div class="empty-state"><p>No active listings right now.</p></div>
  <?php else: ?>
    <div class="listing-grid" style="margin-bottom:48px;">
      <?php foreach ($listings as $item): ?>
        <?php include __DIR__ . '/includes/listing-card.php'; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

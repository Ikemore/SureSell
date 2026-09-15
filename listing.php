<?php
require_once __DIR__ . '/includes/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    die('Listing not found.');
}

$stmt = db()->prepare('SELECT l.*, u.id AS seller_id, u.full_name, u.business_name, u.phone, u.avatar_path,
                               u.phone_verified, u.id_verified, u.deals_completed, u.trust_score, u.bio, u.created_at AS seller_since
                        FROM listings l
                        JOIN users u ON u.id = l.user_id
                        WHERE l.id = ? LIMIT 1');
$stmt->execute([$id]);
$listing = $stmt->fetch();

if (!$listing || $listing['status'] === 'removed') {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap"><div class="empty-state"><h3>Listing not found</h3><p>This listing may have been removed.</p></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Increment view count (best-effort, not security sensitive)
db()->prepare('UPDATE listings SET views = views + 1 WHERE id = ?')->execute([$id]);

$images = db()->prepare('SELECT image_path FROM listing_images WHERE listing_id = ? ORDER BY sort_order');
$images->execute([$id]);
$images = $images->fetchAll();

$reviews = db()->prepare('SELECT r.rating, r.comment, r.created_at, u.full_name
                           FROM reviews r JOIN users u ON u.id = r.reviewer_id
                           WHERE r.reviewee_id = ? ORDER BY r.created_at DESC LIMIT 5');
$reviews->execute([$listing['seller_id']]);
$reviews = $reviews->fetchAll();

$user = current_user();
$isOwner = $user && (int)$user['id'] === (int)$listing['seller_id'];

$dealMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_deal'])) {
    csrf_verify();
    if (!$user) {
        redirect('login.php');
    }
    if ($isOwner) {
        $dealMessage = "You can't record a deal on your own listing.";
    } elseif (too_many_recent_actions($user['id'], 'request_deal', 10, 60)) {
        $dealMessage = 'Too many requests. Please try again shortly.';
    } else {
        $existing = db()->prepare('SELECT id FROM deals WHERE listing_id = ? AND buyer_id = ? AND status = "requested"');
        $existing->execute([$id, $user['id']]);
        if ($existing->fetch()) {
            $dealMessage = 'You already have a pending confirmation request for this item.';
        } else {
            db()->prepare('INSERT INTO deals (listing_id, seller_id, buyer_id, status) VALUES (?, ?, ?, "requested")')
                ->execute([$id, $listing['seller_id'], $user['id']]);
            $dealMessage = 'Request sent — the seller will confirm once the deal is complete.';
        }
    }
}

$revealedPhone = null;
$revealError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reveal_contact'])) {
    csrf_verify();
    if (!$user) {
        redirect('login.php');
    }
    if (too_many_recent_actions($user['id'], 'reveal_contact', 20, 60)) {
        $revealError = 'Too many requests. Please try again shortly.';
    } else {
        db()->prepare('INSERT INTO contact_reveals (viewer_id, listing_id, ip_address) VALUES (?, ?, ?)')
            ->execute([$user['id'], $id, $_SERVER['REMOTE_ADDR'] ?? null]);
        $revealedPhone = $listing['phone'];
    }
}

$badgeClass = $listing['id_verified'] ? 'verified' : ($listing['phone_verified'] ? 'phone' : 'unverified');
$badgeText  = $listing['id_verified'] ? 'Fully Verified' : ($listing['phone_verified'] ? 'Phone Verified' : 'Unverified');

$pageTitle = $listing['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="listing-detail" style="margin-top:28px;">
    <div>
      <div class="detail-gallery-main">
        <?php if ($images): ?>
          <img src="<?= APP_URL ?>/assets/uploads/<?= e($images[0]['image_path']) ?>" alt="<?= e($listing['title']) ?>">
        <?php else: ?>
          <div class="no-image" style="height:100%;">No photo provided</div>
        <?php endif; ?>
      </div>
      <?php if (count($images) > 1): ?>
        <div class="detail-thumbs">
          <?php foreach ($images as $i => $img): ?>
            <img src="<?= APP_URL ?>/assets/uploads/<?= e($img['image_path']) ?>" data-full="<?= APP_URL ?>/assets/uploads/<?= e($img['image_path']) ?>" class="<?= $i===0?'active':'' ?>" alt="">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div style="margin-top:28px;">
        <h1 style="font-size:1.8rem;"><?= e($listing['title']) ?></h1>
        <div class="listing-price" style="font-size:1.6rem;margin-bottom:12px;">
          GH₵ <?= number_format((float)$listing['price'], 2) ?>
          <?php if ($listing['negotiable']): ?><span style="font-size:0.9rem;color:#777;font-weight:400;">(negotiable)</span><?php endif; ?>
        </div>
        <div class="listing-meta" style="margin-bottom:20px;"><?= e($listing['town']) ?> · Listed <?= e(date('M j, Y', strtotime($listing['created_at']))) ?> · <?= (int)$listing['views'] ?> views</div>
        <p style="white-space:pre-line;"><?= e($listing['description']) ?></p>
      </div>

      <?php if ($reviews): ?>
        <div style="margin-top:36px;">
          <h3>What buyers say about this trader</h3>
          <?php foreach ($reviews as $rev): ?>
            <div style="border-bottom:1px solid var(--line);padding:14px 0;">
              <strong><?= e($rev['full_name']) ?></strong> — <?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5-(int)$rev['rating']) ?>
              <?php if ($rev['comment']): ?><p style="margin:6px 0 0;color:#555;"><?= e($rev['comment']) ?></p><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <div class="seller-card">
        <div class="seller-head">
          <?php if (!empty($listing['avatar_path'])): ?>
            <img class="seller-avatar-lg" src="<?= APP_URL ?>/assets/uploads/<?= e($listing['avatar_path']) ?>" alt="">
          <?php else: ?>
            <span class="seller-avatar-lg" style="display:flex;align-items:center;justify-content:center;font-weight:700;color:#a08e6a;font-size:1.3rem;"><?= e(mb_substr($listing['full_name'],0,1)) ?></span>
          <?php endif; ?>
          <div>
            <div style="font-weight:700;"><?= e($listing['business_name'] ?: $listing['full_name']) ?></div>
            <div class="trust-badge <?= $badgeClass ?>" style="margin-top:4px;"><?= e($badgeText) ?></div>
          </div>
        </div>
        <div style="font-size:0.88rem;color:#555;margin-bottom:14px;">
          <?= (int)$listing['deals_completed'] ?> confirmed deals · Trading since <?= e(date('M Y', strtotime($listing['seller_since']))) ?>
        </div>
        <?php if ($listing['bio']): ?><p style="font-size:0.9rem;color:#555;"><?= e($listing['bio']) ?></p><?php endif; ?>

        <?php if ($isOwner): ?>
          <a href="edit-listing.php?id=<?= (int)$listing['id'] ?>" class="btn btn-outline btn-block" style="margin-top:10px;">Edit this listing</a>
        <?php elseif ($revealedPhone): ?>
          <div class="reveal-box">
            <div style="font-size:0.8rem;color:#777;margin-bottom:4px;">Seller's phone</div>
            <div style="font-family:var(--font-head);font-size:1.3rem;color:var(--indigo);"><?= e($revealedPhone) ?></div>
          </div>
        <?php else: ?>
          <?php if ($revealError): ?><div class="alert alert-error"><?= e($revealError) ?></div><?php endif; ?>
          <div class="reveal-box">
            <div style="font-size:0.8rem;color:#777;margin-bottom:4px;">Phone number</div>
            <div style="font-family:var(--font-head);font-size:1.3rem;color:var(--indigo);"><?= e(mask_phone($listing['phone'])) ?></div>
          </div>
          <form method="post">
            <?= csrf_field() ?>
            <button type="submit" name="reveal_contact" value="1" class="btn btn-gold btn-block">Reveal phone number</button>
          </form>
        <?php endif; ?>

        <div class="safety-note">
          <strong>Stay safe:</strong> meet in a public place, inspect the item before paying, and never send money in advance for delivery.
        </div>

        <?php if ($user && !$isOwner): ?>
          <?php if ($dealMessage): ?><div class="alert alert-success" style="margin-top:14px;"><?= e($dealMessage) ?></div><?php endif; ?>
          <form method="post" style="margin-top:14px;">
            <?= csrf_field() ?>
            <button type="submit" name="request_deal" value="1" class="btn btn-outline btn-block">I bought this — notify seller</button>
          </form>
          <a href="report.php?listing_id=<?= (int)$listing['id'] ?>" style="display:block;text-align:center;margin-top:14px;font-size:0.85rem;color:#a83a3a;">Report this listing</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
/** Expects $item with keys: id, title, price, town, created_at, full_name, avatar_path, phone_verified, id_verified, thumb */
$badge = verification_badge($item);
?>
<div class="listing-card-shell">
  <label class="listing-card-toggle" for="compare-listing-<?= (int)$item['id'] ?>">
    <input id="compare-listing-<?= (int)$item['id'] ?>" class="listing-card-selector" type="checkbox" value="<?= (int)$item['id'] ?>">
    <span>Select</span>
  </label>

  <a class="listing-card" href="listing.php?id=<?= (int)$item['id'] ?>" style="color:inherit;">
    <div class="listing-thumb">
      <?php if (!empty($item['thumb'])): ?>
        <img src="<?= APP_URL ?>/assets/uploads/<?= e($item['thumb']) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
      <?php else: ?>
        <div class="no-image">No photo</div>
      <?php endif; ?>
    </div>
    <div class="listing-body">
      <div class="listing-price">GH₵ <?= number_format((float)$item['price'], 2) ?></div>
      <div class="listing-title"><?= e($item['title']) ?></div>
      <div class="listing-meta"><?= e($item['category_name'] ?? 'Uncategorized') ?> · <?= e($item['town']) ?> · <?= e(date('M j', strtotime($item['created_at']))) ?></div>
      <div class="seller-row">
        <?php if (!empty($item['avatar_path'])): ?>
          <img class="seller-avatar" src="<?= APP_URL ?>/assets/uploads/<?= e($item['avatar_path']) ?>" alt="">
        <?php else: ?>
          <span class="seller-avatar" style="display:flex;align-items:center;justify-content:center;font-weight:700;color:#a08e6a;"><?= e(mb_substr($item['full_name'],0,1)) ?></span>
        <?php endif; ?>
        <span class="seller-name"><?= e($item['full_name']) ?></span>
        <span class="trust-badge <?= e($badge['class']) ?>"><span aria-hidden="true">✓</span> <?= e($badge['text']) ?></span>
      </div>
    </div>
  </a>
</div>

<?php
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
ensure_compare_tables();

$selectedIds = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['listing_ids'])) {
    $rawIds = explode(',', (string) $_POST['listing_ids']);
    foreach ($rawIds as $rawId) {
        $id = filter_var(trim($rawId), FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0) {
            $selectedIds[] = (int) $id;
        }
    }
    $selectedIds = array_values(array_unique($selectedIds));
} elseif (!empty($_GET['ids'])) {
    $rawIds = explode(',', (string) $_GET['ids']);
    foreach ($rawIds as $rawId) {
        $id = filter_var(trim($rawId), FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0) {
            $selectedIds[] = (int) $id;
        }
    }
    $selectedIds = array_values(array_unique($selectedIds));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['compare_request'])) {
    csrf_verify();

    if (count($selectedIds) < 2 || count($selectedIds) > 5) {
        flash_set('error', 'Please select between 2 and 5 listings to compare and contact.');
        redirect('browse.php');
    }

    $idsForQuery = implode(',', array_fill(0, count($selectedIds), '?'));
    $stmt = db()->prepare(
        'SELECT l.id, l.title, l.price, l.town, l.region, u.id AS seller_id, u.full_name, u.business_name,
                u.phone_verified, u.id_verified, u.deals_completed, u.avatar_path,
                COALESCE(AVG(r.rating), 0) AS rating, COUNT(r.id) AS review_count,
                (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS thumb
         FROM listings l
         JOIN users u ON u.id = l.user_id
         LEFT JOIN reviews r ON r.reviewee_id = u.id
         WHERE l.status = "active" AND l.id IN (' . $idsForQuery . ')
         GROUP BY l.id, l.title, l.price, l.town, l.region, u.id, u.full_name, u.business_name, u.phone_verified,
                  u.id_verified, u.deals_completed, u.avatar_path, l.created_at
         ORDER BY l.created_at DESC'
    );
    $stmt->execute($selectedIds);
    $selectedListings = $stmt->fetchAll();

    if (count($selectedListings) < 2) {
        flash_set('error', 'Some selected listings are no longer available. Please choose again.');
        redirect('browse.php');
    }

    $message = clean_str((string) ($_POST['message'] ?? ''));
    if ($message === '') {
        $message = 'Hi, I would like to compare your offer and buy from the best option.';
    }
    if (mb_strlen($message) > 1000) {
        flash_set('error', 'Your message is too long. Please keep it under 1000 characters.');
        redirect('browse.php');
    }

    $revealContact = !empty($_POST['reveal_contact']) ? 1 : 0;

    db()->beginTransaction();
    try {
        $insertRequest = db()->prepare('INSERT INTO buyer_requests (buyer_id, message, reveal_contact) VALUES (?, ?, ?)');
        $insertRequest->execute([$user['id'], $message, $revealContact]);
        $requestId = db()->lastInsertId();

        $insertItem = db()->prepare('INSERT INTO buyer_request_items (request_id, seller_id, listing_id, status) VALUES (?, ?, ?, "pending")');
        foreach ($selectedListings as $listing) {
            $insertItem->execute([(int) $requestId, (int) $listing['seller_id'], (int) $listing['id']]);
        }

        db()->commit();
    } catch (Exception $e) {
        db()->rollBack();
        flash_set('error', 'Could not send your comparison request. Please try again.');
        redirect('browse.php');
    }

    flash_set('success', 'Your comparison request was sent to ' . count($selectedListings) . ' seller(s).');
    redirect('dashboard.php');
}

if (count($selectedIds) < 2 || count($selectedIds) > 5) {
    flash_set('error', 'Please select between 2 and 5 listings to compare and contact.');
    redirect('browse.php');
}

$idsForQuery = implode(',', array_fill(0, count($selectedIds), '?'));
$stmt = db()->prepare(
    'SELECT l.id, l.title, l.price, l.town, l.region, l.created_at, u.id AS seller_id, u.full_name, u.business_name,
            u.phone_verified, u.id_verified, u.deals_completed, u.avatar_path,
            COALESCE(AVG(r.rating), 0) AS rating, COUNT(r.id) AS review_count,
            (SELECT image_path FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS thumb,
            c.name AS category_name
     FROM listings l
     JOIN users u ON u.id = l.user_id
     JOIN categories c ON c.id = l.category_id
     LEFT JOIN reviews r ON r.reviewee_id = u.id
     WHERE l.status = "active" AND l.id IN (' . $idsForQuery . ')
     GROUP BY l.id, l.title, l.price, l.town, l.region, l.created_at, u.id, u.full_name, u.business_name,
              u.phone_verified, u.id_verified, u.deals_completed, u.avatar_path, c.name
     ORDER BY l.created_at DESC'
);
$stmt->execute($selectedIds);
$selectedListings = $stmt->fetchAll();

if (count($selectedListings) < 2) {
    flash_set('error', 'Some selected listings are no longer available. Please choose again.');
    redirect('browse.php');
}

$pageTitle = 'Compare sellers';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap compare-page" style="margin-top:28px;">
  <div class="compare-hero">
    <div>
      <p class="compare-kicker">Compare &amp; contact</p>
      <h1>Shop smarter by comparing a few verified sellers at once.</h1>
      <p class="compare-lead">Instead of messaging one seller and waiting, you can compare prices, ratings, and response times side by side, then send one request to each seller individually.</p>
    </div>
    <a href="browse.php" class="btn btn-primary compare-back-link">Back to browse</a>
  </div>

  <div class="compare-showcase" aria-label="Compare and contact flow preview">
    <div class="compare-showcase-card">
      <div class="compare-showcase-tag">STEP 1</div>
      <div class="compare-phone">
        <div class="compare-notch"></div>
        <div class="compare-screen">
          <div class="compare-topbar">
            <span class="compare-back-arrow">←</span>
            <div class="compare-search-pill">Ankara fabric · 6 yards</div>
          </div>
          <div class="compare-list">
            <div class="compare-result compare-result-selected">
              <div class="compare-thumb compare-thumb-gold">🧵</div>
              <div class="compare-result-copy">
                <div class="compare-result-title">Premium Ankara — 6 yards</div>
                <div class="compare-result-meta">Ama Boateng · Kumasi <span class="compare-mini-badge">★4.9</span></div>
                <div class="compare-price">GH₵ 190</div>
              </div>
              <span class="compare-check compare-check-on">✓</span>
            </div>
            <div class="compare-result compare-result-selected">
              <div class="compare-thumb compare-thumb-navy">🧵</div>
              <div class="compare-result-copy">
                <div class="compare-result-title">Ankara wax print, 6yds</div>
                <div class="compare-result-meta">Yaw Darko · Kumasi <span class="compare-mini-badge">★4.6</span></div>
                <div class="compare-price">GH₵ 175</div>
              </div>
              <span class="compare-check compare-check-on">✓</span>
            </div>
            <div class="compare-result">
              <div class="compare-thumb compare-thumb-palm">🧵</div>
              <div class="compare-result-copy">
                <div class="compare-result-title">Original Dutch Ankara</div>
                <div class="compare-result-meta">Efua Mensah · Cape Coast <span class="compare-mini-badge">★5.0</span></div>
                <div class="compare-price">GH₵ 210</div>
              </div>
              <span class="compare-check">○</span>
            </div>
          </div>
          <div class="compare-float-bar">
            <div>
              <div class="compare-float-count">2 sellers selected</div>
              <div class="compare-float-small">Up to 5 at once</div>
            </div>
            <div class="compare-float-cta">Compare →</div>
          </div>
        </div>
      </div>
      <p class="compare-caption"><strong>Browse as normal</strong> — every listing card is selectable.</p>
    </div>

    <div class="compare-showcase-card">
      <div class="compare-showcase-tag">STEP 2</div>
      <div class="compare-phone">
        <div class="compare-notch"></div>
        <div class="compare-screen">
          <div class="compare-topbar">
            <span class="compare-back-arrow">←</span>
            <div class="compare-screen-title">Compare 2 sellers</div>
          </div>
          <div class="compare-compare-list">
            <div class="compare-swap-card">
              <button class="compare-remove" type="button" aria-label="Remove seller">✕</button>
              <div class="compare-swap-head">
                <div class="compare-avatar compare-avatar-palm">AB</div>
                <div>
                  <div class="compare-name">Ama Boateng</div>
                  <div class="compare-location">Kumasi <span class="compare-mini-badge">Verified</span></div>
                </div>
              </div>
              <div class="compare-stat-grid">
                <div class="compare-stat"><span>Price</span><strong>GH₵ 190</strong></div>
                <div class="compare-stat"><span>Rating</span><strong>★4.9</strong></div>
                <div class="compare-stat"><span>Replies</span><strong>~1 hr</strong></div>
                <div class="compare-stat"><span>Delivery</span><strong>Yes</strong></div>
              </div>
            </div>
            <div class="compare-swap-card">
              <button class="compare-remove" type="button" aria-label="Remove seller">✕</button>
              <div class="compare-swap-head">
                <div class="compare-avatar compare-avatar-gold">YD</div>
                <div>
                  <div class="compare-name">Yaw Darko</div>
                  <div class="compare-location">Kumasi <span class="compare-mini-badge">Verified</span></div>
                </div>
              </div>
              <div class="compare-stat-grid">
                <div class="compare-stat"><span>Price</span><strong>GH₵ 175</strong></div>
                <div class="compare-stat"><span>Rating</span><strong>★4.6</strong></div>
                <div class="compare-stat"><span>Replies</span><strong>~3 hrs</strong></div>
                <div class="compare-stat"><span>Delivery</span><strong>Pickup</strong></div>
              </div>
            </div>
            <div class="compare-add-more">+ Add another seller</div>
          </div>
          <div class="compare-bottom-btn">Message both sellers →</div>
        </div>
      </div>
      <p class="compare-caption"><strong>Side-by-side comparison</strong> — price, rating, response time, and location up front.</p>
    </div>

    <div class="compare-showcase-card">
      <div class="compare-showcase-tag">STEP 3</div>
      <div class="compare-phone">
        <div class="compare-notch"></div>
        <div class="compare-screen">
          <div class="compare-topbar">
            <span class="compare-back-arrow">←</span>
            <div class="compare-screen-title">Message 2 sellers</div>
          </div>
          <div class="compare-to-chips">
            <span class="compare-chip"><span class="compare-chip-dot compare-dot-palm"></span>Ama</span>
            <span class="compare-chip"><span class="compare-chip-dot compare-dot-gold"></span>Yaw</span>
          </div>
          <div class="compare-note-box">Sent as separate messages to each seller. They’ll see you’re comparing a few offers — that’s normal on SureSell.</div>
          <div class="compare-message-box">Hi! Is this still available? What’s your best price for 6 yards, and can you deliver to Adum?</div>
          <div class="compare-toggle-row">
            <div>
              <div class="compare-toggle-label">Also request phone number</div>
              <div class="compare-toggle-help">They choose whether to share it</div>
            </div>
            <div class="compare-toggle"><span class="compare-toggle-knob"></span></div>
          </div>
          <div class="compare-bottom-btn compare-bottom-btn-spaced">Send to 2 sellers</div>
        </div>
      </div>
      <p class="compare-caption"><strong>One message, sent individually</strong> — not a group chat, and contact requests stay opt-in per seller.</p>
    </div>

    <div class="compare-showcase-card">
      <div class="compare-showcase-tag">STEP 4</div>
      <div class="compare-phone">
        <div class="compare-notch"></div>
        <div class="compare-screen">
          <div class="compare-topbar">
            <span class="compare-back-arrow">←</span>
            <div class="compare-screen-title">Ankara fabric requests</div>
          </div>
          <div class="compare-request-list">
            <div class="compare-request-row">
              <div class="compare-avatar compare-avatar-palm compare-avatar-small">AB</div>
              <div class="compare-request-copy">
                <div class="compare-request-name">Ama Boateng</div>
                <div class="compare-request-sub">Replied · 2 min ago</div>
              </div>
              <span class="compare-status compare-status-replied">GH₵ 180</span>
            </div>
            <div class="compare-request-row">
              <div class="compare-avatar compare-avatar-gold compare-avatar-small">YD</div>
              <div class="compare-request-copy">
                <div class="compare-request-name">Yaw Darko</div>
                <div class="compare-request-sub">Sent 2 min ago</div>
              </div>
              <span class="compare-status compare-status-pending">Pending</span>
            </div>
          </div>
          <div class="compare-winner-card">
            <div class="compare-winner-label">BEST REPLY SO FAR</div>
            <div class="compare-winner-price">Ama · GH₵ 180</div>
            <div class="compare-winner-btn">Buy from Ama — protected</div>
          </div>
          <div class="compare-foot-note">Choosing a seller lets the others know you’ve gone with someone else.</div>
        </div>
      </div>
      <p class="compare-caption"><strong>One inbox for all replies</strong> — buyer compares live, then checks out with confidence.</p>
    </div>
  </div>

  <section class="compare-request-panel compare-panel-top-space">
    <h2>Selected listings</h2>
    <p>Your request will be sent individually to each seller while you still keep one place to compare replies.</p>

    <div class="compare-grid">
      <?php foreach ($selectedListings as $listing): ?>
        <?php
        $badgeClass = $listing['id_verified'] ? 'verified' : ($listing['phone_verified'] ? 'phone' : 'unverified');
        $badgeText = $listing['id_verified'] ? 'Fully Verified' : ($listing['phone_verified'] ? 'Phone Verified' : 'Unverified');
        $responseHint = seller_response_hint($listing);
        ?>
        <article class="compare-card compare-live-card">
          <div class="compare-thumb">
            <?php if (!empty($listing['thumb'])): ?>
              <img src="<?= APP_URL ?>/assets/uploads/<?= e($listing['thumb']) ?>" alt="<?= e($listing['title']) ?>" loading="lazy">
            <?php else: ?>
              <div class="no-image">No photo</div>
            <?php endif; ?>
          </div>
          <div class="compare-body">
            <div class="compare-title-row">
              <h2><?= e($listing['title']) ?></h2>
              <span class="listing-price">GH₵ <?= number_format((float)$listing['price'], 2) ?></span>
            </div>
            <div class="compare-meta">
              <span><?= e($listing['category_name'] ?? 'Uncategorized') ?></span>
              <span><?= e($listing['town']) ?></span>
              <span><?= e($listing['region']) ?></span>
            </div>

            <div class="compare-seller">
              <?php if (!empty($listing['avatar_path'])): ?>
                <img src="<?= APP_URL ?>/assets/uploads/<?= e($listing['avatar_path']) ?>" alt="" class="seller-avatar-lg">
              <?php else: ?>
                <span class="seller-avatar-lg seller-avatar-placeholder"><?= e(mb_substr($listing['full_name'], 0, 1)) ?></span>
              <?php endif; ?>
              <div>
                <strong><?= e($listing['business_name'] ?: $listing['full_name']) ?></strong>
                <div class="compare-badges">
                  <span class="trust-badge <?= $badgeClass ?>"><?= e($badgeText) ?></span>
                  <span class="response-badge"><?= e($responseHint) ?></span>
                </div>
              </div>
            </div>

            <div class="compare-stats">
              <div>
                <small>Rating</small>
                <strong><?= number_format((float) $listing['rating'], 1) ?> / 5</strong>
              </div>
              <div>
                <small>Trades</small>
                <strong><?= (int) $listing['deals_completed'] ?></strong>
              </div>
              <div>
                <small>Reviews</small>
                <strong><?= (int) $listing['review_count'] ?></strong>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <form method="post" class="compare-message-form">
      <?= csrf_field() ?>
      <input type="hidden" name="listing_ids" value="<?= e(implode(',', $selectedIds)) ?>">
      <div class="field">
        <label for="compareMessage">Message</label>
        <textarea id="compareMessage" name="message" rows="5" placeholder="Hi, I’m comparing offers and would like to see the best option for this item.">Hi, I’m comparing offers and would like to see the best option for this item.</textarea>
      </div>

      <label class="compare-checkbox-row">
        <input type="checkbox" name="reveal_contact" value="1">
        <span>Also request contact info from the sellers who are available to share it.</span>
      </label>

      <div class="compare-actions">
        <button type="submit" name="compare_request" value="1" class="btn btn-primary">Send request to sellers</button>
        <a href="browse.php" class="btn btn-outline">Back to browse</a>
      </div>
    </form>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

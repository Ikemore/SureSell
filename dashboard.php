<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

$stats = db()->prepare('SELECT
    (SELECT COUNT(*) FROM listings WHERE user_id = ?) AS total_listings,
    (SELECT COUNT(*) FROM listings WHERE user_id = ? AND status = "active") AS active_listings,
    (SELECT COUNT(*) FROM deals WHERE seller_id = ? AND status = "confirmed") AS confirmed_deals,
    (SELECT COUNT(*) FROM deals WHERE seller_id = ? AND status = "requested") AS pending_deals
');
$stats->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$stats = $stats->fetch();

$myListings = db()->prepare('SELECT id, title, price, status, views, created_at FROM listings WHERE user_id = ? ORDER BY created_at DESC');
$myListings->execute([$user['id']]);
$myListings = $myListings->fetchAll();

$pendingDeals = db()->prepare('SELECT d.id, d.created_at, l.title, u.full_name AS buyer_name
                                FROM deals d
                                JOIN listings l ON l.id = d.listing_id
                                JOIN users u ON u.id = d.buyer_id
                                WHERE d.seller_id = ? AND d.status = "requested"
                                ORDER BY d.created_at DESC');
$pendingDeals->execute([$user['id']]);
$pendingDeals = $pendingDeals->fetchAll();

$buyerRequests = db()->prepare('SELECT br.id, br.message, br.reveal_contact, br.created_at,
                                      bri.status, bri.listing_id, l.title AS listing_title,
                                      u.full_name AS seller_name
                                FROM buyer_requests br
                                LEFT JOIN buyer_request_items bri ON bri.request_id = br.id
                                LEFT JOIN listings l ON l.id = bri.listing_id
                                LEFT JOIN users u ON u.id = bri.seller_id
                                WHERE br.buyer_id = ?
                                ORDER BY br.created_at DESC, bri.id ASC');
$buyerRequests->execute([$user['id']]);
$buyerRequests = $buyerRequests->fetchAll();

$pageTitle = 'My Dashboard';
$__page = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap" style="margin-top:28px;">
  <h1 style="font-size:1.9rem;">Welcome back, <?= e(explode(' ', $user['full_name'])[0]) ?></h1>

  <?php if (!$user['email_verified']): ?>
    <div class="alert alert-error">Your email isn't verified yet — you can't post listings until it is. <a href="verify-email.php" style="font-weight:700;">Verify now</a></div>
  <?php endif; ?>
  <?php if (!$user['phone_verified']): ?>
    <div class="alert alert-error">Your phone isn't verified yet — you can't post listings until it is. <a href="verify-phone.php" style="font-weight:700;">Verify now</a></div>
  <?php endif; ?>

  <div class="stat-cards" style="margin-top:24px;">
    <div class="stat-card"><b><?= (int)$stats['total_listings'] ?></b><span>Total listings</span></div>
    <div class="stat-card"><b><?= (int)$stats['active_listings'] ?></b><span>Active now</span></div>
    <div class="stat-card"><b><?= (int)$stats['confirmed_deals'] ?></b><span>Confirmed deals</span></div>
    <div class="stat-card"><b><?= (int)$stats['pending_deals'] ?></b><span>Awaiting confirmation</span></div>
  </div>

  <div class="dash-grid">
    <div class="dash-side">
      <a class="active" href="dashboard.php">My listings</a>
      <a href="add-listing.php">Post new listing</a>
      <a href="profile.php?id=<?= (int)$user['id'] ?>">View my public profile</a>
      <a href="account-settings.php">Account settings</a>
      <?php if (!$user['id_verified']): ?><a href="id-verify.php">Get ID verified</a><?php endif; ?>
    </div>

    <div>
      <?php if ($pendingDeals): ?>
        <h3>Deals awaiting your confirmation</h3>
        <table class="data-table" style="margin-bottom:32px;">
          <thead><tr><th>Item</th><th>Buyer</th><th>Requested</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($pendingDeals as $deal): ?>
              <tr>
                <td><?= e($deal['title']) ?></td>
                <td><?= e($deal['buyer_name']) ?></td>
                <td><?= e(date('M j', strtotime($deal['created_at']))) ?></td>
                <td>
                  <form method="post" action="confirm-deal.php" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="deal_id" value="<?= (int)$deal['id'] ?>">
                    <button class="btn btn-gold" style="padding:6px 14px;font-size:0.82rem;" type="submit">Confirm deal</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if ($buyerRequests): ?>
        <h3>My comparison requests</h3>
        <table class="data-table" style="margin-bottom:32px;">
          <thead><tr><th>Request</th><th>Sellers</th><th>Status</th><th>Reveal contact</th></tr></thead>
          <tbody>
            <?php $requestGroups = []; foreach ($buyerRequests as $request): $requestGroups[$request['id']] = $requestGroups[$request['id']] ?? ['message' => $request['message'], 'created_at' => $request['created_at'], 'reveal_contact' => $request['reveal_contact'], 'items' => []]; $requestGroups[$request['id']]['items'][] = ['listing_title' => $request['listing_title'], 'seller_name' => $request['seller_name'], 'status' => $request['status']]; endforeach; ?>
            <?php foreach ($requestGroups as $requestId => $request): ?>
              <tr>
                <td>
                  <strong>Sent <?= e(date('M j', strtotime($request['created_at']))) ?></strong><br>
                  <span style="color:#555;"><?= e($request['message']) ?></span>
                </td>
                <td>
                  <?php foreach ($request['items'] as $item): ?>
                    <?php if (!empty($item['listing_title'])): ?>
                      <div><?= e($item['listing_title']) ?> · <?= e($item['seller_name'] ?: 'Seller') ?></div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </td>
                <td>
                  <?php foreach ($request['items'] as $item): ?>
                    <?php if (!empty($item['listing_title'])): ?>
                      <div><span class="status-pill status-<?= e($item['status']) ?>"><?= e(ucfirst($item['status'])) ?></span></div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </td>
                <td><?= $request['reveal_contact'] ? 'Requested' : 'Not requested' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <h3>My listings</h3>
      <?php if (!$myListings): ?>
        <div class="empty-state">
          <h3>No listings yet</h3>
          <p>Post your first item and start building your trust record.</p>
          <a href="add-listing.php" class="btn btn-primary">Post a listing</a>
        </div>
      <?php else: ?>
        <table class="data-table">
          <thead><tr><th>Title</th><th>Price</th><th>Status</th><th>Views</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($myListings as $l): ?>
              <tr>
                <td><a href="listing.php?id=<?= (int)$l['id'] ?>"><?= e($l['title']) ?></a></td>
                <td>GH₵ <?= number_format((float)$l['price'],2) ?></td>
                <td><span class="status-pill status-<?= e($l['status']) ?>"><?= e(ucfirst($l['status'])) ?></span></td>
                <td><?= (int)$l['views'] ?></td>
                <td><a href="edit-listing.php?id=<?= (int)$l['id'] ?>" style="font-size:0.85rem;">Edit</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

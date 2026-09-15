<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();

$search = clean_str($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$where = ['1 = 1'];
$params = [];
if ($search !== '') {
  $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.town LIKE ?)';
  array_push($params, "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%");
}
if (in_array($statusFilter, ['active', 'suspended', 'banned'], true)) {
  $where[] = 'u.status = ?';
  $params[] = $statusFilter;
}
if (in_array($roleFilter, ['trader', 'buyer', 'admin'], true)) {
  $where[] = 'u.role = ?';
  $params[] = $roleFilter;
}

$accountStmt = db()->prepare('SELECT u.id, u.full_name, u.email, u.phone, u.role, u.town,
                   u.phone_verified, u.email_verified, u.id_verified, u.id_document_path, u.status, u.created_at,
                   (SELECT COUNT(*) FROM listings l WHERE l.user_id = u.id) AS listing_count
                FROM users u
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY u.created_at DESC
                LIMIT 100');
$accountStmt->execute($params);
$accounts = $accountStmt->fetchAll();

$pendingIds = db()->query('SELECT id, full_name, email, phone, id_document_path, created_at
                            FROM users WHERE id_document_path IS NOT NULL AND id_verified = 0
                            ORDER BY created_at')->fetchAll();

$openReports = db()->query('SELECT r.id, r.reason, r.details, r.status, r.created_at,
                                    reporter.full_name AS reporter_name,
                                    l.id AS listing_id, l.title AS listing_title,
                                    reported.id AS reported_id, reported.full_name AS reported_name
                             FROM reports r
                             JOIN users reporter ON reporter.id = r.reporter_id
                             LEFT JOIN listings l ON l.id = r.reported_listing_id
                             LEFT JOIN users reported ON reported.id = r.reported_user_id
                             WHERE r.status IN ("open", "reviewing")
                             ORDER BY r.created_at')->fetchAll();

$totals = db()->query('SELECT
  (SELECT COUNT(*) FROM users) AS accounts,
  (SELECT COUNT(*) FROM users WHERE role="trader") AS traders,
  (SELECT COUNT(*) FROM users WHERE status="suspended") AS suspended,
  (SELECT COUNT(*) FROM users WHERE id_document_path IS NOT NULL AND id_verified = 0) AS pending_verifications,
    (SELECT COUNT(*) FROM listings WHERE status="active") AS active_listings,
    (SELECT COUNT(*) FROM reports WHERE status="open") AS open_reports
')->fetch();

$pageTitle = 'Admin';
require __DIR__ . '/../includes/header.php';
?>
<div class="wrap" style="margin-top:28px;">
  <h1 style="font-size:1.8rem;">Admin dashboard</h1>
  <div class="stat-cards">
    <div class="stat-card"><b><?= (int)$totals['accounts'] ?></b><span>All accounts</span></div>
    <div class="stat-card"><b><?= (int)$totals['traders'] ?></b><span>Registered traders</span></div>
    <div class="stat-card"><b><?= (int)$totals['suspended'] ?></b><span>Suspended accounts</span></div>
    <div class="stat-card"><b><?= (int)$totals['active_listings'] ?></b><span>Active listings</span></div>
    <div class="stat-card"><b><?= (int)$totals['open_reports'] ?></b><span>Open reports</span></div>
  </div>

  <section class="admin-section">
    <div class="admin-section-head">
      <div>
        <h2>Account management</h2>
        <p class="admin-muted">Review identity, account health, listing activity, and access status.</p>
      </div>
      <span class="admin-count">Showing up to 100 accounts</span>
    </div>
    <form method="get" class="admin-filters">
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, phone or town">
      <select name="role" aria-label="Filter by role">
        <option value="">All roles</option>
        <?php foreach (['trader', 'buyer', 'admin'] as $role): ?>
          <option value="<?= $role ?>" <?= $roleFilter === $role ? 'selected' : '' ?>><?= ucfirst($role) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <?php foreach (['active', 'suspended', 'banned'] as $status): ?>
          <option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary" type="submit">Filter</button>
      <a class="btn btn-outline" href="index.php">Clear</a>
    </form>
    <?php if (!$accounts): ?>
      <div class="empty-state"><h3>No accounts found</h3><p>Try clearing the filters or using a different search.</p></div>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="data-table admin-table">
          <thead><tr><th>Account</th><th>Role / location</th><th>Trust</th><th>Listings</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($accounts as $account): ?>
              <tr>
                <td><strong><?= e($account['full_name']) ?></strong><br><span class="admin-muted"><?= e($account['email']) ?><br><?= e($account['phone']) ?></span></td>
                <td><?= e(ucfirst($account['role'])) ?><br><span class="admin-muted"><?= e($account['town']) ?></span></td>
                <td>
                  <span class="status-pill <?= $account['phone_verified'] ? 'status-active' : 'status-pending' ?>"><?= $account['phone_verified'] ? 'Phone verified' : 'Phone pending' ?></span><br>
                  <span class="status-pill <?= $account['email_verified'] ? 'status-active' : 'status-pending' ?>"><?= $account['email_verified'] ? 'Email verified' : 'Email pending' ?></span><br>
                  <span class="status-pill <?= $account['id_verified'] ? 'status-active' : ($account['id_document_path'] ? 'status-pending' : 'status-removed') ?>"><?= $account['id_verified'] ? 'ID verified' : ($account['id_document_path'] ? 'ID review' : 'No ID') ?></span>
                </td>
                <td><?= (int)$account['listing_count'] ?></td>
                <td><span class="status-pill status-<?= e($account['status']) ?>"><?= e(ucfirst($account['status'])) ?></span></td>
                <td>
                  <div class="admin-actions">
                    <a href="user-details.php?id=<?= (int)$account['id'] ?>">View details</a>
                    <?php if ($account['id_document_path'] && !$account['id_verified']): ?>
                      <a href="../assets/uploads/<?= e($account['id_document_path']) ?>" target="_blank" rel="noopener">View ID</a>
                    <?php endif; ?>
                    <?php if ((int)$account['id'] !== (int)$admin['id']): ?>
                      <form method="post" action="manage-user.php">
                        <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>">
                        <?php if (!$account['id_verified'] && $account['id_document_path']): ?><button name="action" value="approve" class="admin-link">Approve ID</button><?php endif; ?>
                        <?php if ($account['id_document_path'] && !$account['id_verified']): ?><button name="action" value="reject" class="admin-link danger-link">Reject ID</button><?php endif; ?>
                        <?php if ($account['role'] !== 'admin'): ?><button name="action" value="make_admin" class="admin-link">Make admin</button><?php endif; ?>
                        <?php if ($account['status'] === 'active'): ?><button name="action" value="suspend" class="admin-link danger-link">Suspend</button><?php elseif ($account['status'] === 'suspended'): ?><button name="action" value="activate" class="admin-link">Activate</button><?php endif; ?>
                        <button name="action" value="delete" class="admin-link danger-link" onclick="return confirm('Delete this account and its associated listings? This cannot be undone.')">Delete</button>
                      </form>
                    <?php else: ?><span class="admin-muted">Current admin</span><?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <h3>Pending ID verifications</h3>
  <?php if (!$pendingIds): ?>
    <p style="color:#777;">Nothing pending.</p>
  <?php else: ?>
    <div class="admin-table-wrap" style="margin-bottom:36px;">
    <table class="data-table admin-table">
      <thead><tr><th>Trader</th><th>Contact</th><th>Submitted</th><th>ID Photo</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($pendingIds as $p): ?>
          <tr>
            <td><?= e($p['full_name']) ?></td>
            <td><?= e($p['email']) ?><br><?= e($p['phone']) ?></td>
            <td><?= e(date('M j', strtotime($p['created_at']))) ?></td>
            <td><a href="../assets/uploads/<?= e($p['id_document_path']) ?>" target="_blank" rel="noopener">View ID</a></td>
            <td>
              <form method="post" action="verify-user.php" style="display:flex;gap:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>">
                <button name="action" value="approve" class="btn btn-gold" style="padding:6px 12px;font-size:0.8rem;">Approve</button>
                <button name="action" value="reject" class="btn btn-danger" style="padding:6px 12px;font-size:0.8rem;">Reject</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>

  <h3>Open reports</h3>
  <?php if (!$openReports): ?>
    <p style="color:#777;">No open reports.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
    <table class="data-table admin-table">
      <thead><tr><th>Reported</th><th>Reason</th><th>Reporter</th><th>Details</th><th>Status / action</th></tr></thead>
      <tbody>
        <?php foreach ($openReports as $r): ?>
          <tr>
            <td>
              <?php if ($r['listing_title']): ?><a href="../listing.php?id=<?= (int)$r['listing_id'] ?>" target="_blank"><?= e($r['listing_title']) ?></a><?php endif; ?>
              <?php if ($r['reported_name']): ?><br><span style="color:#777;">Trader: <?= e($r['reported_name']) ?></span><?php endif; ?>
            </td>
            <td><?= e($r['reason']) ?></td>
            <td><?= e($r['reporter_name']) ?></td>
            <td style="max-width:220px;"><?= e($r['details'] ?? '—') ?></td>
            <td>
              <span class="status-pill <?= $r['status'] === 'reviewing' ? 'status-pending' : 'status-active' ?>"><?= e(ucfirst($r['status'])) ?></span>
              <form method="post" action="handle-report.php" style="display:flex;gap:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                <?php if ($r['status'] === 'open'): ?><button name="action" value="reviewing" class="btn btn-outline" style="padding:6px 12px;font-size:0.8rem;">Review</button><?php endif; ?>
                <button name="action" value="resolved" class="btn btn-gold" style="padding:6px 12px;font-size:0.8rem;">Resolve</button>
                <button name="action" value="dismissed" class="btn btn-outline" style="padding:6px 12px;font-size:0.8rem;">Dismiss</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

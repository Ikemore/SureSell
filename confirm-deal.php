<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}
csrf_verify();

$dealId = filter_input(INPUT_POST, 'deal_id', FILTER_VALIDATE_INT);
if (!$dealId) { redirect('dashboard.php'); }

$stmt = db()->prepare('SELECT * FROM deals WHERE id = ? AND seller_id = ? AND status = "requested" LIMIT 1');
$stmt->execute([$dealId, $user['id']]);
$deal = $stmt->fetch();

if ($deal) {
    db()->beginTransaction();
    db()->prepare('UPDATE deals SET status = "confirmed", confirmed_at = NOW() WHERE id = ?')->execute([$dealId]);
    db()->prepare('UPDATE users SET deals_completed = deals_completed + 1, trust_score = trust_score + 5 WHERE id = ?')->execute([$user['id']]);
    db()->commit();
    flash_set('success', 'Deal confirmed. Your trust record has been updated.');
} else {
    flash_set('error', 'That deal request could not be found.');
}

redirect('dashboard.php');

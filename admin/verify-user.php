<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
csrf_verify();

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if ($userId && in_array($action, ['approve', 'reject'], true)) {
    if ($action === 'approve') {
        db()->prepare('UPDATE users SET id_verified = 1 WHERE id = ?')->execute([$userId]);
        flash_set('success', 'Trader approved as fully verified.');
    } else {
        db()->prepare('UPDATE users SET id_document_path = NULL WHERE id = ?')->execute([$userId]);
        flash_set('success', 'Verification submission rejected.');
    }
}

redirect('index.php');

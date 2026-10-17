<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
csrf_verify();

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if ($userId && in_array($action, ['approve', 'reject'], true)) {
    if ($action === 'approve') {
        db()->prepare('UPDATE users SET id_verified = 1 WHERE id = ? AND id_document_path IS NOT NULL')->execute([$userId]);
        flash_set('success', 'Optional ID verification approved.');
    } else {
        db()->prepare('UPDATE users SET id_document_path = NULL WHERE id = ?')->execute([$userId]);
        flash_set('success', 'ID submission rejected. The trader may continue selling with verified email and phone.');
    }
}

redirect('index.php');

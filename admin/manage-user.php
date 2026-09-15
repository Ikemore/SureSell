<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_verify();

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$allowedActions = ['approve', 'reject', 'suspend', 'activate', 'delete', 'make_admin'];

if (!$userId || !in_array($action, $allowedActions, true)) {
    flash_set('error', 'Invalid account action.');
    redirect('index.php');
}
if ((int)$userId === (int)$admin['id']) {
    flash_set('error', 'You cannot change or delete the currently signed-in administrator.');
    redirect('index.php');
}

$stmt = db()->prepare('SELECT id, role, status, id_document_path, avatar_path FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$target = $stmt->fetch();
if (!$target) {
    flash_set('error', 'Account not found.');
    redirect('index.php');
}

if ($action === 'approve') {
    db()->prepare('UPDATE users SET id_verified = 1 WHERE id = ?')->execute([$userId]);
    flash_set('success', 'ID verification approved.');
} elseif ($action === 'reject') {
    db()->prepare('UPDATE users SET id_document_path = NULL, id_verified = 0 WHERE id = ?')->execute([$userId]);
    flash_set('success', 'ID verification rejected.');
} elseif ($action === 'suspend' && $target['status'] === 'active') {
    db()->prepare('UPDATE users SET status = "suspended" WHERE id = ?')->execute([$userId]);
    flash_set('success', 'Account suspended. Its owner can no longer log in or post.');
} elseif ($action === 'activate' && $target['status'] === 'suspended') {
    db()->prepare('UPDATE users SET status = "active" WHERE id = ?')->execute([$userId]);
    flash_set('success', 'Account reactivated.');
} elseif ($action === 'make_admin') {
    db()->prepare('UPDATE users SET role = "admin" WHERE id = ?')->execute([$userId]);
    flash_set('success', 'Account promoted to admin.');
} elseif ($action === 'delete') {
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    foreach ([$target['id_document_path'], $target['avatar_path']] as $path) {
        if ($path) {
            $file = realpath(__DIR__ . '/../assets/uploads/' . $path);
            $uploadRoot = realpath(__DIR__ . '/../assets/uploads/');
            if ($file && $uploadRoot && str_starts_with($file, $uploadRoot . DIRECTORY_SEPARATOR) && is_file($file)) {
                unlink($file);
            }
        }
    }
    flash_set('success', 'Account and associated records deleted.');
} else {
    flash_set('error', 'That account action is not available for the current state.');
}

redirect('index.php');

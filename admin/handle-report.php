<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php'); }
csrf_verify();

$reportId = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if ($reportId && in_array($action, ['reviewing', 'resolved', 'dismissed'], true)) {
    db()->prepare('UPDATE reports SET status = ?, resolved_at = NOW() WHERE id = ?')->execute([$action, $reportId]);
    flash_set('success', 'Report updated.');
}

redirect('index.php');

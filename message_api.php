<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'count' => 0]);
    exit;
}

$pdo = getDB();
$userId = (int) $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

if ($action === 'unread_count') {
    echo json_encode([
        'success' => true,
        'count' => getUnreadMessageNotificationCount($pdo, $userId)
    ]);
    exit;
}

if ($action === 'mark_read') {
    echo json_encode([
        'success' => true,
        'count' => markMessageNotificationsRead($pdo, $userId)
    ]);
    exit;
}

echo json_encode(['success' => false, 'count' => 0]);

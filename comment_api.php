<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to comment.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    $userId = $_SESSION['user_id'];

    if (!$eventId || empty($comment)) {
        echo json_encode(['success' => false, 'message' => 'Invalid comment data.']);
        exit;
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO event_comments (event_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$eventId, $userId, sanitize($comment)]);
        
        echo json_encode(['success' => true, 'message' => 'Comment added successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    }
}

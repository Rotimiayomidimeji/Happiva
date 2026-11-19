<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pdo = getDB();
$user = getCurrentUser();

$type = isset($_GET['type']) ? $_GET['type'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($type === 'upgrade' && $user['role'] === 'user') {
    // User requested to become an organizer; keep role as user until admin approves.
    $stmt = $pdo->prepare("UPDATE users SET organizer_request_pending = 1 WHERE id = ?");
    $stmt->execute([$user['id']]);

    $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
    $admins = $adminStmt->fetchAll();
    if (!empty($admins)) {
        $notifyStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id) VALUES (?, 'new_organizer', 'New Organizer Verification Request', ?, ?)");
        $message = "A user ({$user['name']}) has requested organizer access and needs verification.";
        foreach ($admins as $admin) {
            $notifyStmt->execute([$admin['id'], $message, $user['id']]);
        }
    }

    redirect('dashboard.php?status=pending');
}

if ($type === 'cancel_upgrade' && $user['role'] === 'user') {
    $stmt = $pdo->prepare("UPDATE users SET organizer_request_pending = 0 WHERE id = ?");
    $stmt->execute([$user['id']]);
    redirect('dashboard.php?status=cancelled');
}

// Only admin can do the below actions
if ($user['role'] !== 'admin') {
    redirect('dashboard.php');
}

if ($type === 'user' && $id > 0) {
    // Admin verifying an organizer request or a pending organizer registration.
    $stmt = $pdo->prepare("UPDATE users SET role = 'organizer', is_verified = 1, organizer_request_pending = 0 WHERE id = ? AND (role = 'organizer' OR organizer_request_pending = 1 OR role = 'user')");
    $stmt->execute([$id]);

    // Refresh the approved user's session immediately if they are currently logged in.
    if ($id === $user['id']) {
        $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $userStmt->execute([$id]);
        $updatedUser = $userStmt->fetch();
        if ($updatedUser) {
            $_SESSION['role'] = $updatedUser['role'];
            $_SESSION['is_verified'] = $updatedUser['is_verified'] ?? 0;
            $_SESSION['organizer_request_pending'] = $updatedUser['organizer_request_pending'] ?? 0;
        }
    }
} elseif ($type === 'remove_organizer' && $id > 0) {
    // Admin removing organizer access from a verified organizer.
    $stmt = $pdo->prepare("UPDATE users SET role = 'user', is_verified = 0, organizer_request_pending = 0 WHERE id = ? AND role = 'organizer'");
    $stmt->execute([$id]);

    if ($id === $user['id']) {
        $_SESSION['role'] = 'user';
        $_SESSION['is_verified'] = 0;
        $_SESSION['organizer_request_pending'] = 0;
    }

    $_SESSION['flash_message'] = 'Organizer removed successfully.';
    $_SESSION['flash_type'] = 'success';
} elseif ($type === 'event' && $id > 0) {
    // Admin approving an event
    $stmt = $pdo->prepare("UPDATE events SET is_approved = 1 WHERE id = ?");
    $stmt->execute([$id]);
}

// Redirect back to dashboard safely
redirect('dashboard.php');

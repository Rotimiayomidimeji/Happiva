<?php
/**
 * Happiva - Event Booking Platform
 * Checkout API - Store checkout item in session
 */

require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

// Validate input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$price = isset($_POST['price']) ? (float)$_POST['price'] : 0;

if (!$eventId || $quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid event or quantity']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    $unitPrice = (float)($event['price'] ?? 0);
    if ($unitPrice <= 0 && $price > 0) {
        $unitPrice = $price;
    }

    if ($unitPrice <= 0) {
        echo json_encode(['success' => false, 'message' => 'This event is not available for booking right now']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as booked FROM bookings WHERE event_id = ? AND status = 'confirmed'");
    $stmt->execute([$eventId]);
    $booked = (int)($stmt->fetch()['booked'] ?? 0);

    if ($booked + $quantity > (int)($event['capacity'] ?? 0)) {
        echo json_encode(['success' => false, 'message' => 'Not enough capacity available']);
        exit;
    }

    $total = round($unitPrice * $quantity, 2);

    $_SESSION['checkout_item'] = [
        'eventId' => $eventId,
        'quantity' => $quantity,
        'price' => $unitPrice,
        'total' => $total
    ];

    echo json_encode(['success' => true, 'total' => $total]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

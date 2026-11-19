<?php
/**
 * Happiva - Event Management API
 * Handles create, edit, delete events
 */

require_once 'config.php';

// Check if user is organizer or admin
if (!isLoggedIn() || (!isOrganizer() && !isAdmin())) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        createEvent();
        break;
    case 'update':
        updateEvent();
        break;
    case 'delete':
        deleteEvent();
        break;
    case 'get':
        getEvent();
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function createEvent() {
    $pdo = getDB();
    
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $expectations = sanitize($_POST['expectations'] ?? '');
    $date = $_POST['date'];
    $time = $_POST['time'];
    $venue = sanitize($_POST['venue']);
    $address = sanitize($_POST['address'] ?? '');
    $price = (float)$_POST['price'];
    $capacity = (int)$_POST['capacity'];
    $category = $_POST['category'];
    $image = sanitize($_POST['image'] ?? '');
    $ticketPrimaryColor = normalizeTicketColor($_POST['ticket_primary_color'] ?? '', '#4f46e5');
    $ticketSecondaryColor = normalizeTicketColor($_POST['ticket_secondary_color'] ?? '', '#ec4899');
    
    try {
        $uploadedImage = uploadEventImage($_FILES['image_file'] ?? null);
        if ($uploadedImage !== null) {
            $image = $uploadedImage;
        }

        $stmt = $pdo->prepare("
            INSERT INTO events (title, description, expectations, date, time, venue, address, price, capacity, category, image, ticket_primary_color, ticket_secondary_color, organizer_id, is_approved)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$title, $description, $expectations, $date, $time, $venue, $address, $price, $capacity, $category, $image, $ticketPrimaryColor, $ticketSecondaryColor, $_SESSION['user_id']]);

        $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
        $admins = $adminStmt->fetchAll();
        $eventId = $pdo->lastInsertId();
        foreach ($admins as $admin) {
            $notifyStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id) VALUES (?, 'event_pending_approval', 'New Event Pending Approval', ?, ?)");
            $notifyStmt->execute([$admin['id'], 'A new event, ' . $title . ', is awaiting approval.', $eventId]);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Event submitted for approval', 'event_id' => $eventId]);
    } catch (RuntimeException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to create event']);
    }
}

function updateEvent() {
    $pdo = getDB();
    
    $eventId = (int)$_POST['event_id'];
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $expectations = sanitize($_POST['expectations'] ?? '');
    $date = $_POST['date'];
    $time = $_POST['time'];
    $venue = sanitize($_POST['venue']);
    $address = sanitize($_POST['address'] ?? '');
    $price = (float)$_POST['price'];
    $capacity = (int)$_POST['capacity'];
    $category = $_POST['category'];
    $image = sanitize($_POST['image'] ?? '');
    $ticketPrimaryColor = normalizeTicketColor($_POST['ticket_primary_color'] ?? '', '#4f46e5');
    $ticketSecondaryColor = normalizeTicketColor($_POST['ticket_secondary_color'] ?? '', '#ec4899');
    
    // Organizers can edit their own events; admins can edit any event.
    if (isAdmin()) {
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ? AND organizer_id = ?");
        $stmt->execute([$eventId, $_SESSION['user_id']]);
    }
    
    if (!$stmt->fetch()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Event not found or unauthorized']);
        exit;
    }
    
    try {
        $uploadedImage = uploadEventImage($_FILES['image_file'] ?? null);
        if ($uploadedImage !== null) {
            $image = $uploadedImage;
        }

        if (isAdmin()) {
            $stmt = $pdo->prepare("
                UPDATE events SET
                    title = ?, description = ?, expectations = ?, date = ?, time = ?, venue = ?,
                    address = ?, price = ?, capacity = ?, category = ?, image = ?, ticket_primary_color = ?, ticket_secondary_color = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $expectations, $date, $time, $venue, $address, $price, $capacity, $category, $image, $ticketPrimaryColor, $ticketSecondaryColor, $eventId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE events SET
                    title = ?, description = ?, expectations = ?, date = ?, time = ?, venue = ?,
                    address = ?, price = ?, capacity = ?, category = ?, image = ?, ticket_primary_color = ?, ticket_secondary_color = ?
                WHERE id = ? AND organizer_id = ?
            ");
            $stmt->execute([$title, $description, $expectations, $date, $time, $venue, $address, $price, $capacity, $category, $image, $ticketPrimaryColor, $ticketSecondaryColor, $eventId, $_SESSION['user_id']]);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
    } catch (RuntimeException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to update event']);
    }
}

function deleteEvent() {
    $pdo = getDB();
    
    $eventId = (int)$_POST['event_id'];
    
    // Organizers can delete their own events; admins can delete any event.
    if (isAdmin()) {
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ? AND organizer_id = ?");
        $stmt->execute([$eventId, $_SESSION['user_id']]);
    }
    
    if (!$stmt->fetch()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Event not found or unauthorized']);
        exit;
    }
    
    try {
        // Delete related bookings first
        $deleteBookings = $pdo->prepare("DELETE FROM bookings WHERE event_id = ?");
        $deleteBookings->execute([$eventId]);
        
        // Delete event
        if (isAdmin()) {
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND organizer_id = ?");
            $stmt->execute([$eventId, $_SESSION['user_id']]);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to delete event']);
    }
}

function getEvent() {
    $pdo = getDB();
    
    $eventId = (int)$_GET['event_id'];
    
    if (isAdmin()) {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND organizer_id = ?");
        $stmt->execute([$eventId, $_SESSION['user_id']]);
    }
    $event = $stmt->fetch();
    
    if ($event) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'event' => $event]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Event not found']);
    }
}

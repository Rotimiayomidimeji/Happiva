<?php
/**
 * Happiva - Event Booking Platform
 * Create Event Handler
 */

require_once 'config.php';

// Check if user is organizer
if (!isLoggedIn()) {
    redirect('index.php');
}

$currentUser = refreshCurrentUserSession();
if (empty($currentUser) || !in_array($currentUser['role'], ['organizer', 'admin'])) {
    redirect('index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    if (empty($date)) {
        $errors[] = 'Date is required';
    }
    if (empty($time)) {
        $errors[] = 'Time is required';
    }
    if (empty($venue)) {
        $errors[] = 'Venue is required';
    }

    if (empty($errors)) {
        try {
            $uploadedImage = uploadEventImage($_FILES['image_file'] ?? null);
            if ($uploadedImage !== null) {
                $image = $uploadedImage;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("
                INSERT INTO events (title, description, expectations, date, time, venue, address, price, capacity, category, image, ticket_primary_color, ticket_secondary_color, organizer_id, is_approved)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([
                $title,
                $description,
                $expectations,
                $date,
                $time,
                $venue,
                $address,
                $price,
                $capacity,
                $category,
                $image,
                $ticketPrimaryColor,
                $ticketSecondaryColor,
                $_SESSION['user_id']
            ]);

            $eventId = $pdo->lastInsertId();
            $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
            $admins = $adminStmt->fetchAll();
            foreach ($admins as $admin) {
                $notifyStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id) VALUES (?, 'event_pending_approval', 'New Event Pending Approval', ?, ?)");
                $notifyStmt->execute([$admin['id'], 'A new event, ' . $title . ', is awaiting approval.', $eventId]);
            }

            $_SESSION['flash_message'] = 'Event submitted successfully and is pending admin approval.';
            $_SESSION['flash_type'] = 'success';
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Failed to create event. Please try again.';
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['flash_message'] = implode(' ', $errors);
        $_SESSION['flash_type'] = 'error';
    }
}

redirect('dashboard.php');

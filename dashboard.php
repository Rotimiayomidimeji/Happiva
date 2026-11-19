<?php
/**
 * Happiva - Event Booking Platform
 * Organizer Dashboard
 */

require_once 'config.php';

$pageTitle = 'Dashboard - EventSmart';

// Check login
if (!isLoggedIn()) {
    redirect('index.php');
}

$user = getCurrentUser();
if (empty($user)) {
    redirect('index.php');
}

$userRole = $user['role'] ?? 'user';
$isVerified = (bool)($user['is_verified'] ?? 0);
$pdo = null;
$stats = ['total_events' => 0, 'total_bookings' => 0, 'total_revenue' => 0, 'checked_in' => 0];
$events = [];
$recentBookings = [];
$notifications = [];
$pendingEvents = [];
$pendingOrgs = [];
$contactSubmissions = [];
$scanResult = null;
$unreadCount = 0;
if (empty($_SESSION['admin_contact_csrf'])) {
    $_SESSION['admin_contact_csrf'] = bin2hex(random_bytes(32));
}
$adminContactCsrf = (string)$_SESSION['admin_contact_csrf'];

// Get dashboard stats
try {
    $pdo = getDB();
    $userId = (int)$_SESSION['user_id'];

    $statsSql = [
        'total_events' => "SELECT COUNT(*) as count FROM events WHERE organizer_id = ?",
        'total_bookings' => "SELECT COUNT(*) as count FROM bookings b JOIN events e ON b.event_id = e.id WHERE e.organizer_id = ?",
        'total_revenue' => "SELECT COALESCE(SUM(b.total_price), 0) as total FROM bookings b JOIN events e ON b.event_id = e.id WHERE e.organizer_id = ? AND b.status = 'confirmed'",
        'checked_in' => "SELECT COUNT(*) as count FROM bookings b JOIN events e ON b.event_id = e.id WHERE e.organizer_id = ? AND b.checked_in = 1"
    ];

    foreach ($statsSql as $key => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        $stats[$key] = (float)($row['count'] ?? $row['total'] ?? 0);
    }

    if ($userRole === 'admin') {
        $stmt = $pdo->query("SELECT e.*, u.name AS organizer_name FROM events e LEFT JOIN users u ON e.organizer_id = u.id ORDER BY e.date DESC");
        $events = $stmt->fetchAll();

        $pendingStmt = $pdo->query("SELECT * FROM events WHERE is_approved = 0 ORDER BY created_at DESC LIMIT 10");
        $pendingEvents = $pendingStmt->fetchAll();

        $orgStmt = $pdo->query("SELECT * FROM users WHERE (role = 'organizer' AND is_verified = 0) OR organizer_request_pending = 1 ORDER BY created_at DESC LIMIT 10");
        $pendingOrgs = $orgStmt->fetchAll();

        $contactStmt = $pdo->query("SELECT id, name, email, category, subject, message, status, created_at FROM contact_submissions ORDER BY (status = 'open') DESC, created_at DESC LIMIT 50");
        $contactSubmissions = $contactStmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE organizer_id = ? ORDER BY date DESC");
        $stmt->execute([$userId]);
        $events = $stmt->fetchAll();
    }

    $recentStmt = $pdo->prepare("
        SELECT b.*, e.title as event_title, u.name as user_name, u.email as user_email
        FROM bookings b
        JOIN events e ON b.event_id = e.id
        JOIN users u ON b.user_id = u.id
        WHERE e.organizer_id = ?
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $recentStmt->execute([$userId]);
    $recentBookings = $recentStmt->fetchAll();

    $notificationsStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $notificationsStmt->execute([$userId]);
    $notifications = $notificationsStmt->fetchAll();
    $unreadCount = count(array_filter($notifications, function($n) { return !($n['is_read'] ?? 0); }));
} catch (PDOException $e) {
    $stats = ['total_events' => 0, 'total_bookings' => 0, 'total_revenue' => 0, 'checked_in' => 0];
    $events = [];
    $recentBookings = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_action'])) {
    if ($userRole !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }

    if (!hash_equals($adminContactCsrf, (string)($_POST['contact_csrf'] ?? ''))) {
        $_SESSION['flash_message'] = 'Your session expired. Please try that action again.';
        $_SESSION['flash_type'] = 'error';
        redirect('dashboard.php#admin-contact-messages');
    }

    $contactId = (int)($_POST['contact_id'] ?? 0);
    $contactStatus = $_POST['contact_action'] === 'resolve' ? 'resolved' : 'open';
    if ($contactId > 0 && in_array($_POST['contact_action'], ['resolve', 'reopen'], true)) {
        $stmt = $pdo->prepare("UPDATE contact_submissions SET status = ?, resolved_at = IF(? = 'resolved', NOW(), NULL), resolved_by = IF(? = 'resolved', ?, NULL) WHERE id = ?");
        $stmt->execute([$contactStatus, $contactStatus, $contactStatus, (int)$_SESSION['user_id'], $contactId]);
        $_SESSION['flash_message'] = $contactStatus === 'resolved' ? 'Support request marked resolved.' : 'Support request reopened.';
        $_SESSION['flash_type'] = 'success';
    }
    redirect('dashboard.php#admin-contact-messages');
}

// Handle QR scan / manual ticket lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['scan_ticket']) || isset($_POST['search_ticket']))) {
    $ticketId = trim($_POST['ticket_id'] ?? '');
    $selectedEventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    $performCheckIn = isset($_POST['scan_ticket']);
    
    try {
        $query = "SELECT b.*, e.title as event_title, e.organizer_id, e.date as event_date, e.time as event_time, e.venue as event_venue, e.address as event_address, e.category as event_category, u.name as user_name FROM bookings b JOIN events e ON b.event_id = e.id JOIN users u ON b.user_id = u.id WHERE b.qr_code = ?";
        $params = [$ticketId];

        if (!isAdmin()) {
            $query .= " AND e.organizer_id = ?";
            $params[] = $_SESSION['user_id'];
        }

        if ($selectedEventId > 0) {
            $query .= " AND e.id = ?";
            $params[] = $selectedEventId;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $ticket = $stmt->fetch();
        
        if ($ticket) {
            if ($ticket['status'] === 'cancelled') {
                $scanResult = ['status' => 'error', 'message' => 'This ticket has been cancelled and is no longer valid.'];
            } elseif ($performCheckIn) {
                if ($ticket['checked_in']) {
                    $scanResult = ['status' => 'error', 'message' => 'Ticket already checked in!'];
                } else {
                    // Mark as checked in
                    $updateStmt = $pdo->prepare("UPDATE bookings SET checked_in = 1 WHERE id = ?");
                    $updateStmt->execute([$ticket['id']]);
                    
                    // Log check-in
                    $logStmt = $pdo->prepare("INSERT INTO checkin_logs (booking_id, scanned_by) VALUES (?, ?)");
                    $logStmt->execute([$ticket['id'], $_SESSION['user_id']]);
                    
                    $ticket['checked_in'] = 1;
                    $scanResult = [
                        'status' => 'success',
                        'message' => 'Check-in successful!',
                        'ticket' => $ticket
                    ];
                }
            } else {
                $scanResult = [
                    'status' => 'success',
                    'message' => 'Ticket found successfully.',
                    'ticket' => $ticket
                ];
            }
        } else {
            $scanResult = ['status' => 'error', 'message' => 'No ticket found for that ID.'];
        }
    } catch (PDOException $e) {
        $scanResult = ['status' => 'error', 'message' => 'Error processing ticket'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=13">
    <link rel="icon" href="uploads/eventsmart-logo.svg">
</head>
<body class="logged-in">
    <!-- Navigation -->
    <nav class="navbar scrolled">
        <div class="container">
            <a href="index.php" class="logo">
                <img src="uploads/eventsmart-logo.svg" alt="EventSmart logo" style="height: 34px; width: 34px; object-fit: contain; margin-right: 10px;">
                <span>EventSmart</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="tickets.php">My Tickets</a></li>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
            </ul>
            <div class="nav-actions">
                <?php if (!empty($notifications)): ?>
                <button class="btn btn-ghost btn-sm" onclick="openModal('notificationsModal')" style="position: relative;">
                    🔔
                    <?php if ($unreadCount > 0): ?>
                    <span style="position: absolute; top: -5px; right: -5px; background: var(--error); color: white; font-size: 10px; padding: 2px 5px; border-radius: 10px;"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </button>
                <?php endif; ?>
                <span class="user-name"><?php echo sanitize($_SESSION['user_name']); ?></span>
                <a href="#" class="btn btn-secondary btn-sm" onclick="event.preventDefault(); openModal('logoutModal');">Logout</a>
                <button class="mobile-menu-btn" type="button" aria-label="Toggle navigation" aria-expanded="false">☰</button>
            </div>
        </div>
    </nav>

    <!-- Dashboard -->
    <div class="dashboard-container">
        <?php if (!empty($_SESSION['flash_message'])): ?>
            <div class="form-<?php echo ($_SESSION['flash_type'] ?? 'success') === 'error' ? 'error' : 'success'; ?>" style="display:block; margin-bottom: 16px;">
                <?php echo sanitize($_SESSION['flash_message']); ?>
            </div>
            <?php unset($_SESSION['flash_message']); unset($_SESSION['flash_type']); ?>
        <?php endif; ?>
        
        <?php if ($user['role'] === 'admin'): ?>
            <!-- Admin Dashboard -->
            <div class="dashboard-header">
                <div>
                    <h1>Admin Dashboard</h1>
                    <p style="color: var(--text-secondary);">Verify Organizers and Approve Events.</p>
                </div>
            </div>

            <nav class="admin-section-nav" aria-label="Admin dashboard sections">
                <a href="#admin-pending-organizers">Pending Organizers</a>
                <a href="#admin-pending-events">Pending Events</a>
                <a href="#admin-contact-messages">Support Inbox (<?php echo count($contactSubmissions); ?>)</a>
                <a href="#admin-all-events">Manage All Events</a>
                <a href="#admin-organizers">Verified Organizers</a>
            </nav>
            
            <div class="dashboard-grid" style="margin-top: 24px;">
                <div class="dashboard-card" id="admin-pending-organizers">
                    <h3>Pending Organizers</h3>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM users WHERE (role = 'organizer' AND is_verified = 0) OR organizer_request_pending = 1 ORDER BY created_at DESC LIMIT 10");
                    $pendingOrgs = $stmt->fetchAll();
                    if(!empty($pendingOrgs)):
                    ?>
                    <table class="events-table">
                        <thead><tr><th>Name</th><th>Email</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach($pendingOrgs as $org): ?>
                            <tr>
                                <td><?php echo sanitize($org['name']); ?></td>
                                <td><?php echo sanitize($org['email']); ?></td>
                                <td>
                                    <a href="verify_api.php?type=user&id=<?php echo $org['id']; ?>" class="btn btn-primary btn-sm">Verify</a>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                        <?php echo !empty($org['organizer_request_pending']) ? 'Upgrade request' : 'Pending organizer signup'; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: echo "<p>No pending organizers.</p>"; endif; ?>
                </div>

                <div class="dashboard-card" id="admin-pending-events">
                    <h3>Pending Events</h3>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM events WHERE is_approved = 0 LIMIT 10");
                    $pendingEvents = $stmt->fetchAll();
                    if(!empty($pendingEvents)):
                    ?>
                    <table class="events-table">
                        <thead><tr><th>Title</th><th>Date</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach($pendingEvents as $ev): ?>
                            <tr>
                                <td><?php echo sanitize($ev['title']); ?></td>
                                <td><?php echo formatDate($ev['date']); ?></td>
                                <td>
                                    <a href="verify_api.php?type=event&id=<?php echo $ev['id']; ?>" class="btn btn-primary btn-sm">Approve</a>
                                    <button class="btn btn-ghost btn-sm" onclick="editEvent(<?php echo $ev['id']; ?>, '<?php echo addslashes($ev['title']); ?>', '<?php echo addslashes($ev['description']); ?>', '<?php echo $ev['date']; ?>', '<?php echo $ev['time']; ?>', '<?php echo addslashes($ev['venue']); ?>', '<?php echo addslashes($ev['address']); ?>', <?php echo $ev['price']; ?>, <?php echo $ev['capacity']; ?>, '<?php echo $ev['category']; ?>', '<?php echo addslashes($ev['image']); ?>', '<?php echo addslashes($ev['expectations'] ?? ''); ?>', '<?php echo addslashes($ev['ticket_primary_color'] ?? '#4f46e5'); ?>', '<?php echo addslashes($ev['ticket_secondary_color'] ?? '#ec4899'); ?>')">Edit</button>
                                    <button class="btn btn-ghost btn-sm" style="color: var(--error);" onclick="deleteEvent(<?php echo $ev['id']; ?>, '<?php echo addslashes($ev['title']); ?>')">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: echo "<p>No pending events.</p>"; endif; ?>
                </div>
            </div>

            <div class="dashboard-card admin-section-target" id="admin-contact-messages" style="margin-top: 24px;">
                <h3>Support Inbox</h3>
                <?php if (!empty($contactSubmissions)): ?>
                <div style="overflow-x: auto;">
                    <table class="events-table">
                        <thead><tr><th>Received</th><th>From</th><th>Topic</th><th>Message</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($contactSubmissions as $submission): ?>
                            <tr>
                                <td><?php echo date('M j, Y g:i A', strtotime($submission['created_at'])); ?></td>
                                <td><strong><?php echo sanitize($submission['name']); ?></strong><br><a href="mailto:<?php echo rawurlencode($submission['email']); ?>"><?php echo sanitize($submission['email']); ?></a></td>
                                <td><?php echo sanitize(ucfirst($submission['category'])); ?><br><strong><?php echo sanitize($submission['subject']); ?></strong></td>
                                <td><div style="max-width: 360px; max-height: 150px; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere;"><?php echo nl2br(sanitize($submission['message'])); ?></div></td>
                                <td><span class="status-badge <?php echo $submission['status'] === 'resolved' ? 'active' : 'inactive'; ?>"><?php echo sanitize(ucfirst($submission['status'])); ?></span></td>
                                <td>
                                    <form method="POST" action="dashboard.php#admin-contact-messages">
                                        <input type="hidden" name="contact_csrf" value="<?php echo sanitize($adminContactCsrf); ?>">
                                        <input type="hidden" name="contact_id" value="<?php echo (int)$submission['id']; ?>">
                                        <input type="hidden" name="contact_action" value="<?php echo $submission['status'] === 'resolved' ? 'reopen' : 'resolve'; ?>">
                                        <button class="btn <?php echo $submission['status'] === 'resolved' ? 'btn-secondary' : 'btn-primary'; ?> btn-sm" type="submit"><?php echo $submission['status'] === 'resolved' ? 'Reopen' : 'Mark resolved'; ?></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary);">No support messages yet.</p>
                <?php endif; ?>
            </div>

            <div class="dashboard-card admin-section-target" id="admin-all-events" style="margin-top: 24px;">
                <h3>Manage All Events (<?php echo count($events); ?>)</h3>
                <?php if (!empty($events)): ?>
                <div style="overflow-x: auto;">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Organizer</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                            <?php
                            $editValues = [
                                (int)$event['id'],
                                (string)$event['title'],
                                (string)$event['description'],
                                (string)$event['date'],
                                (string)$event['time'],
                                (string)$event['venue'],
                                (string)($event['address'] ?? ''),
                                (float)$event['price'],
                                (int)$event['capacity'],
                                (string)$event['category'],
                                (string)($event['image'] ?? ''),
                                (string)($event['expectations'] ?? ''),
                                (string)($event['ticket_primary_color'] ?? '#4f46e5'),
                                (string)($event['ticket_secondary_color'] ?? '#ec4899')
                            ];
                            $editArguments = implode(', ', array_map(function ($value) {
                                return json_encode($value, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
                            }, $editValues));
                            ?>
                            <tr>
                                <td><?php echo sanitize($event['title']); ?></td>
                                <td><?php echo !empty($event['organizer_name']) ? sanitize($event['organizer_name']) : 'Unassigned'; ?></td>
                                <td><?php echo formatDate($event['date']); ?></td>
                                <td><?php echo !empty($event['is_approved']) ? 'Approved' : 'Pending'; ?></td>
                                <td>
                                    <button class="btn btn-ghost btn-sm" onclick='editEvent(<?php echo $editArguments; ?>)'>Edit</button>
                                    <button class="btn btn-ghost btn-sm" style="color: var(--error);" onclick='deleteEvent(<?php echo (int)$event['id']; ?>, <?php echo json_encode((string)$event['title'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>)'>Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="color: var(--text-muted);">No events found.</p>
                <?php endif; ?>
            </div>

            <div class="dashboard-card admin-section-target" id="admin-organizers" style="margin-top: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="margin: 0;">Verified Organizers & Events</h3>
                    <input type="text" id="organizerSearch" placeholder="Search organizers..." style="padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); width: 250px; font-family: inherit; font-size: 14px;">
                </div>
                <?php
                $stmt = $pdo->query("SELECT * FROM users WHERE role = 'organizer' AND is_verified = 1 ORDER BY created_at DESC");
                $verifiedOrgs = $stmt->fetchAll();
                if(!empty($verifiedOrgs)):
                ?>
                <div class="verified-orgs-list" id="orgsList" style="display: flex; flex-direction: column; gap: 16px; margin-top: 16px;">
                    <?php foreach($verifiedOrgs as $org): ?>
                    <div class="org-details" style="padding: 16px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg-dark);" data-org-search="<?php echo strtolower($org['name'] . ' ' . $org['email']); ?>">                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <div>
                                <strong style="font-size: 16px;"><?php echo sanitize($org['name']); ?></strong>
                                <span style="color: var(--text-muted); font-size: 14px; margin-left: 8px;"><?php echo sanitize($org['email']); ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="status-badge active">Verified Organizer</span>
                                <a href="verify_api.php?type=remove_organizer&id=<?php echo $org['id']; ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Remove this organizer and revert them to a standard user?');">Remove Organizer</a>
                            </div>
                        </div>
                        
                        <?php
                        $stmtE = $pdo->prepare("SELECT * FROM events WHERE organizer_id = ? ORDER BY date DESC");
                        $stmtE->execute([$org['id']]);
                        $orgEvents = $stmtE->fetchAll();
                        ?>
                        
                        <h4 style="font-size: 14px; color: var(--text-secondary); margin-bottom: 8px;">Events by this organizer (<?php echo count($orgEvents); ?>)</h4>
                        
                        <?php if (!empty($orgEvents)): ?>
                        <div style="overflow-x: auto;">
                            <table class="events-table" style="font-size: 14px; margin-bottom: 0;">
                                <thead>
                                    <tr>
                                        <th>Event Title</th>
                                        <th>Date</th>
                                        <th>Price</th>
                                        <th>Capacity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($orgEvents as $ev): ?>
                                    <tr>
                                        <td><?php echo sanitize($ev['title']); ?></td>
                                        <td><?php echo formatDate($ev['date']); ?></td>
                                        <td><?php echo $ev['price'] > 0 ? formatPrice($ev['price']) : 'Free'; ?></td>
                                        <td><?php echo $ev['capacity']; ?></td>
                                        <td>
                                            <button class="btn btn-ghost btn-sm" onclick="editEvent(<?php echo $ev['id']; ?>, '<?php echo addslashes($ev['title']); ?>', '<?php echo addslashes($ev['description']); ?>', '<?php echo $ev['date']; ?>', '<?php echo $ev['time']; ?>', '<?php echo addslashes($ev['venue']); ?>', '<?php echo addslashes($ev['address']); ?>', <?php echo $ev['price']; ?>, <?php echo $ev['capacity']; ?>, '<?php echo $ev['category']; ?>', '<?php echo addslashes($ev['image']); ?>', '<?php echo addslashes($ev['expectations'] ?? ''); ?>', '<?php echo addslashes($ev['ticket_primary_color'] ?? '#4f46e5'); ?>', '<?php echo addslashes($ev['ticket_secondary_color'] ?? '#ec4899'); ?>')">Edit</button>
                                            <button class="btn btn-ghost btn-sm" style="color: var(--error);" onclick="deleteEvent(<?php echo $ev['id']; ?>, '<?php echo addslashes($ev['title']); ?>')">Delete</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p style="font-size: 14px; color: var(--text-muted);">No events created yet.</p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color: var(--text-muted); margin-top: 16px;">No verified organizers found.</p>
                <?php endif; ?>
            </div>

        <?php elseif ($user['role'] === 'organizer' && !$isVerified): ?>
            <!-- Pending Organizer Dashboard -->
            <div class="dashboard-header text-center" style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding: 40px;">
                <h1>Account Under Review</h1>
                <p style="color: var(--text-secondary); max-width: 500px; margin-top: 16px;">
                    Thank you for signing up as an Event Organizer! Your account is currently pending administrative verification. You will be able to create and manage events once verified.
                </p>
            </div>
            
        <?php elseif ($user['role'] === 'user'): ?>
            <!-- User Dashboard / Upgrade path -->
            <div class="dashboard-header text-center" style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding: 40px;">
                <h1>User Dashboard</h1>
                <p style="color: var(--text-secondary); max-width: 500px; margin-top: 16px;">
                    You are currently a Standard User. If you want to host events, you can request organizer access.
                </p>
                <?php if (isset($_GET['status']) && $_GET['status'] === 'pending'): ?>
                    <div class="form-success" style="display:block; margin-top: 16px;">Your organizer request has been sent to the admin for review.</div>
                <?php elseif (isset($_GET['status']) && $_GET['status'] === 'cancelled'): ?>
                    <div class="form-warning" style="display:block; margin-top: 16px;">Your organizer request has been cancelled.</div>
                <?php elseif (!empty($user['organizer_request_pending'])): ?>
                    <div class="form-warning" style="display:block; margin-top: 16px;">Your organizer request is still pending admin approval.</div>
                    <a href="verify_api.php?type=cancel_upgrade" class="btn btn-secondary" style="margin-top:16px;" onclick="return confirm('Are you sure you want to cancel your organizer request?');">Cancel Organizer Request</a>
                <?php else: ?>
                    <a href="verify_api.php?type=upgrade" class="btn btn-primary" style="margin-top:24px;">Request Organizer Access</a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Verified Organizer Dashboard -->
            <div class="dashboard-header">
                <div>
                    <h1>Organizer Dashboard</h1>
                    <p style="color: var(--text-secondary);">Manage your events and check-in attendees</p>
                </div>
                <button class="btn btn-primary" data-modal="createEventModal">+ Create Event</button>
            </div>
        
        <div class="dashboard-card" style="margin-bottom: 24px;">
            <h3>Find Ticket by ID</h3>
            <p style="color: var(--text-secondary); margin-bottom: 16px;">Search for a booking quickly by ticket ID to verify payment status and attendee details.</p>
            <form method="POST" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <input type="text" name="ticket_id" placeholder="Enter ticket ID (for example HAP-XXXXXX)" required style="flex: 1; min-width: 260px; padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg); color: var(--text-primary);">
                <button type="submit" name="search_ticket" value="1" class="btn btn-primary">Find Ticket</button>
            </form>
        </div>

        <!-- Stats -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>Total Events</h3>
                <div class="value"><?php echo $stats['total_events']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Bookings</h3>
                <div class="value"><?php echo $stats['total_bookings']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="value"><?php echo formatPrice($stats['total_revenue']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Checked In</h3>
                <div class="value"><?php echo $stats['checked_in']; ?></div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <!-- QR Scanner -->
            <div class="dashboard-card">
                <h3>
                    QR Code Scanner
                    <button class="btn btn-secondary btn-sm" onclick="startCamera()">📷 Start Camera</button>
                </h3>
                
                <div class="scanner-container">
                    <div class="scanner-viewport">
                        <video id="scannerVideo" autoplay playsinline style="display: none;"></video>
                        <div class="scanner-overlay" id="scannerOverlay" style="display: none;"></div>
                        <div id="scannerPlaceholder" style="text-align: center; color: var(--text-muted);">
                            <p>Click "Start Camera" to begin scanning</p>
                        </div>
                    </div>
                    <canvas id="scannerCanvas" style="display: none;"></canvas>
                    
                    <form id="scannerForm" method="POST" style="margin-top: 24px;">
                        <input type="hidden" name="scan_ticket" value="1">
                        <div class="form-group">
                            <label>Search ticket for</label>
                            <select name="event_id" style="width: 100%; padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg); color: var(--text-primary);">
                                <option value="">All <?php echo isAdmin() ? 'events' : 'my events'; ?></option>
                                <?php foreach ($events as $eventOption): ?>
                                <option value="<?php echo $eventOption['id']; ?>" <?php echo (isset($_POST['event_id']) && $_POST['event_id'] == $eventOption['id']) ? 'selected' : ''; ?>><?php echo sanitize($eventOption['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Or enter ticket ID manually</label>
                            <div style="display: flex; gap: 12px;">
                                <input id="scannerTicketInput" type="text" name="ticket_id" placeholder="HAP-XXXXXXXX" required style="flex: 1;">
                                <button type="submit" name="scan_ticket" value="1" class="btn btn-primary">Verify</button>
                            </div>
                        </div>
                        <div id="scannerStatus" style="margin-top: 8px; color: var(--text-secondary); font-size: 14px;"></div>
                    </form>
                    
                    <?php if (isset($scanResult)): ?>
                    <div class="scanner-result <?php echo $scanResult['status']; ?>">
                        <h4 style="color: <?php echo $scanResult['status'] === 'success' ? 'var(--success)' : 'var(--error)'; ?>;">
                            <?php echo $scanResult['status'] === 'success' ? '✓' : '✕'; ?> 
                            <?php echo $scanResult['message']; ?>
                        </h4>
                        <?php if (isset($scanResult['ticket'])): ?>
                        <p><strong>Event:</strong> <?php echo sanitize($scanResult['ticket']['event_title']); ?></p>
                        <p><strong>Date:</strong> <?php echo formatDate($scanResult['ticket']['event_date']); ?> at <?php echo formatTime($scanResult['ticket']['event_time']); ?></p>
                        <p><strong>Venue:</strong> <?php echo sanitize($scanResult['ticket']['event_venue']); ?></p>
                        <p><strong>Ticket ID:</strong> <?php echo sanitize($scanResult['ticket']['qr_code']); ?></p>
                        <p><strong>Booked By:</strong> <?php echo sanitize($scanResult['ticket']['user_name']); ?></p>
                        <p><strong>Quantity:</strong> <?php echo $scanResult['ticket']['quantity']; ?> ticket(s)</p>
                        <p><strong>Payment Status:</strong> <?php echo ($scanResult['ticket']['status'] ?? 'confirmed') === 'confirmed' ? 'Paid and confirmed' : ucfirst($scanResult['ticket']['status'] ?? 'unknown'); ?></p>
                        <p><strong>Check-in Status:</strong> <?php echo !empty($scanResult['ticket']['checked_in']) ? 'Already checked in' : 'Ready to check in'; ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Bookings -->
            <div class="dashboard-card">
                <h3>Recent Bookings</h3>
                <?php if (!empty($recentBookings)): ?>
                <table class="events-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>User</th>
                            <th>Qty</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBookings as $booking): ?>
                        <tr>
                            <td><?php echo sanitize($booking['event_title']); ?></td>
                            <td><?php echo sanitize($booking['user_name']); ?></td>
                            <td><?php echo $booking['quantity']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $booking['checked_in'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $booking['checked_in'] ? 'Checked In' : 'Pending'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: var(--text-muted); text-align: center; padding: 24px;">No bookings yet</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Events List -->
        <div class="dashboard-card" style="margin-top: 24px;">
            <h3>Your Events</h3>
            <?php if (!empty($events)): ?>
            <table class="events-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Price</th>
                        <th>Capacity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                    <tr>
                        <td>
                            <strong><?php echo sanitize($event['title']); ?></strong>
                            <br><small style="color: var(--text-muted);"><?php echo sanitize($event['category']); ?></small>
                        </td>
                        <td><?php echo formatDate($event['date']); ?></td>
                        <td><?php echo sanitize($event['venue']); ?></td>
                        <td><?php echo $event['price'] > 0 ? formatPrice($event['price']) : 'Free'; ?></td>
                        <td><?php echo $event['capacity']; ?></td>
                        <td>
                            <button class="btn btn-ghost btn-sm" onclick="editEvent(<?php echo $event['id']; ?>, '<?php echo addslashes($event['title']); ?>', '<?php echo addslashes($event['description']); ?>', '<?php echo $event['date']; ?>', '<?php echo $event['time']; ?>', '<?php echo addslashes($event['venue']); ?>', '<?php echo addslashes($event['address']); ?>', <?php echo $event['price']; ?>, <?php echo $event['capacity']; ?>, '<?php echo $event['category']; ?>', '<?php echo addslashes($event['image']); ?>', '<?php echo addslashes($event['expectations'] ?? ''); ?>', '<?php echo addslashes($event['ticket_primary_color'] ?? '#4f46e5'); ?>', '<?php echo addslashes($event['ticket_secondary_color'] ?? '#ec4899'); ?>')">Edit</button>
                            <button class="btn btn-ghost btn-sm" style="color: var(--error);" onclick="deleteEvent(<?php echo $event['id']; ?>, '<?php echo addslashes($event['title']); ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <h3>No Events Created</h3>
                <p>Create your first event to start selling tickets!</p>
                <button class="btn btn-primary" data-modal="createEventModal">Create Event</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'site_footer.php'; ?>

    <!-- Logout Confirmation Modal -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal" style="max-width: 420px;">
            <div class="modal-header">
                <h3>Log out?</h3>
                <button class="modal-close" onclick="closeModal('logoutModal')">&times;</button>
            </div>
            <p style="color: var(--text-secondary); margin-bottom: 8px;">Are you sure you want to end your session?</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('logoutModal')">Cancel</button>
                <a href="auth.php?action=logout" class="btn btn-primary">Yes, log out</a>
            </div>
        </div>
    </div>

    <!-- Create Event Modal -->
    <div class="modal-overlay" id="createEventModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3>Create New Event</h3>
                <button class="modal-close" onclick="closeModal('createEventModal')">&times;</button>
            </div>
            <form method="POST" action="create_event.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Event Title</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label>What to Expect (one per line)</label>
                    <textarea name="expectations" rows="4" placeholder="Instant booking confirmation&#10;Unique QR code ticket&#10;Contactless check-in&#10;Digital ticket in My Tickets"></textarea>
                </div>
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" required>
                    </div>
                    <div class="form-group">
                        <label>Time</label>
                        <input type="time" name="time" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Venue</label>
                    <input type="text" name="venue" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address">
                </div>
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Price ($)</label>
                        <input type="number" name="price" step="0.01" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Capacity</label>
                        <input type="number" name="capacity" min="1" value="100">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="Music">Music</option>
                            <option value="Sports">Sports</option>
                            <option value="Tech">Tech</option>
                            <option value="Art">Art</option>
                            <option value="Food">Food</option>
                            <option value="Business">Business</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Ticket Colors</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 8px;">
                        <div>
                            <label style="font-size: 12px; margin-bottom: 6px; display: block;">Primary</label>
                            <input type="color" id="create_ticket_primary_color" name="ticket_primary_color" value="#4f46e5" style="width: 100%; height: 44px; padding: 4px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-surface);">
                        </div>
                        <div>
                            <label style="font-size: 12px; margin-bottom: 6px; display: block;">Secondary</label>
                            <input type="color" id="create_ticket_secondary_color" name="ticket_secondary_color" value="#ec4899" style="width: 100%; height: 44px; padding: 4px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-surface);">
                        </div>
                    </div>
                    <div id="createTicketColorPreview" style="height: 44px; border-radius: var(--radius-sm); background: linear-gradient(135deg, #4f46e5, #ec4899); border: 1px solid rgba(255,255,255,0.2);"></div>
                </div>
                <div class="form-group">
                    <label>Event Image</label>
                    <div style="margin-bottom: 12px;">
                        <input type="file" id="createImageFile" name="image_file" accept="image/*" style="display: none;">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('createImageFile').click()" style="width: 100%; margin-bottom: 8px;">📤 Upload Image</button>
                        <span id="createImageFileName" style="font-size: 12px; color: var(--text-muted);">No file selected</span>
                    </div>
                    <div style="padding: 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg-dark); margin-bottom: 12px; text-align: center;">
                        <img id="createImagePreview" style="max-width: 100%; max-height: 200px; display: none;">
                        <p id="createImagePlaceholder" style="color: var(--text-muted); margin: 0;">Image preview will appear here</p>
                    </div>
                    <input type="url" name="image" placeholder="Or paste image URL (optional)" style="width: 100%;">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Event</button>
            </form>
        </div>
    </div>

    <!-- Edit Event Modal -->
    <div class="modal-overlay" id="editEventModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3>Edit Event</h3>
                <button class="modal-close" onclick="closeModal('editEventModal')">&times;</button>
            </div>
            <form method="POST" action="event_api.php?action=update" id="editEventForm" enctype="multipart/form-data">
                <input type="hidden" name="event_id" id="edit_event_id">
                <div class="form-group">
                    <label>Event Title</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label>What to Expect (one per line)</label>
                    <textarea name="expectations" id="edit_expectations" rows="4"></textarea>
                </div>
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" id="edit_date" required>
                    </div>
                    <div class="form-group">
                        <label>Time</label>
                        <input type="time" name="time" id="edit_time" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Venue</label>
                    <input type="text" name="venue" id="edit_venue" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" id="edit_address">
                </div>
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Price ($)</label>
                        <input type="number" name="price" id="edit_price" step="0.01" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Capacity</label>
                        <input type="number" name="capacity" id="edit_capacity" min="1" value="100">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="edit_category">
                            <option value="Music">Music</option>
                            <option value="Sports">Sports</option>
                            <option value="Tech">Tech</option>
                            <option value="Art">Art</option>
                            <option value="Food">Food</option>
                            <option value="Business">Business</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Ticket Colors</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 8px;">
                        <div>
                            <label style="font-size: 12px; margin-bottom: 6px; display: block;">Primary</label>
                            <input type="color" id="edit_ticket_primary_color" name="ticket_primary_color" value="#4f46e5" style="width: 100%; height: 44px; padding: 4px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-surface);">
                        </div>
                        <div>
                            <label style="font-size: 12px; margin-bottom: 6px; display: block;">Secondary</label>
                            <input type="color" id="edit_ticket_secondary_color" name="ticket_secondary_color" value="#ec4899" style="width: 100%; height: 44px; padding: 4px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-surface);">
                        </div>
                    </div>
                    <div id="editTicketColorPreview" style="height: 44px; border-radius: var(--radius-sm); background: linear-gradient(135deg, #4f46e5, #ec4899); border: 1px solid rgba(255,255,255,0.2);"></div>
                </div>
                <div class="form-group">
                    <label>Event Image</label>
                    <div style="margin-bottom: 12px;">
                        <input type="file" id="editImageFile" name="image_file" accept="image/*" style="display: none;">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('editImageFile').click()" style="width: 100%; margin-bottom: 8px;">📤 Upload New Image</button>
                        <span id="editImageFileName" style="font-size: 12px; color: var(--text-muted);">No new file selected</span>
                    </div>
                    <div style="padding: 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg-dark); margin-bottom: 12px; text-align: center;">
                        <img id="editImagePreview" style="max-width: 100%; max-height: 200px; display: none;">
                        <p id="editImagePlaceholder" style="color: var(--text-muted); margin: 0;">Image preview will appear here</p>
                    </div>
                    <input type="text" name="image" id="edit_image" placeholder="Or paste image URL (optional)">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Update Event</button>
            </form>
        </div>
    </div>

    <!-- Delete Event Modal -->
    <div class="modal-overlay" id="deleteEventModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Delete Event</h3>
                <button class="modal-close" onclick="closeModal('deleteEventModal')">&times;</button>
            </div>
            <form method="POST" action="event_api.php?action=delete" id="deleteEventForm">
                <input type="hidden" name="event_id" id="delete_event_id">
                <p style="text-align: center; margin-bottom: 24px;">Are you sure you want to delete <strong id="delete_event_title"></strong>? This action cannot be undone.</p>
                <button type="submit" class="btn btn-danger" style="width: 100%;">Delete Event</button>
            </form>
        </div>
    </div>

    <!-- Ticket Lookup Modal -->
    <div class="modal-overlay" id="ticketLookupModal">
        <div class="modal" style="max-width: 560px;">
            <div class="modal-header">
                <h3>Ticket Details</h3>
                <button class="modal-close" onclick="closeModal('ticketLookupModal')">&times;</button>
            </div>
            <?php if (isset($scanResult) && (!empty($_POST['search_ticket']) || !empty($_POST['scan_ticket']))): ?>
            <div style="padding: 8px 0 10px;">
                <div class="scanner-result <?php echo $scanResult['status']; ?>" style="margin-top: 0;">
                    <h4 style="color: <?php echo $scanResult['status'] === 'success' ? 'var(--success)' : 'var(--error)'; ?>;">
                        <?php echo $scanResult['status'] === 'success' ? '✓' : '✕'; ?>
                        <?php echo $scanResult['message']; ?>
                    </h4>
                    <?php if (isset($scanResult['ticket'])): ?>
                    <div style="display: grid; gap: 10px; margin-top: 12px;">
                        <p style="margin: 0;"><strong>Event:</strong> <?php echo sanitize($scanResult['ticket']['event_title']); ?></p>
                        <p style="margin: 0;"><strong>Date:</strong> <?php echo formatDate($scanResult['ticket']['event_date']); ?> at <?php echo formatTime($scanResult['ticket']['event_time']); ?></p>
                        <p style="margin: 0;"><strong>Venue:</strong> <?php echo sanitize($scanResult['ticket']['event_venue']); ?></p>
                        <p style="margin: 0;"><strong>Ticket ID:</strong> <?php echo sanitize($scanResult['ticket']['qr_code']); ?></p>
                        <p style="margin: 0;"><strong>Booked By:</strong> <?php echo sanitize($scanResult['ticket']['user_name']); ?></p>
                        <p style="margin: 0;"><strong>Quantity:</strong> <?php echo $scanResult['ticket']['quantity']; ?> ticket(s)</p>
                        <p style="margin: 0;"><strong>Payment Status:</strong> <?php echo ($scanResult['ticket']['status'] ?? 'confirmed') === 'confirmed' ? 'Paid and confirmed' : ucfirst($scanResult['ticket']['status'] ?? 'unknown'); ?></p>
                        <p style="margin: 0;"><strong>Check-in Status:</strong> <?php echo !empty($scanResult['ticket']['checked_in']) ? 'Already checked in' : 'Ready to check in'; ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <p style="padding: 12px 0; color: var(--text-muted);">Use the search bar above to look up a ticket.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications Modal -->
    <div class="modal-overlay" id="notificationsModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Notifications</h3>
                <button class="modal-close" onclick="closeModal('notificationsModal')">&times;</button>
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notif): ?>
                    <div style="padding: 16px; border-bottom: 1px solid var(--border); <?php echo !$notif['is_read'] ? 'background: var(--bg-surface);' : ''; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <strong><?php echo sanitize($notif['title']); ?></strong>
                            <small style="color: var(--text-muted);"><?php echo date('M j, g:i A', strtotime($notif['created_at'])); ?></small>
                        </div>
                        <p style="margin-top: 8px; color: var(--text-secondary); font-size: 14px;"><?php echo sanitize($notif['message']); ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <p style="text-align: center; padding: 24px; color: var(--text-muted);">No notifications</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/main.js?v=7"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.0.0/dist/jsQR.min.js"></script>
    <script>
        let videoStream = null;
        let lastScannedTicket = '';
        
        // Edit event function
        function editEvent(id, title, description, date, time, venue, address, price, capacity, category, image, expectations, ticketPrimaryColor, ticketSecondaryColor) {
            document.getElementById('edit_event_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_time').value = time;
            document.getElementById('edit_venue').value = venue;
            document.getElementById('edit_address').value = address || '';
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_capacity').value = capacity;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_image').value = image || '';
            editImageFile.value = '';
            editImageFileName.textContent = 'No new file selected';
            if (image) {
                editImagePreview.src = image;
                editImagePreview.style.display = 'block';
                editImagePlaceholder.style.display = 'none';
            } else {
                editImagePreview.removeAttribute('src');
                editImagePreview.style.display = 'none';
                editImagePlaceholder.style.display = 'block';
            }
            document.getElementById('edit_expectations').value = expectations || '';
            document.getElementById('edit_ticket_primary_color').value = ticketPrimaryColor || '#4f46e5';
            document.getElementById('edit_ticket_secondary_color').value = ticketSecondaryColor || '#ec4899';
            updateTicketColorPreview('edit');
            openModal('editEventModal');
        }
        
        // Delete event function
        function deleteEvent(id, title) {
            document.getElementById('delete_event_id').value = id;
            document.getElementById('delete_event_title').textContent = title;
            openModal('deleteEventModal');
        }
        
        // Close modal function
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                modal.style.opacity = '0';
                modal.style.visibility = 'hidden';
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
        
        // Open modal function
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                modal.style.display = 'flex';
                modal.style.visibility = 'visible';
                modal.style.opacity = '1';
                document.body.style.overflow = 'hidden';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($scanResult) && (!empty($_POST['search_ticket']) || !empty($_POST['scan_ticket']))): ?>
            openModal('ticketLookupModal');
            <?php endif; ?>
        });
        
        async function startCamera() {
            const video = document.getElementById('scannerVideo');
            const placeholder = document.getElementById('scannerPlaceholder');
            const overlay = document.getElementById('scannerOverlay');
            
            try {
                videoStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'environment' } 
                });
                
                video.srcObject = videoStream;
                video.style.display = 'block';
                placeholder.style.display = 'none';
                overlay.style.display = 'block';
                
                scanQRCode();
            } catch (error) {
                alert('Camera access denied. Please enable camera permissions.');
            }
        }
        
        function scanQRCode() {
            const video = document.getElementById('scannerVideo');
            const canvas = document.getElementById('scannerCanvas');
            const statusEl = document.getElementById('scannerStatus');
            const ticketInput = document.getElementById('scannerTicketInput');
            const form = document.getElementById('scannerForm');
            const ctx = canvas.getContext('2d');
            
            if (!video || !videoStream || video.readyState < video.HAVE_CURRENT_DATA) {
                requestAnimationFrame(scanQRCode);
                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = window.jsQR ? window.jsQR(imageData.data, canvas.width, canvas.height) : null;

            if (code && code.data) {
                const ticketId = code.data.trim();
                if (ticketId && ticketId !== lastScannedTicket) {
                    lastScannedTicket = ticketId;
                    if (ticketInput) ticketInput.value = ticketId;
                    if (statusEl) statusEl.textContent = `Scanned: ${ticketId}`;
                    if (form) form.submit();
                }
                return;
            }
            
            requestAnimationFrame(scanQRCode);
        }
        
        // Stop camera when page closes
        window.addEventListener('beforeunload', () => {
            if (videoStream) {
                videoStream.getTracks().forEach(track => track.stop());
            }
        });

        // Organizer search functionality
        const organizerSearch = document.getElementById('organizerSearch');
        if (organizerSearch) {
            organizerSearch.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const orgItems = document.querySelectorAll('[data-org-search]');
                
                orgItems.forEach(item => {
                    const searchData = item.getAttribute('data-org-search');
                    if (searchData.includes(searchTerm)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }

        function updateTicketColorPreview(type) {
            const primary = document.getElementById(type + '_ticket_primary_color');
            const secondary = document.getElementById(type + '_ticket_secondary_color');
            const preview = document.getElementById(type + 'TicketColorPreview');
            if (primary && secondary && preview) {
                preview.style.background = `linear-gradient(135deg, ${primary.value || '#4f46e5'}, ${secondary.value || '#ec4899'})`;
            }
        }

        // Image file upload preview for Create
        const createImageFile = document.getElementById('createImageFile');
        const createImagePreview = document.getElementById('createImagePreview');
        const createImagePlaceholder = document.getElementById('createImagePlaceholder');
        const createImageFileName = document.getElementById('createImageFileName');
        
        if (createImageFile) {
            createImageFile.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        createImagePreview.src = e.target.result;
                        createImagePreview.style.display = 'block';
                        createImagePlaceholder.style.display = 'none';
                        createImageFileName.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        const createImageUrl = document.querySelector('#createEventModal input[name="image"]');
        if (createImageUrl) {
            createImageUrl.addEventListener('input', function() {
                if (!createImageFile.files.length && this.value) {
                    createImagePreview.src = this.value;
                    createImagePreview.style.display = 'block';
                    createImagePlaceholder.style.display = 'none';
                } else if (!createImageFile.files.length) {
                    createImagePreview.removeAttribute('src');
                    createImagePreview.style.display = 'none';
                    createImagePlaceholder.style.display = 'block';
                }
            });
        }

        // Image file upload preview for Edit
        const editImageFile = document.getElementById('editImageFile');
        const editImagePreview = document.getElementById('editImagePreview');
        const editImagePlaceholder = document.getElementById('editImagePlaceholder');
        const editImageFileName = document.getElementById('editImageFileName');
        
        if (editImageFile) {
            editImageFile.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        editImagePreview.src = e.target.result;
                        editImagePreview.style.display = 'block';
                        editImagePlaceholder.style.display = 'none';
                        editImageFileName.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        if (editImagePreview) {
            editImagePreview.addEventListener('error', function() {
                this.style.display = 'none';
                editImagePlaceholder.style.display = 'block';
            });
        }

        const editImageUrl = document.getElementById('edit_image');
        if (editImageUrl) {
            editImageUrl.addEventListener('input', function() {
                if (!editImageFile.files.length) {
                    if (this.value) {
                        editImagePreview.src = this.value;
                        editImagePreview.style.display = 'block';
                        editImagePlaceholder.style.display = 'none';
                    } else {
                        editImagePreview.removeAttribute('src');
                        editImagePreview.style.display = 'none';
                        editImagePlaceholder.style.display = 'block';
                    }
                }
            });
        }

        const createPrimaryColor = document.getElementById('create_ticket_primary_color');
        const createSecondaryColor = document.getElementById('create_ticket_secondary_color');
        if (createPrimaryColor && createSecondaryColor) {
            createPrimaryColor.addEventListener('input', () => updateTicketColorPreview('create'));
            createSecondaryColor.addEventListener('input', () => updateTicketColorPreview('create'));
            updateTicketColorPreview('create');
        }

        const editPrimaryColor = document.getElementById('edit_ticket_primary_color');
        const editSecondaryColor = document.getElementById('edit_ticket_secondary_color');
        if (editPrimaryColor && editSecondaryColor) {
            editPrimaryColor.addEventListener('input', () => updateTicketColorPreview('edit'));
            editSecondaryColor.addEventListener('input', () => updateTicketColorPreview('edit'));
            updateTicketColorPreview('edit');
        }

        const editEventForm = document.getElementById('editEventForm');
        if (editEventForm) {
            editEventForm.addEventListener('submit', async function(event) {
                event.preventDefault();

                const submitButton = editEventForm.querySelector('button[type="submit"]');
                const originalText = submitButton ? submitButton.textContent : '';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Saving...';
                }

                try {
                    const response = await fetch(editEventForm.action, {
                        method: 'POST',
                        body: new FormData(editEventForm)
                    });
                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.error || 'Failed to update event.');
                    }

                    sessionStorage.setItem('eventUpdateMessage', result.message || 'Event updated successfully.');
                    closeModal('editEventModal');
                    window.location.reload();
                } catch (error) {
                    showToast(error.message || 'Failed to update event.', 'error');
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalText;
                    }
                }
            });
        }

        const eventUpdateMessage = sessionStorage.getItem('eventUpdateMessage');
        if (eventUpdateMessage) {
            sessionStorage.removeItem('eventUpdateMessage');
            showToast(eventUpdateMessage, 'success');
        }
    </script>
</body>
</html>

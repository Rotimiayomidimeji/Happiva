<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pdo = getDB();
$currentUserId = (int)$_SESSION['user_id'];
$pageTitle = 'Messages - EventSmart';

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$recipientId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'], $_POST['recipient_id'], $_POST['event_id'])) {
    $messageText = trim($_POST['message'] ?? '');
    $recipientId = (int)$_POST['recipient_id'];
    $eventId = (int)$_POST['event_id'];

    if ($messageText !== '' && $recipientId > 0 && $eventId > 0 && $recipientId !== $currentUserId) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, event_id, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$currentUserId, $recipientId, $eventId, $messageText]);

        $eventTitle = '';
        $eventTitleStmt = $pdo->prepare("SELECT title FROM events WHERE id = ?");
        $eventTitleStmt->execute([$eventId]);
        $eventTitle = (string) ($eventTitleStmt->fetchColumn() ?: '');

        $notificationTitle = $eventTitle !== '' ? 'New message about ' . $eventTitle : 'New message received';
        $notifyStmt = $pdo->prepare("INSERT INTO notifications (user_id, sender_id, type, title, message, reference_id) VALUES (?, ?, 'message', ?, ?, ?)");
        $notifyStmt->execute([$recipientId, $currentUserId, $notificationTitle, $messageText, $eventId]);
    }

    redirect('messages.php?event_id=' . $eventId . '&user_id=' . $recipientId);
}

$eventsStmt = $pdo->prepare("SELECT e.id, e.title, e.date, e.venue FROM bookings b JOIN events e ON b.event_id = e.id WHERE b.user_id = ? ORDER BY e.date DESC");
$eventsStmt->execute([$currentUserId]);
$events = $eventsStmt->fetchAll();

$selectedEvent = null;
if ($eventId > 0) {
    $eventStmt = $pdo->prepare("SELECT id, title, date, venue FROM events WHERE id = ?");
    $eventStmt->execute([$eventId]);
    $selectedEvent = $eventStmt->fetch();
}

$attendees = [];
if ($eventId > 0) {
    $attendeeStmt = $pdo->prepare("SELECT DISTINCT u.id, u.name, u.profile_image FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.event_id = ? AND b.user_id != ? ORDER BY u.name ASC");
    $attendeeStmt->execute([$eventId, $currentUserId]);
    $attendees = $attendeeStmt->fetchAll();
}

$eventUnreadCounts = [];
if (!empty($events)) {
    $eventIds = array_column($events, 'id');
    if (!empty($eventIds)) {
        $placeholders = implode(', ', array_fill(0, count($eventIds), '?'));
        $eventUnreadStmt = $pdo->prepare("SELECT reference_id AS event_id, COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND type = 'message' AND is_read = 0 AND reference_id IN ($placeholders) GROUP BY reference_id");
        $eventUnreadStmt->execute(array_merge([(int) $currentUserId], $eventIds));
        while ($row = $eventUnreadStmt->fetch()) {
            $eventUnreadCounts[(int) $row['event_id']] = (int) $row['unread_count'];
        }
    }
}

$attendeeUnreadCounts = [];
if ($eventId > 0) {
    $attendeeUnreadStmt = $pdo->prepare("SELECT sender_id AS attendee_id, COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND type = 'message' AND is_read = 0 AND reference_id = ? GROUP BY sender_id");
    $attendeeUnreadStmt->execute([(int) $currentUserId, $eventId]);
    while ($row = $attendeeUnreadStmt->fetch()) {
        $attendeeUnreadCounts[(int) $row['attendee_id']] = (int) $row['unread_count'];
    }
}

$thread = [];
if ($eventId > 0 && $recipientId > 0) {
    $threadStmt = $pdo->prepare("SELECT m.*, s.name AS sender_name, r.name AS recipient_name FROM messages m JOIN users s ON m.sender_id = s.id JOIN users r ON m.recipient_id = r.id WHERE ((m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?)) AND m.event_id = ? ORDER BY m.created_at ASC");
    $threadStmt->execute([$currentUserId, $recipientId, $recipientId, $currentUserId, $eventId]);
    $thread = $threadStmt->fetchAll();
}

$activeRecipient = null;
if ($recipientId > 0) {
    $recipientStmt = $pdo->prepare("SELECT id, name, profile_image FROM users WHERE id = ?");
    $recipientStmt->execute([$recipientId]);
    $activeRecipient = $recipientStmt->fetch();
}

if ($eventId > 0) {
    if ($recipientId > 0) {
        markMessageNotificationsRead($pdo, $currentUserId, $eventId, $recipientId);
    } else {
        markMessageNotificationsRead($pdo, $currentUserId, $eventId);
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
    <link rel="stylesheet" href="css/style.css?v=9">
    <link rel="icon" href="uploads/eventsmart-logo.svg">
</head>
<body class="logged-in">
    <nav class="navbar scrolled">
        <div class="container">
            <a href="index.php" class="logo">
                <img src="uploads/eventsmart-logo.svg" alt="EventSmart logo" style="height: 34px; width: 34px; object-fit: contain; margin-right: 10px;">
                <span>EventSmart</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="messages.php" class="active">Messages</a></li>
                <li><a href="tickets.php">My Tickets</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
            <div class="nav-actions">
                <span class="user-name"><?php echo sanitize($_SESSION['user_name']); ?></span>
                <a href="#" class="btn btn-secondary btn-sm" onclick="event.preventDefault(); openModal('logoutModal');">Logout</a>
                <button class="mobile-menu-btn" type="button" aria-label="Toggle navigation" aria-expanded="false">☰</button>
            </div>
        </div>
    </nav>

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

    <section class="section messages-page-section">
        <div class="container">
            <div class="section-header" style="text-align: left; margin-bottom: 24px;">
                <h2>Message Hub</h2>
                <p>Chat with other attendees from the events you booked. Coordinate meetups, ask questions, and stay connected.</p>
            </div>

            <div style="display:flex; gap:12px; align-items:center; margin-bottom:12px;">
                <a href="#" class="btn btn-ghost btn-sm" onclick="window.location.reload();">Refresh</a>
            </div>

            <div class="messages-shell">
                <aside class="messages-sidebar">
                    <h3>Your events</h3>
                    <?php if (!empty($events)): ?>
                        <ul class="message-event-list">
                            <?php foreach ($events as $eventItem): ?>
                                <li class="message-event-item <?php echo ($selectedEvent && $selectedEvent['id'] == $eventItem['id']) ? 'active' : ''; ?>">
                                    <a href="messages.php?event_id=<?php echo $eventItem['id']; ?>">
                                        <div class="message-list-main">
                                            <strong><?php echo sanitize($eventItem['title']); ?></strong>
                                            <?php $eventUnreadCount = $eventUnreadCounts[$eventItem['id']] ?? 0; ?>
                                            <?php if ($eventUnreadCount > 0): ?>
                                                <span class="message-status-pill"><?php echo $eventUnreadCount > 1 ? $eventUnreadCount . ' new' : 'New'; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-list-meta">
                                            <span><?php echo sanitize($eventItem['venue']); ?></span>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="empty-profile-state">Book an event first to start messaging attendees.</p>
                    <?php endif; ?>

                    <?php if (!empty($selectedEvent) && !empty($attendees)): ?>
                        <h3 style="margin-top: 24px;">Attendees</h3>
                        <ul class="message-event-list">
                            <?php foreach ($attendees as $attendee): ?>
                                <li class="message-event-item <?php echo ($activeRecipient && $activeRecipient['id'] == $attendee['id']) ? 'active' : ''; ?>">
                                    <a href="messages.php?event_id=<?php echo $selectedEvent['id']; ?>&user_id=<?php echo $attendee['id']; ?>">
                                        <div class="message-list-main">
                                            <strong><?php echo sanitize($attendee['name']); ?></strong>
                                            <?php $attendeeUnreadCount = $attendeeUnreadCounts[$attendee['id']] ?? 0; ?>
                                            <?php if ($attendeeUnreadCount > 0): ?>
                                                <span class="message-status-pill"><?php echo $attendeeUnreadCount > 1 ? $attendeeUnreadCount . ' new' : 'New'; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-list-meta">
                                            <span>Private conversation</span>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </aside>

                <div class="messages-panel">
                    <?php if (empty($selectedEvent)): ?>
                        <div class="empty-state-card">
                            <h3>Select an event</h3>
                            <p>Choose an event from the left to view attendees and start a conversation.</p>
                        </div>
                    <?php elseif (empty($attendees)): ?>
                        <div class="empty-state-card">
                            <h3>No attendees yet</h3>
                            <p>There are no other attendees for this event yet. Once someone books, they will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="message-thread-header">
                            <div>
                                <h3><?php echo sanitize($selectedEvent['title']); ?></h3>
                                <p><?php echo sanitize($selectedEvent['venue']); ?></p>
                            </div>
                            <?php if ($activeRecipient): ?>
                                <span class="thread-recipient<?php echo (($attendeeUnreadCounts[$activeRecipient['id']] ?? 0) > 0) ? ' has-unread' : ''; ?>">
                                    <?php echo (($attendeeUnreadCounts[$activeRecipient['id']] ?? 0) > 0) ? 'New messages from ' : 'Chat with '; ?><?php echo sanitize($activeRecipient['name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($activeRecipient): ?>
                            <div class="message-thread">
                                <?php if (!empty($thread)): ?>
                                    <?php foreach ($thread as $msg): ?>
                                        <div class="message-bubble <?php echo $msg['sender_id'] == $currentUserId ? 'mine' : 'theirs'; ?>">
                                            <strong><?php echo sanitize($msg['sender_id'] == $currentUserId ? 'You' : $msg['sender_name']); ?></strong>
                                            <p><?php echo nl2br(sanitize($msg['message'])); ?></p>
                                            <span><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state-card compact">
                                        <p>No messages yet. Start the conversation.</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form method="post" class="message-form">
                                <input type="hidden" name="recipient_id" value="<?php echo $activeRecipient['id']; ?>">
                                <input type="hidden" name="event_id" value="<?php echo $selectedEvent['id']; ?>">
                                <textarea name="message" rows="3" placeholder="Send a friendly message like: 'Hey, are you also attending?'"></textarea>
                                <button type="submit" class="btn btn-primary">Send Message</button>
                            </form>
                        <?php else: ?>
                            <div class="empty-state-card compact">
                                <p>Select an attendee from the list to open a private conversation.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php include 'site_footer.php'; ?>
    <script src="js/main.js?v=7"></script>
</body>
</html>

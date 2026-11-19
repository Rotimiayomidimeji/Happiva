<?php
require_once 'config.php';

$pageTitle = 'My Tickets - Happiva';

if (!isLoggedIn()) {
    redirect('login.php');
}

require_once 'payment_api.php';

$pdo = getDB();
$cancelMessage = '';
$cancelError = '';
$confirmDeleteId = 0;
$confirmDeleteTitle = '';
$confirmCancelId = 0;
$confirmCancelTitle = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket']) && !empty($_POST['booking_id'])) {
    $confirmDeleteId = (int)$_POST['booking_id'];
    try {
        $stmt = $pdo->prepare("SELECT b.id, b.checked_in, b.status, e.date, e.time, e.title FROM bookings b JOIN events e ON b.event_id = e.id WHERE b.id = ? AND b.user_id = ?");
        $stmt->execute([$confirmDeleteId, $_SESSION['user_id']]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }

        $eventTimestamp = strtotime($ticket['date'] . ' ' . $ticket['time']);
        if (time() < $eventTimestamp) {
            throw new Exception('Tickets can only be deleted after the event has passed.');
        }

        $pdo->prepare("DELETE FROM bookings WHERE id = ? AND user_id = ?")->execute([$confirmDeleteId, $_SESSION['user_id']]);
        $cancelMessage = 'Ticket deleted successfully.';
        $confirmDeleteId = 0;
    } catch (Exception $e) {
        $cancelError = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking']) && !empty($_POST['booking_id'])) {
    $confirmCancelId = (int)$_POST['booking_id'];
    try {
        $stmt = $pdo->prepare("SELECT b.id, b.status, b.checked_in, b.total_price, b.user_id, b.event_id, b.quantity, e.title, e.date, e.time, e.organizer_id FROM bookings b JOIN events e ON b.event_id = e.id WHERE b.id = ? AND b.user_id = ?");
        $stmt->execute([$confirmCancelId, $_SESSION['user_id']]);
        $ticket = $stmt->fetch();

        if (!$ticket || $ticket['status'] === 'cancelled') {
            throw new Exception('Ticket not found or already cancelled.');
        }
        if (!empty($ticket['checked_in'])) {
            throw new Exception('Checked-in tickets cannot be cancelled.');
        }

        $eventTimestamp = strtotime($ticket['date'] . ' ' . $ticket['time']);
        $refundCutoff = $eventTimestamp - (48 * 60 * 60);
        $refundFee = time() <= $refundCutoff ? round($ticket['total_price'] * (REFUND_SERVICE_FEE_PERCENTAGE / 100), 2) : 0.00;
        $refundAmount = round($ticket['total_price'] - $refundFee, 2);

        $pdo->prepare("UPDATE bookings SET status = 'cancelled', refund_amount = ?, refund_fee = ? WHERE id = ? AND user_id = ?")
            ->execute([$refundAmount, $refundFee, $confirmCancelId, $_SESSION['user_id']]);

        $cancelMessage = 'Ticket cancelled successfully.';
        if ($refundAmount > 0) {
            $cancelMessage .= ' Refund amount: ' . formatPrice($refundAmount) . '.';
        } else {
            $cancelMessage .= ' No refund is available within 48 hours of the event.';
        }
        $confirmCancelId = 0;
    } catch (Exception $e) {
        $cancelError = $e->getMessage();
    }
}

if (isset($_GET['confirm_delete'])) {
    $confirmDeleteId = (int)$_GET['confirm_delete'];
    $stmt = $pdo->prepare("SELECT b.id, e.title FROM bookings b JOIN events e ON b.event_id = e.id WHERE b.id = ? AND b.user_id = ?");
    $stmt->execute([$confirmDeleteId, $_SESSION['user_id']]);
    $confirmedTicket = $stmt->fetch();
    $confirmDeleteTitle = $confirmedTicket['title'] ?? '';
}

if (isset($_GET['confirm_cancel'])) {
    $confirmCancelId = (int)$_GET['confirm_cancel'];
    $stmt = $pdo->prepare("SELECT b.id, e.title FROM bookings b JOIN events e ON b.event_id = e.id WHERE b.id = ? AND b.user_id = ?");
    $stmt->execute([$confirmCancelId, $_SESSION['user_id']]);
    $confirmedTicket = $stmt->fetch();
    $confirmCancelTitle = $confirmedTicket['title'] ?? '';
}

try {
    $stmt = $pdo->prepare("
        SELECT b.*, e.title, e.date, e.time, e.venue, e.address, e.image, e.category, e.ticket_primary_color, e.ticket_secondary_color, u.name AS booked_by_name
        FROM bookings b
        JOIN events e ON b.event_id = e.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.user_id = ?
        ORDER BY e.date ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    $cancelError = 'Unable to load tickets at this time.';
    $bookings = [];
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
    <link rel="stylesheet" href="css/style.css?v=11">
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
                <li><a href="messages.php">Messages</a></li>
                <li><a href="tickets.php" class="active">My Tickets</a></li>
                <?php if (isOrganizer()): ?>
                <li><a href="dashboard.php">Dashboard</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-actions">
                <span class="user-name"><?php echo sanitize($_SESSION['user_name']); ?></span>
                <a href="#" class="btn btn-secondary btn-sm" onclick="event.preventDefault(); openModal('logoutModal');">Logout</a>
                <button class="mobile-menu-btn" type="button" aria-label="Toggle navigation" aria-expanded="false">☰</button>
            </div>
        </div>
    </nav>

    <!-- Tickets Header -->
    <section class="tickets-header">
        <div class="container">
            <h1>My Tickets</h1>
            <p style="color: var(--text-secondary);">View and manage your event tickets</p>
        </div>
    </section>

    <!-- Tickets Grid -->
    <section class="section" style="padding-top: 0;">
        <div class="container">
            <?php if (!empty($cancelMessage)): ?>
                <div class="form-success" style="margin-bottom: 24px;"><?php echo sanitize($cancelMessage); ?></div>
            <?php endif; ?>
            <?php if (!empty($cancelError)): ?>
                <div class="form-error" style="margin-bottom: 24px;"><?php echo sanitize($cancelError); ?></div>
            <?php endif; ?>
            <?php if (!empty($bookings)): ?>
            <div class="tickets-grid">
                <?php foreach ($bookings as $booking): ?>
                    <?php
                        $eventTimestamp = strtotime($booking['date'] . ' ' . $booking['time']);
                        $canCancel = $booking['status'] !== 'cancelled' && !$booking['checked_in'] && time() < $eventTimestamp;
                        $statusLabel = $booking['status'] === 'cancelled' ? 'Cancelled' : ($booking['checked_in'] ? 'Checked In' : 'Confirmed');
                        $statusColor = $booking['status'] === 'cancelled' ? 'var(--error)' : ($booking['checked_in'] ? 'var(--success)' : 'rgba(255,255,255,0.2)');
                    ?>
                    <div class="ticket-card" data-ticket-id="<?php echo sanitize($booking['qr_code']); ?>" style="--ticket-primary: <?php echo sanitize($booking['ticket_primary_color'] ?? '#4f46e5'); ?>; --ticket-secondary: <?php echo sanitize($booking['ticket_secondary_color'] ?? '#ec4899'); ?>;">
                    <div class="ticket-cover" style="background-image: url('<?php echo sanitize($booking['image'] ?: 'data:image/svg+xml;charset=UTF-8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 400"><rect width="1200" height="400" fill="#4f46e5"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="DM Sans, sans-serif" font-size="48" fill="#ffffff">EventSmart Event</text></svg>')); ?>');"></div>
                    <div class="ticket-header">
                        <div>
                            <span style="font-size: 12px; opacity: 0.8;"><?php echo sanitize($booking['category']); ?></span>
                        </div>
                        <span class="ticket-status" style="background: <?php echo $statusColor; ?>;">
                            <?php echo $statusLabel; ?>
                        </span>
                    </div>
                    <div class="ticket-body">
                        <h3 class="ticket-event"><?php echo sanitize($booking['title']); ?></h3>
                        <div class="ticket-details">
                            <div class="ticket-detail">
                                <label>Date</label>
                                <span><?php echo formatDate($booking['date']); ?></span>
                            </div>
                            <div class="ticket-detail">
                                <label>Time</label>
                                <span><?php echo formatTime($booking['time']); ?></span>
                            </div>
                            <div class="ticket-detail">
                                <label>Venue</label>
                                <span><?php echo sanitize($booking['venue']); ?></span>
                            </div>
                            <div class="ticket-detail">
                                <label>Tickets</label>
                                <span><?php echo $booking['quantity']; ?></span>
                            </div>
                            <div class="ticket-detail">
                                <label>Address</label>
                                <span><?php echo sanitize($booking['address']); ?></span>
                            </div>
                            <div class="ticket-detail">
                                <label>Booked By</label>
                                <span><?php echo sanitize($booking['booked_by_name'] ?? $_SESSION['user_name'] ?? 'You'); ?></span>
                            </div>
                            <div class="ticket-detail">
                                <label>Total</label>
                                <span><?php echo formatPrice($booking['total_price']); ?></span>
                            </div>
                        </div>
                        
                        <div class="ticket-qr" data-qr="<?php echo sanitize($booking['qr_code']); ?>"></div>
                        
                        <p class="ticket-id">Ticket ID: <?php echo sanitize($booking['qr_code']); ?></p>
                        <?php if ($booking['status'] === 'cancelled'): ?>
                            <p style="margin-top: 16px; color: var(--error); font-weight: 600;">This ticket has been cancelled and is no longer valid.</p>
                            <?php if (!empty($booking['refund_amount'])): ?>
                                <p style="margin-top: 6px; color: var(--text-secondary);">Refund issued: <?php echo formatPrice($booking['refund_amount']); ?> (service fee <?php echo formatPrice($booking['refund_fee'] ?? 0); ?>)</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="ticket-actions" style="display: flex; flex-wrap: wrap; gap: 12px;">
                        <button class="btn btn-secondary btn-sm" onclick="printTicket('<?php echo $booking['qr_code']; ?>')" style="flex: 1;" <?php echo $booking['status'] === 'cancelled' ? 'disabled' : ''; ?>>
                            Print
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="downloadTicket('<?php echo $booking['qr_code']; ?>')" style="flex: 1;" <?php echo $booking['status'] === 'cancelled' ? 'disabled' : ''; ?>>
                            Download
                        </button>
                        <?php if ($canCancel): ?>
                            <a href="tickets.php?confirm_cancel=<?php echo (int)$booking['id']; ?>" class="btn btn-secondary btn-sm" style="flex: 1 1 100%; margin-top: 12px; background: #dc2626; border-color: #dc2626; color: #fff; text-align: center; text-decoration: none;">Cancel Ticket</a>
                        <?php elseif ($booking['status'] !== 'cancelled' && !$booking['checked_in']): ?>
                            <div style="flex: 1 1 100%; color: var(--text-secondary); font-size: 13px; margin-top: 12px;">
                                Refunds are available until 48 hours before the event. After that, cancellation is still allowed but no refund will be issued.
                            </div>
                        <?php endif; ?>
                        <?php if (time() >= $eventTimestamp): ?>
                            <a href="tickets.php?confirm_delete=<?php echo (int)$booking['id']; ?>" class="btn btn-secondary btn-sm" style="flex: 1 1 100%; margin-top: 12px; background: #7f1d1d; border-color: #7f1d1d; color: #fff; text-align: center; text-decoration: none;">Delete Ticket</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <h3>No Tickets Yet</h3>
                <p>You haven't booked any events. Browse our events and book your first ticket!</p>
                <a href="events.php" class="btn btn-primary">Browse Events</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'site_footer.php'; ?>

    <!-- Delete Ticket Confirmation Modal -->
    <div class="modal-overlay<?php echo $confirmDeleteId > 0 ? ' active' : ''; ?>" id="deleteTicketModal"<?php echo $confirmDeleteId > 0 ? ' style="display: flex;"' : ''; ?> >
        <div class="modal" style="max-width: 520px;">
            <div class="modal-header">
                <h3>Delete Ticket?</h3>
                <button class="modal-close" onclick="closeModal('deleteTicketModal')">&times;</button>
            </div>
            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                <?php if ($confirmDeleteTitle !== ''): ?>
                    Are you sure you want to permanently delete your ticket for "<?php echo sanitize($confirmDeleteTitle); ?>"?
                <?php else: ?>
                    This ticket is not available for deletion or could not be found.
                <?php endif; ?>
            </p>
            <?php if ($confirmDeleteTitle !== ''): ?>
            <form method="POST" action="tickets.php">
                <input type="hidden" name="booking_id" value="<?php echo $confirmDeleteId; ?>">
                <input type="hidden" name="delete_ticket" value="1">
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteTicketModal')">Keep Ticket</button>
                    <button type="submit" class="btn btn-danger">Delete Ticket</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancel Ticket Confirmation Modal -->
    <div class="modal-overlay<?php echo $confirmCancelId > 0 ? ' active' : ''; ?>" id="cancelTicketModal"<?php echo $confirmCancelId > 0 ? ' style="display: flex;"' : ''; ?> >
        <div class="modal" style="max-width: 520px;">
            <div class="modal-header">
                <h3>Cancel Ticket?</h3>
                <button class="modal-close" onclick="closeModal('cancelTicketModal')">&times;</button>
            </div>
            <p id="cancelTicketMessage" style="color: var(--text-secondary); margin-bottom: 16px;">
                <?php if ($confirmCancelId > 0 && $confirmCancelTitle !== ''): ?>
                    Are you sure you want to cancel the ticket for "<?php echo sanitize($confirmCancelTitle); ?>"?
                <?php else: ?>
                    Are you sure you want to cancel this ticket?
                <?php endif; ?>
            </p>
            <form method="POST" action="tickets.php">
                <input type="hidden" name="booking_id" value="<?php echo $confirmCancelId > 0 ? $confirmCancelId : ''; ?>">
                <input type="hidden" name="cancel_booking" value="1">
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('cancelTicketModal')">Keep Ticket</button>
                    <button type="submit" class="btn btn-primary">Confirm Cancel</button>
                </div>
            </form>
        </div>
    </div>

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

    <!-- AI Chat Widget -->
    <div class="chat-widget">
        <button class="chat-toggle">💬</button>
        <div class="chat-window">
            <div class="chat-header">
                <div class="chat-avatar">🤖</div>
                <div class="chat-title">
                    <h4>EventSmart Assistant</h4>
                    <p>AI Powered Help</p>
                </div>
            </div>
            <div class="chat-messages"></div>
            <div class="chat-input-area">
                <input type="text" placeholder="Ask me anything...">
                <button class="chat-send">➤</button>
            </div>
        </div>
    </div>

    <script src="js/main.js?v=7"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
        function printTicket(ticketId) {
            const el = document.querySelector('.ticket-card[data-ticket-id="' + ticketId + '"]');
            if (!el) return alert('Ticket not found');
            const win = window.open('', '_blank', 'width=800,height=1000');
            const styles = '<link rel="stylesheet" href="css/style.css?v=9">';
            win.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Ticket '+ticketId+'</title>' + styles + '</head><body>' + el.outerHTML + '<script>window.onload=function(){window.print();};</' + 'script></body></html>');
            win.document.close();
        }

        function downloadTicket(ticketId) {
            const el = document.querySelector('.ticket-card[data-ticket-id="' + ticketId + '"]');
            if (!el) return alert('Ticket not found');
            const btn = document.querySelector('.ticket-card[data-ticket-id="' + ticketId + '"] .ticket-actions .btn-primary');
            if (btn) { btn.disabled = true; btn.textContent = 'Generating...'; }
            html2canvas(el, {scale: 2, useCORS: true}).then(function(canvas) {
                const imgData = canvas.toDataURL('image/png', 1.0);
                const jsPDFConstructor = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : (window.jsPDF ? window.jsPDF : null);
                if (!jsPDFConstructor) {
                    if (btn) { btn.disabled = false; btn.textContent = 'Download'; }
                    return alert('PDF library not loaded.');
                }
                const pdf = new jsPDFConstructor({ orientation: 'portrait', unit: 'pt', format: 'a4' });
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                const margin = 20;
                const imgProps = { width: canvas.width, height: canvas.height };
                const ratio = Math.min((pageWidth - margin * 2) / imgProps.width, (pageHeight - margin * 2) / imgProps.height);
                const pdfWidth = imgProps.width * ratio;
                const pdfHeight = imgProps.height * ratio;
                const x = (pageWidth - pdfWidth) / 2;
                const y = (pageHeight - pdfHeight) / 2;
                pdf.addImage(imgData, 'PNG', x, y, pdfWidth, pdfHeight);
                pdf.save(ticketId + '.pdf');
                if (btn) { btn.disabled = false; btn.textContent = 'Download'; }
            }).catch(function(err){ if (btn) { btn.disabled = false; btn.textContent = 'Download'; } console.error(err); alert('Failed to generate PDF.'); });
        }

        function renderTicketQrCodes() {
            document.querySelectorAll('.ticket-qr[data-qr]').forEach(function(el) {
                const value = el.getAttribute('data-qr');
                if (!value) return;
                if (el.querySelector('canvas')) return;
                const qr = new QRious({
                    element: document.createElement('canvas'),
                    value: value,
                    size: 180,
                    background: '#ffffff',
                    foreground: '#111111'
                });
                el.appendChild(qr.canvas);
            });
        }

        document.addEventListener('DOMContentLoaded', renderTicketQrCodes);
    </script>
</body>
</html>

<?php
/**
 * Happiva - Event Booking Platform
 * Success Page
 */

require_once 'config.php';

$pageTitle = 'Booking Confirmed - EventSmart';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('index.php');
}

// Get booking ID
$bookingId = isset($_GET['booking']) ? (int)$_GET['booking'] : 0;

if (!$bookingId) {
    redirect('index.php');
}

// Get booking details
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT b.*, e.title, e.date, e.time, e.venue, e.address, e.image, e.category, e.ticket_primary_color, e.ticket_secondary_color, u.name AS booked_by_name
        FROM bookings b
        JOIN events e ON b.event_id = e.id
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$bookingId, $_SESSION['user_id']]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        redirect('index.php');
    }
} catch (PDOException $e) {
    redirect('index.php');
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
            </ul>
            <div class="nav-actions">
                <span class="user-name"><?php echo sanitize($_SESSION['user_name']); ?></span>
                <a href="#" class="btn btn-secondary btn-sm" onclick="event.preventDefault(); openModal('logoutModal');">Logout</a>
                <button class="mobile-menu-btn" type="button" aria-label="Toggle navigation" aria-expanded="false">☰</button>
            </div>
        </div>
    </nav>

    <!-- Success -->
    <div class="success-container">
        <div class="success-icon">✓</div>
        <h1>Booking Confirmed!</h1>
        <p>Your tickets have been booked successfully. You can find your QR code ticket below or in My Tickets.</p>
        
        <div class="ticket-preview" style="--ticket-primary: <?php echo sanitize($booking['ticket_primary_color'] ?? '#4f46e5'); ?>; --ticket-secondary: <?php echo sanitize($booking['ticket_secondary_color'] ?? '#ec4899'); ?>;">
            <div class="ticket-cover" style="background-image: url('<?php echo sanitize($booking['image'] ?: 'data:image/svg+xml;charset=UTF-8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 400"><rect width="1200" height="400" fill="#4f46e5"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="DM Sans, sans-serif" font-size="48" fill="#ffffff">EventSmart Event</text></svg>')); ?>');"></div>
            <div class="ticket-header" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); padding: 24px; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
                <div style="text-align: center; color: white;">
                    <h3 style="font-size: 24px; margin-bottom: 8px;"><?php echo sanitize($booking['title']); ?></h3>
                    <p><?php echo formatDate($booking['date']); ?> at <?php echo formatTime($booking['time']); ?></p>
                </div>
            </div>
            <div class="ticket-body" style="padding: 32px; background: var(--bg-surface); border-radius: 0 0 var(--radius-lg) var(--radius-lg);">
                <div class="ticket-details" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div class="ticket-detail">
                        <label>Venue</label>
                        <span><?php echo sanitize($booking['venue']); ?></span>
                    </div>
                    <div class="ticket-detail">
                        <label>Tickets</label>
                        <span><?php echo $booking['quantity']; ?> ticket(s)</span>
                    </div>
                    <div class="ticket-detail">
                        <label>Total Paid</label>
                        <span><?php echo formatPrice($booking['total_price']); ?></span>
                    </div>
                    <div class="ticket-detail">
                        <label>Status</label>
                        <span style="color: var(--success);">Confirmed</span>
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
                        <label>Event Category</label>
                        <span><?php echo sanitize($booking['category']); ?></span>
                    </div>
                </div>
                
                <div class="ticket-qr" data-qr="<?php echo sanitize($booking['qr_code']); ?>" style="text-align: center; padding: 24px; background: white; border-radius: var(--radius-sm); margin-bottom: 16px;"></div>
                
                <p style="text-align: center; font-size: 14px; color: var(--text-muted); font-family: monospace;">
                    Ticket ID: <?php echo sanitize($booking['qr_code']); ?>
                </p>
            </div>
        </div>
        
        <div class="flex gap-2" style="justify-content: center; margin-top: 16px;">
            <button class="btn btn-secondary" onclick="printSuccessTicket('<?php echo sanitize($booking['qr_code']); ?>')">Print</button>
            <button class="btn btn-primary" onclick="downloadSuccessTicket('<?php echo sanitize($booking['qr_code']); ?>')">Download</button>
            <a href="tickets.php" class="btn btn-primary">View My Tickets</a>
        </div>
    </div>

    <?php include 'site_footer.php'; ?>

    <!-- Logout Confirmation Modal -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal" style="max-width: 420px;">
            <div class="modal-header">
                <h3>Log out?</h3>
                <button class="modal-close" onclick="closeAllModals()">&times;</button>
            </div>
            <p style="color: var(--text-secondary); margin-bottom: 8px;">Are you sure you want to end your session?</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeAllModals()">Cancel</button>
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

    <script>
        window.currentUserName = <?php echo json_encode($_SESSION['user_name'] ?? null); ?>;
    </script>
    <script src="js/main.js?v=7"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
        function printSuccessTicket(ticketId) {
            const el = document.querySelector('.ticket-preview');
            if (!el) return alert('Ticket not found');
            const win = window.open('', '_blank', 'width=800,height=1000');
            const styles = '<link rel="stylesheet" href="css/style.css?v=9">';
            win.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Ticket '+ticketId+'</title>' + styles + '</head><body>' + el.outerHTML + '<script>window.onload=function(){window.print();};</' + 'script></body></html>');
            win.document.close();
        }

        function downloadSuccessTicket(ticketId) {
            const el = document.querySelector('.ticket-preview');
            if (!el) return alert('Ticket not found');
            const btn = document.querySelector('.flex.gap-2 .btn-primary');
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

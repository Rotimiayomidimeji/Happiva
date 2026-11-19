<?php
/**
 * Happiva - Event Booking Platform
 * Checkout Page with Stripe Payment
 */

require_once 'config.php';
require_once 'payment_api.php';

$pageTitle = 'Checkout - EventSmart';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('index.php');
}

// Get checkout item from session
$checkoutItem = isset($_SESSION['checkout_item']) ? $_SESSION['checkout_item'] : null;

if (!$checkoutItem) {
    redirect('events.php');
}

// Get event details
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$checkoutItem['eventId']]);
    $event = $stmt->fetch();
    
    if (!$event) {
        redirect('events.php');
    }
} catch (PDOException $e) {
    redirect('events.php');
}

$quantity = $checkoutItem['quantity'];
$total = $event['price'] * $quantity;

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_reference'])) {
    $quantity = (int)$_POST['quantity'];
    $total = $event['price'] * $quantity;
    $reference = trim($_POST['payment_reference']);

    $paymentResult = verifyPaystackPayment($reference);
    if (!$paymentResult['success']) {
        $_SESSION['checkout_error'] = $paymentResult['error'] ?? 'Payment failed';
        redirect('checkout.php');
    }

    $bookingId = finalizeBookingWithPayment($pdo, $_SESSION['user_id'], $event, $quantity, $total, $reference, $paymentResult);

    unset($_SESSION['checkout_item']);
    redirect('success.php?booking=' . $bookingId);
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
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <link rel="stylesheet" href="css/style.css?v=11">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90' font-family='sans-serif' font-weight='bold' fill='%236366F1'>H</text></svg>">
    <style>
        .payment-form { max-width: 500px; margin: 0 auto; }
        #card-element { padding: 16px; background: var(--bg-dark); border: 1px solid var(--border); border-radius: var(--radius-sm); }
        .payment-error { color: var(--error); font-size: 14px; margin-top: 12px; display: none; }
        .spinner-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .spinner-overlay.active { display: flex; }
    </style>
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

    <!-- Checkout -->
    <div class="checkout-container">
        <div class="checkout-header">
            <h1>Complete Your Booking</h1>
            <p>You're about to book tickets for <?php echo sanitize($event['title']); ?></p>
        </div>
        
        <form method="POST" class="checkout-form payment-form" id="payment-form">
            <div class="order-summary">
                <h3>Order Summary</h3>
                <div class="order-item">
                    <span>Event</span>
                    <span><?php echo sanitize($event['title']); ?></span>
                </div>
                <div class="order-item">
                    <span>Date</span>
                    <span><?php echo formatDate($event['date']); ?></span>
                </div>
                <div class="order-item">
                    <span>Time</span>
                    <span><?php echo formatTime($event['time']); ?></span>
                </div>
                <div class="order-item">
                    <span>Venue</span>
                    <span><?php echo sanitize($event['venue']); ?></span>
                </div>
                <div class="order-item">
                    <span>Quantity</span>
                    <span><?php echo $quantity; ?></span>
                </div>
                <div class="order-item" style="font-weight: 600; font-size: 18px;">
                    <span>Total</span>
                    <span style="color: var(--primary);"><?php echo formatPrice($total); ?></span>
                </div>
            </div>
            
            <h3 style="margin: 32px 0 24px;">Payment Details</h3>
            
            <div class="form-group">
                <label>Card Information</label>
                <div class="card-element-mock" style="padding: 16px; background: var(--bg-dark); border: 1px solid var(--border); border-radius: var(--radius-sm); display: flex; flex-direction: column; gap: 12px;">
                    <input type="text" id="mock-cc-num" placeholder="Card Number (0000 0000 0000 0000)" required style="width: 100%; padding: 12px; background: var(--bg-surface); border: 1px solid var(--border); border-radius: 4px; color: white;">
                    <div style="display: flex; gap: 12px;">
                        <input type="text" id="mock-cc-exp" placeholder="MM/YY" required style="width: 50%; padding: 12px; background: var(--bg-surface); border: 1px solid var(--border); border-radius: 4px; color: white;">
                        <input type="text" id="mock-cc-cvc" placeholder="CVC" required style="width: 50%; padding: 12px; background: var(--bg-surface); border: 1px solid var(--border); border-radius: 4px; color: white;">
                    </div>
                </div>
            </div>
            
            <div id="card-errors" class="payment-error"></div>
            
            <input type="hidden" name="quantity" value="<?php echo $quantity; ?>">
            <input type="hidden" name="payment_reference" id="payment_reference" value="">
            
            <button type="submit" id="submit-button" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 24px;">
                Pay <?php echo formatPrice($total); ?>
            </button>
            
            <p style="text-align: center; font-size: 13px; color: var(--text-muted); margin-top: 16px;">
                🔒 Secured by Paystack
            </p>
        </form>
    </div>

    <?php include 'site_footer.php'; ?>

    <!-- Loading Overlay -->
    <div class="spinner-overlay" id="spinner-overlay">
        <div class="loading"><div class="spinner"></div></div>
    </div>

    <script src="js/main.js?v=7"></script>
    <script>
        const form = document.getElementById('payment-form');
        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitButton = document.getElementById('submit-button');
            const spinner = document.getElementById('spinner-overlay');
            const errorEl = document.getElementById('card-errors');
            
            submitButton.disabled = true;
            spinner.classList.add('active');
            errorEl.textContent = '';
            errorEl.style.display = 'none';
            
            try {
                const response = await fetch('payment_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=create_payment&amount=<?php echo $total; ?>&email=<?php echo urlencode($_SESSION['user_email'] ?? ''); ?>&event_id=<?php echo $event['id']; ?>&quantity=<?php echo $quantity; ?>'
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Payment initialization failed');
                }

                document.getElementById('payment_reference').value = data.reference || 'demo_' + Date.now();

                if (data.demo_mode || !data.authorization_url) {
                    form.submit();
                    return;
                }

                const handler = PaystackPop.setup({
                    key: data.public_key || '<?php echo PAYSTACK_PUBLIC_KEY; ?>',
                    email: '<?php echo addslashes($_SESSION['user_email'] ?? ''); ?>',
                    amount: data.amount || Math.round(<?php echo $total; ?> * 100),
                    ref: data.reference,
                    currency: 'NGN',
                    label: '<?php echo addslashes($_SESSION['user_name']); ?>',
                    callback: function(response) {
                        document.getElementById('payment_reference').value = response.reference || data.reference;
                        form.submit();
                    },
                    onClose: function() {
                        submitButton.disabled = false;
                        spinner.classList.remove('active');
                    }
                });

                handler.openIframe();
            } catch (error) {
                errorEl.textContent = error.message;
                errorEl.style.display = 'block';
                submitButton.disabled = false;
                spinner.classList.remove('active');
            }
        });
    </script>
</body>
</html>

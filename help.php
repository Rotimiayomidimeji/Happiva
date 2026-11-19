<?php
require_once 'config.php';
require_once 'auth.php';

$pageTitle = 'Help Center - Happiva';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($pageTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=11">
    <link rel="icon" href="uploads/eventsmart-logo.svg">
    <style>
        .support-page { min-height: 100vh; padding: 120px 0 64px; }
        .support-heading { max-width: 720px; margin-bottom: 32px; }
        .support-heading h1 { font-size: 40px; margin-bottom: 10px; }
        .support-heading p { color: var(--text-secondary); }
        .help-list { max-width: 860px; border-top: 1px solid var(--border); }
        .help-list details { padding: 20px 4px; border-bottom: 1px solid var(--border); }
        .help-list summary { cursor: pointer; color: var(--text-primary); font-family: 'Outfit', sans-serif; font-size: 19px; font-weight: 600; }
        .help-list details p { margin: 12px 0 0; max-width: 760px; color: var(--text-secondary); }
        .support-cta { margin-top: 32px; }
        @media (max-width: 600px) { .support-page { padding-top: 104px; } .support-heading h1 { font-size: 32px; } .help-list summary { font-size: 17px; } }
    </style>
</head>
<body>
    <nav class="navbar scrolled public-nav">
        <div class="container">
            <a href="index.php" class="logo"><img src="uploads/eventsmart-logo.svg" alt="Happiva logo" style="height:34px;width:34px;object-fit:contain;margin-right:10px;"><span>Happiva</span></a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="contact.php">Contact</a></li>
                <?php if (!isLoggedIn()): ?><li class="mobile-auth-link"><a href="login.php">Login</a></li><li class="mobile-auth-link"><a href="register.php">Get Started</a></li><?php endif; ?>
            </ul>
            <div class="nav-actions">
                <?php if (isLoggedIn()): ?>
                    <a href="profile.php" class="btn btn-ghost">My account</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-ghost">Login</a><a href="register.php" class="btn btn-primary">Get Started</a>
                <?php endif; ?>
                <button class="mobile-menu-btn" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
            </div>
        </div>
    </nav>
    <main class="support-page">
        <div class="container">
            <header class="support-heading">
                <span class="account-kicker">Happiva Support</span>
                <h1>Help with your events and tickets</h1>
                <p>Find quick answers about booking, payments, tickets, and organizer accounts.</p>
            </header>
            <section class="help-list" aria-label="Frequently asked questions">
                <details>
                    <summary>How do I book an event?</summary>
                    <p>Open an event, choose the number of tickets, and select Book Now. Sign in or create an account to continue to checkout.</p>
                </details>
                <details>
                    <summary>Where can I find my tickets?</summary>
                    <p>After a successful booking, your tickets are available from My Tickets. Each ticket has a QR code for venue check-in.</p>
                </details>
                <details>
                    <summary>My payment did not complete. What should I do?</summary>
                    <p>Check your bookings before retrying to avoid duplicate purchases. If the payment was charged but no booking appears, contact support with the event name, approximate time, and payment reference. Do not send card details.</p>
                </details>
                <details>
                    <summary>How do I become an organizer?</summary>
                    <p>Choose Organize events when creating your account and verify your email. Your organizer access remains pending until an administrator reviews it.</p>
                </details>
                <details>
                    <summary>How do I get help with a booking or account?</summary>
                    <p>Use the contact form and include the email on your account, event name, and a short description. Never include your password or full payment-card details.</p>
                </details>
            </section>
            <div class="support-cta"><a class="btn btn-primary" href="contact.php">Contact Happiva Support</a></div>
        </div>
    </main>
    <?php include 'site_footer.php'; ?>
    <script src="js/main.js?v=7"></script>
</body>
</html>

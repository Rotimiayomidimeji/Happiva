<?php
require_once 'config.php';
require_once 'auth.php';

$pageTitle = 'Privacy Policy - Happiva';
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
    <link rel="stylesheet" href="css/style.css?v=10">
    <link rel="icon" href="uploads/eventsmart-logo.svg">
    <style>
        .support-page { min-height: 100vh; padding: 120px 0 64px; }
        .policy-content { max-width: 820px; }
        .policy-content h1 { font-size: 40px; margin: 10px 0; }
        .policy-content .policy-updated, .policy-content p, .policy-content li { color: var(--text-secondary); }
        .policy-content section { padding: 22px 0; border-top: 1px solid var(--border); }
        .policy-content h2 { font-size: 22px; margin-bottom: 10px; }
        .policy-content ul { padding-left: 22px; }
        .policy-content li { margin: 8px 0; }
        @media (max-width: 600px) { .support-page { padding-top: 104px; } .policy-content h1 { font-size: 32px; } }
    </style>
</head>
<body>
    <nav class="navbar scrolled public-nav">
        <div class="container">
            <a href="index.php" class="logo"><img src="uploads/eventsmart-logo.svg" alt="Happiva logo" style="height:34px;width:34px;object-fit:contain;margin-right:10px;"><span>Happiva</span></a>
            <ul class="nav-links"><li><a href="index.php">Home</a></li><li><a href="events.php">Events</a></li><li><a href="contact.php">Contact</a></li></ul>
            <div class="nav-actions"><button class="mobile-menu-btn" type="button" aria-label="Open navigation" aria-expanded="false">☰</button></div>
        </div>
    </nav>
    <main class="support-page"><article class="container policy-content">
        <span class="account-kicker">Happiva</span>
        <h1>Privacy Policy</h1>
        <p class="policy-updated">Last updated: <?php echo date('F j, Y'); ?></p>
        <p>This policy describes the information Happiva uses to provide event discovery, ticket booking, organizer tools, and customer support.</p>
        <section><h2>Information we collect</h2><ul><li>Account details such as your name, email address, and password hash.</li><li>Event, booking, ticket, check-in, and organizer approval records needed to run the service.</li><li>Messages and support requests you submit, including the contact details and message content.</li><li>Basic technical/session data required to keep you signed in and protect the service.</li></ul></section>
        <section><h2>Payments</h2><p>Payments are handled by Paystack. Happiva receives transaction references and payment status needed to confirm bookings; do not send card details through support forms.</p></section>
        <section><h2>How information is used</h2><p>We use information to operate accounts, process bookings, provide tickets and check-in, review organizer applications, respond to support requests, and protect the platform from abuse.</p></section>
        <section><h2>Sharing and retention</h2><p>Relevant booking details may be shown to the event organizer for attendee management. Payment processing is provided by Paystack. We keep account, booking, and support records for as long as needed to provide the service and meet operational or legal obligations.</p></section>
        <section><h2>Your choices and contact</h2><p>For questions, corrections, or account privacy requests, <a href="contact.php">contact Happiva</a>. Never include your password or full payment-card details.</p></section>
    </article></main>
    <?php include 'site_footer.php'; ?>
    <script src="js/main.js?v=7"></script>
</body>
</html>

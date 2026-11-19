<?php
require_once 'config.php';
require_once 'auth.php';

$pageTitle = 'Contact Us - Happiva';
$errors = [];
$formValues = [
    'name' => isLoggedIn() ? (string)($_SESSION['user_name'] ?? '') : '',
    'email' => isLoggedIn() ? (string)($_SESSION['user_email'] ?? '') : '',
    'category' => 'other',
    'subject' => '',
    'message' => ''
];
$notice = '';

if (empty($_SESSION['contact_csrf_token'])) {
    $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
}

if (!empty($_SESSION['contact_notice'])) {
    $notice = (string)$_SESSION['contact_notice'];
    unset($_SESSION['contact_notice']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formValues as $field => $value) {
        $formValues[$field] = trim((string)($_POST[$field] ?? $value));
    }

    if (!hash_equals((string)$_SESSION['contact_csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your form session expired. Refresh the page and try again.';
    } elseif (!empty($_POST['website'])) {
        $_SESSION['contact_notice'] = 'Thanks. Your message has been received.';
        redirect('contact.php?sent=1');
    } elseif (!empty($_SESSION['contact_last_submitted']) && time() - (int)$_SESSION['contact_last_submitted'] < 30) {
        $errors[] = 'Please wait a short time before sending another message.';
    } else {
        $allowedCategories = ['booking', 'payment', 'account', 'organizer', 'other'];
        if ($formValues['name'] === '' || strlen($formValues['name']) > 100) {
            $errors[] = 'Enter your name (up to 100 characters).';
        }
        if (!filter_var($formValues['email'], FILTER_VALIDATE_EMAIL) || strlen($formValues['email']) > 100) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!in_array($formValues['category'], $allowedCategories, true)) {
            $errors[] = 'Choose a valid topic.';
        }
        if (strlen($formValues['subject']) < 3 || strlen($formValues['subject']) > 150) {
            $errors[] = 'Subject must be between 3 and 150 characters.';
        }
        if (strlen($formValues['message']) < 10 || strlen($formValues['message']) > 5000) {
            $errors[] = 'Message must be between 10 and 5,000 characters.';
        }

        if (empty($errors)) {
            try {
                $pdo = getDB();
                $stmt = $pdo->prepare("INSERT INTO contact_submissions (user_id, name, email, category, subject, message) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    isLoggedIn() ? (int)$_SESSION['user_id'] : null,
                    $formValues['name'],
                    strtolower($formValues['email']),
                    $formValues['category'],
                    $formValues['subject'],
                    $formValues['message']
                ]);
                $_SESSION['contact_last_submitted'] = time();
                $_SESSION['contact_notice'] = 'Thanks. Your message has been sent to the Happiva support inbox.';
                $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
                redirect('contact.php?sent=1');
            } catch (PDOException $e) {
                error_log('Unable to save contact submission: ' . $e->getMessage());
                $errors[] = 'We could not save your message right now. Please try again later.';
            }
        }
    }
}
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
        .support-page { min-height: 100vh; padding: 120px 0 64px; background: radial-gradient(ellipse at 12% 18%, rgba(99,102,241,.16), transparent 34%), var(--bg-dark); }
        .contact-layout { max-width: 1040px; margin: 0 auto; display: grid; grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr); gap: 48px; align-items: start; }
        .contact-intro h1 { font-size: 40px; margin: 10px 0; }
        .contact-intro p { color: var(--text-secondary); max-width: 380px; }
        .contact-note { margin-top: 24px; padding-left: 16px; border-left: 3px solid var(--secondary); color: var(--text-secondary); }
        .contact-form-panel { padding: clamp(22px, 4vw, 36px); border: 1px solid rgba(255,255,255,.1); border-radius: var(--radius-lg); background: linear-gradient(145deg, rgba(30,41,59,.96), rgba(15,23,42,.96)); box-shadow: var(--shadow-lg); }
        .contact-form-panel .form-group { margin-bottom: 16px; }
        .contact-form-panel .form-group label { color: var(--text-primary); }
        .contact-form-panel .form-group input, .contact-form-panel .form-group select, .contact-form-panel .form-group textarea { background: rgba(15,23,42,.72); }
        .contact-form-panel .form-group textarea { min-height: 170px; }
        .contact-message { padding: 12px 14px; margin-bottom: 18px; border-radius: var(--radius-sm); }
        .contact-message.success { border: 1px solid rgba(34,197,94,.35); background: rgba(34,197,94,.1); color: #bbf7d0; }
        .contact-message.error { border: 1px solid rgba(239,68,68,.4); background: rgba(239,68,68,.1); color: #fecaca; }
        .contact-honeypot { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }
        @media (max-width: 760px) { .contact-layout { grid-template-columns: minmax(0,1fr); gap: 24px; } .contact-intro h1 { font-size: 34px; } }
        @media (max-width: 520px) { .support-page { padding: 104px 0 44px; } .contact-form-panel { padding: 20px 16px; } }
    </style>
</head>
<body>
    <nav class="navbar scrolled public-nav">
        <div class="container">
            <a href="index.php" class="logo"><img src="uploads/eventsmart-logo.svg" alt="Happiva logo" style="height:34px;width:34px;object-fit:contain;margin-right:10px;"><span>Happiva</span></a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li><li><a href="events.php">Events</a></li><li><a href="contact.php" class="active">Contact</a></li>
                <?php if (!isLoggedIn()): ?><li class="mobile-auth-link"><a href="login.php">Login</a></li><li class="mobile-auth-link"><a href="register.php">Get Started</a></li><?php endif; ?>
            </ul>
            <div class="nav-actions">
                <?php if (isLoggedIn()): ?><a href="profile.php" class="btn btn-ghost">My account</a><?php else: ?><a href="login.php" class="btn btn-ghost">Login</a><a href="register.php" class="btn btn-primary">Get Started</a><?php endif; ?>
                <button class="mobile-menu-btn" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
            </div>
        </div>
    </nav>
    <main class="support-page">
        <div class="container contact-layout">
            <section class="contact-intro">
                <span class="account-kicker">Happiva Support</span>
                <h1>Tell us what happened.</h1>
                <p>Send a booking, payment, account, or organizer question. Your message is saved to our support inbox for an admin to review.</p>
                <div class="contact-note">Please do not include passwords or full card details. For payment issues, include the event name and payment reference if available.</div>
                <p style="margin-top:24px;"><a href="help.php">Browse the Help Center</a></p>
            </section>
            <section class="contact-form-panel" aria-labelledby="contact-heading">
                <h2 id="contact-heading" style="margin-bottom:20px;">Contact support</h2>
                <?php if ($notice !== ''): ?><div class="contact-message success" role="status"><?php echo sanitize($notice); ?></div><?php endif; ?>
                <?php if (!empty($errors)): ?><div class="contact-message error" role="alert"><?php foreach ($errors as $error): ?><div><?php echo sanitize($error); ?></div><?php endforeach; ?></div><?php endif; ?>
                <form method="POST" action="contact.php">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($_SESSION['contact_csrf_token']); ?>">
                    <label class="contact-honeypot" aria-hidden="true">Leave this field empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    <div class="form-group"><label for="contactName">Name</label><input id="contactName" name="name" autocomplete="name" maxlength="100" value="<?php echo sanitize($formValues['name']); ?>" required></div>
                    <div class="form-group"><label for="contactEmail">Email</label><input id="contactEmail" name="email" type="email" autocomplete="email" maxlength="100" value="<?php echo sanitize($formValues['email']); ?>" required></div>
                    <div class="form-group"><label for="contactCategory">Topic</label><select id="contactCategory" name="category" required>
                        <option value="booking" <?php echo $formValues['category'] === 'booking' ? 'selected' : ''; ?>>Booking or tickets</option>
                        <option value="payment" <?php echo $formValues['category'] === 'payment' ? 'selected' : ''; ?>>Payment</option>
                        <option value="account" <?php echo $formValues['category'] === 'account' ? 'selected' : ''; ?>>Account</option>
                        <option value="organizer" <?php echo $formValues['category'] === 'organizer' ? 'selected' : ''; ?>>Organizer</option>
                        <option value="other" <?php echo $formValues['category'] === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select></div>
                    <div class="form-group"><label for="contactSubject">Subject</label><input id="contactSubject" name="subject" maxlength="150" value="<?php echo sanitize($formValues['subject']); ?>" required></div>
                    <div class="form-group"><label for="contactMessage">Message</label><textarea id="contactMessage" name="message" maxlength="5000" required><?php echo sanitize($formValues['message']); ?></textarea></div>
                    <button class="btn btn-primary" type="submit">Send to support</button>
                </form>
            </section>
        </div>
    </main>
    <?php include 'site_footer.php'; ?>
    <script src="js/main.js?v=7"></script>
</body>
</html>

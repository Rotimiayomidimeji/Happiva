<?php
require_once 'config.php';
require_once 'auth.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Log In - EventSmart';
$formError = '';
$nextPath = safeAuthReturnPath($_POST['next'] ?? $_GET['next'] ?? '');
$nextQuery = $nextPath !== '' ? '?next=' . rawurlencode($nextPath) : '';
$email = strtolower(trim((string)($_POST['email'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = loginUser($email, (string)($_POST['password'] ?? ''));
    if ($result['success']) {
        $destination = in_array($result['role'], ['admin', 'organizer'], true) ? 'dashboard.php' : ($nextPath ?: 'index.php');
        redirect($destination);
    }
    $formError = $result['message'];
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
    <link rel="stylesheet" href="css/style.css?v=9">
    <link rel="icon" href="uploads/eventsmart-logo.svg">
    <style>
        .account-page { min-height: 100vh; padding: 112px 24px 64px; background: radial-gradient(ellipse at 12% 18%, rgba(99,102,241,.18), transparent 34%), var(--bg-dark); }
        .account-nav .container { max-width: 1280px; }
        .login-layout { width: min(1000px, 100%); min-height: 500px; margin: 0 auto; display: grid; grid-template-columns: 1.1fr .9fr; overflow: hidden; border: 1px solid rgba(255,255,255,.1); border-radius: var(--radius-lg); background: linear-gradient(145deg, rgba(30,41,59,.96), rgba(15,23,42,.96)); box-shadow: var(--shadow-lg); }
        .login-story { position: relative; display: flex; flex-direction: column; justify-content: flex-end; min-height: 500px; padding: clamp(30px, 6vw, 64px); overflow: hidden; background: linear-gradient(155deg, rgba(99,102,241,.65), rgba(16,185,129,.28) 55%, rgba(15,23,42,.35)); }
        .login-story::before, .login-story::after { content: ''; position: absolute; border: 1px solid rgba(255,255,255,.15); border-radius: 50%; pointer-events: none; }
        .login-story::before { width: 360px; height: 360px; right: -120px; top: -90px; }
        .login-story::after { width: 250px; height: 250px; right: -65px; top: -35px; }
        .login-story > * { position: relative; z-index: 1; }
        .login-story .account-kicker { color: #bbf7d0; }
        .login-story h1 { max-width: 440px; margin: 12px 0; font-size: clamp(36px, 4.5vw, 54px); }
        .login-story p { max-width: 390px; color: rgba(255,255,255,.82); }
        .login-form-panel { align-self: center; padding: clamp(28px, 5vw, 54px); }
        .login-form-panel h2 { font-size: 28px; margin-bottom: 6px; }
        .login-form-panel > p { color: var(--text-secondary); margin-bottom: 28px; }
        .login-form-panel .form-group { margin-bottom: 18px; }
        .login-form-panel .form-group label { color: var(--text-primary); }
        .login-form-panel .form-group input { background: rgba(15,23,42,.72); }
        .login-form-panel .btn { width: 100%; margin-top: 4px; }
        .login-error, .login-success { padding: 11px 13px; margin-bottom: 18px; border-radius: var(--radius-sm); font-size: 14px; }
        .login-error { border: 1px solid rgba(239,68,68,.4); background: rgba(239,68,68,.1); color: #fecaca; }
        .login-success { border: 1px solid rgba(34,197,94,.35); background: rgba(34,197,94,.1); color: #bbf7d0; }
        .account-switch { margin-top: 22px; color: var(--text-secondary); text-align: center; font-size: 14px; }
        .account-switch a { color: var(--primary-light); font-weight: 600; }
        @media (max-width: 760px) { .login-layout { grid-template-columns: 1fr; max-width: 520px; } .login-story { min-height: 230px; padding: 30px; } .login-story h1 { font-size: 36px; } }
        @media (max-width: 520px) { .account-page { padding: 96px 16px 36px; } .login-story { min-height: 0; padding: 22px; } .login-story h1 { font-size: 27px; } .login-story p { display: none; } .login-form-panel { padding: 24px 20px; } .account-nav .nav-actions > .btn { display: none; } }
    </style>
</head>
<body>
    <nav class="navbar account-nav">
        <div class="container">
            <a href="index.php" class="logo">
                <img src="uploads/eventsmart-logo.svg" alt="EventSmart logo" style="height:34px;width:34px;object-fit:contain;margin-right:10px;">
                <span>EventSmart</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
            <div class="nav-actions">
                <button class="mobile-menu-btn" type="button" aria-label="Toggle navigation">☰</button>
            </div>
        </div>
    </nav>

    <main class="account-page">
        <section class="login-layout">
            <div class="login-story">
                <span class="account-kicker">Welcome back</span>
                <h1>Your next event is waiting.</h1>
                <p>Pick up where you left off and get back to discovering the moments worth showing up for.</p>
            </div>
            <div class="login-form-panel">
                <h2>Log in to Happiva</h2>
                <p>Enter your account details to continue.</p>
                <?php if (isset($_GET['registered'])): ?>
                    <div class="login-success" role="status">Your account is ready. Log in to continue.</div>
                <?php endif; ?>
                <?php if ($formError !== ''): ?>
                    <div class="login-error" role="alert"><?php echo sanitize($formError); ?></div>
                <?php endif; ?>
                <form method="POST" action="login.php">
                    <input type="hidden" name="next" value="<?php echo sanitize($nextPath); ?>">
                    <div class="form-group">
                        <label for="loginEmail">Email address</label>
                        <input type="email" id="loginEmail" name="email" autocomplete="email" placeholder="you@example.com" value="<?php echo sanitize($email); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <input type="password" id="loginPassword" name="password" autocomplete="current-password" placeholder="Your password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Log in</button>
                </form>
                <p class="account-switch">New to Happiva? <a href="register.php<?php echo $nextQuery; ?>">Create an account</a></p>
            </div>
        </section>
    </main>
    <?php include 'site_footer.php'; ?>
    <script src="js/main.js?v=7"></script>
</body>
</html>
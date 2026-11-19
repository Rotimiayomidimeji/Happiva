<?php
require_once 'config.php';
require_once 'auth.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Create Account - EventSmart';
$formError = '';
$formNotice = '';
$name = trim((string)($_POST['name'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$role = isset($_POST['role']) && in_array($_POST['role'], ['user', 'organizer'], true) ? $_POST['role'] : 'user';
$nextPath = safeAuthReturnPath($_POST['next'] ?? $_GET['next'] ?? $_SESSION['auth_return_to'] ?? '');
if ($nextPath !== '') {
    $_SESSION['auth_return_to'] = $nextPath;
}
$nextQuery = $nextPath !== '' ? '?next=' . rawurlencode($nextPath) : '';
$pendingEmail = (string)($_SESSION['pending_registration_email'] ?? '');
$currentStep = $pendingEmail !== '' ? 3 : 1;

if (isset($_GET['cancel']) && $pendingEmail !== '') {
    cancelEmailVerification($pendingEmail);
    unset($_SESSION['pending_registration_email']);
    redirect('register.php');
}

if (isset($_SESSION['registration_notice'])) {
    $formNotice = (string)$_SESSION['registration_notice'];
    unset($_SESSION['registration_notice']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['registration_action'] ?? '');

    if ($action === 'start_verification') {
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $formError = 'Enter your name and a valid email address.';
            $currentStep = 1;
        } elseif (strlen($password) < 6) {
            $formError = 'Your password must be at least 6 characters.';
            $currentStep = 2;
        } elseif ($password !== $confirmPassword) {
            $formError = 'Those passwords do not match.';
            $currentStep = 2;
        } else {
            $result = beginEmailVerification($name, $email, $password, $role);
            if ($result['success']) {
                $_SESSION['pending_registration_email'] = $email;
                $_SESSION['registration_notice'] = $result['message'];
                redirect('register.php?step=verify');
            }
            if (!empty($result['cooldown'])) {
                $_SESSION['pending_registration_email'] = $email;
                $currentStep = 3;
            } else {
                $currentStep = 2;
            }
            $formError = $result['message'];
        }
    } elseif ($action === 'resend_code') {
        $pendingEmail = (string)($_SESSION['pending_registration_email'] ?? '');
        $result = resendRegistrationOtp($pendingEmail);
        $currentStep = 3;
        if ($result['success']) {
            $formNotice = $result['message'];
        } else {
            $formError = $result['message'];
        }
    } elseif ($action === 'verify_code') {
        $pendingEmail = (string)($_SESSION['pending_registration_email'] ?? '');
        $result = verifyRegistrationOtp($pendingEmail, $_POST['otp'] ?? '');
        $currentStep = 3;
        if ($result['success']) {
            $stmt = getDB()->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$result['user_id']]);
            $user = $stmt->fetch();
            if ($user) {
                establishUserSession($user);
                unset($_SESSION['pending_registration_email']);
                $destination = $user['role'] === 'organizer' ? 'dashboard.php' : (safeAuthReturnPath($_SESSION['auth_return_to'] ?? '') ?: 'index.php');
                unset($_SESSION['auth_return_to']);
                redirect($destination);
            }
            $formError = 'Your account was created, but we could not start your session. Please log in.';
        } else {
            $formError = $result['message'];
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
    <link rel="stylesheet" href="css/style.css?v=9">
    <link rel="icon" href="uploads/eventsmart-logo.svg">
    <style>
        .account-page { min-height: 100vh; padding: 112px 24px 64px; background: radial-gradient(ellipse at 12% 18%, rgba(99,102,241,.18), transparent 34%), var(--bg-dark); }
        .account-nav .container { max-width: 1280px; }
        .account-main { width: min(1050px, 100%); margin: 0 auto; display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); align-items: center; gap: clamp(32px, 6vw, 88px); }
        .account-intro { padding: 24px 0; }
        .account-kicker { color: var(--secondary); text-transform: uppercase; font-size: 12px; font-weight: 700; letter-spacing: 1.2px; }
        .account-intro h1 { font-size: clamp(34px, 4vw, 50px); max-width: 470px; margin: 12px 0; }
        .account-intro > p { max-width: 420px; color: var(--text-secondary); font-size: 16px; }
        .account-note { margin-top: 32px; padding: 18px 20px; border-left: 3px solid var(--secondary); background: rgba(16,185,129,.08); color: var(--text-secondary); }
        .account-note strong { display: block; color: var(--text-primary); margin-bottom: 4px; }
        .account-card { padding: clamp(24px, 4vw, 38px); border: 1px solid rgba(255,255,255,.1); border-radius: var(--radius-lg); background: linear-gradient(145deg, rgba(30,41,59,.96), rgba(15,23,42,.96)); box-shadow: var(--shadow-lg); }
        .account-card h2 { font-size: 25px; margin-bottom: 5px; }
        .account-subtitle { color: var(--text-secondary); margin-bottom: 24px; }
        .step-track { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 28px; }
        .step-marker { display: flex; align-items: center; gap: 10px; padding-bottom: 10px; color: var(--text-muted); border-bottom: 2px solid var(--border); font-size: 13px; font-weight: 600; }
        .step-marker span { display: grid; place-items: center; width: 25px; height: 25px; border-radius: 50%; border: 1px solid var(--border); font-size: 12px; }
        .step-marker.active { color: var(--text-primary); border-color: var(--primary-light); }
        .step-marker.active span, .step-marker.complete span { color: white; border-color: var(--primary); background: var(--primary); }
        .signup-step[hidden] { display: none; }
        .signup-step { animation: account-step-in .24s ease both; }
        @keyframes account-step-in { from { opacity: 0; transform: translateX(12px); } to { opacity: 1; transform: translateX(0); } }
        .account-card .form-group { margin-bottom: 16px; }
        .account-card .form-group label { color: var(--text-primary); }
        .account-card .form-group input, .account-card .form-group select { background: rgba(15,23,42,.72); }
        .account-card .form-group input.otp-input { font-size: 24px; font-weight: 700; letter-spacing: 8px; text-align: center; }
        .step-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 8px; }
        .step-actions .btn { min-width: 130px; }
        .account-error { padding: 11px 13px; margin-bottom: 18px; border: 1px solid rgba(239,68,68,.4); border-radius: var(--radius-sm); background: rgba(239,68,68,.1); color: #fecaca; font-size: 14px; }
        .account-notice { padding: 11px 13px; margin-bottom: 18px; border: 1px solid rgba(34,197,94,.35); border-radius: var(--radius-sm); background: rgba(34,197,94,.1); color: #bbf7d0; font-size: 14px; }
        .account-switch { margin-top: 22px; color: var(--text-secondary); text-align: center; font-size: 14px; }
        .account-switch a { color: var(--primary-light); font-weight: 600; }
        @media (max-width: 800px) { .account-main { grid-template-columns: minmax(0, 1fr); gap: 18px; max-width: 560px; } .account-intro, .account-card { min-width: 0; } .account-intro { padding: 8px 0 0; } .account-intro h1 { font-size: 36px; overflow-wrap: anywhere; } .account-note { display: none; } }
        @media (max-width: 520px) { .account-page { padding: 96px 16px 36px; } .account-main { gap: 8px; } .account-intro h1 { font-size: 27px; } .account-intro > p { display: none; } .account-card { padding: 22px 18px; } .account-nav .nav-actions > .btn { display: none; } .step-track { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; } .step-marker { min-width: 0; flex-wrap: wrap; align-content: flex-start; } .step-actions .btn { min-width: 0; } }
        @media (prefers-reduced-motion: reduce) { .signup-step { animation: none; } }
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
        <div class="account-main">
            <section class="account-intro">
                <span class="account-kicker">Your next great night starts here</span>
                <h1>Make room for something memorable.</h1>
                <p>Create your Happiva account to find events, book tickets, and keep everything together.</p>
                <div class="account-note"><strong>Signing up as an organizer?</strong>Verify your email first. Admins will then review your organizer request.</div>
            </section>

            <section class="account-card" aria-labelledby="signup-heading">
                <h2 id="signup-heading">Create your account</h2>
                <p class="account-subtitle"><?php echo $currentStep === 3 ? 'One last step: confirm you can access this email.' : 'Three quick steps to get your account ready.'; ?></p>

                <div class="step-track" aria-label="Registration progress">
                    <div class="step-marker <?php echo $currentStep === 1 ? 'active' : 'complete'; ?>" data-step-marker="1"><span>1</span> Your details</div>
                    <div class="step-marker <?php echo $currentStep === 2 ? 'active' : ($currentStep === 3 ? 'complete' : ''); ?>" data-step-marker="2"><span>2</span> Password</div>
                    <div class="step-marker <?php echo $currentStep === 3 ? 'active' : ''; ?>" data-step-marker="3"><span>3</span> Verify email</div>
                </div>

                <?php if ($formError !== ''): ?>
                    <div class="account-error" role="alert"><?php echo sanitize($formError); ?></div>
                <?php endif; ?>
                <?php if ($formNotice !== ''): ?>
                    <div class="account-notice" role="status"><?php echo sanitize($formNotice); ?></div>
                <?php endif; ?>

                <form method="POST" action="register.php" id="signupForm">
                    <input type="hidden" name="registration_action" value="start_verification">
                    <div class="signup-step" data-step-panel="1" <?php echo $currentStep === 1 ? '' : 'hidden'; ?>>
                        <div class="form-group">
                            <label for="signupRole">I want to</label>
                            <select name="role" id="signupRole" required>
                                <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>Attend events</option>
                                <option value="organizer" <?php echo $role === 'organizer' ? 'selected' : ''; ?>>Organize events</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="signupName">Full name</label>
                            <input type="text" name="name" id="signupName" autocomplete="name" placeholder="Your name" value="<?php echo sanitize($name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="signupEmail">Email address</label>
                            <input type="email" name="email" id="signupEmail" autocomplete="email" placeholder="you@example.com" value="<?php echo sanitize($email); ?>" required>
                        </div>
                        <div class="step-actions">
                            <span></span>
                            <button type="button" class="btn btn-primary" id="continueSignup">Continue <span aria-hidden="true">&rarr;</span></button>
                        </div>
                    </div>

                    <div class="signup-step" data-step-panel="2" <?php echo $currentStep === 2 ? '' : 'hidden'; ?>>
                        <div class="form-group">
                            <label for="signupPassword">Password</label>
                            <input type="password" name="password" id="signupPassword" autocomplete="new-password" placeholder="At least 6 characters" minlength="6" required>
                        </div>
                        <div class="form-group">
                            <label for="signupConfirmPassword">Confirm password</label>
                            <input type="password" name="confirm_password" id="signupConfirmPassword" autocomplete="new-password" placeholder="Enter it once more" minlength="6" required>
                        </div>
                        <div class="step-actions">
                            <button type="button" class="btn btn-secondary" id="backSignup">Back</button>
                            <button type="submit" class="btn btn-primary">Send verification code</button>
                        </div>
                    </div>
                </form>

                <div class="signup-step" data-step-panel="3" <?php echo $currentStep === 3 ? '' : 'hidden'; ?>>
                    <p class="account-subtitle">Enter the 6-digit code sent to <strong><?php echo sanitize($pendingEmail); ?></strong>. It expires in 10 minutes.</p>
                    <form method="POST" action="register.php" id="verifyOtpForm">
                        <input type="hidden" name="registration_action" value="verify_code">
                        <div class="form-group">
                            <label for="signupOtp">Email verification code</label>
                            <input class="otp-input" type="text" name="otp" id="signupOtp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" aria-label="6-digit verification code" required>
                        </div>
                        <div class="step-actions">
                            <a class="btn btn-secondary" href="register.php?cancel=1">Change email</a>
                            <button type="submit" class="btn btn-primary">Verify and continue</button>
                        </div>
                    </form>
                    <form method="POST" action="register.php" style="margin-top: 16px; text-align: center;">
                        <input type="hidden" name="registration_action" value="resend_code">
                        <button type="submit" class="btn btn-ghost">Send a new code</button>
                    </form>
                </div>

                <p class="account-switch">Already have an account? <a href="login.php<?php echo $nextQuery; ?>">Log in</a></p>
            </section>
        </div>
    </main>
    <?php include 'site_footer.php'; ?>
    <script src="js/main.js?v=7"></script>
    <script>
        (() => {
            const form = document.getElementById('signupForm');
            const panels = [...document.querySelectorAll('[data-step-panel]')];
            const markers = [...document.querySelectorAll('[data-step-marker]')];
            const continueButton = document.getElementById('continueSignup');
            const backButton = document.getElementById('backSignup');
            const firstStepFields = [...panels[0].querySelectorAll('input, select')];
            const secondStepFields = [...panels[1].querySelectorAll('input')];
            const password = document.getElementById('signupPassword');
            const confirmation = document.getElementById('signupConfirmPassword');
            let activeStep = <?php echo (int)$currentStep; ?>;

            function showStep(step) {
                activeStep = step;
                panels.forEach(panel => {
                    const isActive = Number(panel.dataset.stepPanel) === step;
                    panel.hidden = !isActive;
                    if (isActive) {
                        panel.classList.remove('signup-step');
                        void panel.offsetWidth;
                        panel.classList.add('signup-step');
                    }
                });
                markers.forEach(marker => {
                    const markerStep = Number(marker.dataset.stepMarker);
                    marker.classList.toggle('active', markerStep === step);
                    marker.classList.toggle('complete', markerStep < step);
                });
            }

            continueButton.addEventListener('click', () => {
                const invalidField = firstStepFields.find(field => !field.checkValidity());
                if (invalidField) {
                    invalidField.reportValidity();
                    return;
                }
                firstStepFields.forEach(field => field.required = false);
                showStep(2);
                password.focus();
            });

            backButton.addEventListener('click', () => {
                firstStepFields.forEach(field => field.required = true);
                showStep(1);
                document.getElementById('signupName').focus();
            });

            form.addEventListener('submit', event => {
                confirmation.setCustomValidity(password.value === confirmation.value ? '' : 'Passwords do not match.');
                if (!form.checkValidity()) {
                    event.preventDefault();
                    form.reportValidity();
                }
            });

            [password, confirmation].forEach(field => {
                field.addEventListener('input', () => confirmation.setCustomValidity(''));
            });
            if (activeStep >= 2) firstStepFields.forEach(field => field.required = false);
            if (activeStep === 3) secondStepFields.forEach(field => field.required = false);
            showStep(activeStep);
        })();
    </script>
</body>
</html>
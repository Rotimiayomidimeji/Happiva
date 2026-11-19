<?php
/**
 * Authentication Functions
 */

require_once 'config.php';

function safeAuthReturnPath($candidate) {
    $candidate = trim((string)$candidate);
    return preg_match('/^event\.php\?id=[1-9][0-9]*$/', $candidate) ? $candidate : '';
}

function createRegisteredUser($name, $email, $passwordHash, $role = 'user') {
    $pdo = getDB();

    $name = trim((string)($name ?? ''));
    $email = strtolower(trim((string)($email ?? '')));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $passwordHash === '') {
        return ['success' => false, 'message' => 'Please provide a valid name, email, and password.'];
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email already registered'];
    }

    $validRoles = ['user', 'organizer'];
    if (!in_array($role, $validRoles, true)) {
        $role = 'user';
    }

    $organizerRequestPending = ($role === 'organizer') ? 1 : 0;

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, email_verified, organizer_request_pending) VALUES (?, ?, ?, ?, 1, ?)");
        $stmt->execute([$name, $email, $passwordHash, $role, $organizerRequestPending]);
        $newUserId = $pdo->lastInsertId();

        if ($role === 'organizer') {
            try {
            $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
            $admins = $adminStmt->fetchAll();
            if (!empty($admins)) {
                $notifyStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id) VALUES (?, 'new_organizer', 'New Organizer Verification Request', ?, ?)");
                $message = "A new organizer ($name) has verified their email and requires account approval.";
                foreach ($admins as $admin) {
                    $notifyStmt->execute([$admin['id'], $message, $newUserId]);
                }
            }
            } catch (PDOException $e) {
                error_log('Unable to notify admins about organizer registration: ' . $e->getMessage());
            }
        }

        return ['success' => true, 'message' => 'Registration successful', 'user_id' => (int)$newUserId, 'role' => $role];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

function registerUser($name, $email, $password, $role = 'user') {
    $password = (string)($password ?? '');
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
    }

    return createRegisteredUser($name, $email, password_hash($password, PASSWORD_DEFAULT), $role);
}

function sendRegistrationOtpEmail($email, $code) {
    $subject = 'Your Happiva email verification code';
    $message = "Your Happiva verification code is: {$code}\n\n";
    $message .= "This code expires in 10 minutes. You have up to 5 attempts to enter it.\n";
    $message .= "If you did not start this signup, you can ignore this email.";

    $smtpHost = trim((string)appConfigValue('SMTP_HOST', 'smtp.gmail.com'));
    if ($smtpHost !== '') {
        $autoloadPath = __DIR__ . '/vendor/autoload.php';
        if (!is_file($autoloadPath)) {
            error_log('SMTP is configured but PHPMailer is missing. Run composer install in the project directory.');
            return false;
        }

        require_once $autoloadPath;
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            error_log('SMTP is configured but PHPMailer could not be loaded.');
            return false;
        }

        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $smtpHost;
            $mailer->Port = (int)appConfigValue('SMTP_PORT', 587);
            $mailer->SMTPAuth = (string)appConfigValue('SMTP_AUTH', '1') !== '0';
            if ($mailer->SMTPAuth) {
                $mailer->Username = (string)appConfigValue('SMTP_USERNAME', ADMIN_EMAIL);
                $mailer->Password = (string)appConfigValue('SMTP_PASSWORD');
            }
            $encryption = strtolower((string)appConfigValue('SMTP_ENCRYPTION', 'tls'));
            $mailer->SMTPSecure = in_array($encryption, ['ssl', 'smtps'], true)
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
            $mailer->setFrom((string)appConfigValue('SMTP_FROM', $mailer->Username ?: ADMIN_EMAIL), (string)appConfigValue('SMTP_FROM_NAME', SITE_NAME));
            $mailer->addAddress($email);
            $mailer->Subject = $subject;
            $mailer->Body = $message;
            $mailer->send();
            return true;
        } catch (Throwable $e) {
            error_log('Unable to send registration OTP email: ' . $e->getMessage());
            return false;
        }
    }

    $headers = "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return @mail($email, $subject, $message, $headers);
}

function issueRegistrationOtp($name, $email, $passwordHash, $role) {
    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("SELECT * FROM registration_verifications WHERE email = ? FOR UPDATE");
        $stmt->execute([$email]);
        $pending = $stmt->fetch();

        if ($pending && (time() - strtotime($pending['last_sent_at'])) < 60) {
            $pdo->rollBack();
            return ['success' => false, 'cooldown' => true, 'message' => 'A code was sent recently. Check your email or wait a minute before resending.'];
        }

        $code = (string)random_int(100000, 999999);
        $codeHash = password_hash($code, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + 600);

        if ($pending) {
            $stmt = $pdo->prepare("UPDATE registration_verifications SET name = ?, password_hash = ?, role = ?, code_hash = ?, attempts = 0, expires_at = ?, last_sent_at = NOW() WHERE email = ?");
            $stmt->execute([$name, $passwordHash, $role, $codeHash, $expiresAt, $email]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO registration_verifications (name, email, password_hash, role, code_hash, expires_at, last_sent_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $passwordHash, $role, $codeHash, $expiresAt]);
        }

        if (!sendRegistrationOtpEmail($email, $code)) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'We could not send the verification email. Please try again later or check the site mail configuration.'];
        }

        $pdo->commit();
        return ['success' => true, 'message' => 'We sent a 6-digit code to your email address.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Unable to issue registration OTP: ' . $e->getMessage());
        return ['success' => false, 'message' => 'We could not start email verification. Please try again.'];
    }
}

function beginEmailVerification($name, $email, $password, $role = 'user') {
    $name = trim((string)$name);
    $email = strtolower(trim((string)$email));
    $password = (string)$password;
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        return ['success' => false, 'message' => 'Check your name, email, and password, then try again.'];
    }

    $validRoles = ['user', 'organizer'];
    if (!in_array($role, $validRoles, true)) {
        $role = 'user';
    }

    $stmt = getDB()->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'That email is already registered. Try logging in instead.'];
    }

    return issueRegistrationOtp($name, $email, password_hash($password, PASSWORD_DEFAULT), $role);
}

function resendRegistrationOtp($email) {
    $email = strtolower(trim((string)$email));
    $stmt = getDB()->prepare("SELECT name, email, password_hash, role FROM registration_verifications WHERE email = ?");
    $stmt->execute([$email]);
    $pending = $stmt->fetch();
    if (!$pending) {
        return ['success' => false, 'message' => 'Your signup session expired. Please start again.'];
    }

    return issueRegistrationOtp($pending['name'], $pending['email'], $pending['password_hash'], $pending['role']);
}

function verifyRegistrationOtp($email, $code) {
    $email = strtolower(trim((string)$email));
    $code = trim((string)$code);
    if (!preg_match('/^[0-9]{6}$/', $code)) {
        return ['success' => false, 'message' => 'Enter the 6-digit code from your email.'];
    }

    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM registration_verifications WHERE email = ? FOR UPDATE");
        $stmt->execute([$email]);
        $pending = $stmt->fetch();
        if (!$pending) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Your signup session expired. Please start again.'];
        }
        if (strtotime($pending['expires_at']) < time()) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'That code has expired. Request a new one below.'];
        }
        if ((int)$pending['attempts'] >= 5) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Too many incorrect attempts. Request a new code to continue.'];
        }
        if (!password_verify($code, $pending['code_hash'])) {
            $stmt = $pdo->prepare("UPDATE registration_verifications SET attempts = attempts + 1 WHERE email = ?");
            $stmt->execute([$email]);
            $pdo->commit();
            return ['success' => false, 'message' => 'That code is not correct. Check it and try again.'];
        }

        $result = createRegisteredUser($pending['name'], $pending['email'], $pending['password_hash'], $pending['role']);
        if (!$result['success']) {
            $pdo->rollBack();
            return $result;
        }

        $stmt = $pdo->prepare("DELETE FROM registration_verifications WHERE email = ?");
        $stmt->execute([$email]);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Unable to verify registration OTP: ' . $e->getMessage());
        return ['success' => false, 'message' => 'We could not verify that code. Please try again.'];
    }
}

function cancelEmailVerification($email) {
    $stmt = getDB()->prepare("DELETE FROM registration_verifications WHERE email = ?");
    $stmt->execute([strtolower(trim((string)$email))]);
}

function establishUserSession($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_profile_image'] = $user['profile_image'] ?? '';
    $_SESSION['user_bio'] = $user['bio'] ?? '';
    $_SESSION['user_cover_image'] = $user['cover_image'] ?? '';
    $_SESSION['role'] = $user['role'];
    $_SESSION['is_verified'] = $user['is_verified'] ?? 0;
    $_SESSION['organizer_request_pending'] = $user['organizer_request_pending'] ?? 0;
}

// Login user
function loginUser($email, $password) {
    $pdo = getDB();

    $email = strtolower(trim((string)($email ?? '')));
    $password = (string)($password ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        establishUserSession($user);
        return ['success' => true, 'message' => 'Login successful', 'role' => $user['role']];
    }
    
    return ['success' => false, 'message' => 'Invalid email or password'];
}

// Logout user
function logoutUser() {
    session_destroy();
    redirect('index.php');
}



// Handle auth actions
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $result = ['success' => false, 'message' => ''];
    
    switch ($_POST['action']) {
        case 'register':
            $result = ['success' => false, 'message' => 'Please use the registration page to verify your email.'];
            break;
            
        case 'login':
            $result = loginUser($_POST['email'], $_POST['password']);
            break;
            
        case 'logout':
            logoutUser();
            break;
    }
    
    echo json_encode($result);
    exit;
}

// Handle GET logout request
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser();
}

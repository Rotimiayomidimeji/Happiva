<?php
/**
 * Happiva - Event Booking Platform
 * Database Configuration
 */

function appConfigValue($name, $default = '') {
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return $_SERVER[$name];
    }

    if (function_exists('apache_getenv')) {
        $value = apache_getenv($name);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }

    static $localConfig = null;
    if ($localConfig === null) {
        $localConfigPath = __DIR__ . '/config.local.php';
        $localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
        if (!is_array($localConfig)) {
            $localConfig = [];
        }
    }

    if (isset($localConfig[$name]) && $localConfig[$name] !== '') {
        return $localConfig[$name];
    }

    return $default;
}

// Database credentials
define('DB_HOST', appConfigValue('DB_HOST', 'localhost'));
define('DB_NAME', appConfigValue('DB_NAME', 'happiva_db'));
define('DB_USER', appConfigValue('DB_USER', 'root'));
define('DB_PASS', appConfigValue('DB_PASS'));

// Site configuration
define('SITE_NAME', 'Happiva');
define('SITE_URL', rtrim(appConfigValue('SITE_URL', 'http://localhost/Happiva'), '/'));
define('ADMIN_EMAIL', appConfigValue('ADMIN_EMAIL', 'dimejirotimi5@gmail.com'));

define('PAYSTACK_PUBLIC_KEY', appConfigValue('PAYSTACK_PUBLIC_KEY', 'pk_test_example'));
define('PAYSTACK_SECRET_KEY', appConfigValue('PAYSTACK_SECRET_KEY'));
define('PAYSTACK_CALLBACK_URL', SITE_URL . '/checkout.php');
define('PAYSTACK_COMMISSION_PERCENTAGE', 10);
define('PAYSTACK_TRANSFER_RECIPIENT_CODE', appConfigValue('PAYSTACK_TRANSFER_RECIPIENT_CODE'));
define('GROQ_API_KEY', appConfigValue('GROQ_API_KEY'));
define('GEMINI_API_KEY', appConfigValue('GEMINI_API_KEY'));

define('REFUND_SERVICE_FEE_PERCENTAGE', 10);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            ensureCoreTables($pdo);
            ensureEventTicketColorColumns($pdo);
            ensureProfileColumns($pdo);
            ensureRegistrationVerificationsTable($pdo);
            ensureFavoritesTable($pdo);
            ensureMessagesTable($pdo);
            ensureNotificationsTable($pdo);
            ensureContactSubmissionsTable($pdo);
            ensureBookingRefundColumns($pdo);
            ensurePaymentsSchema($pdo);
            ensureRefundsTable($pdo);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

function ensureEventTicketColorColumns($pdo) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'ticket_primary_color'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE events ADD COLUMN ticket_primary_color VARCHAR(20) DEFAULT '#4f46e5'");
            $pdo->exec("ALTER TABLE events ADD COLUMN ticket_secondary_color VARCHAR(20) DEFAULT '#ec4899'");
        }
    } catch (PDOException $e) {
        // Ignore migration errors on older databases and continue with defaults.
    }
}

function ensureProfileColumns($pdo) {
    try {
        $columns = [
            'profile_image' => "VARCHAR(255) NULL",
            'bio' => "TEXT NULL",
            'cover_image' => "VARCHAR(255) NULL",
            'is_verified' => "TINYINT(1) DEFAULT 0",
            'email_verified' => "TINYINT(1) NOT NULL DEFAULT 1",
            'organizer_request_pending' => "TINYINT(1) DEFAULT 0"
        ];

        foreach ($columns as $column => $definition) {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE '$column'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
            }
        }
    } catch (PDOException $e) {
        // Ignore migration errors on older databases and continue with defaults.
    }
}

function ensureRegistrationVerificationsTable($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS registration_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        code_hash VARCHAR(255) NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        last_sent_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_registration_verification_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensureFavoritesTable($pdo) {
    try {
        $pdo->query("SELECT 1 FROM favorites LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_event (user_id, event_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )");
    }
}

function ensureMessagesTable($pdo) {
    try {
        $pdo->query("SELECT 1 FROM messages LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            recipient_id INT NOT NULL,
            event_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )");
    }
}

function ensureNotificationsTable($pdo) {
    try {
        $pdo->query("SELECT 1 FROM notifications LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            sender_id INT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT,
            reference_id INT,
            is_read TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'sender_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN sender_id INT NULL AFTER user_id");
        }
    } catch (PDOException $e) {
        // Ignore migration errors on older databases and continue with defaults.
    }
}

function ensureContactSubmissionsTable($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        category VARCHAR(40) NOT NULL DEFAULT 'other',
        subject VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME NULL,
        resolved_by INT NULL,
        INDEX idx_contact_status_created (status, created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensureBookingRefundColumns($pdo) {
    try {
        $refundColumns = [
            'refund_amount' => "DECIMAL(10,2) NULL",
            'refund_fee' => "DECIMAL(10,2) NULL"
        ];

        foreach ($refundColumns as $column => $definition) {
            $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE '$column'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN $column $definition");
            }
        }
    } catch (PDOException $e) {
        // Ignore migration errors on older databases and continue with defaults.
    }
}

function ensurePaymentsSchema($pdo) {
    try {
        $stmt = $pdo->query("SELECT 1 FROM payments LIMIT 1");
        $requiredPaymentColumns = [
            'booking_id' => "INT NOT NULL",
            'commission_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00",
            'organizer_amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00",
            'raw_response' => "TEXT"
        ];

        foreach ($requiredPaymentColumns as $column => $definition) {
            $colStmt = $pdo->query("SHOW COLUMNS FROM payments LIKE '$column'");
            if ($colStmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE payments ADD COLUMN $column $definition");
            }
        }
    } catch (PDOException $e) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS payments");
        } catch (PDOException $inner) {
            // Ignore drop failures and retry create.
        }
        $pdo->exec("CREATE TABLE payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            user_id INT NOT NULL,
            event_id INT NOT NULL,
            reference VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            organizer_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            raw_response TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )");
    }
}

function ensureRefundsTable($pdo) {
    try {
        $pdo->query("SELECT 1 FROM refunds LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("CREATE TABLE refunds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            user_id INT NOT NULL,
            organizer_id INT NULL,
            event_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            fee DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )");
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM refunds LIKE 'organizer_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE refunds ADD COLUMN organizer_id INT NULL AFTER user_id");
        }
    } catch (PDOException $e) {
        // Ignore migration errors on older databases and continue.
    }

    try {
        $stmt = $pdo->query("SELECT 1 FROM payment_payouts LIMIT 1");
        $requiredPayoutColumns = [
            'booking_id' => "INT NOT NULL",
            'user_id' => "INT NOT NULL",
            'type' => "VARCHAR(50) NOT NULL",
            'reference' => "VARCHAR(255)"
        ];

        foreach ($requiredPayoutColumns as $column => $definition) {
            $colStmt = $pdo->query("SHOW COLUMNS FROM payment_payouts LIKE '$column'");
            if ($colStmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE payment_payouts ADD COLUMN $column $definition");
            }
        }
    } catch (PDOException $e) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS payment_payouts");
        } catch (PDOException $inner) {
            // Ignore drop failures and retry create.
        }
        $pdo->exec("CREATE TABLE payment_payouts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            type VARCHAR(50) NOT NULL,
            reference VARCHAR(255),
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    }
}

function ensureCoreTables($pdo) {
    $tables = [
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('user','organizer','admin') DEFAULT 'user',
            is_verified TINYINT(1) DEFAULT 0,
            email_verified TINYINT(1) NOT NULL DEFAULT 1,
            organizer_request_pending TINYINT(1) DEFAULT 0,
            profile_image VARCHAR(255) NULL,
            bio TEXT NULL,
            cover_image VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        'events' => "CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            expectations TEXT,
            date DATE NOT NULL,
            time TIME NOT NULL,
            venue VARCHAR(200) NOT NULL,
            address VARCHAR(300),
            price DECIMAL(10,2) DEFAULT 0.00,
            capacity INT DEFAULT 100,
            category VARCHAR(50),
            image VARCHAR(300),
            ticket_primary_color VARCHAR(20) DEFAULT '#4f46e5',
            ticket_secondary_color VARCHAR(20) DEFAULT '#ec4899',
            organizer_id INT,
            is_approved TINYINT(1) DEFAULT 0,
            featured TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE SET NULL
        )",
        'bookings' => "CREATE TABLE IF NOT EXISTS bookings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            total_price DECIMAL(10,2) NOT NULL,
            refund_amount DECIMAL(10,2) NULL,
            refund_fee DECIMAL(10,2) NULL,
            status ENUM('pending','confirmed','cancelled') DEFAULT 'confirmed',
            qr_code VARCHAR(100) UNIQUE,
            checked_in TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )",
        'event_comments' => "CREATE TABLE IF NOT EXISTS event_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        'checkin_logs' => "CREATE TABLE IF NOT EXISTS checkin_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            scanned_by INT NOT NULL,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (scanned_by) REFERENCES users(id) ON DELETE CASCADE
        )",
        'payments' => "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            user_id INT NOT NULL,
            event_id INT NOT NULL,
            reference VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            organizer_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            raw_response TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )",
        'payment_payouts' => "CREATE TABLE IF NOT EXISTS payment_payouts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            type VARCHAR(50) NOT NULL,
            reference VARCHAR(255),
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    ];

    foreach ($tables as $name => $sql) {
        try {
            $pdo->query("SELECT 1 FROM $name LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $inner) {
                // Ignore failures during schema recovery so the site can still load if possible.
            }
        }
    }
}

function getUnreadMessageNotificationCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'message' AND is_read = 0");
    $stmt->execute([(int) $userId]);
    return (int) $stmt->fetchColumn();
}

function markMessageNotificationsRead($pdo, $userId, $eventId = 0, $senderId = 0) {
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = 'message' AND is_read = 0";
    $params = [(int) $userId];

    if ($eventId > 0) {
        $query .= " AND reference_id = ?";
        $params[] = (int) $eventId;
    }

    if ($senderId > 0) {
        $query .= " AND sender_id = ?";
        $params[] = (int) $senderId;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function normalizeTicketColor($value, $fallback) {
    $color = trim((string) $value);
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
        return $color;
    }

    return $fallback;
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Refresh the current user's session data from the database
function refreshCurrentUserSession() {
    if (!isLoggedIn()) {
        return null;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_profile_image'] = $user['profile_image'] ?? '';
        $_SESSION['user_bio'] = $user['bio'] ?? '';
        $_SESSION['user_cover_image'] = $user['cover_image'] ?? '';
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_verified'] = $user['is_verified'] ?? 0;
        $_SESSION['organizer_request_pending'] = $user['organizer_request_pending'] ?? 0;
    }

    return $user;
}

// Get current user
function getCurrentUser() {
    return refreshCurrentUserSession();
}

function isOrganizer() {
    $user = refreshCurrentUserSession();
    return !empty($user) && in_array($user['role'], ['organizer', 'admin']);
}

function isAdmin() {
    $user = refreshCurrentUserSession();
    return !empty($user) && $user['role'] === 'admin';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateTicketId() {
    return 'HAP-' . strtoupper(bin2hex(random_bytes(4)));
}

function generateUniqueTicketId($pdo) {
    do {
        $ticketId = generateTicketId();
        $stmt = $pdo->prepare("SELECT 1 FROM bookings WHERE qr_code = ?");
        $stmt->execute([$ticketId]);
        $exists = $stmt->fetch();
    } while ($exists);

    return $ticketId;
}

function generateQRCode($data) {
    // Generate QR code using Google Charts API (simple approach)
    return 'https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=' . urlencode($data);
}

function resolveImageUrl($imagePath) {
    $imagePath = trim((string) $imagePath);
    $scriptDirectory = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
    $applicationBasePath = ($scriptDirectory === '.' || $scriptDirectory === '/') ? '' : rtrim($scriptDirectory, '/');

    if ($imagePath === '') {
        return $applicationBasePath . '/uploads/events/event-placeholder.svg';
    }

    if (preg_match('/^https?:\/\//i', $imagePath)) {
        return $imagePath;
    }

    if (strpos($imagePath, 'data:image/') === 0) {
        return $imagePath;
    }

    $normalizedPath = str_replace('\\', '/', $imagePath);
    $projectRoot = str_replace('\\', '/', __DIR__);

    if (preg_match('/^uploads\//', $normalizedPath) || preg_match('/^\/uploads\//', $normalizedPath)) {
        return $applicationBasePath . '/' . ltrim($normalizedPath, '/');
    }

    if (preg_match('/^' . preg_quote($projectRoot, '/') . '/i', $normalizedPath)) {
        $relativePath = ltrim(substr($normalizedPath, strlen($projectRoot)), '/');
        if (!empty($relativePath)) {
            return $applicationBasePath . '/' . $relativePath;
        }
    }

    $relativeCandidate = ltrim($normalizedPath, '/');
    if (file_exists(__DIR__ . '/' . $relativeCandidate)) {
        return $applicationBasePath . '/' . $relativeCandidate;
    }

    return $applicationBasePath . '/' . $relativeCandidate;
}

function uploadEventImage($uploadedFile) {
    if (empty($uploadedFile) || !isset($uploadedFile['error'])) {
        return null;
    }

    if ($uploadedFile['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please try again with an image smaller than 5 MB.');
    }

    if (empty($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
        throw new RuntimeException('The uploaded image could not be verified. Please try again.');
    }

    if ($uploadedFile['size'] > 5000000) {
        throw new RuntimeException('Image must be 5 MB or smaller.');
    }

    $extension = strtolower(pathinfo($uploadedFile['name'] ?? '', PATHINFO_EXTENSION));
    $allowedMimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];

    if (!isset($allowedMimeTypes[$extension])) {
        throw new RuntimeException('Choose a JPG, PNG, GIF, or WEBP image.');
    }

    $imageInfo = getimagesize($uploadedFile['tmp_name']);
    if ($imageInfo === false || $imageInfo['mime'] !== $allowedMimeTypes[$extension]) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }

    $uploadDir = __DIR__ . '/uploads/events/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('The event image folder could not be created.');
    }

    $fileName = 'event_' . bin2hex(random_bytes(12)) . '.' . $extension;
    if (!move_uploaded_file($uploadedFile['tmp_name'], $uploadDir . $fileName)) {
        throw new RuntimeException('The image could not be saved. Check that the uploads folder is writable.');
    }

    return 'uploads/events/' . $fileName;
}

// Format price
function formatPrice($price) {
    return '₦' . number_format($price, 2);
}

// Format date
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Format time
function formatTime($time) {
    return date('g:i A', strtotime($time));
}

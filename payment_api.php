<?php
/**
 * Happiva - Payment API Configuration
 * Paystack Integration with admin commission handling
 */

require_once 'config.php';

function isPaystackConfigured() {
    return !empty(PAYSTACK_SECRET_KEY) && PAYSTACK_SECRET_KEY !== 'your_paystack_secret_key';
}

function makePaystackRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    $headers = [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json'
    ];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'Unable to contact Paystack'];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'error' => 'Invalid Paystack response'];
    }

    return ['success' => true, 'httpCode' => $httpCode, 'data' => $decoded];
}

function createPaystackPayment($amount, $email, $metadata = []) {
    if (!isPaystackConfigured()) {
        $reference = 'demo_' . uniqid();
        return [
            'success' => true,
            'reference' => $reference,
            'authorization_url' => SITE_URL . '/checkout.php?payment_reference=' . $reference,
            'amount' => (int)round($amount * 100),
            'public_key' => PAYSTACK_PUBLIC_KEY,
            'demo_mode' => true
        ];
    }

    $payload = [
        'email' => $email,
        'amount' => (int)round($amount * 100),
        'currency' => 'NGN',
        'callback_url' => PAYSTACK_CALLBACK_URL . '?payment_reference=__REFERENCE__',
        'metadata' => $metadata
    ];

    $result = makePaystackRequest('https://api.paystack.co/transaction/initialize', 'POST', $payload);
    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error']];
    }

    $data = $result['data'];
    if (($data['status'] ?? false) !== true) {
        return ['success' => false, 'error' => $data['message'] ?? 'Unable to initialize Paystack payment'];
    }

    return [
        'success' => true,
        'reference' => $data['data']['reference'] ?? '',
        'authorization_url' => $data['data']['authorization_url'] ?? '',
        'amount' => $data['data']['amount'] ?? 0,
        'public_key' => PAYSTACK_PUBLIC_KEY,
        'demo_mode' => false
    ];
}

function verifyPaystackPayment($reference) {
    if (empty($reference)) {
        return ['success' => false, 'error' => 'Missing payment reference'];
    }

    if (strpos($reference, 'demo_') === 0) {
        return ['success' => true, 'status' => 'success', 'reference' => $reference, 'demo_mode' => true];
    }

    if (!isPaystackConfigured()) {
        return ['success' => true, 'status' => 'success', 'reference' => $reference, 'demo_mode' => true];
    }

    $result = makePaystackRequest('https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error']];
    }

    $data = $result['data'];
    $status = $data['data']['status'] ?? 'failed';
    if ($status === 'success') {
        return ['success' => true, 'status' => 'success', 'reference' => $reference, 'demo_mode' => false, 'amount' => $data['data']['amount'] ?? 0];
    }

    return ['success' => false, 'status' => $status, 'error' => $data['message'] ?? 'Payment was not successful'];
}

function sendPaymentEmail($to, $subject, $message) {
    if (empty($to)) {
        return false;
    }

    $headers = "From: " . ADMIN_EMAIL . "\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return @mail($to, $subject, $message, $headers);
}

function createPaystackTransfer($amount, $recipientCode, $reason, $reference) {
    if (empty(PAYSTACK_SECRET_KEY) || empty($recipientCode)) {
        return ['success' => false, 'error' => 'Paystack transfer recipient is not configured'];
    }

    $payload = [
        'source' => 'balance',
        'reason' => $reason,
        'amount' => (int)round($amount * 100),
        'recipient' => $recipientCode,
        'reference' => $reference
    ];

    $result = makePaystackRequest('https://api.paystack.co/transfer', 'POST', $payload);
    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error']];
    }

    $data = $result['data'];
    if (($data['status'] ?? false) !== true) {
        return ['success' => false, 'error' => $data['message'] ?? 'Unable to process Paystack transfer'];
    }

    return [
        'success' => true,
        'transfer_code' => $data['data']['transfer_code'] ?? '',
        'status' => $data['data']['status'] ?? 'pending'
    ];
}

function createPayoutRecord($pdo, $bookingId, $userId, $amount, $type, $reference, $status = 'pending') {
    $stmt = $pdo->prepare("INSERT INTO payment_payouts (booking_id, user_id, amount, type, reference, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$bookingId, $userId, $amount, $type, $reference, $status]);
    return $pdo->lastInsertId();
}

function finalizeBookingWithPayment($pdo, $userId, $event, $quantity, $total, $paymentReference, $paymentDetails = []) {
    $ticketId = generateUniqueTicketId($pdo);

    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, event_id, quantity, total_price, status, qr_code) VALUES (?, ?, ?, ?, 'confirmed', ?)");
    $stmt->execute([$userId, $event['id'], $quantity, $total, $ticketId]);
    $bookingId = $pdo->lastInsertId();

    $adminCommission = round($total * (PAYSTACK_COMMISSION_PERCENTAGE / 100), 2);
    $organizerShare = round($total - $adminCommission, 2);

    $stmt = $pdo->prepare("INSERT INTO payments (booking_id, user_id, event_id, reference, amount, commission_amount, organizer_amount, status, raw_response) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $bookingId,
        $userId,
        $event['id'],
        $paymentReference,
        $total,
        $adminCommission,
        $organizerShare,
        $paymentDetails['status'] ?? 'success',
        json_encode($paymentDetails)
    ]);

    $adminUser = $pdo->prepare("SELECT id, name, email FROM users WHERE role = 'admin' LIMIT 1");
    $adminUser->execute();
    $admin = $adminUser->fetch();

    $organizerUser = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $organizerUser->execute([$event['organizer_id']]);
    $organizer = $organizerUser->fetch();

    $adminPayoutId = null;
    $organizerPayoutId = null;
    if ($admin) {
        $adminPayoutId = createPayoutRecord($pdo, $bookingId, $admin['id'], $adminCommission, 'admin_commission', $paymentReference, 'pending');
        if ($adminCommission > 0 && !empty(PAYSTACK_TRANSFER_RECIPIENT_CODE)) {
            $transferResult = createPaystackTransfer($adminCommission, PAYSTACK_TRANSFER_RECIPIENT_CODE, 'Admin commission payout for ' . $event['title'], $paymentReference);
            if ($transferResult['success']) {
                $updateStmt = $pdo->prepare("UPDATE payment_payouts SET status = 'processed' WHERE id = ?");
                $updateStmt->execute([$adminPayoutId]);
            } else {
                $updateStmt = $pdo->prepare("UPDATE payment_payouts SET status = 'failed' WHERE id = ?");
                $updateStmt->execute([$adminPayoutId]);
            }
        }
    }
    if ($organizer) {
        $organizerPayoutId = createPayoutRecord($pdo, $bookingId, $organizer['id'], $organizerShare, 'organizer_payout', $paymentReference, 'pending');
    }

    $bookingMessage = 'A new booking was completed for ' . $event['title'] . ' (' . $quantity . ' ticket(s), total ' . formatPrice($total) . ').';
    if ($organizer) {
        $notifyStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id) VALUES (?, 'booking', ?, ?, ?)");
        $notifyStmt->execute([$organizer['id'], 'New Booking: ' . $event['title'], $bookingMessage, $bookingId]);
    }
    if ($admin) {
        $notifyStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id) VALUES (?, 'booking', ?, ?, ?)");
        $notifyStmt->execute([$admin['id'], 'Commission Earned', 'Admin commission for booking ' . $event['title'] . ' is ' . formatPrice($adminCommission) . '.', $bookingId]);
    }

    if ($admin) {
        $adminEmailBody = "Hello {$admin['name']},\n\nA ticket sale for {$event['title']} has been completed.\nTotal collected: " . formatPrice($total) . "\nAdmin commission (10%): " . formatPrice($adminCommission) . "\n\nThis payout is now pending for review.\n";
        sendPaymentEmail($admin['email'], 'Happiva commission received', $adminEmailBody);
    }

    if ($organizer) {
        $organizerEmailBody = "Hello {$organizer['name']},\n\nYour event '{$event['title']}' received a new booking.\nTickets sold: {$quantity}\nNet payout to you: " . formatPrice($organizerShare) . "\n\nThank you for using Happiva.\n";
        sendPaymentEmail($organizer['email'], 'New booking received for your event', $organizerEmailBody);
    }

    return $bookingId;
}

if (isset($_POST['action']) && $_POST['action'] === 'create_payment') {
    header('Content-Type: application/json');

    $amount = floatval($_POST['amount']);
    $email = trim($_POST['email'] ?? '');
    $eventId = intval($_POST['event_id']);
    $quantity = intval($_POST['quantity'] ?? 1);
    $userId = $_SESSION['user_id'] ?? 0;

    $result = createPaystackPayment($amount, $email, [
        'event_id' => $eventId,
        'user_id' => $userId,
        'quantity' => $quantity
    ]);

    echo json_encode($result);
    exit;
}

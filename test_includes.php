<?php
// Prevent any output before headers
ob_start();

// Disable error display but keep logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    // Clear any previous output
    ob_clean();
    
    $results = [];
    
    // Test if files exist
    $files = [
        'class.phpmailer.php',
        'class.smtp.php',
        'class.smtp_new.php'
    ];
    
    foreach ($files as $file) {
        $results[$file] = [
            'exists' => file_exists($file),
            'readable' => is_readable($file),
            'size' => file_exists($file) ? filesize($file) : 0
        ];
    }
    
    // Try to include PHPMailer
    $phpmailerLoaded = false;
    if (file_exists('class.phpmailer.php')) {
        try {
            include_once 'class.phpmailer.php';
            $phpmailerLoaded = class_exists('PHPMailer');
        } catch (Exception $e) {
            $results['phpmailer_error'] = $e->getMessage();
        }
    }
    
    // Try to include SMTP
    $smtpLoaded = false;
    $smtpFiles = ['class.smtp.php', 'class.smtp_new.php'];
    
    foreach ($smtpFiles as $smtpFile) {
        if (file_exists($smtpFile)) {
            try {
                include_once $smtpFile;
                $smtpLoaded = class_exists('SMTP');
                if ($smtpLoaded) {
                    $results['smtp_loaded_from'] = $smtpFile;
                    break;
                }
            } catch (Exception $e) {
                $results['smtp_error'] = $e->getMessage();
            }
        }
    }
    
    $results['phpmailer_loaded'] = $phpmailerLoaded;
    $results['smtp_loaded'] = $smtpLoaded;
    $results['php_version'] = PHP_VERSION;
    $results['timestamp'] = date('Y-m-d H:i:s T');
    
    // Clear any output and send JSON response
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Include test completed',
        'results' => $results
    ]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Test failed',
        'error' => $e->getMessage()
    ]);
} catch (Error $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error',
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
?>
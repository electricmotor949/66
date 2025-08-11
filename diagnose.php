<?php
// Start output buffering
ob_start();

// Disable error display
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$diagnosis = [];

try {
    // Check if files exist
    $files = [
        'class.phpmailer.php',
        'class.smtp.php',
        'class.smtp_new.php',
        'postmailer.php'
    ];
    
    foreach ($files as $file) {
        $diagnosis[$file] = [
            'exists' => file_exists($file),
            'readable' => is_readable($file),
            'size' => file_exists($file) ? filesize($file) : 0
        ];
    }
    
    // Check PHP configuration
    $diagnosis['php_config'] = [
        'version' => PHP_VERSION,
        'display_errors' => ini_get('display_errors'),
        'error_reporting' => ini_get('error_reporting'),
        'max_execution_time' => ini_get('max_execution_time')
    ];
    
    // Try to include files and check for output
    $diagnosis['includes'] = [];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            ob_start();
            $includeResult = @include_once $file;
            $output = ob_get_clean();
            
            $diagnosis['includes'][$file] = [
                'success' => $includeResult !== false,
                'output_length' => strlen($output),
                'output_preview' => substr($output, 0, 100),
                'has_output' => !empty($output)
            ];
        }
    }
    
    // Check for any current output
    $currentOutput = ob_get_contents();
    $diagnosis['current_output'] = [
        'length' => strlen($currentOutput),
        'preview' => substr($currentOutput, 0, 100),
        'has_output' => !empty($currentOutput)
    ];
    
    // Clear any output
    ob_clean();
    
    $diagnosis['timestamp'] = date('Y-m-d H:i:s T');
    
    echo json_encode([
        'success' => true,
        'diagnosis' => $diagnosis
    ]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
?>
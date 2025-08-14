<?php
// Simple test endpoint to verify AJAX is working
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Disable CSRF for testing
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);

// Clean any output
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

try {
    // Test basic functionality
    $test_data = [
        'success' => true,
        'message' => 'Test endpoint working',
        'timestamp' => date('Y-m-d H:i:s'),
        'post_data' => $_POST,
        'get_data' => $_GET,
        'method' => $_SERVER['REQUEST_METHOD']
    ];
    
    echo json_encode($test_data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

exit;
?>
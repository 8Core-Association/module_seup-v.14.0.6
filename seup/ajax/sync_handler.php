<?php
/**
 * AJAX endpoint for Nextcloud sync operations
 * (c) 2025 8Core Association
 */

// Enable comprehensive error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

// Custom error handler to catch all errors
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error = "PHP Error [$errno]: $errstr in $errfile on line $errline";
    error_log($error);
    return false; // Let PHP handle the error normally
}
set_error_handler('customErrorHandler');

// Custom exception handler
function customExceptionHandler($exception) {
    $error = "Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
    error_log($error);
    
    // Clean any output and send JSON error
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    exit;
}
set_exception_handler('customExceptionHandler');

// Disable CSRF for AJAX
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);

// Log the start of the script
error_log("sync_handler.php: Script started");

// Load Dolibarr environment
$res = 0;
error_log("sync_handler.php: Attempting to load Dolibarr environment");

if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
    if ($res) error_log("sync_handler.php: Loaded from CONTEXT_DOCUMENT_ROOT");
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
    if ($res) error_log("sync_handler.php: Loaded from calculated path");
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
    if ($res) error_log("sync_handler.php: Loaded from dirname path");
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
    if ($res) error_log("sync_handler.php: Loaded from ../../main.inc.php");
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
    if ($res) error_log("sync_handler.php: Loaded from ../../../main.inc.php");
}
if (!$res) {
    error_log("sync_handler.php: FAILED to load Dolibarr environment");
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load Dolibarr environment']);
    exit;
}

error_log("sync_handler.php: Dolibarr environment loaded successfully");

// Check if essential Dolibarr objects exist
if (!isset($db)) {
    error_log("sync_handler.php: Database object not available");
    throw new Exception('Database object not available');
}
if (!isset($conf)) {
    error_log("sync_handler.php: Configuration object not available");
    throw new Exception('Configuration object not available');
}
if (!isset($user)) {
    error_log("sync_handler.php: User object not available");
    throw new Exception('User object not available');
}

error_log("sync_handler.php: Essential Dolibarr objects verified");

// Load required classes with error checking
$cloud_helper_path = __DIR__ . '/../class/cloud_helper.class.php';
error_log("sync_handler.php: Looking for Cloud_helper at: " . $cloud_helper_path);

if (!file_exists($cloud_helper_path)) {
    error_log("sync_handler.php: Cloud_helper file not found");
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Cloud_helper class not found at: ' . $cloud_helper_path]);
    exit;
}

error_log("sync_handler.php: Cloud_helper file found, requiring...");
require_once $cloud_helper_path;

// Check if class exists
if (!class_exists('Cloud_helper')) {
    error_log("sync_handler.php: Cloud_helper class not loaded after require");
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Cloud_helper class not loaded']);
    exit;
}

error_log("sync_handler.php: Cloud_helper class loaded successfully");

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("sync_handler.php: Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

error_log("sync_handler.php: POST request confirmed");

// Clean output buffer and set JSON header NOW
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json');

try {
    $action = GETPOST('action', 'alpha');
    $predmet_id = GETPOST('predmet_id', 'int');
    $sync_type = GETPOST('sync_type', 'alpha') ?: 'nextcloud_to_ecm';
    
    error_log("sync_handler.php: Parsed parameters - Action: $action, Predmet: $predmet_id, Type: $sync_type");
    
    // Debug: Log all POST data
    error_log("sync_handler.php: POST data: " . print_r($_POST, true));
    error_log("sync_handler.php: Raw input: " . file_get_contents('php://input'));
    
    if (!$predmet_id) {
        error_log("sync_handler.php: Missing predmet ID");
        throw new Exception('Missing predmet ID. Received POST: ' . print_r($_POST, true));
    }
    
    // Check if required functions exist
    if (!function_exists('getDolGlobalString')) {
        error_log("sync_handler.php: getDolGlobalString function not available");
        throw new Exception('Dolibarr functions not available');
    }
    
    error_log("sync_handler.php: About to call Cloud_helper method for action: $action");
    
    switch ($action) {
        case 'sync_nextcloud':
            error_log("sync_handler.php: Calling sync method with type: $sync_type");
            if ($sync_type === 'bidirectional') {
                $result = Cloud_helper::bidirectionalSync($db, $conf, $user, $predmet_id);
            } else {
                $result = Cloud_helper::syncNextcloudToECM($db, $conf, $user, $predmet_id);
            }
            error_log("sync_handler.php: Sync completed, result: " . print_r($result, true));
            break;
            
        case 'bulk_sync':
            $limit = GETPOST('limit', 'int') ?: 20;
            error_log("sync_handler.php: Calling bulk sync with limit: $limit");
            $result = Cloud_helper::bulkSyncAllPredmeti($db, $conf, $user, $limit);
            break;
            
        case 'validate_connection':
            error_log("sync_handler.php: Calling validate connection");
            $result = Cloud_helper::validateNextcloudConnection($db, $conf);
            break;
            
        default:
            error_log("sync_handler.php: Unknown action: $action");
            throw new Exception('Unknown action: ' . $action);
    }
    
    // Ensure result is valid
    if (!is_array($result)) {
        error_log("sync_handler.php: Invalid result type: " . gettype($result));
        throw new Exception('Invalid result from Cloud_helper method');
    }
    
    error_log("sync_handler.php: Sending successful JSON response");
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("sync_handler.php: Exception caught: " . $e->getMessage());
    error_log("sync_handler.php: Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'debug_info' => [
            'post_data' => $_POST,
            'get_data' => $_GET,
            'request_method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
}

error_log("sync_handler.php: Script completed");
exit;
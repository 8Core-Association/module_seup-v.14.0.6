<?php
/**
 * AJAX endpoint for Nextcloud sync operations
 * (c) 2025 8Core Association
 */

// Disable CSRF for AJAX
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
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
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

// Clean output buffer and set JSON header
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json');

// Load required classes
require_once __DIR__ . '/../class/cloud_helper.class.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $action = GETPOST('action', 'alpha');
    $predmet_id = GETPOST('predmet_id', 'int');
    $sync_type = GETPOST('sync_type', 'alpha') ?: 'nextcloud_to_ecm';
    
    dol_syslog("AJAX Sync request - Action: $action, Predmet: $predmet_id, Type: $sync_type", LOG_INFO);
    
    if (!$predmet_id) {
        throw new Exception('Missing predmet ID');
    }
    
    switch ($action) {
        case 'sync_nextcloud':
            if ($sync_type === 'bidirectional') {
                $result = Cloud_helper::bidirectionalSync($db, $conf, $user, $predmet_id);
            } else {
                $result = Cloud_helper::syncNextcloudToECM($db, $conf, $user, $predmet_id);
            }
            break;
            
        case 'bulk_sync':
            $limit = GETPOST('limit', 'int') ?: 20;
            $result = Cloud_helper::bulkSyncAllPredmeti($db, $conf, $user, $limit);
            break;
            
        case 'validate_connection':
            $result = Cloud_helper::validateNextcloudConnection($db, $conf);
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    dol_syslog("AJAX Sync error: " . $e->getMessage(), LOG_ERR);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

exit;
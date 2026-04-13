<?php
/**
 * Check if ACK file exists for lot transaction
 */

require_once("../globals.php");

if (!isset($_GET['msg_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Message ID required']));
}

$msg_id = $_GET['msg_id'];
$ack_dir = dirname(__FILE__) . '/hl7_ack_messages';

$found = false;
$ack_code = null;

if (is_dir($ack_dir)) {
    $files = scandir($ack_dir);
    foreach ($files as $file) {
        if (strpos($file, '.hl7') !== false) {
            $filepath = $ack_dir . '/' . $file;
            $content = file_get_contents($filepath);
            
            // Check if this ACK is for our message
            if (preg_match('/MSA\|([^|]+)\|' . preg_quote($msg_id) . '/', $content, $matches)) {
                $found = true;
                $ack_code = $matches[1];
                break;
            }
        }
    }
}

echo json_encode([
    'status' => $found ? 'found' : 'not_found',
    'ack_code' => $ack_code
]);
?>

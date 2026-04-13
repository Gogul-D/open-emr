<?php
/**
 * Checks for ACK file status in real-time
 */

require_once("../globals.php");

if (!isset($_GET['msg_id'])) {
    die(json_encode(['error' => 'Message ID required']));
}

$msg_id = $_GET['msg_id'];
$ack_dir = dirname(__FILE__) . '/hl7_ack_messages';

// Check if ACK file exists for this message
if (is_dir($ack_dir)) {
    $files = scandir($ack_dir);
    foreach ($files as $file) {
        if (strpos($file, '.hl7') !== false && strpos($file, $msg_id) !== false) {
            $filepath = $ack_dir . '/' . $file;
            $content = file_get_contents($filepath);
            
            // Parse ACK content
            if (preg_match('/MSA\|([^|]+)\|' . preg_quote($msg_id) . '/', $content, $matches)) {
                $ack_code = $matches[1];
                
                // Return status without deleting file yet
                echo json_encode([
                    'status' => 'found',
                    'ack_code' => $ack_code,
                    'file' => $file,
                    'timestamp' => filemtime($filepath)
                ]);
                exit;
            }
        }
    }
}

// Not found yet
echo json_encode(['status' => 'waiting']);
?>
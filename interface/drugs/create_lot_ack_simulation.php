<?php
/**
 * Creates ACK file simulation for lot operations
 */

require_once("../globals.php");

if (!isset($_GET['msg_id'])) {
    die('Message ID required');
}

$msg_id = $_GET['msg_id'];

// Generate ACK message
function generateLotACKMessage($original_msg_id, $ack_code = 'AA', $text_message = '') {
    $timestamp = date('YmdHis');
    $ack_id = 'A' . $timestamp;
    
    $ack_message = "";
    $ack_message .= "MSH|^~\\&|PYXIS|FACILITY|OPENEMR|CLINIC|{$timestamp}||ACK^R01|{$ack_id}|P|2.3\r\n";
    $ack_message .= "MSA|{$ack_code}|{$original_msg_id}";
    if ($text_message) {
        $ack_message .= "|{$text_message}";
    }
    $ack_message .= "\r\n";
    
    return $ack_message;
}

// Create ACK file
$ack_dir = dirname(__FILE__) . '/hl7_ack_messages';
if (!is_dir($ack_dir)) {
    mkdir($ack_dir, 0755, true);
}

$ack_message = generateLotACKMessage($msg_id, 'AA', 'Lot update received successfully');
$timestamp = date('YmdHis');
$filename = "lot_ack_{$msg_id}_{$timestamp}.hl7";
$filepath = $ack_dir . '/' . $filename;

$result = file_put_contents($filepath, $ack_message);
error_log("Lot ACK file creation for msg_id $msg_id: " . ($result !== false ? "Success - $filepath" : "Failed"));

echo json_encode(['status' => 'created', 'file' => $filename, 'path' => $filepath]);
?>

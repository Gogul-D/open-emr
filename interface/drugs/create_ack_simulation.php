<?php
/**
 * Creates ACK file simulation for BD Pyxis
 */

require_once("../globals.php");

if (!isset($_GET['msg_id'])) {
    die('Message ID required');
}

$msg_id = $_GET['msg_id'];

// Generate ACK message
function generateACKMessage($original_msg_id, $ack_code = 'AA', $text_message = '') {
    $timestamp = date('YmdHis');
    $ack_id = 'A' . $timestamp;
    
    $ack_message = "";
    $ack_message .= "MSH|^~\\&|PYXIS|FACILITY|OPENEMR|CLINIC|{$timestamp}||ACK^RDE|{$ack_id}|P|2.3\r\n";
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

// Determine message type based on msg_id prefix
$message_type = 'creation';
$ack_text = 'Drug creation request approved';

if (strpos($msg_id, 'U') === 0) {
    $message_type = 'update';
    $ack_text = 'Drug update request approved';
} elseif (strpos($msg_id, 'L') === 0) {
    $message_type = 'lot';
    $ack_text = 'Lot creation request approved';
} elseif (strpos($msg_id, 'D') === 0) {
    $message_type = 'creation';
    $ack_text = 'Drug creation request approved';
}

$ack_message = generateACKMessage($msg_id, 'AA', $ack_text);
$timestamp = date('YmdHis');
$filename = "ack_{$msg_id}_{$timestamp}.hl7";
$filepath = $ack_dir . '/' . $filename;

$result = file_put_contents($filepath, $ack_message);
error_log("ACK file creation for msg_id $msg_id: " . ($result !== false ? "Success - $filepath" : "Failed"));

echo json_encode(['status' => 'created', 'file' => $filename, 'path' => $filepath]);
?>
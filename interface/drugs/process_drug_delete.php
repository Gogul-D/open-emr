<?php
/**
 * Process drug deletion after ACK received from BD Pyxis
 */

require_once("../globals.php");

use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Common\Acl\AclMain;

$logger = new SystemLogger();

if (!isset($_GET['msg_id'])) {
    die('Message ID required');
}

$msg_id = $_GET['msg_id'];

// Check permissions
if (!AclMain::aclCheckCore('admin', 'super')) {
    $logger->error("Unauthorized drug deletion attempt for msg_id: $msg_id");
    die('ERROR: Access denied');
}

// Get pending transaction
$transaction = sqlQuery(
    "SELECT * FROM hl7_pending_transactions WHERE message_id = ? AND transaction_type = 'DRUG_DELETE'",
    array($msg_id)
);

$logger->info("Looking for drug delete transaction with msg_id: $msg_id");

if (!$transaction) {
    $logger->error("Drug deletion transaction not found for msg_id: $msg_id");
    // Check if any transaction exists with this msg_id
    $any_transaction = sqlQuery("SELECT * FROM hl7_pending_transactions WHERE message_id = ?", array($msg_id));
    if ($any_transaction) {
        $logger->error("Found transaction with different type: " . $any_transaction['transaction_type']);
        die('ERROR: Transaction found but wrong type: ' . $any_transaction['transaction_type']);
    }
    die('ERROR: Transaction not found');
}

$drug_id = $transaction['entity_id'];

// Debug: Log the raw transaction data
$logger->info("Raw transaction data for msg_id $msg_id: " . $transaction['transaction_data']);

// Parse transaction_data
$drug_data = json_decode($transaction['transaction_data'], true);
if (!$drug_data) {
    $logger->error("Failed to parse drug deletion transaction data for msg_id: $msg_id. Raw data: " . $transaction['transaction_data']);
    $logger->error("JSON decode error: " . json_last_error_msg());
    die('ERROR: Invalid transaction data - ' . json_last_error_msg());
}

// Perform the actual deletion
try {
    sqlStatement("DELETE FROM drug_inventory WHERE drug_id = ?", array($drug_id));
    sqlStatement("DELETE FROM drug_templates WHERE drug_id = ?", array($drug_id));
    sqlStatement("DELETE FROM drugs WHERE drug_id = ?", array($drug_id));
    sqlStatement("DELETE FROM prices WHERE pr_id = ? AND pr_selector != ''", array($drug_id));
    
    // Mark transaction as acknowledged
    sqlStatement(
        "UPDATE hl7_pending_transactions SET status = 'ACKNOWLEDGED', acknowledged_at = NOW() WHERE message_id = ?",
        array($msg_id)
    );
    
    $logger->info("Drug deletion completed for msg_id $msg_id, drug_id $drug_id");
    echo "SUCCESS|ID:$drug_id";
    
} catch (Exception $e) {
    $logger->error("Drug deletion failed for msg_id $msg_id: " . $e->getMessage());
    echo "ERROR: Database deletion failed - " . $e->getMessage();
}
?>
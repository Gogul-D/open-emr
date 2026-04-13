<?php
/**
 * Process lot update after ACK received from BD Pyxis
 */

require_once("../globals.php");

use OpenEMR\Common\Logging\SystemLogger;

$logger = new SystemLogger();

if (!isset($_GET['msg_id'])) {
    die('Message ID required');
}

$msg_id = $_GET['msg_id'];

// Get pending transaction
$transaction = sqlQuery(
    "SELECT * FROM hl7_pending_transactions WHERE message_id = ? AND transaction_type = 'LOT_UPDATE'",
    array($msg_id)
);

if (!$transaction) {
    $logger->error("Lot update transaction not found for msg_id: $msg_id");
    die('ERROR: Transaction not found');
}

// Parse entity_id to get lot_id and drug_id
$entity_parts = explode('_', $transaction['entity_id']);
$lot_id = $entity_parts[0] ?? null;
$drug_id = $entity_parts[1] ?? null;

if (!$lot_id || !$drug_id) {
    $logger->error("Invalid entity_id format for lot update: " . $transaction['entity_id']);
    die('ERROR: Invalid lot data');
}

// Parse transaction_data
$lot_data = json_decode($transaction['transaction_data'], true);
if (!$lot_data) {
    $logger->error("Failed to parse lot update transaction data for msg_id: $msg_id");
    die('ERROR: Invalid transaction data');
}

// Get old lot data for NTE message
$old_lot = sqlQuery(
    "SELECT lot_number, expiration, manufacturer FROM drug_inventory WHERE inventory_id = ?",
    array($lot_id)
);

// Update the lot
$result = sqlStatement(
    "UPDATE drug_inventory SET " .
    "lot_number = ?, " .
    "manufacturer = ?, " .
    "expiration = ?, " .
    "vendor_id = ?, " .
    "warehouse_id = ?, " .
    "on_hand = on_hand + ? " .
    "WHERE drug_id = ? AND inventory_id = ?",
    array(
        $lot_data['lot_number'],
        $lot_data['manufacturer'],
        empty($lot_data['expiration']) ? null : $lot_data['expiration'],
        $lot_data['vendor_id'],
        $lot_data['warehouse_id'],
        $lot_data['quantity'],
        $drug_id,
        $lot_id
    )
);

if ($result) {
    // Mark transaction as acknowledged
    sqlStatement(
        "UPDATE hl7_pending_transactions SET status = 'ACKNOWLEDGED', acknowledged_at = NOW() WHERE message_id = ?",
        array($msg_id)
    );
    
    $logger->info("Lot update result for msg_id $msg_id: SUCCESS for lot_id $lot_id");
    echo "SUCCESS|ID:$lot_id";
} else {
    $logger->error("Lot update failed for msg_id $msg_id");
    echo "ERROR: Database update failed";
}
?>

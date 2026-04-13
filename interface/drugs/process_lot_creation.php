<?php
/**
 * Processes lot creation after ACK received
 */

require_once("../globals.php");
require_once("drugs.inc.php");

use OpenEMR\Common\Acl\AclMain;

// Check authorization
if (!AclMain::aclCheckCore('admin', 'drugs')) {
    die('Not authorized');
}

if (!isset($_GET['msg_id'])) {
    die('Message ID required');
}

$msg_id = $_GET['msg_id'];

// Function to process pending lot transaction after ACK received
function processPendingLotTransaction($msg_id) {
    // Get pending transaction data
    $pending = sqlQuery(
        "SELECT * FROM hl7_pending_transactions WHERE message_id = ? AND status = 'PENDING'",
        array($msg_id)
    );
    
    if (!$pending) {
        error_log("No pending lot transaction found for msg_id: " . $msg_id);
        return false;
    }
    
    $lot_data = json_decode($pending['transaction_data'], true);
    $drug_id = $pending['drug_id'];
    
    if (!$lot_data || !$drug_id) {
        error_log("Failed to decode lot transaction_data for msg_id: " . $msg_id);
        return false;
    }
    
    try {
        // Now actually insert the lot into database
        // Set availability equal to on_hand when creating a new lot
        $quantity = intval($lot_data['quantity']);
        $actual_lot_id = sqlInsert(
            "INSERT INTO drug_inventory ( " .
            "drug_id, lot_number, manufacturer, expiration, " .
            "vendor_id, warehouse_id, on_hand, availability " .
            ") VALUES ( " .
            "?, ?, ?, ?, ?, ?, ?, ?)",
            array(
                $drug_id,
                $lot_data['lot_number'],
                $lot_data['manufacturer'],
                (empty($lot_data['expiration']) ? null : $lot_data['expiration']),
                $lot_data['vendor_id'],
                $lot_data['warehouse_id'],
                $quantity,
                $quantity  // Set availability equal to on_hand
            )
        );
        
        if (!$actual_lot_id) {
            error_log("Failed to insert lot for msg_id: " . $msg_id);
            return false;
        }
        
        // Mark transaction as acknowledged
        sqlStatement(
            "UPDATE hl7_pending_transactions SET status = 'ACKNOWLEDGED', acknowledged_at = NOW(), entity_id = ? WHERE message_id = ?",
            array($actual_lot_id, $msg_id)
        );
        
        // Keep ACK file for audit purposes
        error_log("Lot ACK kept for audit: msg_id $msg_id");
        
        return $actual_lot_id;
        
    } catch (Exception $e) {
        error_log("Database error in processPendingLotTransaction: " . $e->getMessage());
        return false;
    }
}

// Process the lot creation
try {
    $lot_id = processPendingLotTransaction($msg_id);
    error_log("Lot creation result for msg_id $msg_id: " . ($lot_id ? "Success - ID: $lot_id" : "Failed"));
} catch (Exception $e) {
    error_log("Error in processPendingLotTransaction: " . $e->getMessage());
    $lot_id = false;
}

// Ensure we always have a proper response
if ($lot_id === null) {
    $lot_id = false;
}

// Return simple text response for easier parsing
if ($lot_id) {
    echo "SUCCESS|ID:" . $lot_id;
} else {
    echo "ERROR|Failed to create lot";
}
?>

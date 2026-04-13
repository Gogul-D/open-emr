<?php
/**
 * Processes drug creation after ACK received
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

// Function to process pending drug transaction after ACK received
function processPendingDrugTransaction($msg_id) {
    // Get pending transaction data
    $pending = sqlQuery(
        "SELECT * FROM hl7_pending_transactions WHERE message_id = ? AND status = 'PENDING'",
        array($msg_id)
    );
    
    if (!$pending) {
        error_log("No pending transaction found for msg_id: " . $msg_id);
        return false;
    }
    
    $drug_data = json_decode($pending['transaction_data'], true);
    
    if (!$drug_data) {
        error_log("Failed to decode transaction_data for msg_id: " . $msg_id);
        return false;
    }
    
    // Ensure all required fields have default values
    $drug_data = array_merge([
        'name' => '',
        'ndc_number' => '',
        'drug_code' => '',
        'on_order' => 0,
        'reorder_point' => 0,
        'max_level' => 0,
        'form' => '',
        'size' => '',
        'unit' => '',
        'route' => '',
        'cyp_factor' => 0,
        'related_code' => '',
        'dispensable' => 1,
        'allow_multiple' => 0,
        'allow_combining' => 0,
        'active' => 1,
        'consumable' => 0,
        'medid' => '',
        'brand_name' => '',
        'dosage_form_code' => '',
        'med_class_code' => '',
        'mfr' => ''
    ], $drug_data);
    
    try {
        // Now actually insert the drug into database
        $actual_drug_id = sqlInsert(
            "INSERT INTO drugs ( " .
            "name, ndc_number, drug_code, on_order, reorder_point, max_level, form, " .
            "size, unit, route, cyp_factor, related_code, " .
            "dispensable, allow_multiple, allow_combining, active, consumable, " .
            "medid, brand_name, dosage_form_code, med_class_code, mfr " .
            ") VALUES ( " .
            "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array(
                $drug_data['name'],
                $drug_data['ndc_number'],
                $drug_data['drug_code'],
                $drug_data['on_order'],
                $drug_data['reorder_point'],
                $drug_data['max_level'],
                $drug_data['form'],
                $drug_data['size'],
                $drug_data['unit'],
                $drug_data['route'],
                $drug_data['cyp_factor'],
                $drug_data['related_code'],
                $drug_data['dispensable'],
                $drug_data['allow_multiple'],
                $drug_data['allow_combining'],
                $drug_data['active'],
                $drug_data['consumable'],
                $drug_data['medid'],
                $drug_data['brand_name'],
                $drug_data['dosage_form_code'],
                $drug_data['med_class_code'],
                $drug_data['mfr']
            )
        );
        
        if (!$actual_drug_id) {
            error_log("Failed to insert drug for msg_id: " . $msg_id);
            return false;
        }
        
        // Mark transaction as acknowledged
        sqlStatement(
            "UPDATE hl7_pending_transactions SET status = 'ACKNOWLEDGED', acknowledged_at = NOW(), entity_id = ? WHERE message_id = ?",
            array($actual_drug_id, $msg_id)
        );
        
        // Keep ACK file for audit purposes - do not delete
        // The ACK file remains in hl7_ack_messages folder for reference
        error_log("ACK file kept for audit: msg_id $msg_id");
        
        return $actual_drug_id;
        
    } catch (Exception $e) {
        error_log("Database error in processPendingDrugTransaction: " . $e->getMessage());
        return false;
    }
}

// Process the drug creation
try {
    $drug_id = processPendingDrugTransaction($msg_id);
    error_log("Drug creation result for msg_id $msg_id: " . ($drug_id ? "Success - ID: $drug_id" : "Failed"));
} catch (Exception $e) {
    error_log("Error in processPendingDrugTransaction: " . $e->getMessage());
    $drug_id = false;
}

// Ensure we always have a proper response
if ($drug_id === null) {
    $drug_id = false;
}

// Return simple text response for easier parsing
if ($drug_id) {
    echo "SUCCESS|ID:" . $drug_id;
} else {
    echo "ERROR|Failed to create drug";
}
?>
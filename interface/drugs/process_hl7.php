<?php
/**
 * Process inbound HL7 messages from Pyxis (file-based simulation)
 */

// Set ignoreAuth to bypass authentication for command line execution
$ignoreAuth = true;

// Set server variables for CLI execution
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_NAME'] = 'localhost';

require_once("../globals.php");

// Set site_id for command line execution after globals.php is loaded
$_SESSION['site_id'] = 'default';

use OpenEMR\Common\Logging\SystemLogger;
use Aranyasen\HL7\Message;

// Process inbound HL7 files
processInboundHL7Files();

function processInboundHL7Files() {
    $inbound_dir = dirname(__FILE__) . '/hl7_inbound';
    if (!is_dir($inbound_dir)) {
        echo "Inbound HL7 directory does not exist: $inbound_dir\n";
        return;
    }
    
    $files = glob($inbound_dir . '/*.hl7');
    $processed_count = 0;
    
    foreach ($files as $file) {
        $hl7_content = file_get_contents($file);
        if ($hl7_content) {
            echo "Processing file: " . basename($file) . "\n";
            processHL7Message($hl7_content);
            // Move processed file to archive
            $archive_dir = $inbound_dir . '/processed';
            if (!is_dir($archive_dir)) {
                mkdir($archive_dir, 0755, true);
            }
            $new_path = $archive_dir . '/' . basename($file);
            if (rename($file, $new_path)) {
                echo "Moved to archive: $new_path\n";
                $processed_count++;
            }
        }
    }
    
    echo "Processed $processed_count HL7 files\n";
}

function processHL7Message($hl7_content) {
    // Parse manually
    $lines = explode("\n", trim($hl7_content));
    $parsed = [];
    foreach ($lines as $line) {
        $fields = explode('|', $line);
        $parsed[] = $fields;
    }

    if (empty($parsed)) {
        echo "Invalid HL7 message\n";
        return;
    }

    // Check message type
    $msh = $parsed[0]; // MSH is first segment
    $msn9 = explode('^', $msh[8]);
    $messageType = $msn9[0] ?? '';
    $messageEvent = $msn9[1] ?? '';

    echo "Processing HL7 message type: $messageType^$messageEvent\n";

    if ($messageType === 'DFT' && $messageEvent === 'P03') {
        // Process dispense message
        processDispenseMessage($parsed);
    } else {
        echo "Message type not supported: $messageType^$messageEvent\n";
    }
}

function processDispenseMessage($parsed) {
    // Get MSH for message ID
    $msh = $parsed[0];
    $messageId = $msh[9] ?? ''; // MSH-10 Message Control ID
    
    // Find FT1 segment
    $ft1 = null;
    foreach ($parsed as $segment) {
        if ($segment[0] === 'FT1') {
            $ft1 = $segment;
            break;
        }
    }
    
    if (!$ft1) {
        echo "No FT1 segment in DFT message\n";
        return;
    }
    
    // Extract prescription ID from FT1-16 (Placer Order Number), or from message ID
    $prescriptionId = $ft1[16] ?? null;
    if (!$prescriptionId) {
        // Fallback to message ID
        if (preg_match('/PYXDFT(\d+)/', $messageId, $matches)) {
            $prescriptionId = (int) ltrim($matches[1], '0'); // Remove leading zeros and cast to int
        }
    }
    
    if (!$prescriptionId) {
        echo "Could not extract prescription ID from FT1-16 or message ID: $messageId\n";
        return;
    }
    
    // Get prescription quantity from database as fallback
    $prescriptionData = sqlQuery("SELECT quantity FROM prescriptions WHERE id = ?", array($prescriptionId));
    if ($prescriptionData) {
        $prescriptionQuantity = $prescriptionData['quantity'];
    } else {
        echo "Prescription not found for ID: $prescriptionId\n";
        $prescriptionQuantity = 0;
    }

    // Find ZPM segment (if any) and extract ZPM-16 as the dispensed amount
    $zpm = null;
    foreach ($parsed as $segment) {
        if ($segment[0] === 'ZPM') {
            $zpm = $segment;
            break;
        }
    }

    $zpmQuantity = 0;
    if ($zpm) {
        // ZPM fields are 1-indexed; array is zero-indexed with segment[0] = 'ZPM'
        $zpmQuantity = isset($zpm[16]) ? intval($zpm[16]) : 0;
    }

    // Determine drug code: prefer FT1 fields that contain TB{code}, else fallback to ZPM-7
    $drugCode = '';
    foreach ($ft1 as $f) {
        $parts = explode('^', $f);
        if (!empty($parts[0]) && preg_match('/^TB\d+$/', $parts[0])) {
            $drugCode = $parts[0];
            break;
        }
    }
    if (empty($drugCode) && $zpm && !empty($zpm[7])) {
        $drugCode = explode('^', $zpm[7])[0];
    }

    echo "Processing dispense - Prescription ID: $prescriptionId, Drug Code: $drugCode, Prescription Quantity: $prescriptionQuantity, ZPM-16 (dispensed): $zpmQuantity\n";
    
    // Find the drug by code
    $drugQuery = sqlQuery("SELECT drug_id, name FROM drugs WHERE drug_code = ?", array($drugCode));
    if (!$drugQuery) {
        echo "Drug not found for code: $drugCode\n";
        return;
    }
    $drugId = $drugQuery['drug_id'];
    $drugName = $drugQuery['name'];
    
    // Decrement inventory
    $inventoryCheck = sqlQuery("SHOW TABLES LIKE 'drug_inventory'");
    if ($inventoryCheck) {
        // Use ZPM-16 if present, otherwise fallback to prescription quantity
        $decrementQty = $zpmQuantity > 0 ? $zpmQuantity : $prescriptionQuantity;

        // Check current inventory first
        $currentInventory = sqlQuery("SELECT inventory_id, on_hand FROM drug_inventory WHERE drug_id = ? AND on_hand >= ?", 
                                     array($drugId, $decrementQty));

        if ($currentInventory) {
            // Update drug_inventory
            sqlStatement("UPDATE drug_inventory SET on_hand = on_hand - ? WHERE drug_id = ? AND inventory_id = ?", 
                        array($decrementQty, $drugId, $currentInventory['inventory_id']));
            echo "Decremented drug_inventory for drug $drugId ($drugName) by $decrementQty (was: {$currentInventory['on_hand']})\n";
        } else {
            echo "Failed to decrement inventory - insufficient stock or drug not found in inventory\n";
            echo "Drug ID: $drugId, Required quantity: $decrementQty\n";
            // Show what's actually in inventory
            $allInventory = sqlStatement("SELECT inventory_id, on_hand FROM drug_inventory WHERE drug_id = ?", array($drugId));
            echo "Current inventory records for drug $drugId:\n";
            while ($inv = sqlFetchArray($allInventory)) {
                echo "  Inventory ID: {$inv['inventory_id']}, On Hand: {$inv['on_hand']}\n";
            }
        }
    } else {
        echo "drug_inventory table not found\n";
    }
    
    // Insert drug sale
    sqlStatement("INSERT INTO drug_sales (drug_id, sale_date, quantity, fee, pid, encounter, user, inventory_id, prescription_id) 
                 VALUES (?, NOW(), ?, 0, 0, 0, 'PYXIS', 0, ?)", 
                array($drugId, $decrementQty, $prescriptionId));
    
    // Update dispensed_quantity in prescriptions table
    sqlStatement("UPDATE prescriptions SET dispensed_quantity = dispensed_quantity + ? WHERE id = ?", 
                array($decrementQty, $prescriptionId));
    
    echo "Logged dispense transaction for drug $drugId, prescription $prescriptionId\n";
}
?>
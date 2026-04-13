<?php
/**
 * Pyxis Simulator - Monitors RDE messages and generates DFT dispense messages
 * This simulates the BD Pyxis MedStation automatically dispensing medication
 */

// Set ignoreAuth for CLI execution
$ignoreAuth = true;

// Set server variables for CLI execution
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_NAME'] = 'localhost';

require_once("../globals.php");
$_SESSION['site_id'] = 'default';

echo "=== BD Pyxis MedStation Simulator ===\n";
echo "Monitoring for RDE prescription orders...\n\n";

// Monitor hl7_outbound for RDE messages
monitorAndProcessRDE();

function monitorAndProcessRDE() {
    $outbound_dir = dirname(__FILE__) . '/hl7_outbound';
    $inbound_dir = dirname(__FILE__) . '/hl7_inbound';
    
    if (!is_dir($outbound_dir)) {
        echo "Outbound directory not found: $outbound_dir\n";
        return;
    }
    
    if (!is_dir($inbound_dir)) {
        mkdir($inbound_dir, 0755, true);
    }
    
    // Get all RDE files
    $files = glob($outbound_dir . '/rde_*.hl7');
    
    if (empty($files)) {
        echo "No RDE messages found in $outbound_dir\n";
        return;
    }
    
    $processed_count = 0;
    
    foreach ($files as $file) {
        // Check if already processed (look for corresponding DFT)
        $basename = basename($file);
        $dft_file = str_replace('rde_', 'dft_', $basename);
        $dft_path = $inbound_dir . '/' . $dft_file;
        
        if (file_exists($dft_path)) {
            echo "Already processed: $basename (DFT exists)\n";
            continue;
        }
        
        // Read and parse RDE message
        $rde_content = file_get_contents($file);
        echo "Processing RDE: $basename\n";
        
        // Generate DFT dispense message
        $dft_message = generateDFTFromRDE($rde_content);
        
        if ($dft_message) {
            // Write DFT to inbound directory
            if (file_put_contents($dft_path, $dft_message)) {
                echo "✓ Generated DFT: $dft_file\n";
                echo "  Location: $dft_path\n";
                $processed_count++;
            } else {
                echo "✗ Failed to write DFT file\n";
            }
        }
        
        echo "\n";
    }
    
    echo "Processed $processed_count RDE messages\n";
    echo "\nTo decrement inventory, run: php process_hl7.php\n";
}

/**
 * Retrieve BD Pyxis container settings for a given drug
 * 
 * @param int $drug_id - The drug ID to lookup
 * @return array|null - Array with 'bd_dispense_form' and 'bd_dispense_quantity' if BD container is enabled, null otherwise
 */
function getBDDispenseValues($drug_id) {
    // Query drug_templates for Pyxis container settings
    $sql = "SELECT is_pyxis_container, bd_dispense_form, bd_dispense_quantity
            FROM drug_templates
            WHERE drug_id = ? 
            AND is_pyxis_container = 1
            LIMIT 1";
    
    try {
        $result = sqlQuery($sql, [$drug_id]);
        
        if ($result && !empty($result['bd_dispense_form']) && !empty($result['bd_dispense_quantity'])) {
            return [
                'bd_dispense_form' => $result['bd_dispense_form'],
                'bd_dispense_quantity' => $result['bd_dispense_quantity']
            ];
        }
    } catch (Exception $e) {
        echo "Error querying BD Pyxis settings: " . $e->getMessage() . "\n";
    }
    
    return null;
}

function generateDFTFromRDE($rde_content) {
    // Parse RDE message
    $lines = explode("\n", trim($rde_content));
    $parsed = [];
    foreach ($lines as $line) {
        $fields = explode('|', $line);
        if (!empty($fields[0])) {
            $parsed[$fields[0]] = $fields;
        }
    }
    
    if (!isset($parsed['MSH'])) {
        echo "Invalid RDE message - no MSH segment\n";
        return null;
    }
    
    $msh = $parsed['MSH'];
    $pid = $parsed['PID'] ?? null;
    $rxe = $parsed['RXE'] ?? null;
    
    if (!$rxe) {
        echo "No RXE segment in RDE message\n";
        return null;
    }
    
    // Extract data from RDE
    $original_msg_id = $msh[9] ?? '';
    $patient_info = $pid ? $pid : ['PID', '1', '', '1^^^OPENEMR^MR', '', 'TEST PATIENT', '', '19800901', 'M'];
    
    // RXE fields
    $drug_code_full = $rxe[2] ?? ''; // RXE-2: Give Code (drug identifier)
    $quantity = $rxe[3] ?? '1'; // RXE-3: Give Amount - Minimum
    $give_units = $rxe[4] ?? 'TAB'; // RXE-4: Give Units
    
    // Parse drug code (format: TB{drug_id}^{drug_name}^PYXIS)
    $drug_parts = explode('^', $drug_code_full);
    $drug_code = $drug_parts[0] ?? '';
    $drug_name = $drug_parts[1] ?? 'UNKNOWN DRUG';
    
    // Extract drug_id from drug code (format: TB{drug_id})
    $drug_id = null;
    if (preg_match('/TB(\d+)/', $drug_code, $matches)) {
        $drug_id = $matches[1];
    }
    
    // Check for BD Pyxis container settings
    $use_bd_dispense = false;
    $bd_dispense_form = '';
    $bd_dispense_quantity = '';
    
    if ($drug_id) {
        $bd_values = getBDDispenseValues($drug_id);
        if ($bd_values) {
            $use_bd_dispense = true;
            $bd_dispense_form = $bd_values['bd_dispense_form'];
            $bd_dispense_quantity = $bd_values['bd_dispense_quantity'];
            echo "  Using BD Pyxis container settings\n";
        }
    }
    
    // Use BD values if available, otherwise use standard RXE values
    $final_quantity = $use_bd_dispense ? $bd_dispense_quantity : $quantity;
    $final_units = $use_bd_dispense ? $bd_dispense_form : $give_units;
    
    // Generate DFT message
    $timestamp = date('YmdHis');
    $dft_msg_id = 'PYXDFT' . substr($original_msg_id, -7); // Use last 7 chars from RDE msg ID
    
    $dft_message = "";
    
    // MSH segment
    $dft_message .= "MSH|^~\\&|PYXIS|MEDES|OPENEMR|CLINIC|{$timestamp}||DFT^P03|{$dft_msg_id}|P|2.3\r\n";
    
    // PID segment (patient info)
    $dft_message .= implode('|', $patient_info) . "\r\n";
    
    // PV1 segment (patient visit)
    if (isset($parsed['PV1'])) {
        $dft_message .= implode('|', $parsed['PV1']) . "\r\n";
    } else {
        $dft_message .= "PV1|1|O|CLINIC^ROOM1|||||GONZALEZ^ANNETT\r\n";
    }
    
    // FT1 segment (financial transaction - represents the dispense)
    $transaction_date = $timestamp;
    $transaction_type = 'CG'; // Charge
    $transaction_code = $drug_code_full; // Use full drug code from RXE
    $transaction_qty = $final_quantity;
    $unit_price = '25.00'; // Default price
    
    $dft_message .= "FT1|1|{$transaction_date}|{$transaction_date}|{$transaction_type}|{$transaction_code}|{$transaction_qty}|{$final_units}|||{$unit_price}\r\n";
    
    // ZPM segment (custom - Pyxis machine info)
    $dispense_type = 'DISPENSE';
    $station_id = 'STATION01';
    $bin_id = 'BIN23';
    $lot_number = 'LOT789';
    $expiration = date('Ymd', strtotime('+1 year'));
    
    $dft_message .= "ZPM|{$dispense_type}|{$station_id}|{$bin_id}|{$lot_number}|{$expiration}|{$final_quantity}\r\n";
    
    echo "  Drug: $drug_name\n";
    echo "  Code: $drug_code\n";
    echo "  Quantity: $final_quantity $final_units\n";
    
    return $dft_message;
}
?>

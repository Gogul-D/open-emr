<?php
/**
 * ACK Message Processor for BD Pyxis Integration
 * 
 * This script monitors the hl7_ack_messages folder and processes acknowledgment messages
 * to complete pending drug and lot transactions.
 */

require_once("../globals.php");
require_once("drugs.inc.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

// Check authorization
if (!AclMain::aclCheckCore('admin', 'drugs')) {
    die('Not authorized');
}

// Function to scan for new ACK files
function scanForAckFiles() {
    $ack_dir = dirname(__FILE__) . '/hl7_ack_messages';
    if (!is_dir($ack_dir)) {
        return array();
    }
    
    $ack_files = array();
    $files = scandir($ack_dir);
    
    foreach ($files as $file) {
        if (strpos($file, '.hl7') !== false) {
            $filepath = $ack_dir . '/' . $file;
            $content = file_get_contents($filepath);
            
            // Parse MSA segment to get original message ID
            if (preg_match('/MSA\|([^|]+)\|([^|]+)/', $content, $matches)) {
                $ack_code = $matches[1];
                $original_msg_id = $matches[2];
                
                $ack_files[] = array(
                    'file' => $file,
                    'filepath' => $filepath,
                    'ack_code' => $ack_code,
                    'original_msg_id' => $original_msg_id,
                    'timestamp' => filemtime($filepath)
                );
            }
        }
    }
    
    return $ack_files;
}

// Function to process ACK message
function processAckMessage($original_msg_id, $ack_code) {
    // Get pending transaction
    $pending = sqlQuery(
        "SELECT * FROM hl7_pending_transactions WHERE message_id = ? AND status = 'PENDING'",
        array($original_msg_id)
    );
    
    if (!$pending) {
        return "No pending transaction found for message ID: {$original_msg_id}";
    }
    
    if ($ack_code == 'AA') { // Application Accept
        if ($pending['transaction_type'] == 'DRUG') {
            return processPendingDrugTransaction($original_msg_id);
        } else if ($pending['transaction_type'] == 'LOT') {
            return processPendingLotTransaction($original_msg_id);
        }
    } else {
        // Mark as failed
        sqlStatement(
            "UPDATE hl7_pending_transactions SET status = 'FAILED', acknowledged_at = NOW() WHERE message_id = ?",
            array($original_msg_id)
        );
        return "Transaction marked as FAILED due to negative ACK code: {$ack_code}";
    }
    
    return "Unknown transaction type";
}

// Function to process pending drug transaction after ACK
function processPendingDrugTransaction($msg_id) {
    $pending = sqlQuery(
        "SELECT * FROM hl7_pending_transactions WHERE message_id = ? AND status = 'PENDING'",
        array($msg_id)
    );
    
    if (!$pending) {
        return "No pending drug transaction found";
    }
    
    $drug_data = json_decode($pending['transaction_data'], true);
    
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
    
    // Mark as acknowledged
    sqlStatement(
        "UPDATE hl7_pending_transactions SET status = 'ACKNOWLEDGED', acknowledged_at = NOW(), entity_id = ? WHERE message_id = ?",
        array($actual_drug_id, $msg_id)
    );
    
    return "Drug created successfully with ID: {$actual_drug_id}";
}

// Function to process pending lot transaction after ACK
function processPendingLotTransaction($msg_id) {
    $pending = sqlQuery(
        "SELECT * FROM hl7_pending_transactions WHERE message_id = ? AND status = 'PENDING'",
        array($msg_id)
    );
    
    if (!$pending) {
        return "No pending lot transaction found";
    }
    
    $lot_data = json_decode($pending['transaction_data'], true);
    $drug_id = $pending['drug_id'];
    
    // Now actually insert the lot into database
    // Set availability equal to on_hand when creating new lot
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
    
    // Mark as acknowledged
    sqlStatement(
        "UPDATE hl7_pending_transactions SET status = 'ACKNOWLEDGED', acknowledged_at = NOW(), entity_id = ? WHERE message_id = ?",
        array($actual_lot_id, $msg_id)
    );
    
    return "Lot created successfully with ID: {$actual_lot_id}";
}

$message = '';
$processed_files = array();

// Handle manual processing
if ($_POST['action'] == 'process_ack' && $_POST['msg_id']) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
    
    $result = processAckMessage($_POST['msg_id'], $_POST['ack_code']);
    $message = $result;
}

// Handle auto-scan and process
if ($_POST['action'] == 'auto_process') {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
    
    $ack_files = scanForAckFiles();
    foreach ($ack_files as $ack_file) {
        $result = processAckMessage($ack_file['original_msg_id'], $ack_file['ack_code']);
        $processed_files[] = array(
            'file' => $ack_file['file'],
            'msg_id' => $ack_file['original_msg_id'],
            'result' => $result
        );
    }
}

// Get pending transactions
$pending_transactions = sqlStatement(
    "SELECT * FROM hl7_pending_transactions WHERE status = 'PENDING' ORDER BY created_at DESC"
);

// Get recent ACK files
$recent_acks = scanForAckFiles();
usort($recent_acks, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('ACK Message Processor - BD Pyxis Integration'); ?></title>
    <?php Header::setupHeader(['bootstrap']); ?>
</head>
<body>
    <div class="container-fluid">
        <h2><?php echo xlt('BD Pyxis ACK Message Processor'); ?></h2>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo text($message); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($processed_files)): ?>
            <div class="alert alert-success">
                <h5><?php echo xlt('Processed Files:'); ?></h5>
                <?php foreach ($processed_files as $pf): ?>
                    <p><strong><?php echo text($pf['file']); ?>:</strong> <?php echo text($pf['result']); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4><?php echo xlt('Pending Transactions'); ?></h4>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
                            <button type="submit" name="action" value="auto_process" class="btn btn-primary mb-3">
                                <?php echo xlt('Auto-Process All ACK Files'); ?>
                            </button>
                        </form>
                        
                        <?php if (sqlNumRows($pending_transactions) > 0): ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo xlt('Message ID'); ?></th>
                                        <th><?php echo xlt('Type'); ?></th>
                                        <th><?php echo xlt('Created'); ?></th>
                                        <th><?php echo xlt('Action'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($pending = sqlFetchArray($pending_transactions)): ?>
                                        <tr>
                                            <td><?php echo text($pending['message_id']); ?></td>
                                            <td><?php echo text($pending['transaction_type']); ?></td>
                                            <td><?php echo text($pending['created_at']); ?></td>
                                            <td>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
                                                    <input type="hidden" name="msg_id" value="<?php echo attr($pending['message_id']); ?>" />
                                                    <input type="hidden" name="ack_code" value="AA" />
                                                    <button type="submit" name="action" value="process_ack" class="btn btn-sm btn-success">
                                                        <?php echo xlt('Process as Success'); ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-muted"><?php echo xlt('No pending transactions'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4><?php echo xlt('Recent ACK Files'); ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_acks)): ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo xlt('File'); ?></th>
                                        <th><?php echo xlt('Original Msg ID'); ?></th>
                                        <th><?php echo xlt('ACK Code'); ?></th>
                                        <th><?php echo xlt('Time'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($recent_acks, 0, 10) as $ack): ?>
                                        <tr>
                                            <td><?php echo text($ack['file']); ?></td>
                                            <td><?php echo text($ack['original_msg_id']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $ack['ack_code'] == 'AA' ? 'success' : 'danger'; ?>">
                                                    <?php echo text($ack['ack_code']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo text(date('Y-m-d H:i:s', $ack['timestamp'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-muted"><?php echo xlt('No ACK files found'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
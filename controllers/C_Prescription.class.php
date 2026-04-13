<?php

/**
 * C_Prescription class
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Roberto Vasquez <robertogagliotta@gmail.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2015 Roberto Vasquez <robertogagliotta@gmail.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2018 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once($GLOBALS['fileroot'] . "/library/registry.inc.php");
require_once($GLOBALS['fileroot'] . "/library/amc.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Http\oeHttp;
use OpenEMR\Rx\RxList;
use PHPMailer\PHPMailer\PHPMailer;

class C_Prescription extends Controller
{
    var $template_mod;
    var $pconfig;
    var $providerid = 0;
    var $is_faxing = false;
    var $is_print_to_fax = false;
    var $RxList;
    var $prescriptions;

    function __construct($template_mod = "general")
    {
        parent::__construct();
        $this->template_mod = $template_mod;
        $this->assign("FORM_ACTION", $GLOBALS['webroot'] . "/controller.php?" . attr($_SERVER['QUERY_STRING']));
        $this->assign("TOP_ACTION", $GLOBALS['webroot'] . "/controller.php?" . "prescription" . "&");
        $this->assign("STYLE", $GLOBALS['style']);
        $this->assign("WEIGHT_LOSS_CLINIC", $GLOBALS['weight_loss_clinic']);
        $this->assign("SIMPLIFIED_PRESCRIPTIONS", $GLOBALS['simplified_prescriptions']);
        $this->pconfig = $GLOBALS['oer_config']['prescriptions'];
        $this->RxList = new RxList();
        // test if rxnorm available for lookups.
        $rxn = sqlQuery("SELECT table_name FROM information_schema.tables WHERE table_name = 'RXNCONSO' OR table_name = 'rxconso'");
        $rxcui = sqlQuery("SELECT ct_id FROM `code_types` WHERE `ct_key` = ? AND `ct_active` = 1", array('RXCUI'));
        $this->assign("RXNORMS_AVAILABLE", !empty($rxn));
        $this->assign("RXCUI_AVAILABLE", !empty($rxcui));
        // Assign the CSRF_TOKEN_FORM
        $this->assign("CSRF_TOKEN_FORM", CsrfUtils::collectCsrfToken());

        if ($GLOBALS['inhouse_pharmacy']) {
            // Make an array of drug IDs and selectors for the template.
            $drug_array_values = array(0);
            $drug_array_output = array("-- " . xl('or select from inventory') . " --");
            $drug_attributes = '';

            // $res = sqlStatement("SELECT * FROM drugs ORDER BY selector");

            // Modified to include all drugs that are either in templates OR have inventory
            // Exclude component drugs (only show packets and standalone drugs)
            $res = sqlStatement("SELECT DISTINCT d.name, d.ndc_number, d.form, d.size, " .
                "d.unit, d.route, d.substitute, d.drug_id, d.concentration_volume, d.concentration_volume_unit, d.total_volume, d.total_volume_unit, d.is_packet, " .
                "COALESCE(t.selector, CONCAT(d.name, ' (', d.drug_id, ')')) as selector, " .
                "t.dosage, t.period, t.quantity, t.refills, d.drug_code " .
                "FROM drugs AS d " .
                "LEFT JOIN drug_templates AS t ON d.drug_id = t.drug_id " .
                "LEFT JOIN drug_inventory AS i ON d.drug_id = i.drug_id " .
                "WHERE ((t.drug_id IS NOT NULL) OR (i.drug_id IS NOT NULL AND i.on_hand > 0)) " .
                "AND (d.packet_id IS NULL OR d.is_packet = 1) " .
                "ORDER BY COALESCE(t.selector, d.name)");

            while ($row = sqlFetchArray($res)) {
                $tmp_output = $row['selector'];
                if ($row['ndc_number']) {
                    $tmp_output .= ' [' . $row['ndc_number'] . ']';
                }

                $drug_array_values[] = $row['drug_id'];
                $drug_array_output[] = $tmp_output;
                if ($drug_attributes) {
                    $drug_attributes .= ',';
                }

                $drug_attributes .=    "["  .
                    js_escape($row['name'])       . ","  . //  0
                    js_escape($row['form'])       . ","  . //  1
                    js_escape($row['dosage'])     . "," . //  2
                    js_escape($row['size'])       . ","  . //  3
                    js_escape($row['unit'])       . ","   . //  4
                    js_escape($row['route'])      . ","   . //  5
                    js_escape($row['period'])     . ","   . //  6
                    js_escape($row['substitute']) . ","   . //  7
                    js_escape($row['quantity'])   . ","   . //  8
                    js_escape($row['refills'])    . ","   . //  9
                    js_escape($row['quantity'])   . ","   . //  10 quantity per_refill
                    js_escape($row['drug_code'])  . ","   . //  11 rxnorm drug code
                    js_escape($row['drug_id'])    . ","   . //  12 drug_id
                    js_escape($row['concentration_volume'] ?? '') . "," . // 13 concentration_volume
                    js_escape($row['concentration_volume_unit'] ?? '') . "," . // 14 concentration_volume_unit
                    js_escape($row['total_volume'] ?? '') . "," . // 15 total_volume
                    js_escape($row['total_volume_unit'] ?? '') . "," . // 16 total_volume_unit
                    js_escape($row['is_packet'] ?? '0') . "]";  // 17 is_packet flag
            }

            $this->assign("DRUG_ARRAY_VALUES", $drug_array_values);
            $this->assign("DRUG_ARRAY_OUTPUT", $drug_array_output);
            $this->assign("DRUG_ATTRIBUTES", $drug_attributes);
        }
    }

    function default_action()
    {
        $this->assign("prescription", $this->prescriptions[0]);
        $this->display($GLOBALS['template_dir'] . "prescription/" . $this->template_mod . "_edit.html");
    }

    function edit_action($id = "", $patient_id = "", $p_obj = null)
    {

        if ($p_obj != null && get_class($p_obj) == "prescription") {
            $this->prescriptions[0] = $p_obj;
        } elseif (empty($this->prescriptions[0]) || !is_object($this->prescriptions[0]) || (get_class($this->prescriptions[0]) != "prescription")) {
            $this->prescriptions[0] = new Prescription($id);
        }

        if (!empty($patient_id)) {
            $this->prescriptions[0]->set_patient_id($patient_id);
        }

        // When opening the edit screen, process any pending inbound DFT for this prescription
        if (empty($this->prescriptions[0]->dispensed_quantity) || $this->prescriptions[0]->dispensed_quantity == 0) {
            $this->processPendingDft($this->prescriptions[0]->id);
            // reload to pick up any updates made by processing
            $this->prescriptions[0]->populate();
        }

        $this->assign("GBL_CURRENCY_SYMBOL", $GLOBALS['gbl_currency_symbol']);

        // If quantity to dispense is not already set from a POST, set its
        // default value.
        if (! $this->getTemplateVars('DISP_QUANTITY')) {
            $this->assign('DISP_QUANTITY', $this->prescriptions[0]->quantity);
        }

        $this->default_action();
    }

    function list_action($id, $sort = "")
    {
        if (empty($id)) {
            $this->function_argument_error();
            exit;
        }

        if (!empty($sort)) {
            $prescriptions = Prescription::prescriptions_factory($id, $sort);
        } else {
            $prescriptions = Prescription::prescriptions_factory($id);
        }

        // Check inventory for each prescription
        foreach ($prescriptions as $prescription) {
            // Check total inventory across all lots
            $drugInventory = sqlQuery("SELECT SUM(on_hand) as total_on_hand FROM drug_inventory WHERE drug_id = ? AND on_hand > 0", 
                                    array($prescription->drug_id));
            $availableQty = $drugInventory ? $drugInventory['total_on_hand'] : 0;
            if ($availableQty < $prescription->quantity) {
                $prescription->insufficient_inventory = true;
            } else {
                $prescription->insufficient_inventory = false;
            }
        }

        // Before rendering, try to process any matching inbound DFT files
        // for prescriptions that do not yet have a dispensed_quantity set.
        $processed_count = 0;
        $max_per_request = 5;
        foreach ($prescriptions as $prescription) {
            if ($processed_count >= $max_per_request) break;
            if (empty($prescription->dispensed_quantity) || $prescription->dispensed_quantity == 0) {
                if ($this->processPendingDft($prescription->id)) {
                    // reload prescription from DB to reflect updates
                    $prescription->populate();
                    $processed_count++;
                }
            }
        }

        $this->assign("prescriptions", $prescriptions);

        // Collect interactions if the global is turned on
        if ($GLOBALS['rx_show_drug_drug']) {
            $interaction = "";
            // Ensure RxNorm installed
            $rxn = sqlQuery("SELECT table_name FROM information_schema.tables WHERE table_name = 'RXNCONSO' OR table_name = 'rxconso'");
            if ($rxn == false) {
                $interaction = xlt("Could not find RxNorm Table! Please install.");
            } elseif ($rxn == true) {
                //   Grab medication list from prescriptions list and load into array
                $pid = $GLOBALS['pid'];
                $medList = sqlStatement("SELECT drug FROM prescriptions WHERE active = 1 AND patient_id = ?", array($pid));
                $nameList = array();
                while ($name = sqlFetchArray($medList)) {
                    $drug = explode(" ", $name['drug']);
                    $rXn = sqlQuery("SELECT `rxcui` FROM `" . mitigateSqlTableUpperCase('RXNCONSO') . "` WHERE `str` LIKE ?", array("%" . $drug[0] . "%"));
                    $nameList[] = $rXn['rxcui'];
                }
                if (count($nameList) < 2) {
                    $interaction = xlt("Need more than one drug.");
                } else {
                    // If there are drugs to compare, collect the data
                    // (array_filter removes empty items)
                    $rxcui_list = implode("+", array_filter($nameList));
                    // Unable to urlencode the $rxcui, since this breaks the + items on call to rxnav.nlm.nih.gov; so need to include it in the path
                    $response = oeHttp::get('https://rxnav.nlm.nih.gov/REST/interaction/list.json?rxcuis=' . $rxcui_list);
                    $data = $response->body();
                    $json = json_decode($data, true);
                    if (!empty($json['fullInteractionTypeGroup'][0]['fullInteractionType'])) {
                        foreach ($json['fullInteractionTypeGroup'][0]['fullInteractionType'] as $item) {
                            $interaction .= '<div class="alert alert-danger">';
                            $interaction .= xlt('Comment') . ":" . text($item['comment'] ?? '') . "<br />";
                            $drug1 = isset($item['minConcept'][0]['name']) ? text($item['minConcept'][0]['name']) : xlt('Unknown');
                            $drug2 = isset($item['minConcept'][1]['name']) ? text($item['minConcept'][1]['name']) : xlt('Unknown');
                            $interaction .= xlt('Drug1 Name{{Drug1 Interaction}}') . ":" . $drug1 . "<br />";
                            $interaction .= xlt('Drug2 Name{{Drug2 Interaction}}') . ":" . $drug2 . "<br />";
                            $interaction .= xlt('Severity') . ":" . text($item['interactionPair'][0]['severity'] ?? '') . "<br />";
                            $interaction .= xlt('Description') . ":" . text($item['interactionPair'][0]['description'] ?? '');
                            $interaction .= '</div>';
                        }
                    } else {
                        $interaction = xlt('No interactions found');
                    }
                }
            }
            $this->assign("INTERACTION", $interaction);
        }

        // flag to indicate the CAMOS form is regsitered and active
        $this->assign("CAMOS_FORM", isRegistered("CAMOS"));

        $this->display($GLOBALS['template_dir'] . "prescription/" . $this->template_mod . "_list.html");
    }

    function block_action($id, $sort = "")
    {
        if (empty($id)) {
            $this->function_argument_error();
            exit;
        }

        if (!empty($sort)) {
            $this->assign("prescriptions", Prescription::prescriptions_factory($id, $sort));
        } else {
            $this->assign("prescriptions", Prescription::prescriptions_factory($id));
        }

        //print_r(Prescription::prescriptions_factory($id));
        $this->display($GLOBALS['template_dir'] . "prescription/" . $this->template_mod . "_block.html");
    }

    function fragment_action($id, $sort = "")
    {
        if (empty($id)) {
            $this->function_argument_error();
            exit;
        }

        if (!empty($sort)) {
            $this->assign("prescriptions", Prescription::prescriptions_factory($id, $sort));
        } else {
            $this->assign("prescriptions", Prescription::prescriptions_factory($id));
        }

        //print_r(Prescription::prescriptions_factory($id));
        $this->display($GLOBALS['template_dir'] . "prescription/" . $this->template_mod . "_fragment.html");
    }

    /**
     * Look for inbound DFT files that match a prescription id and process one if found.
     * Returns true if a file was processed.
     */
    function processPendingDft($prescription_id)
    {
        $inbound = dirname(__FILE__) . '/../interface/drugs/hl7_inbound';
        // Accept either the timestamped form (dft_prescription_{id}_*.hl7)
        // or the plain form (dft_prescription_{id}.hl7)
        $pattern1 = $inbound . '/dft_prescription_' . intval($prescription_id) . '_*.hl7';
        $pattern2 = $inbound . '/dft_prescription_' . intval($prescription_id) . '.hl7';
        error_log("DEBUG: processPendingDft looking for files with patterns: {$pattern1} and {$pattern2}");
        $files = array_merge((array)glob($pattern1), (array)glob($pattern2));
        error_log("DEBUG: processPendingDft matched files for {$prescription_id}: " . print_r($files, true));
        if (empty($files)) return false;

        // Ensure processing and processed folders exist
        $processing = $inbound . '/processing';
        if (!is_dir($processing)) mkdir($processing, 0755, true);
        $processed = $inbound . '/processed';
        if (!is_dir($processed)) mkdir($processed, 0755, true);

        foreach ($files as $file) {
            $basename = basename($file);
            $tmp = $processing . '/' . $basename . '.proc';
            // Atomically claim the file
            if (!@rename($file, $tmp)) {
                error_log("DEBUG: Failed to claim file for processing (rename returned false): $file -> $tmp. file_exists(file): " . (file_exists($file) ? 'yes' : 'no') . ", is_writable(inbound): " . (is_writable($inbound) ? 'yes' : 'no'));
                // attempt to copy as a fallback and then unlink original
                if (@copy($file, $tmp)) {
                    error_log("DEBUG: Fallback copy succeeded for $file -> $tmp");
                    @unlink($file);
                } else {
                    error_log("DEBUG: Fallback copy failed for $file -> $tmp");
                    continue;
                }
            }

            $hl7 = file_get_contents($tmp);
            if ($hl7 === false) {
                error_log("DEBUG: Failed to read claimed file: $tmp");
                // move to failed
                $failed = $inbound . '/failed';
                if (!is_dir($failed)) mkdir($failed, 0755, true);
                rename($tmp, $failed . '/' . $basename);
                continue;
            }

            // Use existing processor to handle HL7 content; pass prescription id as hint
            $this->processHL7Message($hl7, intval($prescription_id));

            // Archive processed file
            rename($tmp, $processed . '/' . $basename);
            error_log("DEBUG: Processed inbound DFT file for prescription {$prescription_id}: {$basename}");
            return true;
        }

        return false;
    }

    function lookup_action()
    {
        $this->do_lookup();
        $this->display($GLOBALS['template_dir'] . "prescription/" . $this->template_mod . "_lookup.html");
    }

    // AJAX action: process pending DFT for a given prescription id and return JSON
    function process_pending_dft_ajax_action($id = null)
    {
        header('Content-Type: application/json');

        // Basic validation
        $presc_id = intval($id ?: $_REQUEST['id'] ?? 0);
        if (!$presc_id) {
            echo json_encode(array('success' => false, 'message' => 'Missing prescription id'));
            exit;
        }

        error_log("DEBUG: AJAX process_pending_dft_ajax_action called for prescription {$presc_id} from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        // CSRF check
        if (empty($_REQUEST['csrf_token_form']) || !\OpenEMR\Common\Csrf\CsrfUtils::verifyCsrfToken($_REQUEST['csrf_token_form'])) {
            echo json_encode(array('success' => false, 'message' => 'Invalid CSRF token'));
            exit;
        }

        $processed = $this->processPendingDft($presc_id);

        if (!$processed) {
            // Additional diagnostic: list matching files (server-side view)
            $inbound = dirname(__FILE__) . '/../interface/drugs/hl7_inbound';
            $pattern1 = $inbound . '/dft_prescription_' . intval($presc_id) . '_*.hl7';
            $pattern2 = $inbound . '/dft_prescription_' . intval($presc_id) . '.hl7';
            $matches = array_merge((array)glob($pattern1), (array)glob($pattern2));
            error_log("DEBUG: No file processed for prescription {$presc_id}. Glob patterns: {$pattern1}, {$pattern2}. Matches: " . print_r($matches, true));
        }

        // Fetch current dispensed quantity
        $pres = sqlQuery("SELECT dispensed_quantity FROM prescriptions WHERE id = ?", array($presc_id));
        $dispensed = $pres ? intval($pres['dispensed_quantity']) : 0;

        echo json_encode(array('success' => true, 'processed' => (bool)$processed, 'dispensed_quantity' => $dispensed));
        exit;
    }

    function toggle_approval_action($id = null)
    {
        header('Content-Type: application/json');

        // Basic validation
        $presc_id = intval($id ?: $_REQUEST['id'] ?? 0);
        if (!$presc_id) {
            echo json_encode(array('success' => false, 'message' => 'Missing prescription id'));
            exit;
        }

        // CSRF check
        if (empty($_REQUEST['csrf_token_form']) || !\OpenEMR\Common\Csrf\CsrfUtils::verifyCsrfToken($_REQUEST['csrf_token_form'])) {
            echo json_encode(array('success' => false, 'message' => 'Invalid CSRF token'));
            exit;
        }

        // Get the new approved status from POST
        $approved = intval($_REQUEST['approved'] ?? 0);
        
        // Update the prescription approval status
        $result = sqlStatement(
            "UPDATE prescriptions SET approved = ? WHERE id = ?",
            array($approved, $presc_id)
        );

        if ($result) {
            error_log("DEBUG: Prescription {$presc_id} approval status updated to {$approved}");
            
            // If approving (setting to 1), generate HL7 message
            if ($approved == 1) {
                try {
                    // Load the prescription object
                    $prescription = new Prescription($presc_id);
                    
                    // Set patient and provider IDs
                    $prescription->set_patient_id($prescription->get_patient_id());
                    $prescription->set_provider_id($prescription->get_provider_id());
                    
                    // Generate HL7 message for approved prescription
                    $hl7_message = $this->generatePrescriptionHL7($prescription);
                    
                    // Save to outbound folder (use 'new' for first-time approval)
                    $this->savePrescriptionHL7ToOutbound($hl7_message, $presc_id, 'new');
                    
                    error_log("DEBUG: HL7 message generated and saved for approved prescription {$presc_id}");
                } catch (Exception $e) {
                    error_log("ERROR: Failed to generate HL7 for approved prescription {$presc_id}: " . $e->getMessage());
                }
            }
            
            echo json_encode(array('success' => true, 'approved' => $approved));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Failed to update approval status'));
        }
        
        exit;
    }

    function decrement_qoh_ajax_action($id = null)
    {
        header('Content-Type: application/json');

        // Basic validation
        $presc_id = intval($id ?: $_REQUEST['id'] ?? 0);
        if (!$presc_id) {
            echo json_encode(array('success' => false, 'message' => 'Missing prescription id'));
            exit;
        }

        // CSRF check
        if (empty($_REQUEST['csrf_token_form']) || !\OpenEMR\Common\Csrf\CsrfUtils::verifyCsrfToken($_REQUEST['csrf_token_form'])) {
            echo json_encode(array('success' => false, 'message' => 'Invalid CSRF token'));
            exit;
        }

        // Get lot number from POST
        $lot_number = !empty($_REQUEST['lot_number']) ? $_REQUEST['lot_number'] : null;
        if (!$lot_number) {
            echo json_encode(array('success' => false, 'message' => 'Missing lot number'));
            exit;
        }

        // Load the prescription to get drug_id and quantity
        $prescription = sqlQuery(
            "SELECT drug_id, quantity FROM prescriptions WHERE id = ?",
            array($presc_id)
        );

        if (!$prescription) {
            echo json_encode(array('success' => false, 'message' => 'Prescription not found'));
            exit;
        }

        $drug_id = intval($prescription['drug_id']);
        $quantity = floatval($prescription['quantity']);

        // Decrement on_hand (QOH) for the specific lot
        $result = sqlStatement(
            "UPDATE drug_inventory SET on_hand = CASE WHEN (on_hand - ?) < 0 THEN 0 ELSE (on_hand - ?) END WHERE drug_id = ? AND lot_number = ?",
            array($quantity, $quantity, $drug_id, $lot_number)
        );

        if ($result) {
            error_log("DEBUG: QOH decremented for drug_id: {$drug_id}, lot: {$lot_number}, quantity: {$quantity}");
            
            // Update dispensed_quantity in prescription
            sqlStatement(
                "UPDATE prescriptions SET dispensed_quantity = ? WHERE id = ?",
                array($quantity, $presc_id)
            );
            
            echo json_encode(array('success' => true, 'message' => 'QOH decremented successfully'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Failed to decrement QOH'));
        }
        
        exit;
    }



    function edit_action_process()
    {
        if ($_POST['process'] != "true") {
            return;
        }

        //print_r($_POST);

    // Stupid Smarty code treats empty values as not specified values.
    // Since active is a checkbox, represent the unchecked state as -1.
    // But for new prescriptions, default to active.
    if (empty($_POST['id'])) {
        $_POST['active'] = '1';
    } elseif (empty($_POST['active'])) {
        $_POST['active'] = '-1';
    }
        if (!empty($_POST['start_date'])) {
            $_POST['start_date'] = DateToYYYYMMDD($_POST['start_date']);
        }

        $this->prescriptions[0] = new Prescription($_POST['id']);
        parent::populate_object($this->prescriptions[0]);
        //echo $this->prescriptions[0]->toString(true);
        $this->prescriptions[0]->persist();
        
        // Decrement AVAILABILITY (not on_hand) from drug_inventory when new prescription is saved
        // QOH (on_hand) remains unchanged - only internal users' availability is decremented
        // Only decrement for new prescriptions (when $_POST['id'] is empty)
        if (empty($_POST['id'])) {
            $lotNumber = !empty($_POST['lot_number']) ? $_POST['lot_number'] : null;
            $quantity = !empty($_POST['quantity']) ? floatval($_POST['quantity']) : 0;
            $drugId = !empty($_POST['drug_id']) ? intval($_POST['drug_id']) : 0;
            
            error_log("DEBUG: Attempting inventory decrement - lotNumber: $lotNumber, quantity: $quantity, drugId: $drugId");
            
            if ($lotNumber && $quantity > 0 && $drugId > 0) {
                // First verify the lot exists
                $lotCheck = sqlQuery(
                    "SELECT availability, on_hand FROM drug_inventory WHERE drug_id = ? AND lot_number = ?",
                    array($drugId, $lotNumber)
                );
                
                if ($lotCheck) {
                    error_log("DEBUG: Lot found - current availability: " . $lotCheck['availability'] . ", on_hand: " . $lotCheck['on_hand']);
                    
                    // Decrement the availability (not on_hand) for the specific lot
                    $result = sqlStatement(
                        "UPDATE drug_inventory SET availability = CASE WHEN (availability - ?) < 0 THEN 0 ELSE (availability - ?) END WHERE drug_id = ? AND lot_number = ?",
                        array($quantity, $quantity, $drugId, $lotNumber)
                    );
                    error_log("DEBUG: Availability decremented for drug_id: $drugId, lot: $lotNumber, quantity: $quantity");
                } else {
                    error_log("WARNING: Lot not found - drug_id: $drugId, lot_number: $lotNumber");
                }
            } else {
                error_log("DEBUG: Skipping decrement - lotNumber empty or invalid values. lotNumber: '$lotNumber', quantity: $quantity, drugId: $drugId");
            }
        }
        
        // Auto-populate medication list with component drugs if this is a packet prescription
        $this->prescriptions[0]->add_packet_component_medications();
        
        // Check inventory availability before proceeding
        $lotNumber = $this->prescriptions[0]->lot_number ?? null;
        if ($lotNumber) {
            // Check specific lot inventory
            $drugInventory = sqlQuery("SELECT on_hand FROM drug_inventory WHERE drug_id = ? AND lot_number = ? AND on_hand > 0", 
                                    array($this->prescriptions[0]->drug_id, $lotNumber));
            $availableQty = $drugInventory ? $drugInventory['on_hand'] : 0;
        } else {
            // Check total inventory across all lots
            $drugInventory = sqlQuery("SELECT SUM(on_hand) as total_on_hand FROM drug_inventory WHERE drug_id = ? AND on_hand > 0", 
                                    array($this->prescriptions[0]->drug_id));
            $availableQty = $drugInventory ? $drugInventory['total_on_hand'] : 0;
        }
        
        if ($availableQty < $this->prescriptions[0]->quantity) {
            // Insufficient inventory - set flag but continue processing
            $this->prescriptions[0]->insufficient_inventory = true;
            $this->assign("HL7_STATUS_MESSAGE", "Warning: Insufficient inventory. Available: {$availableQty}, Requested: {$this->prescriptions[0]->quantity}. HL7 message generated anyway.");
            $this->assign("HL7_STATUS_TYPE", "warning");
        } else {
            $this->assign("HL7_STATUS_MESSAGE", "Prescription created successfully.");
            $this->assign("HL7_STATUS_TYPE", "success");
        }
        
        // Load patient and provider objects for HL7 generation
        $this->prescriptions[0]->set_patient_id($this->prescriptions[0]->get_patient_id());
        $this->prescriptions[0]->set_provider_id($this->prescriptions[0]->get_provider_id());
        
        // HL7 generation disabled - just show prescription ordered message
        // HL7 generation will be enabled when prescription is approved by NP provider
        
        // Set message for prescription ordered
        $this->assign("HL7_STATUS_MESSAGE", "Prescription ordered successfully.");
        $this->assign("HL7_STATUS_TYPE", "success");
        
        $_POST['process'] = "";

        $this->assign("GBL_CURRENCY_SYMBOL", $GLOBALS['gbl_currency_symbol']);

        // If the "Prescribe and Dispense" button was clicked, then
        // redisplay as in edit_action() but also replicate the fee and
        // include a piece of javascript to call dispense().
        //
        if (!empty($_POST['disp_button'])) {
            $this->assign("DISP_QUANTITY", $_POST['disp_quantity']);
            $this->assign("DISP_FEE", $_POST['disp_fee']);
            $this->assign("ENDING_JAVASCRIPT", "dispense();");
            $this->_state = false;
            return $this->edit_action($this->prescriptions[0]->id);
        }

    // Set the AMC reporting flag (to record percentage of prescriptions that
    // are set as e-prescriptions)
        if (!(empty($_POST['escribe_flag']))) {
              // add the e-prescribe flag
              processAmcCall('e_prescribe_amc', true, 'add', $this->prescriptions[0]->get_patient_id(), 'prescriptions', $this->prescriptions[0]->id);
        } else {
              // remove the e-prescribe flag
              processAmcCall('e_prescribe_amc', true, 'remove', $this->prescriptions[0]->get_patient_id(), 'prescriptions', $this->prescriptions[0]->id);
        }

    // Set the AMC reporting flag (to record prescriptions that checked drug formulary)
        if (!(empty($_POST['checked_formulary_flag']))) {
              // add the e-prescribe flag
              processAmcCall('e_prescribe_chk_formulary_amc', true, 'add', $this->prescriptions[0]->get_patient_id(), 'prescriptions', $this->prescriptions[0]->id);
        } else {
              // remove the e-prescribe flag
              processAmcCall('e_prescribe_chk_formulary_amc', true, 'remove', $this->prescriptions[0]->get_patient_id(), 'prescriptions', $this->prescriptions[0]->id);
        }

    // Set the AMC reporting flag (to record prescriptions that are controlled substances)
        if (!(empty($_POST['controlled_substance_flag']))) {
              // add the e-prescribe flag
              processAmcCall('e_prescribe_cont_subst_amc', true, 'add', $this->prescriptions[0]->get_patient_id(), 'prescriptions', $this->prescriptions[0]->id);
        } else {
              // remove the e-prescribe flag
              processAmcCall('e_prescribe_cont_subst_amc', true, 'remove', $this->prescriptions[0]->get_patient_id(), 'prescriptions', $this->prescriptions[0]->id);
        }

// TajEmo Work by CB 2012/05/29 02:58:29 PM to stop from going to send screen. Improves Work Flow
//     if ($this->prescriptions[0]->get_active() > 0) {
//       return $this->send_action($this->prescriptions[0]->id);
//     }
        $this->list_action($this->prescriptions[0]->get_patient_id());
        exit;
    }

    function send_action($id)
    {
        $_POST['process'] = "true";
        if (empty($id)) {
            $this->function_argument_error();
        }

        $rx = new Prescription($id);
        // Populate pharmacy info if the patient has a default pharmacy.
        // Probably the Prescription object should handle this instead, but
        // doing it there will require more careful research and testing.
        $prow = sqlQuery("SELECT pt.pharmacy_id FROM prescriptions AS rx, " .
            "patient_data AS pt WHERE rx.id = ? AND pt.pid = rx.patient_id", [$id]);
        if ($prow['pharmacy_id']) {
            $rx->pharmacy->set_id($prow['pharmacy_id']);
            $rx->pharmacy->populate();
        }

        $this->assign("prescription", $rx);

        $this->_state = false;
        return $this->fetch($GLOBALS['template_dir'] . "prescription/" .
            $this->template_mod . "_send.html");
    }

    function multiprintfax_header(&$pdf, $p)
    {
        return $this->multiprint_header($pdf, $p);
    }

    function multiprint_header(&$pdf, $p)
    {
        $this->providerid = $p->provider->id;
        //print header
        $pdf->ezImage($GLOBALS['oer_config']['prescriptions']['logo'], null, '50', '', 'center', '');
        $pdf->ezColumnsStart(array('num' => 2, 'gap' => 10));
        $res = sqlQuery("SELECT concat('<b>',f.name,'</b>\n',f.street,'\n',f.city,', ',f.state,' ',f.postal_code,'\nTel:',f.phone,if(f.fax != '',concat('\nFax: ',f.fax),'')) addr FROM users JOIN facility AS f ON f.name = users.facility where users.id ='" .
            add_escape_custom($p->provider->id) . "'");
        $pdf->ezText($res['addr'] ?? '', 12);
        $my_y = $pdf->y;
        $pdf->ezNewPage();
        $pdf->ezText('<b>' . $p->provider->get_name_display() . '</b>', 12);
    // A client had a bad experience with a patient misusing a DEA number, so
    // now the doctors write those in on printed prescriptions and only when
    // necessary.  If you need to change this back, then please make it a
    // configurable option.  Faxed prescriptions were not changed.  -- Rod
    // Now it is configureable. Change value in
    //     Administration->Globals->Rx
        if ($GLOBALS['rx_enable_DEA']) {
            if ($this->is_faxing || $GLOBALS['rx_show_DEA']) {
                $pdf->ezText('<b>' . xl('DEA') . ':</b>' . $p->provider->federal_drug_id, 12);
            } else {
                $pdf->ezText('<b>' . xl('DEA') . ':</b> ________________________', 12);
            }
        }

        if ($GLOBALS['rx_enable_NPI']) {
            if ($this->is_faxing || $GLOBALS['rx_show_NPI']) {
                    $pdf->ezText('<b>' . xl('NPI') . ':</b>' . $p->provider->npi, 12);
            } else {
                $pdf->ezText('<b>' . xl('NPI') . ':</b> _________________________', 12);
            }
        }

        if ($GLOBALS['rx_enable_SLN']) {
            if ($this->is_faxing || $GLOBALS['rx_show_SLN']) {
                $pdf->ezText('<b>' . xl('State Lic. #') . ':</b>' . $p->provider->state_license_number, 12);
            } else {
                $pdf->ezText('<b>' . xl('State Lic. #') . ':</b> ___________________', 12);
            }
        }

        $pdf->ezColumnsStop();
        if ($my_y < $pdf->y) {
            $pdf->ezSetY($my_y);
        }

        $pdf->ezText('', 10);
        $pdf->setLineStyle(1);
        $pdf->ezColumnsStart(array('num' => 2));
        $pdf->line($pdf->ez['leftMargin'], $pdf->y, $pdf->ez['pageWidth'] - $pdf->ez['rightMargin'], $pdf->y);
        $pdf->ezText('<b>' . xl('Patient Name & Address') . '</b>', 6);
        $pdf->ezText($p->patient->get_name_display(), 10);
        $res = sqlQuery("SELECT  concat(street,'\n',city,', ',state,' ',postal_code,'\n',if(phone_home!='',phone_home,if(phone_cell!='',phone_cell,if(phone_biz!='',phone_biz,'')))) addr from patient_data where pid =" . add_escape_custom($p->patient->id));
        $pdf->ezText($res['addr']);
        $my_y = $pdf->y;
        $pdf->ezNewPage();
        $pdf->line($pdf->ez['leftMargin'], $pdf->y, $pdf->ez['pageWidth'] - $pdf->ez['rightMargin'], $pdf->y);
        $pdf->ezText('<b>' . xl('Date of Birth') . '</b>', 6);
        $pdf->ezText($p->patient->date_of_birth, 10);
        $pdf->ezText('');
        $pdf->line($pdf->ez['leftMargin'], $pdf->y, $pdf->ez['pageWidth'] - $pdf->ez['rightMargin'], $pdf->y);
        $pdf->ezText('<b>' . xl('Medical Record #') . '</b>', 6);
        $pdf->ezText(str_pad($p->patient->get_pubpid(), 10, "0", STR_PAD_LEFT), 10);
        $pdf->ezColumnsStop();
        if ($my_y < $pdf->y) {
            $pdf->ezSetY($my_y);
        }

        $pdf->ezText('');
        $pdf->line($pdf->ez['leftMargin'], $pdf->y, $pdf->ez['pageWidth'] - $pdf->ez['rightMargin'], $pdf->y);
        $pdf->ezText('<b>' . xl('Prescriptions') . '</b>', 6);
        $pdf->ezText('', 10);
    }

    function multiprintcss_header($p)
    {
        echo("<div class='paddingdiv'>\n");
        $this->providerid = $p->provider->id;
        echo ("<table cellspacing='0' cellpadding='0' width='100%'>\n");
        echo ("<tr>\n");
        echo ("<td></td>\n");
        echo ("<td>\n");
        echo ("<img WIDTH='68pt' src='./interface/pic/" . $GLOBALS['oer_config']['prescriptions']['logo_pic'] . "' />");
        echo ("</td>\n");
        echo ("</tr>\n");
        echo ("<tr>\n");
        echo ("<td>\n");
        $res = sqlQuery("SELECT concat('<b>',f.name,'</b>\n',f.street,'\n',f.city,', ',f.state,' ',f.postal_code,'\nTel:',f.phone,if(f.fax != '',concat('\nFax: ',f.fax),'')) addr FROM users JOIN facility AS f ON f.name = users.facility where users.id ='" . add_escape_custom($p->provider->id) . "'");
        if (!empty($res)) {
            $patterns = array ('/\n/','/Tel:/','/Fax:/');
            $replace = array ('<br />', xl('Tel') . ':', xl('Fax') . ':');
            $res = preg_replace($patterns, $replace, $res);
        }

        echo ('<span class="large">' . ($res['addr'] ?? '') . '</span>');
        echo ("</td>\n");
        echo ("<td>\n");
        echo ('<b><span class="large">' .  $p->provider->get_name_display() . '</span></b>' . '<br />');

        if ($GLOBALS['rx_enable_DEA']) {
            if ($GLOBALS['rx_show_DEA']) {
                echo ('<span class="large"><b>' . xl('DEA') . ':</b>' . $p->provider->federal_drug_id . '</span><br />');
            } else {
                echo ('<b><span class="large">' . xl('DEA') . ':</span></b> ________________________<br />' );
            }
        }

        if ($GLOBALS['rx_enable_NPI']) {
            if ($GLOBALS['rx_show_NPI']) {
                echo ('<span class="large"><b>' . xl('NPI') . ':</b>' . $p->provider->npi . '</span><br />');
            } else {
                echo ('<b><span class="large">' . xl('NPI') . ':</span></b> ________________________<br />');
            }
        }

        if ($GLOBALS['rx_enable_SLN']) {
            if ($GLOBALS['rx_show_SLN']) {
                echo ('<span class="large"><b>' . xl('State Lic. #') . ':</b>' . $p->provider->state_license_number . '</span><br />');
            } else {
                echo ('<b><span class="large">' . xl('State Lic. #') . ':</span></b> ________________________<br />');
            }
        }

        echo ("</td>\n");
        echo ("</tr>\n");
        echo ("<tr>\n");
        echo ("<td rowspan='2' class='bordered'>\n");
        echo ('<b><span class="small">' . xl('Patient Name & Address') . '</span></b>' . '<br />');
        echo ($p->patient->get_name_display() . '<br />');
        $res = sqlQuery("SELECT  concat(street,'\n',city,', ',state,' ',postal_code,'\n',if(phone_home!='',phone_home,if(phone_cell!='',phone_cell,if(phone_biz!='',phone_biz,'')))) addr from patient_data where pid =" . add_escape_custom($p->patient->id));
        if (!empty($res)) {
            $patterns = array ('/\n/');
            $replace = array ('<br />');
            $res = preg_replace($patterns, $replace, $res);
        }

        echo ($res['addr']);
        echo ("</td>\n");
        echo ("<td class='bordered'>\n");
        echo ('<b><span class="small">' . xl('Date of Birth') . '</span></b>' . '<br />');
        echo ($p->patient->date_of_birth );
        echo ("</td>\n");
        echo ("</tr>\n");
        echo ("<tr>\n");
        echo ("<td class='bordered'>\n");
        echo ('<b><span class="small">' . xl('Medical Record #') . '</span></b>' . '<br />');
        echo (str_pad($p->patient->get_pubpid(), 10, "0", STR_PAD_LEFT));
        echo ("</td>\n");
        echo ("</tr>\n");
        echo ("<tr>\n");
        echo ("<td colspan='2' class='bordered'>\n");
        echo ('<b><span class="small">' . xl('Prescriptions') . '</span></b>');
        echo ("</td>\n");
        echo ("</tr>\n");
        echo ("</table>\n");
    }

    function multiprintcss_preheader()
    {
        // this sets styling and other header information of the multiprint css sheet
        echo ("<html>\n");
        echo ("<head>\n");
        echo ("<style>\n");
        echo ("div {\n");
        echo (" padding: 0;\n");
        echo (" margin: 0;\n");
        echo ("}\n");
        echo ("body {\n");
        echo (" font-family: sans-serif;\n");
        echo (" font-weight: normal;\n");
        echo (" font-size: 10pt;\n");
        echo (" background: white;\n");
        echo (" color: black;\n");
        echo ("}\n");
        echo ("span.large {\n");
        echo (" font-size: 12pt;\n");
        echo ("}\n");
        echo ("span.small {\n");
        echo (" font-size: 6pt;\n");
        echo ("}\n");
        echo ("td {\n");
        echo (" vertical-align: top;\n");
        echo (" width: 50%;\n");
        echo (" font-size: 10pt;\n");
        echo (" padding-bottom: 8pt;\n");
        echo ("}\n");
        echo ("td.bordered {\n");
        echo (" border-top:1pt solid black;\n");
        echo ("}\n");
        echo ("div.paddingdiv {\n");
        echo (" width: 524pt;\n");
        echo (" height: 668pt;\n");
        echo ("}\n");
        echo ("div.scriptdiv {\n");
        echo (" padding-top: 12pt;\n");
        echo (" padding-bottom: 22pt;\n");
        echo (" padding-left: 35pt;\n");
        echo (" border-bottom:1pt solid black;\n");
        echo ("}\n");
        echo ("div.signdiv {\n");
        echo (" margin-top: 40pt;\n");
        echo (" font-size: 12pt;\n");
        echo ("}\n");
        echo ("</style>\n");

        echo ("<title>" . xl('Prescription') . "</title>\n");
        echo ("</head>\n");
        echo ("<body>\n");
    }

    function multiprintfax_footer(&$pdf)
    {
        return $this->multiprint_footer($pdf);
    }

    function multiprint_footer(&$pdf)
    {
        if ($this->pconfig['use_signature'] && ( $this->is_faxing || $this->is_print_to_fax )) {
            $sigfile = str_replace('{userid}', $_SESSION["authUser"], $this->pconfig['signature']);
            if (file_exists($sigfile)) {
                $pdf->ezText(xl('Signature') . ": ", 12);
                // $pdf->ezImage($sigfile, "", "", "none", "left");
                $pdf->ezImage($sigfile, "", "", "none", "center");
                $pdf->ezText(xl('Date') . ": " . date('Y-m-d'), 12);
                if ($this->is_print_to_fax) {
                    $pdf->ezText(xl('Please do not accept this prescription unless it was received via facsimile.'));
                }

                $addenumFile = $this->pconfig['addendum_file'];
                if (file_exists($addenumFile)) {
                    $pdf->ezText('');
                    $f = fopen($addenumFile, "r");
                    while ($line = fgets($f, 1000)) {
                        $pdf->ezText(rtrim($line));
                    }
                }

                return;
            }
        }

        $pdf->ezText("\n\n\n\n" . xl('Signature') . ":________________________________\n" . xl('Date') . ": " . date('Y-m-d'), 12);
    }

    function multiprintcss_footer()
    {
        echo ("<div class='signdiv'>\n");
        echo (xl('Signature') . ":________________________________<br />");
        echo (xl('Date') . ": " . date('Y-m-d'));
        echo ("</div>\n");
        echo ("</div>\n");
    }

    function multiprintcss_postfooter()
    {
        echo("<script>\n");
        echo("opener.top.printLogPrint(window);\n");
        echo("</script>\n");
        echo("</body>\n");
        echo("</html>\n");
    }

    function get_prescription_body_text($p)
    {
        $body = xlt('Rx') . ': ' . text($p->get_drug()) . ' ' . text($p->get_size()) . ' ' . text($p->get_unit_display());
        if ($p->get_form()) {
            $body .= ' [' . text($p->form_array[$p->get_form()]) . "]";
        }

        $body .= "     " .
            text($p->substitute_array[$p->get_substitute()]) . "\n" .
            xlt('Disp #') . ': ' . text($p->get_quantity()) . "\n" .
            xlt('Sig') . ': ' . text($p->get_dosage() ?? '') . ' ' . text($p->form_array[$p->get_form()] ?? '') . ' ' .
            text($p->route_array[$p->get_route()] ?? '') . ' ' . text($p->interval_array[$p->get_interval()] ?? '') . "\n";
        
        // Add concentration and total volume
        // if ($p->get_concentration_volume()) {
        //     $body .= xlt('Concentration Volume') . ': ' . text($p->get_concentration_volume()) . ' ' . text($p->unit_array[$p->get_concentration_volume_unit()] ?? '') . "\n";
        // }
        // if ($p->get_total_volume()) {
        //     $body .= xlt('Total Volume') . ': ' . text($p->get_total_volume()) . ' ' . text($p->unit_array[$p->get_total_volume_unit()] ?? '') . "\n";
        // }
        
        // if ($p->get_refills() > 0) {
        //     $body .= "\n" . xlt('Refills') . ": " .  text($p->get_refills());
        //     if ($p->get_per_refill()) {
        //         $body .= " " . xlt('of quantity') . " " . text($p->get_per_refill());
        //     }

        //     $body .= "\n";
        // } else {
        //     $body .= "\n" . xlt('Refills') . ": 0 (" . xlt('Zero') . ")\n";
        // }

        // $note = $p->get_note();
        // if ($note != '') {
        //     $body .= "\n" . text($note) . "\n";
        // }

        return $body;
    }

    function multiprintfax_body(&$pdf, $p)
    {
        return $this->multiprint_body($pdf, $p);
    }

    function multiprint_body(&$pdf, $p)
    {
        $pdf->ez['leftMargin'] += $pdf->ez['leftMargin'];
        $pdf->ez['rightMargin'] += $pdf->ez['rightMargin'];
        $d = $this->get_prescription_body_text($p);
        if ($pdf->ezText($d, 10, array(), 1)) {
            $pdf->ez['leftMargin'] -= $pdf->ez['leftMargin'];
            $pdf->ez['rightMargin'] -= $pdf->ez['rightMargin'];
            $this->multiprint_footer($pdf);
            $pdf->ezNewPage();
            $this->multiprint_header($pdf, $p);
            $pdf->ez['leftMargin'] += $pdf->ez['leftMargin'];
            $pdf->ez['rightMargin'] += $pdf->ez['rightMargin'];
        }

        $my_y = $pdf->y;
        $pdf->ezText($d, 10);
        if ($this->pconfig['shading']) {
            $pdf->setColor(.9, .9, .9);
            $pdf->filledRectangle($pdf->ez['leftMargin'], $pdf->y, $pdf->ez['pageWidth'] - $pdf->ez['rightMargin'] - $pdf->ez['leftMargin'], $my_y - $pdf->y);
            $pdf->setColor(0, 0, 0);
        }

        $pdf->ezSetY($my_y);
        $pdf->ezText($d, 10);
        $pdf->ez['leftMargin'] = $GLOBALS['rx_left_margin'];
        $pdf->ez['rightMargin'] = $GLOBALS['rx_right_margin'];
        $pdf->ezText('');
        $pdf->line($pdf->ez['leftMargin'], $pdf->y, $pdf->ez['pageWidth'] - $pdf->ez['rightMargin'], $pdf->y);
        $pdf->ezText('');
    }

    function multiprintcss_body($p)
    {
        // Remove separate prescription text, put all in drug inventory table
        $d = $this->get_prescription_body_text($p);
        $patterns = array ('/\n/','/     /');
        $replace = array ('<br />','&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');
        $d = preg_replace($patterns, $replace, $d);
        echo ("<div class='scriptdiv'>\n" . $d . "</div>\n");

        // Always show Drug Inventory labels with data
        // echo "<div class='scriptdiv' style='margin-top:20px;'>";
        // echo "<b>Drug Inventory</b><br>";
        // echo "<table border='1' cellpadding='4' cellspacing='0' style='border-collapse:collapse;'>";
        // echo "<tr><th>Rx</th><td colspan='3'>" . text($p->get_drug()) . " " . text($p->get_size()) . " " . text($p->get_unit_display());
        // if ($p->get_form()) {
        //     echo " [" . text($p->form_array[$p->get_form()]) . "]";
        // }
        // echo " " . text($p->substitute_array[$p->get_substitute()]) . "</td></tr>";
        // echo "<tr><th>Disp #</th><td>" . text($p->get_quantity()) . "</td><th>Sig</th><td>" . text($p->get_dosage() ?? '') . " " . text($p->form_array[$p->get_form()] ?? '') . " " . text($p->route_array[$p->get_route()] ?? '') . " " . text($p->interval_array[$p->get_interval()] ?? '') . "</td></tr>";
        // echo "<tr><th>Dose</th><th>Units</th><th>Concentration Volume</th><th>Concentration Volume Unit</th></tr>";
        // echo "<tr><td>" . text($p->get_size()) . "</td><td>" . text($p->get_unit_display()) . "</td><td>" . text($p->get_concentration_volume()) . "</td><td>" . text($p->unit_array[$p->get_concentration_volume_unit()] ?? '') . "</td></tr>";
        // echo "<tr><th>Total Volume</th><th>Total Volume Unit</th><th>Directions</th><th>Form</th></tr>";
        // echo "<tr><td>" . text($p->get_total_volume()) . "</td><td>" . text($p->unit_array[$p->get_total_volume_unit()] ?? '') . "</td><td>" . text($p->get_dosage() ?? '') . "</td><td>" . text($p->form_array[$p->get_form()] ?? '') . "</td></tr>";
        // echo "<tr><th>Route</th><th>Frequency</th><th>Refills</th><th>Quantity</th></tr>";
        // echo "<tr><td>" . text($p->route_array[$p->get_route()] ?? '') . "</td><td>" . text($p->interval_array[$p->get_interval()] ?? '') . "</td><td>" . text($p->get_refills()) . "</td><td>" . text($p->get_quantity()) . "</td></tr>";
        // echo "<tr><th colspan='4'>Notes</th></tr>";
        // echo "<tr><td colspan='4'>" . text($p->get_note()) . "</td></tr>";
        // echo "<tr><th>Add to Medication List</th><th>Substitution (External Use)</th><th>Active</th><th>&nbsp;</th></tr>";
        // echo "<tr><td>" . ($p->get_active() > 0 ? 'Yes' : 'No') . "</td><td>" . text($p->substitute_array[$p->get_substitute()]) . "</td><td>&nbsp;</td><td>&nbsp;</td></tr>";
        // echo "</table>";
        // echo "</div>\n";
    }

    function multiprintfax_action($id = "")
    {
        $this->is_print_to_fax = true;
        return $this->multiprint_action($id);
    }

    function multiprint_action($id = "")
    {
        $_POST['process'] = "true";
        if (empty($id)) {
            $this->function_argument_error();
        }

        $pdf = new Cezpdf($GLOBALS['rx_paper_size']);
        $pdf->ezSetMargins($GLOBALS['rx_top_margin'], $GLOBALS['rx_bottom_margin'], $GLOBALS['rx_left_margin'], $GLOBALS['rx_right_margin']);
        $pdf->selectFont('Helvetica');

        // $print_header = true;
        $on_this_page = 0;

        //print prescriptions body
        $this->_state = false; // Added by Rod - see Controller.class.php
        $ids = preg_split('/::/', substr($id, 1, strlen($id) - 2), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($ids as $id) {
            $p = new Prescription($id);
            // if ($print_header == true) {
            if ($on_this_page == 0) {
                $this->multiprint_header($pdf, $p);
            }

            if (++$on_this_page > 3 || $p->provider->id != $this->providerid) {
                $this->multiprint_footer($pdf);
                $pdf->ezNewPage();
                $this->multiprint_header($pdf, $p);
                // $print_header = false;
                $on_this_page = 1;
            }

            $this->multiprint_body($pdf, $p);
        }

        $this->multiprint_footer($pdf);

        $pFirstName = $p->patient->fname; //modified by epsdky for prescription filename change to include patient name and ID
        $pFName = convert_safe_file_dir_name($pFirstName);
        $modedFileName = "Rx_{$pFName}_{$p->patient->id}.pdf";
        $pdf->ezStream(array('Content-Disposition' => $modedFileName));
        return;
    }

    function multiprintcss_action($id = "")
    {
        $_POST['process'] = "true";
        if (empty($id)) {
            $this->function_argument_error();
        }

        $this->multiprintcss_preheader();

        $this->_state = false; // Added by Rod - see Controller.class.php
        $ids = preg_split('/::/', substr($id, 1, strlen($id) - 2), -1, PREG_SPLIT_NO_EMPTY);

        $on_this_page = 0;
        foreach ($ids as $id) {
            $p = new Prescription($id);
            if ($on_this_page == 0) {
                $this->multiprintcss_header($p);
            }

            if (++$on_this_page > 3 || $p->provider->id != $this->providerid) {
                $this->multiprintcss_footer();
                $this->multiprintcss_header($p);
                $on_this_page = 1;
            }

            $this->multiprintcss_body($p);
        }

        $this->multiprintcss_footer();
        $this->multiprintcss_postfooter();
        return;
    }

    function send_action_process($id)
    {
        $dummy = ""; // Added by Rod to avoid run-time warnings
        if ($_POST['process'] != "true") {
            return;
        }

        if (empty($id)) {
            $this->function_argument_error();
        }

        $p = new Prescription($id);
        switch ($_POST['submit']) {
            case (xl("Print") . " (" . xl("PDF") . ")"):
                // The following statement added by Rod.
                // Looking at Controller.class.php, it appears that _state is set to false
                // to indicate that no further HTML is to be generated.
                $this->_state = false; // Added by Rod - see Controller.class.php
                return $this->print_prescription($p, $dummy);
                break;
            case (xl("Print") . " (" . xl("HTML") . ")"):
                                $this->_state = false;
                return $this->print_prescription_css($p, $dummy);
                        break;
            case xl("Print To Fax"):
                $this->_state = false;
                $this->is_print_to_fax = true;
                return $this->print_prescription($p, $dummy);
                break;
            case xl("Email"):
                return $this->email_prescription($p, $_POST['email_to']);
                break;
            case xl("Fax"):
                //this is intended to be the hook for the hylafax code we already have that hasn't worked its way into the tree yet.
                //$this->assign("process_result","No fax server is currently setup.");
                return $this->fax_prescription($p, $_POST['fax_to']);
                break;
            case xl("Auto Send"):
                $pharmacy_id = $_POST['pharmacy_id'];
                //echo "auto sending to : " . $_POST['pharmacy_id'];
                $phar = new Pharmacy($_POST['pharmacy_id']);
                //print_r($phar);
                if ($phar->get_transmit_method() == TRANSMIT_PRINT) {
                    return $this->print_prescription($p, $dummy);
                } elseif ($phar->get_transmit_method() == TRANSMIT_EMAIL) {
                    $email = $phar->get_email();
                    if (!empty($email)) {
                        return $this->email_prescription($p, $phar->get_email());
                    }

                    //else print it
                } elseif ($phar->get_transmit_method() == TRANSMIT_FAX) {
                    $faxNum = $phar->get_fax();
                    if (!empty($faxNum)) {
                        return $this->fax_prescription($p, $faxNum);
                    }

                    // return $this->assign("process_result","No fax server is currently setup.");
                    // else default is printing,
                } else {
                    //the pharmacy has no default or default is print
                    return $this->print_prescription($p, $dummy);
                }
                break;
        }

        return;
    }

    function print_prescription($p, &$toFile)
    {
        $pdf = new Cezpdf($GLOBALS['rx_paper_size']);
        $pdf->ezSetMargins($GLOBALS['rx_top_margin'], $GLOBALS['rx_bottom_margin'], $GLOBALS['rx_left_margin'], $GLOBALS['rx_right_margin']);

        $pdf->selectFont('Helvetica');

        // Signature images are to be used only when faxing.
        if (!empty($toFile)) {
            $this->is_faxing = true;
        }

        $this->multiprint_header($pdf, $p);
        $this->multiprint_body($pdf, $p);
        $this->multiprint_footer($pdf);

        if (!empty($toFile)) {
            $toFile = $pdf->ezOutput();
        } else {
            $pdf->ezStream();
            // $pdf->ezStream(array('compress' => 0)); // for testing with uncompressed output
        }

        return;
    }

    function print_prescription_css($p, &$toFile)
    {

        $this->multiprintcss_preheader();
        $this->multiprintcss_header($p);
        $this->multiprintcss_body($p);
        $this->multiprintcss_footer();
        $this->multiprintcss_postfooter();
    }

    function print_prescription_old($p, &$toFile)
    {
        $pdf = new Cezpdf($GLOBALS['rx_paper_size']);
        $pdf->ezSetMargins($GLOBALS['rx_top_margin'], $GLOBALS['rx_bottom_margin'], $GLOBALS['rx_left_margin'], $GLOBALS['rx_right_margin']);
        $pdf->selectFont('Helvetica');
        if (!empty($this->pconfig['logo'])) {
            $pdf->ezImage($this->pconfig['logo'], "", "", "none", "left");
        }

        $pdf->ezText($p->get_prescription_display(), 10);
        if ($this->pconfig['use_signature']) {
            $pdf->ezImage($this->pconfig['signature'], "", "", "none", "left");
        } else {
            $pdf->ezText("\n\n\n\nSignature:________________________________", 10);
        }

        if (!empty($toFile)) {
            $toFile = $pdf->ezOutput();
        } else {
            $pdf->ezStream();
            // $pdf->ezStream(array('compress' => 0)); // for testing with uncompressed output
        }

        return;
    }

    function email_prescription($p, $email)
    {
        if (empty($email)) {
            $this->assign("process_result", "Email could not be sent, the address supplied: '$email' was empty or invalid.");
            return;
        }

        $mail = new PHPMailer();
        //this is a temporary config item until the rest of the per practice billing settings make their way in
        $mail->From = $GLOBALS['practice_return_email_path'];
        $mail->FromName = $p->provider->get_name_display();
        $mail->isMail();
        $mail->Host     = "localhost";
        $mail->Mailer   = "mail";
        $text_body  = $p->get_prescription_display();
        $mail->Body = $text_body;
        $mail->Subject = "Prescription for: " . $p->patient->get_name_display();
        $mail->AddAddress($email);
        if ($mail->Send()) {
            $this->assign("process_result", "Email was successfully sent to: " . $email);
            return;
        } else {
            $this->assign("process_result", "There has been a mail error sending to " . $_POST['email_to'] . " " . $mail->ErrorInfo);
            return;
        }
    }

    function do_lookup()
    {
        if ($_POST['process'] != "true") {
                    // don't do a lookup
            $this->assign("drug", $_GET['drug']);
                    return;
        }

        // process the lookup
        $this->assign("drug", $_POST['drug']);
        $list = array();
        if (!empty($_POST['drug'])) {
            $list = $this->RxList->getList($_POST['drug']);
        }

        if (is_array($list)) {
            $list = array_flip($list);
            $this->assign("drug_options", $list);
            $this->assign("drug_values", array_keys($list));
        } else {
            $this->assign("NO_RESULTS", xl("No results found for") . ": " . $_POST['drug']);
        }

        //print_r($_POST);
        //$this->assign("PROCESS","");

        $_POST['process'] = "";
    }

    function fax_prescription($p, $faxNum)
    {
        $err = "Sent fax";
        //strip - ,(, ), and ws
        $faxNum = preg_replace("/(-*)(\(*)(\)*)(\s*)/", "", $faxNum);
        //validate the number

        if (!empty($faxNum) && is_numeric($faxNum)) {
            //get the sendfax command and execute it
            $cmd = $this->pconfig['sendfax'];
            // prepend any prefix to the fax number
            $pref = $this->pconfig['prefix'];
            $faxNum = $pref . $faxNum;
            if (empty($cmd)) {
                $err .= " Send fax not set in includes/config.php";
            } else {
                //generate file to fax
                $faxFile = "Failed";
                $this->print_prescription($p, $faxFile);
                if (empty($faxFile)) {
                    $err .= " print_prescription returned empty file";
                }

                $fileName = $GLOBALS['OE_SITE_DIR'] . "/documents/" . $p->get_id() .
                $p->get_patient_id() . "_fax_.pdf";
                //print "filename is $fileName";
                touch($fileName); // php bug
                $handle = fopen($fileName, "w");
                if (!$handle) {
                    $err .= " Failed to open file $fileName to write fax to";
                }

                if (fwrite($handle, $faxFile) === false) {
                    $err .= " Failed to write data to $fileName";
                }

                fclose($handle);
                $args = " -n -d $faxNum $fileName";
                //print "command is $cmd $args<br />";
                exec($cmd . $args);
            }
        } else {
            $err = "bad fax number passed to function";
        }

        if ($err) {
            $this->assign("process_result", $err);
        }
    }

    // Function to generate HL7 ADT^A04 message for drug addition
    function generatePrescriptionHL7($prescription) {
        $timestamp = date('YmdHis');
        $msgId = $timestamp; // Unique ID like timestamp

        // Get facility name (one active facility)
        $fac_name = sqlQuery("SELECT name FROM facility WHERE inactive = 0 LIMIT 1")['name'] ?? 'TB CLINIC';

        // Determine clinic name based on facility
        $clinic_name = ($fac_name == "El Paso Department of Public Health Respiratory Disease Clinic") ? "TB Clinic" : "STD Clinic";

        // Get provider NPI
        $provider_id = $prescription->get_provider_id();
        $npi = sqlQuery("SELECT npi FROM users WHERE id = ?", array($provider_id))['npi'] ?? '';

        // Check if this is a packet prescription
        $prescriptionDrug = sqlQuery("SELECT is_packet, drug_id FROM drugs WHERE drug_id = ?", 
                                    array($prescription->get_drug_id()));
        $isPacPrescription = $prescriptionDrug && $prescriptionDrug['is_packet'];

        // Get dosage form from prescription
        $dosage_form = $prescription->get_form() ?? 'TAB';

        // Get frequency from prescription (lookup text from list_options)
        $frequency_row = sqlQuery("SELECT title FROM list_options WHERE list_id = 'drug_interval' AND option_id = ?", array($prescription->interval));
        $frequency = $frequency_row['title'] ?? '';

        // Patient info
        $patient = $prescription->patient;
        $patientData = sqlQuery("SELECT * FROM patient_data WHERE pid = ?", array($patient->get_id()));
        $patientName = strtoupper($patientData['lname'] . '^' . $patientData['fname']);
        if (!empty($patientData['mname'])) {
            $patientName .= '^' . strtoupper($patientData['mname']);
        }
        $patientDob = date('Ymd', strtotime($patientData['DOB']));
        $patientGender = strtoupper($patientData['sex']);
        if ($patientGender == 'MALE') $patientGender = 'M';
        elseif ($patientGender == 'FEMALE') $patientGender = 'F';
        else $patientGender = 'U';
        $patientMrn = $patient->get_id();
        $pid = $patient->get_id();

        // Address
        $addressLine = strtoupper(str_replace(',', '', $patientData['street'])) . '^^';
        if (!empty($patientData['city'])) {
            $cityResult = sqlQuery("SELECT title FROM list_options WHERE list_id = 'Cities' AND option_id = ?", array($patientData['city']));
            $city = strtoupper($cityResult['title'] ?? $patientData['city']);
            $addressLine .= $city;
        }
        $addressLine .= '^';
        if (!empty($patientData['state'])) {
            $stateResult = sqlQuery("SELECT title FROM list_options WHERE list_id = 'state' AND option_id = ?", array($patientData['state']));
            $state = strtoupper($stateResult['title'] ?? $patientData['state']);
            $addressLine .= $state;
        } else {
            $addressLine .= 'VA';
        }
        $addressLine .= '^';
        if (!empty($patientData['postal_code'])) {
            $addressLine .= $patientData['postal_code'];
        }

        // Phone
        $phoneNumber = '';
        if (!empty($patientData['phone_home'])) {
            $phone = preg_replace('/\D/', '', $patientData['phone_home']);
            if (strlen($phone) >= 10) {
                $phoneNumber = '(' . substr($phone, 0, 3) . ')' . substr($phone, 3, 3) . '-' . substr($phone, 6);
            }
        }

        // Race and ethnicity
        $race = strtoupper($patientData['race'] ?? '');
        $ethnicity_raw = $patientData['ethnicity'] ?? '';
        if (strtolower($ethnicity_raw) == 'hisp_or_latin') {
            $ethnicity = 'HISPANIC';
        } else {
            $ethnicity = strtoupper($ethnicity_raw);
        }

        // Language, marital status, religion
        $language = strtoupper($patientData['language'] ?? '');
        $marital = strtoupper($patientData['status'] ?? '');
        $religion = strtoupper($patientData['religion'] ?? '');

        // Get last encounter ID as patient account number
        $encounterResult = sqlQuery("SELECT encounter FROM form_encounter WHERE pid = ? ORDER BY date DESC LIMIT 1", array($patient->get_id()));
        $accountNumber = $encounterResult['encounter'] ?? '';

        // Provider info
        $providerId = sqlQuery("SELECT provider_id FROM prescriptions WHERE id = ?", array($prescription->get_id()))['provider_id'] ?? null;
        if ($providerId) {
            $providerData = sqlQuery("SELECT fname, lname FROM users WHERE id = ?", array($providerId));
            $providerName = ($npi ? $npi : '') . '^' . strtoupper($providerData['lname'] . '^' . $providerData['fname']);
        } else {
            $providerName = '';
        }

        // Drug info
        $drugInfo = sqlQuery("SELECT name, drug_code, medid, form, unit FROM drugs WHERE drug_id = ?", array($prescription->get_drug_id()));
        $drugName = strtoupper($drugInfo['name']);
        $drugCode = $drugInfo['medid']; // Use medid as code
        $simpleDrugName = $drugName;

        // Prescription note for administration instructions
        $prescriptionNote = sqlQuery("SELECT note FROM prescriptions WHERE id = ?", array($prescription->get_id()))['note'] ?? '';

        // Get dosage (directions) from prescriptions table
        $dosage = sqlQuery("SELECT dosage FROM prescriptions WHERE id = ?", array($prescription->get_id()))['dosage'] ?? '';

        // Get drug strength from prescriptions table
        $drugStrength = sqlQuery("SELECT size FROM prescriptions WHERE id = ?", array($prescription->get_id()))['size'] ?? '';

        // Get route from prescriptions table and map to HL7 code
        $routeResult = sqlQuery("SELECT route FROM prescriptions WHERE id = ?", array($prescription->get_id()));
        $route_id = $routeResult['route'] ?? '';
        $route_display = '';
        $route_code = '';
        
        if (!empty($route_id)) {
            $routeOption = sqlQuery("SELECT title FROM list_options WHERE list_id = 'drug_route' AND option_id = ?", array($route_id));
            $route_display = $routeOption['title'] ?? '';
            
            // Map route display to HL7 code
            $route_map = [
                'Per Oris' => 'PO',
                'PO' => 'PO',
                'By Mouth' => 'PO',
                'bymouth' => 'PO',
                'Per Rectum' => 'PR',
                'PR' => 'PR',
                'To Skin' => 'TOP',
                'TOP' => 'TOP',
                'To Affected Area' => 'TOP',
                'Sublingual' => 'SL',
                'SL' => 'SL',
                'OS' => 'OP',
                'OD' => 'OP',
                'OU' => 'OP',
                'SQ' => 'SC',
                'IM' => 'IM',
                'IV' => 'IV',
                'Per Nostril' => 'NASAL',
                'NASAL' => 'NASAL',
                'Both Ears' => 'OTIC',
                'Left Ear' => 'OTIC',
                'Right Ear' => 'OTIC',
                'OTIC' => 'OTIC',
                'Inhale' => 'INH',
                'inhale' => 'INH',
                'INH' => 'INH',
                'Intradermal' => 'ID',
                'intradermal' => 'ID',
                'ID' => 'ID',
                'Transdermal' => 'TD',
                'transdermal' => 'TD',
                'TD' => 'TD',
                'Intramuscular' => 'IM',
                'intramuscular' => 'IM',
                'Intravenous' => 'IV',
                'Subcutaneous' => 'SC',
                'SC' => 'SC',
                'Ophthalmic' => 'OP',
                'OP' => 'OP',
                'Intranasal' => 'NASAL',
            ];
            
            $route_code = $route_map[$route_display] ?? $route_display;
        }

        $quantity = $prescription->quantity ?: 1;
        $refills = $prescription->refills ?: 0;

        // Dosage form and units from drug table, resolved to titles, then mapped to HL7 codes
        $dosage_form_id = $drugInfo['form'] ?? '';
        $units_id = $drugInfo['unit'] ?? '';
        $dosage_form_result = $dosage_form_id ? sqlQuery("SELECT title FROM list_options WHERE list_id = 'drug_form' AND option_id = ?", array($dosage_form_id)) : null;
        $dosage_form_title = strtolower($dosage_form_result['title'] ?? '');
        $units_result = $units_id ? sqlQuery("SELECT title FROM list_options WHERE list_id = 'drug_units' AND option_id = ?", array($units_id)) : null;
        $units_title = strtolower($units_result['title'] ?? '');

        // Map to HL7 short codes
        $units_map = [
            'milligram' => 'MG',
            'mg' => 'MG',
            'gram' => 'G',
            'g' => 'G',
            'milliliter' => 'ML',
            'ml' => 'ML',
            'unit' => 'UNT',
            'tablet' => 'TAB',
            'capsule' => 'CAP',
            'puff' => 'PUFF'
        ];
        $form_map = [
            'tablet' => 'TAB',
            'capsule' => 'CAP',
            'syrup' => 'SYR',
            'suspension' => 'SUSP',
            'injection' => 'INJ',
            'solution' => 'SOL',
            'cream' => 'CRM',
            'ointment' => 'OINT',
            'patch' => 'PATCH'
        ];
        $units = $units_map[$units_title] ?? strtoupper($units_title);
        $dosage_form = $form_map[$dosage_form_title] ?? strtoupper($dosage_form_title);

        // Timing
        $intervalResult = sqlQuery("SELECT title FROM list_options WHERE list_id = 'drug_interval' AND option_id = ?", array($prescription->interval));
        $pyxisTiming = strtoupper(str_replace('.', '', $intervalResult['title'] ?? ''));

        $frequency = $pyxisTiming;

        // Generate order ID
        $orderId = 'ORD' . str_pad($prescription->get_id(), 10, '0', STR_PAD_LEFT);

        $refills_display = $refills ? $refills : '';

        // Check if this is a packet drug with BD Dispense values
        // Packets have BD Dispense Form and Quantity stored in drug_templates
        $use_bd_dispense = false;
        $bd_dispense_form = $dosage_form;
        $bd_dispense_quantity = $quantity;
        
        // Get the drug name to match against drug_templates selector
        $drug_name = trim($prescription->drug);
        
        // For packets and BD Pyxis containers, fetch BD values from drug_templates
        // The selector for a packet would typically be the drug name or the prescription selector
        $bd_values = sqlQuery(
            "SELECT bd_dispense_form, bd_dispense_quantity
             FROM drug_templates
             WHERE drug_id = ? AND is_pyxis_container = 1
             ORDER BY selector LIMIT 1",
            array($prescription->get_drug_id())
        );
        
        if ($bd_values && !empty($bd_values['bd_dispense_form'])) {
            // Use BD values for packets - only populate from drug_templates if they exist
            $use_bd_dispense = true;
            $bd_dispense_form = $bd_values['bd_dispense_form'];
            $bd_dispense_quantity = !empty($bd_values['bd_dispense_quantity']) ? $bd_values['bd_dispense_quantity'] : $quantity;
        }
        
        // Use BD values if available, otherwise use standard values
        $final_form = $use_bd_dispense ? $bd_dispense_form : $dosage_form;
        $final_quantity = $use_bd_dispense ? $bd_dispense_quantity : $quantity;

        // Fetch patient allergies
        $allergies = array();
        $res_allergies = sqlStatement("SELECT title, reaction, severity_al, begdate FROM lists WHERE type='allergy' AND activity=1 AND pid=? AND (enddate IS NULL OR enddate > NOW())", array($pid));
        while ($row = sqlFetchArray($res_allergies)) {
            $allergies[] = $row;
        }

        $hl7_message = "";
        $hl7_message .= "MSH|^~\\&|OPENEMR|{$clinic_name}|PYXIS|FACILITY|{$timestamp}||RDE^O11|{$msgId}|P|2.3\r\n";
        $hl7_message .= "PID|1|{$pid}|{$pid}^^^OPENEMR^MRN||{$patientName}||{$patientDob}|{$patientGender}||{$race}|{$addressLine}||{$phoneNumber}||{$language}|{$marital}|{$religion}|{$accountNumber}||||{$ethnicity}||||||||N\r\n";
        
        // Add AL1 segments for each allergy
        $al1_count = 1;
        if (!empty($allergies)) {
            foreach ($allergies as $allergy) {
                $allergen_name = strtoupper($allergy['title']);
                $reaction = strtoupper($allergy['reaction'] ?? '');
                $severity = strtoupper($allergy['severity_al'] ?? '');
                $identification_date = !empty($allergy['begdate']) ? date('Ymd', strtotime($allergy['begdate'])) : '';
                $hl7_message .= "AL1|{$al1_count}|DA^Drug Allergy|{$allergen_name}|{$severity}|{$reaction}|{$identification_date}|||||\r\n";
                $al1_count++;
            }
        } else {
            // If no allergies, still include a generic one as before
            $hl7_message .= "AL1|1|DA^Drug Allergy||||||||\r\n";
        }
        
        $hl7_message .= "PV1|1|O|{$clinic_name}|||||{$providerName}|||||||||||{$accountNumber}|||||||||||||||||||||||||{$timestamp}||\r\n";
        $hl7_message .= "ORC|NW||{$orderId}||||{$dosage}^{$pyxisTiming}^^{$timestamp}^{$timestamp}^ROUTINE^R||{$timestamp}|||{$providerName}||||||||||||||\r\n";
        
        // Resolve BD Dispense Form ID to display title for RXE segment
        $hl7_form_display = $final_form;  // Default to the form value
        if ($use_bd_dispense && !empty($bd_dispense_form)) {
            // bd_dispense_form is a numeric ID, lookup its title from list_options
            $form_lookup = sqlQuery(
                "SELECT title FROM list_options WHERE list_id = 'drug_form' AND option_id = ?",
                array($bd_dispense_form)
            );
            if ($form_lookup && !empty($form_lookup['title'])) {
                $hl7_form_display = $form_lookup['title'];
            }
        }
        
        $hl7_message .= "RXE|{$dosage}^{$pyxisTiming}^^{$timestamp}^{$timestamp}^ROUTINE^R|{$drugCode}^{$simpleDrugName}^PYXIS|{$drugStrength}|{$drugStrength}|{$units}|{$hl7_form_display}|^{$prescriptionNote}|||{$final_quantity}||{$refills_display}|||||||||||||\r\n";
        $hl7_message .= "RXR|{$route_code}^{$route_display}\r\n";
        $hl7_message .= "TQ1|1||{$frequency}||||{$timestamp}|{$timestamp}|ROUTINE|R|\r\n";

        return $hl7_message;
    }

    // Function to save HL7 message to outbound folder
    function savePrescriptionHL7ToOutbound($hl7_message, $prescription_id, $action_type = 'update') {
        error_log("DEBUG: savePrescriptionHL7ToOutbound called with action_type: " . $action_type . ", prescription_id: " . $prescription_id);
        
        $base_dir = dirname(__FILE__) . '/../interface/drugs';
        if ($action_type == 'new') {
            $subfolder = 'hl7_outbound';
        } elseif ($action_type == 'delete') {
            $subfolder = 'hl7_delete';
        } else {
            $subfolder = 'hl7_update';
        }
        $hl7_dir = $base_dir . '/' . $subfolder;
        error_log("DEBUG: Target directory: " . $hl7_dir);
        
        if (!is_dir($hl7_dir)) {
            mkdir($hl7_dir, 0755, true);
            error_log("DEBUG: Created directory: " . $hl7_dir);
        } else {
            error_log("DEBUG: Directory already exists: " . $hl7_dir);
        }
        
        $timestamp = date('YmdHis');
        $filename = "rde_prescription_{$prescription_id}_{$timestamp}.hl7";
        $filepath = $hl7_dir . '/' . $filename;
        
        error_log("DEBUG: Writing HL7 file to: " . $filepath);
        error_log("DEBUG: Message length: " . strlen($hl7_message) . " bytes");
        
        $bytes_written = file_put_contents($filepath, $hl7_message);
        
        if ($bytes_written === false) {
            error_log("ERROR: Failed to write HL7 file to {$filepath}");
        } else {
            error_log("SUCCESS: Wrote {$bytes_written} bytes to {$filepath}");
            error_log("RDE HL7 saved to {$subfolder}: {$filepath}");
        }
    }

    // Function to simulate Pyxis response by generating DFT message
    function simulatePyxisResponse($prescription, $dispenseQuantity = null) {
        $quantity = $dispenseQuantity ?: $prescription->quantity ?: 1;
        $dft_message = $this->generateDispenseHL7($prescription, $quantity);
        $this->saveDispenseHL7ToInbound($dft_message, $prescription->get_id());
        
        // Automatically process the DFT message to decrement inventory
        $this->processInboundHL7();
    }

    // Function to generate DFT^P03 message
    function generateDispenseHL7($prescription, $quantity = null) {
        $timestamp = date('YmdHis');
        $msgId = 'PYXDFT' . str_pad($prescription->get_id(), 7, '0', STR_PAD_LEFT);

        // Patient info
        $patient = $prescription->patient;
        $patientName = strtoupper($patient->get_name_display());
        $patientDob = date('Ymd', strtotime($patient->date_of_birth));
        // Get patient sex from database since it's not loaded in Patient object
        $patientSexResult = sqlQuery("SELECT sex FROM patient_data WHERE pid = ?", array($patient->get_id()));
        $sexValue = strtoupper($patientSexResult['sex'] ?? '');
        // Convert full gender words to HL7 single letters
        $genderMap = ['MALE' => 'M', 'FEMALE' => 'F', 'M' => 'M', 'F' => 'F'];
        $patientGender = $genderMap[$sexValue] ?? 'U';
        $patientMrn = $patient->get_id(); // Use patient ID as MRN
        $patientAccount = $patient->get_id(); // Use patient ID as account number
        // Determine visit number: use prescription encounter if present, otherwise latest encounter for patient (same as RDE)
        $encounterId = $prescription->get_encounter();
        if (empty($encounterId)) {
            $encounterResult = sqlQuery("SELECT encounter FROM form_encounter WHERE pid = ? ORDER BY date DESC LIMIT 1", array($patient->get_id()));
            $encounterId = $encounterResult['encounter'] ?? '';
        }

        // Drug info - simplified for Pyxis compatibility
        $drugName = strtoupper($prescription->drug);
        // Extract simple drug name, remove complex formulations
        $simpleDrugName = preg_replace('/\{.*?\}/', '', $drugName);
        $simpleDrugName = trim($simpleDrugName);
        
        $drugCode = 'TB' . str_pad($prescription->get_drug_id() ?: '00003758', 7, '0', STR_PAD_LEFT);
        $dispenseQuantity = $quantity ?: $prescription->quantity ?: 1;

        // Get inventory info for quantities
        $lotNumber = $prescription->lot_number ?? null;
        if ($lotNumber) {
            $inventoryInfo = sqlQuery("SELECT on_hand FROM drug_inventory WHERE drug_id = ? AND lot_number = ? AND on_hand > 0", 
                                    array($prescription->get_drug_id(), $lotNumber));
            $currentStock = $inventoryInfo ? $inventoryInfo['on_hand'] : 0;
        } else {
            $inventoryInfo = sqlQuery("SELECT SUM(on_hand) as total_on_hand FROM drug_inventory WHERE drug_id = ? AND on_hand > 0", 
                                    array($prescription->get_drug_id()));
            $currentStock = $inventoryInfo ? $inventoryInfo['total_on_hand'] : 0;
        }
        $remainingStock = $currentStock - $dispenseQuantity;
        if ($remainingStock < 0) $remainingStock = 0; // Don't go negative


         $fac_name = sqlQuery("SELECT name FROM facility WHERE inactive = 0 LIMIT 1")['name'] ?? 'TB CLINIC';

        // Determine clinic name based on facility
        $clinic_name = ($fac_name == "El Paso Department of Public Health Respiratory Disease Clinic") ? "TB Clinic" : "STD Clinic";

        // Provider info
        $provider = $prescription->provider;
        $providerName = strtoupper($provider->lname . '^' . $provider->fname);
        $providerNameWithNPI = strtoupper($provider->lname . '^' . $provider->fname . '^' . $provider->npi);
        $orderId = 'ORD' . str_pad($prescription->get_id(), 7, '0', STR_PAD_LEFT);
        $userId = $_SESSION['authUserID'] ?? '';
        $userName = $_SESSION['authUser'] ?? '';

        $disptotal = $currentStock-$dispenseQuantity;

        $dft_message = "";
        $dft_message .= "MSH|^~\\&|PYXIS|MEDES|OPENEMR|CLINIC|{$timestamp}||DFT^P03|{$msgId}|P|2.3\r\n";
        $dft_message .= "PID|1|{$patientMrn}|{$patientMrn}^^^OPENEMR^MR||{$patientName}||{$patientDob}|{$patientGender}||||||||||{$encounterId}|\r\n";
        $dft_message .= "PV1|1|O|{$clinic_name}|||||{$provider->npi}^{$providerName}|||||||||||{$encounterId}|||||||||||||||||||||||||{$timestamp}||\r\n";
        $dft_message .= "FT1|1|||{$timestamp}|{$timestamp}|V|{$drugCode}^{$simpleDrugName}||{$orderId}|{$dispenseQuantity}|||||||1|||{$providerName}|||{$orderId}\r\n";
        $dft_message .= "ZPM|V|{$clinic_name}|PYXIS|||{$drugCode}|{$simpleDrugName}||{$currentStock}|{$currentStock}|{$dispenseQuantity}|{$userId}|{$userName}|||{$disptotal}|||||||||||||||||{$orderId}|||||||1|0|||0||||||||||||\r\n";

        
        return $dft_message;
    }

    // Function to save DFT message to inbound folder
    function saveDispenseHL7ToInbound($dft_message, $prescription_id) {
        $hl7_dir = dirname(__FILE__) . '/../interface/drugs/hl7_inbound';
        if (!is_dir($hl7_dir)) {
            mkdir($hl7_dir, 0755, true);
        }
        
        $timestamp = date('YmdHis');
        $filename = "dft_prescription_{$prescription_id}_{$timestamp}.hl7";
        $filepath = $hl7_dir . '/' . $filename;
        
        file_put_contents($filepath, $dft_message);
        error_log("DFT HL7 saved to inbound: {$filepath}");
    }

    // Function to process inbound HL7 files
    function processInboundHL7() {
        $inbound_dir = dirname(__FILE__) . '/../interface/drugs/hl7_inbound';
        if (!is_dir($inbound_dir)) {
            return;
        }
        
        $files = glob($inbound_dir . '/*.hl7');
        foreach ($files as $file) {
            $hl7_content = file_get_contents($file);
            if ($hl7_content) {
                // Extract prescription_id from filename (accept both dft_prescription_{id}_{timestamp}.hl7
                // and dft_prescription_{id}.hl7)
                $filename = basename($file);
                if (preg_match('/dft_prescription_(\d+)(?:_|\.|$)/', $filename, $matches)) {
                    $prescription_id = $matches[1];
                    $this->processHL7Message($hl7_content, $prescription_id);
                } else {
                    $this->processHL7Message($hl7_content);
                }
                // Move processed file to archive
                $archive_dir = $inbound_dir . '/processed';
                if (!is_dir($archive_dir)) {
                    mkdir($archive_dir, 0755, true);
                }
                rename($file, $archive_dir . '/' . basename($file));
            }
        }
    }

    // Function to process a single HL7 message
    function processHL7Message($hl7_content, $prescription_id = null) {
        // Parse manually like in process_hl7.php
        $lines = explode("\n", trim($hl7_content));
        $parsed = [];
        foreach ($lines as $line) {
            $fields = explode('|', $line);
            $parsed[] = $fields;
        }

        if (empty($parsed)) {
            error_log("Invalid HL7 message");
            return;
        }

        // Check message type
        $msh = $parsed[0]; // MSH is first segment
        $msn9 = explode('^', $msh[8]);
        $messageType = $msn9[0] ?? '';
        $messageEvent = $msn9[1] ?? '';

        error_log("Processing HL7 message type: $messageType^$messageEvent");

        if ($messageType === 'DFT' && $messageEvent === 'P03') {
            // Process dispense message
            $this->processDispenseMessageManual($parsed, $prescription_id);
        } else {
            error_log("Message type not supported: $messageType^$messageEvent");
        }
    }

    // Internal function to process dispense message with manual parsing
    function processDispenseMessageManual($parsed, $prescription_id = null) {
        error_log("Parsed HL7 segments: " . print_r($parsed, true));
        
        // Find FT1 segment
        $ft1 = null;
        foreach ($parsed as $segment) {
            if ($segment[0] === 'FT1') {
                $ft1 = $segment;
                break;
            }
        }
        
        if (!$ft1) {
            error_log("No FT1 segment in DFT message");
            return;
        }
        
        error_log("FT1 segment: " . print_r($ft1, true));
        
        $drugCode = explode('^', $ft1[7])[0] ?? ''; // FT1-7.1 (Transaction Code)
        $quantity = intval($ft1[10] ?? 0); // FT1-10 (Transaction Quantity)

        // Prefer ZPM-16 if present (ZPM fields are 1-based; array index 16 corresponds to ZPM-16)
        $zpmQty = 0;
        foreach ($parsed as $segment) {
            if ($segment[0] === 'ZPM') {
                $zpmQty = intval($segment[16] ?? 0);
                break;
            }
        }
        if ($zpmQty > 0) {
            $finalQty = $zpmQty;
        } else {
            $finalQty = $quantity;
        }

        error_log("Processing dispense - Drug Code: $drugCode, FT1 Quantity: $quantity, ZPM-16: $zpmQty, Using: $finalQty");
        
        // Find the drug by code - since drug_code is TB{durg_id}, extract drug_id
        if (strpos($drugCode, 'TB') === 0) {
            $drugId = substr($drugCode, 2);
        } else {
            $drugId = null;
        }
        
        if (!$drugId) {
            error_log("Invalid drug code format: $drugCode");
            return;
        }
        
        // Get drug name
        $drugQuery = sqlQuery("SELECT name FROM drugs WHERE drug_id = ?", array($drugId));
        if (!$drugQuery) {
            error_log("Drug not found for ID: $drugId");
            return;
        }
        
        $drugName = $drugQuery['name'];
        
        // Check if we have a prescription_id and get the lot_number
        $lotNumber = null;
        if ($prescription_id) {
            $prescriptionQuery = sqlQuery("SELECT lot_number FROM prescriptions WHERE id = ?", array($prescription_id));
            if ($prescriptionQuery && !empty($prescriptionQuery['lot_number'])) {
                $lotNumber = $prescriptionQuery['lot_number'];
                error_log("Using specific lot from prescription: $lotNumber");
            }
        }
        
        // Decrement inventory
        $inventoryCheck = sqlQuery("SHOW TABLES LIKE 'drug_inventory'");
        if ($inventoryCheck) {
            if ($lotNumber) {
                // Decrement specific lot
                $updated = sqlStatement("UPDATE drug_inventory SET on_hand = on_hand - ? WHERE drug_id = ? AND lot_number = ? AND on_hand >= ?", 
                                       array($finalQty, $drugId, $lotNumber, $finalQty));
                if ($updated) {
                    error_log("Decremented specific lot $lotNumber for drug $drugId ($drugName) by $finalQty");
                } else {
                    error_log("Failed to decrement specific lot $lotNumber - insufficient stock or lot not found");
                }
            } else {
                // Decrement any available lot (fallback)
                $updated = sqlStatement("UPDATE drug_inventory SET on_hand = on_hand - ? WHERE drug_id = ? AND on_hand >= ? ORDER BY expiration ASC LIMIT 1", 
                                       array($finalQty, $drugId, $finalQty));
                if ($updated) {
                    error_log("Decremented first available lot for drug $drugId ($drugName) by $finalQty");
                } else {
                    error_log("Failed to decrement inventory - insufficient stock or drug not found");
                }
            }
        } else {
            error_log("drug_inventory table not found");
        }
        
        // Log the dispense transaction
        sqlStatement("INSERT INTO drug_sales (drug_id, sale_date, quantity, fee, pid, encounter, user, inventory_id) 
                     VALUES (?, NOW(), ?, 0, 0, 0, 'PYXIS', 0)", 
                    array($drugId, $finalQty));

        error_log("Logged dispense transaction for drug $drugId, quantity $finalQty");

        // Update prescription record's dispensed_quantity if a prescription id was provided
        if (!empty($prescription_id)) {
            $update = sqlStatement("UPDATE prescriptions SET dispensed_quantity = ? WHERE id = ?", array($finalQty, $prescription_id));
            if ($update) {
                error_log("Updated prescription {$prescription_id} dispensed_quantity to {$finalQty}");
            } else {
                error_log("Failed to update prescription {$prescription_id} dispensed_quantity");
            }
        }
    }

    // Function to generate HL7 RDE^O11 message for prescription update (ORC|XO|)
    function generatePrescriptionUpdateHL7($prescription) {
        // Use the same logic as generatePrescriptionHL7 but change ORC to XO
        $hl7_message = $this->generatePrescriptionHL7($prescription);
        
        // Replace ORC|NW| with ORC|XO|
        $hl7_message = str_replace("ORC|NW|", "ORC|XO|", $hl7_message);
        
        return $hl7_message;
    }

    // Function to generate HL7 RDE^O11 message for prescription delete/cancel (ORC|CA|)
    function generatePrescriptionDeleteHL7($prescription) {
        // Use the same logic as generatePrescriptionHL7 but change ORC to CA and remove RXE
        error_log("DEBUG: generatePrescriptionDeleteHL7 called for prescription ID: " . $prescription->get_id());
        
        $hl7_message = $this->generatePrescriptionHL7($prescription);
        error_log("DEBUG: Generated base HL7 message length: " . strlen($hl7_message));
        error_log("DEBUG: Base HL7 Message:\n" . $hl7_message);
        
        // Replace ORC|NW| with ORC|CA|
        $hl7_message_before = $hl7_message;
        $hl7_message = str_replace("ORC|NW|", "ORC|CA|", $hl7_message);
        
        if ($hl7_message_before === $hl7_message) {
            error_log("WARNING: ORC|NW| not found in message - replacement may have failed");
        } else {
            error_log("DEBUG: Successfully replaced ORC|NW| with ORC|CA|");
        }
        
        // Remove RXE segment (not required for delete operations)
        $hl7_message_before = $hl7_message;
        $hl7_message = preg_replace('/RXE\|[^\r\n]*\r\n/', '', $hl7_message);
        
        if ($hl7_message_before === $hl7_message) {
            error_log("WARNING: RXE segment not found - no removal performed");
        } else {
            error_log("DEBUG: Successfully removed RXE segment");
        }
        
        error_log("DEBUG: Final HL7 delete message:\n" . $hl7_message);
        return $hl7_message;
    }
}

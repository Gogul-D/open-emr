<?php

/**
 * Immunizations
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2018-2019 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

    require_once("../../globals.php");
    require_once("$srcdir/options.inc.php");
    require_once("$srcdir/immunization_helper.php");

    use OpenEMR\Common\Csrf\CsrfUtils;
    use OpenEMR\Common\Logging\EventAuditLogger;
    use OpenEMR\Common\Uuid\UuidRegistry;
    use OpenEMR\Core\Header;
    use OpenEMR\Menu\PatientMenuRole;



 function hl7_date($dt, $withTime = false) {
    if (!$dt || $dt == '0000-00-00 00:00:00') return '';
    $d = new DateTime($dt);
    return $withTime ? $d->format('YmdHisO') : $d->format('Ymd');
 }


 function map_completion_status($status) {
    switch (strtolower($status)) {
        case 'completed': return 'CP';
        case 'partially administered': return 'PA';
        case 'refused': return 'RE';
        case 'not administered': return 'NA';
        default: return 'CP'; // default to CP if not specified
    }
 }


 function trim_hl7_fields(array $segment): array {
    while (!empty($segment) && end($segment) === '') {
        array_pop($segment);
    }
    return $segment;
 }


 function escape_hl7($value) {
    return str_replace(['\\', '|', '^', '&', '~'], ['\\\\', '\\F\\', '\\S\\', '\\T\\', '\\R\\'], $value);
 }



function download_hl7_file($filePath) {
    header('Content-Description: File Transfer');
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

$filePath="";


 function getManufacturerMVX($name)
{
    $map = [
        'Moderna' => 'MOD',
        'Moderna US, Inc.' => 'MOD',
        'Pfizer, Inc' => 'PFR',
        'Merck and Co., Inc.' => 'MSD',
        'GlaxoSmithKline' => 'SKB',
        'Sanofi Pasteur' => 'PMC',
        'Grifols' => 'GRF',
        'Bavarian Nordic A/S' => 'BNX'
    ];
    return $map[$name] ?? 'MOD';
}


function build_hl7_message($id) {
    $imm = sqlQuery("SELECT * FROM immunizations WHERE id = ?", [$id]);
    if (!$imm) return false;

    $patient = sqlQuery("SELECT * FROM patient_data WHERE pid = ?", [$imm['patient_id']]);
    if (!$patient) return false;

    $pid = $imm['patient_id']; 

    $insurance = sqlQuery(
        "SELECT provider FROM insurance_data 
        WHERE pid = ? AND type = 'primary' 
        ORDER BY date DESC LIMIT 1",
        [$pid]
    );
    $insurance_name = '';

    if (!empty($insurance['provider'])) {
        $provider_id = $insurance['provider'];
        $company = sqlQuery("SELECT name FROM insurance_companies WHERE id = ?", [$provider_id]);
        $insurance_name = $company['name'] ?? 'MEDICAID';
    }

    $dob = $patient['DOB'] ?? null;
    $consent_code = '';
    if ($dob) {
        $dobDate = new DateTime($dob);
        $today = new DateTime();
        $age = $dobDate->diff($today)->y;
        $consent_code = ($age < 18) ? 'TXY' : 'TXA';
    }

    $state = strlen($patient['state']) == 2 ? $patient['state'] : 'TX';
    $msgControlId = 'OPENEMR-' . date('YmdHis');
    $timestamp = date('Ymd');

    $msh = [
        'MSH', '^~\\&', 'OPENEMR', '1100100033', 'TXImmTrac', 'TxDSHS',
        $timestamp, '', 'VXU^V04^VXU_V04', $msgControlId, 'P', '2.5.1', '', '', 'ER', 'AL', '', '', '', '', 'Z22', '145236'
    ];

    $dob_formatted = hl7_date($patient['DOB']);
    $phone = preg_replace('/\D/', '', $patient['phone_cell']);
    $area = substr($phone, 0, 3);
    $local = substr($phone, 3);
    $language = 'ENG^ENGLISH^HL70296';
    $ssn = $patient['ss'] ?? '';
    $race = '2106-3^WHITE^HL70005';
    $motherMaiden = $patient['mothersname'] ?? 'SMITH';
    $alias = "{$patient['lname']}^{$patient['fname']}";

    $pid = [
        'PID', '1', '', "{$patient['id']}^^^^MR^", '',
        "{$patient['lname']}^{$patient['fname']}^^^^^L", $motherMaiden,
        $dob_formatted, strtoupper(substr($patient['sex'], 0, 1)), $alias, $race,
        "{$patient['street']}^^{$patient['city']}^{$state}^{$patient['postal_code']}^USA^H",
        '', "^PRN^PH^^1^{$area}^{$local}", '', $language, '', '', $ssn, '', '', '',
        '2186-5^Not Hispanic or Latino^HL70012', '', 'N', ''
    ];

    $enteredUser = sqlQuery("SELECT id, lname, fname, mname FROM users WHERE id = ?", [$imm['created_by']]);
    $enteredBy = $enteredUser ? "{$enteredUser['id']}^{$enteredUser['lname']}^{$enteredUser['fname']}^{$enteredUser['mname']}" : '';

    $providerUser = sqlQuery("SELECT npi, lname, fname FROM users WHERE id = ?", [$imm['administered_by_id']]);
    $orderProvider = '';
    if ($providerUser) {
        $npi = $providerUser['npi'] ? "NPI {$providerUser['npi']}" : $providerUser['id'];
        $orderProvider = "{$npi}^{$providerUser['fname']} {$providerUser['lname']}";
    }

    $facility = "El Paso Clinic";
    $orderNumber = "{$imm['id']}^{$facility}";
    $transactionDateTime = date('YmdHis', strtotime($imm['administered_date']));

    $orc = ['ORC', 'RE', '', $orderNumber, '', '', '', '', '', $transactionDateTime, $enteredBy, '', $orderProvider];

    $adminDate = hl7_date($imm['administered_date']);
    $expirationDate = hl7_date($imm['expiration_date']);
    $manufacturerName = $imm['manufacturer'] ?? 'MODERNA';
    $manufacturerCode = getManufacturerMVX($manufacturerName);
    $rxa_manufacturer = "{$manufacturerCode}^{$manufacturerName}^MVX";

    $isCustom = !empty($imm['is_custom']) && $imm['is_custom'] == 1;
    $isHistorical = ($imm['information_source'] == 'hist_inf_src_unspecified');

    if ($isCustom) {
        $cvx = $imm['cvx_code'] ?: '999';
        $cvxDesc = $imm['custom_name'] ?: 'Custom Immunization';
        if ($cvx && $cvx !== '999') {
            $cvx_lookup = sqlQuery("SELECT code_text_short FROM codes WHERE code_type = (SELECT ct_id FROM code_types WHERE ct_key = 'CVX') AND code = ?", [$cvx]);
            if ($cvx_lookup && !empty($cvx_lookup['code_text_short'])) {
                $cvxDesc = $cvx_lookup['code_text_short'];
            }
        }
    } else {
        $drug = sqlQuery("SELECT name, cvx_code, cvx_desc FROM immunization_drug WHERE drug_id = ?", [$imm['immunization_id']]);
        $cvx = $drug['cvx_code'] ?? '999';
        $cvxDesc = $drug['cvx_desc'] ?? 'Unknown';
    }

    $rxa = [];
    if ($isHistorical) {
        $rxa = [
            'RXA', '0', '1', $adminDate, $adminDate,
            "$cvx^$cvxDesc^CVX", '999', '', '',
            '01^Historical Record^NIP001', '', '', '', '', '', '', '', '', '', 'A'
        ];
    } else {
        $amount = $imm['amount_administered'] ?? '0';
        $display_unit = generate_display_field(['data_type' => '1', 'list_id' => 'drug_units'], $imm['amount_administered_unit']);
        $lot = $imm['lot_number'] ?? '';
        $rxa_admin_by = ($adminUser = sqlQuery("SELECT fname, lname FROM users WHERE id = ?", [$imm['administered_by_id']])) ? "{$adminUser['lname']}^{$adminUser['fname']}" : '';
        $facility_id = '^^^145236';
        $rxa = [
            'RXA', '0', '1', $adminDate, $adminDate,
            "$cvx^$cvxDesc^CVX", $amount,
            $display_unit ? "$display_unit^milliliter^UCUM" : '', '',
            '00^New immunization record^NIP001',
            $rxa_admin_by, $facility_id, '', '', '', $lot,
            $expirationDate, $rxa_manufacturer, '', '', 'CP', 'A', ''
        ];
    }

    $route_map = ['bymouth' => 'PO^ORAL^HL70162', 'SC' => 'SC^SUBCUTANEOUS^HL70162', 'IM' => 'IM^INTRAMUSCULAR^HL70162', 'ID' => 'ID^INTRADERMAL^HL70162'];
    $site_map = ['left_thigh' => 'LT^LEFT', 'left_arm' => 'LA^LEFT', 'right_arm' => 'RA^RIGHT'];
    $hl7_route = $route_map[$imm['route']] ?? '';
    $hl7_site = $site_map[$imm['administration_site']] ?? '';
    $rxr = ['RXR', $hl7_route, $hl7_site];

    $pd1 = ['PD1', '', '', '', '', '', '', '', '', '', '', '', $consent_code, hl7_date($imm['administered_date']), ''];

    $segments = [
        implode('|', trim_hl7_fields($msh)),
        implode('|', $pid),
        implode('|', trim_hl7_fields($pd1))
    ];

        $nk1 = [];
        if ($isHistorical) {
            $guardianName = $patient['guardiansname'] ?? '';
            $dobToday = (new DateTime($patient['DOB']))->format('Y-m-d') === date('Y-m-d');

            if (!empty($guardianName)) {
                $nk1 = ['NK1', '1', "{$guardianName}^Guardian", '', '', ''];
            } else {
                $nk1 = ['NK1', '1', "Newborn^Guardian", '', '', ''];
            }

            $segments[] = implode('|', ($nk1));
        }

   
    $segments[] = implode('|', trim_hl7_fields($orc));
    $segments[] = implode('|', $rxa);
    if (!$isHistorical) {
    $segments[] = implode('|', trim_hl7_fields($rxr));
    }

    if (!$isHistorical) {
        $vis_date = $imm['vis_date'] ?? '2025-05-30';
        $vis_date_formatted = date('Ymd', strtotime($vis_date));
        $obx_segments = [
            ['OBX','1','CE','64994-7^VACCINE FUNDING PROGRAM ELIGIBILITY CATEGORY^LN','1','V02^'.strtoupper($insurance_name).'^HL70064','','','','','','F','','',$adminDate,'','','VXC40^ELIGIBILITY CAPTURED AT THE IMMUNIZATION LEVEL^CDCPHINVS',''],
            ['OBX','2','CE','30956-7^Vaccine Type^LN','2',"$cvx^$cvxDesc^CVX",'','','','','','F','','',$vis_date_formatted,'','','VXC40^per immunization^CDCPHINVS',''],
            ['OBX','3','TS','29768-9^Date vaccine information statement published^LN','2',$vis_date_formatted,'','','','','','F','','',$vis_date_formatted,''],
            ['OBX','4','TS','29769-7^Date vaccine information statement presented^LN','2',$adminDate,'','','','','','F','','',$adminDate,'']
        ];
        foreach ($obx_segments as $obx) {
            $segments[] = implode('|', $obx);
        }
    }

    return implode("\r", $segments);
} 
 
 if (isset($_GET['hl7_saved']) && $_GET['hl7_saved'] == '1'): ?>
<script>
    
    // Remove hl7_saved from the URL without reloading the page
    if (window.history.replaceState) {
        const url = new URL(window.location);
        url.searchParams.delete('hl7_saved');
        window.history.replaceState({}, document.title, url);
    }
</script>
<?php endif; ?>

<?php 

  if (isset($_GET['mode'])) {
    if (!CsrfUtils::verifyCsrfToken($_GET["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }

     $sel_drug_id = trim($_GET['sel_drug_id'] ?? '');
    $cvx_code = '';
    if ($sel_drug_id) {
        $drug_row = sqlQuery("SELECT cvx_code FROM immunization_drug WHERE drug_id = ?", array($sel_drug_id));
        $cvx_code = $drug_row['cvx_code'] ?? '';
    }

    // echo '<pre>';print_r($_GET); die();

    if ($_GET['mode'] == "add") {

        // Add server-side validation HERE - BEFORE custom immunization logic
    // $required_fields = [
    //     'administered_date' => 'Date & Time Administered',
    //     'immuniz_amt_adminstrd' => 'Amount Administered', 
    //     'form_drug_units' => 'Drug Units',
    //     'immuniz_exp_date' => 'Expiration Date',
    //     'lot_number' => 'Lot Number',
    //     'administered_by_id' => 'Administered By',
    //     'education_date' => 'Education Date',
    //     'vis_date' => 'VIS Date',
    //     'immuniz_route' => 'Route',
    //     'immuniz_admin_ste' => 'Administration Site',
    //     'immunization_informationsource' => 'Information Source',
    //     'immuniz_completion_status' => 'Completion Status',
    //     'ordered_by_id' => 'Ordering Provider'
    // };
    
    // $missing_fields = [];
    // foreach ($required_fields as $field => $label) {
    //     if (empty($_GET[$field]) || trim($_GET[$field]) === '') {
    //         $missing_fields[] = $label;
    //     }
    // }
    
    // if (!empty($missing_fields)) {
    //   $error_params = http_build_query([
    //     'pid' => $pid,
    //     'error' => 'Missing required fields: ' . implode(', ', $missing_fields),
    //     'csrf_token_form' => CsrfUtils::collectCsrfToken()
    // ]);
    // header("Location: immunizations.php?" . $error_params);
    // exit;
    // }


         // Handle custom immunization logic
    $is_custom_immunization = false;
    $custom_immunization_name = '';
    
    if (!empty($_GET['form_immunization_id']) && empty($_GET['sel_drug_id'])) {
        $is_custom_immunization = true;
        $custom_immunization_name = trim($_GET['form_immunization_id']);

        // For custom immunizations, we need to create a temporary drug record or handle it differently
        $cvx_code = $_GET['cvx_code'] ?? '999';
        
        $sel_drug_id = '0'; // Use 0 to indicate custom
    } else {
        $sel_drug_id = trim($_GET['sel_drug_id'] ?? '');
        $cvx_code = '';
        if ($sel_drug_id) {
            $drug_row = sqlQuery("SELECT cvx_code FROM immunization_drug WHERE drug_id = ?", array($sel_drug_id));
            $cvx_code = $drug_row['cvx_code'] ?? '';
        }
    }

       // Prepare values for database insertion
   
    $manufacturer_value = trim($_GET['manufacturer'] ?? '');
    $lot_number_value = trim($_GET['lot_number'] ?? '');

        $facility_value = '';
        if (!empty($_GET['form_facility_id'])) {
            $facility_value = trim($_GET['form_facility_id']);
        } elseif (!empty($_GET['form_facility_id_custom'])) {
            $facility_value = trim($_GET['form_facility_id_custom']);
        }

        // Handle vendor value
        $vendor_value = '';
        if (!empty($_GET['form_vendor_id'])) {
            $vendor_value = trim($_GET['form_vendor_id']);
        } elseif (!empty($_GET['form_vendor_id_custom'])) {
            $vendor_value = trim($_GET['form_vendor_id_custom']);
        }
 // For custom immunizations, we need to handle non-numeric facility_id
    $facility_id_for_db = null;
    if ($facility_value) {
        if (is_numeric($facility_value)) {
            $facility_id_for_db = $facility_value;
        } else {
            // For custom facility names, we'll store as null and use the text
            $facility_id_for_db = null;
        }

    
    }

    // print_r("select: " . $_GET['immuniz_route_select']);

    $route_value = trim($_GET['immuniz_route'] ?? '');
    // Only use select value if text input is empty
    if ($route_value === '' && !empty($_GET['immuniz_route_select'])) {
        $route_id = trim($_GET['immuniz_route_select']);
        $route_value = generate_display_field(
            array('data_type' => '1', 'list_id' => 'drug_route'),
            $route_id
        );
        if (empty($route_value)) {
            $route_value = "";
        }
    }

    // print_r("route-" . $route_value); die();


        // If information_source is historical, always use the manually entered administered_by value
        $is_historical = (trim($_GET['immunization_informationsource'] ?? '') === 'hist_inf_src_unspecified');
        $administered_by_id_val = trim($_GET['administered_by_id']);
        $administered_by_val = trim($_GET['administered_by']);
        if ($is_historical) {
            $administered_by_id_val = null;
        }

        // print_r("admin".$_GET['administered_by']); 

        $sql = "REPLACE INTO immunizations set
            id = ?,
            uuid = ?,
            administered_date = if(?,?,NULL),
            immunization_id = ?,
            cvx_code = ?,
            vendor_id= ?,
            manufacturer = ?,
            lot_number = ?,
            facility_id = ?,
            administered_by_id = if(?,?,NULL),
            administered_by = ?,
            education_date = if(?,?,NULL),
            vis_date = if(?,?,NULL),
            note   = ?,
            patient_id   = ?,
            created_by = ?,
            updated_by = ?,
            create_date = now(),
            amount_administered = ?,
            amount_administered_unit = ?,
            expiration_date = if(?,?,NULL),
            route = ?,
            administration_site = ? ,
            completion_status = ?,
            information_source = ?,
            refusal_reason = ?,
            reason_code = ?,
            reason_description = ?,
            ordering_provider = ?,
            is_custom = ?,
            custom_name = ?";
        $sqlBindArray = array(
            trim($_GET['id']),
            UuidRegistry::isValidStringUUID($_GET['uuid']) ? UuidRegistry::uuidToBytes($_GET['uuid']) : null,
            trim($_GET['administered_date']), trim($_GET['administered_date']),
            $is_custom_immunization ? '0' : $sel_drug_id,
            $cvx_code,
            $vendor_value,
            $manufacturer_value,
            $lot_number_value,
            $facility_id_for_db,
            $administered_by_id_val, $administered_by_id_val,
            $administered_by_val,
            trim($_GET['education_date']), trim($_GET['education_date']),
            trim($_GET['vis_date']), trim($_GET['vis_date']),
            trim($_GET['note']),
            $pid,
            $_SESSION['authUserID'],
            $_SESSION['authUserID'],
            ($_GET['immuniz_amt_adminstrd'] === '' ? null : trim($_GET['immuniz_amt_adminstrd'])),
            ($_GET['form_drug_units'] == 0 ? null : trim($_GET['form_drug_units'])),
            // $_GET['form_drug_units'] ?? '',
            trim($_GET['immuniz_exp_date']), trim($_GET['immuniz_exp_date']),
            $route_value,
            trim($_GET['immuniz_admin_ste']),
            trim($_GET['immuniz_completion_status']),
            trim($_GET['immunization_informationsource']),
            trim($_GET['immunization_refusal_reason']),
            trim($_GET['reason_code']),
            trim($_GET['reason_description'] ?? ''),
            trim($_GET['ordered_by_id']),
            $is_custom_immunization ? 1 : 0,
            $is_custom_immunization ? $custom_immunization_name : null
        );

    
        $newid = sqlInsert($sql, $sqlBindArray);


        // Only update inventory for non-custom immunizations with valid facility_id, and only on add (not edit)
        if (!$is_custom_immunization && $facility_value && is_numeric($facility_value) && (!isset($_GET['id']) || empty($_GET['id']))) {
            // Directly update inventory using facility_id as warehouse_id
            $inventory_updated = sqlStatement(
                "UPDATE immunization_inventory_drug 
                SET on_hand = on_hand - 1 
                WHERE drug_id = ? AND lot_number = ? AND facility_id = ? AND vendor_id = ? AND on_hand > 0",
                [$sel_drug_id, $lot_number_value, $facility_value, $vendor_value]
            );

            // Fetch the correct inventory_id for this drug, lot, and facility
            $inventory_row = sqlQuery(
                "SELECT inventory_id FROM immunization_inventory_drug WHERE drug_id = ? AND lot_number = ? AND facility_id = ? AND vendor_id = ? ORDER BY inventory_id DESC LIMIT 1",
                [$sel_drug_id, $lot_number_value, $facility_value, $vendor_value]
            );
            $inventory_id = $inventory_row ? $inventory_row['inventory_id'] : 0;

            // Insert sales record for inventory tracking and reporting
            $administered_date_for_sales = !empty($_GET['administered_date']) ?
                date('Y-m-d H:i:s', strtotime($_GET['administered_date'])) :
                date('Y-m-d H:i:s');
            $sales_inserted = sqlStatement(
                "INSERT INTO immunization_sales 
                (drug_id, inventory_id, sale_date, quantity, pid, trans_type, lot_number) 
                VALUES (?, ?, ?, 1, ?, 'sale', ?)",
                [
                    $sel_drug_id,
                    $inventory_id,
                    $administered_date_for_sales,
                    $pid,
                    $lot_number_value
                ]
            );
        }


        // $hl7_message = build_hl7_message($newid);
        // $importCode = "ELPASOHD"; 
        // $year = date("y"); 
        // $dayOfYear = str_pad(date("z") + 1, 3, "0", STR_PAD_LEFT); // e.g., "168"

    //    $time = date("His"); // e.g., "153045" (HHMMSS)

    //    $filename = $importCode . $year . $dayOfYear .  ".hl7";


    // Generate HL7 message
        $hl7_message = build_hl7_message($newid);
          
            $encounter_row = sqlQuery("SELECT encounter FROM form_encounter WHERE pid = ? ORDER BY date DESC LIMIT 1", array($pid));
            $encounter_id = $encounter_row ? $encounter_row['encounter'] : 'noenc';
      

        if ($hl7_message) {

            $vacc_date = '';
            if (!empty($_GET['administered_date'])) {
                $vacc_date = date('Ymd', strtotime($_GET['administered_date']));
            } else {
                $vacc_date = date('Ymd');
            }

            $filename = "pid{$pid}_date{$vacc_date}_enc{$encounter_id}.hl7";
         
            $folder = __DIR__ . '/hl7_files';

            if (!is_dir($folder)) {
                mkdir($folder, 0777, true);
            }

            $filePath = $folder . "/" . $filename;
            // print_r($filePath); die();
            $write_result = file_put_contents($filePath, $hl7_message . "\r");
            
            if ($write_result === false) {
                error_log("Failed to write HL7 file: " . $filePath);
                echo "<script>alert('Error: HL7 file could not be saved!');</script>";
            } elseif (file_exists($filePath)) {
                echo "<script>alert('HL7 file saved successfully: " . addslashes($filename) . "');</script>";
            }
        } else {
            error_log("Failed to generate HL7 message for immunization ID: " . $newid);
            echo "<script>alert('Warning: Immunization saved but HL7 generation failed!');</script>";
        }

        // Set variables for form reset
        $administered_date = date('Y-m-d H:i');
        $education_date = date('Y-m-d');
        $immunization_id = $cvx_code = $manufacturer = $lot_number = $administered_by_id = $note = $id = $ordered_by_id = "";
        $administered_by = $vis_date = "";
        $newid = $_GET['id'] ? $_GET['id'] : $newid;
        
        if ($GLOBALS['observation_results_immunization']) {
            saveImmunizationObservationResults($newid, $_GET);
        }
        
    header("Location: immunizations.php?pid=" . urlencode($pid) . "&csrf_token_form=" . urlencode(CsrfUtils::collectCsrfToken()) . "&hl7_saved=1");
    exit;
    } elseif ($_GET['mode'] == "delete") {
        // log the event
        EventAuditLogger::instance()->newEvent("delete", $_SESSION['authUser'], $_SESSION['authProvider'], 1, "Immunization id " . $_GET['id'] . " deleted from pid " . $pid);
        // delete the immunization
        $sql = "DELETE FROM immunizations WHERE id =? LIMIT 1";
        sqlStatement($sql, array($_GET['id']));
    } elseif ($_GET['mode'] == "added_error") {
        // Get the immunization record to determine if we need to update inventory
        $immunization = sqlQuery("SELECT * FROM immunizations WHERE id = ?", array($_GET['id']));
        
        if ($immunization) {
            $isMarkedAsError = ($_GET['isError'] === 'true');
            $currentlyMarkedAsError = !empty($immunization['added_erroneously']);
            
            // If marking as error (and wasn't before), add back to inventory
            if ($isMarkedAsError && !$currentlyMarkedAsError) {
                // Only update inventory for non-custom immunizations
                if (empty($immunization['is_custom']) || $immunization['is_custom'] != 1) {
                    $drug_id = $immunization['immunization_id'];
                    $lot_number = $immunization['lot_number'];
                    $facility_id = $immunization['facility_id'];
                    
                    if ($drug_id && $lot_number && $facility_id) {
                        // Add back to inventory
                        $inventory_updated = sqlStatement(
                            "UPDATE immunization_inventory_drug 
                            SET on_hand = on_hand + 1 
                            WHERE drug_id = ? AND lot_number = ? AND facility_id = ?",
                            [$drug_id, $lot_number, $facility_id]
                        );
                        
                        // Get the inventory_id for sales record
                        $inventory_row = sqlQuery(
                            "SELECT inventory_id FROM immunization_inventory_drug 
                            WHERE drug_id = ? AND lot_number = ? AND facility_id = ? 
                            ORDER BY inventory_id DESC LIMIT 1",
                            [$drug_id, $lot_number, $facility_id]
                        );
                        $inventory_id = $inventory_row ? $inventory_row['inventory_id'] : 0;
                        
                        // Insert return record for tracking
                        $return_date = date('Y-m-d H:i:s');
                        sqlStatement(
                            "INSERT INTO immunization_sales 
                            (drug_id, inventory_id, sale_date, quantity, pid, trans_type, lot_number) 
                            VALUES (?, ?, ?, 1, ?, 'return', ?)",
                            [
                                $drug_id,
                                $inventory_id,
                                $return_date,
                                $immunization['patient_id'],
                                $lot_number
                            ]
                        );
                    }
                }
            }
            // If unmarking as error (was marked before), subtract from inventory
            elseif (!$isMarkedAsError && $currentlyMarkedAsError) {
                // Only update inventory for non-custom immunizations
                if (empty($immunization['is_custom']) || $immunization['is_custom'] != 1) {
                    $drug_id = $immunization['immunization_id'];
                    $lot_number = $immunization['lot_number'];
                    $facility_id = $immunization['facility_id'];
                    
                    if ($drug_id && $lot_number && $facility_id) {
                        // Subtract from inventory (but don't go below 0)
                        $inventory_updated = sqlStatement(
                            "UPDATE immunization_inventory_drug 
                            SET on_hand = GREATEST(0, on_hand - 1) 
                            WHERE drug_id = ? AND lot_number = ? AND facility_id = ?",
                            [$drug_id, $lot_number, $facility_id]
                        );
                        
                        // Get the inventory_id for sales record
                        $inventory_row = sqlQuery(
                            "SELECT inventory_id FROM immunization_inventory_drug 
                            WHERE drug_id = ? AND lot_number = ? AND facility_id = ? 
                            ORDER BY inventory_id DESC LIMIT 1",
                            [$drug_id, $lot_number, $facility_id]
                        );
                        $inventory_id = $inventory_row ? $inventory_row['inventory_id'] : 0;
                        
                        // Insert sale record for tracking
                        $sale_date = date('Y-m-d H:i:s');
                        sqlStatement(
                            "INSERT INTO immunization_sales 
                            (drug_id, inventory_id, sale_date, quantity, pid, trans_type, lot_number) 
                            VALUES (?, ?, ?, 1, ?, 'sale', ?)",
                            [
                                $drug_id,
                                $inventory_id,
                                $sale_date,
                                $immunization['patient_id'],
                                $lot_number
                            ]
                        );
                    }
                }
            }
        }
        
        // Update the immunization record
        $sql = "UPDATE immunizations " .
               "SET added_erroneously=? "  .
               "WHERE id=?";
        $sql_arg_array = array(
            ($_GET['isError'] === 'true'),
            $_GET['id']
        );
        sqlStatement($sql, $sql_arg_array);
    } elseif ($_GET['mode'] == "edit") {
        $sql = "select * from immunizations where id = ?";
        $result = sqlQuery($sql, array($_GET['id']));

        $administered_date = new DateTime($result['administered_date']);
        $uuid = null;
        if (isset($result['uuid']) && !UuidRegistry::isEmptyBinaryUUID($result['uuid'])) {
            $uuid = UuidRegistry::uuidToString($result['uuid']);
        }
        $administered_date = $administered_date->format('Y-m-d H:i');

        $immuniz_amt_adminstrd = $result['amount_administered'];
        $drugunitselecteditem = $result['amount_administered_unit'];
        $immunization_id = $result['immunization_id'];
        // Handle custom immunizations - show custom_name instead of immunization_id
        if (!empty($result['is_custom']) && $result['is_custom'] == 1) {
            // For custom immunizations, use the custom_name as the immunization_id for display
            $immunization_id = $result['custom_name'];
            $is_custom_edit = true;
        } else {
            $is_custom_edit = false;
        }
        $manufacturer = $result['manufacturer'];
        $facility = $result['facility_id'];
        $vendor = $result['vendor_id'];
        $lot_number = $result['lot_number'];
        $immuniz_exp_date = $result['expiration_date'];
        $reason_code = trim($result['reason_code'] ?? '');
        $reason_code_text = trim($result['reason_description'] ?? '');

        $cvx_code = $result['cvx_code'];
        $code_text = '';
        if (!(empty($cvx_code))) {
            $query = "SELECT codes.code_text as `code_text`, codes.code as `code` " .
                     "FROM codes " .
                     "LEFT JOIN code_types on codes.code_type = code_types.ct_id " .
                     "WHERE code_types.ct_key = 'CVX' AND codes.code = ?";
            $result_code_text = sqlQuery($query, array($cvx_code));
            $code_text = $result_code_text['code_text']; 
        }

        $manufacturer = $result['manufacturer'];
        $lot_number = $result['lot_number'];
        $administered_by_id = ($result['administered_by_id'] ? $result['administered_by_id'] : 0);
        $ordered_by_id      = ($result['ordering_provider'] ? $result['ordering_provider'] : 0);
        $entered_by_id      = ($result['created_by'] ? $result['created_by'] : 0);



        // Set administered_by for edit mode
        $administered_by = "";
        if (!empty($result['administered_by_id'])) {
            // If administered_by_id is present, fetch the user's full name
            $stmt = "select CONCAT(IFNULL(lname,''), ' ,',IFNULL(fname,'')) as full_name from users where id=?";
            $user_result = sqlQuery($stmt, array($result['administered_by_id']));
            $administered_by = $user_result ? $user_result['full_name'] : "";
        } elseif (!empty($result['administered_by'])) {
            // If only administered_by string is present
            $administered_by = $result['administered_by'];
        } else {
            // Fallback to current user
            $stmt = "select CONCAT(IFNULL(lname,''), ' ,',IFNULL(fname,'')) as full_name from users where id=?";
            $user_result = sqlQuery($stmt, array($_SESSION['authUserID']));
            $administered_by = $user_result ? $user_result['full_name'] : "";
        }

        // print_r($administered_by);die();

        $education_date = $result['education_date'];
        $vis_date = $result['vis_date'];
        $immuniz_route = $result['route'];
        $immuniz_admin_ste = $result['administration_site'];
        $note = $result['note'];
        $isAddedError = $result['added_erroneously'];

        $immuniz_completion_status = $result['completion_status'];
        $immuniz_information_source = $result['information_source'];
        $immuniz_refusal_reason     = $result['refusal_reason'];
        //set id for page
        $id = $_GET['id'];

        $imm_obs_data = getImmunizationObservationResults();
    }
}

$observation_criteria = getImmunizationObservationLists('1');
$observation_criteria_value = getImmunizationObservationLists('2');
// Decide whether using the CVX list or the custom list in list_options
if ($GLOBALS['use_custom_immun_list']) {
    // user forces the use of the custom list
    $useCVX = false;
} else {
    if (!empty($_GET['mode']) && ($_GET['mode'] == "edit")) {
        //depends on if a cvx code is enterer already
        if (empty($cvx_code)) {
            $useCVX = false;
        } else {
            $useCVX = true;
        }
    } else { // $_GET['mode'] == "add"
        $useCVX = true;
    }
}

// set the default sort method for the list of past immunizations
$sortby = $_GET['sortby'] ?? null;
if (!$sortby) {
    $sortby = 'vacc';
}

// set the default value of 'administered_by'
if (empty($administered_by) && empty($administered_by_id)) {
    $stmt = "select CONCAT(IFNULL(lname,''), ' ,',IFNULL(fname,'')) as full_name " .
            " from users where " .
            " id=?";
    $row = sqlQuery($stmt, array($_SESSION['authUserID']));
    $administered_by = $row['full_name'];
}

// get the entered username
if (!empty($entered_by_id)) {
    $stmt = "select CONCAT(IFNULL(lname,''), ' ,',IFNULL(fname,'')) as full_name " .
            " from users where " .
            " id=?";
    $row = sqlQuery($stmt, array($entered_by_id));
    $entered_by = $row['full_name'];
}

if (!empty($_POST['type']) && ($_POST['type'] == 'duplicate_row')) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
    $observation_criteria = getImmunizationObservationLists('1');
    echo json_encode($observation_criteria);
    exit;
}

if (!empty($_POST['type']) && ($_POST['type'] == 'duplicate_row_2')) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
    $observation_criteria_value = getImmunizationObservationLists('2');
    echo json_encode($observation_criteria_value);
    exit;
}

function getImmunizationObservationLists($k)
{
    if ($k == 1) {
        $observation_criteria_res = sqlStatement("SELECT * FROM list_options WHERE list_id = ? AND activity=1 ORDER BY seq, title", array('immunization_observation'));
        for ($iter = 0; $row = sqlFetchArray($observation_criteria_res); $iter++) {
            $observation_criteria[0]['option_id'] = '';
            $observation_criteria[0]['title']     = 'Unassigned';
            $observation_criteria[++$iter] = $row;
        }

        return $observation_criteria;
    } else {
        $observation_criteria_value_res = sqlStatement("SELECT * FROM list_options WHERE list_id = ? AND activity=1 ORDER BY seq, title", array('imm_vac_eligibility_results'));
        for ($iter = 0; $row = sqlFetchArray($observation_criteria_value_res); $iter++) {
            $observation_criteria_value[0]['option_id'] = '';
            $observation_criteria_value[0]['title']     = 'Unassigned';
            $observation_criteria_value[++$iter] = $row;
        }

        return $observation_criteria_value;
    }
}

function getImmunizationObservationResults()
{
    $obs_res_q = "SELECT
                  *
                FROM
                  immunization_observation
                WHERE imo_pid = ?
                  AND imo_im_id = ?";
    $res = sqlStatement($obs_res_q, array($_SESSION["pid"],$_GET['id']));
    $imm_obs_data = [];
    for ($iter = 0; $row = sqlFetchArray($res); $iter++) {
        $imm_obs_data[$iter] = $row;
    }

    return $imm_obs_data;
}

function saveImmunizationObservationResults($id, $immunizationdata)
{
    $imm_obs_data = getImmunizationObservationResults();
    if (!empty($imm_obs_data) && count($imm_obs_data) > 0) {
        foreach ($imm_obs_data as $key => $val) {
            if ($val['imo_id'] && $val['imo_id'] != 0) {
                $sql2                   = " DELETE
                                            FROM
                                              immunization_observation
                                            WHERE imo_im_id = ?
                                              AND imo_pid = ?";
                $result2                = sqlQuery($sql2, array($val['imo_im_id'],$val['imo_pid']));
            }
        }
    }

    for ($i = 0; $i < $immunizationdata['tr_count']; $i++) {
        if ($immunizationdata['observation_criteria'][$i] == 'vaccine_type') {
            $code                     = $immunizationdata['cvx_vac_type_code'][$i];
            $code_text                = $immunizationdata['code_text_hidden'][$i];
            $code_type                = $immunizationdata['code_type_hidden'][$i];
            $vis_published_dateval    = $immunizationdata['vis_published_date'][$i] ? $immunizationdata['vis_published_date'][$i] : '';
            $vis_presented_dateval    = $immunizationdata['vis_presented_date'][$i] ? $immunizationdata['vis_presented_date'][$i] : '';
            $imo_criteria_value       = '';
        } elseif ($immunizationdata['observation_criteria'][$i] == 'disease_with_presumed_immunity') {
            $code                     = $immunizationdata['sct_code'][$i];
            $code_text                = $immunizationdata['codetext'][$i];
            $code_type                = $immunizationdata['codetypehidden'][$i];
            $imo_criteria_value       = '';
            $vis_published_dateval    = '';
            $vis_presented_dateval    = '';
        } elseif ($immunizationdata['observation_criteria'][$i] == 'funding_program_eligibility') {
            $imo_criteria_value       = $immunizationdata['observation_criteria_value'][$i];
            $code                     = '';
            $code_text                = '';
            $code_type                = '';
            $vis_published_dateval    = '';
            $vis_presented_dateval    = '';
        }

        if ($immunizationdata['observation_criteria'][$i] != '') {
            $sql                      = " INSERT INTO immunization_observation (
                                          imo_im_id,
                                          imo_pid,
                                          imo_criteria,
                                          imo_criteria_value,
                                          imo_user,
                                          imo_code,
                                          imo_codetext,
                                          imo_codetype,
                                          imo_vis_date_published,
                                          imo_vis_date_presented
                                        )
                                        VALUES
                                          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $res                      = sqlQuery($sql, array($id,$_SESSION["pid"],$immunizationdata['observation_criteria'][$i],$imo_criteria_value,$_SESSION['authUserID'],$code, $code_text, $code_type,$vis_published_dateval,$vis_presented_dateval));
        }
    }

    return;
}
?>

<html>
<head>

<?php //Header::setupHeader(['  ', 'select2']); ?>

<?php Header::setupHeader(['datetime-picker', 'select2']); ?>
<title><?php echo xlt('Immunizations'); ?></title>

<style>
.highlight {
  color: green;
}
tr.selected {
  background-color: white;
}

/* Add required field asterisk styling */
/* label[for] + select[required] + ::after,
label[for] + input[required] + ::after,
.form-group:has(select[required]) label::after,
.form-group:has(input[required]) label::after {
    content: " *";
    color: red;
    font-weight: bold;
} */

/* Alternative approach - direct targeting */
.form-group label::after {
    content: "";
}

.form-group:has([required]) label::after {
    content: " *";
    color: red;
    font-weight: bold;
}

</style>

</head>

<?php $downloadUrl = "immunizations.php?mode=download&id=" . urlencode($id) . "&csrf_token_form=" . urlencode(CsrfUtils::collectCsrfToken()); ?>

<body>
    <div class="container mt-3">
        <div class="row">
            <div class="col-12 d-flex align-items-center justify-content-between mb-3">
                <h2 class="mb-0"><?php echo xlt('Immunizations'); ?></h2>
            </div>
            <div class="col-12">
                <?php
                    $list_id = ""; // to indicate nav item is active, count and give correct id
                    $menuPatient = new PatientMenuRole();
                    $menuPatient->displayHorizNavBarMenu();
                ?>
            </div>
            <div class="col-12">
                <form class="jumbotron p-4" action="immunizations.php" name="add_immunization" id="add_immunization">
                    <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />

                    <input type="hidden" name="mode" id="mode" value="add" />
                    <input type="hidden" name="id" id="id" value="<?php echo attr($id ?? ''); ?>" />
                    <input type="hidden" name="pid" id="pid" value="<?php echo attr($pid); ?>" />
                    <input type="hidden" name="uuid" id="uuid" value="<?php echo attr($uuid ?? ''); ?>" />
                   <?php if (!empty($_GET['download_hl7']) && !empty($_GET['file'])) {
    $downloadUrl = "immunizations.php?mode=download&file=" . urlencode($_GET['file']) . "&csrf_token_form=" . urlencode(CsrfUtils::collectCsrfToken());

   ?>
    <a id="downloadLink" href="<?php echo $downloadUrl; ?>" style="display:none;" target="_blank"></a>
    <?php } ?>
                    <?php
                    if (!empty($isAddedError)) {
                        echo "<p class='text-danger font-weight-bold'>" . xlt("Entered in Error") . "</p>";
                    }
                    ?>

                    <?php if (!($useCVX)) { ?>

                    <div class="form-group mt-3">
                        <label><?php echo xlt('Information Source'); ?></label>
                        <?php echo generate_select_list('immunization_informationsource', 'immunization_informationsource', ($immuniz_information_source ?? ''), 'Select Information Source', ' ', '',
                            '',
                            '',
                            ['required' => 'required']);?>
                        
                    </div>

                        <div class="form-group mt-3">
                        <label><?php echo xlt('Stock'); ?></label>  
                         <div id="vendor_select_wrapper">
                         <?php generate_form_field(
                            array('data_type' => 14, 'field_id' => 'vendor_id',
                            'list_id' => '', 'edit_options' => 'V',
                            'description' => xl('Address book entry for the vendor')),
                            $vendor ?? ''
                                ); ?>
                    </div>
                    <input type="text" class="form-control mt-2" name="form_vendor_id_custom" id="form_vendor_id_text"
                value="" placeholder="<?php echo xla('Enter Stock'); ?>" style="display:none;">
            </div> 

                    <div class="form-group mt-3">
                        <label><?php echo xlt('Immunization'); ?></label>
                        <?php
                        // Modified 7/2009 by BM to incorporate the immunization items into the list_options listings
                        // generate_form_field(array('data_type' => 1,'field_id' => 'immunization_id','list_id' => 'immunizations','empty_title' => 'SKIP'), $immunization_id);
                        ?>

                <!-- <select class="form-control" name="form_immunization_id" id="immunization_id">
                        <option value=""><?php //echo xlt('Select Immunization'); ?></option>
                        <?php
                        // $result = sqlStatement("SELECT id, name FROM immunization_drug ORDER BY name");
                        // while ($row = sqlFetchArray($result)) {
                        //     $selected = ($immunization_id == $row['drug_id']) ? "selected" : "";
                        //     echo "<option value='" . attr($row['drug_id']) . "' $selected>" . text($row['name']) . "</option>";
                        // }
                        ?>
                    </select> -->

                    <select class="form-control" name="form_immunization_id" id="immunization_id" required>
                        <option value="" <?php echo empty($immunization_id) ? 'selected' : ''; ?>><?php echo xlt('Select Immunization'); ?></option>
                 <?php
                    $result = sqlStatement("SELECT drug_id, name FROM immunization_drug");
                    while ($row = sqlFetchArray($result)) {
                        $selected = ($row['drug_id'] == $immunization_id) ? "selected" : "";
                        echo "<option value='" . attr($row['drug_id']) . "' $selected>" . text($row['name']) . "</option>";
                    }
            // Add custom immunization option if editing a custom immunization
                if (isset($is_custom_edit) && $is_custom_edit && !empty($immunization_id)) {
                    echo "<option value='" . attr($immunization_id) . "' selected>" . text($immunization_id) . "</option>";
                }

                  ?>
                      </select>

                    </div>
                    <?php } else { ?>
                    <div class="form-group mt-3">
                        <label><?php echo xlt('Immunization'); ?> (<?php echo xlt('CVX Code'); ?>)</label>
                        <input type='text' class='form-control' size='10' name='cvx_code' id='cvx_code'
                            value='<?php echo attr($cvx_code ?? ''); ?>' onclick='sel_cvxcode(this)'
                            title='<?php echo xla('Click to select or change CVX code'); ?>'/>
                        <div id='cvx_description' class='d-inline float-right p-1 ml-2'>
                            <?php echo xlt($code_text ?? ''); ?>
                        </div>
                    </div>
                    <?php } ?>

                <input type="hidden" name="sel_drug_id" id="sel_drug_id" value="">

                    <div class="form-group mt-3">
                        <label><?php echo xlt('Date & Time Administered'); ?></label>
                        <input type='text' size='14' class='datetimepicker form-control' name="administered_date" id="administered_date"
                            value='<?php echo (!empty($administered_date)) ? attr($administered_date) : date('Y-m-d H:i'); ?>'
                            title='<?php echo xla('yyyy-mm-dd Hours(24):minutes'); ?>' required />
                    </div>
                    <div class="form-group mt-3">
                        <label><?php echo xlt('Amount Administered'); ?></label>
                        <input class='text form-control mb-2' type='text' id= "immuniz_amt_adminstrd" name="immuniz_amt_adminstrd" size="25" value=<?php echo attr($immuniz_amt_adminstrd ?? ''); ?>>
                        <?php echo generate_select_list("form_drug_units", "drug_units", ($drugunitselecteditem ?? ''), 'Select Drug Unit', '', '',
                            '',
                            '',
                            ['required' => 'required']); ?>
                    </div>
                    <div class="form-group mt-3">
                        <label><?php echo xlt('Immunization Expiration Date'); ?></label>
                        <input type='text' size='10' class='datepicker form-control' name="immuniz_exp_date" id="immuniz_exp_date"
                            value='<?php echo (!empty($immuniz_exp_date)) ? attr($immuniz_exp_date) : ''; ?>'
                            title='<?php echo xla('yyyy-mm-dd'); ?>' required/>
                    </div>
                      <!-- <div class="form-group mt-3">
                        <label><?php echo xlt('Stock'); ?></label>
                         <div id="vendor_select_wrapper">
                         <?php generate_form_field(
                            array('data_type' => 14, 'field_id' => 'vendor_id',
                            'list_id' => '', 'edit_options' => 'V',
                            'description' => xl('Address book entry for the vendor')),
                            $vendor ?? ''
                                ); ?>
                    </div>
                    <input type="text" class="form-control mt-2" name="form_vendor_id_custom" id="form_vendor_id_text"
                value="" placeholder="<?php echo xla('Enter Vendor'); ?>" style="display:none;">
            </div>                     -->
        
        
                    <div class="form-group mt-3">
                        <label><?php echo xlt('Immunization Lot Number'); ?></label>
                        <br>                        <div id="lot_number_select_wrapper">
                                             <?php if (!empty($lot_number)): ?>
                    <select class='auto form-control' id="lot_number" name="lot_number" required>
                        <option value="<?php echo attr($lot_number); ?>" selected><?php echo text($lot_number); ?></option>
                    </select>
                <?php else: ?>
                    <select class='auto form-control' id="lot_number" name="lot_number" required>
                        <option value="">Select Lot Number</option>
                    </select>
                <?php endif; ?>
                        </div>
                        <input type="text" class="form-control mt-2" name="lot_number" id="lot_number_text"
                            value="" placeholder="<?php echo xla('Enter Lot Number'); ?>" style="display:none;">
                        <!-- <select class='auto form-control' type='text' id="lot_number" name="lot_number" value="<?php echo attr($lot_number ?? ''); ?>"></select> -->
                    </div>
                    
               <!-- <div class="form-group mt-3">
                <label for="facility_id"><?php echo xlt('Facility'); ?>:</label>
                <select class="form-control" name="form_facility_id" id="facility_id" required>
                    <option value=""><?php echo xlt('Select Facility'); ?></option>
                    <option value="lower_valley">El Paso Clinic - Lower Valley</option>
                    <option value="henderson">El Paso Clinic - Henderson</option>
                    <option value="northeast">El Paso Clinic - North East</option>
                    <option value="westside">El Paso Clinic - Westside</option>
                    <option value="ep_community_clinic">El Paso Community Clinic</option>
                </select>
            </div> -->
                    <?php $query = "SELECT id, name FROM facility ORDER BY name";
                    $fres = sqlStatement($query);?>

                            <div class="form-group mt-3">
                    <label for="facility_id"><?php echo xlt('Facility'); ?>:</label>
                     <div id="facility_select_wrapper">
                    <select class="form-control" name="form_facility_id" id="facility_id" required>
                        <option value=""><?php echo xlt('Select Facility'); ?></option>
                        <?php while ($frow = sqlFetchArray($fres)) { ?>
                            <option value="<?php echo attr($frow['id']); ?>"
                            <?php
                                if (isset($facility) && $_GET['mode'] == 'edit' && $facility == $frow['id']) {
                                    echo ' selected';
                                } ?>>
                                <?php echo text($frow['name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                    <input type="text" class="form-control mt-2" name="form_facility_id_custom" id="form_facility_id_text"
        value="" placeholder="<?php echo xla('Enter Facility'); ?>" style="display:none;">
          </div>

                <div class="form-group mt-3">
                        <label><?php echo xlt('Immunization Manufacturer'); ?></label>
                        <div id="manufacturer_select_wrapper">
                                        <?php if (!empty($manufacturer)): ?>
                    <select class='auto form-control' id="manufacturer" name="manufacturer" required>
                        <option value="<?php echo attr($manufacturer); ?>" selected><?php echo text($manufacturer); ?></option>
                    </select>
                <?php else: ?>
                    <select class='auto form-control' id="manufacturer" name="manufacturer" required>
                        <option value="">Select Manufacturer</option>
                    </select>
                <?php endif; ?>
                        </div>
                        <input type="text" class="form-control mt-2" name="manufacturer" id="manufacturer_text"
                            value="" placeholder="<?php echo xla('Enter Manufacturer'); ?>" style="display:none;">
                        <!-- <?php //echo generate_select_list('manufacturer', 'Immunization_Manufacturer', ($manufacturer ?? ''), 'Select Manufacturer', ' ');?> -->
                    </div>
                    
                    
                    
                    <div class="form-row mt-3">
                        <div class="col-12">
                            <label><?php echo xlt('Name and Title of Immunization Administrator'); ?> <span style="color:red; font-weight:bold; font-size:1.2em;">*</span></label>
                        </div>
                        <div class="col-12 col-sm-5">
                            <input type="text" class="form-control" name="administered_by" id="administered_by" size="25" value="<?php echo attr($administered_by); ?>" required />
                        </div>
                        <div class="col-12 col-sm-2 text-center">
                            <?php echo xlt('or choose'); ?>
                        </div>
                        <div class="col-12 col-sm-5">
                        <!-- NEEDS WORK -->
                        <select class="form-control" name="administered_by_id" id='administered_by_id' required>
                                    <option value=""></option>
                                        <?php
                                        $sql = "select id, CONCAT_WS(' ',lname,fname) as full_name " .
                                            "from users where username != '' and password != 'NoLogin' " .
                                            "order by full_name";

                                        $result = sqlStatement($sql);
                                        while ($row = sqlFetchArray($result)) {
                                            echo '<OPTION VALUE=' . attr($row['id']);
                                            echo (isset($administered_by_id) && $administered_by_id != "" ? $administered_by_id : $_SESSION['authUserID']) == $row['id'] ? ' selected>' : '>';
                                            echo text($row['full_name']) . '</OPTION>';
                                        }
                                        ?>
                                </select>
                        </div>
                    </div>
                    <div class="form-group mt-3">
                        <label><?php echo xlt('Date Immunization Information Statements Given'); ?></label>
                        <input type='text' size='10' class='datepicker form-control' name="education_date" id="education_date"
                            value='<?php echo (!empty($education_date)) ? attr($education_date) : date('Y-m-d'); ?>'
                            title='<?php echo xla('yyyy-mm-dd'); ?>' />
                    </div>
                    <div class="form-group mt-3">
                        <label>
                            <?php echo xlt('Date of VIS Statement'); ?>
                            (<a href="https://www.cdc.gov/vaccines/pubs/vis/default.htm" title="<?php echo xla('Help'); ?>" rel="noopener" target="_blank">?</a>)
                        </label>
                        <input type='text' size='10' class='datepicker  form-control' name="vis_date" id="vis_date"
                            value='<?php echo (!empty($vis_date)) ? attr($vis_date) : ""; ?>'
                            title='<?php echo xla('yyyy-mm-dd'); ?>'  required/>
                    </div>

                    <div class="form-group mt-3">
                        <label><?php echo xlt('Route'); ?></label>
                        <input class="form-control" name="immuniz_route" id="immuniz_route"
                            type="text" value="<?php echo attr($immuniz_route ?? ''); ?>"
                            placeholder=""
                            onclick="sel_drug_route(this);"
                        title="<?php echo xla('Click to select or change route'); ?>" >
                     <!-- Select dropdown (for custom immunizations) -->
                    <div id="route_select_wrapper" style="display:none;">
                        <?php
                        // Ensure the default is empty string if $immuniz_route is empty or not set
                        $route_selected = (isset($immuniz_route) && trim($immuniz_route) !== '') ? $immuniz_route : '';

                        print_r($route_selected);
                        echo generate_select_list('immuniz_route_select', 'drug_route', $route_selected, 'Select Route', '', '', '', '', ['required' => 'required']);
                        ?>
                    </div>
                    
                    </div>
                    <div class="form-group mt-3">
                        <label><?php echo xlt('Administration Site'); ?></label>
                        <?php echo generate_select_list('immuniz_admin_ste', 'immunization_administered_site', ($immuniz_admin_ste ?? ''), 'Select Administration Site', ' ', '', '', '', ['required' => 'required'], false, 'proc_body_site');?>
                    </div>
                    <div class="form-group mt-3">
                        <label><?php echo xlt('Notes'); ?></label>
                        <textarea class="form-control" name="note" id="note" rows="5" cols="25"><?php echo text($note ?? ''); ?></textarea>
                    </div>
                    <!-- <div class="form-group mt-3">
                        <label><?php echo xlt('Information Source'); ?></label>
                        <?php echo generate_select_list('immunization_informationsource', 'immunization_informationsource', ($immuniz_information_source ?? ''), 'Select Information Source', ' ','',
                            '',
                            '',
                            ['required' => 'required']);?>
                        
                    </div> -->
                    <div class="form-group mt-3">
                        <label><?php echo xlt('Completion Status'); ?></label>
                        <?php echo generate_select_list('immuniz_completion_status', 'Immunization_Completion_Status', ($immuniz_completion_status ?? ''), 'Select Completion Status', ' ', '', '', '', ['required' => 'required']);?>
                    </div>
                    <div class="form-group mt-3">
                        <label><?php echo xlt('Substance Refusal Reason'); ?></label>
                        <?php echo generate_select_list('immunization_refusal_reason', 'immunization_refusal_reason', ($immuniz_refusal_reason ?? ''), 'Select Refusal Reason', ' ', '', '', '', ['required' => 'required']);?>
                    </div>
                    <div class="form-group mt-3">
                        <label><?php echo xlt('Reason Code'); ?></label>
                        <input class="code-selector-popup form-control immunizationReasonCode"
                               name="reason_code" id="reason_code" type="text" value="<?php echo attr($reason_code ?? ''); ?>"
                               placeholder="<?php echo xla("Select a reason code"); ?>"
                               required
                        />
                        <input type="hidden" name="reason_code_text" id="reason_code_text" value="<?php echo attr($reason_code_text ?? ''); ?>" />
                        <p  class="reason_code_text d-inline float-right p-1 ml-2 <?php echo empty($reason_code_text) ? "" : "d-none"; ?>"></p>
                    </div>

                    
                    <div class="form-group mt-3" id="cvx_code_wrapper" style="display:none;">
                        <label><?php echo xlt('Immunization'); ?> (<?php echo xlt('CVX Code'); ?>) <span style="color:red; font-weight:bold; font-size:1.2em;">*</span></label>
                        <input type='text' class='form-control' size='10' name='cvx_code' id='cvx_code'
                            value='<?php echo attr($cvx_code ?? ''); ?>' onclick='sel_cvxcode(this)'
                            title='<?php echo xla('Click to select or change CVX code'); ?>' />
                        <div id='cvx_description' class='d-inline float-right p-1 ml-2'>
                            <?php echo xlt($code_text ?? ''); ?>
                        </div>
                    </div>
                    <div class="form-group mt-3" style="display:none;">
			<label><?php echo xlt('Immunization Ordering Provider'); ?></label>
			<input type="hidden" name="ordered_by_id" id="ordered_by_id" value="<?php echo attr($_SESSION['authUserID']);?>">
                    </div>

                    <div class="row mt-3">
                        <div class="col-12 text-center">
                            <?php
                            if (!empty($entered_by)) { ?>
                                <p><?php echo xlt('Entered By'); ?> <?php echo text($entered_by); ?></p>
                            <?php } ?>

                            <?php if ($GLOBALS['observation_results_immunization']) { ?>
                            <button type="button" class="btn btn-primary" onclick="showObservationResultSection();" title='<?php echo xla('Click here to see observation results'); ?>'>
                                <?php echo xlt('See observation results'); ?>
                            </button>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="observation_results" style="display:none;">
                        <fieldset class="obs_res_head">
                            <legend><?php echo xlt('Observation Results'); ?></legend>
                            <div class="obs_res_table">
                                <?php
                                if (!empty($imm_obs_data) && count($imm_obs_data) > 0) {
                                    foreach ($imm_obs_data as $key => $value) {
                                        $key_snomed = 0;
                                        $key_cvx = 0;
                                        $style = '';?>
                                        <div class="form-row" id="or_tr_<?php echo attr(($key + 1)); ?>">
                                            <?php
                                            if ($id == 0) {
                                                if ($key == 0) {
                                                    $style = 'display: table-cell;width:765px !important';
                                                } else {
                                                    $style = 'display: none;width:765px !important';
                                                }
                                            } else {
                                                $style = 'display : table-cell;width:765px !important';
                                            }
                                            ?>
                                            <div class="form-group col" id="observation_criteria_td_<?php echo attr(($key + 1)); ?>" style="<?php echo $style;?>">
                                                <label><?php echo xlt('Observation Criteria');?></label>
                                                <br>
                                                <select class="form-control" id="observation_criteria_<?php echo attr(($key + 1)); ?>" name="observation_criteria[]" onchange="selectCriteria(this.id,this.value);">
                                                    <?php foreach ($observation_criteria as $keyo => $valo) { ?>
                                                        <option value="<?php echo attr($valo['option_id']);?>" <?php echo ($valo['option_id'] == $value['imo_criteria'] && $id != 0) ? 'selected = "selected"' : ''; ?> ><?php echo text($valo['title']);?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div <?php echo ($value['imo_criteria'] != 'funding_program_eligibility' || $id == 0) ? 'style="display: none;"' : ''; ?> class="form-group col observation_criteria_value_td" id="observation_criteria_value_td_<?php echo attr(($key + 1)); ?>">
                                                <label><?php echo xlt('Observation Criteria Value'); ?></label>
                                                <br>
                                                <select class="form-control" name="observation_criteria_value[]" id="observation_criteria_value_<?php echo attr(($key + 1)); ?>">
                                                    <?php foreach ($observation_criteria_value as $keyoc => $valoc) { ?>
                                                        <option value="<?php echo attr($valoc['option_id']);?>" <?php echo ($valoc['option_id'] == $value['imo_criteria_value']  && $id != 0) ? 'selected = "selected"' : ''; ?>><?php echo text($valoc['title']);?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div <?php echo ($value['imo_criteria'] != 'disease_with_presumed_immunity' || $id == 0) ? 'style="display: none;"' : ''; ?> class="form-group col code_serach_td" id="code_search_td_<?php echo attr(($key + 1)); ?>">
                                                <?php $key_snomed = ($key > 0) ? (($key * 2) + 2) : ($key + 2);?>
                                                <label><?php echo xlt('SNOMED-CT Code'); ?></label>
                                                <br>
                                                <input type="text" id="sct_code_<?php echo attr($key_snomed); ?>" name="sct_code[]" class="form-control code" value="<?php echo ($id != 0 && $value['imo_criteria'] == 'disease_with_presumed_immunity') ? attr($value['imo_code']) : ''; ?>" onclick='sel_code(this.id);' />
                                                <br />
                                                <span id="displaytext_<?php echo attr($key_snomed); ?>" class="displaytext d-block text-primary">
                                                    <?php echo text($value['imo_codetext']);?>
                                                </span>
                                                <input type="hidden" id="codetext_<?php echo attr($key_snomed); ?>" name="codetext[]" class="codetext" value="<?php echo attr($value['imo_codetext']); ?>" />
                                                <input type="hidden" value="SNOMED-CT" name="codetypehidden[]" id="codetypehidden<?php echo attr($key_snomed); ?>" />
                                            </div>
                                            <div <?php echo ($value['imo_criteria'] != 'vaccine_type' || $id == 0) ? 'style="display: none;"' : ''; ?> class="form-group col code_serach_vaccine_type_td" id="code_serach_vaccine_type_td_<?php echo attr(($key + 1)); ?>">
                                                <label><?php echo xlt('CVX Code');?></label>
                                                <br>
                                                <?php $key_cvx = ($key > 0) ? (($key * 2) + 3) : ($key + 3);?>
                                                <input type="text" class="form-control" id="cvx_code<?php echo attr($key_cvx); ?>" name="cvx_vac_type_code[]" onclick="sel_cvxcode(this);"
                                                    value="<?php echo ($id != 0 && $value['imo_criteria'] == 'vaccine_type') ? attr($value['imo_code']) : ''; ?>" />
                                                <div class="imm-imm-add-12" id="imm-imm-add-12<?php echo attr($key_cvx); ?>">
                                                    <?php echo ($id != 0 && $value['imo_criteria'] == 'vaccine_type') ? text($value['imo_codetext']) : ''; ?>
                                                </div>
                                                <input type="hidden"  value="CVX" name="code_type_hidden[]" id="code_type_hidden<?php echo attr($key_cvx); ?>" />
                                                <input type="hidden" class="code_text_hidden" name="code_text_hidden[]" id="code_text_hidden<?php echo attr($key_cvx); ?>" value="<?php echo ($id != 0 && $value['imo_criteria'] == 'vaccine_type') ? attr($value['imo_codetext']) : ''; ?>"/>
                                            </div>
                                            <div <?php echo ($value['imo_criteria'] != 'vaccine_type' || $id == 0) ? 'style="display: none;"' : ''; ?> class="form-group col vis_published_date_td" id="vis_published_date_td_<?php echo attr(($key + 1)); ?>">
                                                <label><?php echo xlt('Date VIS Published'); ?></label>
                                                <br>
                                                <?php
                                                $vis_published_dateval = $value['imo_vis_date_published'] ? $value['imo_vis_date_published'] : '';
                                                ?>
                                                <input type="text" class='datepicker form-control' name="vis_published_date[]" value="<?php echo ($id != 0 && $vis_published_dateval != 0) ? attr($vis_published_dateval) : ''; ?>" id="vis_published_date_<?php echo attr(($key + 1)); ?>" />
                                            </div>
                                            <div <?php echo ($value['imo_criteria'] != 'vaccine_type' || $id == 0) ? 'style="display: none;"' : ''; ?> class="form-group col vis_presented_date_td" id="vis_presented_date_td_<?php echo attr(($key + 1)); ?>">
                                                <label><?php echo xlt('Date VIS Presented'); ?></label>
                                                <br>
                                                <?php
                                                $vis_presented_dateval = $value['imo_vis_date_presented'] ? $value['imo_vis_date_presented'] : '';
                                                ?>
                                                <input type="text" class='datepicker form-control' name="vis_presented_date[]" value="<?php echo ($id != 0 && $vis_presented_dateval != 0) ? attr($vis_presented_dateval) : ''; ?>" id="vis_presented_date_<?php echo attr(($key + 1)); ?>" />
                                            </div>
                                            <?php if ($key != 0 && $id != 0) {?>
                                            <div class="form-group col">
                                                <button type="button" class="btn btn-danger btn-delete" id ="<?php echo attr(($key + 1)); ?>"  onclick="RemoveRow(this.id);" title='<?php echo xla('Click here to delete the row'); ?>'>
                                                    <?php echo xlt('Delete'); ?>
                                                </button>
                                            </div>
                                            <?php } ?>
                                        </div>
                                        <?php
                                    }
                                } else {?>
                                        <div class="form-row" id="or_tr_1">
                                            <div class="form-group col" id="observation_criteria_td_1">
                                                <label><?php echo xlt('Observation Criteria'); ?></label>
                                                <br>
                                                <select class="form-control" id="observation_criteria_1" name="observation_criteria[]" onchange="selectCriteria(this.id,this.value);">
                                                <?php foreach ($observation_criteria as $keyo => $valo) { ?>
                                                    <option value="<?php echo attr($valo['option_id']);?>" <?php echo (!empty($value['imo_criteria']) && ($valo['option_id'] == $value['imo_criteria']) && $id != 0) ? 'selected = "selected"' : ''; ?> ><?php echo text($valo['title']);?></option>
                                                <?php } ?>
                                                </select>
                                            </div>
                                            <div <?php echo (empty($value['imo_criteria']) || (!empty($value['imo_criteria']) && ($value['imo_criteria'] != 'funding_program_eligibility'))) ? 'style="display: none;"' : ''; ?> class="form-group col observation_criteria_value_td" id="observation_criteria_value_td_1">
                                                <label><?php echo xlt('Observation Criteria Value'); ?></label>
                                                <br>
                                                <select class="form-control" id="observation_criteria_value_1" name="observation_criteria_value[]">
                                                <?php foreach ($observation_criteria_value as $keyoc => $valoc) { ?>
                                                    <option value="<?php echo attr($valoc['option_id']);?>" <?php echo (!empty($value['imo_criteria_value']) && ($valoc['option_id'] == $value['imo_criteria_value']) && $id != 0) ? 'selected = "selected"' : ''; ?>><?php echo text($valoc['title']);?></option>
                                                <?php } ?>
                                                </select>
                                            </div>
                                            <div <?php echo (empty($value['imo_criteria']) || (!empty($value['imo_criteria']) && ($value['imo_criteria'] != 'disease_with_presumed_immunity')) || empty($id)) ? 'style="display: none;"' : ''; ?> class="form-group col code_serach_td" id="code_search_td_1">
                                                <label><?php echo xlt('SNOMED-CT Code');?></label>
                                                <br />
                                                <input type="text" id="sct_code_2" name="sct_code[]" class="code form-control" value="<?php echo (!empty($id) && !empty($value['imo_criteria']) && ($value['imo_criteria'] == 'disease_with_presumed_immunity')) ? attr($value['imo_code']) : ''; ?>"  onclick='sel_code(this.id);' />
                                                <span id="displaytext_2" class="displaytext d-block text-primary">
                                                    <?php echo text($value['imo_codetext'] ?? '');?>
                                                </span>
                                                <input type="hidden" id="codetext_2" name="codetext[]" class="codetext" value="<?php echo attr($value['imo_codetext'] ?? ''); ?>" />
                                                <input type="hidden" value="SNOMED-CT" name="codetypehidden[]" id="codetypehidden2" />
                                            </div>
                                            <div <?php echo (empty($value['imo_criteria']) || (!empty($value['imo_criteria']) && ($value['imo_criteria'] != 'vaccine_type')) || empty($id)) ? 'style="display: none;"' : ''; ?> class="form-group col code_serach_vaccine_type_td" id="code_serach_vaccine_type_td_1">
                                                <label><?php echo xlt('CVX Code'); ?></label>
                                                <br>
                                                <input type="text" class="form-control" id="cvx_code3" name="cvx_vac_type_code[]" onclick="sel_cvxcode(this);"
                                                    value="<?php echo (!empty($id) && (!empty($value['imo_criteria']) && ($value['imo_criteria'] == 'vaccine_type'))) ? attr($value['imo_code']) : ''; ?>" />
                                                <div class="imm-imm-add-12" id="imm-imm-add-123">
                                                    <?php echo (!empty($id) && (!empty($value['imo_criteria']) && ($value['imo_criteria'] == 'vaccine_type'))) ? text($value['imo_codetext']) : ''; ?>
                                                </div>
                                                <input type="hidden" value="CVX" name="code_type_hidden[]" id="code_type_hidden3"/>
                                                <input type="hidden" class="code_text_hidden" name="code_text_hidden[]" id="code_text_hidden3" value="<?php echo (!empty($id) && (!empty($value['imo_criteria']) && ($value['imo_criteria'] == 'vaccine_type'))) ? attr($value['imo_codetext']) : ''; ?>"/>
                                            </div>
                                            <div <?php echo (empty($value['imo_criteria']) || (!empty($value['imo_criteria']) && ($value['imo_criteria'] != 'vaccine_type')) || empty($id)) ? 'style="display: none;"' : ''; ?> class="form-group col vis_published_date_td" id="vis_published_date_td_1">
                                                <label><?php echo xlt('Date VIS Published'); ?></label>
                                                <br>
                                                <?php
                                                $vis_published_dateval = (!empty($value['imo_vis_date_published'])) ? $value['imo_vis_date_published'] : '';
                                                ?>
                                                <input type="text" class='datepicker form-control' name="vis_published_date[]" value="<?php echo (!empty($id) && $vis_published_dateval != 0) ? attr($vis_published_dateval) : ''; ?>" id="vis_published_date_1" />
                                            </div>
                                            <div <?php echo (empty($value['imo_criteria']) || (!empty($value['imo_criteria']) && ($value['imo_criteria'] != 'vaccine_type')) || empty($id)) ? 'style="display: none;"' : ''; ?> class="form-group col vis_presented_date_td" id="vis_presented_date_td_1">
                                                <label><?php echo xlt('Date VIS Presented'); ?></label>
                                                <br>
                                                <?php
                                                $vis_presented_dateval = (!empty($value['imo_vis_date_presented'])) ? $value['imo_vis_date_presented'] : '';
                                                ?>
                                                <input type="text" class='datepicker form-control' name="vis_presented_date[]" value="<?php echo (!empty($id) && $vis_presented_dateval != 0) ? attr($vis_presented_dateval) : ''; ?>" id="vis_presented_date_1" />
                                            </div>
                                        </div>
                                <?php } ?>
                            </div>

                            <div class="row">
                                <div class="col-12 text-center">
                                    <button type="button" class="btn btn-primary btn-sm btn-add" onclick="addNewRow();" title='<?php echo xla('Click here to add new row'); ?>'>
                                        <?php echo xlt('Add'); ?>
                                    </button>
                                </div>
                            </div>

                            <input type ="hidden" name="tr_count" id="tr_count" value="<?php echo (!empty($imm_obs_data) && count($imm_obs_data) > 0) ? attr(count($imm_obs_data)) : 1 ;?>" />
                            <input type="hidden" id="clickId" value="" />
                        </fieldset>
                    </div>
                    <div class="btn-group mt-3">
                        <button type="button" class="btn btn-primary btn-save" name="save" id="save" value="<?php echo xla('Save Immunization'); ?>">
                            <?php echo xlt('Save Immunization'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-print" name="print" id="print" value="<?php echo attr(xl('Print Record') . ' (' . xl('PDF') . ')'); ?>">
                            <?php echo xlt('Print Record (PDF)'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-print" name="printHtml" id="printHtml" value="<?php echo attr(xl('Print Record') . ' (' . xl('HTML') . ')'); ?>">
                            <?php echo xlt('Print Record (HTML)'); ?>
                        </button>
                        <button type="reset" class="btn btn-secondary btn-cancel" name="clear" id="clear" value="<?php echo xla('Clear'); ?>">
                            <?php echo xlt('Clear'); ?>
                        </button>

                         <button type="button" class="btn btn-primary query">
                            <?php echo xlt('Query Registry'); ?>
                        </button>
                    </div>
                </form>

                <div class="table-responsive" id="immunization_list">
                    <table class="table">
                        <!-- some columns are sortable -->
                        <tr>
                            <th>
                                <a href="javascript:top.restoreSession();location.href='immunizations.php?sortby=vacc';" title='<?php echo xla('Sort by vaccine'); ?>'>
                                    <?php echo xlt('Vaccine'); ?>
                                </a>
                                <span class='small'>
                                    <!-- <?php echo ($sortby == 'vacc') ? 'v' : ''; ?> -->
                                </span>
                            </th>
                            <th>
                                <a href="javascript:top.restoreSession();location.href='immunizations.php?sortby=date';" title='<?php echo xla('Sort by date'); ?>'>
                                    <?php echo xlt('Date'); ?>
                                </a>
                                <span class='small'>
                                    <?php echo ($sortby == 'date') ? 'v' : ''; ?>
                                </span>
                            </th>
                            <th><?php echo xlt('Amount'); ?></th>
                            <th><?php echo xlt('Expiration'); ?></th>
                            <th><?php echo xlt('Manufacturer'); ?></th>
                            <th><?php echo xlt('Lot Number'); ?></th>
                            <th><?php echo xlt('Administered By'); ?></th>
                            <th><?php echo xlt('Education Date'); ?></th>
                            <th><?php echo xlt('Route'); ?></th>
                            <th><?php echo xlt('Administered Site'); ?></th>
                            <th><?php echo xlt('Notes'); ?></th>
                            <th><?php echo xlt('Completion Status'); ?></th>
                            <th><?php echo xlt('Error'); ?></th>
                            <th>&nbsp;</th>
                        </tr>

                        <?php
                        $result = getImmunizationList($pid, ($_GET['sortby'] ?? null), true);
                         $sql = "SELECT * FROM immunizations WHERE patient_id = ? ORDER BY administered_date DESC";
                         $result = sqlStatement($sql, array($pid));

            while ($row = sqlFetchArray($result)) {
            $isError = !empty($row['added_erroneously']);
            $del_tag_open = $isError ? "<del>" : "";
            $del_tag_close = $isError ? "</del>" : "";

            // Get vaccine name from immunization_drug if possible
            $vaccine_name = '';
            if (!empty($row['is_custom']) && $row['is_custom'] == 1) {
                // For custom immunizations, use the custom_name
                $vaccine_name = $row['custom_name'] ?: 'Custom Immunization';
            } else {

            if (!empty($row['immunization_id'])) {
                $drug = sqlQuery("SELECT name FROM immunization_drug WHERE drug_id = ?", array($row['immunization_id']));
                $vaccine_name = $drug ? $drug['name'] : '';
            }
            if (!$vaccine_name && !empty($row['cvx_code'])) {
                $vaccine_name = $row['cvx_code'];
            }
          }

            // Get administered by name if possible
            $admin_by = '';
            if (!empty($row['administered_by_id'])) {
                $user = sqlQuery("SELECT CONCAT_WS(' ', fname, lname) AS full_name FROM users WHERE id = ?", array($row['administered_by_id']));
                $admin_by = $user ? $user['full_name'] : '';
            } else {
                $admin_by = $row['administered_by'];
            }

            if($row['amount_administered_unit'] != '') {
                // If amount_administered_unit is not empty, use it
                $display_unit = generate_display_field(
                    array('data_type' => '1', 'list_id' => 'drug_units'),
                    $row['amount_administered_unit']
                );
            } else {
                // If amount_administered_unit is empty, set display_unit to null
                $display_unit = null;
            }
       

            echo "<tr class='immrow' 
                data-id='" . attr($row['id']) . "'
                data-vaccine='" . attr($vaccine_name) . "'
                data-administered_date='" . attr($row['administered_date']) . "'
                data-amount='" . attr($row['amount_administered']) . "'
                data-immunization_id='" . attr($row['immunization_id']) . "'
                data-amount_unit='" . attr($row['amount_administered_unit']) . "'
                data-expiration='" . attr($row['expiration_date']) . "'
                data-manufacturer='" . attr($row['manufacturer']) . "'
                data-lot_number='" . attr($row['lot_number']) . "'
                data-administered_by='" . attr($admin_by) . "'
                data-administered_by_id='" . attr($row['administered_by_id']) . "'
                data-education_date='" . attr($row['education_date']) . "'
                data-route='" . attr($row['route']) . "'
                data-administration_site='" . attr($row['administration_site']) . "'
                data-note='" . attr($row['note']) . "'
                data-completion_status='" . attr($row['completion_status']) . "'
                data-vendor_id='" . attr($row['vendor_id']) . "'        
                data-facility_id='" . attr($row['facility_id']) . "'
                data-information_source ='" . attr($row['information_source']) . "'
                >";
            echo "<td>{$del_tag_open}" . text($vaccine_name) . "{$del_tag_close}</td>";
            echo "<td>{$del_tag_open}" . text($row['administered_date']) . "{$del_tag_close}</td>";
            // If value is empty, display 0 (no icon or span)
            $amt = ($row['amount_administered'] !== null && $row['amount_administered'] !== '') ? $row['amount_administered'] : '';
            $unit = $display_unit ? ' ' . text($display_unit) : '';
            echo "<td>{$del_tag_open}" . text($amt) . $unit . "{$del_tag_close}</td>";
            echo "<td>{$del_tag_open}" . text($row['expiration_date']) . "{$del_tag_close}</td>";
            echo "<td>{$del_tag_open}" . text($row['manufacturer']) . "{$del_tag_close}</td>";
            echo "<td>{$del_tag_open}" . text($row['lot_number']) . "{$del_tag_close}</td>";
            echo "<td>{$del_tag_open}" . text($admin_by) . "{$del_tag_close}</td>";
            echo "<td>{$del_tag_open}" . text($row['education_date']) . "{$del_tag_close}</td>";
            echo "<td>{$del_tag_open}" . text($row['route']) . "{$del_tag_close}</td>";
            echo "<td>{$del_tag_open}" . text($row['administration_site']) . "{$del_tag_close}</td>";
            echo "<td>{$del_tag_open}" . text($row['note']) . "{$del_tag_close}</td>";
            echo "<td>{$del_tag_open}" . text($row['completion_status']) . "{$del_tag_close}</td>";
            echo "<td><input type='checkbox' class='error' id='" . attr($row["id"]) . "' value='" . xlt('Error') . "' " . ($isError ? "checked" : "") . " /></td>";
            echo "<td><button type='button' class='btn btn-danger btn-delete' id='" . attr($row["id"]) . "' value='" . xlt('Delete') . "'>" . xlt('Delete') . "</button></td>";
            echo "</tr>";
        }




                        while ($row = sqlFetchArray($result)) {
                            $isError = $row['added_erroneously'];

                            if ($isError) {
                                $tr_title = 'title="' . xla("Entered in Error") . '"';
                            } else {
                                $tr_title = "";
                            }

                            if (!empty($id) && ($row["id"] == $id)) {
                                echo "<tr " . $tr_title . " class='immrow text selected' id='" . attr($row["id"]) . "'>";
                            } else {
                                echo "<tr " . $tr_title . " class='immrow text' id='" . attr($row["id"]) . "'>";
                            }

                            // Figure out which name to use (ie. from cvx list or from the custom list)
                            if ($GLOBALS['use_custom_immun_list']) {
                            //     $vaccine_display = generate_display_field(array('data_type' => '1','list_id' => 'immunizations'), $row['immunization_id']);
                            // } else {
                            //     if (!empty($row['code_text_short'])) {
                            //         $vaccine_display = xlt($row['code_text_short']);
                            //     } else {
                            //         $vaccine_display = generate_display_field(array('data_type' => '1','list_id' => 'immunizations'), $row['immunization_id']);
                            //     }

                                $vaccine_display = '';
                                    if (!empty($row['immunization_id'])) {
                                        $drug = sqlQuery("SELECT name FROM immunization_drug WHERE drug_id = ?", array($row['immunization_id']));
                                        $vaccine_display = $drug ? $drug['name'] : '';
                                    }
                                    // Fallback to list if not found
                                    if (!$vaccine_display) {
                                        $vaccine_display = generate_display_field(array('data_type' => '1','list_id' => 'immunizations'), $row['immunization_id']);
                                    }
                                } else {
                                    if (!empty($row['code_text_short'])) {
                                        $vaccine_display = xlt($row['code_text_short']);
                                    } else {
                                        // Try to get the name from immunization_drug table
                                        $vaccine_display = '';
                                        if (!empty($row['immunization_id'])) {
                                            $drug = sqlQuery("SELECT name FROM immunization_drug WHERE drug_id = ?", array($row['immunization_id']));
                                            $vaccine_display = $drug ? $drug['name'] : '';
                                        }
                                        // Fallback to list if not found
                                        if (!$vaccine_display) {
                                            $vaccine_display = generate_display_field(array('data_type' => '1','list_id' => 'immunizations'), $row['immunization_id']);
                                        }
                                    }


                            }

                            if ($isError) {
                                $del_tag_open = "<del>";
                                $del_tag_close = "</del>";
                            } else {
                                $del_tag_open = "";
                                $del_tag_close = "";
                            }

                            echo "<td>" . $del_tag_open . $vaccine_display . $del_tag_close . "</td>";

                            if ($row["administered_date"]) {
                                $administered_date_summary = new DateTime($row['administered_date']);
                                $administered_date_summary = $administered_date_summary->format('Y-m-d H:i');
                            } else {
                                $administered_date_summary = "";
                            }

                            echo "<td>" . $del_tag_open . text($administered_date_summary) . $del_tag_close . "</td>";
                            if ($row["amount_administered"] !== null && $row["amount_administered"] !== '') {
                                echo "<td>" . $del_tag_open . text($row["amount_administered"]) . " " . generate_display_field(array('data_type' => '1','list_id' => 'drug_units'), $row['amount_administered_unit']) . $del_tag_close . "</td>";
                            } else {
                                echo "<td>&nbsp;</td>";
                            }

                            echo "<td>" . $del_tag_open . text($row["expiration_date"]) . $del_tag_close . "</td>";
                            echo "<td>" . $del_tag_open . text($row["manufacturer"]) . $del_tag_close . "</td>";
                            echo "<td>" . $del_tag_open . text($row["lot_number"]) . $del_tag_close . "</td>";
                            echo "<td>" . $del_tag_open . text($row["administered_by"]) . $del_tag_close . "</td>";
                            echo "<td>" . $del_tag_open . text($row["education_date"]) . $del_tag_close . "</td>";
                            echo "<td>" . $del_tag_open . generate_display_field(array('data_type' => '1','list_id' => 'drug_route'), $row['route']) . $del_tag_close . "</td>";
                            echo "<td>" . $del_tag_open . generate_display_field(array('data_type' => '1','list_id' => 'immunization_administered_site'), $row['administration_site']) . $del_tag_close . "</td>";
                            echo "<td>" . $del_tag_open . text($row["note"]) . $del_tag_close . "</td>";
                            echo "<td>" . $del_tag_open . generate_display_field(array('data_type' => '1','list_id' => 'Immunization_Completion_Status'), $row['completion_status']) . $del_tag_close . "</td>";

                            if ($isError) {
                                $checkbox = "checked";
                            } else {
                                $checkbox = "";
                            }

                                echo "<td><input type='checkbox' class='error' id='" . attr($row["id"]) . "' value='" . xlt('Error') . "' " . $checkbox . " /></td>";

                                echo "<td><button type='button' class='btn btn-danger btn-delete' id='" . attr($row["id"]) . "' value='" . xlt('Delete') . "'>" . xlt('Delete') . "</button></td>";
                                echo "</tr>";
                        }
                        ?>
                    </table>
                </div> <!-- end immunizations -->
            </div>
        </div>
    </div>
</body>śś

<script>

 document.addEventListener('DOMContentLoaded', function() {

  var infoSourceField = document.getElementById('immunization_informationsource');
  var infoSource = infoSourceField.value;


    // --- Immunization Reason Code / CVX Code toggle logic ---
    function toggleReasonAndCVXFields() {
        var infoSourceField = document.getElementById('immunization_informationsource');
        var cvxCodeWrapper = document.getElementById('cvx_code_wrapper');
        var cvxCode = document.getElementById('cvx_code');
        var reasonCodeInput = document.querySelector('input[name="reason_code"]');
        var reasonCodeGroup = reasonCodeInput ? reasonCodeInput.closest('.form-group') : null;
        if (!infoSourceField || !cvxCodeWrapper || !cvxCode || !reasonCodeInput || !reasonCodeGroup) return;
        var infoSource = infoSourceField.value;
        if (infoSource === 'hist_inf_src_unspecified') {
            // Show CVX, hide Reason Code
            cvxCodeWrapper.style.display = '';
            cvxCode.disabled = false;
            reasonCodeGroup.style.display = 'none';
            reasonCodeInput.disabled = true;
            reasonCodeInput.value = '';
        } else {
            // Show Reason Code, hide CVX
            cvxCodeWrapper.style.display = 'none';
            cvxCode.disabled = true;
            cvxCode.value = '';
            var cvxDesc = document.getElementById('cvx_description');
            if (cvxDesc) cvxDesc.textContent = '';
            reasonCodeGroup.style.display = '';
            reasonCodeInput.disabled = false;
        }
    }

    // Run on page load
    toggleReasonAndCVXFields();
    // Also run when information source changes
    var infoSourceField = document.getElementById('immunization_informationsource');
    if (infoSourceField) {
        infoSourceField.addEventListener('change', toggleReasonAndCVXFields);
    }
    // Track original required fields on page load
    var originalRequiredFields = [];
    document.querySelectorAll('select[required], input[required], textarea[required]').forEach(function(el) {
        originalRequiredFields.push(el.id);
    });
     var infoSourceField = document.getElementById('immunization_informationsource');
       if (infoSourceField.value != 'hist_inf_src_unspecified') {
            var vendorSelect = document.getElementById('form_vendor_id');
            if (vendorSelect) {
                vendorSelect.setAttribute('required', 'required');
            }
        }
    // On every load, check information source and update required fields/asterisks
    function updateRequiredFieldsForInfoSource() {
        var infoSourceField = document.getElementById('immunization_informationsource');
        if (!infoSourceField) return;
        var infoSource = infoSourceField.value;
        var exceptionIds = ['immunization_informationsource', 'immunization_id', 'administered_date', 'administered_by', 'reason_code'];
        if (infoSource === 'hist_inf_src_unspecified') {
            // Remove required from all except exceptions
            document.querySelectorAll('select[required], input[required], textarea[required]').forEach(function(el) {
                if (!exceptionIds.includes(el.id)) {
                    el.removeAttribute('required');
                }
            });
            // Explicitly remove required from form_vendor_id for historical records
            var vendorField = document.getElementById('form_vendor_id');
            if (vendorField) vendorField.removeAttribute('required');
            exceptionIds.forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.setAttribute('required', 'required');
            });
        } else {
            // Restore required to all fields that were originally required
            originalRequiredFields.forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.setAttribute('required', 'required');
            });
            // Also explicitly restore required to Stock (immunization) field if present
            var stockField = document.getElementById('form_vendor_id');
            if (stockField) stockField.setAttribute('required', 'required');
        }
    }
    updateRequiredFieldsForInfoSource();
    // Also update on change of information source
    var infoSourceField = document.getElementById('immunization_informationsource');
    if (infoSourceField) {
        infoSourceField.addEventListener('change', updateRequiredFieldsForInfoSource);
    }

    if (infoSource != 'hist_inf_src_unspecified') {
            var vendorSelect = document.getElementById('form_vendor_id');
            if (vendorSelect) {
                vendorSelect.setAttribute('required', 'required');
            }
            var drug = document.getElementById('immunization_id');
             $("#sel_drug_id").val(drug.value);

        }




});
            var tr_count = $('#tr_count').val();

// jQuery stuff to make the page a little easier to use

$(function () {
    <?php if (!($useCVX)) { ?>
      $("#save").on("click", function(e) {
        e.preventDefault(); // Prevent default form submission
          SaveForm();
        
      });
    <?php } else { ?>
      $("#save").on("click", function(e) {
        e.preventDefault(); // Prevent default form submission
        if (validate_cvx()) {
          SaveForm();
        }
        else {
          return;
        }
      });
    <?php } ?>
    $("#print").on("click", function() { PrintForm("pdf"); });
    $("#printHtml").on("click", function() { PrintForm("html"); });
    $(".immrow").on("click", function() { EditImm(this); });
    $(".error").on("click", function(event) { ErrorImm(this); event.stopPropagation(); });
    $(".delete").on("click", function(event) { DeleteImm(this); event.stopPropagation(); });

    $(".immrow").on("mouseover", function() { $(this).toggleClass("highlight"); });
    $(".immrow").on("mouseout", function() { $(this).toggleClass("highlight"); });

    $("#administered_by_id").on("change", function() { $("#administered_by").val($("#administered_by_id :selected").text()); });

    $("#form_immunization_id").on("change", function() {
        if ( $(this).val() != "" ) {
            $("#cvx_code").val( "" );
            $("#cvx_description").text( "" );
            $("#cvx_code").trigger("change");
        }
    });
    $(".immunizationReasonCode").click();



  $('.datepicker').datetimepicker({
    <?php $datetimepicker_timepicker = false; ?>
    <?php $datetimepicker_showseconds = false; ?>
    <?php $datetimepicker_formatInput = false; ?>
    <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
    <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
  });
  $('.datetimepicker').datetimepicker({
    <?php $datetimepicker_timepicker = true; ?>
    <?php $datetimepicker_showseconds = false; ?>
    <?php $datetimepicker_formatInput = false; ?>
    <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
    <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
  });
  // special cases to deal with datepicker items that are added dynamically
  $(document).on('mouseover','.datepicker_dynamic', function(){
    $(this).datetimepicker({
        <?php $datetimepicker_timepicker = false; ?>
        <?php $datetimepicker_showseconds = false; ?>
        <?php $datetimepicker_formatInput = false; ?>
        <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
        <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
    });
  });
});

var PrintForm = function(typ) {
    top.restoreSession();
    newURL='shot_record.php?output=' + encodeURIComponent(typ) + '&sortby=' + <?php echo js_url($sortby); ?>;
    window.open(newURL, '_blank', "menubar=1,toolbar=1,scrollbars=1,resizable=1,width=600,height=450");
}

var SaveForm = function() {

  var isValid = true;
    var missingFields = [];
    
    // Check all required fields
    $('[required]').each(function() {
        var $field = $(this);
        // Skip hidden/disabled fields
        if ($field.is(':hidden') || $field.is(':disabled')) {
            return;
        }
        var value = $field.val();
        var fieldName = $field.attr('name') || $field.attr('id') || 'Unknown field';
        // Accept 0 and 0.0 as valid values
        if (value === undefined || value === null || (typeof value === 'string' && value.trim() === '')) {
            isValid = false;
            missingFields.push(fieldName);
            $field.css('border', '2px solid red'); // Highlight missing field
        } else {
            $field.css('border', ''); // Remove highlight if filled
        }
    });
    
    if (!isValid) {
        alert('Please fill in all required fields:\n\n');
        return false;
    }


    top.restoreSession();
    $("#add_immunization").submit();
}


var EditImm = function(imm) {
    top.restoreSession();
    var immId = imm.getAttribute('data-id');
    // Get information_source from data attribute if available
    var infoSource = imm.getAttribute('data-information_source') || '';
    // If not present, fallback to triggering the edit as before
    location.href='immunizations.php?mode=edit&id=' + encodeURIComponent(immId) + "&csrf_token_form=" + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>;

    var drugId = imm.getAttribute('data-immunization_id') || '';
    
    if(drugId) {
        // If immunization_id is present, set it in the form
        $('#sel_drug_id').val(drugId);
    } else {
        // If not, clear the field
        $('#sel_drug_id').val('');
    }
    
    // After navigation, on DOMContentLoaded, toggle fields based on infoSource
    // This part is for when the form is already loaded (single-page, no reload)
    // If you use full reload, this is not needed, but for SPA/iframe, you may want:
    
    if (infoSource === 'hist_inf_src_unspecified') {
        $('#cvx_code_wrapper').show();
        $('#cvx_code').prop('disabled', false);
        $('.form-group:has(input[name="reason_code"])').hide();
        $('input[name="reason_code"]').prop('disabled', true).val('');
    } else {
        $('#cvx_code_wrapper').hide();
        $('#cvx_code').prop('disabled', true).val('');
        $('#cvx_description').text('');
        $('.form-group:has(input[name="reason_code"])').show();
        $('input[name="reason_code"]').prop('disabled', false);
    }
    
}

var DeleteImm = function(imm) {
    if (confirm(<?php echo xlj('This action cannot be undone.'); ?> + "\n" + <?php echo xlj('Do you wish to PERMANENTLY delete this immunization record?'); ?>)) {
        top.restoreSession();
        location.href='immunizations.php?mode=delete&id=' + encodeURIComponent(imm.id) + "&csrf_token_form=" + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>;
    }
}

var ErrorImm = function(imm) {
    top.restoreSession();
    location.href='immunizations.php?mode=added_error&id=' + encodeURIComponent(imm.id) + '&isError=' + encodeURIComponent(imm.checked) + "&csrf_token_form=" + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>;
}

//This is for callback by the find-code popup.
//Appends to or erases the current list of diagnoses.
function set_related(codetype, code, selector, codedesc) {

    if(codetype == 'CVX') {
    var f = document.forms[0][current_sel_name];
        if(!f.length) {
    var s = f.value;

    if (code) {
        s = code;
    }
    else {
        s = '';
    }

    f.value = s;
            if(f.name != 'cvx_vac_type_code[]'){
    $("#cvx_description").text( codedesc );
    $("#form_immunization_id").attr( "value", "" );
    $("#form_immunization_id").trigger("change");
            }else{
                id_arr = f.id.split('cvx_code');
                counter = id_arr[1];
                $('#imm-imm-add-12'+counter).html(codedesc);
                $('#code_text_hidden'+counter).val(codedesc);
            }
        }else {
            var index = document.forms[0][current_sel_name].length -1;
            var elem = document.forms[0][current_sel_name][index];
            var ss = elem.value;
            if (code) {
                ss = code;
            }
            else {
                ss = '';
}

            elem.value = ss;
            arr = elem.id.split('cvx_code');
            count = arr[1];
            $('#imm-imm-add-12'+count).html(codedesc);
            $('#code_text_hidden'+count).val(codedesc);
        }
    }else {
        var checkId = $('#clickId').val();
        $("#sct_code_" + checkId).val(code);
        $("#codetext_" + checkId).val(codedesc);
        $("#displaytext_" + checkId).html(codedesc);
    }
}


window.set_related = set_related;

function sel_reasonCode(e) {
    let target = e.currentTarget;
    if (!(target && target.name)) {
        console.error("Could not find DOMNode name for event target");
        return;
    }
    current_sel_name = e.currentTarget.name;

    let previousSelCodeFunction = window.set_related;
    let restoreCallback = function() {
        window.set_related = previousSelCodeFunction;
    };

    let set_relatedReasonCode = function(codetype, code, selector, codedesc) {

        let field = document.forms[0][current_sel_name];
        let field_text = document.forms[0][current_sel_name + "_text"];
        let text = document.querySelector("." + current_sel_name + "_text");
        if (typeof code == 'string' && code.trim() != '') {
            field.value = codetype + ":" + code;
            if (field_text && text) {
                field_text.value = codedesc;
                text.classList.remove("d-none"); // remove anything hiding the attribute.
                text.innerText = codedesc;
            }
        } else {
            field.value = "";
            if (field_text && text) {
                field_text.value = "";
                text.classList.add("d-none");
                text.innerText = "";
            }
        }
    };

    let opts = {
        callBack: {
            call: restoreCallback
        }
    };
    // we are going to replace our global function for the time, and it will get setback in the callback

    window.set_related = set_relatedReasonCode;
    window.top.restoreSession();
    window.dlgopen("../encounter/find_code_popup.php?default=SNOMED-CT"
        , '_blank', 700, 400, false, undefined, opts);
}

// This is for callback by the find-code popup.
// Returns the array of currently selected codes with each element in codetype:code format.
function get_related() {
    return new Array();
}

// This is for callback by the find-code popup.
// Deletes the specified codetype:code from the currently selected list.
function del_related(s) {
    var e = document.forms[0][current_sel_name];
    e.value = '';
    $("#cvx_description").text('');
    $("#form_immunization_id").attr("value", "");
    $("#form_immunization_id").trigger("change");
}

// This invokes the find-code popup.
function sel_cvxcode(e) {
 current_sel_name = e.name;
 dlgopen('../encounter/find_code_dynamic.php?codetype=CVX,VALUESET', '_blank', 900, 600);
    // var width = 900;`
    // var height = 600;
    // var left = (window.screen.width / 2) - (width / 2);
    // var top = (window.screen.height / 2) - (height / 2);
//   window.open(
//         '../encounter/find_cvxcode_popup.php',
//         '_blank',
//         'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes'
//     );
}

// This ensures the cvx centric entry is filled.
function validate_cvx() {
 if (document.add_immunization.cvx_code.value>0) {
  return true;
 }
 else {
  document.add_immunization.cvx_code.style.backgroundColor="red";
  document.add_immunization.cvx_code.focus();
  return false;
 }
}

function showObservationResultSection()
{
    $('.observation_results').slideToggle();
}

function selectCriteria(id,value)
{
    var arr = id.split('observation_criteria_');
    var key = arr[1];
    if (value == 'funding_program_eligibility') {
        if(key > 1) {
            var target = $("#observation_criteria_value_"+key);
            $.ajax({
                type: "POST",
                url:  "immunizations.php",
                dataType: "json",
                data: {
                    type : 'duplicate_row_2',
                    csrf_token_form: <?php echo js_escape(CsrfUtils::collectCsrfToken()); ?>
                },
                success: function(thedata){
                    $.each(thedata,function(i,item) {
                        target.append($('<option />').val(item.option_id).text(item.title));
                    });
                    $('#observation_criteria_value_'+key+' option[value=""]').prop('selected', true);
                },
                error:function(){
                  alert("ajax error");
                }
            });
        }
        $("#code_search_td_"+key).hide();
        $("#vis_published_date_td_"+key).hide();
        $("#vis_presented_date_td_"+key).hide();
        $("#code_serach_vaccine_type_td_"+key).hide();
        $("#observation_criteria_value_td_"+key).show();
    }
    if (value == 'vaccine_type')
    {
        $("#observation_criteria_value_td_"+key).hide();
        $("#code_search_td_"+key).hide();
        $("#code_serach_vaccine_type_td_"+key).show();
        $("#vis_published_date_td_"+key).show();
        $("#vis_presented_date_td_"+key).show();
        if(key == 1) {
            key = parseInt(key) + 2;
        }
        else {
            key = (parseInt(key) * 2) + 1;
        }
        $("#cvx_code"+key).css("background-color", "red");
        $("#cvx_code"+key).focus();
        return false;
    }
    if (value == 'disease_with_presumed_immunity')
    {
        $("#observation_criteria_value_td_"+key).hide();
        $("#vis_published_date_td_"+key).hide();
        $("#vis_presented_date_td_"+key).hide();
        $("#code_serach_vaccine_type_td_"+key).hide();
        $("#code_search_td_"+key).show();
        if(key == 1) {
            key = parseInt(key) + 1;
        }
        else {
            key = (parseInt(key) * 2);
        }
        $("#sct_code_"+key).css("background-color", "red");
        $("#sct_code_"+key).focus();
        return false;
    }
    if (value == '')
    {
        $("#observation_criteria_value_td_"+key).hide();
        $("#vis_published_date_td_"+key).hide();
        $("#vis_presented_date_td_"+key).hide();
        $("#code_serach_vaccine_type_td_"+key).hide();
        $("#code_search_td_"+key).hide();
    }
}

function RemoveRow(id)
{
    tr_count = parseInt($("#tr_count").val());
    new_tr_count = tr_count-1;
    $("#tr_count").val(new_tr_count);
    $("#or_tr_"+id).remove();
}

function addNewRow()
{
    tr_count = parseInt($("#tr_count").val());
    new_tr_count = tr_count+1;
    new_tr_count_2 = (new_tr_count * 2);
    new_tr_count_3 = (new_tr_count *2) + 1;
    $("#tr_count").val(new_tr_count);
    label1 = <?php echo xlj('Observation Criteria'); ?>;
    label2 = <?php echo xlj('Observation Criteria Value'); ?>;
    label3 = <?php echo xlj('SNOMED-CT Code'); ?>;
    label4 = <?php echo xlj('CVX Code'); ?>;
    label5 = <?php echo xlj('Date VIS Published'); ?>;
    label6 = <?php echo xlj('Click here to choose a date'); ?>;
    label7 = <?php echo xlj('Date VIS Presented'); ?>;
    label8 = <?php echo xlj('Click here to choose a date'); ?>;
    label9 = <?php echo xlj('Click here to delete the row'); ?>;
    label10 = <?php echo xlj('Delete'); ?>;
    str = '<div class="form-row" id ="or_tr_'+new_tr_count+'">'+
              '<div class="form-group col" id ="observation_criteria_td_'+new_tr_count+'"><label>'+label1+'</label><select class="form-control" id="observation_criteria_'+new_tr_count+'" name="observation_criteria[]" onchange="selectCriteria(this.id,this.value);"></select>'+
              '</div>'+
              '<div id="observation_criteria_value_td_'+new_tr_count+'" class="form-group col observation_criteria_value_td" style="display: none;"><label>'+label2+'</label><select class="form-control" name="observation_criteria_value[]" id="observation_criteria_value_'+new_tr_count+'"></select>'+
              '</div>'+
              '<div class="form-group col code_serach_td" id="code_search_td_'+new_tr_count+'" style="display: none;"><label>'+label3+'</label>'+
                '<input type="text" id="sct_code_'+new_tr_count_2+'" name="sct_code[]" class="code form-control" onclick=sel_code(this.id) /><br />'+
                '<span id="displaytext_'+new_tr_count_2+'" class="displaytext d-block text-primary"></span>'+
                '<input type="hidden" id="codetext_'+new_tr_count_2+'" name="codetext[]" class="codetext" />'+
                '<input type="hidden"  value="SNOMED-CT" name="codetypehidden[]" id="codetypehidden'+new_tr_count_2+'" /> '+
             '</div>'+
             '<div class="form-group col code_serach_vaccine_type_td" id="code_serach_vaccine_type_td_'+new_tr_count+'" style="display: none;"><label>'+label4+'</label>'+
               '<input type="text" class="form-control" id="cvx_code'+new_tr_count_3+'" name="cvx_vac_type_code[]" onclick=sel_cvxcode(this); />'+
               '<div class="imm-imm-add-12" id="imm-imm-add-12'+new_tr_count_3+'"></div> '+
               '<input type="hidden"  value="CVX" name="code_type_hidden[]" id="code_type_hidden'+new_tr_count_3+'" /> '+
               '<input type="hidden" class="code_text_hidden" name="code_text_hidden[]" id="code_text_hidden'+new_tr_count_3+'" value="" />'+
             '</div>'+
             '<div id="vis_published_date_td_'+new_tr_count+'" class="form-group col vis_published_date_td" style="display: none;"><label>'+label5+'</label><input type="text" class="datepicker_dynamic form-control" name= "vis_published_date[]" id ="vis_published_date_'+new_tr_count+'" />'+
             '</div>'+
             '<div id="vis_presented_date_td_'+new_tr_count+'" class="form-group col vis_presented_date_td" style="display: none;"><label>'+label7+'</label><input type="text" class="datepicker_dynamic form-control" name= "vis_presented_date[]" id ="vis_presented_date_'+new_tr_count+'" />'+
             '</div>'+
             '<div class="form-group col d-flex align-items-end justify-content-center"><button type="button" class="btn btn-danger btn-delete" id="' + new_tr_count +'" onclick="RemoveRow(this.id);" title="' + label9 + '">' + label10 + '</button></div></div>';

    $(".obs_res_table").append(str);

    var ajax_url = 'immunizations.php';
    var target = $("#observation_criteria_"+new_tr_count);
    $.ajax({
        type: "POST",
        url: ajax_url,
        dataType: "json",
        data: {
            type : 'duplicate_row',
            csrf_token_form: <?php echo js_escape(CsrfUtils::collectCsrfToken()); ?>
        },
        success: function(thedata){ 
            $.each(thedata,function(i,item) {
                target.append($('<option></option>').val(item.option_id).text(item.title));
            });
            $('#observation_criteria_'+new_tr_count+' option[value=""]').prop('selected', true);
        },
        error:function(){
          alert("ajax error");
        }
    });
}

function sel_code(id)
{
    id = id.split('sct_code_');
    var checkId = id[1];
    $('#clickId').val(checkId);
    dlgopen('<?php echo $GLOBALS['webroot'] . "/interface/patient_file/encounter/" ?>find_code_popup.php', '_blank', 700, 400);
}

 function normalize(text) {
    return text.toLowerCase().replace(/\s+/g, '').replace(/-/g, '');
}


var originalOptions = [];

document.addEventListener('DOMContentLoaded', function () {
    // Save facility select box options (value and text) in an array on page load
    window.facilityOptionsArray = [];
    var facilitySelect = document.getElementById('facility_id');
    if (facilitySelect) {
        for (var i = 0; i < facilitySelect.options.length; i++) {
            var opt = facilitySelect.options[i];
            window.facilityOptionsArray.push({
                value: opt.value,
                text: opt.text
            });
        }

  
    } else {
        console.error('Facility select element not found.');

    }
    // Store original options before Select2 initialization
    $('#immunization_id option').each(function() {
        if (this.value) {
            originalOptions.push(this.value);
        }
    });

    // Store facility options for mapping later
    window.facilityOptionsArray = [];
    $('#facility_id option').each(function() {
        if (this.value) {
            window.facilityOptionsArray.push({
                value: this.value,
                text: $(this).text()
            });
        }
    });
    var isCustomEdit = <?php echo isset($is_custom_edit) && $is_custom_edit ? 'true' : 'false'; ?>;

 
    if (isCustomEdit) {
        // Use proper JSON encoding to handle special characters
        var customName = <?php echo json_encode($immunization_id ?? ''); ?>;
        var $select = $('#immunization_id');
        
        // Add custom name to original options so it's recognized as valid
        if (customName && originalOptions.indexOf(customName) === -1) {
            originalOptions.push(customName);
        }
        
        // Create and add the custom option BEFORE Select2 is used
        if (customName && $select.find('option[value="' + customName + '"]').length === 0) {
            var newOption = new Option(customName, customName, true, true);
            $select.append(newOption);
        }
    
    }

    
    $('#immunization_id').select2({
        placeholder: "Select or type to search",
        allowClear: true,
        width: '100%',
        tags: true 
    });

    if (isCustomEdit) {
        var customName = <?php echo json_encode($immunization_id ?? ''); ?>;
        var $select = $('#immunization_id');
        
        // Set the value WITHOUT triggering change events
        $select.val(customName);
        // Only trigger Select2's visual update, not the change handler
        $select.trigger('change.select2');
        
    }
    
    // Initialize field visibility based on current immunization
    var currentImmunizationId = $('#immunization_id').val();
    var isCustomOnLoad = false;
    
    if (currentImmunizationId && originalOptions.indexOf(currentImmunizationId) === -1) {
        isCustomOnLoad = true;
    }
    
    // Set initial state for manufacturer fields
    var $manufacturerSelect = $('#manufacturer');
    var $manufacturerInput = $('#manufacturer_text');
    if (isCustomOnLoad) {
        $manufacturerSelect.hide().prop('disabled', true);
        $manufacturerInput.show().prop('disabled', false);
    } else {
        $manufacturerSelect.show().prop('disabled', false);
        $manufacturerInput.hide().prop('disabled', true);
    }
    
    // Set initial state for lot number fields
    var $lotNumberSelect = $('#lot_number');
    var $lotNumberInput = $('#lot_number_text');
    if (isCustomOnLoad) {
        $lotNumberSelect.hide().prop('disabled', true);
        $lotNumberInput.show().prop('disabled', false);
    } else {
        $lotNumberSelect.show().prop('disabled', false);
        $lotNumberInput.hide().prop('disabled', true);
    }


    var immunizationSelect = document.getElementById('immunization_id');
    var lotNumberSelect = document.getElementById('lot_number');
    var expDateInput = document.getElementById('immuniz_exp_date');
    var manufacturerInput = document.getElementById('manufacturer');
    var facilitySelect = document.getElementById('facility_id');

    if (immunizationSelect) {
        $('#immunization_id').on('change', function () {
            var drug_id = this.value;
            var $select = $(this);
            var isCustom = false;            // Check if the value exists in the original options loaded from server
            if (drug_id && originalOptions.indexOf(drug_id) === -1) {
                isCustom = true;
            }
            

           // Get vendor_id from the visible/enabled field (select or text input)
           var vendor = '';
           var $vendorSelect = $('#form_vendor_id');
           var $vendorInput = $('#form_vendor_id_text');
           if ($vendorSelect.is(':visible') && !$vendorSelect.prop('disabled')) {
               vendor = $vendorSelect.val();
           } else if ($vendorInput.is(':visible') && !$vendorInput.prop('disabled')) {
               vendor = $vendorInput.val();
           }
   
            $('#immuniz_admin_ste').val('');
            $('#note').val('');

            const exceptionIds = ['immunization_informationsource', 'immunization_id', 'administered_date', 'administered_by', 'reason_code'];
            const clearFieldsOnHist = ['education_date', 'administered_by', 'ordered_by_id'];
            // const $infoSource = $('#immunization_informationsource');
            const requiredFields = [];

                // Store originally required fields (excluding exceptions)
                $('select[required], input[required], textarea[required]').each(function () {
                    const id = $(this).attr('id');
                    if (!exceptionIds.includes(id)) {
                        requiredFields.push(this);
                    }
                });

                   if (isCustom) {
                        requiredFields.forEach(function (field) {
                            $(field).removeAttr('required');
                        });

                        clearFieldsOnHist.forEach(function (id) {
                            $('#' + id).val('');
                        });
                    }

            // Clear these fields only for custom immunizations
            if (isCustom) {
                // $('#immunization_informationsource').val('');
                $('#immuniz_amt_administered').val('');
                $('#form_drug_units').val('');
                $('#immuniz_completion_status').val('');
                $('#immunization_refusal_reason').val('');
                $('#manafacturer').val('');

                $('#administered_by').val('');
                $('#education_date').val('');
                $('#ordered_by_id').val('');

                var educationDateInput = document.getElementById('education_date');
                var visDateInput = document.getElementById('vis_date');
                // if (educationDateInput) educationDateInput.value = '';
                if (visDateInput) visDateInput.value = '';
            } else {
                // For standard immunizations, set default values if not already set
                // if (!$('#immunization_informationsource').val()) {
                //     $('#immunization_informationsource').val('new_immunization_record');
                // }
 
                if (!$('#immunization_refusal_reason').val()) {
                    $('#immunization_refusal_reason').val('patient_accepted');
                }
                if (!$('#immuniz_completion_status').val()) {
                    $('#immuniz_completion_status').val('Completed');
               }
            }
  
            lotNumberSelect.innerHTML = '';
            if (expDateInput) expDateInput.value = '';
            if (manufacturerInput) manufacturerInput.value = '';
            
            // Clear facility dropdown when drug changes
            if (facilitySelect) {
                facilitySelect.innerHTML = '';
                var emptyFacilityOption = document.createElement('option');
                emptyFacilityOption.value = '';
                emptyFacilityOption.text = 'Select Facility';
                facilitySelect.appendChild(emptyFacilityOption);
            }
            
            if (!drug_id || isCustom) {
                var emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.text = '';
                lotNumberSelect.appendChild(emptyOption);
                // Vendor
                var $vendorSelect = $('#form_vendor_id');
                var $vendorInput = $('#form_vendor_id_text');
                if (isCustom) {
                    $vendorSelect.hide().prop('disabled', true).removeAttr('name');
                    $vendorInput.show().prop('disabled', false).attr('name', 'form_vendor_id');
                } else {
                    $vendorSelect.show().prop('disabled', false).attr('name', 'form_vendor_id');
                    $vendorInput.hide().prop('disabled', true).removeAttr('name');
                }
                // Facility
                var $facilitySelect = $('#facility_id');
                var $facilityInput = $('#form_facility_id_text');
               
                if (isCustom) {
                    $facilitySelect.hide().prop('disabled', true).removeAttr('name');
                    $facilityInput.show().prop('disabled', false).attr('name', 'form_facility_id');
                } else {
                    $facilitySelect.show().prop('disabled', false).attr('name', 'form_facility_id');
                    $facilityInput.hide().prop('disabled', true).removeAttr('name');
                }
                // Manufacturer
                var $manufacturerSelect = $('#manufacturer');
                var $manufacturerInput = $('#manufacturer_text');
                
                if (isCustom) {
                    $manufacturerSelect.hide().prop('disabled', true);
                    $manufacturerInput.show().prop('disabled', false);
                } else {
                    $manufacturerSelect.show().prop('disabled', false);
                    $manufacturerInput.hide().prop('disabled', true);
                }
                // Lot Number
                var $lotNumberSelect = $('#lot_number');
                var $lotNumberInput = $('#lot_number_text');
              
                if (isCustom) {
                    $lotNumberSelect.hide().prop('disabled', true);
                    $lotNumberInput.show().prop('disabled', false);
                } else {
                    $lotNumberSelect.show().prop('disabled', false);
                    $lotNumberInput.hide().prop('disabled', true);
                }
                // Route
                var $routeInput = $('#immuniz_route');
                var $routeSelectWrapper = $('#route_select_wrapper');
                var $routeSelect = $('#immuniz_route_select');
                
                if (isCustom) {
                    $routeInput.hide().prop('disabled', true);
                    $routeSelectWrapper.show();
                    $routeSelect.prop('disabled', false);
                    $routeSelect.val('');
                    if ($routeInput.val()) {
                        $routeSelect.val($routeInput.val());
                    }
                } else {
                    $routeInput.show().prop('disabled', false);
                    $routeSelectWrapper.hide();
                    $routeSelect.prop('disabled', true);
                    if ($routeSelect.val()) {
                        $routeInput.val($routeSelect.val());
                    }
                    $routeSelect.on('change', function() {
                        $routeInput.val($(this).val());
                    });
                }
                // CVX Code and Reason Code field toggling (mutually exclusive)
                var $cvxCodeWrapper = $('#cvx_code_wrapper');
                var $cvxCodeInput = $('#cvx_code');
                var $reasonCodeWrapper = $('.form-group:has(input[name="reason_code"])');
                var $reasonCodeInput = $('input[name="reason_code"]');
                
                if (isCustom) {
                    $cvxCodeWrapper.show();
                    $cvxCodeInput.prop('disabled', false);
                    $reasonCodeWrapper.hide();
                    $reasonCodeInput.prop('disabled', true).val('');
                } else {
                    $cvxCodeWrapper.hide();
                    $cvxCodeInput.prop('disabled', true).val('');
                    $('#cvx_description').text('');
                    $reasonCodeWrapper.show();
                    $reasonCodeInput.prop('disabled', false);
                }
                return;
            }

            fetch('get_immunizations.php?vendor=' + vendor + '&drug_id=' + encodeURIComponent(drug_id))
                .then(response => response.text())
                .then(text => {
                    console.log('Raw response:', text);
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e, text);
                        return;
                    }

                    $('#sel_drug_id').val(data.first.drug_id);
                    var record = $('#immunization_informationsource').val();

                    // Set fields from first item
                    if (data.first) {
                        
                        if(data.lots == '' && record != 'hist_inf_src_unspecified') {
                            alert('No lot exists for the selected stock and drug.');
                            $('#form_vendor_id').val('');
                            $('#immunization_id').val('').trigger('change.select2');
                            return;
                        }

                        // Populate Lot Number dropdown (unique lot numbers only)
                        var $lotNumberSelect = $('#lot_number');
                        $lotNumberSelect.empty();
                        $lotNumberSelect.append($('<option>', { value: '', text: 'Select Lot' }));
                        if (Array.isArray(data.lots)) {
                            data.lots.forEach(function(lot) {
                                $lotNumberSelect.append($('<option>', {
                                    value: lot.lot_number,
                                    text: lot.lot_number
                                }));
                            });
                        }
                        
                        // Populate Manufacturer dropdown with all available manufacturers
                        // var $manufacturerSelect = $('#manufacturer');
                        // $manufacturerSelect.empty();
                        // $manufacturerSelect.append($('<option>', { value: '', text: 'Select Manufacturer' }));
                        // if (Array.isArray(data.manufacturers)) {
                        //     data.manufacturers.forEach(function(manufacturer) {
                        //         $manufacturerSelect.append($('<option>', {
                        //             value: manufacturer,
                        //             text: manufacturer
                        //         }));
                        //     });
                        // }

                        // Fix: define $facilitySelect before using it
                        // var $facilitySelect = $('#facility_id');
                        // $facilitySelect.empty();
                        // $facilitySelect.append($('<option>', { value: '', text: 'Select Facility' }));
                        // var selectedFacilityId = '';
                        // if (data.matched_facility_id) {
                        //     selectedFacilityId = String(data.matched_facility_id);
                        // } else if (Array.isArray(data.facilities) && data.facilities.length === 1) {
                        //     selectedFacilityId = String(data.facilities[0].id);
                        // }
                        // if (Array.isArray(data.facilities)) {
                        //     data.facilities.forEach(function(fac) {
                        //         $facilitySelect.append($('<option>', {
                        //             value: fac.id,
                        //             text: fac.name
                        //         }));
                        //     });
                        // }
                        // if (selectedFacilityId) {
                        //     $facilitySelect.val(selectedFacilityId);
                        // }

                        if (expDateInput && Array.isArray(data.lots) && data.lots.length > 0) {
                            expDateInput.value = data.lots[0].expiration || '';
                        }

                      

                        const amtInput1 = document.getElementById('immuniz_amt_adminstrd');
                        if (amtInput1) {
                            amtInput1.value = data.first.size;
                            // amtInput.value = (data.first.size !== undefined && data.first.size !== null && data.first.size !== '')
                            //     ? data.first.size
                            //     : (data.size || '');
                        }

                        const unitsSelect = document.getElementById('form_drug_units');
                        if (unitsSelect) {
                            let unitValue = data.first.unit !== undefined && data.first.unit !== null && data.first.unit !== ''
                                ? data.first.unit
                                : (data.unit || '');
                            if (unitValue === 'ML') {
                                unitValue = '9';
                            }
                            unitsSelect.value = unitValue;
                        }

                        // if (manufacturerInput && data.first.manufacturer) {
                        //     let found = false;
                        //     for (let i = 0; i < manufacturerInput.options.length; i++) {
                        //         if (manufacturerInput.options[i].value === data.first.manufacturer) {
                        //             found = true;
                        //             break;
                        //         }
                        //     }

                        //     if (!found) {
                        //         let opt = document.createElement('option');
                        //         opt.value = data.first.manufacturer;
                        //         opt.text = data.first.manufacturer;
                        //         manufacturerInput.appendChild(opt);
                        //     }
                        //     // Don't auto-select manufacturer - let user choose
                        // } 
                        
                        const routeInput = document.getElementById('immuniz_route');
                        if (routeInput && data.first.route) {
                            routeInput.value = data.first.route;
                        }
                        
                        // Set VIS date if available
                        const visDateInput = document.getElementById('vis_date');
                        if (visDateInput && data.first.vis_date) {
                            visDateInput.value = data.first.vis_date;
                        }
                        
                        // Manufacturer will be populated when facility is selected

                        const reasonCodeInput = document.getElementById('reason_code');
                        if (reasonCodeInput && data.first.cvx_code) {
                            reasonCodeInput.value = data.first.cvx_code;
                        }
                        const reasonCodetext = document.getElementById('reason_code_text');
                        if (reasonCodetext && data.first.cvx_text) {
                            // Also update the visible description if needed
                            const descElem = document.querySelector('.reason_code_text');
                            if (descElem) {
                                descElem.textContent = data.first.cvx_text;
                                descElem.classList.remove('d-none');
                            }
                        }
                    }

                       // Clear fields first when changing drug (except for historical records)
                    if(record == 'hist_inf_src_unspecified') {
                        $('#immuniz_amt_adminstrd').val('');
                        $('#form_drug_units').val('');
                        $('#immuniz_completion_status').val('');
                        $('#immunization_refusal_reason').val('');
                        $('#manufacturer').val(''); // Fixed typo: was 'manafacturer'
                        $('#administered_by').val('');
                        $('#education_date').val('');
                        $('#ordered_by_id').val('');
                        $('#immuniz_route').val('');
                        $('#vis_date').val('');
                    }

                })
                .catch(err => {
                    const errorOption = document.createElement('option');
                    errorOption.value = '';
                    errorOption.text = 'Error loading lots';
                    lotNumberSelect.appendChild(errorOption);
                    console.error('Fetch error:', err);
                });
        });
    }


});

document.getElementById('lot_number').addEventListener('change', function () {
    var lotNumber = this.value;
    var drugId = document.getElementById('immunization_id').value;
    var facilitySelect = document.getElementById('facility_id');
    var manufacturerSelect = document.getElementById('manufacturer');

       var vendor = '';
           var $vendorSelect = $('#form_vendor_id');
           var $vendorInput = $('#form_vendor_id_text');
           if ($vendorSelect.is(':visible') && !$vendorSelect.prop('disabled')) {
               vendor = $vendorSelect.val();
           } else if ($vendorInput.is(':visible') && !$vendorInput.prop('disabled')) {
               vendor = $vendorInput.val();
           }

    if (lotNumber && drugId) {
        fetch('get_immunizations.php?vendor=' + vendor + '&drug_id=' + encodeURIComponent(drugId) + '&lot_number=' + encodeURIComponent(lotNumber))
            .then(response => response.json())
            .then(data => {

               console.log(vendor);
               console.log("Fetched lot data:", data);
               
                // Check if no data found for this lot
                if (data.no_manufacturers || (Array.isArray(data.manufacturers) && data.manufacturers.length === 0)) {
                    alert('🚨 INVENTORY SETUP REQUIRED 🚨\n\n' +
                        'Lot Number: ' + lotNumber + '\n' +
                        'Issue: No inventory records found\n\n' +
                        'This lot does not have any inventory records.\n' +
                        'Please contact inventory management to add inventory data for this lot.');
                    
                    // Clear the lot selection since there are no records
                    document.getElementById('lot_number').value = '';
                    
                    // Clear manufacturer dropdown
                    if (manufacturerSelect) {
                        manufacturerSelect.innerHTML = '';
                        var emptyOption = document.createElement('option');
                        emptyOption.value = '';
                        emptyOption.text = 'Select Manufacturer';
                        manufacturerSelect.appendChild(emptyOption);
                    }
                    
                    // Clear facility dropdown
                    if (facilitySelect) {
                        facilitySelect.innerHTML = '';
                        var emptyFacilityOption = document.createElement('option');
                        emptyFacilityOption.value = '';
                        emptyFacilityOption.text = 'Select Facility';
                        facilitySelect.appendChild(emptyFacilityOption);
                    }
                    
                    return;
                }
                
                // Populate Facility dropdown with facilities for this specific lot
                if (facilitySelect && Array.isArray(data.lot_facilities)) {
                    facilitySelect.innerHTML = '';
                    var defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.text = 'Select Facility';
                    facilitySelect.appendChild(defaultOption);
                    
                    // Use a Set to track unique facility IDs to avoid duplicates
                    var addedFacilities = new Set();
                    data.lot_facilities.forEach(function(facility) {
                        // Only add if we haven't already added this facility ID
                        if (!addedFacilities.has(facility.id)) {
                            var option = document.createElement('option');
                            option.value = facility.id;
                            option.text = facility.name;
                            facilitySelect.appendChild(option);
                            addedFacilities.add(facility.id);
                        }
                    });
                }
                
                // Clear manufacturer dropdown (will be populated when facility is selected)
                if (manufacturerSelect) {
                    manufacturerSelect.innerHTML = '';
                    var defaultManufacturerOption = document.createElement('option');
                    defaultManufacturerOption.value = '';
                    defaultManufacturerOption.text = 'Select Manufacturer';
                    manufacturerSelect.appendChild(defaultManufacturerOption);
                }
                
                // Auto-fill other fields
                if (data.first) {
                    if (data.first.expiration_date) {
                        document.getElementById('immuniz_exp_date').value = data.first.expiration_date;
                    }
                    
                    // var amtInput = document.getElementById('immuniz_amt_adminstrd');
                    // console.log("lot", data.first.size);
                    // if (amtInput) {
                    //     amtInput.value = (data.first.size !== undefined && data.first.size!== null && data.first.size !== '')
                    //         ? data.first.size
                    //         : (data.size || '');
                    //         console.log("lot amount", amtInput.value);
                    // }
                    if (data.first.unit) {
                        document.getElementById('form_drug_units').value = data.first.unit;
                    }
                    if (data.first.route) {
                        document.getElementById('immuniz_route').value = data.first.route;
                    }
                    if (data.first.vis_date) {
                        document.getElementById('vis_date').value = data.first.vis_date;
                    }
                    
                    // Calculate total available stock across all facilities for this lot
                    var totalStock = 0;
                    if (data.lot_facilities && Array.isArray(data.lot_facilities)) {
                        data.lot_facilities.forEach(function(facility) {
                            if (facility.on_hand && facility.on_hand > 0) {
                                totalStock += parseFloat(facility.on_hand) || 0;
                            }
                        });
                    }
                    
                    // Check if total stock is depleted across all facilities
                    if (totalStock <= 0) {
                        alert('🚨 CRITICAL INVENTORY ALERT 🚨\n\n' +
                            'Lot Number: ' + lotNumber + '\n' +
                            'Total Available Stock: ' + totalStock + ' units\n' +
                            'Status: DEPLETED ACROSS ALL FACILITIES\n\n' +
                            'Please contact inventory management or select an alternative lot or vendor.');
                        document.getElementById('lot_number').value = '';
                        return;
                    }
                }

                // Populate facility dropdown with all facilities for this lot, preselect the first one
                // if (facilitySelect && Array.isArray(data.lot_facilities)) {
                //     facilitySelect.innerHTML = '';
                //     facilitySelect.appendChild(new Option('Select Facility', ''));
                //     var seenFacilityIds = {};
                //     data.lot_facilities.forEach(function(fac) {
                //         if (!seenFacilityIds[fac.id]) {
                //             var opt = new Option(fac.name, String(fac.id));
                //             facilitySelect.appendChild(opt);
                //             seenFacilityIds[fac.id] = true;
                //         }
                //     });
                //     // Preselect the first facility (if any)
                //     if (data.lot_facilities.length > 0) {
                //         facilitySelect.value = String(data.lot_facilities[0].id);
                //         facilitySelect.dispatchEvent(new Event('change'));
                //     }
                // }
            })
            .catch(error => {
                console.error('Error fetching lot-specific immunization data:', error);
            });
    }
});

// Manufacturer change event handler
document.getElementById('manufacturer').addEventListener('change', function () {
    var manufacturer = this.value;
    console.log('Manufacturer changed to:', manufacturer);
    
    // Manufacturer is the final selection in the cascade
    // No need to update other fields since manufacturer comes last
    // Facility should not be changed when manufacturer is selected
});

// Function to check stock depletion for current lot and facility selection
function checkStockDepletion() {
    var drugId = document.getElementById('immunization_id').value;
    var lotNumber = document.getElementById('lot_number').value;
    var facilityId = document.getElementById('facility_id').value;
    
    // Get vendor_id from the visible/enabled field
    var vendor = '';
    var $vendorSelect = $('#form_vendor_id');
    var $vendorInput = $('#form_vendor_id_text');
    if ($vendorSelect.is(':visible') && !$vendorSelect.prop('disabled')) {
        vendor = $vendorSelect.val();
    } else if ($vendorInput.is(':visible') && !$vendorInput.prop('disabled')) {
        vendor = $vendorInput.val();
    }
    
    // Only check if we have all required fields
    if (!drugId || !vendor || !lotNumber || !facilityId) {
        return;
    }
    
    // Build API URL to get stock data for this specific combination
    var apiUrl = 'get_immunizations.php?vendor=' + vendor + '&drug_id=' + encodeURIComponent(drugId) + 
                 '&lot_number=' + encodeURIComponent(lotNumber) + '&facility_id=' + encodeURIComponent(facilityId);
    
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            // Check if this specific facility has stock for this lot
            var facilityStock = 0;
            if (data.lot_facilities && Array.isArray(data.lot_facilities)) {
                data.lot_facilities.forEach(function(facility) {
                    if (facility.id == facilityId && facility.on_hand) {
                        facilityStock = parseFloat(facility.on_hand) || 0;
                    }
                });
            }
            
            // Show depletion alert if this facility has no stock
            if (facilityStock <= 0) {
                alert('🚨 CRITICAL INVENTORY ALERT 🚨\n\n' +
                    'Lot Number: ' + lotNumber + '\n' +
                    'Facility: ' + $('#facility_id option:selected').text() + '\n' +
                    'Available Stock: ' + facilityStock + ' units\n' +
                    'Status: DEPLETED AT THIS FACILITY\n\n' +
                    'Please select a different facility or contact inventory management.');
                
                // Clear the facility selection since this facility has no stock
                document.getElementById('facility_id').value = '';
                
                // Also clear manufacturer since facility changed
                var manufacturerSelect = document.getElementById('manufacturer');
                if (manufacturerSelect) {
                    manufacturerSelect.innerHTML = '';
                    var defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.text = 'Select Manufacturer';
                    manufacturerSelect.appendChild(defaultOption);
                }
            }
        })
        .catch(error => {
            console.error('Error checking stock depletion:', error);
        });
}

// Function to update manufacturers based on drug, vendor, lot, and facility
function updateManufacturers() {
    var drugId = document.getElementById('immunization_id').value;
    var lotNumber = document.getElementById('lot_number').value;
    var facilityId = document.getElementById('facility_id').value;
    var manufacturerSelect = document.getElementById('manufacturer');
    
    // Get vendor_id from the visible/enabled field
    var vendor = '';
    var $vendorSelect = $('#form_vendor_id');
    var $vendorInput = $('#form_vendor_id_text');
    if ($vendorSelect.is(':visible') && !$vendorSelect.prop('disabled')) {
        vendor = $vendorSelect.val();
    } else if ($vendorInput.is(':visible') && !$vendorInput.prop('disabled')) {
        vendor = $vendorInput.val();
    }
    
    // Only proceed if we have drug, vendor, lot, and facility
    if (!drugId || !vendor || !lotNumber || !facilityId || !manufacturerSelect) {
        console.log('updateManufacturers: Missing required fields', {
            drugId: drugId,
            vendor: vendor,
            lotNumber: lotNumber,
            facilityId: facilityId
        });
        return;
    }
    
    // Build API URL with all parameters
    var apiUrl = 'get_immunizations.php?vendor=' + vendor + '&drug_id=' + encodeURIComponent(drugId) + 
                 '&lot_number=' + encodeURIComponent(lotNumber) + '&facility_id=' + encodeURIComponent(facilityId);
    
    console.log('Updating manufacturers with URL:', apiUrl);
    
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            console.log("Updating manufacturers based on selection:", data);
            
            // Store current selection to preserve it if possible
            var currentManufacturer = manufacturerSelect.value;
            
            // Clear and populate manufacturer dropdown
            manufacturerSelect.innerHTML = '';
            var defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.text = 'Select Manufacturer';
            manufacturerSelect.appendChild(defaultOption);
            
            if (Array.isArray(data.manufacturers) && data.manufacturers.length > 0) {
                var manufacturerFound = false;
                data.manufacturers.forEach(function(manufacturer) {
                    var option = document.createElement('option');
                    option.value = manufacturer;
                    option.text = manufacturer;
                    manufacturerSelect.appendChild(option);
                    
                    // Restore previous selection if it's still available
                    if (currentManufacturer && manufacturer === currentManufacturer) {
                        manufacturerFound = true;
                    }
                });
                
                // Restore previous selection if found in new list
                if (manufacturerFound) {
                    manufacturerSelect.value = currentManufacturer;
                }
            } else {
                // Show message if no manufacturers found
                var noManufacturerOption = document.createElement('option');
                noManufacturerOption.value = '';
                noManufacturerOption.text = 'No manufacturers available for this selection';
                noManufacturerOption.disabled = true;
                manufacturerSelect.appendChild(noManufacturerOption);
                
                console.warn('No manufacturers found for drug:', drugId, 'vendor:', vendor, 'lot:', lotNumber);
            }
        })
        .catch(error => {
            console.error('Error updating manufacturers:', error);
            // Add error option to dropdown
            manufacturerSelect.innerHTML = '';
            var errorOption = document.createElement('option');
            errorOption.value = '';
            errorOption.text = 'Error loading manufacturers';
            errorOption.disabled = true;
            manufacturerSelect.appendChild(errorOption);
        });
}

// Add event handlers to update manufacturers when key fields change
$(document).ready(function() {
    // When vendor/stock changes, clear downstream fields but don't populate manufacturer yet
    $('#form_vendor_id').on('change', function() {
        // Clear lot number, facility, and manufacturer when vendor changes
        $('#lot_number').empty().append('<option value="">Select Lot Number</option>');
        $('#facility_id').empty().append('<option value="">Select Facility</option>');
        $('#manufacturer').empty().append('<option value="">Select Manufacturer</option>');
        
        console.log('Vendor changed - cleared downstream fields');
    });
    
    // Update manufacturers when drug changes (handled in existing drug change handler)
    // This is already handled in the immunization_id change event
    
    // Update manufacturers when facility changes - this is when manufacturer should populate
    $('#facility_id').on('change', function() {
        // First check if this facility has stock for the selected lot
        checkStockDepletion();
        
        // Then update manufacturers (only if stock check passes)
        setTimeout(function() {
            if ($('#facility_id').val()) { // Only if facility is still selected after stock check
                updateManufacturers();
            }
        }, 100);
    });
});


$(document).on('click', '.immrow', function () {
    $('.immrow').removeClass('selected');
    $(this).addClass('selected');
    const facilityId = $(this).data('facility_id');
    const infoSource = $(this).data('information_source');
    const $select = $('#immunization_id');
    const $vendorSelect = $('#form_vendor_id');
    const $facilitySelect = $('#facility_id');

    if (vaccineId && $select.length) {
        // Check if the option exists
        const $option = $select.find(`option[value="${vaccineId}"]`);
        if ($option.length > 0) {
            // Mark it selected manually and refresh
            $select.val(null).trigger('change.select2'); // clear selection first
            $select.val(vaccineId).trigger('change'); // trigger full change event for all logic
            // Wait for the change handler to finish (adjust delay as needed)
            setTimeout(function () {
                if (vendorId && $vendorSelect.length) {
                    $vendorSelect.val(vendorId).trigger('change');
                }
                if (facilityId && $facilitySelect.length) {
                    $facilitySelect.val(facilityId).trigger('change');
                }
                // Toggle CVX/Reason code fields based on information_source
                if (infoSource === 'hist_inf_src_unspecified') {
                    $('#cvx_code_wrapper').show();
                    $('#cvx_code').prop('disabled', false);
                    $('.form-group:has(input[name="reason_code"])').hide();
                    $('input[name="reason_code"]').prop('disabled', true).val('');
                } else {
                    $('#cvx_code_wrapper').hide();
                    $('#cvx_code').prop('disabled', true).val('');
                    $('#cvx_description').text('');
                    $('.form-group:has(input[name="reason_code"])').show();
                    $('input[name="reason_code"]').prop('disabled', false);
                }
            }, 200); // 200ms delay, adjust if needed
        } else {
            console.warn('Option not found for value:', vaccineId);
        }
    }
});


$(document).ready(function () {

    const exceptionIds = ['immunization_informationsource', 'immunization_id', 'administered_date', 'administered_by', 'reason_code'];
    const clearFieldsOnHist = ['education_date', 'administered_by', 'administered_by_id', 'ordered_by_id'];
    const $infoSource = $('#immunization_informationsource');

    // Helper to get all required fields except exceptions
    function getRequiredFields() {
        const fields = [];
        $('select[required], input[required], textarea[required]').each(function () {
            const id = $(this).attr('id');
            if (!exceptionIds.includes(id)) {
                fields.push(this);
            }
        });
        return fields;
    }

    let savedOrderedById = $('#ordered_by_id').val();
    let savedAdminId = $('#administered_by_id').val();
    let savedEducationDate = $('#education_date').val();

    // On page load, if in edit mode, ensure required fields are set
    if ($('#mode').val() === 'edit') {
        const requiredFields = getRequiredFields();
        requiredFields.forEach(function (field) {
            $(field).attr('required', 'required');
        });
    }

    $infoSource.on('change', function () {
        const selected = $(this).val();
        const requiredFields = getRequiredFields();

        // CVX/Reason toggle elements
        const $cvxCodeWrapper = $('#cvx_code_wrapper');
        const $cvxCodeInput = $('#cvx_code');
        const $reasonCodeWrapper = $('.form-group:has(input[name="reason_code"])');
        const $reasonCodeInput = $('input[name="reason_code"]');
        savedAdminId = $('#administered_by_id').val();

        if (selected === 'hist_inf_src_unspecified') {
            // Clear all form fields except disabled/hidden and the information source field itself
            $('#add_immunization').find('input, select, textarea').not(':disabled, :hidden, #immunization_informationsource').each(function () {
                $(this).val('');
            });
            // Remove required from all except information source
            requiredFields.forEach(function (field) {
                $(field).removeAttr('required');
            });
            // Hide/show vendor fields as before
            $('#form_vendor_id').hide().prop('disabled', true).attr('name', 'form_vendor_id');
            $('#form_vendor_id_text').show().prop('disabled', false).removeAttr('name');
        } else {
            // Not historical — restore required fields
            requiredFields.forEach(function (field) {
                $(field).attr('required', 'required');
            });

            if (selected === 'new_immunization_record') {
                // Clear all form fields
                $('#add_immunization').find('input, select, textarea').not(':disabled, :hidden').each(function () {
                    if (!exceptionIds.includes(this.id)) {
                        $(this).val('');
                    }
                });

                // Set administered_date to current date
                var now = new Date();
                var yyyy = now.getFullYear();
                var mm = String(now.getMonth() + 1).padStart(2, '0');
                var dd = String(now.getDate()).padStart(2, '0');
                var hh = String(now.getHours()).padStart(2, '0');
                var min = String(now.getMinutes()).padStart(2, '0');
                var formatted = yyyy + '-' + mm + '-' + dd + ' ' + hh + ':' + min;
                $('#administered_date').val(formatted);

                // Reason code should be shown, CVX code hidden
                $cvxCodeWrapper.hide();
                $cvxCodeInput.prop('disabled', true).val('');
                $('#cvx_description').text('');

                $reasonCodeWrapper.show();
                $reasonCodeInput.prop('disabled', false).val('');

                $('#form_vendor_id').show().prop('disabled', false).attr('name', 'form_vendor_id');
                $('#form_vendor_id_text').hide().prop('disabled', true).removeAttr('name');

                if (savedAdminId) {
                    $('#administered_by_id').val(savedAdminId).trigger('change');
                }
                if (savedOrderedById) {
                    $('#ordered_by_id').val(savedOrderedById).trigger('change');
                }
                if (savedEducationDate) {
                    $('#education_date').val(savedEducationDate);
                }
            }
        }

        $('#immunization_id').val('').trigger('change.select2');
    });

  $('#form_vendor_id').on('change', function () {
    const vendorId = $(this).val();
    // Clear all dependent fields when vendor changes
    try {
        // Immunization selection
        const $immSel = $('#immunization_id');
        $immSel.val(null).trigger('change.select2');

        // Lot selectors/inputs
        $('#lot_number').empty().append(new Option('Select Lot', ''));
        $('#lot_number_text').val('');

    // Manufacturer selectors/inputs
    $('#manufacturer').empty().append(new Option('Select Manufacturer', ''));
    $('#manufacturer_text').val('');

        // Facility selectors/inputs
        $('#facility_id').empty().append(new Option('Select Facility', ''));
        $('#form_facility_id_text').val('');

        // Other dependent fields
        $('#immuniz_exp_date').val('');
        $('#form_drug_units').val('');
        $('#immuniz_route').val('');
        $('#immuniz_route_select').val('');
        $('#vis_date').val('');
        $('#immuniz_amt_adminstrd').val('');
        $('#immuniz_amt_administered').val('');
        $('#immuniz_completion_status').val('');
        $('#immunization_refusal_reason').val('');
        $('#reason_code').val('');
        $('#reason_code_text').val('');
        $('#cvx_code').val('');
        $('#cvx_description').text('');
    } catch (e) {
        console.warn('Field clear on vendor change encountered an issue:', e);
    }
 
    $.ajax({
        url: 'druglist.php',
        type: 'POST',
        data: { vendor_id: vendorId },
        success: function (data) {
            const $select = $('#immunization_id');
            $select.empty(); // remove old options

            $select.append(new Option('Select Immunization', '', true, true)); // optional placeholder

            data.forEach(function (item) {
                $select.append(new Option(item.name, item.id));
            });
            // Reset cache of original options to reflect the new vendor's list
            originalOptions = [];
            $('#immunization_id option').each(function() {
                if (this.value) {
                    originalOptions.push(this.value);
                }
            });
        },
        error: function () {
            alert('Unable to load immunizations for selected vendor.');
        }
    });

  
});

    $('.query').on('click', function (e) {
        var $btn = $(this);
        // Remove focus immediately so it doesn't remain highlighted after alert
        $btn.blur();
        var pid = <?php echo (int)$_SESSION['pid']; ?>;
        $.ajax({
            type: 'POST',
            url: 'build_qbp_message.php',
            data: { pid: pid },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    alert("✅ HL7 QBP message saved:\n" + res.filename);
                } else {
                    alert("❌ Error: " + res.message);
                } 
                // Safety blur in case focus returned
                $btn.blur();
            },
            error: function () {
                alert("❌ Failed to contact server.");
                $btn.blur();
            }
        });
    });
 

});

</script>


</html>

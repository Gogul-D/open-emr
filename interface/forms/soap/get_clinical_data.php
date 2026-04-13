<?php
// Fetch LBF form notes by form name
function getLBFNotesByFormName($pid, $formName) {
    $notes = [];
    // Get ALL forms for the given patient and form name, ordered by date DESC
    $sql = "SELECT f.form_id, f.date, f.encounter, f.user FROM forms f WHERE f.pid = ? AND f.form_name = ? AND f.deleted = 0 ORDER BY f.date DESC";
    $forms = sqlStatement($sql, [$pid, $formName]);
    while ($row = sqlFetchArray($forms)) {
        $form_id = $row['form_id'];
        $date = $row['date'];
        $encounter = $row['encounter'];
        $author = $row['user'];
        // Fetch all fields for this form_id, omitting signature fields
        $fields = sqlStatement("SELECT field_id, field_value FROM lbf_data WHERE form_id = ?", [$form_id]);
        $content = [];
        while ($field = sqlFetchArray($fields)) {
            $fid = strtolower($field['field_id']);
            // Omit any field that looks like a signature (contains 'sig' or 'signature')
            if (strpos($fid, 'sig') !== false || strpos($fid, 'signature') !== false) {
                continue;
            }
            // Only include the value, not the field name
            $value = trim($field['field_value']);
            if ($value !== '') {
                $content[] = $value;
            }
        }
        // Only add if there's actual content
        if (!empty($content)) {
            $header = $formName . ' (' . date('Y-m-d', strtotime($date)) . ')';
            if (!empty($author)) {
                $header .= ' - ' . $author;
            }
            $notes[] = [
                'header' => $header,
                'content' => implode("\n", $content),
                'encounter' => $encounter,
                'form_id' => $form_id,
                'author' => $author
            ];
        }
    }
    return $notes;
}

/**
 * Clinical Data API for SOAP Form Drag and Drop
 * 
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Custom Implementation
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Start output buffering to catch any unwanted output
ob_start();

require_once(__DIR__ . "/../../globals.php");
require_once("$srcdir/api.inc.php");
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/lists.inc.php");
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

// Clear any unwanted output that might have been generated during includes
ob_clean();

// Verify CSRF token
if (!CsrfUtils::verifyCsrfToken($_GET["csrf_token_form"] ?? $_POST["csrf_token_form"] ?? '')) {
    CsrfUtils::csrfNotVerified();
}

// Get patient ID
$pid = $_SESSION['pid'] ?? $_GET['pid'] ?? $_POST['pid'] ?? 0;
$encounter = $_SESSION['encounter'] ?? $_GET['encounter'] ?? $_POST['encounter'] ?? 0;

header('Content-Type: application/json');

if (!$pid) {
    ob_end_clean();
    echo json_encode(['error' => 'No patient ID provided']);
    exit;
}

// Function to get LBF forms data
function getLBFForms($pid) {
  
    
    $forms = array(); // Initialize return array
    
    // Get all forms that belong to the Orders category
    $ordersForms = array();
    
    // Query layout_group_properties for Orders forms
    $layoutQuery = "SELECT grp_title FROM layout_group_properties WHERE grp_mapping = 'Orders' AND grp_title != ''";
    $layoutResult = sqlStatement($layoutQuery);
    while ($row = sqlFetchArray($layoutResult)) {
        $ordersForms[] = $row['grp_title'];
    }
    
    // Query registry for Orders forms
    $registryQuery = "SELECT directory FROM registry WHERE category = 'Orders' AND directory != ''";
    $registryResult = sqlStatement($registryQuery);
    while ($row = sqlFetchArray($registryResult)) {
        // For registry entries, we need to get the form_name from the forms table
        $formNameQuery = "SELECT DISTINCT form_name FROM forms WHERE formdir = ? AND form_name != '' LIMIT 1";
        $formNameResult = sqlQuery($formNameQuery, array($row['directory']));
        if ($formNameResult && !empty($formNameResult['form_name'])) {
            $ordersForms[] = $formNameResult['form_name'];
        }
    }
    
    // Remove duplicates
    $ordersForms = array_unique($ordersForms);
    
    // Filter out procedure orders - they should only appear in results, not orders
    $ordersForms = array_filter($ordersForms, function($formName) {
        return stripos($formName, 'procedure') === false;
    });
    

    
    // Get all LBF forms for this patient that are in the Orders category
    $query = "SELECT f.id, f.encounter, f.form_name, f.form_id, f.formdir, COALESCE(fe.date, f.date) as date 
              FROM forms f 
              LEFT JOIN form_encounter fe ON f.encounter = fe.encounter 
              WHERE f.pid = ? 
              AND f.form_name IN ('" . implode("','", array_map('addslashes', $ordersForms)) . "')
              AND f.deleted = 0 
              ORDER BY COALESCE(fe.date, f.date) DESC";
              
 
              
    $result = sqlStatement($query, array($pid));
    
    while ($row = sqlFetchArray($result)) {
      
        
        // Process all matching forms
       
        
        $formFields = array();
        
        // Handle Work/School Note forms differently - they use form_note table
        if ($row['form_name'] == 'Work/School Note') {
            // Query form_note table for Work/School Note data
            $noteQuery = sqlStatement(
                "SELECT note_type, message, doctor, date_of_signature FROM form_note WHERE id = ?",
                array($row['form_id'])
            );
            
            if ($noteRow = sqlFetchArray($noteQuery)) {
                $formFields['note_type'] = $noteRow['note_type'];
                $formFields['message'] = $noteRow['message'];
                $formFields['doctor'] = $noteRow['doctor'];
                $formFields['date_of_signature'] = $noteRow['date_of_signature'];
               
            }
        } else {
            // Get all fields from lbf_data for other LBF forms
            $fieldsQuery = sqlStatement(
                "SELECT field_id, field_value FROM lbf_data WHERE form_id = ?",
                array($row['form_id'])
            );
            
        
            
            // Create an associative array of field values
            while ($field = sqlFetchArray($fieldsQuery)) {
         
                if ($field['field_id'] != 'patient_sig') { // Skip signature field
                    $formFields[$field['field_id']] = $field['field_value'];
                }
            }
        }
        
    
        
        // Initialize content string
        $content = '';
        
        // Build content based on available fields
        if (!empty($formFields['referral_to'])) {
            $content .= "Referral to: " . $formFields['referral_to'] . "\n";
          
        }
        
        if (!empty($formFields['referral_reason'])) {
            $content .= "Referral For: " . $formFields['referral_reason'];
           
        }
        
        // Check for Work/School Note message field
        if (!empty($formFields['message'])) {
            $content .= "Message: " . $formFields['message'];
      
        }
        
        // Only add form if we have content or if it's a form that might not have specific fields
        if (!empty($content) || !empty($row['form_name'])) {
            $header = $row['form_name'] . ' - (' . date('m/d/Y', strtotime($row['date'])) . ')';
       
            
            $forms[] = array(
                'type' => 'LBF',
                'name' => $row['form_name'],
                'encounter' => $row['encounter'],
                'date' => $row['date'],
                'header' => $header,
                'content' => !empty($content) ? $content : $row['form_name'] . ' form completed',
                'form_id' => $row['form_id']
            );
          
        }
    }
    

    return $forms;
}

// Get all clinical data for the SOAP form
// $response = [
//     'patient_details' => getPatientDetails($pid),
//     'vitals' => getVitals($pid),
//     'physical_exam' => getPhysicalExamFindings($pid),
//     'labs' => getLatestLabs($pid),
//     'allergies' => getAllergies($pid),
//     'medications' => getCurrentMedications($pid),
//     'diagnoses' => getDiagnoses($pid),
//     'forms' => getLBFForms($pid),
//     'ros' => getReviewOfSystems($pid),
//     'history' => getPatientHistory($pid)
// ];

$response = [
    'patient_details' => getPatientDetails($pid),
    'vitals' => getVitals($pid),
    'physical_exam' => getPhysicalExamFindings($pid),
    'labs' => getLatestLabs($pid),
    'allergies' => getAllergies($pid),
    'prescriptions' => getPrescriptions($pid),
    'medications' => getMedications($pid),
    'medical_problems' => getMedicalProblems($pid, $encounter),
    'forms' => getLBFForms($pid),
    'ros' => getReviewOfSystems($pid),
    'history' => getPatientHistory($pid),
    'eligibility_notes' => getLBFNotesByFormName($pid, 'Eligibility Notes'),
    'nurses_notes' => getLBFNotesByFormName($pid, 'Nurses Notes')
];

// Clear any output buffer content and ensure clean JSON response
ob_end_clean();
echo json_encode($response);
function getVitals($pid) {
    $vitals = [];
    
    try {
        // Get all vital signs from all dates
        $sql = "SELECT * 
                FROM form_vitals 
                WHERE pid = ? AND activity = 1 
                ORDER BY date DESC";
        
        $results = sqlStatement($sql, [$pid]);
        
        if ($results === false) {
            
            return [[
                'type' => 'vital',
                'header' => xl('Vital Signs'),
                'content' => xl('Error loading vital signs'),
                'date' => ''
            ]];
        }
        
        // Process each vital record
        while ($result = sqlFetchArray($results)) {
            // Add date information to all vital entries
            $vital_date = $result['date'] ?? '';
            
            // Blood Pressure Systolic
            if (!empty($result['bps']) && $result['bps'] > 0) {
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Systolic BP'),
                    'content' => $result['bps'] . ' mmHg',
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Blood Pressure Diastolic
            if (!empty($result['bpd']) && $result['bpd'] > 0) {
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Diastolic BP'),
                    'content' => $result['bpd'] . ' mmHg',
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Combined Blood Pressure
            if (!empty($result['bps']) && !empty($result['bpd']) && $result['bps'] > 0 && $result['bpd'] > 0) {
                $bp_status = '';
                if ($result['bps'] < 120 && $result['bpd'] < 80) {
                    $bp_status = ' (' . xl('Normal') . ')';
                } elseif ($result['bps'] < 130 && $result['bpd'] < 80) {
                    $bp_status = ' (' . xl('Elevated') . ')';
                } elseif ($result['bps'] < 140 || $result['bpd'] < 90) {
                    $bp_status = ' (' . xl('Stage 1 Hypertension') . ')';
                } else {
                    $bp_status = ' (' . xl('Stage 2 Hypertension') . ')';
                }
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Blood Pressure'),
                    'content' => $result['bps'] . '/' . $result['bpd'] . ' mmHg' . $bp_status,
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Pulse/Heart Rate
            if (!empty($result['pulse']) && $result['pulse'] > 0) {
                $pulse_status = '';
                if ($result['pulse'] >= 60 && $result['pulse'] <= 100) {
                    $pulse_status = ' (' . xl('Normal') . ')';
                } elseif ($result['pulse'] < 60) {
                    $pulse_status = ' (' . xl('Bradycardia') . ')';
                } else {
                    $pulse_status = ' (' . xl('Tachycardia') . ')';
                }
                
                // Format pulse to remove trailing zeros
                $formatted_pulse = rtrim(rtrim(number_format($result['pulse'], 6), '0'), '.');
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Pulse'),
                    'content' => $formatted_pulse . ' bpm' . $pulse_status,
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Temperature
            if (!empty($result['temperature']) && $result['temperature'] > 0) {
                $temp_value = $result['temperature'];
                $temp_unit = '°F';
                $temp_method = '';
                
                // Check temperature method
                if (!empty($result['temp_method'])) {
                    $temp_method = ' (' . $result['temp_method'] . ')';
                    // Convert if Celsius
                    if (strtolower($result['temp_method']) === 'celsius' || strtolower($result['temp_method']) === 'c') {
                        $temp_value = ($temp_value * 9/5) + 32;
                        $temp_unit = '°F (converted from °C)';
                    }
                }
                
                $temp_status = '';
                if ($temp_value >= 97.0 && $temp_value <= 99.5) {
                    $temp_status = ' [' . xl('Normal') . ']';
                } elseif ($temp_value > 99.5) {
                    $temp_status = ' [' . xl('Fever') . ']';
                } else {
                    $temp_status = ' [' . xl('Hypothermia') . ']';
                }
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Temperature'),
                    'content' => number_format($temp_value, 1) . $temp_unit . $temp_method . $temp_status,
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Respiratory Rate
            if (!empty($result['respiration']) && $result['respiration'] > 0) {
                $resp_status = '';
                if ($result['respiration'] >= 12 && $result['respiration'] <= 20) {
                    $resp_status = ' (' . xl('Normal') . ')';
                } elseif ($result['respiration'] > 20) {
                    $resp_status = ' (' . xl('Tachypnea') . ')';
                } else {
                    $resp_status = ' (' . xl('Bradypnea') . ')';
                }
                
                // Format respiration to remove trailing zeros
                $formatted_respiration = rtrim(rtrim(number_format($result['respiration'], 6), '0'), '.');
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Respiratory Rate'),
                    'content' => $formatted_respiration . '/min' . $resp_status,
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Oxygen Saturation
            if (!empty($result['oxygen_saturation']) && $result['oxygen_saturation'] > 0) {
                $o2_status = '';
                if ($result['oxygen_saturation'] >= 95) {
                    $o2_status = ' (' . xl('Normal') . ')';
                } else {
                    $o2_status = ' (' . xl('Low') . ')';
                }
                
                // Format oxygen saturation to remove trailing zeros
                $formatted_o2sat = rtrim(rtrim(number_format($result['oxygen_saturation'], 2), '0'), '.');
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Oxygen Saturation'),
                    'content' => $formatted_o2sat . '%' . $o2_status,
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Oxygen Flow Rate
            if (!empty($result['oxygen_flow_rate']) && $result['oxygen_flow_rate'] > 0) {
                // Format oxygen flow rate to remove trailing zeros
                $formatted_flow_rate = rtrim(rtrim(number_format($result['oxygen_flow_rate'], 6), '0'), '.');
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Oxygen Flow Rate'),
                    'content' => $formatted_flow_rate . ' L/min',
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Inhaled Oxygen Concentration (new field from table structure)
            if (!empty($result['inhaled_oxygen_concentration']) && $result['inhaled_oxygen_concentration'] > 0) {
                // Format inhaled oxygen concentration to remove trailing zeros
                $formatted_inhaled_o2 = rtrim(rtrim(number_format($result['inhaled_oxygen_concentration'], 2), '0'), '.');
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Inhaled O2 Concentration'),
                    'content' => $formatted_inhaled_o2 . '%',
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Weight
            if (!empty($result['weight']) && $result['weight'] > 0) {
                // Format weight to remove trailing zeros
                $formatted_weight = rtrim(rtrim(number_format($result['weight'], 6), '0'), '.');
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Weight'),
                    'content' => $formatted_weight . ' lbs',
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Height
            if (!empty($result['height']) && $result['height'] > 0) {
                // Format height to remove trailing zeros
                $formatted_height = rtrim(rtrim(number_format($result['height'], 6), '0'), '.');
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Height'),
                    'content' => $formatted_height . ' inches',
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // BMI
            if (!empty($result['BMI']) && $result['BMI'] > 0) {
                $bmi_status = '';
                if (!empty($result['BMI_status'])) {
                    $bmi_status = ' (' . $result['BMI_status'] . ')';
                } else {
                    // Calculate status if not stored
                    if ($result['BMI'] < 18.5) {
                        $bmi_status = ' (' . xl('Underweight') . ')';
                    } elseif ($result['BMI'] < 25) {
                        $bmi_status = ' (' . xl('Normal') . ')';
                    } elseif ($result['BMI'] < 30) {
                        $bmi_status = ' (' . xl('Overweight') . ')';
                    } else {
                        $bmi_status = ' (' . xl('Obese') . ')';
                    }
                }
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('BMI'),
                    'content' => number_format($result['BMI'], 1) . $bmi_status,
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Waist Circumference
            if (!empty($result['waist_circ']) && $result['waist_circ'] > 0) {
                // Format waist circumference to remove trailing zeros
                $formatted_waist = rtrim(rtrim(number_format($result['waist_circ'], 6), '0'), '.');
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Waist Circumference'),
                    'content' => $formatted_waist . ' cm',
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Head Circumference
            if (!empty($result['head_circ']) && $result['head_circ'] > 0) {
                // Format head circumference to remove trailing zeros
                $formatted_head = rtrim(rtrim(number_format($result['head_circ'], 6), '0'), '.');
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Head Circumference'),
                    'content' => $formatted_head . ' cm',
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Pediatric Weight/Height Percentile
            if (!empty($result['ped_weight_height']) && $result['ped_weight_height'] > 0) {
                // Format percentile to remove trailing zeros
                $formatted_ped_wh = rtrim(rtrim(number_format($result['ped_weight_height'], 2), '0'), '.');
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Weight/Height Percentile'),
                    'content' => $formatted_ped_wh . '%',
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Pediatric BMI Percentile
            if (!empty($result['ped_bmi']) && $result['ped_bmi'] > 0) {
                // Format percentile to remove trailing zeros
                $formatted_ped_bmi = rtrim(rtrim(number_format($result['ped_bmi'], 2), '0'), '.');
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('BMI Percentile'),
                    'content' => $formatted_ped_bmi . '%',
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Pediatric Head Circumference Percentile
            if (!empty($result['ped_head_circ']) && $result['ped_head_circ'] > 0) {
                // Format percentile to remove trailing zeros
                $formatted_ped_head = rtrim(rtrim(number_format($result['ped_head_circ'], 2), '0'), '.');
                
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Head Circumference Percentile'),
                    'content' => $formatted_ped_head . '%',
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // Vital Signs Note
            if (!empty($result['note'])) {
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('Vital Signs Note'),
                    'content' => $result['note'],
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
            
            // External ID (if used)
            if (!empty($result['external_id'])) {
                $vitals[] = [
                    'type' => 'vital',
                    'header' => xl('External ID'),
                    'content' => $result['external_id'],
                    'date' => $vital_date,
                    'encounter_date' => $vital_date
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Vitals data query error: " . $e->getMessage());
    }
    
    // If no vitals found, provide message
    if (empty($vitals)) {
        $vitals = [
            [
                'type' => 'vital',
                'header' => xl('Vital Signs'),
                'content' => xl('No recent vital signs available')
            ]
        ];
    }
    
    return $vitals;
}

/**
 * Get patient demographic details for drag and drop
 */
function getPatientDetails($pid) {
    $patient_details = [];
    
    try {
        // Get patient demographic data from patient_data table
        $sql = "SELECT fname, lname, mname, DOB, sex,
                       TIMESTAMPDIFF(YEAR, DOB, CURDATE()) as age
                FROM patient_data 
                WHERE pid = ?";
        
        $result = sqlQuery($sql, [$pid]);
        
        if ($result === false || !$result) {
         
            return [[
                'type' => 'patient_detail',
                'header' => xl('Patient Details'),
                'content' => xl('Error loading patient details'),
                'field' => 'error'
            ]];
        }
        
        if ($result) {
            // First Name
            if (!empty($result['fname'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('First Name'),
                    'content' => $result['fname'],
                    'field' => 'fname'
                ];
            }
            
            // Last Name
            if (!empty($result['lname'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Last Name'),
                    'content' => $result['lname'],
                    'field' => 'lname'
                ];
            }
            
            // Date of Birth
            if (!empty($result['DOB']) && $result['DOB'] != '0000-00-00') {
                // Format the date for display
                $formatted_dob = date('Y-m-d', strtotime($result['DOB']));
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Date of Birth'),
                    'content' => $formatted_dob,
                    'field' => 'dob'
                ];
            }
            
            // Age
            if (!empty($result['age']) && $result['age'] >= 0) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Age'),
                    'content' => $result['age'] . ' ' . xl('years old'),
                    'field' => 'age'
                ];
            }
            
            // Gender
            if (!empty($result['sex'])) {
                $gender_display = '';
                switch (strtolower($result['sex'])) {
                    case 'male':
                    case 'm':
                        $gender_display = xl('Male');
                        break;
                    case 'female':
                    case 'f':
                        $gender_display = xl('Female');
                        break;
                    case 'other':
                    case 'o':
                        $gender_display = xl('Other');
                        break;
                    default:
                        $gender_display = $result['sex'];
                        break;
                }
                
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Gender'),
                    'content' => $gender_display,
                    'field' => 'gender'
                ];
            }
            
            // Address
            $address_parts = [];
            if (!empty($result['street'])) $address_parts[] = $result['street'];
            if (!empty($result['city'])) $address_parts[] = $result['city'];
            if (!empty($result['state'])) $address_parts[] = $result['state'];
            if (!empty($result['postal_code'])) $address_parts[] = $result['postal_code'];
            
            if (!empty($address_parts)) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Address'),
                    'content' => implode(', ', $address_parts),
                    'field' => 'address'
                ];
            }
            
            // Phone Numbers
            if (!empty($result['phone_home'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Home Phone'),
                    'content' => $result['phone_home'],
                    'field' => 'phone_home'
                ];
            }
            
            if (!empty($result['phone_cell'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Cell Phone'),
                    'content' => $result['phone_cell'],
                    'field' => 'phone_cell'
                ];
            }
            
            if (!empty($result['phone_biz'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Business Phone'),
                    'content' => $result['phone_biz'],
                    'field' => 'phone_biz'
                ];
            }
            
            // Email
            if (!empty($result['email'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Email'),
                    'content' => $result['email'],
                    'field' => 'email'
                ];
            }
            
            // Social Security Number (if allowed and available)
            if (!empty($result['ss'])) {
                // Mask SSN for privacy - show only last 4 digits
                $masked_ssn = 'XXX-XX-' . substr($result['ss'], -4);
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('SSN'),
                    'content' => $masked_ssn,
                    'field' => 'ssn'
                ];
            }
            
            // Driver's License
            if (!empty($result['drivers_license'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Driver\'s License'),
                    'content' => $result['drivers_license'],
                    'field' => 'drivers_license'
                ];
            }
            
            // Marital Status
            if (!empty($result['marital_status'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Marital Status'),
                    'content' => $result['marital_status'],
                    'field' => 'marital_status'
                ];
            }
            
            // Race
            if (!empty($result['race'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Race'),
                    'content' => $result['race'],
                    'field' => 'race'
                ];
            }
            
            // Ethnicity
            if (!empty($result['ethnicity'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Ethnicity'),
                    'content' => $result['ethnicity'],
                    'field' => 'ethnicity'
                ];
            }
            
            // Language
            if (!empty($result['language'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Primary Language'),
                    'content' => $result['language'],
                    'field' => 'language'
                ];
            }
            
            // Religion
            if (!empty($result['religion'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Religion'),
                    'content' => $result['religion'],
                    'field' => 'religion'
                ];
            }
            
            // Occupation
            if (!empty($result['occupation'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Occupation'),
                    'content' => $result['occupation'],
                    'field' => 'occupation'
                ];
            }
            
            // Emergency Contact
            if (!empty($result['emergency_contact'])) {
                $patient_details[] = [
                    'type' => 'patient_detail',
                    'header' => xl('Emergency Contact'),
                    'content' => $result['emergency_contact'],
                    'field' => 'emergency_contact'
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Patient details query error: " . $e->getMessage());
    }
    
    // If no patient details found, provide message
    if (empty($patient_details)) {
        $patient_details = [
            [
                'type' => 'patient_detail',
                'header' => xl('Patient Details'),
                'content' => xl('No patient details available'),
                'field' => 'none'
            ]
        ];
    }
    
    return $patient_details;
}

/**
 * Get physical exam findings from form_physical_exam table and LBF medical exam forms
 */
function getPhysicalExamFindings($pid) {
    $findings = [];
    
    try {
        // Include the physical exam lines definitions
        include_once(__DIR__ . "/../../forms/physical_exam/lines.php");
        require_once($GLOBALS["srcdir"] . "/options.inc.php");
        global $pelines;
        
        // Get physical exam forms for this patient
        $sql = "SELECT DISTINCT f.form_id, f.date, f.encounter 
                FROM forms f 
                WHERE f.pid = ? AND f.formdir = 'physical_exam' AND f.deleted = 0 
                ORDER BY f.date DESC";
        $form_results = sqlStatement($sql, [$pid]);
        
        // Also get LBF medical exam forms
        $lbf_sql = "SELECT DISTINCT f.form_id, f.date, f.encounter, f.formdir 
                   FROM forms f 
                   WHERE f.pid = ? AND (f.formdir = 'LBF_Medical_Exam' OR f.formdir = 'LBF_Male_Medical_Exam') AND f.deleted = 0 
                   ORDER BY f.date DESC";
        $lbf_form_results = sqlStatement($lbf_sql, [$pid]);
        
        // Debug: Log what forms we found
        error_log("Physical Exam Debug: Searching for LBF forms for PID: $pid");
        error_log("Physical Exam Debug: LBF Query: " . $lbf_sql);
        $lbf_count = sqlNumRows($lbf_form_results);
        error_log("Physical Exam Debug: Found $lbf_count LBF medical exam forms");
        
        // Process each physical exam form
        while ($form = sqlFetchArray($form_results)) {
            // Get the physical exam line items for this form
            $exam_sql = "SELECT line_id, wnl, abn, diagnosis, comments 
                        FROM form_physical_exam 
                        WHERE forms_id = ? 
                        ORDER BY line_id";
            $exam_results = sqlStatement($exam_sql, [$form['form_id']]);
            
            // Group findings by system using line_id mapping
            $systems_with_findings = [];
            
            while ($exam_line = sqlFetchArray($exam_results)) {
                $line_id = $exam_line['line_id'];
                $wnl = $exam_line['wnl'];
                $abn = $exam_line['abn'];
                $diagnosis = trim($exam_line['diagnosis'] ?? '');
                $comments = trim($exam_line['comments'] ?? '');
                
                // Find which system this line_id belongs to and get description
                $system_name = '';
                $line_description = '';
                
                foreach ($pelines as $system => $lines) {
                    if ($system === '*') continue; // Skip treatment lines
                    
                    if (isset($lines[$line_id])) {
                        $system_name = $system;
                        $line_description = $lines[$line_id];
                        break;
                    }
                }
                
                // Only process if we found a matching system and there's some data
                if ($system_name && ($wnl || $abn || $diagnosis || $comments)) {
                    if (!isset($systems_with_findings[$system_name])) {
                        $systems_with_findings[$system_name] = [];
                    }
                    
                    $finding_content = '';
                    
                    // Build the finding content based on available data
                    if ($wnl) {
                        $finding_content = $line_description . ': ' . xl('Within Normal Limits');
                    } elseif ($abn) {
                        $finding_content = $line_description . ': ' . xl('Abnormal');
                        if ($diagnosis) {
                            $finding_content .= ' - ' . $diagnosis;
                        }
                    } elseif ($diagnosis) {
                        $finding_content = $line_description . ': ' . $diagnosis;
                    }
                    
                    // Add comments if present
                    if ($comments) {
                        if ($finding_content) {
                            $finding_content .= ' (' . $comments . ')';
                        } else {
                            $finding_content = $line_description . ': ' . $comments;
                        }
                    }
                    
                    if ($finding_content) {
                        $systems_with_findings[$system_name][] = $finding_content;
                    }
                }
            }
            
            // Create findings for each system that has data
            foreach ($systems_with_findings as $system => $system_findings) {
                $system_display_names = [
                    'GEN' => xl('General'),
                    'EYE' => xl('Eyes'),
                    'ENT' => xl('ENT'),
                    'CV' => xl('Cardiovascular'),
                    'CHEST' => xl('Chest'),
                    'RESP' => xl('Respiratory'),
                    'GI' => xl('Gastrointestinal'),
                    'GU' => xl('Genitourinary'),
                    'LYMPH' => xl('Lymphatic'),
                    'MUSC' => xl('Musculoskeletal'),
                    'NEURO' => xl('Neurological'),
                    'PSYCH' => xl('Psychiatric'),
                    'SKIN' => xl('Skin'),
                    'OTHER' => xl('Other')
                ];
                
                $system_display = $system_display_names[$system] ?? $system;
                
                $findings[] = [
                    'type' => 'physical',
                    'header' => $system_display,
                    'content' => implode('; ', $system_findings),
                    'date' => $form['date']
                ];
            }
        }
        
        // Process LBF Medical Exam forms
        while ($lbf_form = sqlFetchArray($lbf_form_results)) {
            $form_id = $lbf_form['form_id'];
            $encounter = $lbf_form['encounter'];
            $formdir = $lbf_form['formdir'];
            
            // Use formdir for layout_options query and form_id for data
            $form_layout_id = $formdir;  // LBF_Medical_Exam
            
            error_log("Physical Exam Debug: Processing LBF form - FormDir: $formdir, FormID: $form_id");
            
            $lbf_findings = getLBFPhysicalExamData($form_id, $encounter, $form_layout_id, $lbf_form['date']);
            error_log("Physical Exam Debug: Found " . count($lbf_findings) . " LBF physical findings");
            $findings = array_merge($findings, $lbf_findings);
        }
        
    } catch (Exception $e) {
        // If there are any database errors, log and continue
        error_log("Physical exam query error: " . $e->getMessage());
    }
    
    return $findings;
}

/**
 * Helper function to parse multi-select checkbox data from LBF forms
 * Format: "1:1:|2:0:|3:1:" where 1:1 means option 1 is checked
 */
function parseMultiSelectData($data, $frow) {
    $findings = [];
    
    if (empty($data) || strpos($data, ':') === false) {
        return $findings;
    }
    
    // Parse the format "1:1:|2:0:|3:1:"
    $pairs = explode('|', trim($data, '|'));
    
    foreach ($pairs as $pair) {
        if (empty($pair)) continue;
        
        $parts = explode(':', $pair);
        if (count($parts) >= 2) {
            $option_num = $parts[0];
            $is_checked = $parts[1] == '1';
            
            if ($is_checked) {
                // Get the option text from the list_options table
                $option_text = getListOptionTitle($frow['list_id'], $option_num);
                if ($option_text) {
                    $finding = $option_text . ': Yes';
                    // Check if there's free text (additional parts after the checked indicator)
                    if (count($parts) >= 3 && !empty($parts[2])) {
                        $finding .= ' ' . trim($parts[2]);
                    }
                    $findings[] = $finding;
                } else {
                    // Fallback to just the option number
                    $findings[] = "Option $option_num: Yes";
                }
            }
        }
    }
    
    return $findings;
}

/**
 * Get option title from list_options table
 */
function getListOptionTitle($list_id, $option_id) {
    if (empty($list_id) || empty($option_id)) {
        return null;
    }
    
    $sql = "SELECT title FROM list_options WHERE list_id = ? AND option_id = ?";
    $result = sqlQuery($sql, [$list_id, $option_id]);
    
    return $result['title'] ?? null;
}

/**
 * Get associated free text fields for multi-select options
 */
function getAssociatedFreeTextFields($form_layout_id, $form_id, $encounter, $base_field_id) {
    $text_findings = [];
    
    // Query for potential associated text fields
    $text_sql = "SELECT * FROM layout_options 
                 WHERE form_id = ? AND uor > 0 AND field_id LIKE ?
                 ORDER BY seq";
    $text_results = sqlStatement($text_sql, [$form_layout_id, $base_field_id . '_%']);
    
    while ($text_frow = sqlFetchArray($text_results)) {
        $text_field_id = $text_frow['field_id'];
        $text_title = $text_frow['title'];
        
        // Skip the base field itself
        if ($text_field_id == $base_field_id) continue;
        
        $text_value = lbf_current_value($text_frow, $form_id, $encounter);
        
        if ($text_value !== false && $text_value !== '') {
            $text_findings[] = $text_title . ': ' . $text_value;
        }
    }
    
    return $text_findings;
}

/**
 * Get the physical exam system name based on field_id
 */
function getPhysicalExamSystemName($field_id) {
    // Map field_ids to body systems
    if (strpos($field_id, 'neurosyphilis') !== false) {
        return 'NeuroSyphilis';
    } elseif (strpos($field_id, 'oro_pharynx') !== false) {
        return 'Oro-Pharynx';
    } elseif (strpos($field_id, 'nodes') !== false) {
        return 'Nodes';
    } elseif (strpos($field_id, 'skin') !== false) {
        return 'Skin';
    } elseif (strpos($field_id, 'rectal') !== false) {
        return 'Rectal';
    } elseif (strpos($field_id, 'pubic_hair') !== false) {
        return 'Pubic Hair';
    } elseif (strpos($field_id, 'vulva_vagina') !== false) {
        return 'Vulva/Vagina';
    } elseif (strpos($field_id, 'cervix') !== false) {
        return 'Cervix';
    } elseif (strpos($field_id, 'uterus') !== false) {
        return 'Uterus';
    } elseif (strpos($field_id, 'adnexa') !== false) {
        return 'Adnexa';
    } else {
        return 'Physical Exam';
    }
}

/**
 * Get the ROS system name based on field_id
 */
function getROSSystemName($field_id) {
    // Map field_ids to body systems
    if (strpos($field_id, 'general') !== false) {
        return 'General';
    } elseif (strpos($field_id, 'heent') !== false) {
        return 'HEENT';
    } elseif (strpos($field_id, 'lymphatic') !== false) {
        return 'Lymphatic';
    } elseif (strpos($field_id, 'respiratory') !== false) {
        return 'Respiratory';
    } elseif (strpos($field_id, 'cvs') !== false) {
        return 'CVS';
    } elseif (strpos($field_id, 'gi') !== false) {
        return 'GI';
    } elseif (strpos($field_id, 'gu') !== false) {
        return 'GU';
    } elseif (strpos($field_id, 'ms') !== false) {
        return 'M/S';
    } elseif (strpos($field_id, 'skin') !== false) {
        return 'Skin';
    } elseif (strpos($field_id, 'neuro') !== false) {
        return 'Neuro';
    } elseif (strpos($field_id, 'neurosyphilis') !== false) {
        return 'NeuroSyphilis';
    } else {
        return 'Review of Systems';
    }
}

/**
 * Helper function to extract Physical Exam data from LBF forms
 */
function getLBFPhysicalExamData($form_id, $encounter, $form_layout_id, $form_date) {
    $findings = [];
    
    try {
        error_log("LBF Physical Debug: Starting processing for form_id: $form_id, form_layout_id: $form_layout_id");
        
        // Get layout options using the form_layout_id (LBF_Medical_Exam)
        $layout_sql = "SELECT * FROM layout_options 
                      WHERE form_id = ? AND uor > 0 
                      ORDER BY group_id, seq";
        $layout_results = sqlStatement($layout_sql, [$form_layout_id]);
        
        $layout_count = sqlNumRows($layout_results);
        error_log("LBF Physical Debug: Found $layout_count layout options for form_layout_id: $form_layout_id");
        
        if ($layout_count == 0) {
            error_log("LBF Physical Debug: No layout options found for form_id: $form_layout_id");
            return $findings;
        }
        
        $physical_exam_systems = [];
        
        while ($frow = sqlFetchArray($layout_results)) {
            $field_id = $frow['field_id'];
            $group_id = $frow['group_id'];
            $title = $frow['title'];
            
            error_log("LBF Physical Debug: Processing field - field_id: $field_id, group_id: $group_id, title: $title");
            
            // Get current value for this field
            $currvalue = lbf_current_value($frow, $form_id, $encounter);
            
            error_log("LBF Physical Debug: Field $field_id returned value: " . ($currvalue === false ? 'FALSE' : "'" . $currvalue . "'"));
            
            if ($currvalue === false || $currvalue === '') {
                error_log("LBF Physical Debug: Skipping field $field_id - empty or false value");
                continue;
            }
            
            // Check if this is a physical exam related field
            // Only Group 4 = "Female Physical Exam" (exclude Group 3 = General Exam)
            $is_physical_exam = ($group_id == '4' || strpos($field_id, 'pe_') === 0 || strpos(strtolower($title), 'physical') !== false || strpos(strtolower($title), 'exam') !== false);
            
            error_log("LBF Physical Debug: Field $field_id in group '$group_id' - is_physical_exam: " . ($is_physical_exam ? 'YES' : 'NO'));
            
            if ($is_physical_exam) {
                // Parse the multi-select checkbox format or handle free text
                if (strpos($currvalue, ':') !== false && strpos($currvalue, '|') !== false) {
                    // Multi-select checkboxes
                    $findings = parseMultiSelectData($currvalue, $frow);
                    
                    // Also check for associated free text fields
                    // $associated_text_findings = getAssociatedFreeTextFields($form_layout_id, $form_id, $encounter, $field_id);
                    // $findings = array_merge($findings, $associated_text_findings);
                } else {
                    // Free text or single value
                    $findings = [$title . ': ' . $currvalue];
                }
                
                if (!empty($findings)) {
                    // Determine the body system based on field_id
                    $system_name = getPhysicalExamSystemName($field_id);
                    
                    if (!isset($physical_exam_systems[$system_name])) {
                        $physical_exam_systems[$system_name] = [];
                    }
                    
                    error_log("LBF Physical Debug: Adding findings to system '$system_name': " . implode(', ', $findings));
                    $physical_exam_systems[$system_name] = array_merge($physical_exam_systems[$system_name], $findings);
                }
            } else {
                error_log("LBF Physical Debug: Skipping field $field_id - not classified as physical exam");
            }
        }
        
        error_log("LBF Physical Debug: Final systems count: " . count($physical_exam_systems));
        foreach ($physical_exam_systems as $sys => $findings) {
            error_log("LBF Physical Debug: System '$sys' has " . count($findings) . " findings");
        }
        
        // Create findings for each system with proper formatting
        foreach ($physical_exam_systems as $system => $system_findings) {
            if (!empty($system_findings)) {
                // Format as: System: finding1, finding2, finding3 (no redundant header)
                $content = $system . ": " . implode(", ", $system_findings);
                
                $findings[] = [
                    'type' => 'physical',
                    'header' => 'Physical Exam',
                    'content' => $content,
                    'date' => $form_date
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("LBF Physical exam processing error: " . $e->getMessage());
    }
    
    return $findings;
}

/**
 * Get latest lab results using the same query pattern as demographics.php
 */
function getLatestLabs($pid, $filter = 'lab_date') {
    $labs = [];
    
    try {
        // First get all orders
        $order_sql = "SELECT DISTINCT po.procedure_order_id, po.date_ordered, pr.date_collected, 
                             po.order_diagnosis, po.clinical_hx
                      FROM procedure_order po
                      LEFT JOIN procedure_report pr ON pr.procedure_order_id = po.procedure_order_id
                      WHERE po.patient_id = ?
                      ORDER BY po.date_ordered DESC";
        $order_results = sqlStatement($order_sql, [$pid]);
        
        while ($order_row = sqlFetchArray($order_results)) {
            $order_id = $order_row['procedure_order_id'];
            
            // Get test names for this order
            $test_sql = "SELECT DISTINCT procedure_name 
                        FROM procedure_order_code 
                        WHERE procedure_order_id = ? AND procedure_name IS NOT NULL AND procedure_name != ''
                        ORDER BY procedure_order_seq";
            $test_results = sqlStatement($test_sql, [$order_id]);
            $test_names = [];
            while ($test_row = sqlFetchArray($test_results)) {
                $test_names[] = $test_row['procedure_name'];
            }
            
            // Get results for this order
            $result_sql = "SELECT pr.result, pr.result_text, pr.units, pr.abnormal, pr.result_code,
                                  pt.name, pt.procedure_code
                           FROM procedure_result pr
                           LEFT JOIN procedure_report prep ON prep.procedure_report_id = pr.procedure_report_id
                           LEFT JOIN procedure_type pt ON pt.procedure_code = pr.result_code
                           WHERE prep.procedure_order_id = ?
                           ORDER BY pt.name";
            $result_results = sqlStatement($result_sql, [$order_id]);
            
            $results = [];
            $result_map = []; // Track results by test name to avoid duplicates
            
            while ($result_row = sqlFetchArray($result_results)) {
                if (!empty($result_row['result']) || !empty($result_row['result_text'])) {
                    $result_value = trim($result_row['result'] ?: $result_row['result_text']);
                    $test_name = $result_row['name'] ?: $result_row['procedure_code'] ?: xl('Lab Result');
                    
                    $result_text = $test_name;
                    
                    // Only add result value if it's valid (not the test name itself and doesn't contain ':' which indicates it's malformed)
                    if (!empty($result_value) && $result_value !== $test_name && strpos($result_value, ':') === false) {
                        $result_text .= ': ' . $result_value;
                    }
                    
                    // Add abnormal flag if present
                    if (!empty($result_row['abnormal'])) {
                        if (strtolower($result_row['abnormal']) === 'yes' || strtolower($result_row['abnormal']) === 'abnormal') {
                            $result_text .= ' [' . xl('Abnormal') . ']';
                        } elseif (strtolower($result_row['abnormal']) === 'no' || strtolower($result_row['abnormal']) === 'normal') {
                            $result_text .= ' [' . xl('Normal') . ']';
                        }
                    }
                    
                    // Use test name as key for deduplication, preferring results with actual values
                    if (!isset($result_map[$test_name]) || strpos($result_text, ':') !== false) {
                        $result_map[$test_name] = $result_text;
                    }
                }
            }
            
            // Convert map to array and sort
            $results = array_values($result_map);
            sort($results);
            
            // Only include orders that have results
            if (!empty($results)) {
                // Create header
                $header_parts = [];
                if (!empty($test_names)) {
                    $header_parts[] = implode(', ', $test_names);
                } else {
                    $header_parts[] = xl('Lab Order');
                }
                $header_parts[] = xl('Ordered') . ': ' . date('m/d/Y', strtotime($order_row['date_ordered']));
                if (!empty($order_row['date_collected'])) {
                    $header_parts[] = xl('Collected') . ': ' . date('m/d/Y', strtotime($order_row['date_collected']));
                }
                if (!empty($order_row['order_diagnosis'])) {
                    $header_parts[] = xl('Dx') . ' ' . $order_row['order_diagnosis'];
                }
                $header = implode(' | ', $header_parts);
                
                // Create content
                $content_parts = [];
                if (!empty($order_row['clinical_hx'])) {
                    $content_parts[] = xl('Clinical History') . ': ' . $order_row['clinical_hx'];
                }
                $content_parts[] = xl('Results') . ':';
                foreach ($results as $result) {
                    $content_parts[] = "- " . $result;
                }
                $content = "\n" . implode("\n", $content_parts);
                
                $labs[] = [
                    'type' => 'lab',
                    'header' => $header,
                    'content' => $content,
                    'date' => $order_row['date_ordered'],
                    'order_id' => $order_id,
                    'draggable_group' => 'lab_order_' . $order_id,
                    'draggable_content' => $header . $content,
                    'status' => 'completed'
                ];
            }
        }
        
        if (empty($labs)) {
            $labs[] = [
                'type' => 'lab',
                'header' => xl('Lab Results'),
                'content' => xl('No recent lab results available'),
                'date' => ''
            ];
        }
        
    } catch (Exception $e) {
        error_log("Lab results query error: " . $e->getMessage());
        $labs[] = [
            'type' => 'lab',
            'header' => xl('Lab Results'),
            'content' => xl('Error loading lab results'),
            'date' => ''
        ];
    }
    
    return $labs;
}

/**
 * Get patient history using direct database query to get ALL history data
 */
function getPatientHistory($pid) {
    $history = [];
    
    try {
        // First, try OpenEMR's standard function for basic compatibility
        $historyData = getHistoryData($pid);
        
        // Then, get comprehensive data directly from history_data table
        $sql = "SELECT * FROM history_data WHERE pid = ? ORDER BY date DESC LIMIT 1";
        $result = sqlQuery($sql, [$pid]);
        
        if ($result === false) {
           
            return [[
                'type' => 'history',
                'header' => xl('Patient History'),
                'content' => xl('No patient history available'),
                'date' => ''
            ]];
        }
        
        if ($result) {
            // Define all possible history fields with their display names
            $historyFields = [
                // Basic History
                'history' => 'Past Medical History'
                // ...existing fields...
            ];
            // Past Medical History
            if (!empty($historyData['history'])) {
                $history[] = [
                    'type' => 'history',
                    'header' => xl('Past Medical History'),
                    'content' => $historyData['history']
                ];
            }
            
            // Family History  
            if (!empty($historyData['family_history'])) {
                $history[] = [
                    'type' => 'history',
                    'header' => xl('Family History'),
                    'content' => $historyData['family_history']
                ];
            }
            
            // Social History
            $socialItems = [];
            if (!empty($historyData['tobacco'])) {
                $socialItems[] = xl('Tobacco') . ': ' . formatSocialHistoryValue($historyData['tobacco'], 'tobacco');
            }
            if (!empty($historyData['alcohol'])) {
                $socialItems[] = xl('Alcohol') . ': ' . formatSocialHistoryValue($historyData['alcohol'], 'alcohol');
            }
            if (!empty($historyData['recreational_drugs'])) {
                $socialItems[] = xl('Recreational Drugs') . ': ' . formatSocialHistoryValue($historyData['recreational_drugs'], 'recreational_drugs');
            }
            if (!empty($historyData['occupation'])) {
                $socialItems[] = xl('Occupation') . ': ' . $historyData['occupation'];
            }
            
            if (!empty($socialItems)) {
                $history[] = [
                    'type' => 'history',
                    'header' => xl('Social History'),
                    'content' => implode('; ', $socialItems)
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("History data query error: " . $e->getMessage());
    }
    
    return $history;
}

/**
 * Get patient allergies using OpenEMR's standard approach
 */
function getAllergies($pid) {
    $allergies = [];
    
    try {
        // Query lists table for allergies following OpenEMR pattern
        $sql = "SELECT * FROM lists 
                WHERE pid = ? AND type = 'allergy' AND activity = 1 
                ORDER BY begdate DESC";
        $results = sqlStatement($sql, [$pid]);
        
        if ($results === false) {
           
            return [[
                'type' => 'allergy',
                'header' => xl('Allergies'),
                'content' => 'NKDA (No Known Drug Allergies)'
            ]];
        }
        
        while ($row = sqlFetchArray($results)) {
            $allergyText = $row['title'];
            
            // Add reaction if available
            if (!empty($row['reaction'])) {
                $allergyText .= ' (' . xl('Reaction') . ': ' . $row['reaction'] . ')';
            }
            
            // Add severity if available  
            if (!empty($row['severity_al'])) {
                $allergyText .= ' [' . xl('Severity') . ': ' . $row['severity_al'] . ']';
            }
            
            // Add onset date if available
            if (!empty($row['begdate']) && $row['begdate'] != '0000-00-00') {
                $allergyText .= ' - ' . xl('Since') . ' ' . date('m/d/Y', strtotime($row['begdate']));
            }
            
            $allergies[] = [
                'type' => 'allergy',
                'header' => $row['title'],
                'content' => $allergyText
            ];
        }
        
        if (empty($allergies)) {
            $allergies[] = [
                'type' => 'allergy',
                'header' => xl('Allergies'),
                'content' => 'NKDA (No Known Drug Allergies)'
            ];
        }
    } catch (Exception $e) {
  
        $allergies[] = [
            'type' => 'allergy',
            'header' => xl('Allergies'),
            'content' => 'NKDA (No Known Drug Allergies)'
        ];
    }
    
    return $allergies;
}

/**
 * Get current prescriptions using OpenEMR's standard approach
 */
function getPrescriptions($pid) {
    $prescriptions = [];
    
    try {
        // Get active prescriptions
        $sql = "SELECT * FROM prescriptions 
                WHERE patient_id = ? 
                ORDER BY date_added DESC";
        $results = sqlStatement($sql, [$pid]);
        
        if ($results === false) {

            return [[
                'type' => 'prescription',
                'header' => xl('Prescriptions'),
                'content' => xl('No current prescriptions documented')
            ]];
        }
        
        while ($row = sqlFetchArray($results)) {
            // Format fields using generate_display_field like in the original query
            $row['unit'] = generate_display_field(array('data_type' => '1', 'list_id' => 'drug_units'), $row['unit']);
            $row['form'] = generate_display_field(array('data_type' => '1', 'list_id' => 'drug_form'), $row['form']);
            $row['route'] = generate_display_field(array('data_type' => '1', 'list_id' => 'drug_route'), $row['route']);
            $row['interval'] = generate_display_field(array('data_type' => '1', 'list_id' => 'drug_interval'), $row['interval']);

            // Build drug name with strength
            $drugName = preg_replace('/\s+\d+mg$/', '', $row['drug']); // Remove mg if it exists in drug name
            if (!empty($row['size'])) {
                $drugName .= ' ' . $row['size'] . 'mg';
            }

            // Build instruction string exactly as shown in the dashboard
            $instructions = [];
            
             if (!empty($row['dosage'])) {
                $instructions[] =  $row['dosage'];
            }
          
            if (!empty($row['form'])) {
                $instructions[] =  $row['form'];
            }
            if (!empty($row['route'])) {
                $instructions[] = $row['route'];
            }
              if (!empty($row['interval'])) {
                $instructions[] = $row['interval'];
            }
            
            $instruction = '';
            if (!empty($instructions)) {
                $instruction = implode(' ', $instructions);
            }

            // Get dispensed quantity and refills 
            $dispRefStr = '';
            if (!empty($row['quantity']) || isset($row['refills'])) {
                $dispRefStr = ' (Dispensed ' . intval($row['quantity'] ?? 0) . ', refills ' . intval($row['refills'] ?? 0) . ')';
            }
            
            // Format date as MM/DD/YYYY
            $prescDate = '';
            if (!empty($row['date_added']) && $row['date_added'] != '0000-00-00') {
                $prescDate = ' - ' . date('m/d/Y', strtotime($row['date_added']));
            } elseif (!empty($row['date']) && $row['date'] != '0000-00-00') {
                $prescDate = ' - ' . date('m/d/Y', strtotime($row['date']));
            }

            // Compose final content string: name - instructions (dispensed X, refills Y) - date
            $contentParts = [];
            if ($instruction !== '') { $contentParts[] = $instruction; }
            if ($dispRefStr !== '') { $contentParts[] = $dispRefStr; }
            if ($prescDate !== '') { $contentParts[] = $prescDate; }

            // Format prescription string exactly as requested
            $prescText = $drugName;  // Start with drug name and strength
            
            // Add instructions if available
            if (!empty($contentParts)) {
                $prescText .= ' - ' . implode('', $contentParts);
            }

            $prescriptions[] = [
                'type' => 'prescription',
                'header' => $drugName,
                'content' => $prescText
            ];
        }
        
        if (empty($prescriptions)) {
            $prescriptions[] = [
                'type' => 'prescription',
                'header' => xl('Prescriptions'),
                'content' => xl('No current prescriptions documented')
            ];
        }
    } catch (Exception $e) {
   
        $prescriptions[] = [
            'type' => 'prescription',
            'header' => xl('Prescriptions'),
            'content' => xl('No current prescriptions documented')
        ];
    }
    
    return $prescriptions;
}

/**
 * Get medications from lists table
 */
function getMedications($pid) {
    $medications = [];
    
    try {
        // Get medications from lists table
        $sql = "SELECT * FROM lists 
                WHERE pid = ? AND type = 'medication'
                ORDER BY begdate DESC";
        $results = sqlStatement($sql, [$pid]);
        
        if ($results === false) {
        
            return [[
                'type' => 'medication',
                'header' => xl('Medications'),
                'content' => xl('No medications documented')
            ]];
        }
        
        while ($row = sqlFetchArray($results)) {
            $medText = $row['title'];
            
            // Add dosage if available
            if (!empty($row['dosage'])) {
                $medText .= ' - ' . $row['dosage'];
            }
            
            // Add onset date if available
            if (!empty($row['begdate']) && $row['begdate'] != '0000-00-00') {
                $medText .= ' - ' . xl('Since') . ' ' . date('m/d/Y', strtotime($row['begdate']));
            }
            
            $medications[] = [
                'type' => 'medication',
                'header' => $row['title'],
                'content' => $medText
            ];
        }
        
        if (empty($medications)) {
            $medications[] = [
                'type' => 'medication',
                'header' => xl('Medications'),
                'content' => xl('No medications documented')
            ];
        }
    } catch (Exception $e) {
       
        $medications[] = [
            'type' => 'medication',
            'header' => xl('Medications'),
            'content' => xl('No medications documented')
        ];
    }
    
    return $medications;
}

/**
 * Get medical problems from lists table with ICD-10 codes from billing table
 */
function getMedicalProblems($pid, $encounter = null) {
    $problems = [];

    try {
        // Get medical problems from lists table
        $sql = "SELECT
                    l.id,
                    l.title,
                    l.diagnosis,
                    l.begdate,
                    l.enddate,
                    l.activity,
                    l.comments
                FROM lists l
                WHERE l.pid = ?
                AND l.type = 'medical_problem'
                ORDER BY l.begdate DESC";

        $results = sqlStatement($sql, [$pid]);

        if ($results === false) {
           
            return [[
                'type' => 'problem',
                'header' => xl('Medical Problems'),
                'content' => xl('No active medical problems documented')
            ]];
        }

        while ($row = sqlFetchArray($results)) {
            $problemId = $row['id'];
            $title = trim($row['title'] ?? '');
            $diagnosis = trim($row['diagnosis'] ?? '');
            $begDate = (!empty($row['begdate']) && $row['begdate'] != '0000-00-00') ? date('m/d/Y', strtotime($row['begdate'])) : '';
            $endDate = (!empty($row['enddate']) && $row['enddate'] != '0000-00-00') ? date('m/d/Y', strtotime($row['enddate'])) : '';
            $activity = $row['activity'];
            $comments = trim($row['comments'] ?? '');

            // Skip inactive problems unless specifically requested
            if ($activity != 1) {
                continue;
            }

            // Get ICD-10 codes from billing table (fee sheet) for this patient
            // Get all active ICD-10 codes from encounters, not trying to match by problem title
            $billingSql = "SELECT DISTINCT
                            b.code,
                            b.code_text,
                            e.date,
                            b.encounter
                        FROM billing b
                        LEFT JOIN form_encounter e ON b.encounter = e.encounter
                        WHERE b.pid = ?
                        AND b.code_type = 'ICD10'
                        AND b.activity = 1
                        ORDER BY e.date DESC, b.encounter DESC";

            $billingResult = sqlStatement($billingSql, [$pid]);
            $icd10Codes = [];
            $icd10Details = [];

            if ($billingResult) {
                while ($billingRow = sqlFetchArray($billingResult)) {
                    if (!empty($billingRow['code'])) {
                        $code = trim($billingRow['code']);
                        $icd10Codes[] = $code;
                        
                        // Use the stored description from billing table
                        $description = trim($billingRow['code_text']);
                        $date = (!empty($billingRow['date']) && $billingRow['date'] != '0000-00-00') ? date('m/d/Y', strtotime($billingRow['date'])) : '';
                        
                        $icd10Details[] = [
                            'code' => $code,
                            'description' => $description,
                            'date' => $date
                        ];
                    }
                }
            }

            // Format ICD-10 information
            $icd10Display = '';
            if (!empty($icd10Details)) {
                $icd10Parts = [];
                foreach ($icd10Details as $detail) {
                    $codeText = 'ICD10:' . $detail['code'];
                    if (!empty($detail['description'])) {
                        $codeText .= ' - ' . $detail['description'];
                    }
                    $icd10Parts[] = $codeText;
                }
                $icd10Display = implode(' ; ', $icd10Parts);
            }

            // Add date information
            $dateInfo = '';
            if (!empty($begDate)) {
                $dateInfo = $begDate;
                if (!empty($endDate)) {
                    $dateInfo .= ' to ' . $endDate;
                }
            }

            // Build content: Problem title with ICD codes and date
            $content = $title;
            
            // Try to find matching ICD-10 codes for this problem
            $matchingIcdCodes = [];
            if (!empty($icd10Details)) {
                // Look for ICD codes that might match this problem
                // This is a simple text match - could be improved with more sophisticated matching
                foreach ($icd10Details as $icdDetail) {
                    if (!empty($icdDetail['description']) && 
                        (stripos($icdDetail['description'], $title) !== false || 
                         stripos($title, $icdDetail['description']) !== false)) {
                        $matchingIcdCodes[] = $icdDetail;
                    }
                }
                
                // If no direct matches, use the most recent ICD code
                if (empty($matchingIcdCodes) && !empty($icd10Details)) {
                    $matchingIcdCodes[] = $icd10Details[0]; // Most recent
                }
            }
            
            // Format as: Diagnosis Description - ICD:10 Code - Date
            if (!empty($matchingIcdCodes)) {
                $icdDetail = $matchingIcdCodes[0]; // Use first matching ICD code
                $content = $title . ' - ICD10:' . $icdDetail['code'];
                if (!empty($icdDetail['date'])) {
                    $content .= ' - ' . $icdDetail['date'];
                }
            } else {
                // No ICD code found, still include date if available
                if (!empty($begDate)) {
                    $content .= ' - ' . $begDate;
                }
            }

            // Add comments if available
            if (!empty($comments)) {
                $content .= ' (' . $comments . ')';
            }

            // Add the medical problem entry
            $problems[] = [
                'type' => 'problem',
                'header' => xl('Medical Problem'),
                'content' => $content,
                'draggable_group' => 'medical_problem_' . $problemId,
                'draggable_content' => $content
            ];

            // Add separate entries for each ICD-10 code with description
            if (!empty($icd10Details)) {
                foreach ($icd10Details as $detail) {
                    // Format as: Diagnosis Description - ICD:10 Code - Date
                    $diagnosisContent = '';
                    if (!empty($detail['description'])) {
                        $diagnosisContent .= $detail['description'];
                    } else {
                        $diagnosisContent .= 'Diagnosis';
                    }
                    $diagnosisContent .= ' - ICD10:' . $detail['code'];
                    if (!empty($detail['date'])) {
                        $diagnosisContent .= ' - ' . $detail['date'];
                    }
                    
                    $problems[] = [
                        'type' => 'diagnosis',
                        'header' => xl('Diagnosis'),
                        'content' => $diagnosisContent,
                        'draggable_group' => 'diagnosis_' . $detail['code'],
                        'draggable_content' => $diagnosisContent
                    ];
                }
            }
        }

        if (empty($problems)) {
            $problems[] = [
                'type' => 'problem',
                'header' => xl('Medical Problems'),
                'content' => xl('No active medical problems documented')
            ];
        }
    } catch (Exception $e) {
       
        $problems[] = [
            'type' => 'problem',
            'header' => xl('Medical Problems'),
            'content' => xl('No active medical problems documented')
        ];
    }

    return $problems;
}

/**
 * Get procedure orders (lab orders, imaging orders, etc.)
 */
function getProcedureOrders($pid) {
    $orders = [];
    
    try {
        // Get procedure orders with their details
        $sql = "SELECT 
                    po.procedure_order_id,
                    po.date_ordered,
                    po.order_diagnosis,
                    po.order_status,
                    po.clinical_hx,
                    pt.procedure_name,
                    pt.procedure_code,
                    pt.procedure_type
                FROM procedure_order po
                LEFT JOIN procedure_order_code poc ON poc.procedure_order_id = po.procedure_order_id
                LEFT JOIN procedure_type pt ON pt.procedure_type_id = poc.procedure_type_id
                WHERE po.patient_id = ?
                ORDER BY po.date_ordered DESC";
        
        $results = sqlStatement($sql, [$pid]);
        
        if ($results === false) {
           
            return [];
        }
        
        // Group by order_id
        $orderGroups = [];
        while ($row = sqlFetchArray($results)) {
            $orderId = $row['procedure_order_id'];
            if (!isset($orderGroups[$orderId])) {
                $orderGroups[$orderId] = [
                    'order_id' => $orderId,
                    'date_ordered' => $row['date_ordered'],
                    'diagnosis' => $row['order_diagnosis'],
                    'status' => $row['order_status'],
                    'clinical_hx' => $row['clinical_hx'],
                    'procedures' => []
                ];
            }
            
            if (!empty($row['procedure_name'])) {
                $orderGroups[$orderId]['procedures'][] = [
                    'name' => $row['procedure_name'],
                    'code' => $row['procedure_code'],
                    'type' => $row['procedure_type']
                ];
            }
        }
        
        // Convert to the format expected by the frontend
        foreach ($orderGroups as $order) {
            $header = 'Procedure Order';
            if (!empty($order['date_ordered'])) {
                $header .= ' - ' . date('m/d/Y', strtotime($order['date_ordered']));
            }
            
            $content = '';
            if (!empty($order['procedures'])) {
                $procedureNames = array_map(function($proc) {
                    return $proc['name'] . (!empty($proc['code']) ? ' (' . $proc['code'] . ')' : '');
                }, $order['procedures']);
                $content = implode(', ', $procedureNames);
            }
            
            if (!empty($order['diagnosis'])) {
                $content .= ' - Dx: ' . $order['diagnosis'];
            }
            
            if (!empty($order['status'])) {
                $content .= ' - Status: ' . $order['status'];
            }
            
            $orders[] = [
                'type' => 'order',
                'header' => $header,
                'content' => $content,
                'date' => $order['date_ordered'],
                'order_id' => $order['order_id']
            ];
        }
        
    } catch (Exception $e) {
       
    }
    
    return $orders;
}

/**
 * Get Review of Systems data from form_ros table and LBF medical exam forms
 */
function getReviewOfSystems($pid) {
    $rosData = [];
    
    try {
        require_once($GLOBALS["srcdir"] . "/options.inc.php");
        
        // Query form_ros table directly since it has pid and date columns
        $sql = "SELECT * FROM form_ros 
                WHERE pid = ? 
                ORDER BY date DESC";
        $ros_results = sqlStatement($sql, [$pid]);
        
        // Also get LBF medical exam forms for ROS data
        $lbf_sql = "SELECT DISTINCT f.form_id, f.date, f.encounter, f.formdir 
                   FROM forms f 
                   WHERE f.pid = ? AND (f.formdir = 'LBF_Medical_Exam' OR f.formdir = 'LBF_Male_Medical_Exam') AND f.deleted = 0 
                   ORDER BY f.date DESC";
        $lbf_form_results = sqlStatement($lbf_sql, [$pid]);
        
        // Debug: Log what forms we found for ROS
        error_log("ROS Debug: Searching for LBF forms for PID: $pid");
        $lbf_count = sqlNumRows($lbf_form_results);
        error_log("ROS Debug: Found $lbf_count LBF medical exam forms");
        
        // Also debug: let's see what forms actually exist for this patient
        $all_forms_sql = "SELECT f.formdir, COUNT(*) as count FROM forms f WHERE f.pid = ? AND f.deleted = 0 GROUP BY f.formdir";
        $all_forms_result = sqlStatement($all_forms_sql, [$pid]);
        error_log("ROS Debug: All forms for PID $pid:");
        while ($form_row = sqlFetchArray($all_forms_result)) {
            error_log("ROS Debug: FormDir: {$form_row['formdir']}, Count: {$form_row['count']}");
        }
        
        if (sqlNumRows($ros_results) == 0 && sqlNumRows($lbf_form_results) == 0) {
            return [[
                'type' => 'ros',
                'header' => xl('Review of Systems'),
                'content' => xl('No review of systems data available')
            ]];
        }
        
        // Process each ROS form
        while ($ros_data = sqlFetchArray($ros_results)) {
            // Use the same field mapping from report.php
            $field_map = [
                "glaucoma_history" => "Glaucoma Family History",
                "irritation" => "Eye Irritation",
                "redness" => "Eye Redness",
                "discharge" => "ENT Discharge",
                "pain" => "ENT Pain",
                "biopsy" => "Breast Biopsy",
                "hemoptsyis" => "Hemoptysis",
                "copd" => "COPD",
                "pnd" => "PND",
                "doe" => "DOE",
                "peripheal" => "Peripheral",
                "legpain_cramping" => "Leg Pain/Cramping",
                "frequency" => "Urine Frequency",
                "urgency" => "Urine Urgency",
                "utis" => "UTIs",
                "hesitancy" => "Urine Hesitancy",
                "dribbling" => "Urine Dribbling",
                "stream" => "Urine Stream",
                "g" => "Female G",
                "p" => "Female P",
                "lc" => "Female LC",
                "ap" => "Female AP",
                "mearche" => "Menarche",
                "lmp" => "LMP",
                "f_frequency" => "Menstrual Frequency",
                "f_flow" => "Menstrual Flow",
                "f_symptoms" => "Female Symptoms",
                "f_hirsutism" => "Hirsutism/Striae",
                "swelling" => "Musc Swelling",
                "m_redness" => "Musc Redness",
                "m_warm" => "Musc Warm",
                "m_stiffness" => "Musc Stiffness",
                "m_aches" => "Musc Aches",
                "fms" => "FMS",
                "loc" => "LOC",
                "tia" => "TIA",
                "n_numbness" => "Neuro Numbness",
                "n_weakness" => "Neuro Weakness",
                "n_headache" => "Headache",
                "s_cancer" => "Skin Cancer",
                "s_acne" => "Acne",
                "s_other" => "Skin Other",
                "s_disease" => "Skin Disease",
                "p_diagnosis" => "Psych Diagnosis",
                "p_medication" => "Psych Medication",
                "abnormal_blood" => "Endo Abnormal Blood",
                "fh_blood_problems" => "FH Blood Problems",
                "hiv" => "HIV",
                "hai_status" => "HAI Status",
            ];
            
            // Organize by medical systems based on actual form structure
            $ros_systems = [
                'Constitutional' => [
                    'weight_change', 'weakness', 'fatigue', 'anorexia', 'fever', 
                    'chills', 'night_sweats', 'insomnia', 'irritability', 'heat_or_cold', 'intolerance'
                ],
                'Eyes' => [
                    'change_in_vision', 'glaucoma_history', 'eye_pain', 'irritation', 'redness', 
                    'excessive_tearing', 'double_vision', 'blind_spots', 'photophobia'
                ],
                'ENT' => [
                    'hearing_loss', 'discharge', 'pain', 'vertigo', 'tinnitus', 'frequent_colds', 
                    'sore_throat', 'sinus_problems', 'post_nasal_drip', 'nosebleed', 'snoring', 'apnea'
                ],
                'Breast' => [
                    'breast_mass', 'breast_discharge', 'biopsy', 'abnormal_mammogram'
                ],
                'Respiratory' => [
                    'cough', 'sputum', 'shortness_of_breath', 'wheezing', 'hemoptsyis', 'asthma', 'copd'
                ],
                'Cardiovascular' => [
                    'chest_pain', 'palpitation', 'syncope', 'pnd', 'doe', 'orthopnea', 
                    'peripheal', 'edema', 'legpain_cramping', 'history_murmur', 'arrythmia', 'heart_problem'
                ],
                'Gastrointestinal' => [
                    'dysphagia', 'heartburn', 'bloating', 'belching', 'flatulence', 'nausea', 'vomiting', 
                    'hematemesis', 'gastro_pain', 'food_intolerance', 'hepatitis', 'jaundice', 
                    'hematochezia', 'changed_bowel', 'diarrhea', 'constipation'
                ],
                'Genitourinary' => [
                    'polyuria', 'polydypsia', 'dysuria', 'hematuria', 'frequency', 'urgency', 
                    'incontinence', 'renal_stones', 'utis', 'hesitancy', 'dribbling', 'stream', 'nocturia'
                ],
                'Male GU' => [
                    'erections', 'ejaculations'
                ],
                'Female GU/GYN' => [
                    'g', 'p', 'ap', 'lc', 'mearche', 'menopause', 'lmp', 'f_frequency', 
                    'f_flow', 'f_symptoms', 'abnormal_hair_growth', 'f_hirsutism'
                ],
                'Musculoskeletal' => [
                    'joint_pain', 'swelling', 'm_redness', 'm_warm', 'm_stiffness', 
                    'muscle', 'm_aches', 'fms', 'arthritis'
                ],
                'Neurological' => [
                    'loc', 'seizures', 'stroke', 'tia', 'n_numbness', 'n_weakness', 'paralysis', 
                    'intellectual_decline', 'memory_problems', 'dementia', 'n_headache'
                ],
                'Skin' => [
                    's_cancer', 'psoriasis', 's_acne', 's_other', 's_disease'
                ],
                'Psychiatric' => [
                    'p_diagnosis', 'p_medication', 'depression', 'anxiety', 'social_difficulties'
                ],
                'Endocrine' => [
                    'thyroid_problems', 'diabetes', 'abnormal_blood'
                ],
                'Hematologic/Immunologic' => [
                    'anemia', 'fh_blood_problems', 'bleeding_problems', 'allergies', 
                    'frequent_illness', 'hiv', 'hai_status'
                ]
            ];
            
            // Process each system
            foreach ($ros_systems as $system_name => $fields) {
                $positive_findings = [];
                $negative_findings = [];
                
                foreach ($fields as $field) {
                    $value = $ros_data[$field] ?? '';
                    
                    // Skip empty, N/A, and default values
                    if ($value === '' || $value === 'N/A' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                        continue;
                    }
                    
                    // Handle "on" value (checkbox)
                    if ($value === 'on') {
                        $value = 'Yes';
                    }
                    
                    // Get display name
                    $display_name = $field_map[$field] ?? ucwords(str_replace('_', ' ', $field));
                    
                    // Check for positive findings (case insensitive)
                    if (strtoupper($value) === 'YES') {
                        $positive_findings[] = $display_name . ': ' . $value;
                    } elseif (strtoupper($value) === 'NO') {
                        $negative_findings[] = $display_name . ': ' . $value;
                    }
                }
                
                // Create ROS entries for this system if there are any findings
                if (!empty($positive_findings) || !empty($negative_findings)) {
                    $content_parts = [];
                    
                    if (!empty($positive_findings)) {
                        $content_parts[] = implode(', ', $positive_findings);
                    }
                    
                    if (!empty($negative_findings)) {
                        $content_parts[] = implode(', ', $negative_findings);
                    }
                    
                    if (!empty($content_parts)) {
                        $rosData[] = [
                            'type' => 'ros',
                            'header' => 'Review of Systems',
                            'content' => $system_name . ': ' . implode('; ', $content_parts),
                            'date' => $ros_data['date']
                        ];
                    }
                }
            }
        }
        
        // Include free text comments if present
        if (!empty($ros_data['comments'])) {
            $rosData[] = [
                'type' => 'ros',
                'header' => 'Review of Systems',
                'content' => 'Comments: ' . $ros_data['comments'],
                'date' => $ros_data['date']
            ];
        }
        
        // Process LBF Medical Exam forms for ROS data
        while ($lbf_form = sqlFetchArray($lbf_form_results)) {
            $form_id = $lbf_form['form_id'];
            $encounter = $lbf_form['encounter'];
            $formdir = $lbf_form['formdir'];
            
            // Use formdir for layout_options query and form_id for data
            $form_layout_id = $formdir;  // LBF_Medical_Exam
            
            error_log("ROS Debug: Processing LBF form - FormDir: $formdir, FormID: $form_id");
            
            $lbf_ros_findings = getLBFROSData($form_id, $encounter, $form_layout_id, $lbf_form['date']);
            error_log("ROS Debug: Found " . count($lbf_ros_findings) . " LBF ROS findings");
            $rosData = array_merge($rosData, $lbf_ros_findings);
        }
        
    } catch (Exception $e) {
        error_log("ROS query error: " . $e->getMessage());
    }
    
    return $rosData;
}

/**
 * Helper function to extract Review of Systems data from LBF forms
 */
function getLBFROSData($form_id, $encounter, $form_layout_id, $form_date) {
    $rosData = [];
    
    try {
        error_log("LBF ROS Debug: Starting processing for form_id: $form_id, form_layout_id: $form_layout_id");
        
        // Get layout options using the form_layout_id (LBF_Medical_Exam)
        $layout_sql = "SELECT * FROM layout_options 
                      WHERE form_id = ? AND uor > 0 
                      ORDER BY group_id, seq";
        $layout_results = sqlStatement($layout_sql, [$form_layout_id]);
        
        $layout_count = sqlNumRows($layout_results);
        error_log("LBF ROS Debug: Found $layout_count layout options for form_layout_id: $form_layout_id");
        
        if ($layout_count == 0) {
            error_log("LBF ROS Debug: No layout options found for form_name: $form_name");
            return $rosData;
        }
        
        $ros_systems = [];
        
        while ($frow = sqlFetchArray($layout_results)) {
            $field_id = $frow['field_id'];
            $group_id = $frow['group_id'];
            $title = $frow['title'];
            
            error_log("LBF ROS Debug: Processing field - field_id: $field_id, group_id: $group_id, title: $title");
            
            // Get current value for this field
            $currvalue = lbf_current_value($frow, $form_id, $encounter);
            
            error_log("LBF ROS Debug: Field $field_id returned value: " . ($currvalue === false ? 'FALSE' : "'" . $currvalue . "'"));
            
            if ($currvalue === false || $currvalue === '') {
                error_log("LBF ROS Debug: Skipping field $field_id - empty or false value");
                continue;
            }
            
            // Check if this is a ROS related field
            // Group 2 = "Female ROS", but also include fields that might be ROS-related
            $is_ros = ($group_id == '2' || strpos($field_id, 'ros_') === 0 || strpos(strtolower($title), 'ros') !== false);
                
            error_log("LBF ROS Debug: Field $field_id in group '$group_id' - is_ros: " . ($is_ros ? 'YES' : 'NO'));
            
            if ($is_ros) {
                // Parse the multi-select checkbox format or handle free text
                if (strpos($currvalue, ':') !== false && strpos($currvalue, '|') !== false) {
                    // Multi-select checkboxes
                    $findings = parseMultiSelectData($currvalue, $frow);
                    
                    // Also check for associated free text fields
                    // $associated_text_findings = getAssociatedFreeTextFields($form_layout_id, $form_id, $encounter, $field_id);
                    // $findings = array_merge($findings, $associated_text_findings);
                } else {
                    // Free text or single value
                    $findings = [$title . ': ' . $currvalue];
                }
                
                if (!empty($findings)) {
                    // Determine the body system based on field_id
                    $system_name = getROSSystemName($field_id);
                    
                    if (!isset($ros_systems[$system_name])) {
                        $ros_systems[$system_name] = [];
                    }
                    
                    error_log("LBF ROS Debug: Adding findings to system '$system_name': " . implode(', ', $findings));
                    $ros_systems[$system_name] = array_merge($ros_systems[$system_name], $findings);
                }
            } else {
                error_log("LBF ROS Debug: Skipping field $field_id - not classified as ROS (group $group_id)");
            }
        }
        
        error_log("LBF ROS Debug: Final systems count: " . count($ros_systems));
        foreach ($ros_systems as $sys => $findings) {
            error_log("LBF ROS Debug: System '$sys' has " . count($findings) . " findings");
        }
        
        // Create ROS findings for each system with proper formatting
        foreach ($ros_systems as $system => $system_findings) {
            if (!empty($system_findings)) {
                // Format as: System: finding1, finding2, finding3 (no redundant header)
                $content = $system . ": " . implode(", ", $system_findings);
                
                $rosData[] = [
                    'type' => 'ros',
                    'header' => 'Review of Systems',
                    'content' => $content,
                    'date' => $form_date
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("LBF ROS processing error: " . $e->getMessage());
    }
    
    return $rosData;
}

/**
 * Format social history values for better readability
 */
function formatSocialHistoryValue($value, $type) {
    if (empty($value)) {
        return '';
    }
    
    // Clean up the value - handle various formatting issues
    $cleanValue = $value;
    
    // Remove pipe characters and handle concatenated values
    $cleanValue = str_replace('|', '', $cleanValue);
    
    // Handle specific field types with comprehensive mappings
    switch ($type) {
        case 'tobacco':
            return formatTobaccoValue($cleanValue);
            
        case 'alcohol':
            return formatAlcoholValue($cleanValue);
            
        case 'recreational_drugs':
            return formatRecreationalDrugsValue($cleanValue);
            
        case 'coffee':
            return formatCoffeeValue($cleanValue);
            
        case 'exercise_patterns':
            return formatExerciseValue($cleanValue);
            
        case 'general':
        default:
            return formatGeneralHistoryValue($cleanValue, $type);
    }
}

/**
 * Format tobacco values
 */
function formatTobaccoValue($value) {
    $tobaccoMappings = [
        'nevertobacco' => 'Never used tobacco',
        'quittobacco' => 'Former tobacco user (quit)',
        'currenttobacco' => 'Current tobacco user',
        'occasionaltobacco' => 'Occasional tobacco use',
        'never' => 'Never used tobacco',
        'quit' => 'Former tobacco user (quit)',
        'current' => 'Current tobacco user',
        'occasional' => 'Occasional tobacco use'
    ];
    
    // Check for numeric values (years since quit, packs per day, etc.)
    if (preg_match('/(\w+)(\d+)/', $value, $matches)) {
        $baseValue = $matches[1];
        $number = $matches[2];
        
        if (isset($tobaccoMappings[$baseValue])) {
            if ($baseValue === 'quit' || $baseValue === 'quittobacco') {
                return $tobaccoMappings[$baseValue] . " ($number years ago)";
            } else {
                return $tobaccoMappings[$baseValue] . " ($number per day)";
            }
        }
    }
    
    // Check for direct mappings
    foreach ($tobaccoMappings as $key => $description) {
        if (stripos($value, $key) !== false) {
            return $description;
        }
    }
    
    return formatGeneralValue($value);
}

/**
 * Format alcohol values
 */
function formatAlcoholValue($value) {
    $alcoholMappings = [
        'neveralcohol' => 'Never drinks alcohol',
        'currentalcohol' => 'Current alcohol use',
        'quitalcohol' => 'Former alcohol use (quit)',
        'occasionalalcohol' => 'Occasional alcohol use',
        'never' => 'Never drinks alcohol',
        'current' => 'Current alcohol use',
        'quit' => 'Former alcohol use (quit)',
        'occasional' => 'Occasional alcohol use',
        'social' => 'Social drinker',
        'moderate' => 'Moderate alcohol use',
        'heavy' => 'Heavy alcohol use'
    ];
    
    // Check for numeric values (drinks per week, etc.)
    if (preg_match('/(\w+)(\d+)/', $value, $matches)) {
        $baseValue = $matches[1];
        $number = $matches[2];
        
        if (isset($alcoholMappings[$baseValue])) {
            return $alcoholMappings[$baseValue] . " ($number drinks per week)";
        }
    }
    
    // Check for direct mappings
    foreach ($alcoholMappings as $key => $description) {
        if (stripos($value, $key) !== false) {
            return $description;
        }
    }
    
    return formatGeneralValue($value);
}

/**
 * Format recreational drugs values
 */
function formatRecreationalDrugsValue($value) {
    $drugMappings = [
        'neverrecreational_drugs' => 'Never used recreational drugs',
        'currentrecreational_drugs' => 'Current recreational drug use',
        'quitrecreational_drugs' => 'Former recreational drug use (quit)',
        'occasionalrecreational_drugs' => 'Occasional recreational drug use',
        'never' => 'Never used recreational drugs',
        'current' => 'Current recreational drug use',
        'quit' => 'Former recreational drug use (quit)',
        'occasional' => 'Occasional recreational drug use'
    ];
    
    // Check for direct mappings
    foreach ($drugMappings as $key => $description) {
        if (stripos($value, $key) !== false) {
            return $description;
        }
    }
    
    return formatGeneralValue($value);
}

/**
 * Format coffee consumption values
 */
function formatCoffeeValue($value) {
    $coffeeMappings = [
        'nevercoffee' => 'Never drinks coffee',
        'currentcoffee' => 'Current coffee drinker',
        'quitcoffee' => 'Former coffee drinker (quit)',
        'occasionalcoffee' => 'Occasional coffee drinker',
        'never' => 'Never drinks coffee',
        'current' => 'Current coffee drinker',
        'quit' => 'Former coffee drinker (quit)',
        'occasional' => 'Occasional coffee drinker'
    ];
    
    // Check for numeric values (cups per day, etc.)
    if (preg_match('/(\w+)(\d+)/', $value, $matches)) {
        $baseValue = $matches[1];
        $number = $matches[2];
        
        if (isset($coffeeMappings[$baseValue])) {
            return $coffeeMappings[$baseValue] . " ($number cups per day)";
        }
    }
    
    // Check for direct mappings
    foreach ($coffeeMappings as $key => $description) {
        if (stripos($value, $key) !== false) {
            return $description;
        }
    }
    
    return formatGeneralValue($value);
}

/**
 * Format exercise patterns values
 */
function formatExerciseValue($value) {
    // Handle concatenated values like "not_applicableexercise_patterns"
    $value = str_replace('not_applicableexercise_patterns', 'not_applicable', $value);
    
    $exerciseMappings = [
        'not_applicable' => 'Exercise patterns not applicable',
        'not_app' => 'Exercise patterns not applicable',
        'sedentary' => 'Sedentary lifestyle',
        'light' => 'Light exercise',
        'moderate' => 'Moderate exercise',
        'heavy' => 'Heavy exercise',
        'daily' => 'Daily exercise',
        'weekly' => 'Weekly exercise',
        'never' => 'No regular exercise',
        'irregular' => 'Irregular exercise pattern'
    ];
    
    // Check for direct mappings
    foreach ($exerciseMappings as $key => $description) {
        if (stripos($value, $key) !== false) {
            return $description;
        }
    }
    
    return formatGeneralValue($value);
}

/**
 * Format general history values with common medical abbreviations
 */
function formatGeneralHistoryValue($value, $type) {
    // Handle pipe-separated medical abbreviations like "ht|db|hep|gb"
    if (strpos($value, '|') !== false || preg_match('/^[a-z]{2,4}(\|[a-z]{2,4})*$/i', $value)) {
        return parseMedicalAbbreviations($value, '|');
    }
    
    // Handle concatenated medical abbreviations like "Htdbhepgb" or "htdbhepgb"
    if (preg_match('/^[a-z]+$/i', $value) && strlen($value) >= 4) {
        return parseConcatenatedAbbreviations($value);
    }
    
    return formatGeneralValue($value);
}

/**
 * Parse medical abbreviations separated by pipes
 */
function parseMedicalAbbreviations($value, $separator = '|') {
    $parts = explode($separator, $value);
    $medicalAbbreviations = getMedicalAbbreviationsMap();
    
    $expandedTerms = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if (!empty($part)) {
            $expandedTerms[] = $medicalAbbreviations[strtolower($part)] ?? ucwords(str_replace('_', ' ', $part));
        }
    }
    
    if (!empty($expandedTerms)) {
        return implode(', ', $expandedTerms);
    }
    
    return formatGeneralValue($value);
}

/**
 * Parse concatenated medical abbreviations like "Htdbhepgb"
 */
function parseConcatenatedAbbreviations($value) {
    $medicalAbbreviations = getMedicalAbbreviationsMap();
    $lowerValue = strtolower($value);
    $expandedTerms = [];
    $position = 0;
    $valueLength = strlen($lowerValue);
    
    // Try to match known abbreviations from the concatenated string
    while ($position < $valueLength) {
        $matched = false;
        
        // Try longer abbreviations first (4 chars, then 3, then 2)
        for ($length = 4; $length >= 2; $length--) {
            if ($position + $length <= $valueLength) {
                $substring = substr($lowerValue, $position, $length);
                
                if (isset($medicalAbbreviations[$substring])) {
                    $expandedTerms[] = $medicalAbbreviations[$substring];
                    $position += $length;
                    $matched = true;
                    break;
                }
            }
        }
        
        // If no match found, move to next character
        if (!$matched) {
            $position++;
        }
    }
    
    if (!empty($expandedTerms)) {
        return implode(', ', $expandedTerms);
    }
    
    // If no medical abbreviations found, return the original value formatted
    return formatGeneralValue($value);
}

/**
 * Get the medical abbreviations mapping
 */
function getMedicalAbbreviationsMap() {
    return [
        'ht' => 'Hypertension',
        'htn' => 'Hypertension', 
        'db' => 'Diabetes',
        'dm' => 'Diabetes Mellitus',
        'hep' => 'Hepatitis',
        'gb' => 'Gallbladder disease',
        'cad' => 'Coronary Artery Disease',
        'chf' => 'Congestive Heart Failure',
        'copd' => 'COPD',
        'asthma' => 'Asthma',
        'stroke' => 'Stroke',
        'mi' => 'Myocardial Infarction',
        'afib' => 'Atrial Fibrillation',
        'pvd' => 'Peripheral Vascular Disease',
        'ckd' => 'Chronic Kidney Disease',
        'esrd' => 'End Stage Renal Disease',
        'hypothyroid' => 'Hypothyroidism',
        'hyperthyroid' => 'Hyperthyroidism',
        'depression' => 'Depression',
        'anxiety' => 'Anxiety',
        'bipolar' => 'Bipolar Disorder',
        'cancer' => 'Cancer',
        'osteoporosis' => 'Osteoporosis',
        'arthritis' => 'Arthritis',
        'gerd' => 'GERD',
        'ibs' => 'Irritable Bowel Syndrome',
        'sleep_apnea' => 'Sleep Apnea',
        // Additional common abbreviations
        'bp' => 'Blood Pressure',
        'hr' => 'Heart Rate',
        'rr' => 'Respiratory Rate',
        'temp' => 'Temperature',
        'wt' => 'Weight',
        'bmi' => 'Body Mass Index',
        'o2sat' => 'Oxygen Saturation',
        'ecg' => 'Electrocardiogram',
        'echo' => 'Echocardiogram',
        'ct' => 'CT Scan',
        'mri' => 'MRI',
        'xray' => 'X-Ray',
        'lab' => 'Laboratory',
        'cbc' => 'Complete Blood Count',
        'bmp' => 'Basic Metabolic Panel',
        'cmp' => 'Comprehensive Metabolic Panel',
        'lipid' => 'Lipid Panel',
        'tsh' => 'Thyroid Stimulating Hormone',
        'psa' => 'Prostate Specific Antigen',
        'hba1c' => 'Hemoglobin A1C',
        'pt' => 'Prothrombin Time',
        'ptt' => 'Partial Thromboplastin Time',
        'inr' => 'International Normalized Ratio'
    ];
}

/**
 * General value formatter for fallback cases
 */
function formatGeneralValue($value) {
    // Handle common abbreviations and clean up
    $generalMappings = [
        'not_applicable' => 'Not applicable',
        'not_app' => 'Not applicable',
        'n/a' => 'Not applicable',
        'na' => 'Not applicable',
        'none' => 'None',
        'unknown' => 'Unknown',
        'unremarkable' => 'Unremarkable',
        'normal' => 'Normal',
        'abnormal' => 'Abnormal'
    ];
    
    $lowerValue = strtolower(trim($value));
    
    // Check for direct mappings
    if (isset($generalMappings[$lowerValue])) {
        return $generalMappings[$lowerValue];
    }
    
    // Clean up underscores and capitalize
    $cleaned = str_replace('_', ' ', $value);
    $cleaned = ucwords(trim($cleaned));
    
    // If still empty or just spaces, return a default
    if (empty(trim($cleaned))) {
        return 'Not specified';
    }
    
    return $cleaned;
}

?>

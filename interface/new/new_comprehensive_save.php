<?php

/**
 * new_comprehensive_save.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2009-2017 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Services\ContactService;

if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
    CsrfUtils::csrfNotVerified();
}

// Validation for non-unique external patient identifier.
$alertmsg = '';
if (!empty($_POST["form_pubpid"])) {
    $form_pubpid = trim($_POST["form_pubpid"]);
    $result = sqlQuery("SELECT count(*) AS count FROM patient_data WHERE " .
    "pubpid = ?", array($form_pubpid));
    if ($result['count']) {
        // Error, not unique.
        $alertmsg = xl('Warning: Patient ID is not unique!');
    }
}

require_once("$srcdir/pid.inc.php");
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/options.inc.php");

/**
 * Generate HL7 ADT^A04 message for patient registration
 */
function generatePatientADTMessage($pid) {

  $patient = sqlQuery("SELECT * FROM patient_data WHERE pid = ?", array($pid));
    if (!$patient) {
        return false;
    }

    $timestamp = date('YmdHis');
    $msgId = $timestamp; // Use timestamp as unique message ID

    // Get facility name (one active facility)
    $fac_name = sqlQuery("SELECT name FROM facility WHERE inactive = 0 LIMIT 1")['name'];

    // Determine clinic name based on facility
    $clinic_name = ($fac_name == "El Paso Department of Public Health Respiratory Disease Clinic") ? "TB Clinic" : "STD Clinic";

    // Build patient name (format: LASTNAME^FIRSTNAME^MIDDLENAME)
    $patientName = strtoupper($patient['lname'] . '^' . $patient['fname']);
    if (!empty($patient['mname'])) {
        $patientName .= '^' . strtoupper($patient['mname']);
    }

    // Patient DOB (format: YYYYMMDD)
    $dob = date('Ymd', strtotime($patient['DOB']));

    // Patient sex (M, F, or F as default)
    $sex = strtoupper(substr($patient['sex'], 0, 1));
    if ($sex == 'M') {
        $sex = 'M';
    } else {
        $sex = 'F';
    }

    // Patient race (from patient_data)
    $race = strtoupper($patient['race'] ?? '');

    // Patient ethnicity from patient_data
    $ethnicity_raw = $patient['ethnicity'] ?? '';
    if (strtolower($ethnicity_raw) == 'hisp_or_latin') {
        $ethnicity = 'HISPANIC';
    } else {
        $ethnicity = strtoupper($ethnicity_raw);
    }

    // Patient language, marital status, religion from patient_data
    $language = strtoupper($patient['language'] ?? '');
    $marital = strtoupper($patient['status'] ?? '');
    $religion = strtoupper($patient['religion'] ?? '');

    // Address from patient_data (format: STREET^^CITY^STATE^ZIP)
    $addressLine = '';
    if (!empty($patient['street'])) {
        $street = str_replace(',', '', $patient['street']); // Remove commas
        $addressLine = strtoupper($street) . '^^';
        if (!empty($patient['city'])) {
            $cityResult = sqlQuery("SELECT title FROM list_options WHERE list_id = 'Cities' AND option_id = ?", array($patient['city']));
            $city = strtoupper($cityResult['title'] ?? $patient['city']);
            $addressLine .= $city;
        }
        $addressLine .= '^';
        if (!empty($patient['state'])) {
            $stateResult = sqlQuery("SELECT title FROM list_options WHERE list_id = 'state' AND option_id = ?", array($patient['state']));
            $state = strtoupper($stateResult['title'] ?? $patient['state']);
            $addressLine .= $state;
        }
        $addressLine .= '^';
        if (!empty($patient['postal_code'])) {
            $addressLine .= $patient['postal_code'];
        }
    }

    // Phone from patient_data
    $phoneNumber = '';
    if (!empty($patient['phone_home'])) {
        $phoneNumber = preg_replace('/\D/', '', $patient['phone_home']);
    }

    // Patient account number (use pubpid or pid) - empty for patient registration
    $accountNumber = '';
    $patientAccountNumber = $accountNumber;

    // Attending provider name from patient provider
    $provider_id = $patient['providerID'] ?? null;
    $provider_name = '';
    if ($provider_id) {
        $provider_info = sqlQuery("SELECT fname, lname FROM users WHERE id = ?", array($provider_id));
        if ($provider_info) {
            $provider_name = strtoupper($provider_info['lname'] . '^' . $provider_info['fname']);
        }
    }

    // Build HL7 message according to required format
    // MSH segment
    $hl7 = "MSH|^~\\&|OPENEMR|{$clinic_name}|PYXIS|FACILITY|{$timestamp}||ADT^A04|{$msgId}|P|2.3\r\n";

    // PID segment (Patient Identification)
    // Fields: Set ID|Patient ID (internal)|Patient ID List||Patient Name||DOB|Sex||Race|Address|||Language|Marital Status|Religion|Patient Account Number||||Ethnicity||||||||N
    $hl7 .= "PID|1|{$pid}|{$pid}^^^OPENEMR^MRN||{$patientName}||{$dob}|{$sex}||{$race}|{$addressLine}||||{$language}|{$marital}|{$religion}|{$accountNumber}||||{$ethnicity}||||||||N\r\n";
    // $hl7 .= "AL1|1|DA^Drug Allergy||||||||\r\n";

    // PD1 segment (Additional Demographics)
    $hl7 .= "PD1||||||NONE\r\n";

    // PV1 segment (Patient Visit)
    // Fields: Set ID|Patient Class|Assigned Patient Location|Admission Type|...|Attending Provider|...
    $hl7 .= "PV1|1|O|{$clinic_name}|||||{$provider_name}|||||||||||{$patientAccountNumber}|||||||||||||||||||||||||{$timestamp}||\r\n";

    return $hl7;
}

/**
 * Save HL7 message to file
 */
function saveHL7Message($hl7_message, $pid, $type = 'adt') {
    $hl7_dir = dirname(__FILE__) . '/hl7_outbound';
    if (!is_dir($hl7_dir)) {
        mkdir($hl7_dir, 0755, true);
    }

    $timestamp = date('YmdHis');
    $filename = "{$type}_patient_{$pid}_{$timestamp}.hl7";
    $filepath = $hl7_dir . '/' . $filename;

    file_put_contents($filepath, $hl7_message);
    error_log("HL7 {$type} message saved: {$filepath}");

    return $filepath;
}

// Update patient_data and employer_data:
// First, we prepare the data for insert into DB by querying the layout
// fields to see what valid fields we have to insert from the post we are receiving
$newdata = array();
$newdata['patient_data'] = array();
$newdata['employer_data'] = array();
$fres = sqlStatement("SELECT * FROM layout_options " .
  "WHERE form_id = 'DEM' AND (uor > 0 OR field_id = 'pubpid') AND field_id != '' " .
  "ORDER BY group_id, seq");
$addressFieldsToSave = array();
while ($frow = sqlFetchArray($fres)) {
    $data_type = $frow['data_type'];
    $field_id  = $frow['field_id'];
  // $value     = '';
    $colname   = $field_id;
    $tblname   = 'patient_data';
    if (strpos($field_id, 'em_') === 0) {
        $colname = substr($field_id, 3);
        $tblname = 'employer_data';
    }

  //get value only if field exist in $_POST (prevent deleting of field with disabled attribute)
    // TODO: why is this a different conditional than demographics_save.php...
    if ($data_type == 54) { // address list
        $addressFieldsToSave[$field_id] = get_layout_form_value($frow);
    } else if (isset($_POST["form_$field_id"]) || $field_id == "pubpid") {
        $value = get_layout_form_value($frow);
        $newdata[$tblname][$colname] = $value;
    }
}

// Use the global helper to use the PatientService to create a new patient
// The result contains the pid, so use that to set the global session pid
$pid = updatePatientData(null, $newdata['patient_data'], true);
if (empty($pid)) {
    die("Internal error: setpid(" . text($pid) . ") failed!");
}
setpid($pid);

// Generate and save HL7 ADT^A04 message for patient registration
$adt_message = generatePatientADTMessage($pid);
if ($adt_message) {
    saveHL7Message($adt_message, $pid, 'adt');
}

if (!$GLOBALS['omit_employers']) {
    updateEmployerData($pid, $newdata['employer_data'], true);
}

if (!empty($addressFieldsToSave)) {
    // TODO: we would handle other types of address fields here, for now we will just go through and populate the patient
    // address information
    // TODO: how are error messages supposed to display if the save fails?
    foreach ($addressFieldsToSave as $field => $addressFieldData) {
        // if we need to save other kinds of addresses we could do that here with our field column...
        $contactService = new ContactService();
        $contactService->saveContactsForPatient($pid, $addressFieldData);
    }
}

$i1dob = DateToYYYYMMDD(filter_input(INPUT_POST, "i1subscriber_DOB"));
$i1date = DateToYYYYMMDD(filter_input(INPUT_POST, "i1effective_date"));

newHistoryData($pid);
// no need to save insurance for simple demos
if (!$GLOBALS['simplified_demographics']) {
    newInsuranceData(
        $pid,
        "primary",
        filter_input(INPUT_POST, "i1provider"),
        filter_input(INPUT_POST, "i1policy_number"),
        filter_input(INPUT_POST, "i1group_number"),
        filter_input(INPUT_POST, "i1plan_name"),
        filter_input(INPUT_POST, "i1subscriber_lname"),
        filter_input(INPUT_POST, "i1subscriber_mname"),
        filter_input(INPUT_POST, "i1subscriber_fname"),
        filter_input(INPUT_POST, "form_i1subscriber_relationship"),
        filter_input(INPUT_POST, "i1subscriber_ss"),
        $i1dob,
        filter_input(INPUT_POST, "i1subscriber_street"),
        filter_input(INPUT_POST, "i1subscriber_postal_code"),
        filter_input(INPUT_POST, "i1subscriber_city"),
        filter_input(INPUT_POST, "form_i1subscriber_state"),
        filter_input(INPUT_POST, "form_i1subscriber_country"),
        filter_input(INPUT_POST, "i1subscriber_phone"),
        filter_input(INPUT_POST, "i1subscriber_employer"),
        filter_input(INPUT_POST, "i1subscriber_employer_street"),
        filter_input(INPUT_POST, "i1subscriber_employer_city"),
        filter_input(INPUT_POST, "i1subscriber_employer_postal_code"),
        filter_input(INPUT_POST, "form_i1subscriber_employer_state"),
        filter_input(INPUT_POST, "form_i1subscriber_employer_country"),
        filter_input(INPUT_POST, 'i1copay'),
        filter_input(INPUT_POST, 'form_i1subscriber_sex'),
        $i1date,
        filter_input(INPUT_POST, 'i1accept_assignment')
    );

    //Dont save more than one insurance since only one is allowed / save space in DB
    if (!$GLOBALS['insurance_only_one']) {
        $i2dob = DateToYYYYMMDD(filter_input(INPUT_POST, "i2subscriber_DOB"));
        $i2date = DateToYYYYMMDD(filter_input(INPUT_POST, "i2effective_date"));

        newInsuranceData(
            $pid,
            "secondary",
            filter_input(INPUT_POST, "i2provider"),
            filter_input(INPUT_POST, "i2policy_number"),
            filter_input(INPUT_POST, "i2group_number"),
            filter_input(INPUT_POST, "i2plan_name"),
            filter_input(INPUT_POST, "i2subscriber_lname"),
            filter_input(INPUT_POST, "i2subscriber_mname"),
            filter_input(INPUT_POST, "i2subscriber_fname"),
            filter_input(INPUT_POST, "form_i2subscriber_relationship"),
            filter_input(INPUT_POST, "i2subscriber_ss"),
            $i2dob,
            filter_input(INPUT_POST, "i2subscriber_street"),
            filter_input(INPUT_POST, "i2subscriber_postal_code"),
            filter_input(INPUT_POST, "i2subscriber_city"),
            filter_input(INPUT_POST, "form_i2subscriber_state"),
            filter_input(INPUT_POST, "form_i2subscriber_country"),
            filter_input(INPUT_POST, "i2subscriber_phone"),
            filter_input(INPUT_POST, "i2subscriber_employer"),
            filter_input(INPUT_POST, "i2subscriber_employer_street"),
            filter_input(INPUT_POST, "i2subscriber_employer_city"),
            filter_input(INPUT_POST, "i2subscriber_employer_postal_code"),
            filter_input(INPUT_POST, "form_i2subscriber_employer_state"),
            filter_input(INPUT_POST, "form_i2subscriber_employer_country"),
            filter_input(INPUT_POST, 'i2copay'),
            filter_input(INPUT_POST, 'form_i2subscriber_sex'),
            $i2date,
            filter_input(INPUT_POST, 'i2accept_assignment')
        );

        $i3dob = DateToYYYYMMDD(filter_input(INPUT_POST, "i3subscriber_DOB"));
        $i3date = DateToYYYYMMDD(filter_input(INPUT_POST, "i3effective_date"));

        newInsuranceData(
            $pid,
            "tertiary",
            filter_input(INPUT_POST, "i3provider"),
            filter_input(INPUT_POST, "i3policy_number"),
            filter_input(INPUT_POST, "i3group_number"),
            filter_input(INPUT_POST, "i3plan_name"),
            filter_input(INPUT_POST, "i3subscriber_lname"),
            filter_input(INPUT_POST, "i3subscriber_mname"),
            filter_input(INPUT_POST, "i3subscriber_fname"),
            filter_input(INPUT_POST, "form_i3subscriber_relationship"),
            filter_input(INPUT_POST, "i3subscriber_ss"),
            $i3dob,
            filter_input(INPUT_POST, "i3subscriber_street"),
            filter_input(INPUT_POST, "i3subscriber_postal_code"),
            filter_input(INPUT_POST, "i3subscriber_city"),
            filter_input(INPUT_POST, "form_i3subscriber_state"),
            filter_input(INPUT_POST, "form_i3subscriber_country"),
            filter_input(INPUT_POST, "i3subscriber_phone"),
            filter_input(INPUT_POST, "i3subscriber_employer"),
            filter_input(INPUT_POST, "i3subscriber_employer_street"),
            filter_input(INPUT_POST, "i3subscriber_employer_city"),
            filter_input(INPUT_POST, "i3subscriber_employer_postal_code"),
            filter_input(INPUT_POST, "form_i3subscriber_employer_state"),
            filter_input(INPUT_POST, "form_i3subscriber_employer_country"),
            filter_input(INPUT_POST, 'i3copay'),
            filter_input(INPUT_POST, 'form_i3subscriber_sex'),
            $i3date,
            filter_input(INPUT_POST, 'i3accept_assignment')
        );
    }
}
?>
<html>
<body>
<script>
<?php
if ($alertmsg) {
    echo "alert(" . js_escape($alertmsg) . ");\n";
}

  echo "window.location='$rootdir/patient_file/summary/demographics.php?" .
    "set_pid=" . attr_url($pid) . "&is_new=1';\n";
?>
</script>

</body>
</html>


<?php

/**
 * demographics_save.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../../globals.php");
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Services\ContactService;

if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
    CsrfUtils::csrfNotVerified();
}
global $pid;
// Check authorization.
if ($pid) {
    if (!AclMain::aclCheckCore('patients', 'demo', '', 'write')) {
        die(xlt('Updating demographics is not authorized.'));
    }

    $tmp = getPatientData($pid, "squad");
    if ($tmp['squad'] && ! AclMain::aclCheckCore('squads', $tmp['squad'])) {
        die(xlt('You are not authorized to access this squad.'));
    }
} else {
    if (!AclMain::aclCheckCore('patients', 'demo', '', array('write','addonly'))) {
        die(xlt('Adding demographics is not authorized.'));
    }
}

foreach ($_POST as $key => $val) {
    if ($val == "MM/DD/YYYY") {
        $_POST[$key] = "";
    }
}

// Update patient_data and employer_data:
//
$newdata = array();
$newdata['patient_data']['id'] = $_POST['db_id'];
$fres = sqlStatement("SELECT * FROM layout_options " .
  "WHERE form_id = 'DEM' AND uor > 0 AND field_id != '' " .
  "ORDER BY group_id, seq");

$addressFieldsToSave = array();
while ($frow = sqlFetchArray($fres)) {
    $data_type = $frow['data_type'];
    if ((int)$data_type === 52) {
        // patient name history is saved in add.
        continue;
    }
    $field_id = $frow['field_id'];
    $colname = $field_id;
    $table = 'patient_data';
    if (str_starts_with($field_id, 'em_')) {
        $colname = substr($field_id, 3);
        $table = 'employer_data';
    }

    // Get value only if field exist in $_POST (prevent deleting of field with disabled attribute)
    // *unless* the data_type is a checkbox ("21"), because if the checkbox is unchecked, then it will not
    // have a value set on the form, it will be empty.
    if ($data_type == 54) { // address list
        $addressFieldsToSave[$field_id] = get_layout_form_value($frow);
    } elseif (isset($_POST["form_$field_id"]) || $data_type == 21) {
        $newdata[$table][$colname] = get_layout_form_value($frow);
    }
}

// TODO: All of this should be bundled up inside a transaction...

updatePatientData($pid, $newdata['patient_data']);
if (!$GLOBALS['omit_employers']) {
    updateEmployerData($pid, $newdata['employer_data']);
}

if (!empty($addressFieldsToSave)) {
    // TODO: we would handle other types of address fields here,
    // for now we will just go through and populate the patient
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
$i1date_end = DateToYYYYMMDD(filter_input(INPUT_POST, "i1effective_date_end"));

$swap_value = $_POST['isSwapClicked'] ?? null;
$type = ($swap_value == '2') ? "secondary" : "primary";
newInsuranceData(
    $pid,
    $type,
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
    filter_input(INPUT_POST, 'i1accept_assignment'),
    filter_input(INPUT_POST, 'i1policy_type'),
    $i1date_end
);

//Dont save more than one insurance since only one is allowed / save space in DB
if (!$GLOBALS['insurance_only_one']) {
    $i2dob = DateToYYYYMMDD(filter_input(INPUT_POST, "i2subscriber_DOB"));
    $i2date = DateToYYYYMMDD(filter_input(INPUT_POST, "i2effective_date"));
    $i2date_end = DateToYYYYMMDD(filter_input(INPUT_POST, "i2effective_date_end"));

    // secondary swaps with primary, tertiary with secondary
    if ($swap_value == '2') {
        $type = "primary";
    } elseif ($swap_value == '3') {
        $type = "tertiary";
    } else {
        $type = "secondary";
    }

    newInsuranceData(
        $pid,
        $type,
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
        filter_input(INPUT_POST, 'i2accept_assignment'),
        filter_input(INPUT_POST, 'i2policy_type'),
        $i2date_end
    );

    $i3dob = DateToYYYYMMDD(filter_input(INPUT_POST, "i3subscriber_DOB"));
    $i3date = DateToYYYYMMDD(filter_input(INPUT_POST, "i3effective_date"));
    $i3date_end = DateToYYYYMMDD(filter_input(INPUT_POST, "i3effective_date_end"));

    $type = ($swap_value == '3') ? "secondary" : "tertiary";

    newInsuranceData(
        $pid,
        $type,
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
        filter_input(INPUT_POST, 'i3accept_assignment'),
        filter_input(INPUT_POST, 'i3policy_type'),
        $i3date_end
    );
}

// Function to generate HL7 ADT^A08 message for patient demographics update
function generateDemographicsHL7($pid) {
    $timestamp = date('YmdHis');
    $msgId = $timestamp;

    // Get facility name
    $fac_name = sqlQuery("SELECT name FROM facility WHERE inactive = 0 LIMIT 1")['name'] ?? 'TB CLINIC';
    $clinic_name = ($fac_name == "El Paso Department of Public Health Respiratory Disease Clinic") ? "TB Clinic" : "STD Clinic";

    // Patient data
    $patientData = getPatientData($pid);
    $patientName = strtoupper($patientData['lname'] . '^' . $patientData['fname']);
    if (!empty($patientData['mname'])) {
        $patientName .= '^' . strtoupper($patientData['mname']);
    }
    $patientDob = date('Ymd', strtotime($patientData['DOB']));
    $patientGender = strtoupper($patientData['sex']);
    if ($patientGender == 'MALE') $patientGender = 'M';
    elseif ($patientGender == 'FEMALE') $patientGender = 'F';
    else $patientGender = 'U';

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

    // Business Phone
    $businessPhone = '';
    if (!empty($patientData['phone_biz'])) {
        $phone = preg_replace('/\D/', '', $patientData['phone_biz']);
        if (strlen($phone) >= 10) {
            $businessPhone = '(' . substr($phone, 0, 3) . ')' . substr($phone, 3, 3) . '-' . substr($phone, 6);
        }
    }

    // SSN
    $ssn = $patientData['ss'] ?? '';

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
    $encounterResult = sqlQuery("SELECT encounter FROM form_encounter WHERE pid = ? ORDER BY date DESC LIMIT 1", array($pid));
    $accountNumber = $encounterResult['encounter'] ?? '';

    // Get attending doctor from patient table and users table
    $patientProvider = sqlQuery("SELECT providerID FROM patient_data WHERE pid = ? LIMIT 1", array($pid));
    $providerId = $patientProvider['providerID'] ?? '';
    $providerName = '';
    if (!empty($providerId)) {
        $providerData = sqlQuery("SELECT fname, lname, npi FROM users WHERE id = ? LIMIT 1", array($providerId));
        if ($providerData) {
            $npi = $providerData['npi'] ?? '';
            $providerName = ($npi ? $npi : '') . '^' . strtoupper($providerData['lname'] . '^' . $providerData['fname']);
        }
    }

    // Build HL7 message
    $hl7_message = "";
    $hl7_message .= "MSH|^~\\&|OPENEMR|{$clinic_name}|PYXIS|FACILITY|{$timestamp}||ADT^A08|{$msgId}|P|2.3\r\n";
    $hl7_message .= "EVN|A08|{$timestamp}||||\r\n";
    $hl7_message .= "PID|1|{$pid}|{$pid}^^^OPENEMR^MRN||{$patientName}||{$patientDob}|{$patientGender}||{$race}|{$addressLine}||{$phoneNumber}|{$businessPhone}|{$language}|{$marital}|{$religion}|{$accountNumber}|{$ssn}|||{$ethnicity}||||||||N\r\n";
    $hl7_message .= "PD1||||||NONE\r\n";
    $hl7_message .= "PV1|1|O|{$clinic_name}||||{$providerName}||||||||||||{$accountNumber}|||||||||||||||||||||||||{$timestamp}||\r\n";

    // Save to file
    $outbound_dir = dirname(__FILE__) . '/hl7_messages';
    if (!is_dir($outbound_dir)) {
        mkdir($outbound_dir, 0755, true);
    }
    $filename = "adt_demographics_{$pid}_{$timestamp}.hl7";
    file_put_contents($outbound_dir . '/' . $filename, $hl7_message);
}

// Generate HL7 ADT^A08 message for patient demographics update
generateDemographicsHL7($pid);

// if refresh tab after saving then results in csrf error
include_once("demographics.php");

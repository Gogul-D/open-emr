<?php
/**
 * MMR Immunization Form - save.php
 * Handles both INSERT (mode=new) and UPDATE (mode=update).
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 *
 * LOCATION: interface/forms/mmr_immunization/save.php
 */

require_once("../../globals.php");
require_once("$srcdir/api.inc.php");
require_once("$srcdir/forms.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

// -------------------------------------------------------
// CSRF verification — always required
// -------------------------------------------------------
if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
    CsrfUtils::csrfNotVerified();
}

$table_name  = "form_mmr_immunization";
$form_name   = "MMR Immunization";
$form_folder = "mmr_immunization";

if ($encounter == "") {
    $encounter = date("Ymd");
}

// -------------------------------------------------------
// Build a clean data array from POST
// (sanitise / map field names as needed)
// -------------------------------------------------------
$data = [];

// Vaccination header
$data['vaccination_site']      = $_POST['vaccination_site']      ?? null;
$data['vaccination_date']      = $_POST['vaccination_date']      ?? null;
$data['vaccine_administrator'] = $_POST['vaccine_administrator'] ?? null;

// Medical & social history radios
$data['sick_today']         = $_POST['sick_today']         ?? null;
$data['severe_allergy']     = $_POST['severe_allergy']     ?? null;
$data['vaccines_last_4wks'] = $_POST['vaccines_last_4wks'] ?? null;
$data['steroids_immuno']    = $_POST['steroids_immuno']    ?? null;
$data['blood_transfusion']  = $_POST['blood_transfusion']  ?? null;
$data['pregnant']           = $_POST['pregnant']           ?? null;

// Vaccine consent
$data['consent_patient_name'] = $_POST['consent_patient_name'] ?? null;
$data['consent_date']         = $_POST['consent_date']         ?? null;
$data['consent_relationship'] = $_POST['consent_relationship'] ?? null;

// ImmTrac2 consent
$data['immtrac2_agree']        = $_POST['immtrac2_agree']        ?? null;
$data['immtrac2_date']         = $_POST['immtrac2_date']         ?? null;
$data['immtrac2_relationship'] = $_POST['immtrac2_relationship'] ?? null;

// -------------------------------------------------------
// Insert or Update
// -------------------------------------------------------
if ($_GET["mode"] === "new") {
    // formSubmit() inserts row AND fills standard fields (pid, user, date, etc.)
    $newid = formSubmit($table_name, $data, $_GET["id"], $userauthorized);

    // Link this form record to the encounter in the 'forms' table
    addForm($encounter, $form_name, $newid, $form_folder, $pid, $userauthorized);

} elseif ($_GET["mode"] === "update") {
    // formUpdate() performs UPDATE by id
    formUpdate($table_name, $data, $_GET["id"], $userauthorized);
}

formHeader("Redirecting....");
formJump();      // redirect back to encounter view
formFooter();

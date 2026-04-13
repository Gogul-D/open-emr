<?php
/**
 * MMR Immunization Form - report.php
 * Read-only summary displayed inside the encounter report.
 * Function name MUST match folder name: mmr_immunization_report()
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 *
 * LOCATION: interface/forms/mmr_immunization/report.php
 */

require_once("../../globals.php");
require_once($GLOBALS["srcdir"] . "/api.inc.php");

/**
 * @param int $pid       Patient ID
 * @param int $encounter Encounter ID
 * @param int $cols      Number of columns for layout
 * @param int $id        Form record ID
 */
function mmr_immunization_report($pid, $encounter, $cols, $id)
{
    $table_name = "form_mmr_immunization";

    $data = formFetch($table_name, $id);
    if (!$data) {
        return;
    }

    // Skip internal / empty fields
    $skip = ['id', 'pid', 'user', 'groupname', 'authorized', 'activity', 'date'];

    // Human-readable labels for each field
    $labels = [
        'vaccination_site'      => 'Vaccination Site',
        'vaccination_date'      => 'Vaccination Date',
        'vaccine_administrator' => 'Vaccine Administrator',
        'sick_today'            => 'Patient sick today?',
        'severe_allergy'        => 'Severe allergy after vaccine?',
        'vaccines_last_4wks'    => 'Vaccines received in last 4 weeks?',
        'steroids_immuno'       => 'Steroids / Immunosuppressants (past 3 months)?',
        'blood_transfusion'     => 'Blood transfusion / Immune globulin (past year)?',
        'pregnant'              => 'Pregnant or chance of pregnancy?',
        'consent_patient_name'  => 'Consent — Patient/Guardian Name',
        'consent_date'          => 'Consent Date',
        'consent_relationship'  => 'Consent — Relationship to Patient',
        'immtrac2_agree'        => 'ImmTrac2 Agreement',
        'immtrac2_date'         => 'ImmTrac2 Date',
        'immtrac2_relationship' => 'ImmTrac2 — Relationship to Patient',
    ];
    ?>
    <table style="width:100%; font-size:13px; border-collapse:collapse; margin-bottom:10px;">
      <tr>
        <td colspan="2"
            style="background:#d9edf7; font-weight:600; padding:6px 8px;
                   border:1px solid #bce8f1; font-size:14px;">
          <?php echo xlt('MMR Immunization'); ?>
        </td>
      </tr>
      <?php
      foreach ($labels as $key => $label) {
          $value = $data[$key] ?? '';
          if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
              continue;
          }
          // Strip time from date-only fields
          if (strpos($value, ' ') !== false && in_array($key, ['vaccination_date','consent_date','immtrac2_date'])) {
              $value = explode(' ', $value)[0];
          }
          echo "<tr style='border-bottom:1px solid #eee;'>";
          echo "<td style='padding:5px 8px; color:#555; width:40%; font-weight:500;'>" . xlt($label) . ":</td>";
          echo "<td style='padding:5px 8px;'>" . text($value) . "</td>";
          echo "</tr>";
      }
      ?>
    </table>
    <?php
}

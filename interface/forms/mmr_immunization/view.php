<?php
/**
 * MMR Immunization Form - view.php
 * Pre-populated edit form — loaded when reopening a saved record.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../../globals.php");
require_once("$srcdir/api.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

$table_name  = "form_mmr_immunization";
$form_name   = "MMR Immunization";
$form_folder = "mmr_immunization";

formHeader("Form: " . $form_name);
$returnurl = 'encounter_top.php';

// Fetch patient demographics
$ptrow       = sqlQuery("SELECT * FROM patient_data WHERE pid = ?", [$pid]);
$fname       = $ptrow['fname']       ?? '';
$mname       = $ptrow['mname']       ?? '';
$lname       = $ptrow['lname']       ?? '';
$dob         = $ptrow['DOB']         ?? '';
$sex         = $ptrow['sex']         ?? '';
$ethnicity   = $ptrow['ethnicity']   ?? '';
$race        = $ptrow['race']        ?? '';
$phone_cell  = $ptrow['phone_cell']  ?? '';
$email       = $ptrow['email']       ?? '';
$street      = $ptrow['street']      ?? '';
$city        = $ptrow['city']        ?? '';
$state       = $ptrow['state']       ?? '';
$postal_code = $ptrow['postal_code'] ?? '';
$county      = $ptrow['county']      ?? '';

// Load the saved record
$record = formFetch($table_name, $_GET["id"]);

// Strip time portion from date fields
foreach (['vaccination_date', 'consent_date', 'immtrac2_date'] as $df) {
    if (!empty($record[$df])) {
        $record[$df] = explode(' ', $record[$df])[0];
    }
}

function radioChecked($field, $val, $record) {
    return (isset($record[$field]) && $record[$field] === $val) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader('datetime-picker'); ?>
    <?php
    // Inline equivalent of signer_head() — loads the shared portal signer system
    $web_root_js = $GLOBALS['web_root'];
    $v_js        = $GLOBALS['v_js_includes'];
    ?>
    <link href="<?php echo attr($web_root_js); ?>/portal/sign/css/signer_modal.css?v=<?php echo attr($v_js); ?>" rel="stylesheet"/>
    <script src="<?php echo attr($web_root_js); ?>/portal/sign/assets/signature_pad.umd.js?v=<?php echo attr($v_js); ?>"></script>
    <script src="<?php echo attr($web_root_js); ?>/portal/sign/assets/signer_api.js?v=<?php echo attr($v_js); ?>"></script>
    <script>
        /* Required globals for signer_api.js */
        var cpid     = <?php echo js_escape($pid); ?>;
        var cuser    = <?php echo js_escape($_SESSION['authUserID']); ?>;
        var ptName   = <?php echo js_escape(trim(($fname ?? '') . ' ' . ($lname ?? ''))); ?>;
        var webRoot  = <?php echo js_escape($GLOBALS['web_root']); ?>;
        var isPortal = 0;
    </script>
    <script>
    function PrintForm() {
        window.open(
            "<?php echo "http://" . $_SERVER['SERVER_NAME'] . $rootdir . "/forms/" . $form_folder . "/print.php?id="; ?>"
            + <?php echo js_url($_GET["id"]); ?>, "printwin"
        );
    }
    </script>
</head>
<body class="body_top">

<form method="post"
      action="<?php echo $rootdir; ?>/forms/<?php echo $form_folder; ?>/save.php?mode=update&id=<?php echo attr_url($_GET["id"]); ?>"
      name="mmr_form"
      id="mmr_form">

    <input type="hidden" name="csrf_token_form"
           value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />

    <!-- ================================================================
         HEADER
    ================================================================ -->
    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
        <h4 class="mb-0"><?php echo xlt($form_name); ?></h4>
        <div>
            <button type="button" class="btn btn-primary btn-save mr-1"><?php echo xlt('Save Changes'); ?></button>
            <button type="button" class="btn btn-secondary btn-dontsave mr-1"><?php echo xlt("Cancel Changes"); ?></button>
        </div>
    </div>

    <!-- ================================================================
         SECTION 1: DEMOGRAPHICS (read-only)
    ================================================================ -->
    <fieldset class="card mb-3">
        <legend class="card-header d-flex align-items-center font-weight-bold py-2 px-3">
            <input type="checkbox" checked disabled class="mr-2">
            <?php echo xlt('Demographics'); ?>
        </legend>
        <div class="card-body pb-2">

            <!-- Row 1: Name | DOB | Sex -->
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('Name'); ?>:</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?php echo attr($fname); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('Middle'); ?>:</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?php echo attr($mname); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="mmr-label">&nbsp;</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?php echo attr($lname); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('DOB'); ?>:</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?php echo attr($dob); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('Sex'); ?>:</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?php echo attr($sex); ?>" readonly>
                    </div>
                </div>
            </div>

            <!-- Row 2: Ethnicity | Race | Mobile Phone | Contact Email -->
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('Ethnicity'); ?>:</label>
                        <select name="demo_ethnicity" class="form-control form-control-sm" disabled>
                            <?php foreach (['Hispanic or Latino','Not Hispanic or Latino'] as $eth) : ?>
                            <option <?php echo ($ethnicity === $eth) ? 'selected' : ''; ?>><?php echo text($eth); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('Race'); ?>:</label>
                        <select name="demo_race" class="form-control form-control-sm" disabled>
                            <?php foreach (['White','Black','Asian','Other'] as $rc) : ?>
                            <option <?php echo ($race === $rc) ? 'selected' : ''; ?>><?php echo text($rc); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('Mobile Phone'); ?>:</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?php echo attr($phone_cell); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('Contact Email'); ?>:</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?php echo attr($email); ?>" readonly>
                    </div>
                </div>
            </div>

            <!-- Row 3: Address | Address Line 2 -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('Address'); ?>:</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?php echo attr($street); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('Address Line 2'); ?>:</label>
                        <input type="text" class="form-control form-control-sm" readonly>
                    </div>
                </div>
            </div>

            <!-- Row 4: City | State | Postal Code | County -->
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('City'); ?>:</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?php echo attr($city); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('State'); ?>:</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?php echo attr($state); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('Postal Code'); ?>:</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?php echo attr($postal_code); ?>" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="mmr-label"><?php echo xlt('County'); ?>:</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?php echo attr($county); ?>" readonly>
                    </div>
                </div>
            </div>

        </div><!-- /card-body -->
    </fieldset>

    <!-- ================================================================
         SECTION 2: MMR IMMUNIZATION
    ================================================================ -->
    <fieldset class="card mb-3">
        <legend class="card-header d-flex align-items-center font-weight-bold py-2 px-3">
            <input type="checkbox" checked disabled class="mr-2">
            <?php echo xlt('MMR Immunization'); ?>
        </legend>
        <div class="card-body">

            <!-- Vaccination Site / Date / Administrator -->
            <div class="row align-items-end mb-3 pb-3 border-bottom">
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label class="mmr-label"><?php echo xlt('Vaccination Site'); ?>:</label>
                        <div class="d-flex">
                            <select name="vaccination_site" class="form-control form-control-sm mr-2">
                                <option value=""><?php echo xlt('Unassigned'); ?></option>
                                <?php foreach (['Left Arm','Right Arm','Left Thigh','Right Thigh'] as $site) : ?>
                                <option value="<?php echo attr($site); ?>"
                                    <?php echo ($record['vaccination_site'] === $site) ? 'selected' : ''; ?>>
                                    <?php echo text($site); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-info btn-sm"><?php echo xlt('Add'); ?></button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label class="mmr-label"><?php echo xlt('Vaccination Date'); ?>:</label>
                        <input type="text" class="form-control form-control-sm datepicker"
                               name="vaccination_date"
                               value="<?php echo attr($record['vaccination_date'] ?? ''); ?>"
                               title="<?php echo xla('yyyy-mm-dd'); ?>">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group mb-0">
                        <label class="mmr-label"><?php echo xlt('Vaccine Administrator'); ?>:</label>
                        <select name="vaccine_administrator" class="form-control form-control-sm">
                            <option value=""><?php echo xlt('Unassigned'); ?></option>
                            <?php
                            $providers = sqlStatement(
                                "SELECT id, fname, lname FROM users WHERE active=1 AND abook_type='physician' ORDER BY lname"
                            );
                            while ($prov = sqlFetchArray($providers)) {
                                $sel = ($record['vaccine_administrator'] == $prov['id']) ? 'selected' : '';
                                echo "<option value='" . attr($prov['id']) . "' $sel>" .
                                     text($prov['lname']) . ", " . text($prov['fname']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Medical and Social History -->
            <p class="font-weight-bold mb-2"><?php echo xlt('Medical and Social History'); ?>:</p>
            <?php
            $questions = [
                ['sick_today',
                 'Is the patient (Child or Adult) sick today? :'],
                ['severe_allergy',
                 'Has the Child/Adult had a severe allergy (life threatening) after receiving a vaccine?'],
                ['vaccines_last_4wks',
                 'Has the Child/Adult received vaccines/shots within the last 4 weeks?'],
                ['steroids_immuno',
                 'Has the Child/Adult taken steroids (cortisone, prednisone, or other steroids), anticancer medication, antiviral medication or other medications to weaken the immune system for over 2 weeks within the past 3 months?'],
                ['blood_transfusion',
                 'Has the Child/Adult received a transfusion of blood or blood product or been given immune (gamma) globulin in the past year?'],
                ['pregnant',
                 'Is the Child/Adult pregnant or is there a chance she could become pregnant during the next month?'],
            ];
            foreach ($questions as $q) :
                [$field, $label] = $q;
            ?>
            <div class="d-flex justify-content-between align-items-start border-bottom py-2">
                <div class="flex-grow-1 pr-3" style="font-size:13px;"><?php echo xlt($label); ?></div>
                <div class="d-flex flex-column" style="min-width:70px;">
                    <div class="form-check">
                        <input class="form-check-input" type="radio"
                               name="<?php echo attr($field); ?>" value="NO"
                               id="<?php echo attr($field); ?>_no"
                               <?php echo radioChecked($field, 'NO', $record); ?>>
                        <label class="form-check-label" for="<?php echo attr($field); ?>_no">
                            <?php echo xlt('NO'); ?>
                        </label>
                    </div>
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="radio"
                               name="<?php echo attr($field); ?>" value="YES"
                               id="<?php echo attr($field); ?>_yes"
                               <?php echo radioChecked($field, 'YES', $record); ?>>
                        <label class="form-check-label" for="<?php echo attr($field); ?>_yes">
                            <?php echo xlt('YES'); ?>
                        </label>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Vaccine Consent -->
            <div class="mt-4 pt-3 border-top">
                <p class="font-weight-bold mb-2"><?php echo xlt('Vaccine Consent'); ?>:</p>
                <div class="p-3 mb-3 border rounded bg-light" style="font-size:12px;">
                    <?php echo xlt('I have read, or have has explained to me, the Vaccine Information Statement about MMR vaccination. I have had a chance to ask questions, which were answered to my satisfaction, and I understand the benefit and risks of the vaccination as described. I request that the MMR vaccination be given to me (or the person named above for whom I am authorized to make this request).'); ?>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="mmr-label"><?php echo xlt('Patient/Parent or Legal Guardian Name'); ?>:</label>
                            <input type="text" name="consent_patient_name" class="form-control form-control-sm"
                                   value="<?php echo attr($record['consent_patient_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="mmr-label"><?php echo xlt('Date'); ?>:</label>
                            <input type="text" class="form-control form-control-sm datepicker"
                                   name="consent_date"
                                   value="<?php echo attr($record['consent_date'] ?? ''); ?>"
                                   title="<?php echo xla('yyyy-mm-dd'); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="mmr-label"><?php echo xlt('Signature'); ?>:</label>
                            <div class="sig-wrapper border rounded p-1 bg-light" style="min-height:80px; cursor:pointer;">
                                <img id="consent_sig_img"
                                     src=""
                                     alt="<?php echo xla('Click to update signature'); ?>"
                                     title="<?php echo xla('Click to update signature'); ?>"
                                     class="signature img-fluid"
                                     style="max-height:80px; width:auto;"
                                     data-action="fetch_signature"
                                     data-type="patient-signature"
                                     data-pid="<?php echo attr($pid); ?>"
                                     data-user="<?php echo attr($_SESSION['authUserID']); ?>">
                            </div>
                            <small class="text-muted"><?php echo xlt('Click above to update signature'); ?></small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="mmr-label"><?php echo xlt('Relationship to Patient'); ?>:</label>
                            <input type="text" name="consent_relationship" class="form-control form-control-sm"
                                   value="<?php echo attr($record['consent_relationship'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Texas ImmTrac2 Consent -->
            <div class="mt-4 pt-3 border-top">
                <p class="font-weight-bold mb-2"><?php echo xlt('Texas Immunization Registry (Immtrac2) Consent'); ?>:</p>
                <div class="p-3 mb-3 border rounded bg-light" style="font-size:12px;">
                    <?php echo xlt('* I understand that, by granting the consent below, I am authorizing the release of my immunization information to DSHS and I further understand DSHS will include this information in the Texas Immunization Registry. Once in the Texas Immunization Registry, my immunization information may by law be accessed by: a Texas physician, or other health-care provider legally authorized to administer vaccines, for treatment of the individual as a patient; a Texas school in which the individual is enrolled; a Texas public health district or local health department, for public health purposes within their areas of jurisdiction; a state agency having legal custody of the individual; a payor, currently authorized by the Texas Department of Insurance to operate in Texas for immunization records relating to the specific individual covered under the payor\'s policy. I understand that I may withdraw this consent at any time by submitting a completed Withdrawal of Consent Form in writing to the Texas Department of State Health Services, Texas Immunization Registry.'); ?>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="mmr-label"><?php echo xlt('I AGREE to participate in ImmTrac2'); ?>:</label>
                            <select name="immtrac2_agree" class="form-control form-control-sm">
                                <option value=""><?php echo xlt('Unassigned'); ?></option>
                                <?php foreach (['YES','NO'] as $opt) : ?>
                                <option value="<?php echo attr($opt); ?>"
                                    <?php echo ($record['immtrac2_agree'] === $opt) ? 'selected' : ''; ?>>
                                    <?php echo text($opt); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="mmr-label"><?php echo xlt('Date'); ?>:</label>
                            <input type="text" class="form-control form-control-sm datepicker"
                                   name="immtrac2_date"
                                   value="<?php echo attr($record['immtrac2_date'] ?? ''); ?>"
                                   title="<?php echo xla('yyyy-mm-dd'); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="mmr-label"><?php echo xlt('Signature'); ?>:</label>
                            <div id="immtrac2_sig_wrapper"
                                 class="sig-wrapper border rounded p-1 bg-light position-relative"
                                 style="min-height:80px; cursor:not-allowed; opacity:0.5;"
                                 title="<?php echo xla('Please select YES or NO for ImmTrac2 first'); ?>">
                                <img id="immtrac2_sig_img"
                                     src=""
                                     alt="<?php echo xla('Click to update signature'); ?>"
                                     title="<?php echo xla('Click to update signature'); ?>"
                                     class="signature img-fluid"
                                     style="max-height:80px; width:auto; pointer-events:none;"
                                     data-action="fetch_signature"
                                     data-type="patient-signature"
                                     data-pid="<?php echo attr($pid); ?>"
                                     data-user="<?php echo attr($_SESSION['authUserID']); ?>">
                                <!-- Lock overlay shown when Unassigned -->
                                <div id="immtrac2_sig_lock"
                                     style="position:absolute;top:0;left:0;right:0;bottom:0;
                                            background:rgba(200,200,200,0.5);
                                            display:flex;align-items:center;justify-content:center;
                                            font-size:12px;color:#c0392b;font-weight:bold;">
                                    ⚠ <?php echo xlt('Select YES or NO above to enable signature'); ?>
                                </div>
                            </div>
                            <small class="text-muted" id="immtrac2_sig_hint">
                                <?php echo xlt('Select YES or NO to enable signing'); ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="mmr-label"><?php echo xlt('Relationship to Patient'); ?>:</label>
                            <input type="text" name="immtrac2_relationship" class="form-control form-control-sm"
                                   value="<?php echo attr($record['immtrac2_relationship'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /card-body -->
    </fieldset>

    <!-- Bottom buttons -->
    <div class="mb-4">
        <button type="button" class="btn btn-primary btn-save mr-1"><?php echo xlt('Save Changes'); ?></button>
        <button type="button" class="btn btn-secondary btn-dontsave mr-1"><?php echo xlt("Cancel Changes"); ?></button>
    </div>

</form>

<style>
.mmr-label {
    font-weight: 700;
    font-size: 11px;
    color: #c0392b;
    text-transform: uppercase;
    letter-spacing: .03em;
    margin-bottom: 2px;
    display: block;
}
</style>

<script>
$(function () {
    $(".btn-dontsave").click(function () {
        parent.closeTab(window.name, false);
    });

    $('.datepicker').datetimepicker({
        <?php $datetimepicker_timepicker = false; ?>
        <?php $datetimepicker_showseconds = false; ?>
        <?php $datetimepicker_formatInput = false; ?>
        <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
    });

    // Re-bind signer click events and auto-load stored signature on view
    $(window).on('load', function () {
        if (typeof bindFetch === 'function') {
            bindFetch();
        }
        // Auto-load existing signature from onsite_signatures into img fields
        var $sigs = $("img[data-action=fetch_signature]");
        if ($sigs.length && typeof getSignature === 'function') {
            getSignature($sigs[0], true).then(function (result) {
                if (result && typeof result !== 'string') return;
                $sigs.each(function () {
                    if ($(this).attr('src') !== result && result) {
                        $(this).attr('src', result);
                    }
                });
            });
        }
        // Apply correct ImmTrac2 state based on saved value
        updateImmtrac2SigState();
    });

    /* ---------------------------------------------------------------
     * ImmTrac2 Signature Guard
     * Block signing when dropdown = Unassigned (value = "")
     * --------------------------------------------------------------- */
    function updateImmtrac2SigState() {
        var val = $('select[name="immtrac2_agree"]').val();
        var $wrapper = $('#immtrac2_sig_wrapper');
        var $lock    = $('#immtrac2_sig_lock');
        var $img     = $('#immtrac2_sig_img');
        var $hint    = $('#immtrac2_sig_hint');

        if (val === '' || val === null) {
            // UNASSIGNED — lock the signature area
            $wrapper.css({ 'cursor': 'not-allowed', 'opacity': '0.5' });
            $img.css('pointer-events', 'none');
            $lock.show();
            $hint.text('<?php echo xlt('Select YES or NO to enable signing'); ?>');
        } else {
            // YES or NO — unlock the signature area
            $wrapper.css({ 'cursor': 'pointer', 'opacity': '1' });
            $img.css('pointer-events', 'auto');
            $lock.hide();
            if (val === 'YES') {
                $hint.text('<?php echo xlt('Click above to sign consent'); ?>');
            } else {
                $hint.text('<?php echo xlt('Click above to sign refusal'); ?>');
            }
        }
    }

    // Run every time the dropdown changes
    $('select[name="immtrac2_agree"]').on('change', function () {
        updateImmtrac2SigState();
    });

    // Click guard on the wrapper itself (extra safety layer)
    $('#immtrac2_sig_wrapper').on('click', function (e) {
        var val = $('select[name="immtrac2_agree"]').val();
        if (val === '' || val === null) {
            e.stopImmediatePropagation();
            alert('<?php echo xlt('Please select YES or NO for ImmTrac2 participation before signing.'); ?>');
            return false;
        }
    });

    /* ---------------------------------------------------------------
     * Form Save Validation
     * Warn if ImmTrac2 is still Unassigned before submitting
     * --------------------------------------------------------------- */
    $(".btn-save").click(function () {
        var immtrac2 = $('select[name="immtrac2_agree"]').val();
        if (immtrac2 === '' || immtrac2 === null) {
            var proceed = confirm(
                '<?php echo xlt('WARNING: ImmTrac2 consent is not answered (Unassigned).'); ?>\n' +
                '<?php echo xlt('Please select YES or NO before saving.'); ?>\n\n' +
                '<?php echo xlt('Click OK to save anyway, or Cancel to go back and answer.'); ?>'
            );
            if (!proceed) return false;
        }
        top.restoreSession();
        document.mmr_form.submit();
    });
}); // end $(function)
</script>
</body>
</html>

<?php

 // Copyright (C) 2006-2021 Rod Roark <rod@sunsetsystems.com>
 //
 // This program is free software; you can redistribute it and/or
 // modify it under the terms of the GNU General Public License
 // as published by the Free Software Foundation; either version 2
 // of the License, or (at your option) any later version

require_once("../globals.php");
require_once("drugs.inc.php");
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;

$alertmsg = '';
$drug_id = $_REQUEST['drug'];
$info_msg = "";
$tmpl_line_no = 0;

// Check authorization - allow admin with drugs permission
$auth_drugs = AclMain::aclCheckCore('admin', 'drugs');
if (!$auth_drugs) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Edit/Add Drug")]);
    exit;
}

// Write a line of data for one template to the form.
//
function writeTemplateLine($selector, $dosage, $period, $quantity, $refills, $prices, $taxrates, $pkgqty, $is_pyxis_container = 0, $bd_dispense_form = '', $bd_dispense_quantity = '')
{
    global $tmpl_line_no;
    ++$tmpl_line_no;

    echo " <tr>\n";
    echo "  <td class='tmplcell drugsonly'>";
    echo "<input class='form-control' name='form_tmpl[" . attr($tmpl_line_no) . "][selector]' value='" . attr($selector) . "' size='40' maxlength='120'>";
    echo "</td>\n";
    echo "  <td class='tmplcell drugsonly'>";
    echo "<input class='form-control' name='form_tmpl[" . attr($tmpl_line_no) . "][dosage]' value='" . attr($dosage) . "' size='10' maxlength='40'>";
    echo "</td>\n";
    echo "  <td class='tmplcell drugsonly'>";
    generate_form_field(array(
    'data_type'   => 1,
    'field_id'    => 'tmpl[' . attr($tmpl_line_no) . '][period]',
    'list_id'     => 'drug_interval',
    'empty_title' => 'SKIP'
    ), $period);
    echo "</td>\n";
    echo "  <td class='tmplcell drugsonly'>";
    echo "<input class='form-control' name='form_tmpl[" . attr($tmpl_line_no) . "][quantity]' value='" . attr($quantity) . "' size='10' maxlength='40'>";
    echo "</td>\n";
    echo "  <td class='tmplcell drugsonly'>";
    echo "<input class='form-control' name='form_tmpl[" . attr($tmpl_line_no) . "][refills]' value='" . attr($refills) . "' size='10' maxlength='40'>";
    echo "</td>\n";
    
    // Hidden field to track if this template row uses BD Pyxis container
    echo "  <input type='hidden' name='form_tmpl[" . attr($tmpl_line_no) . "][is_pyxis_container]' value='" . attr($is_pyxis_container) . "'>\n";
    
    // BD Pyxis container fields - shown when is_pyxis_container is checked OR when creating packets
    echo "  <td class='tmplcell pyxis-field bd-dispense-field' style='display:" . ($is_pyxis_container ? "table-cell" : "none") . ";'>";
    echo "    <select class='form-control' name='form_tmpl[" . attr($tmpl_line_no) . "][bd_dispense_form]'>";
    echo "      <option value=''>-- " . xlt('Select Form') . " --</option>";
    $fres = sqlStatement("SELECT option_id, title FROM list_options WHERE list_id = 'drug_form' AND activity = 1 ORDER BY seq, title");
    while ($frow = sqlFetchArray($fres)) {
        $selected = ($frow['option_id'] == $bd_dispense_form) ? " selected" : "";
        echo "      <option value='" . attr($frow['option_id']) . "'$selected>" . text($frow['title']) . "</option>";
    }
    echo "    </select>";
    echo "</td>\n";
    echo "  <td class='tmplcell pyxis-field bd-dispense-field' style='display:" . ($is_pyxis_container ? "table-cell" : "none") . ";'>";
    echo "<input class='form-control' name='form_tmpl[" . attr($tmpl_line_no) . "][bd_dispense_quantity]' value='" . attr($bd_dispense_quantity) . "' size='10' maxlength='10' placeholder='Qty'>";
    echo "</td>\n";

    /******************************************************************
    echo "  <td class='tmplcell drugsonly'>";
    echo "<input type='text' class='form-control' name='form_tmpl[" . attr($tmpl_line_no) .
        "][pkgqty]' value='" . attr($pkgqty) . "' size='3' maxlength='5'>";
    echo "</td>\n";
    ******************************************************************/

    foreach ($prices as $pricelevel => $price) {
        echo "  <td class='tmplcell'>";
        echo "<input class='form-control' name='form_tmpl[" . attr($tmpl_line_no) . "][price][" . attr($pricelevel) . "]' value='" . attr($price) . "' size='6' maxlength='12'>";
        echo "</td>\n";
    }

    $pres = sqlStatement("SELECT option_id FROM list_options " .
    "WHERE list_id = 'taxrate' AND activity = 1 ORDER BY seq");
    while ($prow = sqlFetchArray($pres)) {
        echo "  <td class='tmplcell'>";
        echo "<input type='checkbox' name='form_tmpl[" . attr($tmpl_line_no) . "][taxrate][" . attr($prow['option_id']) . "]' value='1'";
        if (strpos(":$taxrates", $prow['option_id']) !== false) {
            echo " checked";
        }

        echo " /></td>\n";
    }

    echo " </tr>\n";
}

?>
<html>
<head>
<title><?php echo $drug_id ? xlt("Edit") : xlt("Add New");
echo ' ' . xlt('Drug'); ?></title>

<?php Header::setupHeader(["opener"]); ?>

<style>

<?php if ($GLOBALS['sell_non_drug_products'] == 2) { // "Products but no prescription drugs and no templates" ?>
.drugsonly { display:none; }
<?php } else { ?>
.drugsonly { }
<?php } ?>

<?php if (empty($GLOBALS['ippf_specific'])) { ?>
.ippfonly { display:none; }
<?php } else { ?>
.ippfonly { }
<?php } ?>

/* Increase font size for better readability */
.form-control {
    font-size: 16px;
}

</style>

<script>

<?php require($GLOBALS['srcdir'] . "/restoreSession.php"); ?>

// This is for callback by the find-code popup.
// Appends to or erases the current list of related codes.
// The target element is set by the find-code popup
// (this allows use of this in multiple form elements on the same page)
function set_related_target(codetype, code, selector, codedesc, target_element, limit=0) {
    var f = document.forms[0];
    var s = f[target_element].value;
    if (code) {
        if (limit > 0) {
            s = codetype + ':' + code;
        }
        else {
            if (codetype != 'PROD') {
                // Return an error message if a service code is already selected.
                if (s.indexOf(codetype + ':') == 0 || s.indexOf(';' + codetype + ':') > 0) {
                    return <?php echo xlj('A code of this type is already selected. Erase the field first if you need to replace it.') ?>;
                }
            }
            if (s.length > 0) {
                s += ';';
            }
            s += codetype + ':' + code;
        }
    } else {
        s = '';
    }
    f[target_element].value = s;
    return '';
}

// This is for callback by the find-code popup.
// Returns the array of currently selected codes with each element in codetype:code format.
function get_related() {
 return document.forms[0].form_related_code.value.split(';');
}

// This is for callback by the find-code popup.
// Deletes the specified codetype:code from the currently selected list.
function del_related(s) {
 my_del_related(s, document.forms[0].form_related_code, false);
}

// This invokes the find-code popup.
function sel_related(getter = '') {
 dlgopen('../patient_file/encounter/find_code_dynamic.php' + getter, '_blank', 900, 800);
}

// onclick handler for "allow inventory" checkbox.
function dispensable_changed() {
 var f = document.forms[0];
 var dis = !f.form_dispensable.checked;
 f.form_allow_multiple.disabled = dis;
 f.form_allow_combining.disabled = dis;
 return true;
}

// Toggle BD Pyxis container fields visibility
function togglePyxisFields() {
    var is_pyxis = document.getElementById('is_pyxis_container').checked;
    var is_packet = document.getElementById('create_packet') ? document.getElementById('create_packet').checked : false;
    
    // Toggle all pyxis headers except BD Dispense ones (which have their own logic)
    var pyxis_headers = document.querySelectorAll('.pyxis-header:not(.bd-dispense-header)');
    var pyxis_fields = document.querySelectorAll('.pyxis-field:not(.bd-dispense-field)');
    var bd_dispense_headers = document.querySelectorAll('.bd-dispense-header');
    var bd_dispense_fields = document.querySelectorAll('.bd-dispense-field');
    
    // Toggle visibility for regular pyxis fields
    pyxis_headers.forEach(function(elem) {
        elem.style.display = is_pyxis ? 'table-cell' : 'none';
    });
    
    pyxis_fields.forEach(function(elem) {
        elem.style.display = is_pyxis ? 'table-cell' : 'none';
    });
    
    // BD Dispense fields show when creating packet OR when pyxis is checked
    var show_bd_dispense = is_packet || is_pyxis;
    bd_dispense_headers.forEach(function(elem) {
        elem.style.display = show_bd_dispense ? 'table-cell' : 'none';
    });
    
    bd_dispense_fields.forEach(function(elem) {
        elem.style.display = show_bd_dispense ? 'table-cell' : 'none';
    });
    
    // Update hidden is_pyxis_container fields for all template rows
    var pyxis_hidden = document.querySelectorAll('input[name*="is_pyxis_container"]');
    pyxis_hidden.forEach(function(elem) {
        // Only update form_tmpl hidden fields, not the main checkbox
        if (elem.name.indexOf('form_tmpl') > -1) {
            elem.value = is_pyxis ? '1' : '0';
        }
    });
}

// Toggle Create Packet fields visibility
function togglePacketFields() {
    var create_packet = document.getElementById('create_packet').checked;
    var drug_name_group = document.getElementById('drug_name_group');
    var packet_name_group = document.getElementById('packet_name_group');
    var bd_pyxis_section = document.getElementById('bd_pyxis_section');
    var drugs_packet_section = document.getElementById('drugs_packet_section');
    var form_to_route_section = document.getElementById('form_to_route_section');
    var packet_route_section_form = document.getElementById('packet_route_section_form');
    
    if (create_packet) {
        // Hide drug name field and show packet name field
        drug_name_group.style.display = 'none';
        packet_name_group.style.display = 'block';
        // Hide BD Pyxis section when creating a packet
        if (bd_pyxis_section) {
            bd_pyxis_section.style.display = 'none';
        }
        // Show drugs packet table when creating a packet
        if (drugs_packet_section) {
            drugs_packet_section.style.display = 'block';
        }
        // Hide main form_to_route_section when creating a packet
        if (form_to_route_section) {
            form_to_route_section.style.display = 'none';
        }
        // Show packet route section for packet creation
        if (packet_route_section_form) {
            packet_route_section_form.style.display = 'block';
        }
        // Uncheck and hide pyxis fields when creating a packet (but keep BD Dispense fields visible)
        document.getElementById('is_pyxis_container').checked = false;
        var pyxis_headers = document.querySelectorAll('.pyxis-header:not(.bd-dispense-header)');
        var bd_dispense_headers = document.querySelectorAll('.bd-dispense-header');
        var pyxis_fields = document.querySelectorAll('.pyxis-field:not(.bd-dispense-field)');
        var bd_dispense_fields = document.querySelectorAll('.bd-dispense-field');
        pyxis_headers.forEach(function(elem) {
            elem.style.display = 'none';
        });
        bd_dispense_headers.forEach(function(elem) {
            elem.style.display = 'table-cell';
        });
        pyxis_fields.forEach(function(elem) {
            elem.style.display = 'none';
        });
        // Show BD Dispense fields for packet creation
        bd_dispense_fields.forEach(function(elem) {
            elem.style.display = 'table-cell';
        });
    } else {
        // Show drug name field and hide packet name field
        drug_name_group.style.display = 'block';
        packet_name_group.style.display = 'none';
        // Show BD Pyxis section when not creating a packet
        if (bd_pyxis_section) {
            bd_pyxis_section.style.display = 'block';
        }
        // Hide drugs packet table when not creating a packet
        if (drugs_packet_section) {
            drugs_packet_section.style.display = 'none';
        }
        // Show form to route section when not creating a packet
        if (form_to_route_section) {
            form_to_route_section.style.display = 'block';
        }
        // Hide packet route section when not creating a packet
        if (packet_route_section_form) {
            packet_route_section_form.style.display = 'none';
        }
    }
}

// Add new drug row to packet

var drugRowCounter = 3;
function addDrugRow() {
    drugRowCounter++;
    var drugsTableBody = document.getElementById('drugs_table_body');
    
    var newRow = document.createElement('tr');
    
    var formOptions = '';
    var strengthUnitOptions = '';
    var routeOptions = '';
    var frequencyOptions = '';
    
    // Get the options from existing selects
    var formSelect = document.querySelector('select[name="form_drugs[1][form]"]');
    var unitSelect = document.querySelector('select[name="form_drugs[1][strength_unit]"]');
    var routeSelect = document.querySelector('select[name="form_drugs[1][route]"]');
    var frequencySelect = document.querySelector('select[name="form_drugs[1][frequency]"]');
    
    if (formSelect) formOptions = formSelect.innerHTML;
    if (unitSelect) strengthUnitOptions = unitSelect.innerHTML;
    if (routeSelect) routeOptions = routeSelect.innerHTML;
    if (frequencySelect) frequencyOptions = frequencySelect.innerHTML;
    
    var html = '';
    html += '  <td><input class="form-control form-control-sm" type="text" name="form_drugs[' + drugRowCounter + '][name]" maxlength="120" /></td>';
    html += '  <td><select class="form-control form-control-sm" name="form_drugs[' + drugRowCounter + '][form]"><option value=""></option>' + formOptions + '</select></td>';
    html += '  <td><input class="form-control form-control-sm" type="text" name="form_drugs[' + drugRowCounter + '][strength]" maxlength="20" /></td>';
    html += '  <td><select class="form-control form-control-sm" name="form_drugs[' + drugRowCounter + '][strength_unit]"><option value=""></option>' + strengthUnitOptions + '</select></td>';
    html += '  <td><select class="form-control form-control-sm" name="form_drugs[' + drugRowCounter + '][route]"><option value=""></option>' + routeOptions + '</select></td>';
    html += '  <td><input class="form-control form-control-sm" type="text" name="form_drugs[' + drugRowCounter + '][dose]" maxlength="40" /></td>';
    html += '  <td><select class="form-control form-control-sm" name="form_drugs[' + drugRowCounter + '][frequency]"><option value=""></option>' + frequencyOptions + '</select></td>';
    html += '  <td><input class="form-control form-control-sm" type="text" name="form_drugs[' + drugRowCounter + '][quantity]" maxlength="40" /></td>';
    
    newRow.innerHTML = html;
    drugsTableBody.appendChild(newRow);
}

function validate(f) {
 var saving = f.form_save && f.form_save.clicked ? true : false;
 if (f.form_save) f.form_save.clicked = false;
 if (saving) {
  var nameField = f.create_packet.checked ? f.form_packet_name : f.form_name;
  if (nameField.value.search(/[^\s]/) < 0) {
   alert('Product name is required');
   return false;
  }
  if (!/^[A-Za-z0-9]+$/.test(f.form_medid.value.trim())) {
   alert(<?php echo xlj('MEDID must contain only alphanumeric characters'); ?>);
   return false;
  }
  if (f.form_medid.value.trim().length > 20) {
   alert(<?php echo xlj('MEDID cannot exceed 20 characters'); ?>);
   return false;
  }
  if (f.form_brand_name.value.trim().length > 100) {
   alert(<?php echo xlj('Brand Name cannot exceed 100 characters'); ?>);
   return false;
  }
  // Check if Brand Name is required (when different than Generic Name)
  var genericName = f.form_name.value.trim().toLowerCase();
  var brandName = f.form_brand_name.value.trim().toLowerCase();
  if (brandName !== '' && brandName === genericName) {
   alert(<?php echo xlj('Brand Name must be different than Generic Name or left empty'); ?>);
   return false;
  }
 }
 var deleting = f.form_delete && f.form_delete.clicked ? true : false;
 if (f.form_delete) f.form_delete.clicked = false;
 if (deleting) {
  if (!confirm('This will permanently delete all lots of this product. Related reports will be incomplete or incorrect. Are you sure?')) {
   return false;
  }
 }
 top.restoreSession();
 return true;
}

</script>

</head>

<body class="body_top">
<?php
// If we are saving, then save and close the window.
// First check for duplicates.
//
if (!empty($_POST['form_save'])) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }

    $drugName = !empty($_POST['create_packet']) ? trim($_POST['form_packet_name']) : trim($_POST['form_name']);
    if (trim($_POST['form_medid']) === '') {
        // Auto-generate MEDID based on user's facility
        $user_id = $_SESSION['authUserID'];
        $user_fac = sqlQuery("SELECT facility_id FROM users WHERE id = ?", array($user_id));
        $fac_id = $user_fac['facility_id'] ?? $GLOBALS['facility'];
        $fac_name = sqlQuery("SELECT name FROM facility WHERE id = ?", array($fac_id))['name'];
        if ($fac_name == "El Paso Department of Public Health Respiratory Disease Clinic") {
            $prefix = "TB";
        } elseif ($fac_name == "El Paso Department of Public Health Sexual Health (STD) Clinic") {
            $prefix = "STD";
        } else {
            $prefix = "TB"; // Default prefix
        }
        if ($prefix) {
            $prefix_len = strlen($prefix);
            $row = sqlQuery("SELECT medid FROM drugs WHERE medid LIKE ? ORDER BY medid DESC LIMIT 1", array($prefix . '%'));
            if ($row && !empty($row['medid'])) {
                $number_part = substr($row['medid'], $prefix_len);
                $number = intval($number_part) + 1;
            } else {
                $number = 1;
            }
            $_POST['form_medid'] = $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
        }
    }
    if ($drugName === '') {
    // Removed Dosage Form Code validation

    } elseif (strlen(trim($_POST['form_medid'])) > 20) {
        $alertmsg = xl('MEDID cannot exceed 20 characters');
    } elseif (strlen(trim($_POST['form_brand_name'])) > 100) {
        $alertmsg = xl('Brand Name cannot exceed 100 characters');
    } else {
        $crow = sqlQuery(
            "SELECT COUNT(*) AS count FROM drugs WHERE " .
            "name = ? AND " .
            "form = ? AND " .
            "size = ? AND " .
            "unit = ? AND " .
            "route = ? AND " .
            "drug_id != ?",
            array(
                $drugName,
                trim($_POST['form_form']),
                trim($_POST['form_size']),
                trim($_POST['form_unit']),
                trim($_POST['form_route']),
                $drug_id
            )
        );
        if ($crow['count']) {
            $alertmsg = xl('Cannot add this entry because it already exists!');
        }
    }
}

if ((!empty($_POST['form_save']) || !empty($_POST['form_delete'])) && !$alertmsg) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }

    $new_drug = false;
    if ($drug_id && $_POST['form_save']) { // updating an existing drug
        // Update the drugs table with new fields
        sqlStatement(
            "UPDATE drugs SET name = ?, ndc_number = ?, drug_code = ?, on_order = ?, reorder_point = ?, max_level = ?, form = ?, size = ?, unit = ?, route = ?, cyp_factor = ?, related_code = ?, dispensable = ?, allow_multiple = ?, allow_combining = ?, active = ?, consumable = ?, medid = ?, brand_name = ?, med_class_code = ?, mfr = ?, generic_name = ?, concentration_volume = ?, concentration_volume_unit = ?, total_volume = ?, total_volume_unit = ?, uses_pyxis_containers = ?, is_packet = ? WHERE drug_id = ?",
            array(
                $drugName,
                trim($_POST['form_ndc_number'] ?? ''),
                trim($_POST['form_drug_code'] ?? ''),
                trim($_POST['form_on_order'] ?? '0'),
                trim($_POST['form_reorder_point'] ?? '0'),
                trim($_POST['form_max_level'] ?? '0'),
                trim($_POST['form_form'] ?? ''),
                trim($_POST['form_size'] ?? ''),
                trim($_POST['form_unit'] ?? ''),
                trim($_POST['form_route'] ?? ''),
                trim($_POST['form_cyp_factor'] ?? '0'),
                trim($_POST['form_related_code'] ?? ''),
                (empty($_POST['form_dispensable']) ? 0 : 1),
                (empty($_POST['form_allow_multiple']) ? 0 : 1),
                (empty($_POST['form_allow_combining']) ? 0 : 1),
                (empty($_POST['form_active']) ? 0 : 1),
                (empty($_POST['form_consumable']) ? 0 : 1),
                trim($_POST['form_medid'] ?? ''),
                trim($_POST['form_brand_name'] ?? ''),
                trim($_POST['form_med_class_code'] ?? ''),
                trim($_POST['form_mfr'] ?? ''),
                trim($_POST['form_generic_name'] ?? ''),
                trim($_POST['form_concentration_volume'] ?? ''),
                trim($_POST['form_concentration_volume_unit'] ?? ''),
                trim($_POST['form_total_volume'] ?? ''),
                trim($_POST['form_total_volume_unit'] ?? ''),
                (empty($_POST['is_pyxis_container']) ? 0 : 1),
                (empty($_POST['create_packet']) ? 0 : 1),
                $drug_id
            )
        );
        sqlStatement("DELETE FROM drug_templates WHERE drug_id = ?", array($drug_id));
    }
    if ($drug_id && $_POST['form_delete']) { // deleting
        if (AclMain::aclCheckCore('admin', 'super')) {
            // First check for related records that might prevent deletion
            // Only count active (non-destroyed) inventory lots
            $related_inventory = sqlQuery("SELECT COUNT(*) as count FROM drug_inventory WHERE drug_id = ? AND destroy_date IS NULL", array($drug_id));
            $related_prescriptions = sqlQuery("SELECT COUNT(*) as count FROM prescriptions WHERE drug_id = ?", array($drug_id));
            $related_sales = sqlQuery("SELECT COUNT(*) as count FROM drug_sales WHERE drug_id = ?", array($drug_id));

            if ($related_inventory['count'] > 0 || $related_prescriptions['count'] > 0 || $related_sales['count'] > 0) {
                $info_msg = "Cannot delete drug: it has " . $related_inventory['count'] . " active inventory lots, " . $related_prescriptions['count'] . " prescriptions, and " . $related_sales['count'] . " sales records.";
            } else {
                // Get drug data for HL7 message (optional)
                $drug_data = sqlQuery("SELECT * FROM drugs WHERE drug_id = ?", array($drug_id));

                // Delete related records first
                sqlStatement("DELETE FROM drug_templates WHERE drug_id = ?", array($drug_id));
                sqlStatement("DELETE FROM prices WHERE pr_id = ?", array($drug_id));
                sqlStatement("DELETE FROM drug_sales WHERE drug_id = ?", array($drug_id));
                // Also delete destroyed lots
                sqlStatement("DELETE FROM drug_inventory WHERE drug_id = ?", array($drug_id));

                // Then delete the drug
                $delete_result = sqlStatement("DELETE FROM drugs WHERE drug_id = ?", array($drug_id));
                if ($delete_result !== false) {
                    $info_msg = "Drug deleted successfully.";
                    $deletion_success = true;

                    // Send HL7 message if drug data was retrieved successfully
                    if ($drug_data && isset($drug_data['drug_id'])) {
                        // Clean binary data that can't be JSON encoded
                        if (isset($drug_data['uuid'])) {
                            $drug_data['uuid'] = bin2hex($drug_data['uuid']);
                        }

                        // Ensure required fields exist for HL7 message
                        $drug_data['medid'] = $drug_data['medid'] ?? '';
                        $drug_data['name'] = $drug_data['name'] ?? 'Unknown Drug';

                        // Generate and save HL7 delete message (non-blocking)
                        $hl7_result = generateDrugDeleteHL7Message($drug_data);
                        $hl7_message = $hl7_result['message'];
                        $msg_id = $hl7_result['msg_id'];

                        saveDrugDeleteHL7ToFolder($hl7_message, $drug_id, $msg_id);
                        createPendingDrugDeleteTransaction($drug_data, $msg_id, $drug_id);

                        // Generate ACK message automatically
                        $ack_message = generateACKMessage($msg_id, 'AA', 'Drug delete processed');
                        saveACKMessage($ack_message, $msg_id);
                    }
                } else {
                    $info_msg = "Error: Failed to delete drug from database.";
                }
            }
        } else {
            $info_msg = "Access denied for drug deletion.";
        }
    }

    if (!$drug_id && $_POST['form_save']) { // saving a new drug
        $new_drug = true;
        
        // Look up the option_id for 'packets' form if creating a packet
        $packet_form_id = 'packet'; // fallback value
        if (!empty($_POST['create_packet'])) {
            $packet_form_option = sqlQuery(
                "SELECT option_id FROM list_options WHERE list_id = 'drug_form' AND (title = 'packets' OR title = 'packet') AND activity = 1"
            );
            if ($packet_form_option && isset($packet_form_option['option_id'])) {
                $packet_form_id = $packet_form_option['option_id'];
            }
        }
        
        // Similarly, look up option_id for 'packet' unit if creating a packet
        $packet_unit_id = 'packet'; // fallback value
        if (!empty($_POST['create_packet'])) {
            $packet_unit_option = sqlQuery(
                "SELECT option_id FROM list_options WHERE list_id = 'drug_units' AND (title = 'packets' OR title = 'packet') AND activity = 1"
            );
            if ($packet_unit_option && isset($packet_unit_option['option_id'])) {
                $packet_unit_id = $packet_unit_option['option_id'];
            }
        }
        
        // Standard insert logic
        $drug_id = sqlInsert(
            "INSERT INTO drugs (name, ndc_number, drug_code, on_order, reorder_point, max_level, form, size, unit, route, cyp_factor, related_code, dispensable, allow_multiple, allow_combining, active, consumable, medid, brand_name, med_class_code, mfr, generic_name, concentration_volume, concentration_volume_unit, total_volume, total_volume_unit, uses_pyxis_containers, is_packet) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array(
                $drugName,
                trim($_POST['form_ndc_number'] ?? ''),
                trim($_POST['form_drug_code'] ?? ''),
                trim($_POST['form_on_order'] ?? '0'),
                trim($_POST['form_reorder_point'] ?? '0'),
                trim($_POST['form_max_level'] ?? '0'),
                (!empty($_POST['create_packet']) ? $packet_form_id : trim($_POST['form_form'] ?? '')),
                trim($_POST['form_size'] ?? ''),
                (!empty($_POST['create_packet']) ? $packet_unit_id : trim($_POST['form_unit'] ?? '')),
                trim($_POST['form_route'] ?? ''),
                trim($_POST['form_cyp_factor'] ?? '0'),
                trim($_POST['form_related_code'] ?? ''),
                (empty($_POST['form_dispensable']) ? 0 : 1),
                (empty($_POST['form_allow_multiple']) ? 0 : 1),
                (empty($_POST['form_allow_combining']) ? 0 : 1),
                (empty($_POST['form_active']) ? 0 : 1),
                (empty($_POST['form_consumable']) ? 0 : 1),
                trim($_POST['form_medid'] ?? ''),
                trim($_POST['form_brand_name'] ?? ''),
                trim($_POST['form_med_class_code'] ?? ''),
                trim($_POST['form_mfr'] ?? ''),
                trim($_POST['form_generic_name'] ?? ''),
                trim($_POST['form_concentration_volume'] ?? ''),
                trim($_POST['form_concentration_volume_unit'] ?? ''),
                trim($_POST['form_total_volume'] ?? ''),
                trim($_POST['form_total_volume_unit'] ?? ''),
                (empty($_POST['is_pyxis_container']) ? 0 : 1),
                (empty($_POST['create_packet']) ? 0 : 1)
            )
        );
        $info_msg = "Drug added successfully.";
    }

    // Save packet drugs if creating a packet
    if (!empty($_POST['create_packet']) && !empty($_POST['form_save']) && $drug_id) {
        error_log("DEBUG: Saving packet drugs. Drug ID: $drug_id");
        error_log("DEBUG: form_drugs data: " . json_encode($_POST['form_drugs'] ?? array()));
        
        // Delete existing packet drugs first
        sqlStatement("DELETE FROM drugs WHERE packet_id = ?", array($drug_id));
        
        // Insert each packet drug
        if (!empty($_POST['form_drugs'])) {
            foreach ($_POST['form_drugs'] as $row_num => $drug_data) {
                $drug_name = trim($drug_data['name'] ?? '');
                error_log("DEBUG: Row $row_num - Drug name: '$drug_name'");
                if (!empty($drug_name)) {
                    error_log("DEBUG: Inserting drug: $drug_name, dose: " . ($drug_data['dose'] ?? '') . ", frequency: " . ($drug_data['frequency'] ?? '') . ", quantity: " . ($drug_data['quantity'] ?? ''));
                    sqlInsert(
                        "INSERT INTO drugs (name, ndc_number, form, size, unit, route, dose, pack_frequency, quantity, active, dispensable, allow_multiple, allow_combining, packet_id, is_packet) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        array(
                            $drug_name,
                            '',
                            trim($drug_data['form'] ?? ''),
                            trim($drug_data['strength'] ?? ''),
                            trim($drug_data['strength_unit'] ?? ''),
                            trim($drug_data['route'] ?? ''),
                            trim($drug_data['dose'] ?? ''),
                            trim($drug_data['frequency'] ?? ''),
                            trim($drug_data['quantity'] ?? ''),
                            1,
                            1,
                            1,
                            0,
                            $drug_id,
                            0
                        )
                    );
                }
            }
        } else {
            error_log("DEBUG: form_drugs is empty!");
        }
    } else {
        error_log("DEBUG: Condition not met. create_packet: " . (!empty($_POST['create_packet']) ? '1' : '0') . ", form_save: " . (!empty($_POST['form_save']) ? '1' : '0') . ", drug_id: $drug_id");
    }

    if ($_POST['form_save'] && $drug_id) {
        $tmpl = $_POST['form_tmpl'];
       // If using the simplified drug form, then force the one and only
       // selector name to be the same as the product name.
        if ($GLOBALS['sell_non_drug_products'] == 2) {
            $tmpl["1"]['selector'] = $drugName;
        }
        for ($lino = 1; isset($tmpl["$lino"]['selector']); ++$lino) {
            $iter = $tmpl["$lino"];
            $selector = trim($iter['selector']);
            if ($selector) {
                $taxrates = "";
                if (!empty($iter['taxrate'])) {
                    foreach ($iter['taxrate'] as $key => $value) {
                        $taxrates .= "$key:";
                    }
                }

                sqlStatement(
                    "INSERT INTO drug_templates ( " .
                    "drug_id, selector, dosage, period, quantity, refills, taxrates, pkgqty, is_pyxis_container, bd_dispense_form, bd_dispense_quantity " .
                    ") VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )",
                    array(
                        $drug_id,
                        $selector,
                        trim($iter['dosage']),
                        trim($iter['period']),
                        trim($iter['quantity']),
                        trim($iter['refills']),
                        $taxrates,
                        1.0,
                        isset($iter['is_pyxis_container']) ? 1 : 0,
                        trim($iter['bd_dispense_form'] ?? ''),
                        trim($iter['bd_dispense_quantity'] ?? '')
                    )
                );

                // Add prices for this drug ID and selector.
                foreach ($iter['price'] as $key => $value) {
                    if ($value) {
                         $value = $value + 0;
                         sqlStatement(
                             "INSERT INTO prices ( " .
                             "pr_id, pr_selector, pr_level, pr_price ) VALUES ( " .
                             "?, ?, ?, ? )",
                             array($drug_id, $selector, $key, $value)
                         );
                    }
                } // end foreach price
            } // end if selector is present
        } // end for each selector
       // Save warehouse-specific mins and maxes for this drug.
        sqlStatement("DELETE FROM product_warehouse WHERE pw_drug_id = ?", array($drug_id));
        foreach ($_POST['form_wh_min'] as $whid => $whmin) {
            $whmin = 0 + $whmin;
            $whmax = 0 + $_POST['form_wh_max'][$whid];
            if ($whmin != 0 || $whmax != 0) {
                sqlStatement("INSERT INTO product_warehouse ( " .
                "pw_drug_id, pw_warehouse, pw_min_level, pw_max_level ) VALUES ( " .
                "?, ?, ?, ? )", array($drug_id, $whid, $whmin, $whmax));
            }
        }
    } // end if saving a drug

  // Close this window and redisplay the updated list of drugs.
  //
    echo "<script>\n";
    // Don't show alert for HL7 monitoring messages - they are handled by real-time monitoring
    if ($info_msg && strpos($info_msg, 'HL7') === false) {
        echo " alert('" . addslashes($info_msg) . "');\n";
    }

    echo " if (opener.refreshme) opener.refreshme();\n";
    if (($new_drug || !empty($_POST['create_packet'])) && $drug_id) {
        echo " window.location.href='add_edit_lot.php?drug=" . attr_url($drug_id) . "&lot=0'\n";
    } else if (!$drug_id && strpos($info_msg, 'HL7') !== false) {
        // During HL7 process - don't redirect, let the monitoring handle it
        echo " // HL7 process in progress, redirect will be handled by monitoring\n";
    } else if ($drug_id && strpos($info_msg, 'HL7 update message') !== false) {
        // During HL7 update process - don't close window, let monitoring handle it
        echo " // HL7 update in progress, window close will be handled by monitoring\n";
    } else {
        echo " window.close();\n";
    }

    echo "</script></body></html>\n";
    exit();
}

if ($drug_id) {
    $row = sqlQuery("SELECT * FROM drugs WHERE drug_id = ?", array($drug_id));
    $tres = sqlStatement("SELECT * FROM drug_templates WHERE " .
    "drug_id = ? ORDER BY selector", array($drug_id));
    
    // Load packet drugs if this is a packet
    $packet_drugs = array();
    if (!empty($row['is_packet'])) {
        $packet_res = sqlStatement("SELECT * FROM drugs WHERE packet_id = ? ORDER BY drug_id", array($drug_id));
        while ($prow = sqlFetchArray($packet_res)) {
            $packet_drugs[] = $prow;
        }
    }
} else {
    $row = array(
    'name' => '',
    'active' => '1',
    'dispensable' => '1',
    'allow_multiple' => '1',
    'allow_combining' => '',
    'consumable' => '0',
    'ndc_number' => '',
    'drug_code' => '',
    'on_order' => '0',
    'reorder_point' => '0',
    'max_level' => '0',
    'form' => '',
    'size' => '',
    'unit' => '',
    'route' => '',
    'cyp_factor' => '',
    'related_code' => '',
    'medid' => '',
    'brand_name' => '',
    'med_class_code' => '1',
    'mfr' => '',
    );
    if (!$drug_id) { // Generate MEDID for new drugs
        // Get user's facility from users table
        $user_id = $_SESSION['authUserID'];
        $user_fac = sqlQuery("SELECT facility_id FROM users WHERE id = ?", array($user_id));
        $fac_id = $user_fac['facility_id'] ?? $GLOBALS['facility'];
        $fac_name = sqlQuery("SELECT name FROM facility WHERE id = ?", array($fac_id))['name'];
        
        if ($fac_name == "El Paso Department of Public Health Respiratory Disease Clinic") {
            $prefix = "TB";
        } elseif ($fac_name == "El Paso Department of Public Health Sexual Health (STD) Clinic") {
            $prefix = "STD";
        } else {
            $prefix = "TB"; // Default prefix
        }
        $prefix_len = strlen($prefix);
        $row2 = sqlQuery("SELECT medid FROM drugs WHERE medid LIKE ? ORDER BY medid DESC LIMIT 1", array($prefix . '%'));
        if ($row2 && !empty($row2['medid'])) {
            $number_part = substr($row2['medid'], $prefix_len);
            $number = intval($number_part) + 1;
        } else {
            $number = 1;
        }
        $row['medid'] = $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
    }
    $tres = null; // Initialize $tres for new drugs
}
$title = $drug_id ? xl("Update Drug") : xl("Add Drug");
?>
<h3 class="ml-1"><?php echo text($title);?></h3>
<form method='post' name='theform' action='add_edit_drug.php?drug=<?php echo attr_url($drug_id); ?>'
 onsubmit='return validate(this);'>
    <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />

    <!-- Create Packet Option -->
    <div class="alert alert-success mt-2 mb-3 d-inline-block ml-3" style="padding: 10px;">
        <label style="font-weight: bold; margin: 0;">
            <input type="checkbox" id="create_packet" name="create_packet" value="1" 
                   onchange="togglePacketFields()" <?php echo (!empty($row['is_packet']) ? 'checked' : ''); ?> />
            <?php echo xlt('Create Packet'); ?>
        </label>
        <small class="form-text text-muted d-block mt-1"><?php echo xlt('Check this to create a packet instead of individual items'); ?></small>
    </div>

    <div class="form-group" id="drug_name_group">
        <label id="name_label"><?php echo xlt('Drug Name'); ?>:</label>
        <input class="form-control" id="drug_name_input" size="60" name="form_name" maxlength="120" value='<?php echo attr($row['name']) ?>' />
    </div>
    
    <div class="form-group" id="packet_name_group" style="display: none;">
        <label><?php echo xlt('Packet Name'); ?>:</label>
        <input class="form-control" size="60" name="form_packet_name" maxlength="120" value='<?php echo attr($row['name']) ?>' />
    </div>

    <div class="form-group mt-3">
        <label><?php echo xlt('Attributes'); ?>:</label>
        <input type='checkbox' name='form_active' value='1'<?php
        if ($row['active']) {
            echo ' checked';
        } ?> />
        <?php echo xlt('Active{{Drug}}'); ?>
        <input type='checkbox' name='form_consumable' value='1'<?php
        if ($row['consumable']) {
            echo ' checked';
        } ?> />
        <?php echo xlt('Consumable'); ?>
    </div>

    <div class="form-group mt-3">
        <label><?php echo xlt('Allow'); ?>:</label>
        <input type='checkbox' name='form_dispensable' value='1' onclick='dispensable_changed();'<?php
        if ($row['dispensable']) {
            echo ' checked';
        } ?> />
        <?php echo xlt('Inventory'); ?>
        <input type='checkbox' name='form_allow_multiple' value='1'<?php
        if ($row['allow_multiple']) {
            echo ' checked';
        } ?> />
        <?php echo xlt('Multiple Lots'); ?>
        <input type='checkbox' name='form_allow_combining' value='1'<?php
        if ($row['allow_combining']) {
            echo ' checked';
        } ?> />
        <?php echo xlt('Combining Lots'); ?>
    </div>

      <div class="form-group mt-3">
        <label><?php echo xlt('MEDID'); ?>:</label>
        <input class="form-control" size="20" name="form_medid" maxlength="20" value='<?php echo attr($row['medid']) ?>' pattern="[A-Za-z0-9]+" title="<?php echo xla('Alphanumeric characters only'); ?>" readonly />
    </div>

    <div class="form-group mt-3">
        <label><?php echo xlt('NDC Number'); ?>:</label>
        <input class="form-control w-100" size="40" name="form_ndc_number" maxlength="20" value='<?php echo attr($row['ndc_number']) ?>' onkeyup='maskkeyup(this,"<?php echo attr(addslashes($GLOBALS['gbl_mask_product_id'])); ?>")' onblur='maskblur(this,"<?php echo attr(addslashes($GLOBALS['gbl_mask_product_id'])); ?>")' />
    </div> 

    <!-- Manufacturer field removed per user request -->
    <!--
    <div class="form-group mt-3">
        <label><span class="text-danger">*</span> <?php echo xlt('Manufacturer'); ?>:</label>
        <input class="form-control" size="50" name="form_mfr" maxlength="50" value='<?php echo attr($row['mfr']) ?>' title="<?php echo xla('Manufacturer must contain only alphanumeric characters and spaces'); ?>" required />
    </div>
    -->

    <div class="form-group mt-3">
        <label><?php echo xlt('RXCUI Code'); ?>:</label>
        <input class="form-control w-100" type="text" size="50" name="form_drug_code" value='<?php echo attr($row['drug_code']) ?>'
             onclick='sel_related("?codetype=RXCUI&limit=1&target_element=form_drug_code")' title='<?php echo xla('Click to select RXCUI code'); ?>' data-toggle="tooltip" data-placement="top" readonly />
    </div>

    <div class="form-group mt-3">
        <label><?php echo xlt('Generic Drug Name'); ?>:</label>
        <input class="form-control" size="60" name="form_generic_name" maxlength="120" value='<?php echo isset($row['generic_name']) ? attr($row['generic_name']) : '' ?>' />
    </div>

    
    <div class="form-group mt-3">
        <label><?php echo xlt('Brand Drug Name'); ?>:</label>
        <input class="form-control" size="100" name="form_brand_name" maxlength="100" value='<?php echo attr($row['brand_name']) ?>' pattern="[A-Za-z0-9\s]+" title="<?php echo xla('Alphanumeric characters only'); ?>" />
        <small class="form-text text-muted"><?php echo xlt('Required when different than Generic Name'); ?></small>
    </div>

    <div class="form-group mt-3">
        <label><?php echo xlt('On Order'); ?>:</label>
        <input class="form-control" size="5" name="form_on_order" maxlength="7" value='<?php echo attr($row['on_order']) ?>' />
    </div>

    <div class="form-group mt-3">
        <label><?php echo xlt('Limits'); ?>:</label>
        <table class="table table-borderless pl-5">
            <tr>
                <td class="align-top ">
                    <?php echo !empty($GLOBALS['gbl_min_max_months']) ? xlt('Months') : xlt('Units'); ?>
                </td>
                <td class="align-top"><?php echo xlt('Global'); ?></td>
<?php
                    // One column header per warehouse title.
                    $pwarr = array();
                    $pwres = sqlStatement(
                        "SELECT lo.option_id, lo.title, " .
                        "pw.pw_min_level, pw.pw_max_level " .
                        "FROM list_options AS lo " .
                        "LEFT JOIN product_warehouse AS pw ON " .
                        "pw.pw_drug_id = ? AND " .
                        "pw.pw_warehouse = lo.option_id WHERE " .
                        "lo.list_id = 'warehouse' AND lo.activity = 1 ORDER BY lo.seq, lo.title",
                        array($drug_id)
                    );
                    while ($pwrow = sqlFetchArray($pwres)) {
                        $pwarr[] = $pwrow;
                        echo "     <td class='align-top'>" . text($pwrow['title']) . "</td>\n";
                    }
                    ?>
            </tr>
            <tr>
                <td class="align-top"><?php echo xlt('Min'); ?>&nbsp;</td>
                <td class="align-top">
                    <input class="form-control" size='5' name='form_reorder_point' maxlength='7' value='<?php echo attr($row['reorder_point']) ?>' title='<?php echo xla('Reorder point, 0 if not applicable'); ?>' data-toggle="tooltip" data-placement="top" />
                </td>
                <?php
                foreach ($pwarr as $pwrow) {
                    echo "     <td class='align-top'>";
                    echo "<input class='form-control' name='form_wh_min[" .
                    attr($pwrow['option_id']) .
                    "]' value='" . attr(0 + $pwrow['pw_min_level']) . "' size='5' " .
                    "title='" . xla('Warehouse minimum, 0 if not applicable') . "' data-toggle='tooltip' data-placement='top' />";
                    echo "&nbsp;&nbsp;</td>\n";
                }
                ?>
            </tr>
            <tr>
                <td class="align-top"><?php echo xlt('Max'); ?>&nbsp;</td>
                <td>
                    <input class='form-control' size='5' name='form_max_level' maxlength='7' value='<?php echo attr($row['max_level']) ?>' title='<?php echo xla('Maximum reasonable inventory, 0 if not applicable'); ?>' data-toggle="tooltip" data-placement="top" />
                </td>
                <?php
                foreach ($pwarr as $pwrow) {
                    echo "     <td class='align-top'>";
                    echo "<input class='form-control' name='form_wh_max[" .
                    attr($pwrow['option_id']) .
                    "]' value='" . attr(0 + $pwrow['pw_max_level']) . "' size='5' " .
                    "title='" . xla('Warehouse maximum, 0 if not applicable') . "' data-toggle='tooltip' data-placement='top' />";
                    echo "</td>\n";
                }
                ?>
            </tr>
        </table>
    </div>

    <div class="form-group mt-3 drugsonly" id="drugs_packet_section" style="display: none;">
        <label><?php echo xlt('Included Drugs in Packet'); ?>:</label>
        <p class="text-muted small"><?php echo xlt('Add drug details for multiple drugs in this packet'); ?></p>
        <table class='table table-borderless table-sm'>
            <thead>
                <tr>
                    <th style="min-width: 150px;"><?php echo xlt('Drug Name'); ?></th>
                    <th style="min-width: 100px;"><?php echo xlt('Form'); ?></th>
                    <th style="min-width: 80px;"><?php echo xlt('Strength'); ?></th>
                    <th style="min-width: 100px;"><?php echo xlt('Strength Unit'); ?></th>
                    <th style="min-width: 80px;"><?php echo xlt('Route'); ?></th>
                    <th style="min-width: 80px;"><?php echo xlt('Dose'); ?></th>
                    <th style="min-width: 80px;"><?php echo xlt('Frequency'); ?></th>
                    <th style="min-width: 80px;"><?php echo xlt('Quantity'); ?></th>
                </tr>
            </thead>
            <tbody id="drugs_table_body">
                <?php
                // Determine if we're editing a packet with existing drugs
                $editing_packet = !empty($packet_drugs);
                $row_num = 1;
                
                // If editing a packet with drugs, display existing drugs
                if ($editing_packet) {
                    foreach ($packet_drugs as $pdrug) {
                        echo "<tr>";
                        echo "  <td><input class='form-control form-control-sm' type='text' name='form_drugs[$row_num][name]' maxlength='120' value='" . attr($pdrug['name'] ?? '') . "' /></td>";
                        echo "  <td>";
                        echo "    <select class='form-control form-control-sm' name='form_drugs[$row_num][form]'>";
                        echo "      <option value=''></option>";
                        $fres = sqlStatement("SELECT option_id, title FROM list_options WHERE list_id = 'drug_form' AND activity = 1 ORDER BY seq");
                        while ($frow = sqlFetchArray($fres)) {
                            $selected = ($frow['option_id'] == ($pdrug['form'] ?? '')) ? " selected" : "";
                            echo "      <option value='" . attr($frow['option_id']) . "'$selected>" . text($frow['title']) . "</option>";
                        }
                        echo "    </select>";
                        echo "  </td>";
                        // Strength is stored in 'size' field for packet drugs
                        echo "  <td><input class='form-control form-control-sm' type='text' name='form_drugs[$row_num][strength]' maxlength='20' value='" . attr($pdrug['size'] ?? '') . "' /></td>";
                        echo "  <td>";
                        echo "    <select class='form-control form-control-sm' name='form_drugs[$row_num][strength_unit]'>";
                        echo "      <option value=''></option>";
                        $ures = sqlStatement("SELECT option_id, title FROM list_options WHERE list_id = 'drug_units' AND activity = 1 ORDER BY seq");
                        while ($urow = sqlFetchArray($ures)) {
                            $selected = ($urow['option_id'] == ($pdrug['unit'] ?? '')) ? " selected" : "";
                            echo "      <option value='" . attr($urow['option_id']) . "'$selected>" . text($urow['title']) . "</option>";
                        }
                        echo "    </select>";
                        echo "  </td>";
                        echo "  <td>";
                        echo "    <select class='form-control form-control-sm' name='form_drugs[$row_num][route]'>";
                        echo "      <option value=''></option>";
                        $rres = sqlStatement("SELECT option_id, title FROM list_options WHERE list_id = 'drug_route' AND activity = 1 ORDER BY seq");
                        while ($rrow = sqlFetchArray($rres)) {
                            $selected = ($rrow['option_id'] == ($pdrug['route'] ?? '')) ? " selected" : "";
                            echo "      <option value='" . attr($rrow['option_id']) . "'$selected>" . text($rrow['title']) . "</option>";
                        }
                        echo "    </select>";
                        echo "  </td>";
                        echo "  <td><input class='form-control form-control-sm' type='text' name='form_drugs[$row_num][dose]' maxlength='40' value='" . attr($pdrug['dose'] ?? '') . "' /></td>";
                        echo "  <td>";
                        echo "    <select class='form-control form-control-sm' name='form_drugs[$row_num][frequency]'>";
                        echo "      <option value=''></option>";
                        $freq_res = sqlStatement("SELECT option_id, title FROM list_options WHERE list_id = 'drug_interval' AND activity = 1 ORDER BY seq");
                        while ($freq_row = sqlFetchArray($freq_res)) {
                            $selected = ($freq_row['option_id'] == ($pdrug['pack_frequency'] ?? '')) ? " selected" : "";
                            echo "      <option value='" . attr($freq_row['option_id']) . "'$selected>" . text($freq_row['title']) . "</option>";
                        }
                        echo "    </select>";
                        echo "  </td>";
                        echo "  <td><input class='form-control form-control-sm' type='text' name='form_drugs[$row_num][quantity]' maxlength='40' value='" . attr($pdrug['quantity'] ?? '') . "' /></td>";
                        echo "</tr>";
                        $row_num++;
                    }
                }
                
                // Display 3 blank rows (or blank rows after existing drugs)
                $blank_rows = $editing_packet ? 1 : 3;
                for ($i = $row_num; $i < $row_num + $blank_rows; $i++) {
                    echo "<tr>";
                    echo "  <td><input class='form-control form-control-sm' type='text' name='form_drugs[$i][name]' maxlength='120' /></td>";
                    echo "  <td>";
                    echo "    <select class='form-control form-control-sm' name='form_drugs[$i][form]'>";
                    echo "      <option value=''></option>";
                    $fres = sqlStatement("SELECT option_id, title FROM list_options WHERE list_id = 'drug_form' AND activity = 1 ORDER BY seq");
                    while ($frow = sqlFetchArray($fres)) {
                        echo "      <option value='" . attr($frow['option_id']) . "'>" . text($frow['title']) . "</option>";
                    }
                    echo "    </select>";
                    echo "  </td>";
                    echo "  <td><input class='form-control form-control-sm' type='text' name='form_drugs[$i][strength]' maxlength='20' /></td>";
                    echo "  <td>";
                    echo "    <select class='form-control form-control-sm' name='form_drugs[$i][strength_unit]'>";
                    echo "      <option value=''></option>";
                    $ures = sqlStatement("SELECT option_id, title FROM list_options WHERE list_id = 'drug_units' AND activity = 1 ORDER BY seq");
                    while ($urow = sqlFetchArray($ures)) {
                        echo "      <option value='" . attr($urow['option_id']) . "'>" . text($urow['title']) . "</option>";
                    }
                    echo "    </select>";
                    echo "  </td>";
                    echo "  <td>";
                    echo "    <select class='form-control form-control-sm' name='form_drugs[$i][route]'>";
                    echo "      <option value=''></option>";
                    $rres = sqlStatement("SELECT option_id, title FROM list_options WHERE list_id = 'drug_route' AND activity = 1 ORDER BY seq");
                    while ($rrow = sqlFetchArray($rres)) {
                        echo "      <option value='" . attr($rrow['option_id']) . "'>" . text($rrow['title']) . "</option>";
                    }
                    echo "    </select>";
                    echo "  </td>";
                    echo "  <td><input class='form-control form-control-sm' type='text' name='form_drugs[$i][dose]' maxlength='40' /></td>";
                    echo "  <td>";
                    echo "    <select class='form-control form-control-sm' name='form_drugs[$i][frequency]'>";
                    echo "      <option value=''></option>";
                    $freq_res = sqlStatement("SELECT option_id, title FROM list_options WHERE list_id = 'drug_interval' AND activity = 1 ORDER BY seq");
                    while ($freq_row = sqlFetchArray($freq_res)) {
                        echo "      <option value='" . attr($freq_row['option_id']) . "'>" . text($freq_row['title']) . "</option>";
                    }
                    echo "    </select>";
                    echo "  </td>";
                    echo "  <td><input class='form-control form-control-sm' type='text' name='form_drugs[$i][quantity]' maxlength='40' /></td>";
                    echo "</tr>";
                }
                ?>
                <script>
                    // Set drugRowCounter based on number of rows displayed
                    drugRowCounter = <?php echo ($row_num + $blank_rows - 1); ?>;
                </script>
            </tbody>
        </table>
        <button type="button" class="btn btn-sm btn-success mt-2" onclick="addDrugRow()">
            <i class="fa fa-plus"></i> <?php echo xlt('Add Drug Row'); ?>
        </button>
    </div>

    <div id="form_to_route_section" style="display: block;">
    <div class="form-group mt-3 drugsonly">
        <label><?php echo xlt('Form'); ?>:</label>
        <?php
            generate_form_field(array('data_type' => 1,'field_id' => 'form','list_id' => 'drug_form','empty_title' => 'SKIP'), $row['form']);
        ?>
    </div>

    <div class="form-group mt-3 drugsonly">
        <label><?php echo xlt('Strength'); ?>:</label>
        <input class="form-control" size="10" name="form_size" maxlength="20" value='<?php echo attr($row['size']) ?>' />
    </div>

    <div class="form-group mt-3 drugsonly" title='<?php echo xlt('Strength Unit'); ?>'>
        <label><?php echo xlt('Strength Unit'); ?>:</label>
        <?php
            generate_form_field(array('data_type' => 1,'field_id' => 'unit','list_id' => 'drug_units','empty_title' => 'SKIP'), $row['unit']);
        ?>
    </div>

    <div class="form-group mt-3 drugsonly">
        <label><?php echo xlt('Concentration Volume'); ?>:</label>
        <input class="form-control" size="10" name="form_concentration_volume" maxlength="20" value='<?php echo isset($row['concentration_volume']) ? attr($row['concentration_volume']) : '' ?>' />
    </div>

    <div class="form-group mt-3 drugsonly" title='<?php echo xlt('Concentration Volume Unit'); ?>'>
        <label><?php echo xlt('Concentration Volume Unit'); ?>:</label>
        <?php
            generate_form_field(array('data_type' => 1,'field_id' => 'concentration_volume_unit','list_id' => 'drug_units','empty_title' => 'SKIP'), isset($row['concentration_volume_unit']) ? $row['concentration_volume_unit'] : '');
        ?>
    </div>

    <div class="form-group mt-3 drugsonly">
        <label><?php echo xlt('Total Volume'); ?>:</label>
        <input class="form-control" size="10" name="form_total_volume" maxlength="20" value='<?php echo isset($row['total_volume']) ? attr($row['total_volume']) : '' ?>' />
    </div>

    <div class="form-group mt-3 drugsonly" title='<?php echo xlt('Total Volume Unit'); ?>'>
        <label><?php echo xlt('Total Volume Unit'); ?>:</label>
        <?php
            generate_form_field(array('data_type' => 1,'field_id' => 'total_volume_unit','list_id' => 'drug_units','empty_title' => 'SKIP'), isset($row['total_volume_unit']) ? $row['total_volume_unit'] : '');
        ?>
    </div>

    <div class="form-group mt-3 drugsonly">
        <label><?php echo xlt('Route'); ?>:</label>
        <?php
            generate_form_field(array('data_type' => 1,'field_id' => 'route','list_id' => 'drug_route','empty_title' => 'SKIP'), $row['route']);
        ?>
    </div>

    <div class="form-group mt-3 ippfonly" style='display:none'> <!-- Removed per CV 2017-03-29 -->
        <label><?php echo xlt('CYP Factor'); ?>:</label>
        <input class="form-control" size="10" name="form_cyp_factor" maxlength="20" value='<?php echo attr($row['cyp_factor']) ?>' />
    </div>
    </div>

    <div class="form-group mt-3 drugsonly">
        <label><?php echo xlt('Relate To'); ?>:</label>
        <input class="form-control w-100" type="text" size="50" name="form_related_code" value='<?php echo attr($row['related_code']) ?>'
             onclick='sel_related("?target_element=form_related_code")' title='<?php echo xla('Click to select related code'); ?>' data-toggle="tooltip" data-placement="top" readonly />
    </div>

       <div class="form-group mt-3" id="packet_route_section_form" style="display: none;">
        <label><?php echo xlt('Route'); ?>:</label>
        <?php
            generate_form_field(array('data_type' => 1,'field_id' => 'route','list_id' => 'drug_route','empty_title' => 'SKIP'), $row['route']);
        ?>
    </div>

    <div class="form-group mt-3">
        <label><?php echo xlt('Medication Class'); ?>:</label>
        <select class="form-control" name="form_med_class_code">
            <option value=""><?php echo xlt('Select...'); ?></option>
            <option value="00" <?php echo ($row['med_class_code'] == '00') ? 'selected' : ''; ?>>00</option>
            <option value="0" <?php echo ($row['med_class_code'] == '0') ? 'selected' : ''; ?>>0</option>
            <option value="1" <?php echo ($row['med_class_code'] == '1') ? 'selected' : ''; ?>>1</option>
            <option value="2" <?php echo ($row['med_class_code'] == '2') ? 'selected' : ''; ?>>2</option>
            <option value="3" <?php echo ($row['med_class_code'] == '3') ? 'selected' : ''; ?>>3</option>
            <option value="4" <?php echo ($row['med_class_code'] == '4') ? 'selected' : ''; ?>>4</option>
            <option value="5" <?php echo ($row['med_class_code'] == '5') ? 'selected' : ''; ?>>5</option>
        </select>
    </div>

    <div class="form-group mt-3 drugsonly">
        <label>
            <?php echo $GLOBALS['sell_non_drug_products'] == 2 ? xlt('Fees') : xlt('Prescription Templates'); ?>:
        </label>
        
        <!-- BD Pyxis Container Option -->
        <div class="alert alert-info mt-2 mb-3" id="bd_pyxis_section" style="padding: 10px;">
            <label style="font-weight: bold; margin: 0;">
                <input type="checkbox" id="is_pyxis_container" name="is_pyxis_container" value="1" 
                       onchange="togglePyxisFields()" 
                       <?php echo (isset($row['uses_pyxis_containers']) && $row['uses_pyxis_containers']) ? 'checked' : ''; ?> />
                <?php echo xlt('Order to BD Pyxis as a container?'); ?>
            </label>
            <small class="form-text text-muted d-block mt-1"><?php echo xlt('If yes, specify BD Dispense Form and Quantity for each template'); ?></small>
        </div>
        
        <table class='table table-borderless'>
            <thead>
                <tr>
                    <th class='drugsonly' style="min-width:180px;max-width:300px;"><?php echo xlt('Drug Name'); ?></th>
                    <th class='drugsonly'><?php echo xlt('Dose'); ?></th>
                    <th class='drugsonly'><?php echo xlt('Frequency'); ?></th>
                    <th class='drugsonly'><?php echo xlt('Quantity'); ?></th>
                    <th class='drugsonly'><?php echo xlt('Refills'); ?></th>
                    <th class='drugsonly pyxis-header bd-dispense-header' style='display:none;'><?php echo xlt('BD Dispense Form'); ?></th>
                    <th class='drugsonly pyxis-header bd-dispense-header' style='display:none;'><?php echo xlt('BD Dispense Qty'); ?></th>
                    <?php
                    // Show a heading for each price level.  Also create an array of prices
                    // for new template lines.
                    $emptyPrices = array();
                    $pres = sqlStatement("SELECT option_id, title FROM list_options " .
                        "WHERE list_id = 'pricelevel' AND activity = 1 ORDER BY seq");
                    while ($prow = sqlFetchArray($pres)) {
                        $emptyPrices[$prow['option_id']] = '';
                        echo "     <th>" .
                        generate_display_field(array('data_type' => '1','list_id' => 'pricelevel'), $prow['option_id']) .
                        "</th>\n";
                    }

                    // Show a heading for each tax rate.
                    $pres = sqlStatement("SELECT option_id, title FROM list_options " .
                        "WHERE list_id = 'taxrate' AND activity = 1 ORDER BY seq");
                    while ($prow = sqlFetchArray($pres)) {
                        echo "     <th>" .
                            generate_display_field(array('data_type' => '1','list_id' => 'taxrate'), $prow['option_id']) .
                            "</th>\n";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $blank_lines = $GLOBALS['sell_non_drug_products'] == 2 ? 1 : 3;
            if ($tres) {
                while ($trow = sqlFetchArray($tres)) {
                    $blank_lines = $GLOBALS['sell_non_drug_products'] == 2 ? 0 : 1;
                    $selector = $trow['selector'];
                // Get array of prices.
                    $prices = array();
                    $pres = sqlStatement(
                        "SELECT lo.option_id, p.pr_price " .
                        "FROM list_options AS lo LEFT OUTER JOIN prices AS p ON " .
                        "p.pr_id = ? AND p.pr_selector = ? AND " .
                        "p.pr_level = lo.option_id " .
                        "WHERE lo.list_id = 'pricelevel' AND lo.activity = 1 ORDER BY lo.seq",
                        array($drug_id, $selector)
                    );
                    while ($prow = sqlFetchArray($pres)) {
                        $prices[$prow['option_id']] = $prow['pr_price'];
                    }

                    writeTemplateLine(
                        $selector,
                        $trow['dosage'],
                        $trow['period'],
                        $trow['quantity'],
                        $trow['refills'],
                        $prices,
                        $trow['taxrates'],
                        $trow['pkgqty'],
                        $trow['is_pyxis_container'] ?? 0,
                        $trow['bd_dispense_form'] ?? '',
                        $trow['bd_dispense_quantity'] ?? ''
                    );
                }
            }

            for ($i = 0; $i < $blank_lines; ++$i) {
                $selector = $GLOBALS['sell_non_drug_products'] == 2 ? $row['name'] : '';
                writeTemplateLine($selector, '', '', '', '', $emptyPrices, '', '1');
            }
            ?>
            </tbody>
        </table>
    </div>

    <div class="btn-group">
        <button type='submit' class="btn btn-primary btn-save" name='form_save'
         value='<?php echo  $drug_id ? xla('Update') : xla('Add') ; ?>'
         onclick='return this.clicked = true;'
         ><?php echo $drug_id ? xlt('Update') : xlt('Add') ; ?></button>
        <?php if (AclMain::aclCheckCore('admin', 'super') && $drug_id) { ?>
        <button class="btn btn-danger" type='submit' name='form_delete'
         onclick='return this.clicked = true;' value='<?php echo xla('Delete'); ?>'
         ><?php echo xlt('Delete'); ?></button>
        <?php } ?>
        <button type='button' class="btn btn-secondary btn-cancel" onclick='window.close()'><?php echo xlt('Cancel'); ?></button>
    </div>
</form>

<script>

$(function () {
  $('[data-toggle="tooltip"]').tooltip();
});

dispensable_changed();
togglePyxisFields();
togglePacketFields();

<?php
if ($alertmsg) {
    echo "alert('" . addslashes($alertmsg) . "');\n";
}
if ($info_msg) {
    echo "alert('" . addslashes($info_msg) . "');\n";
    if (strpos($info_msg, 'deleted successfully') !== false) {
        echo "// Reload parent window if it exists, then close this window\n";
        echo "if (window.opener) {\n";
        echo "    window.opener.location.reload();\n";
        echo "}\n";
        echo "window.close();\n";
    }
}
// Initialize packet fields visibility on page load if editing a packet
if (!empty($row['is_packet'])) {
    echo "document.addEventListener('DOMContentLoaded', function() { togglePacketFields(); });\n";
}
?>

</script>

</body>
</html>

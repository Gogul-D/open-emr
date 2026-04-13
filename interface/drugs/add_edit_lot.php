<?php

/**
 * add and edit lot
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2006-2021 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2017 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

 // TODO: Replace tables with BS4 grid classes for GSoC


require_once("../globals.php");
require_once("drugs.inc.php");
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;

// Check authorizations.
$auth_admin = AclMain::aclCheckCore('admin', 'drugs');
$auth_lots  = $auth_admin               ||
    AclMain::aclCheckCore('inventory', 'lots') ||
    AclMain::aclCheckCore('inventory', 'purchases') ||
    AclMain::aclCheckCore('inventory', 'transfers') ||
    AclMain::aclCheckCore('inventory', 'adjustments') ||
    AclMain::aclCheckCore('inventory', 'consumption') ||
    AclMain::aclCheckCore('inventory', 'destruction');
if (!$auth_lots) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Edit/Add Lot")]);
    exit;
}

function checkWarehouseUsed($warehouse_id)
{
    global $drug_id;
    $row = sqlQuery("SELECT count(*) AS count FROM drug_inventory WHERE " .
    "drug_id = ? AND on_hand != 0 AND " .
    "destroy_date IS NULL AND warehouse_id = ?", array($drug_id,$warehouse_id));
    return $row['count'];
}

function areVendorsUsed()
{
    $row = sqlQuery(
        "SELECT COUNT(*) AS count FROM users " .
        "WHERE active = 1 AND (info IS NULL OR info NOT LIKE '%Inactive%') " .
        "AND abook_type LIKE 'vendor%'"
    );
    return $row['count'];
}

// Generate a <select> list of warehouses.
// If multiple lots are not allowed for this product, then restrict the
// list to warehouses that are unused for the product.
// Returns the number of warehouses allowed.
// For these purposes the "unassigned" option is considered a warehouse.
//
function genWarehouseList($tag_name, $currvalue, $title, $class = '')
{
    global $drug_id, $is_user_restricted;

    $drow = sqlQuery("SELECT allow_multiple FROM drugs WHERE drug_id = ?", array($drug_id));
    $allow_multiple = $drow['allow_multiple'];

    $lres = sqlStatement("SELECT * FROM list_options " .
    "WHERE list_id = 'warehouse' AND activity = 1 ORDER BY seq, title");

    echo "<select name='" . attr($tag_name) . "' id='" . attr($tag_name) . "'";
    if ($class) {
        echo " class='" . attr($class) . "'";
    }

    echo " title='" . attr($title) . "'>";

    $got_selected = false;
    $count = 0;

    if ($allow_multiple /* || !checkWarehouseUsed('') */) {
        echo "<option value=''>" . xlt('Unassigned') . "</option>";
        ++$count;
    }

    while ($lrow = sqlFetchArray($lres)) {
        $whid = $lrow['option_id'];
        $facid = (int) ($lrow['option_value'] ?? null);
        if ($whid != $currvalue) {
            if (!$allow_multiple && checkWarehouseUsed($whid)) {
                continue;
            }
            if ($is_user_restricted && !isWarehouseAllowed($facid, $whid)) {
                continue;
            }
        }
        // Value identifies both warehouse and facility to support validation.
        echo "<option value='" . attr("$whid|$facid") . "'";

        if (
            (strlen($currvalue) == 0 && $lrow['is_default']) ||
            (strlen($currvalue)  > 0 && $whid == $currvalue)
        ) {
            echo " selected";
            $got_selected = true;
        }

        echo ">" . text($lrow['title']) . "</option>\n";

        ++$count;
    }

    if (!$got_selected && strlen($currvalue) > 0) {
        echo "<option value='" . attr($currvalue) . "' selected>* " . text($currvalue) . " *</option>";
        echo "</select>";
        echo " <span class='text-danger' title='" .
        xla('Please choose a valid selection from the list.') . "'>" .
        xlt('Fix this') . "!</span>";
    } else {
        echo "</select>";
    }

    return $count;
}


$drug_id = $_REQUEST['drug'] + 0;
$lot_id  = $_REQUEST['lot'] + 0;
$info_msg = "";

$form_trans_type = intval(isset($_POST['form_trans_type']) ? $_POST['form_trans_type'] : '0');

// Note if user is restricted to any facilities and/or warehouses.
$is_user_restricted = isUserRestricted();

if (!$drug_id) {
    die(xlt('Drug ID missing!'));
}

// Load drug info and check if it's a packet
$drug_info = sqlQuery("SELECT * FROM drugs WHERE drug_id = ?", array($drug_id));
$is_packet = !empty($drug_info['is_packet']);
$packet_drugs = array();

// Load component drugs if this is a packet
if ($is_packet) {
    $component_res = sqlStatement(
        "SELECT * FROM drugs WHERE packet_id = ? AND is_packet = 0 ORDER BY drug_id",
        array($drug_id)
    );
    while ($comp = sqlFetchArray($component_res)) {
        $packet_drugs[] = $comp;
    }
}
?>
<html>
<head>
<title><?php echo $lot_id ? xlt("Edit") : xlt("Add New");
echo " " . xlt('Lot'); ?></title>

<?php Header::setupHeader(['datetime-picker', 'opener']); ?>

<style>
td {
    font-size: 0.8125rem;
}
/* Fix datepicker calendar visibility */
.xdsoft_datetimepicker {
    z-index: 9999 !important;
}
.datetimepicker {
    z-index: 9999 !important;
}
</style>

<script>

 function validate() {
  var f = document.forms[0];
  
  // Check if this is a packet lots form (has packet_drugs input fields)
  var isPacketLots = document.querySelector('input[name*="packet_drugs"]') !== null;
  
  if (isPacketLots) {
    // Validate packet lots table - all fields optional, user can add lots with minimal info
    var packetLotsTableRows = document.querySelectorAll('table.table-borderless tbody tr');
    if (packetLotsTableRows.length > 0) {
      var errors = [];
      
      for (var i = 0; i < packetLotsTableRows.length; i++) {
        var lotNumberInput = packetLotsTableRows[i].querySelector('input[name*="[lot_number]"]');
        var qohInput = packetLotsTableRows[i].querySelector('input[name*="[on_hand]"]');
        var warehouseSelect = packetLotsTableRows[i].querySelector('select[name*="[warehouse]"]');
        
        if (lotNumberInput && qohInput && warehouseSelect) {
          var lotNum = lotNumberInput.value.trim();
          var qoh = parseInt(qohInput.value) || 0;
          var warehouse = warehouseSelect.value.trim();
          
          // All fields are optional - skip validation
        }
      }
      
      if (errors.length > 0) {
        alert(<?php echo xlj('Please fix the following errors:'); ?> + '\n' + errors.join('\n'));
        return false;
      }
      
      // Allow form submission with optional fields
      return true;
    }
  }
  
  // Standard lot form validation (only if not packet lots)
  if (!f.form_trans_type) {
    return true; // Skip if form field not available
  }
  
  var trans_type = f.form_trans_type.value;

  if (trans_type > '0') {
   // Transaction date validation. Must not be later than today or before 2000.
   if (f.form_sale_date.value > <?php echo js_escape(date('Y-m-d')) ?> || f.form_sale_date.value < '2000-01-01') {
    alert(<?php echo xlj('Transaction date must not be in the future or before 2000'); ?>);
    return false;
   }
   // Quantity validations.
   var qty = parseInt(f.form_quantity.value);
   if (!qty) {
    alert(<?php echo xlj('A quantity is required'); ?>);
    return false;
   }
   if (f.form_trans_type.value != '5' && qty < 0) {
    alert(<?php echo xlj('Quantity cannot be negative for this transaction type'); ?>);
    return false;
   }
  }

  // Get source and target facility IDs.
  var facfrom = 0;
  var facto = 0;
  var a = f.form_source_lot.value.split('|', 2);
  var lotfrom = parseInt(a[0]);
  if (a.length > 1) facfrom = parseInt(a[1]);
  a = f.form_warehouse_id.value.split('|', 2);
  whid = a[0];
  if (a.length > 1) facto = parseInt(a[1]);

  if (lotfrom == '0' && f.form_lot_number.value.search(/\S/) < 0) {
   alert(<?php echo xlj('A lot number is required'); ?>);
   return false;
  }

  // Require comments for all transactions.
  if (f.form_trans_type.value > '0' && f.form_notes.value.search(/\S/) < 0) {
   alert(<?php echo xlj('Comments are required'); ?>);
   return false;
  }

  if (f.form_trans_type.value == '4') {
   // Transfers require a source lot.
   if (!lotfrom) {
    alert(<?php echo xlj('A source lot is required'); ?>);
    return false;
   }
   // Check the case of a transfer between different facilities.
   if (facto != facfrom) {
    if (!confirm(<?php echo xlj('Warning: Source and target facilities differ. Continue anyway?'); ?>))
     return false;
   }
  }

  // Check for missing expiration date on a purchase or simple update.
  if (f.form_expiration.value == '' && f.form_trans_type.value <= '2') {
   if (!confirm(<?php echo xlj('Warning: Most lots should have an expiration date. Continue anyway?'); ?>)) {
    return false;
   }
  }

  return true;
 }

 function trans_type_changed() {
  var f = document.forms[0];
  var sel = f.form_trans_type;
  var type = sel.options[sel.selectedIndex].value;
  // display attributes
  var showQuantity  = true;
  var showOnHand       = true;
  var showSaleDate  = true;
  var showCost      = true;
  var showSourceLot = true;
  var showNotes     = true;
  var showManufacturer = true;
  var showLotNumber    = true;
  var showWarehouse    = true;
  var showExpiration   = true;
  var showVendor       = true;  // Always show vendor/stock field

  // readonly attributes.
  var roManufacturer   = true;
  var roLotNumber      = true;
  var roExpiration     = true;

  labelWarehouse       = <?php echo xlj('Warehouse'); ?>;

  if (type == '2') { // purchase
    showSourceLot = false;
    roManufacturer = false;
    roLotNumber    = false;
    roExpiration   = false;
<?php if (!$lot_id) { // target lot is not known yet ?>
    showOnHand     = false;
<?php } ?>
  }
  else if (type == '3') { // return
    showSourceLot = false;
    showManufacturer = false;
  }
  else if (type == '4') { // transfer
    showCost         = false;
    showManufacturer = false;
    showLotNumber    = false;
    showExpiration   = false;
<?php if ($lot_id) { // disallow warehouse change on xfer to existing lot ?>
    showWarehouse    = false;
<?php } else { // target lot is not known yet ?>
    showOnHand       = false;
<?php } ?>
    labelWarehouse = <?php echo xlj('Destination Warehouse'); ?>;
  }
  else if (type == '5') { // adjustment
    showCost = false;
    showSourceLot = false;
    showManufacturer = false;
  }
  else if (type == '7') { // consumption
    showCost      = false;
    showSourceLot = false;
    showManufacturer = false;
  }
  else {                  // Edit Only
    showQuantity  = false;
    showSaleDate  = false;
    showCost      = false;
    showSourceLot = false;
    showNotes     = false;
    roManufacturer = false;
    roLotNumber    = false;
    roExpiration   = false;
  }
  document.getElementById('row_quantity'  ).style.display = showQuantity  ? '' : 'none';
  document.getElementById('row_on_hand'     ).style.display = showOnHand       ? '' : 'none';
  document.getElementById('row_sale_date' ).style.display = showSaleDate  ? '' : 'none';
  document.getElementById('row_cost'      ).style.display = showCost      ? '' : 'none';
  document.getElementById('row_source_lot').style.display = showSourceLot ? '' : 'none';
  document.getElementById('row_notes'     ).style.display = showNotes     ? '' : 'none';
  document.getElementById('row_manufacturer').style.display = showManufacturer ? '' : 'none';
  document.getElementById('row_vendor'      ).style.display = showVendor       ? '' : 'none';
  document.getElementById('row_lot_number'  ).style.display = showLotNumber    ? '' : 'none';
  document.getElementById('row_warehouse'   ).style.display = showWarehouse    ? '' : 'none';
  document.getElementById('row_expiration'  ).style.display = showExpiration   ? '' : 'none';

  f.form_manufacturer.readOnly = roManufacturer;
  f.form_lot_number.readOnly   = roLotNumber;
  f.form_expiration.readOnly   = roExpiration;

  document.getElementById('label_warehouse').innerHTML = labelWarehouse;
 }

    $(function () {
        // Initialize all datepicker fields
        $('.datepicker').datetimepicker({
            <?php $datetimepicker_timepicker = false; ?>
            <?php $datetimepicker_showseconds = false; ?>
            <?php $datetimepicker_formatInput = false; ?>
            <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
        });
    });
</script>

</head>

<body class="body_top">
<?php
if ($lot_id) {
    $row = sqlQuery("SELECT * FROM drug_inventory WHERE drug_id = ? " .
    "AND inventory_id = ?", array($drug_id,$lot_id));
}

// If we are saving, then save and close the window.
//
if (!empty($_POST['form_save'])) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }

    // Handle packet lot bulk creation
    if ($is_packet && !$lot_id && !empty($_POST['packet_drugs'])) {
        $packet_error = '';
        $created_count = 0;
        $skipped_count = 0;
        
        error_log("DEBUG: Starting packet lot creation for packet_id=$drug_id");
        
        // First, create a lot for the main packet itself if lot number is provided
        if (!empty($_POST['form_lot_number'])) {
            $packet_lot_number = trim($_POST['form_lot_number']);
            $packet_manufacturer = trim($_POST['form_manufacturer'] ?? '');
            $packet_expiration = trim($_POST['form_expiration'] ?? '');
            list($packet_warehouse_id) = explode('|', $_POST['form_warehouse_id'] ?? '');
            $packet_vendor_id = $_POST['form_vendor_id'] ?? '';
            $packet_quantity = is_numeric($_POST['form_quantity']) ? intval($_POST['form_quantity']) : 0;
            
            if ($packet_quantity > 0 && !empty($packet_warehouse_id)) {
                // Check for duplicate lot
                $exptest = $packet_expiration ?
                    ("expiration = '" . add_escape_custom($packet_expiration) . "'") : "expiration IS NULL";
                $dup_check = sqlQuery(
                    "SELECT COUNT(*) AS count FROM drug_inventory " .
                    "WHERE lot_number = ? " .
                    "AND drug_id = ? " .
                    "AND warehouse_id = ? " .
                    "AND $exptest " .
                    "AND on_hand != 0 " .
                    "AND destroy_date IS NULL",
                    array($packet_lot_number, $drug_id, $packet_warehouse_id)
                );

                if ($dup_check['count'] == 0) {
                    try {
                        $packet_quantity_int = intval($packet_quantity);
                        $created_lot_id = sqlInsert(
                            "INSERT INTO drug_inventory ( " .
                            "drug_id, lot_number, manufacturer, expiration, " .
                            "warehouse_id, vendor_id, on_hand, availability " .
                            ") VALUES ( " .
                            "?, ?, ?, ?, ?, ?, ?, ? " .
                            ")",
                            array(
                                $drug_id,
                                $packet_lot_number,
                                $packet_manufacturer,
                                (empty($packet_expiration) ? NULL : $packet_expiration),
                                $packet_warehouse_id,
                                $packet_vendor_id,
                                $packet_quantity_int,
                                $packet_quantity_int  // Set availability equal to on_hand
                            )
                        );

                        if ($created_lot_id) {
                            $created_count++;
                            error_log("DEBUG: Successfully inserted main packet lot with ID=$created_lot_id for drug_id=$drug_id");
                        }
                    } catch (Exception $e) {
                        $packet_error .= xlt('Packet') . ': ' . xlt('Error creating lot') . "\n";
                        error_log("DEBUG: Exception caught for main packet: " . $e->getMessage());
                    }
                } else {
                    $packet_error .= xlt('Packet') . ': ' . xlt('Duplicate lot') . "\n";
                }
            }
        }
        
        // Then create lots for each component drug
        error_log("DEBUG: form_drugs data: " . json_encode($_POST['packet_drugs'] ?? array()));
        
        foreach ($_POST['packet_drugs'] as $drug_idx => $drug_lot_data) {
            error_log("DEBUG: Raw drug_lot_data for row $drug_idx: " . json_encode($drug_lot_data));
            
            // Extract and validate data
            $packet_drug_id = intval($drug_lot_data['drug_id'] ?? 0);
            $packet_lot_number = trim($drug_lot_data['lot_number'] ?? '');
            $packet_manufacturer = trim($drug_lot_data['manufacturer'] ?? '');
            $packet_expiration = trim($drug_lot_data['expiration'] ?? '');
            
            // Parse warehouse value - warehouse option_id is text, not numeric (e.g., 'tb_clinic')
            $packet_warehouse_id = trim($drug_lot_data['warehouse'] ?? '');
            error_log("DEBUG: Raw warehouse value: '$packet_warehouse_id'");
            
            $packet_vendor_id = trim($drug_lot_data['vendor_id'] ?? '');
            $packet_quantity = is_numeric($drug_lot_data['on_hand']) ? intval($drug_lot_data['on_hand']) : 0;

            error_log("DEBUG: Drug $drug_idx - ID=$packet_drug_id, Lot=$packet_lot_number, Warehouse=$packet_warehouse_id, QOH=$packet_quantity");

            // Validate required fields
            if (empty($packet_lot_number) || $packet_quantity <= 0) {
                $skipped_count++;
                error_log("DEBUG: Skipping row $drug_idx - empty lot number or zero quantity");
                continue;
            }
            
            if ($packet_drug_id <= 0 || empty($packet_warehouse_id)) {
                if (empty($packet_warehouse_id)) {
                    $packet_error .= xlt('Drug') . ' ' . $drug_idx . ' (' . text($drug_lot_data['drug_id'] ?? 'unknown') . '): ' . xlt('Warehouse must be selected') . "\n";
                    error_log("DEBUG: Error - Warehouse not selected for drug_idx=$drug_idx");
                } else {
                    $packet_error .= xlt('Drug') . ' ' . $drug_idx . ': ' . xlt('Invalid drug ID') . "\n";
                    error_log("DEBUG: Error - Invalid drug_id=$packet_drug_id");
                }
                continue;
            }

            // Check for duplicate lot
            $exptest = $packet_expiration ?
                ("expiration = '" . add_escape_custom($packet_expiration) . "'") : "expiration IS NULL";
            $dup_check = sqlQuery(
                "SELECT COUNT(*) AS count FROM drug_inventory " .
                "WHERE lot_number = ? " .
                "AND drug_id = ? " .
                "AND warehouse_id = ? " .
                "AND $exptest " .
                "AND on_hand != 0 " .
                "AND destroy_date IS NULL",
                array($packet_lot_number, $packet_drug_id, $packet_warehouse_id)
            );

            if ($dup_check['count'] > 0) {
                $packet_error .= xlt('Drug') . ' ' . $drug_idx . ': ' . xlt('Duplicate lot') . "\n";
                error_log("DEBUG: Duplicate lot found for drug_id=$packet_drug_id, lot=$packet_lot_number");
                continue;
            }

            // Insert the lot for this drug
            try {
                $packet_quantity_int = intval($packet_quantity);
                $created_lot_id = sqlInsert(
                    "INSERT INTO drug_inventory ( " .
                    "drug_id, lot_number, manufacturer, expiration, " .
                    "warehouse_id, vendor_id, on_hand, availability " .
                    ") VALUES ( " .
                    "?, ?, ?, ?, ?, ?, ?, ? " .
                    ")",
                    array(
                        $packet_drug_id,
                        $packet_lot_number,
                        $packet_manufacturer,
                        (empty($packet_expiration) ? NULL : $packet_expiration),
                        $packet_warehouse_id,
                        $packet_vendor_id,
                        $packet_quantity_int,
                        $packet_quantity_int  // Set availability equal to on_hand
                    )
                );

                if ($created_lot_id) {
                    $created_count++;
                    error_log("DEBUG: Successfully inserted lot with ID=$created_lot_id for drug_id=$packet_drug_id");
                } else {
                    error_log("DEBUG: Insert failed - no ID returned for drug_id=$packet_drug_id");
                }
            } catch (Exception $e) {
                $packet_error .= xlt('Drug') . ' ' . $drug_idx . ': ' . xlt('Error creating lot') . "\n";
                error_log("DEBUG: Exception caught: " . $e->getMessage());
            }
        }

        // Close window after packet lot creation
        echo "<script>\n";
        $message = xlt('Created') . ' ' . $created_count . ' ' . xlt('lot(s)');
        if ($skipped_count > 0) {
            $message .= " (" . $skipped_count . " " . xlt('skipped - empty or zero quantity') . ")";
        }
        if (!empty($packet_error)) {
            $message .= "\n" . xlt('Errors:') . "\n" . $packet_error;
        }
        echo " alert(" . js_escape($message) . ");\n";
        echo " window.close();\n";
        echo " if (opener.refreshme) opener.refreshme();\n";
        echo "</script></body></html>\n";
        exit();
    }

    $form_quantity = is_numeric($_POST['form_quantity']) ? intval($_POST['form_quantity']) : 0;
    $form_cost = sprintf('%0.2f', $_POST['form_cost']);
    // $form_source_lot = $_POST['form_source_lot'] + 0;

    list($form_source_lot, $form_source_facility) = explode('|', $_POST['form_source_lot']);
    $form_source_lot = intval($form_source_lot);

    list($form_warehouse_id) = explode('|', $_POST['form_warehouse_id']);

    $form_expiration   = $_POST['form_expiration'] ?? '';
    $form_lot_number   = $_POST['form_lot_number'] ?? '';
    $form_manufacturer = $_POST['form_manufacturer'] ?? '';
    $form_vendor_id    = $_POST['form_vendor_id'] ?? '';

    if ($form_trans_type < 0 || $form_trans_type > 7) {
        die(xlt('Internal error!'));
    }

    if (
        !$auth_admin && (
            $form_trans_type == 2 && !AclMain::aclCheckCore('inventory', 'purchases') ||
            $form_trans_type == 3 && !AclMain::aclCheckCore('inventory', 'purchases') ||
            $form_trans_type == 4 && !AclMain::aclCheckCore('inventory', 'transfers') ||
            $form_trans_type == 5 && !AclMain::aclCheckCore('inventory', 'adjustments') ||
            $form_trans_type == 7 && !AclMain::aclCheckCore('inventory', 'consumption')
            )
    ) {
        die(xlt('Not authorized'));
    }

      // Some fixups depending on transaction type.
    if ($form_trans_type == 3) { // return
        $form_quantity = 0 - $form_quantity;
        $form_cost = 0 - $form_cost;
    } elseif ($form_trans_type == 5) { // adjustment
        $form_cost = 0;
    } elseif ($form_trans_type == 7) { // consumption
        $form_quantity = 0 - $form_quantity;
        $form_cost = 0;
    } elseif ($form_trans_type == 0) { // no transaction
        $form_quantity = 0;
        $form_cost = 0;
    }
    if ($form_trans_type != 4) { // not transfer
        $form_source_lot = 0;
    }

    // If a transfer, make sure there is sufficient quantity in the source lot
    // and apply some default values from it.
    if ($form_source_lot) {
        $srow = sqlQuery(
            "SELECT lot_number, expiration, manufacturer, vendor_id, on_hand " .
            "FROM drug_inventory WHERE drug_id = ? AND inventory_id = ?",
            array($drug_id, $form_source_lot)
        );
        if (empty($form_lot_number)) {
            $form_lot_number = $srow['lot_number'  ];
        }
        if (empty($form_expiration)) {
             $form_expiration = $srow['expiration'  ];
        }
        if (empty($form_manufacturer)) {
             $form_manufacturer = $srow['manufacturer'];
        }
        if (empty($form_vendor_id)) {
             $form_vendor_id = $srow['vendor_id'   ];
        }
        if ($form_quantity && $srow['on_hand'] < $form_quantity) {
            $info_msg = xl('Transfer failed, insufficient quantity in source lot');
        }
    }

    if (!$info_msg) {
        // If purchase or transfer with no destination lot specified, see if one already exists.
        if (!$lot_id && $form_lot_number && ($form_trans_type == 2 || $form_trans_type == 4)) {
            $erow = sqlQuery(
                "SELECT * FROM drug_inventory WHERE " .
                "drug_id = ? AND warehouse_id = ? AND lot_number = ? AND destroy_date IS NULL AND on_hand != 0 " .
                "ORDER BY inventory_id DESC LIMIT 1",
                array($drug_id, $form_warehouse_id, $form_lot_number)
            );
            if (!empty($erow['inventory_id'])) {
                // Yes a matching lot exists, use it and its values.
                $lot_id = $erow['inventory_id'];
                if (empty($form_expiration)) {
                    $form_expiration   = $erow['expiration'  ];
                }
                if (empty($form_manufacturer)) {
                    $form_manufacturer = $erow['manufacturer'];
                }
                if (empty($form_vendor_id)) {
                    $form_vendor_id    = $erow['vendor_id'   ];
                }
            }
        }

        // Destination lot already exists.
        if ($lot_id) {
            if ($_POST['form_save']) {
                // Make sure the destination quantity will not end up negative.
                if (($row['on_hand'] + $form_quantity) < 0) {
                    $info_msg = xl('Transaction failed, insufficient quantity in destination lot');
                } else {
                    sqlStatement(
                        "UPDATE drug_inventory SET " .
                        "lot_number = ?, " .
                        "manufacturer = ?, " .
                        "expiration = ?, "  .
                        "vendor_id = ?, " .
                        "warehouse_id = ?, " .
                        "on_hand = on_hand + ? "  .
                        "WHERE drug_id = ? AND inventory_id = ?",
                        array(
                            $form_lot_number,
                            $form_manufacturer,
                            (empty($form_expiration) ? "NULL" : $form_expiration),
                            $form_vendor_id,
                            $form_warehouse_id,
                            $form_quantity,
                            $drug_id,
                            $lot_id
                        )
                    );
                }
            } else {
                sqlStatement("DELETE FROM drug_inventory WHERE drug_id = ? " .
                "AND inventory_id = ?", array($drug_id,$lot_id));
            }
        } else { // Destination lot will be created.
            if ($form_quantity < 0) {
                $info_msg = xl('Transaction failed, quantity is less than zero');
            } else {
                $exptest = $form_expiration ?
                    ("expiration = '" . add_escape_custom($form_expiration) . "'") : "expiration IS NULL";
                $crow = sqlQuery(
                    "SELECT count(*) AS count from drug_inventory " .
                    "WHERE lot_number = ? " .
                    "AND drug_id = ? " .
                    "AND warehouse_id = ? " .
                    "AND $exptest " .
                    "AND on_hand != 0 " .
                    "AND destroy_date IS NULL",
                    array($form_lot_number, $drug_id, $form_warehouse_id)
                );
                if ($crow['count']) {
                    $info_msg = xl('Transaction failed, duplicate lot');
                } else {
                    $form_quantity_int = intval($form_quantity);
                    $lot_id = sqlInsert(
                        "INSERT INTO drug_inventory ( " .
                        "drug_id, lot_number, manufacturer, expiration, " .
                        "vendor_id, warehouse_id, on_hand, availability " .
                        ") VALUES ( " .
                        "?, "                            .
                        "?, " .
                        "?, " .
                        "?, "  .
                        "?, " .
                        "?, " .
                        "?, " .
                        "? "  .
                        ")",
                        array(
                            $drug_id,
                            $form_lot_number,
                            $form_manufacturer,
                            (empty($form_expiration) ? "NULL" : $form_expiration),
                            $form_vendor_id,
                            $form_warehouse_id,
                            $form_quantity_int,
                            $form_quantity_int  // Set availability equal to on_hand
                        )
                    );
                }
            }
        }

        // Create the corresponding drug_sales transaction.
        if ($_POST['form_save'] && $form_quantity && !$info_msg) {
            $form_notes = $_POST['form_notes'];
            $form_sale_date = $_POST['form_sale_date'];
            if (empty($form_sale_date)) {
                $form_sale_date = date('Y-m-d');
            }

            sqlStatement(
                "INSERT INTO drug_sales ( " .
                "drug_id, inventory_id, prescription_id, pid, encounter, user, sale_date, " .
                "quantity, fee, xfer_inventory_id, distributor_id, notes, trans_type " .
                ") VALUES ( " .
                "?, " .
                "?, '0', '0', '0', " .
                "?, " .
                "?, " .
                "?, " .
                "?, " .
                "?, " .
                "?, " .
                "?, " .
                "? )",
                array(
                    $drug_id,
                    $lot_id,
                    $_SESSION['authUser'],
                    $form_sale_date,
                    (0 - $form_quantity),
                    (0 - $form_cost),
                    $form_source_lot,
                    0,
                    $form_notes,
                    $form_trans_type
                )
            );

            // If this is a transfer then reduce source QOH.
            if ($form_source_lot) {
                sqlStatement(
                    "UPDATE drug_inventory SET " .
                    "on_hand = on_hand - ? " .
                    "WHERE inventory_id = ?",
                    array($form_quantity,$form_source_lot)
                );
            }
        }
    } // end if not $info_msg

    // Close this window and redisplay the updated list of drugs.
    //
    echo "<script>\n";
    if ($info_msg) {
        echo " alert(" . js_escape($info_msg) . ");\n";
    }

    echo " window.close();\n";
    echo " if (opener.refreshme) opener.refreshme();\n";
    echo "</script></body></html>\n";
    exit();
}
$title = $lot_id ? xl("Update Lot") : xl("Add Lot");
?>
<h3 class="ml-1"><?php echo text($title);?></h3>
<form method='post' name='theform' action='add_edit_lot.php?drug=<?php echo attr_url($drug_id); ?>&lot=<?php echo attr_url($lot_id); ?>' onsubmit='return validate()'>
<input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />

<?php if ($is_packet && !$lot_id) { // Show packet lots table when creating lots for a new packet ?>
    <div class="alert alert-info mb-3">
        <strong><?php echo xlt('Packet:'); ?> <?php echo text($drug_info['name']); ?></strong>
        <br /><small><?php echo xlt('Add lot details for the packet and each drug in this packet'); ?></small>
    </div>

    <!-- Packet Lot Details Section -->
    <div class="form-group mt-3">
        <h5 class="mb-3"><?php echo xlt('Packet Lot Details'); ?></h5>
        <table class="table table-borderless w-100">
            <tr>
                <td class="text-nowrap align-top" style="min-width: 150px;"><?php echo xlt('Lot Number'); ?>:</td>
                <td>
                    <input class="form-control" type='text' size='40' name='form_lot_number' maxlength='40' value='<?php echo attr($row['lot_number'] ?? '') ?>' />
                </td>
            </tr>
            <tr>
                <td class="text-nowrap align-top"><?php echo xlt('Manufacturer'); ?>:</td>
                <td>
                    <input class="form-control" type='text' size='40' name='form_manufacturer' maxlength='250' value='<?php echo attr($row['manufacturer'] ?? '') ?>' />
                </td>
            </tr>
            <tr>
                <td class="text-nowrap align-top"><?php echo xlt('Expiration'); ?>:</td>
                <td>
                    <input type='text' class='datepicker form-control' style="max-width: 200px;" size='10' name='form_expiration' id='form_expiration'
                        value='<?php echo attr($row['expiration'] ?? '') ?>'
                        title='<?php echo xla('yyyy-mm-dd date of expiration'); ?>' />
                </td>
            </tr>
            <tr id='row_warehouse'>
                <td class="text-nowrap align-top"><span id="label_warehouse"><?php echo xlt('Warehouse'); ?></span>:</td>
                <td>
                    <?php
                    genWarehouseList('form_warehouse_id', $row['warehouse_id'] ?? '', xlt('Warehouse stocking location'), 'form-control');
                    ?>
                </td>
            </tr>
            <tr id='row_stock'>
                <td class="text-nowrap align-top"><?php echo xlt('Stock'); ?>:</td>
                <td>
                    <?php
                    generate_form_field(
                        array('data_type' => 14, 'field_id' => 'vendor_id',
                        'list_id' => '', 'edit_options' => 'V',
                        'description' => xl('Address book entry for the stock/vendor')),
                        $row['vendor_id'] ?? ''
                    );
                    ?>
                </td>
            </tr>
            <tr>
                <td class="text-nowrap align-top"><?php echo xlt('QOH (Quantity on Hand)'); ?>:</td>
                <td>
                    <input class="form-control" type='number' size='10' name='form_quantity' value='<?php echo attr($row['on_hand'] ?? '0') ?>' />
                </td>
            </tr>
        </table>
    </div>

    <hr />

    <!-- Packet Drugs Lot Details Section -->
    <div class="form-group mt-4">
        <h5 class="mb-3"><?php echo xlt('Component Drugs in Packet'); ?></h5>
        <p class="text-muted small"><?php echo xlt('Add lot details for each drug included in this packet (optional)'); ?></p>
    </div>

    <table class='table table-borderless table-sm'>
        <thead>
            <tr>
                <th style="min-width: 150px;"><?php echo xlt('Drug Name'); ?></th>
                <th style="min-width: 100px;"><?php echo xlt('Lot Number'); ?></th>
                <th style="min-width: 120px;"><?php echo xlt('Manufacturer'); ?></th>
                <th style="min-width: 100px;"><?php echo xlt('Expiration'); ?></th>
                <th style="min-width: 100px;"><?php echo xlt('Warehouse'); ?></th>
                <th style="min-width: 100px;"><?php echo xlt('Stock'); ?></th>
                <th style="min-width: 80px;"><?php echo xlt('QOH'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($packet_drugs as $idx => $pdrug) {
                $drug_num = $idx + 1;
                echo "<tr>";
                echo "  <td><strong>" . text($pdrug['name']) . "</strong></td>";
                echo "  <td><input class='form-control form-control-sm' type='text' name='packet_drugs[$drug_num][lot_number]' maxlength='40' /></td>";
                echo "  <td><input class='form-control form-control-sm' type='text' name='packet_drugs[$drug_num][manufacturer]' maxlength='250' /></td>";
                echo "  <td><input type='text' class='datepicker form-control form-control-sm' name='packet_drugs[$drug_num][expiration]' maxlength='10' /></td>";
                echo "  <td>";
                echo "    <select class='form-control form-control-sm' name='packet_drugs[$drug_num][warehouse]' id='warehouse_$drug_num'>";
                echo "      <option value=''>-- " . xlt('Select Warehouse') . " --</option>";
                
                $wres = sqlStatement("SELECT lo.option_id, lo.title, lo.option_value FROM list_options AS lo WHERE lo.list_id = 'warehouse' AND lo.activity = 1 ORDER BY lo.seq, lo.title");
                $warehouse_count = 0;
                while ($wrow = sqlFetchArray($wres)) {
                    $warehouse_count++;
                    $warehouse_value = $wrow['option_id'];  // Just use option_id for now
                    echo "      <option value='" . attr($warehouse_value) . "'>" . text($wrow['title']) . "</option>";
                }
                
                if ($warehouse_count == 0) {
                    echo "      <option value='' disabled>" . xlt('No warehouses available') . "</option>";
                    error_log("DEBUG: No warehouses found from list_options");
                }
                
                echo "    </select>";
                echo "  </td>";
                echo "  <td>";
                echo "    <select class='form-control form-control-sm' name='packet_drugs[$drug_num][vendor_id]' id='vendor_$drug_num'>";
                echo "      <option value=''>-- " . xlt('Select Stock') . " --</option>";
                
                $vres = sqlStatement("SELECT id, organization FROM users WHERE active = 1 AND abook_type LIKE 'vendor%' ORDER BY organization");
                while ($vrow = sqlFetchArray($vres)) {
                    echo "      <option value='" . attr($vrow['id']) . "'>" . text($vrow['organization']) . "</option>";
                }
                
                echo "    </select>";
                echo "  </td>";
                echo "  <td><input class='form-control form-control-sm' type='text' name='packet_drugs[$drug_num][on_hand]' maxlength='10' value='0' /></td>";
                echo "  <input type='hidden' name='packet_drugs[$drug_num][drug_id]' value='" . attr($pdrug['drug_id']) . "' />";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>

    <div class="form-group mt-3">
        <button type='submit' class="btn btn-primary" name='form_save' value='<?php echo xla('Add Lots'); ?>' onclick='return this.clicked = true;'><?php echo xlt('Add Lots for Packet'); ?></button>
        <button type='button' class="btn btn-secondary" onclick='window.close()'><?php echo xlt('Cancel'); ?></button>
    </div>

<?php } else { // Standard single-drug lot form ?>

<table class="table table-borderless w-100">

 <tr id='row_sale_date'>
  <td class="text-nowrap align-top"><?php echo xlt('Date'); ?>:</td>
  <td>
   <input type='text' class="datepicker" size='10' name='form_sale_date' id='form_sale_date'
    value='<?php echo attr(date('Y-m-d')) ?>'
    title='<?php echo xla('yyyy-mm-dd date of purchase or transfer'); ?>' />
  </td>
 </tr>

 <tr>
  <td class="text-nowrap align-top"><?php echo xlt('Transaction Type'); ?>:</td>
  <td>
   <select name='form_trans_type' class='form-control' onchange='trans_type_changed()'>
<?php
foreach (
    array(
    '2' => xl('Purchase/Receipt'),
    '3' => xl('Return'),
    '4' => xl('Transfer'),
    '5' => xl('Adjustment'),
    '7' => xl('Consumption'),
    '0' => xl('Edit Only'),
    ) as $key => $value
) {
    echo "<option value='" . attr($key) . "'";
    if (
        !$auth_admin && (
        $key == 2 && !AclMain::aclCheckCore('inventory', 'purchases') ||
        $key == 3 && !AclMain::aclCheckCore('inventory', 'purchases') ||
        $key == 4 && !AclMain::aclCheckCore('inventory', 'transfers') ||
        $key == 5 && !AclMain::aclCheckCore('inventory', 'adjustments') ||
        $key == 7 && !AclMain::aclCheckCore('inventory', 'consumption')
        )
    ) {
        echo " disabled";
    } else if (
        $lot_id  && in_array($key, array('2', '4'     )) ||
        // $lot_id  && in_array($key, array('2')) ||
        !$lot_id && in_array($key, array('0', '3', '5', '7'))
    ) {
        echo " disabled";
    } else {
        if (isset($_POST['form_trans_type']) && $key == $form_trans_type) {
            echo " selected";
        }
    }
    echo ">" . text($value) . "</option>\n";
}
?>
   </select>
  </td>
 </tr>

 <tr id='row_lot_number'>
  <td class="text-nowrap align-top"><?php echo xlt('Lot Number'); ?>:</td>
  <td>
   <input class="form-control w-100" type='text' size='40' name='form_lot_number' maxlength='40' value='<?php echo attr(isset($row['lot_number']) ? $row['lot_number'] : '') ?>' />
  </td>
 </tr>

 <tr id='row_manufacturer'>
  <td class="text-nowrap align-top"><?php echo xlt('Manufacturer'); ?>:</td>
  <td>
   <input class="form-control w-100" type='text' size='40' name='form_manufacturer' maxlength='250' value='<?php echo attr(isset($row['manufacturer']) ? $row['manufacturer'] : '') ?>' />
  </td>
 </tr>

 <tr id='row_expiration'>
  <td class="text-nowrap align-top"><?php echo xlt('Expiration'); ?>:</td>
  <td>
   <input type='text' class='datepicker form-control w-50' size='10' name='form_expiration' id='form_expiration'
    value='<?php echo attr(isset($row['expiration']) ? $row['expiration'] : '') ?>'
    title='<?php echo xla('yyyy-mm-dd date of expiration'); ?>' />
  </td>
 </tr>

 <tr id='row_source_lot'>
  <td class="text-nowrap align-top"><?php echo xlt('Source Lot'); ?>:</td>
  <td>
   <select name='form_source_lot' class='form-control'>
    <option value='0'> </option>
<?php
$lres = sqlStatement(
    "SELECT " .
    "di.inventory_id, di.lot_number, di.on_hand, lo.title, lo.option_value, di.warehouse_id " .
    "FROM drug_inventory AS di " .
    "LEFT JOIN list_options AS lo ON lo.list_id = 'warehouse' AND " .
    "lo.option_id = di.warehouse_id AND lo.activity = 1 " .
    "WHERE di.drug_id = ? AND di.inventory_id != ? AND " .
    "di.on_hand > 0 AND di.destroy_date IS NULL " .
    "ORDER BY di.lot_number, lo.title, di.inventory_id",
    array ($drug_id,$lot_id)
);
while ($lrow = sqlFetchArray($lres)) {
    // TBD: For transfer to an existing lot do we want to force the same lot number?
    // Check clinic/wh permissions.
    $facid = (int) ($lrow['option_value'] ?? null);
    if ($is_user_restricted && !isWarehouseAllowed($facid, $lrow['warehouse_id'])) {
        continue;
    }
    echo "<option value='" . attr($lrow['inventory_id']) . '|' . attr($facid)  . "'>";
    echo text($lrow['lot_number']);
    if (!empty($lrow['title'])) {
        echo " / " . text($lrow['title']);
    }
    echo " (" . text($lrow['on_hand']) . ")";
    echo "</option>\n";
}
?>
   </select>
  </td>
 </tr>

 <tr id='row_vendor'>
  <td class="text-nowrap align-top"><?php echo xlt('Stock'); ?>:</td>
  <td>
<?php
// Address book entries for vendors.
generate_form_field(
    array('data_type' => 14, 'field_id' => 'vendor_id',
    'list_id' => '', 'edit_options' => 'V',
    'description' => xl('Address book entry for the stock/vendor')),
    isset($row['vendor_id']) ? $row['vendor_id'] : ''
);
?>
  </td>
 </tr>

 <tr id='row_warehouse'>
  <td class="text-nowrap align-top" id="label_warehouse"><?php echo xlt('Warehouse'); ?>:</td>
  <td>
<?php
if (
    !genWarehouseList(
        "form_warehouse_id",
        isset($row['warehouse_id']) ? $row['warehouse_id'] : '',
        xl('Location of this lot'),
        "form-control"
    )
) {
    $info_msg = xl('This product allows only one lot per warehouse.');
}
?>
  </td>
 </tr>

 <tr id='row_on_hand'>
  <td class="text-nowrap align-top"><?php echo xlt('On Hand'); ?>:</td>
  <td>
    <span><?php echo text((isset($row['on_hand']) ? $row['on_hand'] : 0) + 0); ?></span>
  </td>
 </tr>

 <tr id='row_quantity'>
  <td class="text-nowrap align-top"><?php echo xlt('Quantity'); ?>:</td>
  <td>
   <input class="form-control" type='text' size='5' name='form_quantity' maxlength='7' />
  </td>
 </tr>

 <tr id='row_cost'>
  <td class="text-nowrap align-top"><?php echo xlt('Total Cost'); ?>:</td>
  <td>
   <input class="form-control" type='text' size='7' name='form_cost' maxlength='12' />
  </td>
 </tr>

 <tr id='row_notes' title='<?php echo xla('Include your initials and details of reason for transaction.'); ?>'>
  <td class="text-nowrap align-top"><?php echo xlt('Comments'); ?>:</td>
  <td>
   <input class="form-control w-100" type='text' size='40' name='form_notes' maxlength='255' />
  </td>
 </tr>

</table>

<div class="btn-group mt-3">
<input type='submit' class="btn btn-primary" name='form_save' value='<?php echo $lot_id ? xla('Update') : xla('Add') ?>' />

<?php if ($lot_id && ($auth_admin || AclMain::aclCheckCore('inventory', 'destruction'))) { ?>
<input type='button' class="btn btn-danger" value='<?php echo xla('Destroy'); ?>'
 onclick="window.location.href='destroy_lot.php?drug=<?php echo attr_url($drug_id); ?>&lot=<?php echo attr_url($lot_id); ?>'" />
<?php } ?>

<input type='button' class="btn btn-primary btn-print" value='<?php echo xla('Print'); ?>' onclick='window.print()' />

<input type='button' class="btn btn-warning" value='<?php echo xla('Cancel'); ?>' onclick='window.close()' />
</div>

<?php } // End of standard lot form conditional ?>

</form>
<script>
<?php
if ($info_msg) {
    echo " alert('" . addslashes($info_msg) . "');\n";
    echo " window.close();\n";
}
?>
// Only call trans_type_changed for standard lot forms (not for packet lots)
if (document.forms[0].form_trans_type) {
    trans_type_changed();
}
</script>
</body>
</html>

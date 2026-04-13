<?php

 // Copyright (C) 2006-2021 Rod Roark <rod@sunsetsystems.com>
 //
 // This program is free software; you can redistribute it and/or
 // modify it under the terms of the GNU General Public License
 // as published by the Free Software Foundation; either version 2
 // of the License, or (at your option) any later version.

require_once("../globals.php");
require_once("immunization.inc.php");
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;

$hasEmptyTds = false;

// Check authorizations.
$auth_admin = AclMain::aclCheckCore('admin', 'drugs');
$auth_lots  = $auth_admin                             ||
    AclMain::aclCheckCore('inventory', 'lots') ||
    AclMain::aclCheckCore('inventory', 'purchases') ||
    AclMain::aclCheckCore('inventory', 'transfers') ||
    AclMain::aclCheckCore('inventory', 'adjustments') ||
    AclMain::aclCheckCore('inventory', 'consumption') ||
    AclMain::aclCheckCore('inventory', 'destruction');
$auth_anything = $auth_lots                           ||
    AclMain::aclCheckCore('inventory', 'sales') ||
    AclMain::aclCheckCore('inventory', 'reporting');
if (!$auth_anything) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Drug Inventory")]);
    exit;
}
// Note if user is restricted to any facilities and/or warehouses.
$is_user_restricted = isUserRestricted();

// For each sorting option, specify the ORDER BY argument.
//
$ORDERHASH = array(
  'prod' => 'd.name, d.drug_id, di.expiration, di.lot_number',
  'act'  => 'd.active, d.name, d.drug_id, di.expiration, di.lot_number',
  'ndc'  => 'd.ndc_number, d.name, d.drug_id, di.expiration, di.lot_number',
  'con'  => 'd.consumable, d.name, d.drug_id, di.expiration, di.lot_number',
  'form' => 'lof.title, d.name, d.drug_id, di.expiration, di.lot_number',
  'lot'  => 'di.lot_number, d.name, d.drug_id, di.expiration',
  'wh'   => 'lo.title, d.name, d.drug_id, di.expiration, di.lot_number',
  'fac'  => 'f.name, d.name, d.drug_id, di.expiration, di.lot_number',
  'qoh'  => 'di.on_hand, d.name, d.drug_id, di.expiration, di.lot_number',
  'exp'  => 'di.expiration, d.name, d.drug_id, di.lot_number',
  'stock' => 'di.vendor_id, d.name, d.drug_id, di.expiration, di.lot_number',
);

$form_facility = 0 + empty($_REQUEST['form_facility']) ? 0 : $_REQUEST['form_facility'];
$form_show_empty = empty($_REQUEST['form_show_empty']) ? 0 : 1;
$form_show_inactive = empty($_REQUEST['form_show_inactive']) ? 0 : 1;
$form_consumable = isset($_REQUEST['form_consumable']) ? intval($_REQUEST['form_consumable']) : 0;

// Incoming form_warehouse, if not empty is in the form "warehouse/facility".
// The facility part is an attribute used by JavaScript logic.
$form_warehouse = empty($_REQUEST['form_warehouse']) ? '' : $_REQUEST['form_warehouse'];
$tmp = explode('/', $form_warehouse);
$form_warehouse = $tmp[0];

// Get the order hash array value and key for this request.
$form_orderby = isset($ORDERHASH[$_REQUEST['form_orderby'] ?? '']) ? $_REQUEST['form_orderby'] : 'prod';
$orderby = $ORDERHASH[$form_orderby];

$binds = array();
$where = "WHERE 1 = 1";
if ($form_facility) {
    $where .= " AND lo.option_value IS NOT NULL AND lo.option_value = ?";
    $binds[] = $form_facility;
}
if ($form_warehouse) {
    $where .= " AND di.warehouse_id IS NOT NULL AND di.warehouse_id = ?";
    $binds[] = $form_warehouse;
}
if (!$form_show_inactive) {
    $where .= " AND d.active = 1";
}
if ($form_consumable) {
    if ($form_consumable == 1) {
        $where .= " AND d.consumable = '1'";
    } else {
        $where .= " AND d.consumable != '1'";
    }
}


// If show empty lots is checked, only show drugs with at least one lot with on_hand = 0
if ($form_show_empty) {
    $dion = "AND di.on_hand = 0";
} else {
    $dion = "AND di.on_hand != 0";
}


// Main query for all lots
$res = sqlStatement(
    "SELECT d.drug_id,
    d.name,
    d.active,
    d.cvx_code,
    d.form,
    d.size,
    d.unit,
    d.route,
    d.dispensable, " .
    "di.inventory_id, di.lot_number, di.expiration, di.facility_id, di.manufacturer, di.ndc, di.on_hand, " .
    "di.warehouse_id, di.vendor_id, lo.title, lo.option_value AS facid, f.name AS facname, di.last_trans " .
    "FROM immunization_drug AS d " .
    "LEFT JOIN  immunization_inventory_drug AS di ON di.drug_id = d.drug_id " .
    "AND di.destroy_date IS NULL $dion " .
    "LEFT JOIN list_options AS lo ON lo.list_id = 'warehouse' AND " .
    "lo.option_id = di.warehouse_id AND lo.activity = 1 " .
    "LEFT JOIN facility AS f ON f.id = di.facility_id " .
    "LEFT JOIN list_options AS lof ON lof.list_id = 'drug_form' AND " .
    "lof.option_id = d.form AND lof.activity = 1 " .
    "$where ORDER BY d.active DESC, $orderby",
    $binds
);

// Separate query for 'Edit Only' lots (transaction type 0)
$editonly_lots = array();
$editonly_res = sqlStatement(
    "SELECT di.*, d.name, d.active, d.ndc_number, d.form, d.size, d.unit, d.route, lo.title, lo.option_value AS facid, f.name AS facname " .
    "FROM immunization_inventory_drug di " .
    "JOIN immunization_drug d ON di.drug_id = d.drug_id " .
    "LEFT JOIN list_options AS lo ON lo.list_id = 'warehouse' AND lo.option_id = di.warehouse_id AND lo.activity = 1 " .
    "LEFT JOIN facility AS f ON f.id = di.facility_id " .
    "WHERE di.destroy_date IS NULL AND di.last_trans IS NULL " .
    "AND di.on_hand != 0 " .
    "ORDER BY di.drug_id, di.expiration, di.lot_number"
);
while ($erow = sqlFetchArray($editonly_res)) {
    $editonly_lots[$erow['drug_id']][] = $erow;
}


function generateEmptyTd($n)
{

global $hasEmptyTds;
    $hasEmptyTds = true; 

    $temp = '';
    while ($n > 0) {
        // $temp .= "<td></td>";
        //  $temp .= "<td><div>N/A</div></td>";
        $temp .= "<td class='empty-td'><div>N/A&nbsp;</div></td>";
        $n--;
    }
    echo $temp;

    // echo "<td title='" . xla('Not applicable') . "'>N/A</td>";
}
function processData($data)
{
    $data['inventory_id'] = [$data['inventory_id']];
    $data['lot_number'] = [$data['lot_number']];
    // $data['facname'] =  [$data['facname']];
    $data['facname'] =  [$data['facname']];
    $data['title'] =  [$data['title']];
    $data['on_hand'] = [$data['on_hand']];
    $data['expiration'] = [$data['expiration']];
    $data['vendor_id'] = [$data['vendor_id']];
    return $data;
}
function mergeData($d1, $d2)
{
    $d1['inventory_id'] = array_merge($d1['inventory_id'], $d2['inventory_id']);
    $d1['lot_number'] = array_merge($d1['lot_number'], $d2['lot_number']);
    $d1['facname'] = array_merge($d1['facname'], $d2['facname']);
    $d1['title'] = array_merge($d1['title'], $d2['title']);
    $d1['on_hand'] = array_merge($d1['on_hand'], $d2['on_hand']);
    $d1['expiration'] = array_merge($d1['expiration'], $d2['expiration']);
    $d1['vendor_id'] = array_merge($d1['vendor_id'], $d2['vendor_id']);
    return $d1;
}
 function mapToTable($row)
 {


    global $auth_admin, $auth_lots;
    $today = date('Y-m-d');
    $td_count = 0;
    if ($row) {
        echo " <tr class='detail'>\n";
        $lastid = $row['drug_id'];
        if ($auth_admin) {
            echo "<td title='" . xla('Click to edit') . "' onclick='dodclick(" . attr(addslashes($lastid)) . ")'>" .
            "<a href='' onclick='return false'>" .
            text($row['name']) . "</a></td>\n";
        } else {
            echo "  <td>" . text($row['name']) . "</td>\n";
        }
       
        echo "  <td>" . ($row['active'] ? xlt('Yes') : xlt('No')) . "</td>\n";
        echo "  <td>" . text($row['ndc']) . "</td>\n";
        echo "  <td>" .
        generate_display_field(array('data_type' => '1','list_id' => 'drug_form'), $row['form']) .
        "</td>\n";
        echo "  <td>" . text(number_format((float)$row['size'], 2)) . "</td>\n";
        echo "  <td title='" . xla('Measurement Units') . "'>" .
        generate_display_field(array('data_type' => '1','list_id' => 'drug_units'), $row['unit']) .
        "</td>\n";

        if ($auth_lots && $row['dispensable']) {
            echo "  <td onclick='doiclick(" . intval($lastid) . ",0)' title='" .
                xla('Purchase or Transfer') . "' style='padding:0'>" .
                "<input type='button' value='" . xla('Tran') . "'style='padding:0' /></td>\n";
        } else {
            echo "  <td title='" . xla('Not applicable') . "'>&nbsp;</td>\n";
        }
     
        //    print_r($row['inventory_id']);

        if (!empty($row['inventory_id'][0])) {
            echo "<td>";
            foreach ($row['inventory_id'] as $key => $value) {
                if ($auth_lots) {

                    // print_r("first row");
                    echo "<div title='" .
                        xla('Adjustment, Consumption, Return, or Edit') .
                        "' onclick='doiclick(" . intval($lastid) . "," .
                        intval($row['inventory_id'][$key]) . ")'>" .
                        "<a href='' onclick='return false'>" .
                        text($row['lot_number'][$key]) .
                        "</a></div>";
                } else {

                  
                    echo "  <div>" . text($row['lot_number'][$key]) . "</div>\n";
                }
            }
            echo "</td>"; 

                            echo "<td>";
                foreach ($row['vendor_id'] as $value) {
                    if ($value) {
                        $vendor = sqlQuery("SELECT organization FROM users WHERE id = ?", array($value));
                        echo "<div>" . text($vendor['organization']) . "</div>";
                    } else {
                        echo "<div>N/A</div>";
                    }
                }
                echo "</td>\n<td>";
        
            foreach ($row['facname'] as $value) {
            $value = $value != null ? $value : "N/A";
            echo "<div >" . text($value) . "</div>";
            }
            // Display facility name by matching facility_id with facility table
            // foreach ($row['facility_id'] as $fid) {
            //     if ($fid) {
            //         $frow = sqlQuery("SELECT name FROM facility WHERE id = ?", array($fid));
            //         $fname = $frow && !empty($frow['name']) ? $frow['name'] : "N/A";
            //         echo "<div>" . text($fname) . "</div>";
            //     } else {
            //         echo "<div>N/A</div>";
            //     }
            // }
            echo "</td>\n<td>";

            foreach ($row['title'] as $value) {
                $value = $value != null ? $value : "N/A";
                echo "<div >" .  text($value) . "</div>";
            }
            echo "</td>\n<td>";

            foreach ($row['on_hand'] as $value) {
                $value = $value != null ? $value : "N/A";
                echo "<div >" . text($value) . "</div>";
            }
            echo "</td>\n<td>";

            foreach ($row['expiration'] as $value) {
                // Make the expiration date red if expired.
                $expired = !empty($value) && strcmp($value, $today) <= 0;
                $value = !empty($value) ? oeFormatShortDate($value) : xl('N/A');
                echo "<div" . ($expired ? " style='color:red'" : "") . ">" . text($value) . "</div>";
            }
            echo "</td>\n";
        } else {
                   generateEmptyTd(6); // Lot, Stock, Facility, Warehouse, QOH, Expires
        }
        echo " </tr>\n";
    }
}

?>
<html>

<head>

<title><?php echo xlt('Immunization Inventory'); ?></title>

<style>
a, a:visited, a:hover {
  color: var(--primary);
}
#mymaintable thead .sorting::before,
#mymaintable thead .sorting_asc::before,
#mymaintable thead .sorting_asc::after,
#mymaintable thead .sorting_desc::before,
#mymaintable thead .sorting_desc::after,
#mymaintable thead .sorting::after {
  display: none;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
  padding: 0 !important;
  margin: 0 !important;
  border: 0 !important;
}

.paginate_button:hover {
  background: transparent !important;
}



#mymaintable th, #mymaintable td {
  /* padding: 0px;  */
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
  vertical-align: top;
  
} 

#mymaintable td.empty-td {
  min-width: 140px;
}

/* #mymaintable td {
  min-width: 100px;
} */

#datatable-loader {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(255,255,255,0.8);
  z-index: 9999;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}
.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #ccc;
  border-top: 4px solid #007bff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}



</style>

<?php Header::setupHeader(['datatables', 'datatables-dt', 'datatables-bs', 'report-helper']); ?>

<script>

// callback from add_edit_drug.php or add_edit_immunization_inventory_drug.php:
function refreshme() {
  const loader = document.getElementById('datatable-loader');
  const table = document.getElementById('mymaintable');
  if (loader && table) {
    loader.innerHTML = `
      <div class="spinner"></div>
      <p style="margin-top:10px;font-weight:bold;">Loading Immunization Inventory...</p>
    `;
    loader.style.display = 'block';
    table.style.display = 'none';
  }
  // Add cache-busting parameter to force fresh data
  const url = new URL(location.href);
  url.searchParams.set('_refresh', Date.now());
  location.href = url.toString();
}

// Process click on drug title.
function dodclick(id) {
 dlgopen('add_edit_immunization.php?drug=' + id, '_blank', 900, 600);
}

// Process click on drug QOO or lot.
function doiclick(id, lot) {
 dlgopen('add_edit_lot.php?drug=' + id + '&lot=' + lot, '_blank', 600, 475);
}

// Enable/disable warehouse options depending on current facility.
function facchanged() {
    var f = document.forms[0];
    var facid = f.form_facility.value;
    var theopts = f.form_warehouse.options;
    for (var i = 1; i < theopts.length; ++i) {
        var tmp = theopts[i].value.split('/');
        var dis = facid && (tmp.length < 2 || tmp[1] != facid);
        theopts[i].disabled = dis;
        if (dis) {
            theopts[i].selected = false;
        }
    }
}

</script>
</head>
<body class="body_top">
<form method='post' action='immunization_inventory.php?t=<?php echo time(); ?>' onsubmit='return top.restoreSession()'>

<table border='0' cellpadding='3' width='100%'>
 <tr>
  <td>
   <?php echo xlt('Inventory Management'); ?>
  </td>
  <td align='right'>
<?php
// Build a drop-down list of facilities.
$query = "SELECT id, name FROM facility where inactive = 0 ORDER BY name";
$fres = sqlStatement($query);
echo "   <select name='form_facility' onchange='facchanged()'>\n";
echo "    <option value=''>-- " . xlt('All Facilities') . " --\n";
while ($frow = sqlFetchArray($fres)) {
    $facid = $frow['id'];
    // if ($is_user_restricted && !isFacilityAllowed($facid)) {
    //     continue;
    // }
    echo "    <option value='" . attr($facid) . "'";
    if ($facid == $form_facility) {
        echo " selected";
    }
    echo ">" . text($frow['name']) . "\n";
}
echo "   </select>\n";

// Build a drop-down list of warehouses.
echo "&nbsp;";
echo "   <select name='form_warehouse'>\n";
echo "    <option value=''>" . xlt('All Warehouses') . "</option>\n";
$lres = sqlStatement(
    "SELECT * FROM list_options " .
    "WHERE list_id = 'warehouse' AND activity = 1 ORDER BY seq, title"
);
while ($lrow = sqlFetchArray($lres)) {
    $whid  = $lrow['option_id'];
    $facid = $lrow['option_value'];
    // if ($is_user_restricted && !isWarehouseAllowed($facid, $whid)) {
    //     continue;
    // }
    echo "    <option value='" . attr("$whid/$facid") . "'";
    echo " id='fac" . attr($facid) . "'";
    if (strlen($form_warehouse)  > 0 && $whid == $form_warehouse) {
        echo " selected";
    }
    echo ">" . text(xl_list_label($lrow['title'])) . "</option>\n";
}
echo "   </select>\n";
?>
   &nbsp;
   <select name='form_consumable'>
<?php
foreach (
    array(
    '0' => xl('All Product Types'),
    '1' => xl('Consumable Only'),
    '2' => xl('Non-Consumable Only'),
    ) as $key => $value
) {
    echo "    <option value='" . attr($key) . "'";
    if ($key == $form_consumable) {
        echo " selected";
    }
    echo ">" . text($value) . "</option>\n";
}
?>
   </select>&nbsp;
  </td>
  <td>
   <input type='checkbox' name='form_show_empty' value='1'<?php if ($form_show_empty) {
        echo " checked";} ?> onchange='this.form.submit()' />
   <?php echo xlt('Show empty lots'); ?>
  </td>
  <td>
   <input type='submit' name='form_refresh' value="<?php echo xla('Refresh'); ?>" />
  </td>
 </tr>
 <tr>
  <td height="1">
  </td>
 </tr>
</table>

<div style="position:relative;">
        <div id="datatable-loader" style="text-align:center;">
  <div class="spinner"></div>
</div>

<!-- TODO: Why are we not using the BS4 table class here? !-->
<table id='mymaintable' class="table table-striped">
    <thead>
        <tr>
            <th><?php echo xlt('Name'); ?> </a></th>
            <th><?php echo xlt('Act'); ?></th>
            <th><?php echo xlt('NDC'); ?> </a></th>
            <th><?php echo xlt('Form'); ?> </a></th>
            <th><?php echo xlt('Size'); ?></th>
            <th title='<?php echo xlt('Measurement Units'); ?>'><?php echo xlt('Unit'); ?></th>
            <th title='<?php echo xla('Purchase or Transfer'); ?>'><?php echo xlt('Tran'); ?></th>
            <th><?php echo xlt('Lot'); ?> </a></th>
            <th><?php echo xlt('Stock'); ?></th>
            <th><?php echo xlt('Facility'); ?> </a></th>
            <th><?php echo xlt('Warehouse'); ?> </a></th>
            <th><?php echo xlt('QOH'); ?> </a></th>
            <th><?php echo xlt('Expires'); ?> </a></th>
        </tr>
    </thead>
 <tbody class="table-body" style ="display:none;">

<?php
// Restore merging logic: merge lots for the same drug into a single row

// Merge edit only lots into the main lot data for each drug
$prevRow = '';
while ($row = sqlFetchArray($res)) {
    // Warehouse restriction check disabled
    // if (!empty($row['inventory_id']) && $is_user_restricted && !isWarehouseAllowed($row['facid'], $row['warehouse_id'])) {
    //     continue;
    // }
    $row = processData($row);
    global $form_show_empty;
    if ($form_show_empty && (empty($row['inventory_id']) || $row['inventory_id'][0] === null)) {
        continue;
    }
    // If this is the first row for a drug, merge in any edit only lots for this drug
    if ($prevRow == '' || $prevRow['drug_id'] != $row['drug_id']) {
        if (!empty($editonly_lots[$row['drug_id']])) {
            foreach ($editonly_lots[$row['drug_id']] as $editrow) {
                $editrow = processData($editrow);
                $row = mergeData($row, $editrow);
            }
            unset($editonly_lots[$row['drug_id']]);
        }
    }
    if ($prevRow == '') {
        $prevRow = $row;
        continue;
    }
    if ($prevRow['drug_id'] == $row['drug_id']) {
        $row = mergeData($prevRow, $row);
    } else {
        mapToTable($prevRow);
    }
    $prevRow = $row;
}
// Also skip last row if show empty lots is checked and it has no empty lots
if (!($form_show_empty && (empty($prevRow['inventory_id']) || $prevRow['inventory_id'][0] === null))) {
    mapToTable($prevRow);
}

// Output any remaining editonly_lots for drugs not present in the main query
if (!empty($editonly_lots)) {
    foreach ($editonly_lots as $drug_id => $lots) {
        $row = null;
        foreach ($lots as $editrow) {
            $editrow = processData($editrow);
            if ($row === null) {
                $row = $editrow;
            } else {
                $row = mergeData($row, $editrow);
            }
        }
        if ($row !== null && !($form_show_empty && (empty($row['inventory_id']) || $row['inventory_id'][0] === null))) {
            mapToTable($row);
        }
    }
}
?>
 </tbody>
</table>
</div>

<input class="btn btn-primary btn-block w-25 mx-auto" type='button' value='<?php echo xla('Add Drug'); ?>' onclick='dodclick(0)' />

<input type="hidden" name="form_orderby" value="<?php echo attr($form_orderby) ?>" />

</form>

<script>
$(document).ready(function () {
//   $'(#datatable-loader').show(); // This should show spinner div

$('#datatable-loader').show().html(`
    <div class="spinner"></div>
    <p style="margin-top:10px;font-weight:bold;">Loading Immunization Inventory...</p>
`);

  $('#mymaintable').hide();
$('#mymaintable tbody').hide();

  setTimeout(() => {
    $('#mymaintable').DataTable({
      stripeClasses: ['stripe1', 'stripe2'],
      orderClasses: false,
      <?php require($GLOBALS['srcdir'] . '/js/xl/datatables-net.js.php'); ?>,
     initComplete: function () {
  $('#datatable-loader').fadeOut(300, function () {
    $('#mymaintable tbody').fadeIn(200); // fade in the body
    $('#mymaintable').fadeIn(200);       // fade in the whole table
  });
}
    });
  }, 200); // Small delay to ensure loader shows
facchanged();
    // Count and display <tr> and <td> in the table
    const trCount = $('#mymaintable tbody tr').length;
    let tdCount = 0;
    $('#mymaintable tbody tr').each(function() {
      tdCount += $(this).find('td').length;
    });
    // Count columns in the table header
    const colCount = $('#mymaintable thead th').length;
    // Show counts below the table
    // $('#mymaintable').after(`<div id='row-td-count' style='margin:10px 0;font-weight:bold;'>Row count: ${trCount}, TD count: ${tdCount}, Column count: ${colCount}</div>`);

});
</script>

</body>
</html>

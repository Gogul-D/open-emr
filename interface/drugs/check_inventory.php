<?php
$ignoreAuth = true;
$_GET['site'] = 'default';
require_once("../globals.php");

$sql = "SELECT di.on_hand FROM drug_inventory di JOIN drugs d ON di.drug_id = d.drug_id WHERE d.name = 'test drug 64'";
$result = sqlQuery($sql);

if ($result) {
    echo "On hand quantity for 'test drug 64': " . $result['on_hand'] . "\n";
} else {
    echo "Drug not found or no inventory record.\n";
}
?>
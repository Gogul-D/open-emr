<?php
require_once('globals.php');
$result = sqlQuery('SELECT on_hand FROM drug_inventory WHERE drug_id = 66');
if ($result) {
    echo 'Current inventory for drug 66: ' . $result['on_hand'] . PHP_EOL;
} else {
    echo 'No inventory record found for drug 66' . PHP_EOL;
}
?>
<?php
require_once('interface/globals.php');
$result = sqlQuery("SELECT drug_id, name FROM drugs WHERE name LIKE '%test drug 66%'");
if ($result) {
    echo 'Found drug: ID=' . $result['drug_id'] . ', Name=' . $result['name'] . PHP_EOL;
    
    // Check inventory
    $invResult = sqlQuery('SELECT on_hand FROM drug_inventory WHERE drug_id = ?', array($result['drug_id']));
    if ($invResult) {
        echo 'Current inventory: ' . $invResult['on_hand'] . PHP_EOL;
    } else {
        echo 'No inventory record found' . PHP_EOL;
    }
} else {
    echo 'Drug not found' . PHP_EOL;
}
?>
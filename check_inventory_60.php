<?php
require_once('interface/globals.php');
$result = sqlQuery('SELECT on_hand FROM drug_inventory WHERE drug_id = 60');
if ($result) {
    echo 'Current inventory for drug 60: ' . $result['on_hand'] . PHP_EOL;
} else {
    echo 'No inventory record found for drug 60' . PHP_EOL;
}

// Also check drug_sales to see if transaction was logged
$salesResult = sqlQuery('SELECT * FROM drug_sales WHERE drug_id = 60 ORDER BY sale_date DESC LIMIT 5');
if ($salesResult) {
    echo 'Recent sales for drug 60:' . PHP_EOL;
    do {
        echo '  Date: ' . $salesResult['sale_date'] . ', Quantity: ' . $salesResult['quantity'] . ', User: ' . $salesResult['user'] . PHP_EOL;
    } while ($salesResult = sqlFetchArray($salesResult));
} else {
    echo 'No sales records found for drug 60' . PHP_EOL;
}
?>
<?php
require_once('interface/globals.php');
$query = sqlStatement('SELECT id, drug, quantity, filled_date FROM prescriptions WHERE patient_id = 1 AND active = 1 ORDER BY date_added DESC LIMIT 5');
while ($row = sqlFetchArray($query)) {
    echo 'ID: ' . $row['id'] . ', Drug: ' . $row['drug'] . ', Quantity: ' . $row['quantity'] . ', Filled: ' . $row['filled_date'] . PHP_EOL;
}
?>
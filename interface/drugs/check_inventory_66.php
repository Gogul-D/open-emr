<?php
// Check inventory for test drug 66
$ignoreAuth = true;

// Set server variables for CLI execution
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_NAME'] = 'localhost';

require_once("../globals.php");
$_SESSION['site_id'] = 'default';

$sql = "SELECT d.drug_id, d.name, di.on_hand, di.inventory_id FROM drugs d LEFT JOIN drug_inventory di ON d.drug_id = di.drug_id WHERE d.name = 'test drug 66'";
$result = sqlStatement($sql);
echo "Inventory for 'test drug 66':\n";
while ($row = sqlFetchArray($result)) {
    print_r($row);
}

// Also check drug_sales
$salesSql = "SELECT * FROM drug_sales WHERE drug_id IN (SELECT drug_id FROM drugs WHERE name = 'test drug 66') ORDER BY sale_date DESC LIMIT 5";
$salesResult = sqlStatement($salesSql);
echo "\nRecent sales for 'test drug 66':\n";
while ($row = sqlFetchArray($salesResult)) {
    print_r($row);
}
?>

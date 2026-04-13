<?php
$ignoreAuth = true;
$_GET['site'] = 'default';
require_once("../globals.php");

$sql = "SELECT drug_id FROM drugs WHERE name = 'test drug 64'";
$result = sqlQuery($sql);

if ($result) {
    echo "Drug ID for 'test drug 64': " . $result['drug_id'] . "\n";
} else {
    echo "Drug not found.\n";
}
?>
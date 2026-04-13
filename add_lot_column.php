<?php
require_once("interface/globals.php");
sqlStatement("ALTER TABLE prescriptions ADD COLUMN lot_number VARCHAR(255) DEFAULT NULL");
echo "Added lot_number column to prescriptions table\n";
?>
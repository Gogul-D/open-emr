<?php
/**
 * Manual HL7 processor - can be run from command line or web
 */

require_once("../globals.php");

// Process inbound HL7 files
echo "Starting HL7 processing...\n";
require_once("process_hl7.php");
echo "HL7 processing completed.\n";
?>
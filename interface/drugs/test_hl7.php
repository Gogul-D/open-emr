<?php
// Set ignoreAuth to bypass authentication for testing
$ignoreAuth = true;

// Set site_id for testing
$_SESSION['site_id'] = 'default';

require_once("../globals.php");
require_once("../../controllers/C_Prescription.class.php");

// Create a mock prescription object
class MockPrescription {
    public $drug = 'ASPIRIN 325 MG TABLET';
    public $quantity = 10;
    public $patient;
    public $provider;
    private $id = 115;

    public function __construct() {
        $this->patient = (object)[
            'get_name_display' => function() { return 'TEST PATIENT'; },
            'date_of_birth' => '1980-09-01',
            'sex' => 'M',
            'get_id' => function() { return 123; }
        ];
        $this->provider = (object)[
            'get_lname' => 'SMITH',
            'get_fname' => 'JOHN'
        ];
    }

    public function get_id() { return $this->id; }
    public function set_id($id) { $this->id = $id; }
    public function get_drug_id() { return 3758; }
}

$prescription = new MockPrescription();
$controller = new C_Prescription();

echo "=== RDE Message ===\n";
$rde = $controller->generatePrescriptionHL7($prescription);
echo $rde . "\n\n";

echo "=== DFT Message ===\n";
$dft = $controller->generateDispenseHL7($prescription);
echo $dft . "\n";
?>
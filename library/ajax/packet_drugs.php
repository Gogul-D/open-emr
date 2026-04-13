<?php
/**
 * Fetch component drugs for a packet
 * 
 * @package OpenEMR
 * @link    http://www.open-emr.org
 */

require_once("../../interface/globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;

header('Content-Type: application/json');

// Verify CSRF token
if (!CsrfUtils::verifyCsrfToken($_GET['csrf_token_form'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token validation failed']);
    exit;
}

$drug_id = intval($_GET['drug_id'] ?? 0);

if (!$drug_id) {
    echo json_encode([]);
    exit;
}

// Verify this is a packet drug
$packet_check = sqlQuery(
    "SELECT is_packet FROM drugs WHERE drug_id = ?",
    array($drug_id)
);

if (!$packet_check || !$packet_check['is_packet']) {
    echo json_encode([]);
    exit;
}

// Get all component drugs for this packet
$component_drugs = array();
$result = sqlStatement(
    "SELECT drug_id, name, form, size, unit, route, dose, pack_frequency, quantity 
     FROM drugs 
     WHERE packet_id = ? AND is_packet = 0 
     ORDER BY drug_id",
    array($drug_id)
);

while ($row = sqlFetchArray($result)) {
    // Get display names for lookup fields
    $form_display = '';
    if (!empty($row['form'])) {
        $form_row = sqlQuery(
            "SELECT title FROM list_options WHERE list_id = 'drug_form' AND option_id = ? AND activity = 1",
            array($row['form'])
        );
        $form_display = $form_row ? $form_row['title'] : $row['form'];
    }
    
    $unit_display = '';
    if (!empty($row['unit'])) {
        $unit_row = sqlQuery(
            "SELECT title FROM list_options WHERE list_id = 'drug_units' AND option_id = ? AND activity = 1",
            array($row['unit'])
        );
        $unit_display = $unit_row ? $unit_row['title'] : $row['unit'];
    }
    
    $route_display = '';
    if (!empty($row['route'])) {
        $route_row = sqlQuery(
            "SELECT title FROM list_options WHERE list_id = 'drug_route' AND option_id = ? AND activity = 1",
            array($row['route'])
        );
        $route_display = $route_row ? $route_row['title'] : $row['route'];
    }
    
    $frequency_display = '';
    if (!empty($row['pack_frequency'])) {
        $freq_row = sqlQuery(
            "SELECT title FROM list_options WHERE list_id = 'drug_interval' AND option_id = ? AND activity = 1",
            array($row['pack_frequency'])
        );
        $frequency_display = $freq_row ? $freq_row['title'] : $row['pack_frequency'];
    }
    
    $component_drugs[] = array(
        'drug_id' => $row['drug_id'],
        'name' => $row['name'],
        'form' => $row['form'],
        'form_display' => $form_display,
        'size' => $row['size'],
        'unit' => $row['unit'],
        'unit_display' => $unit_display,
        'route' => $row['route'],
        'route_display' => $route_display,
        'dose' => $row['dose'],
        'pack_frequency' => $row['pack_frequency'],
        'frequency_display' => $frequency_display,
        'quantity' => $row['quantity']
    );
}

echo json_encode($component_drugs);
exit;

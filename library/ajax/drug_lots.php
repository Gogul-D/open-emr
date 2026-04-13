<?php

/**
 * AJAX endpoint to get available lots for a drug
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Custom Implementation
 * @copyright Copyright (c) 2026 Custom Implementation
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../../interface/globals.php");
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

// Verify CSRF token
if (!CsrfUtils::verifyCsrfToken($_GET["csrf_token_form"])) {
    CsrfUtils::csrfNotVerified();
}

$drug_id = $_GET['drug_id'] ?? 0;

if (!$drug_id) {
    echo json_encode([]);
    exit;
}

$lots = [];

// Get available lots for this drug, ordered by expiration date (soonest first)
// Exclude lots where stock/vendor is "Pharmacy Only"
$sql = "SELECT di.lot_number, di.expiration, di.on_hand, di.availability 
        FROM drug_inventory di
        LEFT JOIN users u ON di.vendor_id = u.id
        WHERE di.drug_id = ? 
        AND di.on_hand > 0 
        AND di.destroy_date IS NULL 
        AND (LOWER(TRIM(u.organization)) != 'pharmacy only' OR u.id IS NULL)
        ORDER BY di.expiration ASC, di.lot_number ASC";

$result = sqlStatement($sql, [$drug_id]);

while ($row = sqlFetchArray($result)) {
    $lots[] = [
        'lot_number' => $row['lot_number'],
        'expiration' => $row['expiration'] ? date('m/d/Y', strtotime($row['expiration'])) : 'No Exp',
        'on_hand' => $row['on_hand'],
        'availability' => $row['availability'] ?? $row['on_hand']
    ];
}

echo json_encode($lots);
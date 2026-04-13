<?php

/**
 * Save SOAP Template
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 */

require_once("../../globals.php");
require_once("$srcdir/api.inc.php");
require_once("$srcdir/patient.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

// Verify CSRF
if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
    CsrfUtils::csrfNotVerified();
}

$response = array('success' => false);

try {
    // Debug: Log received data
    error_log("Save template POST data: " . print_r($_POST, true));
    
    // Validate required fields
    $required_fields = array('name', 'context', 'category', 'content');
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Sanitize inputs
    $template_name = trim($_POST['name']);
    $context = trim($_POST['context']);
    $category = trim($_POST['category']);
    $content = trim($_POST['content']);
    $created_by = $_SESSION['authUserID']; // Get current user ID

    // Insert into soap_custom_templates table with all fields
    $sql = "INSERT INTO soap_custom_templates (
                template_name, 
                context, 
                category, 
                template_content, 
                created_by,
                created_date,
                is_public,
                is_active
            ) VALUES (?, ?, ?, ?, ?, NOW(), 1, 1)";
    
    $result = sqlStatement($sql, array(
        $template_name,
        $context,
        $category,
        $content,
        $created_by
    ));
    
    if ($result) {
        $response['success'] = true;
        $response['message'] = 'Template saved successfully';
    } else {
        throw new Exception("Error saving template to database");
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
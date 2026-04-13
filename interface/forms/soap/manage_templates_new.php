<?php
/**
 * Custom Templates Management API for SOAP Form
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . "/../../globals.php");
require_once("$srcdir/api.inc.php");
require_once("$srcdir/sql.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

// Function to handle errors
function returnError($message, $code = 500) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Function to create templates table
function createCustomTemplatesTable() {
    $sql = "CREATE TABLE IF NOT EXISTS `soap_custom_templates` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `context` varchar(255) NOT NULL,
        `category` varchar(255) NOT NULL,
        `template_name` varchar(255) NOT NULL,
        `template_content` text NOT NULL,
        `created_by` int(11) NOT NULL,
        `created_date` datetime DEFAULT CURRENT_TIMESTAMP,
        `is_public` tinyint(1) DEFAULT 1,
        `is_active` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_context_category` (`context`, `category`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    sqlStatement($sql);
}

// Function to save template
function saveCustomTemplate() {
    try {
        while (ob_get_level()) ob_end_clean();
        
        // Get and validate input
        $context = trim($_POST['context'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $template_name = trim($_POST['template_name'] ?? '');
        $template_content = trim($_POST['template_content'] ?? '');
        $is_public = (bool)($_POST['is_public'] ?? true);
        
        // Validation
        if (empty($context) || empty($category) || empty($template_name) || empty($template_content)) {
            returnError('All fields are required', 400);
        }
        
        // Insert template
        createCustomTemplatesTable();
        $result = sqlStatement(
            "INSERT INTO soap_custom_templates (context, category, template_name, template_content, created_by, is_public) 
             VALUES (?, ?, ?, ?, ?, ?)",
            array($context, $category, $template_name, $template_content, $_SESSION['authUserID'], $is_public ? 1 : 0)
        );
        
        if (!$result) {
            returnError('Failed to save template', 500);
        }

        // Return success
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Template saved successfully',
            'template_id' => sqlInsertId()
        ]);
        exit();
        
    } catch (Exception $e) {
        returnError($e->getMessage(), 500);
    }
}

// Main execution
try {
    // Clean output buffer
    while (ob_get_level()) ob_end_clean();
    ob_start();

    // Handle GET requests for the form
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['popup']) && $_GET['popup'] === '1') {
            include(__DIR__ . '/templates/manage_templates_form.php');
            exit();
        }
        returnError('Invalid request method', 400);
    }

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            returnError('CSRF validation failed', 403);
        }

        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'save_template':
                saveCustomTemplate();
                break;
            default:
                returnError('Invalid action', 400);
        }
    }

    returnError('Invalid request method', 400);

} catch (Exception $e) {
    returnError($e->getMessage(), 500);
}
<?php
/**
 * Get Template Categories and Contexts
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 */

require_once("../../globals.php");
require_once("$srcdir/api.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

// Verify CSRF
if (!isset($_GET["csrf_token_form"]) || !CsrfUtils::verifyCsrfToken($_GET["csrf_token_form"])) {
    $response['success'] = false;
    $response['message'] = 'Invalid CSRF token';
    $response['debug'] = array(
        'csrf_provided' => isset($_GET["csrf_token_form"]),
        'action' => $_GET['action'] ?? 'none'
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$response = array('success' => false);

try {
    // First verify the table exists
    $tableCheck = sqlQuery("SHOW TABLES LIKE 'soap_custom_templates'");
    if (!$tableCheck) {
        throw new Exception('Template table does not exist');
    }
    
    // First verify the table exists and has data
    $checkSql = "SELECT COUNT(*) as count FROM soap_custom_templates WHERE is_active = 1";
    $checkResult = sqlQuery($checkSql);
  
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_contexts':
            // Get all unique contexts
            $sql = "SELECT DISTINCT context FROM soap_custom_templates WHERE is_active = 1 ORDER BY context";
            $result = sqlStatement($sql);
            $contexts = array();
            while ($row = sqlFetchArray($result)) {
                $contexts[] = $row['context'];
            }
            $response['contexts'] = $contexts;
            $response['success'] = true;
            break;
            
        case 'get_categories':
            // Get categories for a specific context
            $context = trim($_GET['context'] ?? '');
            if ($context) {
                $sql = "SELECT DISTINCT category FROM soap_custom_templates WHERE context = ? AND is_active = 1 ORDER BY category";
                $result = sqlStatement($sql, array($context));
            } else {
                $sql = "SELECT DISTINCT category FROM soap_custom_templates WHERE is_active = 1 ORDER BY category";
                $result = sqlStatement($sql);
            }
            $categories = array();
            while ($row = sqlFetchArray($result)) {
                $categories[] = $row['category'];
            }
            $response['categories'] = $categories;
            $response['success'] = true;
            break;
            
        case 'get_templates':
            // Get templates for context and/or category
            $context = trim($_GET['context'] ?? '');
            $category = trim($_GET['category'] ?? '');
            
            $params = array();
            $where = "is_active = 1";
            
            if ($context) {
                $where .= " AND context = ?";
                $params[] = $context;
            }
            if ($category) {
                $where .= " AND category = ?";
                $params[] = $category;
            }
            
      
            
            // For debugging, try a simple query first
            $testSql = "SELECT COUNT(*) as cnt FROM soap_custom_templates WHERE is_active = 1";
            $testResult = sqlQuery($testSql);
 
            
            // Try the same pattern as get_categories
            if ($context && $category) {
                $sql = "SELECT id, template_name as name, context, category, created_by FROM soap_custom_templates WHERE context = ? AND category = ? AND is_active = 1 ORDER BY template_name";
                $result = sqlStatement($sql, array($context, $category));
            } elseif ($context) {
                $sql = "SELECT id, template_name as name, context, category, created_by FROM soap_custom_templates WHERE context = ? AND is_active = 1 ORDER BY template_name";
                $result = sqlStatement($sql, array($context));
            } else {
                $sql = "SELECT id, template_name as name, context, category, created_by FROM soap_custom_templates WHERE is_active = 1 ORDER BY template_name";
                $result = sqlStatement($sql);
            }
            
       
            
            if (!$result) {
               
                throw new Exception('Database query failed: ' . $sql);
            }
            
            $templates = array();
            
            // Get current user ID for admin check
            $currentUser = $_SESSION['authUserID'] ?? null;
      
            
            while ($row = sqlFetchArray($result)) {
                // Check if user can delete this template (admin or creator)
                $canDelete = false;
                if ($currentUser) {
                    // Check if user is admin using proper ACL query
                    $isAdmin = false;
                    try {
                        $login_user_id = $currentUser;
                        $roleQuery = sqlQuery("SELECT GROUP_CONCAT(gacl_aro_groups.name ORDER BY gacl_aro_groups.name ASC) AS role FROM users JOIN gacl_aro ON gacl_aro.value = users.username JOIN gacl_groups_aro_map ON gacl_groups_aro_map.aro_id = gacl_aro.id JOIN gacl_aro_groups ON gacl_aro_groups.id = gacl_groups_aro_map.group_id WHERE users.id = ?", array($login_user_id));
                        
                        if ($roleQuery && !empty($roleQuery['role'])) {
                            $userRoles = explode(',', $roleQuery['role']);
                            // Check for admin roles
                            $isAdmin = in_array('admin', $userRoles) || in_array('super', $userRoles) || in_array('administrator', $userRoles);
                       
                        } else {
                            error_log("[Template Debug] No roles found for user $currentUser");
                        }
                    } catch (Exception $e) {
                  
                        $isAdmin = false;
                    }
                    
                    $canDelete = $isAdmin || ($row['created_by'] == $currentUser);
                }
                
                $templates[] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'context' => $row['context'],
                    'category' => $row['category'],
                    'can_delete' => $canDelete
                );
            }
            $response['templates'] = $templates;
            $response['success'] = true;
            break;
            
        case 'get_template':
            // Get a specific template by ID
            $templateId = intval($_GET['id'] ?? 0);
            if ($templateId > 0) {
                $sql = "SELECT id, template_name as name, context, category, template_content as content FROM soap_custom_templates WHERE id = ? AND is_active = 1";
                $result = sqlQuery($sql, array($templateId));
                if ($result) {
                    $response['template'] = $result;
                    $response['success'] = true;
                } else {
                    $response['message'] = 'Template not found';
                }
            } else {
                $response['message'] = 'Invalid template ID';
            }
            break;
            
        default:
            // Legacy support - original behavior
            // Get contexts
            if (!isset($_GET['context']) || $_GET['context'] === '') {
                $sql = "SELECT DISTINCT context FROM soap_custom_templates WHERE is_active = 1 ORDER BY context";
                $result = sqlStatement($sql);
                $contexts = array();
                while ($row = sqlFetchArray($result)) {
                    $contexts[] = $row['context'];
                   
                }
                $response['contexts'] = $contexts;
                $response['contextCount'] = count($contexts);
           
            }
            // Get categories for a context
            else if (!isset($_GET['category']) || $_GET['category'] === '') {
                $context = trim($_GET['context']);
                $sql = "SELECT DISTINCT category FROM soap_custom_templates WHERE context = ? AND is_active = 1 ORDER BY category";
                $result = sqlStatement($sql, array($context));
                $categories = array();
                while ($row = sqlFetchArray($result)) {
                    $categories[] = $row['category'];
                   
                }
                $response['categories'] = $categories;
                $response['categoryCount'] = count($categories);
              
            }
            // Get templates for a context and category
            else {
                $context = trim($_GET['context']);
                $category = trim($_GET['category']);
                $sql = "SELECT id, template_name, template_content FROM soap_custom_templates WHERE context = ? AND category = ? AND is_active = 1 ORDER BY template_name";
                $result = sqlStatement($sql, array($context, $category));
                $templates = array();
                while ($row = sqlFetchArray($result)) {
                    $templates[] = $row;
                   
                }
                $response['templates'] = $templates;
                $response['templateCount'] = count($templates);

            }
            
            $response['success'] = true;
            break;
    }
    
    $response['debug'] = array(
        'action' => $action,
        'params' => $_GET,
        'totalTemplates' => ($checkResult ? $checkResult['count'] : 0),
        'tableExists' => ($tableCheck ? true : false)
    );

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
  
}

// Return JSON response
header('Content-Type: application/json');

// Suppress any PHP errors/warnings that might break JSON
error_reporting(0);
ini_set('display_errors', 0);

// Clean any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Start fresh output buffer
ob_start();

echo json_encode($response);

// Ensure only JSON is sent
ob_end_flush();
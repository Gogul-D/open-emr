<?php
/**
 * Custom Templates Management API for SOAP Form
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Calculate the correct path to globals.php
$GLOBALS['rootdir'] = dirname(__FILE__) . "/../../../interface";
$GLOBALS['srcdir'] = dirname(__FILE__) . "/../../../library";
$GLOBALS['webroot'] = '/phix-openemr';

// Include required globals
if (!defined('OPENEMR_DIRECT_INCLUDE')) {
    $ignoreAuth = false;
    require_once(dirname(__FILE__) . "/../../../interface/globals.php");
}

// Required OpenEMR files - include these before any session handling
require_once(dirname(__FILE__) . "/../../../library/api.inc");
require_once(dirname(__FILE__) . "/../../../library/forms.inc");
require_once(dirname(__FILE__) . "/../../../library/options.inc.php");
require_once(dirname(__FILE__) . "/../../../library/patient.inc");
require_once(dirname(__FILE__) . "/../../../library/report.inc");
require_once(dirname(__FILE__) . "/../../../library/classes/Document.class.php");
require_once(dirname(__FILE__) . "/../../../library/classes/Note.class.php");

// Set site ID from facility if not set
if (empty($_SESSION['site_id'])) {
    $facilityService = new \Services\FacilityService();
    $facility = $facilityService->getPrimaryBusinessEntity();
    if ($facility) {
        $_SESSION['site_id'] = $facility['id'];
        $_SESSION['site_facility_name'] = $facility['name'];
    } else {
        // Fallback to default if no facility found
        $_SESSION['site_id'] = 'default';
        $_SESSION['site_facility_name'] = 'Default Facility';
    }
}

// Ensure site ID is set
if (empty($_SESSION['site_id'])) {
    // Default to the main site if there's only one
    $res = sqlStatement("SELECT * FROM `sites` WHERE `id` = ?", array('default'));
    if ($row = sqlFetchArray($res)) {
        $_SESSION['site_id'] = $row['site_id'];
    } else {
        // If no default site, use the first available site
        $res = sqlStatement("SELECT * FROM `sites` ORDER BY `site_id` LIMIT 1");
        if ($row = sqlFetchArray($res)) {
            $_SESSION['site_id'] = $row['site_id'];
        } else {
            $_SESSION['site_id'] = 'default';
        }
    }
}

// Required OpenEMR files
require_once($GLOBALS['srcdir'] . "/sql.inc.php");
require_once($GLOBALS['srcdir'] . "/sqlconf.php");
require_once($GLOBALS['srcdir'] . "/formatting.inc.php");
require_once($GLOBALS['srcdir'] . "/options.inc.php");
require_once($GLOBALS['srcdir'] . "/api.inc");

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verify and initialize session requirements
if (!isset($_SESSION['authProvider'])) {
    $_SESSION['authProvider'] = 0;
}
if (!isset($_SESSION['authId'])) {
    $_SESSION['authId'] = '';
}
// ini_set('display_errors', 1);

require_once(__DIR__ . "/../../globals.php");
require_once("$srcdir/api.inc.php");
require_once("$srcdir/sql.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

// Function to handle errors and return JSON response
function returnError($message, $code = 500) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Create templates table if it doesn't exist
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

// Delete a custom template
function deleteCustomTemplate($templateId) {
    // Ensure table exists
    try {
        createCustomTemplatesTable();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create templates table: ' . $e->getMessage()]);
        return;
    }
    
    // Validate CSRF token
    // Temporarily disabled for testing
    /*
    try {
        if (!isset($_POST['csrf_token_form']) || !CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            return;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'CSRF validation error: ' . $e->getMessage()']);
        return;
    }
    */
    
    $templateId = intval($templateId);
    if ($templateId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid template ID']);
        return;
    }
    
    // Check if user has permission to delete (admin or creator)
    $currentUser = $_SESSION['authUserID'] ?? null;
    if (!$currentUser) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        return;
    }
    
    // Check if user is admin
    $isAdmin = false;
    try {
        $login_user_id = $currentUser;
        $roleQuery = sqlQuery("SELECT GROUP_CONCAT(gacl_aro_groups.name ORDER BY gacl_aro_groups.name ASC) AS role FROM users JOIN gacl_aro ON gacl_aro.value = users.username JOIN gacl_groups_aro_map ON gacl_groups_aro_map.aro_id = gacl_aro.id JOIN gacl_aro_groups ON gacl_aro_groups.id = gacl_aro_groups.id = gacl_groups_aro_map.group_id WHERE users.id = ?", array($login_user_id));
        
        if ($roleQuery && !empty($roleQuery['role'])) {
            $userRoles = explode(',', $roleQuery['role']);
            // Check for admin roles
            $isAdmin = in_array('admin', $userRoles) || in_array('super', $userRoles) || in_array('administrator', $userRoles);
        }
    } catch (Exception $e) {
        // Ignore errors, assume not admin
    }
    
    // Get template info
    try {
        $template = sqlQuery("SELECT created_by FROM soap_custom_templates WHERE id = ? AND is_active = 1", array($templateId));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        return;
    }
    if (!$template) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Template not found']);
        return;
    }
    
    // Check permissions
    if (!$isAdmin && $template['created_by'] != $currentUser) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this template']);
        return;
    }
    
    // Soft delete the template
    try {
        $result = sqlStatement("UPDATE soap_custom_templates SET is_active = 0 WHERE id = ?", array($templateId));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        return;
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Template deleted successfully']);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete template']);
        exit;
    }
}

// Save a new template
function saveCustomTemplate() {
    try {
        while (ob_get_level()) ob_end_clean();
        
        createCustomTemplatesTable();
        
        $context = trim($_POST['context'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $template_name = trim($_POST['template_name'] ?? '');
        $template_content = trim($_POST['template_content'] ?? '');
        $is_public = (bool)($_POST['is_public'] ?? true);
        
        if (empty($context) || empty($category) || empty($template_name) || empty($template_content)) {
            returnError('All fields are required', 400);
        }
        
        if (!isset($_SESSION['authUserID'])) {
            returnError('User session not found', 401);
        }
        
        $result = sqlStatement(
            "INSERT INTO soap_custom_templates (context, category, template_name, template_content, created_by, is_public) 
             VALUES (?, ?, ?, ?, ?, ?)",
            array($context, $category, $template_name, $template_content, $_SESSION['authUserID'], $is_public ? 1 : 0)
        );
        
        if (!$result) {
            returnError('Failed to save template', 500);
        }
        
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

// Render the popup form
function renderPopupForm() {
    $preselectedContext = $_GET['context'] ?? '';
    $preselectedCategory = $_GET['category'] ?? '';
    $csrfToken = CsrfUtils::collectCsrfToken();
    
    // Start HTML output
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Custom Template</title>
    <link rel="stylesheet" href="<?php echo $GLOBALS['webroot']; ?>/public/assets/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo $GLOBALS['webroot']; ?>/public/assets/font-awesome/css/font-awesome.min.css">
    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .popup-container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 1rem; }
        .suggestion-badges {
            margin-top: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .badge {
            cursor: pointer;
            padding: 5px 10px;
            background: #e9ecef;
            color: #495057;
            transition: all 0.2s;
        }
        .badge:hover {
            background: #007bff !important;
            color: white !important;
        }
        #new-template-content {
            resize: vertical;
            min-height: 150px;
        }
        .btn-group { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="popup-container">
        <h4 class="mb-4">
            <i class="fa fa-plus-circle text-primary"></i> Create Custom Template
        </h4>

        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            Create new templates for your SOAP notes. Select an existing context or create a new one.
        </div>

        <form id="template-form" onsubmit="return false">
            <input type="hidden" name="csrf_token_form" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="new-context">Context <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new-context" name="context"
                               value="<?php echo htmlspecialchars($preselectedContext, ENT_QUOTES); ?>" required>
                        <div id="context-suggestions" class="suggestion-badges"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="new-category">Category <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new-category" name="category"
                               value="<?php echo htmlspecialchars($preselectedCategory, ENT_QUOTES); ?>" required>
                        <div id="category-suggestions" class="suggestion-badges"></div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="new-template-name">Template Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="new-template-name" name="template_name" required>
            </div>

            <div class="form-group">
                <label for="new-template-content">Template Content <span class="text-danger">*</span></label>
                <textarea class="form-control" id="new-template-content" name="template_content" rows="6" required></textarea>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="is-public" name="is_public" checked>
                <label class="form-check-label" for="is-public">
                    Make this template available to all users
                </label>
            </div>

            <div class="btn-group">
                <button type="button" class="btn btn-primary" id="save-template-btn">
                    <i class="fa fa-save"></i> Save Template
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.close()">
                    <i class="fa fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>

    <script src="<?php echo $GLOBALS['webroot']; ?>/public/assets/jquery/dist/jquery.min.js"></script>
    <script>
        // Template suggestions data
        const predefinedContexts = [
            'EXPRESS VISITS', 'TOC MSM', 'RESULTS NORMAL', 'RESULTS ABNORMAL',
            'FEMALE CONDITIONS', 'EXPOSURE TREATMENTS', 'FOLLOW UP VISITS'
        ];

        const contextCategories = {
            'EXPRESS VISITS': ['FEMALE Express', 'MALE Express', 'MSM Express'],
            'RESULTS NORMAL': ['Female Results', 'Male Results', 'MSM Results'],
            'RESULTS ABNORMAL': ['CT Treatment', 'GC Treatment', 'Syphilis Treatment']
        };

        function setupAutocompleteSuggestions() {
            setupContextSuggestions();
            setupCategorySuggestions();
        }

        function setupContextSuggestions() {
            const contextInput = document.getElementById('new-context');
            const contextSuggestions = document.getElementById('context-suggestions');
            
            contextInput.addEventListener('input', function() {
                const value = this.value.toUpperCase();
                contextSuggestions.innerHTML = '';
                
                if (value) {
                    const matches = predefinedContexts.filter(context =>
                        context.toUpperCase().includes(value)
                    ).slice(0, 5);

                    matches.forEach(match => {
                        const badge = document.createElement('span');
                        badge.className = 'badge';
                        badge.textContent = match;
                        badge.onclick = () => {
                            contextInput.value = match;
                            contextSuggestions.innerHTML = '';
                            updateCategorySuggestions(match);
                        };
                        contextSuggestions.appendChild(badge);
                    });
                }
            });
        }

        function setupCategorySuggestions() {
            const categoryInput = document.getElementById('new-category');
            const categorySuggestions = document.getElementById('category-suggestions');
            
            categoryInput.addEventListener('input', function() {
                const context = document.getElementById('new-context').value;
                const value = this.value.toUpperCase();
                categorySuggestions.innerHTML = '';
                
                if (value) {
                    const suggestions = contextCategories[context] || [];
                    const matches = suggestions.filter(category =>
                        category.toUpperCase().includes(value)
                    ).slice(0, 5);

                    matches.forEach(match => {
                        const badge = document.createElement('span');
                        badge.className = 'badge';
                        badge.textContent = match;
                        badge.onclick = () => {
                            categoryInput.value = match;
                            categorySuggestions.innerHTML = '';
                        };
                        categorySuggestions.appendChild(badge);
                    });
                }
            });
        }

        function saveTemplate() {
            const form = document.getElementById('template-form');
            const formData = new FormData(form);
            formData.append('action', 'save_template');

            // Validate form
            const requiredFields = ['context', 'category', 'template_name', 'template_content'];
            for (const field of requiredFields) {
                if (!formData.get(field).trim()) {
                    alert('Please fill in all required fields');
                    return;
                }
            }

            // Disable save button
            const saveBtn = document.getElementById('save-template-btn');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
            saveBtn.disabled = true;

            // Send request
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const text = await response.text();
                console.log('Raw server response:', text); // Debug log
                console.log('Response headers:', 
                    Array.from(response.headers.entries())
                        .map(([key, value]) => `${key}: ${value}`)
                        .join('\n')
                ); // Debug log
                
                let data;
                try {
                    data = JSON.parse(text);
                    console.log('Parsed JSON data:', data); // Debug log
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text:', text);
                    console.error('Response status:', response.status);
                    throw new Error('Server returned invalid JSON response. Check console for details.');
                }
                
                if (!response.ok) {
                    throw new Error(data.error || `Server error: ${response.status}`);
                }
                
                if (!data.success) {
                    throw new Error(data.error || 'Operation was not successful');
                }
                
                // Notify parent window
                if (window.opener) {
                    window.opener.postMessage({
                        type: 'TEMPLATE_SAVED',
                        context: formData.get('context'),
                        category: formData.get('category')
                    }, window.location.origin);
                }
                
                alert('Template saved successfully!');
                window.close();
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'An unexpected error occurred');
            })
            .finally(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            setupAutocompleteSuggestions();
            
            const saveBtn = document.getElementById('save-template-btn');
            saveBtn.addEventListener('click', saveTemplate);

            // Focus first empty field
            const contextInput = document.getElementById('new-context');
            const categoryInput = document.getElementById('new-category');
            
            if (!contextInput.value) contextInput.focus();
            else if (!categoryInput.value) categoryInput.focus();
            else document.getElementById('new-template-name').focus();
        });
    </script>
</body>
</html><?php
    exit();
}

/**
 * Get all custom contexts
 */
function getCustomContexts() {
    createCustomTemplatesTable();
    
    $sql = "SELECT DISTINCT context FROM soap_custom_templates WHERE is_active = 1";
    if (!($_SESSION['authUserID'] ?? false)) {
        $sql .= " AND is_public = 1";
    }
    $sql .= " ORDER BY context";
    
    $result = sqlStatement($sql);
    $contexts = [];
    
    while ($row = sqlFetchArray($result)) {
        $contexts[] = $row['context'];
    }
    
    echo json_encode(['contexts' => $contexts]);
}

/**
 * Get custom categories for a context
 */
function getCustomCategories($context) {
    createCustomTemplatesTable();
    
    $sql = "SELECT DISTINCT category FROM soap_custom_templates WHERE context = ? AND is_active = 1";
    $params = [$context];
    
    if (!($_SESSION['authUserID'] ?? false)) {
        $sql .= " AND is_public = 1";
    }
    $sql .= " ORDER BY category";
    
    $result = sqlStatement($sql, $params);
    $categories = [];
    
    while ($row = sqlFetchArray($result)) {
        $categories[] = $row['category'];
    }
    
    echo json_encode(['categories' => $categories]);
}

// Main execution
try {
    // Clean output buffer
    while (ob_get_level()) ob_end_clean();
    ob_start();

    // Handle OPTIONS requests for CORS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        exit;
    }

    // Handle GET requests for the form
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['popup']) && $_GET['popup'] === '1') {
            renderPopupForm();
            exit();
        }
        returnError('Invalid request', 400);
    }

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
            returnError('CSRF validation failed', 403);
        }

        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'save_template':
                saveCustomTemplate();
                break;
            case 'get_custom_contexts':
                getCustomContexts();
                break;
            case 'get_custom_categories':
                getCustomCategories($_POST['context'] ?? '');
                break;
            case 'delete_template':
                deleteCustomTemplate($_POST['template_id'] ?? '');
                break;
            default:
                returnError('Invalid action', 400);
        }
    }

    returnError('Invalid request method', 400);

} catch (Exception $e) {
    returnError($e->getMessage(), 500);
}
?>

// Function to handle errors and return JSON response
function returnError($message, $code = 500) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
   
    exit;
}

// Clean any previous output
if (ob_get_level()) ob_end_clean();
// Start fresh output buffer
ob_start();

// Set content type
header('Content-Type: application/json');

// Verify CSRF token
if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"] ?? '')) {
    returnError('CSRF validation failed', 403);
}

// Get action
$action = $_POST['action'] ?? '';

try {
   
    
    // Check for required globals.php functions
    if (!function_exists('sqlStatement') || !function_exists('sqlQuery')) {
        throw new Exception('Required database functions not available. Check globals.php inclusion.');
    }
    
    // Check if session is active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    
    
    switch ($action) {
        case 'save_template':
            saveCustomTemplate();
            break;
        case 'get_custom_contexts':
            getCustomContexts();
            break;
        case 'get_custom_categories':
            getCustomCategories($_POST['context'] ?? '');
            break;
        case 'delete_template':
            deleteCustomTemplate($_POST['template_id'] ?? '');
            break;
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    $trace = $e->getTraceAsString();
 
    http_response_code(500);
    echo json_encode(['error' => $errorMsg, 'trace' => $trace]);
}
exit;

ob_end_flush();

/**
 * Create the custom templates table if it doesn't exist
 */
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
        KEY `idx_context_category` (`context`, `category`),
        KEY `idx_created_by` (`created_by`),
        KEY `idx_is_public_active` (`is_public`, `is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    sqlStatement($sql);
}

/**
 * Save a new custom template
 */
function saveCustomTemplate() {
    // Ensure we start with a clean slate and set proper headers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Prevent any PHP errors from being output
    ini_set('display_errors', '0');
    error_reporting(0);
    
    // Set proper headers
    header('Content-Type: application/json; charset=utf-8');
    
    try {
    
        
        // Ensure table exists
        createCustomTemplatesTable();
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token_form']) || !CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'])) {
            throw new Exception('Invalid security token');
        }
        
        $context = trim($_POST['context'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $template_name = trim($_POST['template_name'] ?? '');
        $template_content = trim($_POST['template_content'] ?? '');
        $is_public = (bool)($_POST['is_public'] ?? true);
        

        
        // Validation
        if (empty($context) || empty($category) || empty($template_name) || empty($template_content)) {
            throw new Exception('All fields are required');
        }
    
        // Check if template already exists
        $existing = sqlQuery(
            "SELECT id FROM soap_custom_templates WHERE context = ? AND category = ? AND template_name = ? AND is_active = 1",
            [$context, $category, $template_name]
        );
        
        if ($existing) {
            throw new Exception('A template with this name already exists in this category');
        }
        
        // Check for valid user session
        if (!isset($_SESSION['authUserID'])) {

            throw new Exception('User session not found. Please log in again.');
        }

        // Insert new template
        $result = sqlStatement(
            "INSERT INTO soap_custom_templates (context, category, template_name, template_content, created_by, is_public) VALUES (?, ?, ?, ?, ?, ?)",
            [$context, $category, $template_name, $template_content, $_SESSION['authUserID'], $is_public ? 1 : 0]
        );
        
        if (!$result) {
            $dbError = sqlGetLastError();

            throw new Exception("Failed to save template: Database error");
        }
        
        $newId = sqlLastInsertId();
       
        
        echo json_encode([
            'success' => true,
            'message' => 'Template saved successfully',
            'template_id' => $newId
        ]);
    } catch (Exception $e) {
    
        http_response_code(400);
        
        $response = [
            'success' => false,
            'error' => $e->getMessage(),
            'debug_info' => [
                'time' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'session_status' => session_status()
            ]
        ];
        
        // Ensure clean output
        if (ob_get_level()) ob_end_clean();
        echo json_encode($response);
    } catch (Exception $e) {
        
        
        // Clear any previous output
        if (ob_get_level()) ob_end_clean();
        
        // Send error response
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

/**
 * Delete a custom template
 */
function deleteCustomTemplate($templateId) {
    // Ensure table exists
    try {
        createCustomTemplatesTable();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create templates table: ' . $e->getMessage()]);
        return;
    }
    
    // Validate CSRF token
    // Temporarily disabled for testing
    /*
    try {
        if (!isset($_POST['csrf_token_form']) || !CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            return;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'CSRF validation error: ' . $e->getMessage()]);
        return;
    }
    */
    
    $templateId = intval($templateId);
    if ($templateId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid template ID']);
        return;
    }
    
    // Check if user has permission to delete (admin or creator)
    $currentUser = $_SESSION['authUserID'] ?? null;
    if (!$currentUser) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        return;
    }
    
    // Check if user is admin
    $isAdmin = false;
    try {
        $login_user_id = $currentUser;
        $roleQuery = sqlQuery("SELECT GROUP_CONCAT(gacl_aro_groups.name ORDER BY gacl_aro_groups.name ASC) AS role FROM users JOIN gacl_aro ON gacl_aro.value = users.username JOIN gacl_groups_aro_map ON gacl_groups_aro_map.aro_id = gacl_aro.id JOIN gacl_aro_groups ON gacl_aro_groups.id = gacl_groups_aro_map.group_id WHERE users.id = ?", array($login_user_id));
        
        if ($roleQuery && !empty($roleQuery['role'])) {
            $userRoles = explode(',', $roleQuery['role']);
            // Check for admin roles
            $isAdmin = in_array('admin', $userRoles) || in_array('super', $userRoles) || in_array('administrator', $userRoles);
        }
    } catch (Exception $e) {
        // Ignore errors, assume not admin
    }
    
    // Get template info
    try {
        $template = sqlQuery("SELECT created_by FROM soap_custom_templates WHERE id = ? AND is_active = 1", array($templateId));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        return;
    }
    if (!$template) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Template not found']);
        return;
    }
    
    // Check permissions
    if (!$isAdmin && $template['created_by'] != $currentUser) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this template']);
        return;
    }
    
    // Soft delete the template
    try {
        $result = sqlStatement("UPDATE soap_custom_templates SET is_active = 0 WHERE id = ?", array($templateId));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        return;
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Template deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete template']);
    }
}

/**
 * Render the popup form for template creation
 */
function renderPopupForm() {
    require_once(__DIR__ . "/../../globals.php");
    require_once("$srcdir/api.inc.php");
    require_once("$srcdir/sql.inc.php");

    $preselectedContext = $_GET['context'] ?? '';
    $preselectedCategory = $_GET['category'] ?? '';
    $csrfToken = \OpenEMR\Common\Csrf\CsrfUtils::collectCsrfToken();

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Create Custom Template</title>
        <link href="../../../public/assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background: #f8f9fa;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            .popup-container {
                padding: 20px;
                max-width: 700px;
                margin: 0 auto;
            }
            .form-group {
                margin-bottom: 1rem;
            }
            .suggestion-badges {
                margin-top: 5px;
            }
            .badge {
                cursor: pointer;
                margin: 2px;
                transition: all 0.2s;
            }
            .badge:hover {
                background-color: #007bff !important;
                color: white !important;
            }
            #new-template-content {
                resize: vertical;
                min-height: 150px;
                max-height: 300px;
            }
            .btn-group {
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="popup-container">
            <h4 class="mb-4">
                <i class="fa fa-plus-circle text-primary"></i> Create Custom Template
            </h4>

            <div class="alert alert-info alert-sm">
                <i class="fa fa-info-circle"></i>
                <strong>Create Your Own Templates:</strong>
                Create new contexts and categories, or add to existing ones.
            </div>

            <form id="template-form" onsubmit="return false">
                <input type="hidden" name="csrf_token_form" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="new-context" class="form-label">Context <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new-context" name="context"
                                   value="<?php echo htmlspecialchars($preselectedContext); ?>" required>
                            <div id="context-suggestions" class="suggestion-badges"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="new-category" class="form-label">Category <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new-category" name="category"
                                   value="<?php echo htmlspecialchars($preselectedCategory); ?>" required>
                            <div id="category-suggestions" class="suggestion-badges"></div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new-template-name" class="form-label">Template Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="new-template-name" name="template_name" required>
                </div>

                <div class="form-group">
                    <label for="new-template-content" class="form-label">Template Content <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="new-template-content" name="template_content" rows="6" required></textarea>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is-public" name="is_public" checked>
                    <label class="form-check-label" for="is-public">
                        Make this template public (available to all users)
                    </label>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-primary" id="save-template-btn">
                        <i class="fa fa-save"></i> Save Template
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="window.close()">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>

        <script src="../../../public/assets/jquery/dist/jquery.min.js"></script>
        <script src="../../../public/assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Predefined data
            const predefinedContexts = [
                'EXPRESS VISITS', 'TOC MSM', 'RESULTS NORMAL', 'RESULTS ABNORMAL',
                'FEMALE CONDITIONS', 'EXPOSURE TREATMENTS', 'FOLLOW UP VISITS',
                'ANNUAL PHYSICALS', 'PREVENTIVE CARE', 'ACUTE VISITS'
            ];

            const contextCategories = {
                'EXPRESS VISITS': ['FEMALE Express', 'HETEROSEXUAL MALE Express', 'ASYMPTOMATIC MSM'],
                'RESULTS NORMAL': ['Female Results', 'Heterosexual Male Results', 'MSM Results'],
                'RESULTS ABNORMAL': ['CT Treatment - Doxycycline', 'GC Treatment', 'CT and GC Treatment'],
                'FEMALE CONDITIONS': ['Bacterial Vaginosis', 'Yeast Infection', 'HSV', 'PID'],
                'EXPOSURE TREATMENTS': ['BV with CT Exposure', 'BV with GC and CT Exposure']
            };

            // Setup autocomplete suggestions
            function setupAutocompleteSuggestions() {
                setupContextSuggestions();
                setupCategorySuggestions();
            }

            function setupContextSuggestions() {
                const contextInput = document.getElementById('new-context');
                const contextSuggestions = document.getElementById('context-suggestions');

                contextInput.addEventListener('input', function() {
                    const value = this.value.toUpperCase();
                    contextSuggestions.innerHTML = '';

                    if (value.length > 0) {
                        const matches = predefinedContexts.filter(context =>
                            context.toUpperCase().includes(value) && context.toUpperCase() !== value
                        ).slice(0, 5);

                        matches.forEach(match => {
                            const span = document.createElement('span');
                            span.className = 'badge bg-light text-dark';
                            span.style.cursor = 'pointer';
                            span.textContent = match;
                            span.onclick = () => {
                                contextInput.value = match;
                                contextSuggestions.innerHTML = '';
                                updateCategorySuggestions(match);
                            };
                            contextSuggestions.appendChild(span);
                        });
                    }
                });
            }

            function setupCategorySuggestions() {
                const categoryInput = document.getElementById('new-category');
                const categorySuggestions = document.getElementById('category-suggestions');

                categoryInput.addEventListener('input', function() {
                    const context = document.getElementById('new-context').value;
                    const value = this.value.toUpperCase();
                    categorySuggestions.innerHTML = '';

                    if (value.length > 0) {
                        const suggestions = contextCategories[context] || ['ROUTINE', 'URGENT', 'FOLLOW-UP', 'NEW PATIENT'];
                        const matches = suggestions.filter(category =>
                            category.toUpperCase().includes(value) && category.toUpperCase() !== value
                        ).slice(0, 5);

                        matches.forEach(match => {
                            const span = document.createElement('span');
                            span.className = 'badge bg-light text-dark';
                            span.style.cursor = 'pointer';
                            span.textContent = match;
                            span.onclick = () => {
                                categoryInput.value = match;
                                categorySuggestions.innerHTML = '';
                            };
                            categorySuggestions.appendChild(span);
                        });
                    }
                });
            }

            function updateCategorySuggestions(context) {
                // Trigger category suggestions update
                const categoryInput = document.getElementById('new-category');
                const event = new Event('input', { bubbles: true });
                categoryInput.dispatchEvent(event);
            }

            // Save template function
            function saveTemplate() {
                const form = document.getElementById('template-form');
                const formData = new FormData(form);

                // Add action
                formData.append('action', 'save_template');

                // Validate required fields
                const context = formData.get('context').trim();
                const category = formData.get('category').trim();
                const templateName = formData.get('template_name').trim();
                const templateContent = formData.get('template_content').trim();

                if (!context || !category || !templateName || !templateContent) {
                    alert('All fields are required');
                    return;
                }

                // Disable button
                const saveBtn = document.getElementById('save-template-btn');
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
                saveBtn.disabled = true;

                // Debug logging
                console.log('Submitting template:', {
                    action: 'save_template',
                    context,
                    category,
                    templateName,
                    contentLength: templateContent.length,
                    isPublic: formData.get('is_public')
                });

                // Log form data for debugging
                console.log('Sending form data:', Object.fromEntries(formData));

                fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                })
                .then(async response => {
                    const text = await response.text();
                    console.log('Raw server response:', text);

                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse server response:', text);
                        throw new Error('Server returned invalid JSON: ' + text);
                    }

                    if (!response.ok) {
                        console.error('Server error:', {
                            status: response.status,
                            statusText: response.statusText,
                            data: data
                        });
                        throw new Error(data.error || 'Server error occurred');
                    }

                    return data;
                })
                .then(data => {
                    console.log('Processed response:', data);
                    if (data.success) {
                        // Send message to parent window
                        if (window.opener) {
                            window.opener.postMessage({
                                type: 'TEMPLATE_SAVED',
                                context: formData.get('context'),
                                category: formData.get('category')
                            }, window.location.origin);
                        }

                        alert('Template saved successfully!');
                        window.close();
                    } else {
                        console.error('Server returned success: false', data);
                        throw new Error(data.error || 'Unknown error occurred');
                    }
                })
                .catch(error => {
                    console.error('Error details:', {
                        message: error.message,
                        stack: error.stack,
                        error: error
                    });
                    
                    let errorMessage = error.message;
                    if (error.message.includes('Failed to fetch')) {
                        errorMessage = 'Could not connect to server. Please check your connection.';
                    } else if (error.message.includes('HTTP 403')) {
                        errorMessage = 'Session expired. Please refresh the page and try again.';
                    }
                    
                    alert(errorMessage);
                })
                .finally(() => {
                    // Re-enable button
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                });
            }

            // Initialize when DOM is loaded
            document.addEventListener('DOMContentLoaded', function() {
                setupAutocompleteSuggestions();

                // Setup save button with enhanced error handling
                const saveBtn = document.getElementById('save-template-btn');
                if (saveBtn) {
                    saveBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('Save button clicked');
                        saveTemplate();
                    });
                } else {
                    console.error('Save button not found in DOM');
                }

                // Focus on first empty field
                const contextInput = document.getElementById('new-context');
                const categoryInput = document.getElementById('new-category');

                if (!contextInput.value) {
                    contextInput.focus();
                } else if (!categoryInput.value) {
                    categoryInput.focus();
                } else {
                    document.getElementById('new-template-name').focus();
                }
            });
        </script>
    </body>
    </html>

}
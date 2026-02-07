<?php
// Path: src/pages/tags/index.php
require_once __DIR__ . '/../../../init.php';
defined('URL_HOME') || require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

$dbTable = defined('NOVEL_TAGS') ? NOVEL_TAGS : 'novel_tag';
$auditPage = 'Tag Management';
$deleteQuery = "DELETE FROM " . $dbTable . " WHERE id = ?";
// When included from the user dashboard, we only render the inner tag card
$isEmbeddedInDashboard = isset($EMBED_TAGS_PAGE) && $EMBED_TAGS_PAGE === true;

// For AJAX requests, return JSON error instead of redirecting
$isAjaxRequest = isset($_GET['mode']) && $_GET['mode'] === 'data';
$isDeleteRequest = isset($_POST['mode']) && $_POST['mode'] === 'delete';

// Check user authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if ($isAjaxRequest || $isDeleteRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'draw' => intval($_GET['draw'] ?? 0),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Session expired. Please login again.'
        ]);
        exit();
    }
    header("Location: " . URL_LOGIN); 
    exit();
}

// Handle AJAX requests for DataTables
if ($isAjaxRequest) {
    header('Content-Type: application/json');

    /**
     * Safe JSON encoder that works even if the json extension is disabled.
     */
    if (!function_exists('safeJsonEncode')) {
        function safeJsonEncode($data) {
            if (function_exists('json_encode')) {
                $flags = defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0;
                return json_encode($data, $flags);
            }

            // Minimal manual encoder (supports nested arrays, strings, numbers, bool, null)
            if (is_array($data)) {
                // IMPORTANT: Empty arrays must always be encoded as [] (not {})
                if ($data === []) {
                    return '[]';
                }

                $isAssoc = array_keys($data) !== range(0, count($data) - 1);
                $items = [];
                foreach ($data as $key => $value) {
                    $encodedValue = safeJsonEncode($value);
                    if ($isAssoc) {
                        $items[] = '"' . addslashes((string)$key) . '":' . $encodedValue;
                    } else {
                        $items[] = $encodedValue;
                    }
                }
                return $isAssoc ? '{' . implode(',', $items) . '}' : '[' . implode(',', $items) . ']';
            } elseif (is_string($data)) {
                $escaped = str_replace(
                    ["\\", "\"", "\n", "\r", "\t", "\f", "\b"],
                    ["\\\\", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b"],
                    $data
                );
                return '"' . $escaped . '"';
            } elseif (is_bool($data)) {
                return $data ? 'true' : 'false';
            } elseif (is_null($data)) {
                return 'null';
            } else {
                return (string)$data;
            }
        }
    }

    /**
     * Unified error response helper for DataTables.
     */
    if (!function_exists('sendTagTableError')) {
        function sendTagTableError($message) {
            $draw = isset($_GET['draw']) ? (int) $_GET['draw'] : 0;
            echo safeJsonEncode([
                "draw" => $draw,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => $message,
            ]);
            exit();
        }
    }

    // Check database connection
    if (!isset($conn) || !$conn) {
        sendTagTableError('Database connection is not available.');
    }
    
    // Get DataTables parameters
    $start  = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $search = '';
    if (isset($_GET['search']) && is_array($_GET['search']) && isset($_GET['search']['value'])) {
        $search = $_GET['search']['value'];
    }

    // Build SQL queries
    $sql = "SELECT id, name FROM " . $dbTable . " WHERE 1=1";
    $countSql = "SELECT COUNT(*) FROM " . $dbTable . " WHERE 1=1";
    
    $mainParams = []; 
    $mainTypes = "";
    $countParams = []; 
    $countTypes = "";

    // Add search condition if provided
    if (!empty($search)) {
        $term = "%" . $search . "%";
        $sql .= " AND name LIKE ?";
        $countSql .= " AND name LIKE ?";
        
        $mainParams[] = $term; 
        $countParams[] = $term;
        $mainTypes .= "s"; 
        $countTypes .= "s";
    }

    // Add ordering and pagination
    $sql .= " ORDER BY id DESC LIMIT ?, ?";
    $mainParams[] = $start; 
    $mainParams[] = $length; 
    $mainTypes .= "ii";

    // Helper for Safe Parameter Binding
    if (!function_exists('bindDynamicParams')) {
        function bindDynamicParams($stmt, $types, $params) {
            if (!empty($params) && $stmt instanceof mysqli_stmt) {
                $bindParams = [];
                $bindParams[] = $types;
                for ($i = 0; $i < count($params); $i++) {
                    $bindParams[] = &$params[$i];
                }
                call_user_func_array(array($stmt, 'bind_param'), $bindParams);
            }
        }
    }

    // 1. Get total records count (filtered)
    $cStmt = $conn->prepare($countSql);
    if ($cStmt === false) {
        error_log("Tag list count query prepare failed: " . $conn->error);
        sendTagTableError('System error while loading tag list.');
    }
    bindDynamicParams($cStmt, $countTypes, $countParams);
    if (!$cStmt->execute()) {
        error_log("Tag list count query execute failed: " . $cStmt->error);
        sendTagTableError('System error while loading tag list.');
    }
    $cStmt->bind_result($totalRecords);
    $cStmt->fetch();
    $cStmt->close();

    // 2. Fetch paginated data
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Tag list data query prepare failed: " . $conn->error);
        sendTagTableError('System error while loading tag list.');
    }
    bindDynamicParams($stmt, $mainTypes, $mainParams);
    if (!$stmt->execute()) {
        error_log("Tag list data query execute failed: " . $stmt->error);
        sendTagTableError('System error while loading tag list.');
    }
    
    // Bind results dynamically
    $meta = $stmt->result_metadata();
    $row = []; 
    $bindResult = [];
    while ($field = $meta->fetch_field()) { 
        $bindResult[] = &$row[$field->name]; 
    }
    call_user_func_array(array($stmt, 'bind_result'), $bindResult);

    // Build data array for DataTables
    $data = [];
    while ($stmt->fetch()) {
        $safeRow = []; 
        foreach($row as $k => $v) { 
            $safeRow[$k] = $v; 
        }
        
        // Always route tag edit through the user dashboard (embedded form)
        $editUrl = URL_USER_DASHBOARD . '?view=tag_form&id=' . $safeRow['id'];
        $actions = '<a href="' . $editUrl . '" class="btn btn-sm btn-outline-primary btn-action" title="编辑"><i class="fa-solid fa-pen"></i></a>'
            . '<button class="btn btn-sm btn-outline-danger btn-action delete-btn" data-id="' . $safeRow['id'] . '" data-name="' . htmlspecialchars($safeRow['name']) . '" title="删除"><i class="fa-solid fa-trash"></i></button>';
        $data[] = [htmlspecialchars($safeRow['name']), $actions];
    }
    $stmt->close();

    // Return JSON response for DataTables
    echo safeJsonEncode([
        "draw" => intval($_GET['draw'] ?? 0),
        "recordsTotal" => (int) $totalRecords,
        "recordsFiltered" => (int) $totalRecords,
        "data" => $data
    ]);
    exit();
}

// DELETE API - Handle tag deletion
if (isset($_POST['mode']) && $_POST['mode'] === 'delete') {
    // Start output buffering to capture any stray output
    ob_start();
    
    // Set JSON response header
    header('Content-Type: application/json; charset=utf-8');
    
    // Disable error display for production, but log all errors
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    
    // Validate database connection
    if (!isset($conn) || !($conn instanceof mysqli)) {
        // Clean any output before sending error
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode([
            'success' => false, 
            'message' => 'Database connection is not available.'
        ]);
        exit();
    }
    
    // Validate required parameters
    $id = intval($_POST['id'] ?? 0);
    $tagName = $_POST['name'] ?? 'Unknown';
    $currentUserId = $_SESSION['user_id'] ?? 0;
    
    if ($id <= 0) {
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid tag ID.'
        ]);
        exit();
    }
    
    // 1. Load existing row for audit trail
    $oldData = null;
    // Try to get basic information first, if additional columns don't exist
    $selectSql = "SELECT id, name FROM " . $dbTable . " WHERE id = ?";
    if ($sel = $conn->prepare($selectSql)) {
        $sel->bind_param("i", $id);
        if ($sel->execute()) {
            $sel->bind_result($rId, $rName);
            if ($sel->fetch()) {
                $oldData = [
                    'id'   => $rId,
                    'name' => $rName,
                ];
            }
        }
        $sel->close();
    }
    
    // Ensure oldData is serializable
    if ($oldData) {
        $oldData = array_map('strval', $oldData);
    }
    
    // 2. Perform the actual delete
    $stmt = $conn->prepare($deleteQuery);
    if ($stmt === false) {
        error_log("Delete tag prepare failed: " . $conn->error);
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode([
            'success' => false, 
            'message' => 'Error preparing delete statement.'
        ]);
        exit();
    }
    
    $stmt->bind_param("i", $id);
    
    // Execute deletion
    if ($stmt->execute()) {
        // Log audit trail with proper error handling
        $auditLogged = false;
        $auditError = null;
        
        if (function_exists('logAudit')) {
            // Prepare audit data
            $auditData = [
                'page'           => $auditPage,
                'action'         => 'D',
                'action_message' => 'Deleted Tag: ' . $tagName,
                'query'          => $deleteQuery,
                'query_table'    => $dbTable,
                'user_id'        => $currentUserId,
                'old_value'      => $oldData,
                'new_value'      => null,
            ];
            
            // Log audit with error handling
            try {
                logAudit($auditData);
                $auditLogged = true;
            } catch (Exception $e) {
                $auditError = $e->getMessage();
                error_log("Audit log error: " . $auditError);
                // Continue even if audit fails - deletion was successful
            }
        }
        
        // Clean output buffer
        if (ob_get_length()) {
            ob_clean();
        }
        
        // Prepare success response
        $response = ['success' => true];
        
        // Add warning if audit failed
        if (!$auditLogged && !empty($auditError)) {
            $response['warning'] = 'Audit logging failed (tag was deleted successfully)';
        }
        
        echo json_encode($response);
        
    } else {
        // Delete failed
        error_log("Delete tag execute failed: " . $stmt->error);
        if (ob_get_length()) {
            ob_clean();
        }
        echo json_encode([
            'success' => false, 
            'message' => 'Error deleting tag from database.'
        ]);
    }
    
    $stmt->close();
    exit();
}

// If not an AJAX request, render the HTML page
$pageTitle = "小说标签 - " . WEBSITE_NAME;

// If embedded in dashboard, only render the inner card markup (no full HTML shell)
if ($isEmbeddedInDashboard): ?>
    <div class="tag-container">
        <div class="card tag-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h4 class="m-0 text-primary"><i class="fa-solid fa-tags"></i> 标签管理</h4>
                <a href="<?php echo URL_USER_DASHBOARD; ?>?view=tag_form" class="btn btn-primary desktop-add-btn">
                    <i class="fa-solid fa-plus"></i> 新增标签
                </a>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        标签保存成功！ 
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <table
                    id="tagTable"
                    class="table table-hover w-100"
                    data-api-url="<?php echo URL_NOVEL_TAGS_API; ?>?mode=data"
                    data-delete-url="<?php echo URL_NOVEL_TAGS_API; ?>"
                >
                    <thead>
                    <tr>
                        <th>标签名称</th>
                        <th style="width:100px;">操作</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
    <a href="<?php echo URL_USER_DASHBOARD; ?>?view=tag_form" class="btn btn-primary btn-add-mobile">
        <i class="fa-solid fa-plus fa-lg"></i>
    </a>
<?php else: ?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/tag.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="tag-container">
    <div class="card tag-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h4 class="m-0 text-primary"><i class="fa-solid fa-tags"></i> 标签管理</h4>
            <a href="<?php echo URL_USER_DASHBOARD; ?>?view=tag_form" class="btn btn-primary desktop-add-btn">
                <i class="fa-solid fa-plus"></i> 新增标签
            </a>
        </div>
        <div class="card-body">
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        标签保存成功！ 
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <table
                id="tagTable"
                class="table table-hover w-100"
                data-api-url="<?php echo URL_NOVEL_TAGS_API; ?>?mode=data"
                data-delete-url="<?php echo URL_NOVEL_TAGS_API; ?>"
            >
                <thead>
                <tr>
                        <th>标签名称</th>
                        <th style="width:100px;">操作</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
<a href="<?php echo URL_USER_DASHBOARD; ?>?view=tag_form" class="btn btn-primary btn-add-mobile">
    <i class="fa-solid fa-plus fa-lg"></i>
</a>
<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/tag.js"></script>
</body>
</html>
<?php endif; ?>
<?php
// Path: src/pages/tags/index.php
require_once dirname(__DIR__, 3) . '/common.php';

// 1. Identify this specific view's URL as registered in your DB
$currentUrl = '/dashboard.php?view=tags'; 

// [ADDED] Fetch the dynamic permission object for this page
$perm = hasPagePermission($conn, $currentUrl);

// 2. Check if the user has View permission for this view
if (empty($perm) || !$perm->view) {
    // Handle AJAX DataTables request
    if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
        header('Content-Type: application/json');
        echo safeJsonEncode(['error' => 'Access Denied']);
        exit();
    }
    // Handle UI load
    denyAccess("权限不足：您没有访问标签管理页面的权限。");
}

$tagTable = NOVEL_TAGS;
$auditPage = 'Tag Management';
$viewQuery = "SELECT id, name FROM " . $tagTable;
$deleteQuery = "DELETE FROM " . $tagTable . " WHERE id = ?";
$isEmbeddedInDashboard = isset($EMBED_TAGS_PAGE) && $EMBED_TAGS_PAGE === true;

// Request Type Detection
$isAjaxRequest = isset($_GET['mode']) && $_GET['mode'] === 'data';
$isDeleteRequest = isset($_POST['mode']) && $_POST['mode'] === 'delete';

// 1. Auth Check
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

// 2. Helper Functions (safeJsonEncode is loaded from functions.php via common.php)

if (!function_exists('jsonEncodeWrapper')) {
    function jsonEncodeWrapper($data) {
        return safeJsonEncode($data);
    }
}

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

if (!function_exists('sendDeleteSuccess')) {
    function sendDeleteSuccess($auditLogged = true, $debug = false, $traceId = '') {
        while (ob_get_level()) { ob_end_clean(); }
        $response = ['success' => true];
        if (!$auditLogged) $response['warning'] = 'Audit logging failed';
        if ($debug && $traceId) $response['trace_id'] = $traceId;
        echo jsonEncodeWrapper($response);
        exit();
    }
}

if (!function_exists('sendDeleteError')) {
    function sendDeleteError($message, $debug = false, $traceId = '') {
        while (ob_get_level()) { ob_end_clean(); }
        $payload = ['success' => false, 'message' => $message];
        if ($debug && $traceId) $payload['trace_id'] = $traceId;
        echo jsonEncodeWrapper($payload);
        exit();
    }
}

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

// 3. API: List Data (GET)
if ($isAjaxRequest) {
    header('Content-Type: application/json');

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
    $sql = "SELECT id, name FROM " . $tagTable . " WHERE 1=1";
    $countSql = "SELECT COUNT(*) FROM " . $tagTable . " WHERE 1=1";
    
    $mainParams = []; 
    $mainTypes = "";
    $countParams = []; 
    $countTypes = "";

    // Add search condition if provided
    if (!empty($search)) {
        $term = "%" . $search . "%";
        $sql .= " AND name LIKE ?";
        $countSql .= " AND name LIKE ?";
        $mainParams[] = $term; $countParams[] = $term;
        $mainTypes .= "s"; $countTypes .= "s";
    }

    $sql .= " ORDER BY id DESC LIMIT ?, ?";
    $mainParams[] = $start; $mainParams[] = $length; 
    $mainTypes .= "ii";

    // Count
    $cStmt = $conn->prepare($countSql);
    if ($cStmt === false) sendTagTableError('System error while loading tag list.');
    bindDynamicParams($cStmt, $countTypes, $countParams);
    if (!$cStmt->execute()) sendTagTableError('System error while loading tag list.');
    $cStmt->bind_result($totalRecords);
    $cStmt->fetch();
    $cStmt->close();

    // Data
    $stmt = $conn->prepare($sql);
    if ($stmt === false) sendTagTableError('System error while loading tag list.');
    bindDynamicParams($stmt, $mainTypes, $mainParams);
    if (!$stmt->execute()) sendTagTableError('System error while loading tag list.');
    
    $stmt->bind_result($id, $name);

    $data = [];
    while ($stmt->fetch()) {
        $editUrl = URL_USER_DASHBOARD . '?view=tag_form&id=' . (int) $id;
        
        $actionsHtml = '';
        // 1. Check dynamic permission properties
        if ($perm->edit) {
            $actionsHtml .= '<a href="' . $editUrl . '" class="btn btn-sm btn-outline-primary btn-action" title="Edit"><i class="fa-solid fa-pen"></i></a>';
        }
        if ($perm->delete) {
            $actionsHtml .= '<button class="btn btn-sm btn-outline-danger btn-action delete-btn" data-id="' . $id . '" data-name="' . htmlspecialchars($name) . '" title="Delete"><i class="fa-solid fa-trash"></i></button>';
        }

        // 2. Use the global helper to wrap buttons and fix CSS alignment
        $outputActions = renderTableActions($actionsHtml);
        
        $data[] = [htmlspecialchars($name), $outputActions];
    }
    $stmt->close();

    echo safeJsonEncode([
        "draw" => intval($_GET['draw'] ?? 0),
        "recordsTotal" => (int) $totalRecords,
        "recordsFiltered" => (int) $totalRecords,
        "data" => $data
    ]);
    exit();
}

// 4. API: Delete (POST)
if ($isDeleteRequest) {
    // [ADDED] Secure the Delete API
    if (!$perm->delete) {
        sendDeleteError('操作禁止：您没有删除权限。');
    }

    while (ob_get_level()) { ob_end_clean(); }
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
    
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    $debug = isset($_POST['debug']) && $_POST['debug'] === '1';
    $traceId = uniqid('tag-del-', true);
    
    if (!isset($conn) || !($conn instanceof mysqli)) {
        sendDeleteError('Database connection is not available.', $debug, $traceId);
    }
    
    $id = intval($_POST['id'] ?? 0);
    $tagName = $_POST['name'] ?? 'Unknown';
    $currentUserId = $_SESSION['user_id'] ?? 0;
    
    if ($id <= 0) sendDeleteError('Invalid tag ID.', $debug, $traceId);
    
    // Audit Data Load
    $oldData = null;
    $selectSql = "SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $tagTable . " WHERE id = ?";
    if ($sel = $conn->prepare($selectSql)) {
        $sel->bind_param("i", $id);
        if ($sel->execute()) {
            $sel->store_result(); // [CRITICAL FIX]
            if ($sel->num_rows > 0) {
                $sel->bind_result($rId, $rName, $rCr, $rUp, $rCb, $rUb);
                $sel->fetch();
                $oldData = [
                    'id' => $rId, 'name' => $rName, 'created_at' => $rCr, 
                    'updated_at' => $rUp, 'created_by' => $rCb, 'updated_by' => $rUb
                ];
            }
        }
        $sel->close();
    }

    // Fallback: still log something useful even if old row can't be loaded
    if (empty($oldData)) {
        $oldData = [
            'id' => $id,
            'name' => $tagName,
        ];
    }
    
    // Execute Delete
    $stmt = $conn->prepare($deleteQuery);
    if ($stmt === false) sendDeleteError('Error preparing delete statement.', $debug, $traceId);
    $stmt->bind_param("i", $id);
    
    // Execute deletion
    if ($stmt->execute()) {
        $auditLogged = false;
        if (function_exists('logAudit')) {
            try {
                logAudit([
                    'page'           => $auditPage,
                    'action'         => 'D',
                    'action_message' => 'Deleted Tag: ' . $tagName,
                    'query'          => $deleteQuery,
                    'query_table'    => $tagTable,
                    'user_id'        => $currentUserId,
                    'record_id'      => $id,
                    'record_name'    => $tagName,
                    'old_value'      => $oldData,
                    'new_value'      => null,
                ]);
                $auditLogged = true;
            } catch (Exception $e) { 
                // Ignore audit error
            }
        }
        $stmt->close();
        sendDeleteSuccess($auditLogged, $debug, $traceId);
    } else {
        $stmt->close();
        sendDeleteError('Error deleting tag from database.', $debug, $traceId);
    }
    
    exit();
}

// Flash message
$flashMsg = $_SESSION['flash_msg'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
if ($flashMsg !== '') {
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// 5. Audit Log (View) & HTML Render

// [NEW] Log that user viewed this page
if (function_exists('logAudit')) {
    logAudit([
        'page'           => $auditPage,
        'action'         => 'V',
        'action_message' => 'User viewed Tag List',
        'query'          => $viewQuery,
        'query_table'    => $tagTable,
        'user_id'        => $_SESSION['user_id']
    ]);
}

if ($isEmbeddedInDashboard): ?>
    <div class="tag-container">
        <div class="card tag-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h4 class="m-0 text-primary"><i class="fa-solid fa-tags"></i> 标签管理</h4>
                <?php if ($perm->add): ?>
                <a href="<?php echo URL_USER_DASHBOARD; ?>?view=tag_form" class="btn btn-primary desktop-add-btn">
                    <i class="fa-solid fa-plus"></i> 新增标签
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($flashMsg): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($flashMsg); ?>
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
<?php else: ?>
<?php $pageMetaKey = '/dashboard.php?view=tags'; ?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/global.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="tag-container">
    <div class="card tag-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h4 class="m-0 text-primary"><i class="fa-solid fa-tags"></i> 标签管理</h4>
            <?php if ($perm->add): ?>
            <a href="<?php echo URL_USER_DASHBOARD; ?>?view=tag_form" class="btn btn-primary desktop-add-btn">
                <i class="fa-solid fa-plus"></i> 新增标签
            </a>
            <?php endif; ?>
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
<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/tag.js"></script>
</body>
</html>
<?php endif; ?>
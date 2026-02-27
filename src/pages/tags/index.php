<?php
// Path: src/pages/tags/index.php
require_once dirname(__DIR__, 3) . '/common.php';

// Auth Check
requireLogin();

// 1. Identify this specific view's URL as registered in your DB
$currentUrl = '/dashboard.php?view=tags'; 

// [ADDED] Fetch the dynamic permission object for this page
$perm = hasPagePermission($conn, $currentUrl);

checkPermissionError('view', $perm);

$tagTable = NOVEL_TAGS;
$auditPage = 'Tag Management';
$viewQuery = "SELECT id, name FROM " . $tagTable;
$deleteQuery = "DELETE FROM " . $tagTable . " WHERE id = ?";

// Request Type Detection
$isEmbeddedInDashboard = ($EMBED_TAGS_PAGE ?? false) === true;

$tagMode = input(QUERY_TAG_MODE) ?: (input('pa_mode') ?: '');
$pageActionMode = ($tagMode === QUERY_FORM_MODE) ? QUERY_FORM_MODE : 'list';

$isAjaxRequest = input('mode') === 'data';
$isDeleteRequest = post('mode') === 'delete';

if ($isEmbeddedInDashboard && $pageActionMode === 'form') {
    $EMBED_TAG_FORM_PAGE = true;
    require BASE_PATH . PATH_NOVEL_TAGS_FORM;
    return;
}

// 2. Helper Functions (safeJsonEncode is loaded from functions.php via common.php)

if (!function_exists('jsonEncodeWrapper')) {
    function jsonEncodeWrapper($data) {
        return safeJsonEncode($data);
    }
}

if (!function_exists('sendTagTableError')) {
    function sendTagTableError($message) {
        $draw = input('draw') ?? 0;
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
        $start  = (int)numberInput('start');
        $length = (int)(numberInput('length') ?: 10);

    // Use the global array helper for the nested search value
    $search = getArray('search')['value'] ?? '';

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
        $editUrl = URL_NOVEL_TAGS_FORM . '&id=' . $id;
        
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
        
        // [MODIFIED] Added $id to the beginning of the array
        $data[] = [$id, htmlspecialchars($name), $outputActions];
    }
    $stmt->close();

    echo safeJsonEncode([
        "draw" => intval(input('draw') ?? 0),
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $totalRecords,
        "data" => $data
    ]);
    exit();
}

// 4. API: Delete (POST)
if ($isDeleteRequest) {
    // [ADDED] Secure the Delete API
    $deleteError = checkPermissionError('delete', $perm);
    if ($deleteError) {
        sendDeleteError($deleteError);
    }

    while (ob_get_level()) { ob_end_clean(); }
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
    
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    $debug = post('debug') === '1';
    $traceId = uniqid('tag-del-', true);
    
    if (!isset($conn) || !($conn instanceof mysqli)) {
        sendDeleteError('Database connection is not available.', $debug, $traceId);
    }
    
    $id = intval(post('id') ?? 0);
    $tagName = postSpaceFilter('name') ?? 'Unknown';
    $currentUserId = sessionInt('user_id');
    
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
$flashMsg = session('flash_msg');
$flashType = session('flash_type') ?: 'success';
if ($flashMsg !== '') {
    unsetSession('flash_msg');
    unsetSession('flash_type');
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
        'user_id'        => sessionInt('user_id')
    ]);
}

if ($isEmbeddedInDashboard):
    $pageScripts = ['jquery.dataTables.min.js', 'dataTables.bootstrap.min.js', 'tag.js'];
?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
    <div class="tag-container">
        <div class="card tag-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div>
                    <?php echo generateBreadcrumb($conn, $currentUrl); ?>
                    <h4 class="m-0 text-primary"><i class="fa-solid fa-tags"></i> 标签管理</h4>
                </div>
                <?php if ($perm->add): ?>
                <a href="<?php echo URL_NOVEL_TAGS_FORM; ?>" class="btn btn-primary desktop-add-btn">
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
                        <th style="width:80px;">ID</th>
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
            <div>
                <?php echo generateBreadcrumb($conn, $currentUrl); ?>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-tags"></i> 标签管理</h4>
            </div>
            <?php if ($perm->add): ?>
            <a href="<?php echo URL_NOVEL_TAGS_FORM; ?>" class="btn btn-primary desktop-add-btn">
                <i class="fa-solid fa-plus"></i> 新增标签
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (input('msg') === 'saved'): ?>
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
                        <th style="width:80px;">ID</th>
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
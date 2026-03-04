<?php
// Path: src/pages/tags/index.php
require_once dirname(__DIR__, 3) . '/common.php';

// Auth Check
requireLogin();

// 1. Identify this specific view's URL as registered in your DB
$currentUrl = parse_url(URL_NOVEL_TAGS, PHP_URL_PATH) ?: '/tags.php'; 

// [ADDED] Fetch the dynamic permission object for this page
$perm = hasPagePermission($conn, $currentUrl);
$pageName = getDynamicPageName($conn, $perm, $currentUrl);

checkPermissionError('view', $perm);

$tagTable = NOVEL_TAGS;
$auditPage = 'Tag Management';

// [MODIFIED] Soft delete implementation
$viewQuery = "SELECT id, name FROM " . $tagTable . " WHERE status = 'A'";
$deleteQuery = "UPDATE " . $tagTable . " SET status = 'D', updated_by = ?, updated_at = NOW() WHERE id = ?";

// Request Type Detection
$tagMode = input(QUERY_TAG_MODE) ?: (input('pa_mode') ?: '');
$pageActionMode = ($tagMode === QUERY_FORM_MODE) ? QUERY_FORM_MODE : 'list';

$isAjaxRequest = input('mode') === 'data';
$isDeleteRequest = post('mode') === 'delete';

// When form mode is requested, include the form page directly
if ($pageActionMode === 'form') {
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

    // [MODIFIED] Only load active tags
    $sql = "SELECT id, name FROM " . $tagTable . " WHERE status = 'A'";
    $countSql = "SELECT COUNT(*) FROM " . $tagTable . " WHERE status = 'A'";
    
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
    // Secure the Delete API
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
    
    // [MODIFIED] Logic Validation: Prevent deletion ONLY if the tag is explicitly saved inside a novel
    $isTagUsedByNovel = false;
    
    // [MODIFIED] Optimized targeted SQL check to prevent PHP memory exhaustion
    // Replaces spaces after commas so FIND_IN_SET works perfectly
    $novelCheckSql = "SELECT 1 FROM " . NOVEL . " WHERE FIND_IN_SET(?, REPLACE(tags, ', ', ',')) > 0 OR FIND_IN_SET(?, REPLACE(tags, ', ', ',')) > 0 LIMIT 1";
    
    if ($checkStmt = $conn->prepare($novelCheckSql)) {
        $idStr = (string)$id;
        $checkStmt->bind_param("ss", $idStr, $tagName);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $isTagUsedByNovel = true;
        }
        $checkStmt->close();
    }
    
    // Execute the block if the tag is actively used
    if ($isTagUsedByNovel) {
        sendDeleteError('无法删除：该标签正被一部或多部小说直接使用。请先在小说管理中移除此标签。', $debug, $traceId);
    }

    // Audit Data Load
    $oldData = null;
    $selectSql = "SELECT id, name, status, created_at, updated_at, created_by, updated_by FROM " . $tagTable . " WHERE id = ?";
    if ($sel = $conn->prepare($selectSql)) {
        $sel->bind_param("i", $id);
        if ($sel->execute()) {
            $sel->store_result();
            if ($sel->num_rows > 0) {
                $sel->bind_result($rId, $rName, $rStatus, $rCr, $rUp, $rCb, $rUb);
                $sel->fetch();
                $oldData = [
                    'id' => $rId, 'name' => $rName, 'status' => $rStatus, 'created_at' => $rCr, 
                    'updated_at' => $rUp, 'created_by' => $rCb, 'updated_by' => $rUb
                ];
            }
        }
        $sel->close();
    }

    if (empty($oldData)) {
        $oldData = [
            'id' => $id,
            'name' => $tagName,
        ];
    }
    
    // Execute Soft Delete
    $stmt = $conn->prepare($deleteQuery);
    if ($stmt === false) sendDeleteError('Error preparing delete statement.', $debug, $traceId);
    $stmt->bind_param("ii", $currentUserId, $id);
    
    // Execute deletion
    if ($stmt->execute()) {
        $auditLogged = false;
        if (function_exists('logAudit')) {
            try {
                $newData = $oldData;
                $newData['status'] = 'D'; // Indicate it's deleted in the new state

                logAudit([
                    'page'           => $auditPage,
                    'action'         => 'D',
                    'action_message' => 'Soft Deleted Tag: ' . $tagName,
                    'query'          => $deleteQuery,
                    'query_table'    => $tagTable,
                    'user_id'        => $currentUserId,
                    'record_id'      => $id,
                    'record_name'    => $tagName,
                    'old_value'      => $oldData,
                    'new_value'      => $newData,
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

?>
<?php $pageMetaKey = $currentUrl; ?>
<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="tag-container app-page-shell">
    <?php echo generateBreadcrumb($conn, $currentUrl); ?>
    <div class="card tag-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <div>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-tags"></i> <?php echo htmlspecialchars($pageName); ?></h4>
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
<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo SITEURL; ?>/src/pages/tags/js/tag.js"></script>
</body>
</html>
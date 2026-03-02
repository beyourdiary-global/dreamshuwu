<?php
// Path: src/pages/category/index.php
require_once dirname(__DIR__, 3) . '/common.php';

// Auth Check
requireLogin();

// 1. Identify this specific view's URL as registered in your DB
$currentUrl = '/dashboard.php?view=categories'; 

// [ADDED] Fetch the dynamic permission object for this page
$perm = hasPagePermission($conn, $currentUrl);
$pageName = getDynamicPageName($conn, $perm, $currentUrl);

// --- 2. Check View Permission ---
checkPermissionError('view', $perm);

$catTable  = NOVEL_CATEGORY;
$linkTable = CATEGORY_TAG;
$tagTable  = NOVEL_TAGS;
$auditPage = 'Category Management'; // Define audit page for logging

// [REVERTED] Back to standard fetching and Hard Delete
$viewQuery = "SELECT id, name FROM " . $catTable;
$deleteQuery = "DELETE FROM " . $catTable . " WHERE id = ?";

// 2. Embed Detection
$isEmbeddedInDashboard = isset($EMBED_CATS_PAGE) && $EMBED_CATS_PAGE === true;
$catMode = input(QUERY_CAT_MODE) !== '' ? input(QUERY_CAT_MODE) : input('pa_mode');
$pageActionMode = ($catMode === QUERY_FORM_MODE) ? QUERY_FORM_MODE : 'list';

if ($isEmbeddedInDashboard && $pageActionMode === 'form') {
    $EMBED_CAT_FORM_PAGE = true;
    require BASE_PATH . PATH_NOVEL_CATS_FORM;
    return;
}

// API: DATA FETCH
if (input('mode') === 'data') {
    header('Content-Type: application/json');

    // Use numberInput with strict casting for pagination
    $start  = (int)numberInput('start'); 
    $length = (int)(numberInput('length') ?: 10); 

    // Use getArray one-liner for DataTables nested search
    $search = getArray('search')['value'] ?? '';

    // [REVERTED] Removed is_active filter
    $sql = "SELECT id, name FROM $catTable WHERE 1=1";
    $countSql = "SELECT COUNT(*) FROM $catTable WHERE 1=1";

    $mainParams = []; $mainTypes = "";
    $countParams = []; $countTypes = "";

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

    // Helper
    if (!function_exists('bindDynamicParams')) {
        function bindDynamicParams($stmt, $types, $params) {
            if (!empty($params)) {
                $bindParams = []; $bindParams[] = $types;
                for ($i = 0; $i < count($params); $i++) $bindParams[] = &$params[$i];
                call_user_func_array(array($stmt, 'bind_param'), $bindParams);
            }
        }
    }

    // Count
    $cStmt = $conn->prepare($countSql);
    bindDynamicParams($cStmt, $countTypes, $countParams);
    $cStmt->execute();
    $cStmt->bind_result($totalRecords);
    $cStmt->fetch();
    $cStmt->close();

    // Data
    $stmt = $conn->prepare($sql);
    bindDynamicParams($stmt, $mainTypes, $mainParams);
    $stmt->execute();
    
    $categories = [];
    $meta = $stmt->result_metadata();
    $row = []; $bindResult = [];
    while ($field = $meta->fetch_field()) { $bindResult[] = &$row[$field->name]; }
    call_user_func_array(array($stmt, 'bind_result'), $bindResult);

    while ($stmt->fetch()) {
        $cRow = []; foreach($row as $k => $v) { $cRow[$k] = $v; }
        $categories[] = $cRow;
    }
    $stmt->close();

    // Fetch Tags
    $tagsMap = [];
    if (!empty($categories)) {
        $catIds = array_column($categories, 'id');
        $idList = implode(',', array_map('intval', $catIds));
        if ($idList) {
            $tagSql = "SELECT ct.category_id, t.name 
                       FROM $linkTable ct 
                       JOIN $tagTable t ON ct.tag_id = t.id 
                       WHERE ct.category_id IN ($idList)
                       ORDER BY t.name ASC";
            $tRes = $conn->query($tagSql);
            if ($tRes) {
                while ($tRow = $tRes->fetch_assoc()) {
                    $tagsMap[$tRow['category_id']][] = $tRow['name'];
                }
            }
        }
    }

    $data = [];
    foreach ($categories as $cat) {
        $catTags = $tagsMap[$cat['id']] ?? [];
        $tagHtml = '<span class="text-muted small">无标签</span>';
        if (!empty($catTags)) {
            $tagHtml = '';
            foreach ($catTags as $tagName) {
                $tagHtml .= '<span class="badge bg-secondary me-1">' . htmlspecialchars($tagName) . '</span>';
            }
        }

        // Edit URL points to Dashboard
        $editUrl = URL_NOVEL_CATS_FORM . '&id=' . $cat['id'];
        
        // [ADDED] Dynamically build actions based on permission object properties
        $btns = '';
        if ($perm->edit) {
            $btns .= '<a href="'.$editUrl.'" class="btn btn-sm btn-outline-primary btn-action" title="编辑"><i class="fa-solid fa-pen"></i></a>';
        }
        if ($perm->delete) {
            $btns .= '<button class="btn btn-sm btn-outline-danger btn-action delete-btn" data-id="'.$cat['id'].'" data-name="'.htmlspecialchars($cat['name']).'" title="删除"><i class="fa-solid fa-trash"></i></button>';
        }

        // [MODIFIED] Added $cat['id'] to the beginning of the array
        $data[] = [
            $cat['id'], // ID Column
            htmlspecialchars($cat['name']), 
            $tagHtml, 
            renderTableActions($btns)
        ];
    
    }

    echo safeJsonEncode([
    "draw"            => (int)numberInput('draw'),
    "recordsTotal"    => $totalRecords,
    "recordsFiltered" => $totalRecords,
    "data"            => $data
]);
exit();
}

// API: DELETE
if (post('mode') === 'delete') {
    header('Content-Type: application/json');

$deleteError = checkPermissionError('delete', $perm);
    if ($deleteError) {
        echo safeJsonEncode(['success' => false, 'message' => $deleteError]);
        exit();
    }

    $id = intval(post('id'));
    $name = post('name') ?? 'Unknown';

    // 1. Fetch Old Data BEFORE Deleting
    $oldData = null;
    $fetchOld = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $catTable . " WHERE id = ?");
    if ($fetchOld) {
        $fetchOld->bind_param("i", $id);
        if ($fetchOld->execute()) {
            $fetchOld->store_result(); // Buffer the result
            if ($fetchOld->num_rows > 0) {
                $fetchOld->bind_result($oId, $oName, $oCr, $oUp, $oCb, $oUb);
                $fetchOld->fetch();
                $oldData = [
                    'id' => $oId, 'name' => $oName, 'created_at' => $oCr, 
                    'updated_at' => $oUp, 'created_by' => $oCb, 'updated_by' => $oUb
                ];
            }
        }
        $fetchOld->close();
    }

    // Fallback: still log something useful even if old row can't be loaded
    if (empty($oldData)) {
        $oldData = [
            'id' => $id,
            'name' => $name,
        ];
    }

    // 2. Logical Pre-Validation & Hard Deletion
    try {
        $checkSql = "SELECT COUNT(*) FROM " . NOVEL . " WHERE category_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        
        if ($checkStmt) {
            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();
            $checkStmt->bind_result($novelCount);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($novelCount > 0) {
                echo safeJsonEncode(['success' => false, 'message' => '无法删除：该分类下还有关联的小说，请先移除小说。']);
                exit();
            }
        }

        $conn->begin_transaction();

        // [RESTORED] Step 2: Unlink tags from this category FIRST (Required for Hard Delete)
        $delLink = $conn->prepare("DELETE FROM $linkTable WHERE category_id = ?");
        if ($delLink) {
            $delLink->bind_param("i", $id);
            if (!$delLink->execute()) throw new Exception("标签解绑失败");
            $delLink->close();
        }

        // [REVERTED] Step 3: HARD DELETE the category 
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("i", $id); 
        if (!$stmt->execute()) throw new Exception($stmt->error);

        $conn->commit();

        if (function_exists('logAudit')) {
            logAudit([
                'page'           => $auditPage,
                'action'         => 'D',
                'action_message' => 'Deleted Category: ' . $name,
                'query'          => $deleteQuery,
                'query_table'    => $catTable,
                'user_id'        => sessionInt('user_id'),
                'record_id'      => $id,
                'record_name'    => $name,
                'old_value'      => $oldData
            ]);
        }
        echo safeJsonEncode(['success' => true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo safeJsonEncode(['success' => false, 'message' => '删除失败: ' . $e->getMessage()]);
    }
    exit();
}
// API URL for DataTables
$fullApiUrl = URL_NOVEL_CATS_API;

// Log that user viewed this page
if (function_exists('logAudit')) {
    logAudit([
        'page'           => $auditPage,
        'action'         => 'V',
        'action_message' => 'User viewed Category List',
        'query'          => $viewQuery,
        'query_table'    => $catTable,
        'user_id'        => sessionInt('user_id')
    ]);
}

// Flash message
$flashMsg = session('flash_msg');
$flashType = session('flash_type') ?: 'success';
if ($flashMsg !== '') {
    unsetSession('flash_msg');
    unsetSession('flash_type');
}


// HTML Output
if ($isEmbeddedInDashboard):
    $pageScripts = ['jquery.dataTables.min.js', 'dataTables.bootstrap.min.js', 'src/pages/category/js/category.js'];
?>
<link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
<div class="category-container">
    <div class="card category-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <div>
                <?php echo generateBreadcrumb($conn, $currentUrl); ?>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-layer-group"></i> <?php echo htmlspecialchars($pageName); ?></h4>
            </div>
            <?php if ($perm->add): ?>
            <a href="<?php echo URL_NOVEL_CATS_FORM; ?>" class="btn btn-primary desktop-add-btn"><i class="fa-solid fa-plus"></i> 新增分类</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($flashMsg): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flashMsg); ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <table id="categoryTable" class="table table-hover w-100" 
                   data-api-url="<?php echo $fullApiUrl; ?>?mode=data"
                   data-delete-url="<?php echo $fullApiUrl; ?>">
                <thead>
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th>分类名称</th>
                        <th>关联标签</th>
                        <th style="width:100px;">操作</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<?php $pageMetaKey = '/dashboard.php?view=categories'; ?>
<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="category-container">
    <div class="card category-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <div>
                <?php echo generateBreadcrumb($conn, $currentUrl); ?>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-layer-group"></i> <?php echo htmlspecialchars($pageName); ?></h4>
            </div>
            <?php if ($perm->add): ?>
            <a href="<?php echo URL_NOVEL_CATS_FORM; ?>" class="btn btn-primary desktop-add-btn"><i class="fa-solid fa-plus"></i> 新增分类</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($flashMsg): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flashMsg); ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <table id="categoryTable" class="table table-hover w-100" 
                   data-api-url="<?php echo $fullApiUrl; ?>?mode=data"
                   data-delete-url="<?php echo $fullApiUrl; ?>">
                <thead>
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th>分类名称</th>
                        <th>关联标签</th>
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
<script src="<?php echo SITEURL; ?>/src/pages/category/js/category.js"></script>
</body>
</html>
<?php endif; ?>

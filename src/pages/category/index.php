<?php
// Path: src/pages/category/index.php
require_once __DIR__ . '/../../../init.php';
defined('URL_HOME') || require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
        header('Content-Type: application/json');
        echo safeJsonEncode(["error" => "Unauthorized"]);
        exit();
    }
    header("Location: " . URL_LOGIN);
    exit();
}

$catTable  = NOVEL_CATEGORY;
$linkTable = CATEGORY_TAG;
$tagTable  = NOVEL_TAGS;
$auditPage = 'Category Management'; // Define audit page for logging
$viewQuery = "SELECT id, name FROM " . $catTable;
$deleteQuery = "DELETE FROM " . $catTable . " WHERE id = ?";

// 2. Embed Detection
$isEmbeddedInDashboard = isset($EMBED_CATS_PAGE) && $EMBED_CATS_PAGE === true;

// API: DATA FETCH
if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
    header('Content-Type: application/json');

    $start  = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $search = $_GET['search']['value'] ?? '';

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
        $editUrl = URL_USER_DASHBOARD . "?view=cat_form&id=" . $cat['id'];
        
        $actions = '
            <a href="'.$editUrl.'" class="btn btn-sm btn-outline-primary btn-action" title="编辑"><i class="fa-solid fa-pen"></i></a>
            <button class="btn btn-sm btn-outline-danger btn-action delete-btn" data-id="'.$cat['id'].'" data-name="'.htmlspecialchars($cat['name']).'" title="删除"><i class="fa-solid fa-trash"></i></button>
        ';
        $data[] = [htmlspecialchars($cat['name']), $tagHtml, $actions];
    }

    echo safeJsonEncode([
        "draw" => intval($_GET['draw']),
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $totalRecords,
        "data" => $data
    ]);
    exit();
}

// API: DELETE
if (isset($_POST['mode']) && $_POST['mode'] === 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $name = $_POST['name'] ?? 'Unknown';

    // [ADDED] 1. Fetch Old Data BEFORE Deleting
    // 1. Fetch Old Data BEFORE Deleting
    $oldData = null;
    $fetchOld = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $catTable . " WHERE id = ?");
    if ($fetchOld) {
        $fetchOld->bind_param("i", $id);
        if ($fetchOld->execute()) {
            $fetchOld->store_result(); // [CRITICAL FIX] Buffer the result
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

    // 2. Perform Delete
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if (function_exists('logAudit')) {
            logAudit([
                'page'           => $auditPage,
                'action'         => 'D',
                'action_message' => 'Deleted Category: ' . $name,
                'query'          => $deleteQuery,
                'query_table'    => $catTable,
                'user_id'        => $_SESSION['user_id'],
                'old_value'      => $oldData // [ADDED] Pass the old data here
            ]);
        }
        echo safeJsonEncode(['success' => true]);
    } else {
        echo safeJsonEncode(['success' => false, 'message' => '无法删除分类']);
    }
    exit();
}

// API URL for DataTables
$fullApiUrl = URL_NOVEL_CATS_API;

$pageTitle = "分类管理 - " . WEBSITE_NAME;

// [NEW] Log that user viewed this page
if (function_exists('logAudit')) {
    logAudit([
        'page'           => $auditPage,
        'action'         => 'V',
        'action_message' => 'User viewed Category List',
        'query'          => $viewQuery,
        'query_table'    => $catTable,
        'user_id'        => $_SESSION['user_id']
    ]);
}

// HTML Output
if ($isEmbeddedInDashboard): ?>
<div class="category-container">
    <div class="card category-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h4 class="m-0 text-primary"><i class="fa-solid fa-layer-group"></i> 分类管理</h4>
            <a href="<?php echo URL_USER_DASHBOARD; ?>?view=cat_form" class="btn btn-primary desktop-add-btn"><i class="fa-solid fa-plus"></i> 新增分类</a>
        </div>
        <div class="card-body">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    分类保存成功！ <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <table id="categoryTable" class="table table-hover w-100" 
                   data-api-url="<?php echo $fullApiUrl; ?>?mode=data"
                   data-delete-url="<?php echo $fullApiUrl; ?>">
                <thead><tr><th>分类名称</th><th>关联标签</th><th style="width:100px;">操作</th></tr></thead>
            </table>
        </div>
    </div>
</div>
<a href="<?php echo URL_USER_DASHBOARD; ?>?view=cat_form" class="btn btn-primary btn-add-mobile"><i class="fa-solid fa-plus fa-lg"></i></a>
<?php else: ?>
<?php endif; ?>
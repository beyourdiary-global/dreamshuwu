<?php
// Path: src/pages/category/index.php
require_once __DIR__ . '/../../../init.php';
defined('URL_HOME') || require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

// 2. Use Constants Directly (Defined in init.php)
$catTable  = NOVEL_CATEGORY;
$linkTable = CATEGORY_TAG;
$tagTable  = NOVEL_TAGS;

// ==========================================
//  API: DATA FETCH
// ==========================================
if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
    if (!function_exists('json_encode')) die('{"error": "PHP JSON extension missing"}');
    header('Content-Type: application/json');

    $start  = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $search = $_GET['search']['value'] ?? '';

    // Query 1: Fetch Categories
    $sql = "SELECT id, name, created_at FROM $catTable WHERE 1=1";
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

    $sql .= " ORDER BY created_at DESC LIMIT ?, ?";
    $mainParams[] = $start; $mainParams[] = $length;
    $mainTypes .= "ii";

    // Helper for safe binding
    function bindDynamicParams($stmt, $types, $params) {
        if (!empty($params)) {
            $bindParams = []; $bindParams[] = $types;
            for ($i = 0; $i < count($params); $i++) $bindParams[] = &$params[$i];
            call_user_func_array(array($stmt, 'bind_param'), $bindParams);
        }
    }

    // Execute Count
    $cStmt = $conn->prepare($countSql);
    bindDynamicParams($cStmt, $countTypes, $countParams);
    $cStmt->execute();
    $cStmt->bind_result($totalRecords);
    $cStmt->fetch();
    $cStmt->close();

    // Execute Main
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

    // Query 2: Fetch Tags
    $tagsMap = [];
    if (!empty($categories)) {
        $catIds = array_column($categories, 'id');
        $idList = implode(',', array_map('intval', $catIds));
        
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

    // Merge Data
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

        // [UPDATED] Use URL_CAT_ADD for edit link
        $editUrl = URL_CAT_ADD . "?id=" . $cat['id'];
        
        $actions = '
            <a href="'.$editUrl.'" class="btn btn-sm btn-outline-primary btn-action" title="编辑"><i class="fa-solid fa-pen"></i></a>
            <button class="btn btn-sm btn-outline-danger btn-action delete-btn" data-id="'.$cat['id'].'" data-name="'.htmlspecialchars($cat['name']).'" title="删除"><i class="fa-solid fa-trash"></i></button>
        ';
        $data[] = [htmlspecialchars($cat['name']), $tagHtml, $actions];
    }

    echo json_encode(["draw" => intval($_GET['draw']), "recordsTotal" => $totalRecords, "recordsFiltered" => $totalRecords, "data" => $data]);
    exit();
}

// API: Delete
if (isset($_POST['mode']) && $_POST['mode'] === 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $name = $_POST['name'] ?? 'Unknown';

    $stmt = $conn->prepare("DELETE FROM " . $catTable . " WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if (function_exists('logAudit')) {
            logAudit(['page'=>'Category','action'=>'D','action_message'=>'Deleted Category: '.$name,'query_table'=>$catTable,'user_id'=>$_SESSION['user_id']]);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '无法删除分类']);
    }
    exit();
}

$pageTitle = "分类管理 - " . WEBSITE_NAME;
$customCSS = 'dataTables.bootstrap.min.css';
?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="category-container">
    <div class="card category-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h4 class="m-0 text-primary"><i class="fa-solid fa-layer-group"></i> 分类管理</h4>
            <a href="<?php echo URL_CAT_ADD; ?>" class="btn btn-primary desktop-add-btn"><i class="fa-solid fa-plus"></i> 新增分类</a>
        </div>
        <div class="card-body">
            <table id="categoryTable" class="table table-hover w-100">
                <thead><tr><th>分类名称</th><th>关联标签</th><th style="width:100px;">操作</th></tr></thead>
            </table>
        </div>
    </div>
</div>
<a href="<?php echo URL_CAT_ADD; ?>" class="btn btn-primary btn-add-mobile"><i class="fa-solid fa-plus fa-lg"></i></a>

<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/category.js"></script>
</body>
</html>
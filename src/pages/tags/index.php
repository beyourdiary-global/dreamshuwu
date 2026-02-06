<?php
// Path: src/pages/tags/index.php (3 levels deep)
require_once __DIR__ . '/../../../init.php';
defined('URL_HOME') || require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$dbTable = defined('NOVEL_TAGS') ? NOVEL_TAGS : 'novel_tag';

if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
    header('Content-Type: application/json');
    
    $start  = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $search = $_GET['search']['value'] ?? '';

    $sql = "SELECT id, name FROM " . $dbTable . " WHERE 1=1";
    $countSql = "SELECT COUNT(*) FROM " . $dbTable . " WHERE 1=1";
    
    // Separate params for safety
    $mainParams = []; $mainTypes = "";
    $countParams = []; $countTypes = "";

    if (!empty($search)) {
        $term = "%" . $search . "%";
        $sql .= " AND name LIKE ?";
        $countSql .= " AND name LIKE ?";
        
        $mainParams[] = $term; $countParams[] = $term;
        $mainTypes .= "s"; $countTypes .= "s";
    }

    // Sort & Limit (Main Query Only)
    $sql .= " ORDER BY created_at DESC LIMIT ?, ?";
    $mainParams[] = $start; $mainParams[] = $length; 
    $mainTypes .= "ii";

    // 1. Count
    $cStmt = $conn->prepare($countSql);
    if (!empty($countParams)) $cStmt->bind_param($countTypes, ...$countParams);
    $cStmt->execute();
    $cStmt->bind_result($totalRecords);
    $cStmt->fetch();
    $cStmt->close();

    // 2. Fetch Data
    $stmt = $conn->prepare($sql);
    if (!empty($mainParams)) $stmt->bind_param($mainTypes, ...$mainParams);
    $stmt->execute();
    
    $meta = $stmt->result_metadata();
    $row = []; $bindResult = [];
    while ($field = $meta->fetch_field()) { $bindResult[] = &$row[$field->name]; }
    call_user_func_array(array($stmt, 'bind_result'), $bindResult);

    $data = [];
    while ($stmt->fetch()) {
        $safeRow = []; foreach($row as $k => $v) { $safeRow[$k] = $v; }
        
        $editUrl = "form.php?id=" . $safeRow['id'];
        $actions = '
            <a href="'.$editUrl.'" class="btn btn-sm btn-outline-primary btn-action" title="编辑"><i class="fa-solid fa-pen"></i></a>
            <button class="btn btn-sm btn-outline-danger btn-action delete-btn" data-id="'.$safeRow['id'].'" data-name="'.htmlspecialchars($safeRow['name']).'" title="删除"><i class="fa-solid fa-trash"></i></button>
        ';
        $data[] = [htmlspecialchars($safeRow['name']), $actions];
    }
    $stmt->close();

    echo json_encode(["draw" => intval($_GET['draw']), "recordsTotal" => $totalRecords, "recordsFiltered" => $totalRecords, "data" => $data]);
    exit();
}

// DELETE API
if (isset($_POST['mode']) && $_POST['mode'] === 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $tagName = $_POST['name'] ?? 'Unknown';

    $stmt = $conn->prepare("DELETE FROM " . $dbTable . " WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if (function_exists('logAudit')) {
            logAudit(['page'=>'Tag List','action'=>'D','action_message'=>'Deleted Tag: '.$tagName,'query_table'=>$dbTable,'user_id'=>$_SESSION['user_id']]);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting tag.']);
    }
    exit();
}

$pageTitle = "小说标签 - " . WEBSITE_NAME;
?>
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
            <a href="form.php" class="btn btn-primary desktop-add-btn"><i class="fa-solid fa-plus"></i> 新增标签</a>
        </div>
        <div class="card-body">
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    标签保存成功！ <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <table id="tagTable" class="table table-hover w-100">
                <thead><tr><th>标签名称</th><th style="width:100px;">操作</th></tr></thead>
            </table>
        </div>
    </div>
</div>
<a href="form.php" class="btn btn-primary btn-add-mobile"><i class="fa-solid fa-plus fa-lg"></i></a>
<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/tag.js"></script>
</body>
</html>
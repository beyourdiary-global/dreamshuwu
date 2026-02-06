<?php
// Path: src/pages/tags/index.php
require_once __DIR__ . '/../../../init.php';
defined('URL_HOME') || require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN); exit();
}

$dbTable = defined('NOVEL_TAGS') ? NOVEL_TAGS : 'novel_tag';
// When included from the user dashboard, we only render the inner tag card
$isEmbeddedInDashboard = isset($EMBED_TAGS_PAGE) && $EMBED_TAGS_PAGE === true;

if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
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
                return '"' . addslashes($data) . '"';
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

    if (!isset($conn) || !$conn) {
        sendTagTableError('Database connection is not available.');
    }
    
    $start  = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $search = $_GET['search']['value'] ?? '';

    $sql = "SELECT id, name FROM " . $dbTable . " WHERE 1=1";
    $countSql = "SELECT COUNT(*) FROM " . $dbTable . " WHERE 1=1";
    
    $mainParams = []; $mainTypes = "";
    $countParams = []; $countTypes = "";

    if (!empty($search)) {
        $term = "%" . $search . "%";
        $sql .= " AND name LIKE ?";
        $countSql .= " AND name LIKE ?";
        
        $mainParams[] = $term; $countParams[] = $term;
        $mainTypes .= "s"; $countTypes .= "s";
    }

    // Order by ID (safe even if created_at column doesn't exist)
    $sql .= " ORDER BY id DESC LIMIT ?, ?";
    $mainParams[] = $start; $mainParams[] = $length; 
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

    // 1. Count
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

    // 2. Fetch Data
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
    
    $meta = $stmt->result_metadata();
    $row = []; $bindResult = [];
    while ($field = $meta->fetch_field()) { $bindResult[] = &$row[$field->name]; }
    call_user_func_array(array($stmt, 'bind_result'), $bindResult);

    $data = [];
    while ($stmt->fetch()) {
        $safeRow = []; foreach($row as $k => $v) { $safeRow[$k] = $v; }
        
        // Always route tag edit through the user dashboard (embedded form)
        $editUrl = URL_USER_DASHBOARD . '?view=tag_form&id=' . $safeRow['id'];
        $actions = '
            <a href="'.$editUrl.'" class="btn btn-sm btn-outline-primary btn-action" title="编辑"><i class="fa-solid fa-pen"></i></a>
            <button class="btn btn-sm btn-outline-danger btn-action delete-btn" data-id="'.$safeRow['id'].'" data-name="'.htmlspecialchars($safeRow['name']).'" title="删除"><i class="fa-solid fa-trash"></i></button>
        ';
        $data[] = [htmlspecialchars($safeRow['name']), $actions];
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

// DELETE API
if (isset($_POST['mode']) && $_POST['mode'] === 'delete') {
    header('Content-Type: application/json');
    $id = intval($_POST['id']);
    $tagName = $_POST['name'] ?? 'Unknown';

    // 1. Load existing row so we can log old_value in audit trail
    $oldData = null;
    $selectSql = "SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $dbTable . " WHERE id = ?";
    if ($sel = $conn->prepare($selectSql)) {
        $sel->bind_param("i", $id);
        if ($sel->execute()) {
            $sel->bind_result($rId, $rName, $rCreatedAt, $rUpdatedAt, $rCreatedBy, $rUpdatedBy);
            if ($sel->fetch()) {
                $oldData = [
                    'id'         => $rId,
                    'name'       => $rName,
                    'created_at' => $rCreatedAt,
                    'updated_at' => $rUpdatedAt,
                    'created_by' => $rCreatedBy,
                    'updated_by' => $rUpdatedBy,
                ];
            }
        }
        $sel->close();
    }

    // 2. Perform the actual delete
    $stmt = $conn->prepare("DELETE FROM " . $dbTable . " WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if (function_exists('logAudit')) {
            logAudit([
                'page'           => 'Tag List',
                'action'         => 'D',
                'action_message' => 'Deleted Tag: ' . $tagName,
                'query'          => "DELETE FROM " . $dbTable . " WHERE id = ?",
                'query_table'    => $dbTable,
                'user_id'        => $_SESSION['user_id'],
                'old_value'      => $oldData,
                'new_value'      => null,
            ]);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting tag.']);
    }
    exit();
}

$pageTitle = "小说标签 - " . WEBSITE_NAME;

// If embedded in dashboard, only render the inner card markup (no full HTML shell)
if ($isEmbeddedInDashboard): ?>
    <div class="tag-container">
        <div class="card tag-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h4 class="m-0 text-primary"><i class="fa-solid fa-tags"></i> 标签管理</h4>
                <a href="<?php echo URL_USER_DASHBOARD; ?>?view=tag_form" class="btn btn-primary desktop-add-btn"><i class="fa-solid fa-plus"></i> 新增标签</a>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        标签保存成功！ <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
    <a href="<?php echo URL_USER_DASHBOARD; ?>?view=tag_form" class="btn btn-primary btn-add-mobile"><i class="fa-solid fa-plus fa-lg"></i></a>
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
            <a href="<?php echo URL_USER_DASHBOARD; ?>?view=tag_form" class="btn btn-primary desktop-add-btn"><i class="fa-solid fa-plus"></i> 新增标签</a>
        </div>
        <div class="card-body">
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'saved'): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    标签保存成功！ <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
<a href="<?php echo URL_USER_DASHBOARD; ?>?view=tag_form" class="btn btn-primary btn-add-mobile"><i class="fa-solid fa-plus fa-lg"></i></a>
<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/tag.js"></script>
</body>
</html>
<?php endif; ?>
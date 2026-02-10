<?php
// Path: src/pages/audit-log.php
require_once __DIR__ . '/../../init.php';
require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$auditActions = ['V' => 'View', 'E' => 'Edit', 'A' => 'Add', 'D' => 'Delete'];

if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
    header('Content-Type: application/json');
    $debugAudit = isset($_GET['debug']) && $_GET['debug'] === '1';

    if (!function_exists('sendAuditTableError')) {
        function sendAuditTableError($message) {
            echo safeJsonEncode([
                "draw" => isset($_GET['draw']) ? (int)$_GET['draw'] : 0,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => $message,
            ]);
            exit();
        }
    }

    if (!isset($conn) || !$conn) {
        sendAuditTableError('Database connection unavailable.');
    }

    // [FIX 1] Select Specific Columns (No SELECT *)
    // This allows us to safely use bind_result below without guessing column order
    $columns = "page, action, action_message, query, old_value, new_value, user_id, created_at";
    $sql = "SELECT $columns FROM " . AUDIT_LOG . " WHERE 1=1";
    $countSql = "SELECT COUNT(*) FROM " . AUDIT_LOG . " WHERE 1=1";
    
    $mainParams = []; $mainTypes = "";
    $countParams = []; $countTypes = "";

    // 1. Search Logic
    if (!empty($_GET['search']['value'])) {
        $search = "%" . $_GET['search']['value'] . "%";
        $userSubQuery = "(SELECT id FROM " . USR_LOGIN . " WHERE name LIKE ?)";
        $clause = " AND (page LIKE ? OR action_message LIKE ? OR user_id IN $userSubQuery)";
        
        $sql .= $clause;
        $countSql .= $clause;
        
        $searchParams = [$search, $search, $search];
        foreach($searchParams as $p) { $mainParams[] = $p; $countParams[] = $p; }
        $mainTypes .= "sss"; $countTypes .= "sss";
    }
    
    // 2. Filter Action
    if (!empty($_GET['filter_action'])) {
        $clause = " AND action = ?";
        $sql .= $clause;
        $countSql .= $clause;
        $mainParams[] = $_GET['filter_action']; $countParams[] = $_GET['filter_action'];
        $mainTypes .= "s"; $countTypes .= "s";
    }

    // Helper
    if (!function_exists('bindDynamicParams')) {
        function bindDynamicParams($stmt, $types, $params) {
            if (!empty($params)) {
                $bindParams = [$types];
                foreach ($params as $k => $v) $bindParams[] = &$params[$k];
                call_user_func_array([$stmt, 'bind_param'], $bindParams);
            }
        }
    }

    // 3. Count Query
    $countStmt = $conn->prepare($countSql);
    bindDynamicParams($countStmt, $countTypes, $countParams);
    $countStmt->execute();
    $countStmt->bind_result($totalRecords);
    $countStmt->fetch();
    $countStmt->close();

    // 4. Sort & Limit
    $sortCols = ['page', 'action', 'action_message', 'user_id', 'created_at', 'created_at']; 
    $colIdx = $_GET['order'][0]['column'] ?? 5; 
    $realColIdx = ($colIdx > 0) ? $colIdx - 1 : 4;
    $colName = $sortCols[$realColIdx] ?? 'created_at';
    $dir = ($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $sql .= " ORDER BY " . $colName . " " . $dir;

    $start = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $sql .= " LIMIT ?, ?";
    $mainParams[] = $start; $mainParams[] = $length;
    $mainTypes .= "ii";

   // 5. Execute Main
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Audit log data query prepare failed: " . $conn->error);
        sendAuditTableError('System error while loading audit log.');
    }
    bindDynamicParams($stmt, $mainTypes, $mainParams);
    
    if (!$stmt->execute()) {
        error_log("Audit log data query execute failed: " . $stmt->error);
        sendAuditTableError('System error while loading audit log.');
    }
    
    // Use store_result() + dynamic bind_result()
    $stmt->store_result(); 

    $meta = $stmt->result_metadata();
    $bindVars = [];
    $row = [];

    while ($field = $meta->fetch_field()) {
        $bindVars[] = &$row[$field->name];
    }

    call_user_func_array(array($stmt, 'bind_result'), $bindVars);

    $results = [];
    while ($stmt->fetch()) {
        // [FIX] Use array_merge to copy values and break references in one line
        $results[] = array_merge([], $row);
    }
    $stmt->close();

    // 6. User Mapping

    // 6. User Mapping
    $userIds = array_unique(array_column($results, 'user_id'));
    $userMap = [];
    if (!empty($userIds)) {
        $idList = implode(',', array_map('intval', $userIds));
        $uRes = $conn->query("SELECT id, name FROM " . USR_LOGIN . " WHERE id IN ($idList)");
        if($uRes) while ($uRow = $uRes->fetch_assoc()) $userMap[$uRow['id']] = $uRow['name'];
    }

    // 7. Output Data
    $data = [];
    foreach ($results as $cRow) {
        $badgeClass = 'secondary';
        $actCode = trim($cRow['action'] ?? '');
        
        switch ($actCode) {
            case 'V': $badgeClass = 'info'; break;
            case 'E': $badgeClass = 'warning'; break;
            case 'D': $badgeClass = 'danger'; break;
            case 'A': $badgeClass = 'success'; break;
        }

        $actionLabel = $auditActions[$actCode] ?? ($actCode ?: "Record");
        
        // Date Formatting
        $dateStr = ''; $timeStr = '';
        if (!empty($cRow['created_at'])) {
            $ts = strtotime($cRow['created_at']);
            $dateStr = date('Y-m-d', $ts);
            $timeStr = date('H:i:s', $ts);
        }

        // --- JSON DECODING FIX ---
        // Safely decode Old Value
        $decodedOld = null;
        if ($cRow['old_value'] !== null) {
            $json = json_decode($cRow['old_value'], true);
            $decodedOld = (json_last_error() === JSON_ERROR_NONE) ? $json : $cRow['old_value'];
        }

        // Safely decode New Value
        $decodedNew = null;
        if ($cRow['new_value'] !== null) {
            $json = json_decode($cRow['new_value'], true);
            $decodedNew = (json_last_error() === JSON_ERROR_NONE) ? $json : $cRow['new_value'];
        }

        $details = [
            'query' => $cRow['query'] ?? '',
            'old'   => $decodedOld,
            'new'   => $decodedNew,
        ];

        $data[] = [
            'page'    => htmlspecialchars($cRow['page']),
            'action'  => '<span class="badge badge-custom badge-'.$badgeClass.'">' . htmlspecialchars($actionLabel) . '</span>',
            'message' => htmlspecialchars($cRow['action_message']),
            'user'    => htmlspecialchars(($userMap[$cRow['user_id']] ?? 'Unknown') . " (ID:" . $cRow['user_id'] . ")"),
            'date'    => $dateStr,
            'time'    => $timeStr,
            'details' => $details,
        ];
    }

    echo safeJsonEncode([
        "draw" => intval($_GET['draw'] ?? 0),
        "recordsTotal" => (int) $totalRecords,
        "recordsFiltered" => (int) $totalRecords,
        "data" => $data
    ]);
    exit();
}

$pageTitle = "System Audit Log - " . WEBSITE_NAME;
?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/responsive.bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/audit-log.css?v=<?php echo time(); ?>">
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="container-fluid mt-4" style="max-width: 1400px;">
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="m-0 text-primary"><i class="fa-solid fa-file-shield"></i> System Audit Log</h4>
            <div class="d-flex align-items-center gap-2">
                <label class="text-muted small m-0">Filter:</label>
                <select id="actionFilter" class="form-select form-select-sm" style="width: 150px;">
                    <option value="">All Actions</option>
                    <?php foreach ($auditActions as $code => $label): echo "<option value='$code'>$label</option>"; endforeach; ?>
                </select>
            </div>
        </div>
        <div class="card-body">
            <table id="auditTable" class="table table-hover w-100" data-api-url="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?mode=data">
                <thead><tr><th style="width: 30px;"></th><th>Page</th><th>Action</th><th>Message</th><th>User</th><th>Date</th><th>Time</th></tr></thead>
            </table>
        </div>
    </div>
</div>
<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/audit-log.js?v=<?php echo time(); ?>"></script>
</body>
</html>
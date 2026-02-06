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
    // [FIX] Removed the manual JSON check that was causing false positives
    header('Content-Type: application/json');

    $sql = "SELECT * FROM " . AUDIT_LOG . " WHERE 1=1";
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

    // [FIX] Safe Parameter Binding Helper
    function bindDynamicParams($stmt, $types, $params) {
        if (!empty($params)) {
            $bindParams = [];
            $bindParams[] = $types;
            for ($i = 0; $i < count($params); $i++) {
                $bindParams[] = &$params[$i];
            }
            call_user_func_array(array($stmt, 'bind_param'), $bindParams);
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
    $realColIdx = ($colIdx > 0) ? $colIdx - 1 : 4; // Adjust for hidden column
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
    bindDynamicParams($stmt, $mainTypes, $mainParams);
    $stmt->execute();
    
    $results = [];
    $meta = $stmt->result_metadata();
    $row = []; $bindResult = [];
    while ($field = $meta->fetch_field()) { $bindResult[] = &$row[$field->name]; }
    call_user_func_array(array($stmt, 'bind_result'), $bindResult);
    while ($stmt->fetch()) {
        $cRow = []; foreach($row as $k => $v) { $cRow[$k] = $v; }
        $results[] = $cRow;
    }
    $stmt->close();

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

        // [FIX] Ensure Action Text is never empty
        $actionLabel = $auditActions[$actCode] ?? $actCode;
        if (empty($actionLabel)) $actionLabel = "Record"; 

        $data[] = [
            'page'    => htmlspecialchars($cRow['page']),
            'action'  => '<span class="badge badge-custom badge-'.$badgeClass.'">' . htmlspecialchars($actionLabel) . '</span>',
            'message' => htmlspecialchars($cRow['action_message']),
            'user'    => htmlspecialchars(($userMap[$cRow['user_id']] ?? 'Unknown') . " (ID:" . $cRow['user_id'] . ")"),
            'date'    => (new DateTime($cRow['created_at']))->format('Y-m-d'),
            'time'    => (new DateTime($cRow['created_at']))->format('H:i:s')
        ];
    }

    echo json_encode(["draw" => intval($_GET['draw']), "recordsTotal" => $totalRecords, "recordsFiltered" => $totalRecords, "data" => $data]);
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
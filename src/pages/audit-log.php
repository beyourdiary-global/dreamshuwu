<?php
require_once __DIR__ . '/../../init.php';
require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$auditActions = [
    'V' => 'View', 'E' => 'Edit', 'A' => 'Add', 'D' => 'Delete'
];

// ==========================================
//  BACKEND: JSON API Mode
// ==========================================
if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
    if (!function_exists('json_encode')) {
        die('{"error": "PHP JSON extension is not enabled."}');
    }
    header('Content-Type: application/json');

    // ---------------------------------------------------------
    // QUERY 1: Fetch Audit Logs
    // ---------------------------------------------------------
    
    $sql = "SELECT * FROM " . AUDIT_LOG . " WHERE 1=1";
    $countSql = "SELECT COUNT(*) FROM " . AUDIT_LOG . " WHERE 1=1";
    
    $params = []; 
    $types = "";

    // Search Logic
    if (!empty($_GET['search']['value'])) {
        $search = "%" . $_GET['search']['value'] . "%";
        
        // Subquery for Name Search
        $userSubQuery = "(SELECT id FROM " . USR_LOGIN . " WHERE name LIKE ?)";
        
        $clause = " AND (page LIKE ? OR action_message LIKE ? OR user_id IN $userSubQuery)";
        
        $sql .= $clause;
        $countSql .= $clause;
        
        array_push($params, $search, $search, $search);
        $types .= "sss";
    }
    
    // Filter Action
    if (!empty($_GET['filter_action'])) {
        $clause = " AND action = ?";
        $sql .= $clause;
        $countSql .= $clause;
        array_push($params, $_GET['filter_action']);
        $types .= "s";
    }

    // --- HELPER: SAFE DYNAMIC BINDING ---
    // This fixes the "Cannot pass parameter 2 by reference" Fatal Error
    function bindDynamicParams($stmt, $types, $params) {
        if (!empty($params)) {
            $bindParams = [];
            $bindParams[] = $types;
            // Loop and create references for each parameter
            for ($i = 0; $i < count($params); $i++) {
                $bindParams[] = &$params[$i];
            }
            call_user_func_array(array($stmt, 'bind_param'), $bindParams);
        }
    }

    // 1. Execute Count
    $countStmt = $conn->prepare($countSql);
    bindDynamicParams($countStmt, $types, $params);
    $countStmt->execute();
    $countStmt->bind_result($totalRecords);
    $countStmt->fetch();
    $countStmt->close();

    // 2. Sorting
    $sortCols = ['page', 'action', 'action_message', 'user_id', 'created_at', 'created_at']; 
    $colIdx = $_GET['order'][0]['column'] ?? 5; 
    $realColIdx = ($colIdx > 0) ? $colIdx - 1 : 4; 
    $colName = $sortCols[$realColIdx] ?? 'created_at';
    $dir = ($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    
    $sql .= " ORDER BY " . $colName . " " . $dir;

    // 3. Pagination
    $start = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $sql .= " LIMIT ?, ?";
    array_push($params, $start, $length);
    $types .= "ii";

    // 4. Execute Main Query
    $stmt = $conn->prepare($sql);
    bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    
    // Fetch Results
    $results = [];
    $meta = $stmt->result_metadata();
    $row = []; 
    $bindResultParams = [];
    while ($field = $meta->fetch_field()) { 
        $bindResultParams[] = &$row[$field->name]; 
    }
    call_user_func_array(array($stmt, 'bind_result'), $bindResultParams);
    
    while ($stmt->fetch()) {
        $cRow = [];
        foreach($row as $key => $val) { $cRow[$key] = $val; }
        $results[] = $cRow;
    }
    $stmt->close();

    // ---------------------------------------------------------
    // QUERY 2: Fetch User Names (Batch)
    // ---------------------------------------------------------
    
    $userIds = [];
    foreach ($results as $r) {
        if (!empty($r['user_id'])) $userIds[] = (int)$r['user_id'];
    }
    $userIds = array_unique($userIds);
    
    $userMap = [];
    if (!empty($userIds)) {
        $idList = implode(',', $userIds);
        $userSql = "SELECT id, name FROM " . USR_LOGIN . " WHERE id IN ($idList)";
        $uRes = $conn->query($userSql);
        if ($uRes) {
            while ($uRow = $uRes->fetch_assoc()) {
                $userMap[$uRow['id']] = $uRow['name'];
            }
        }
    }

    // ---------------------------------------------------------
    // MERGE & OUTPUT
    // ---------------------------------------------------------
    $data = [];
    foreach ($results as $cRow) {
        try {
            $dt = new DateTime($cRow['created_at']);
            $dateStr = $dt->format('Y-m-d');
            $timeStr = $dt->format('H:i:s');
        } catch (Exception $e) { $dateStr = '-'; $timeStr = '-'; }
        
        $actionLabel = $auditActions[$cRow['action']] ?? $cRow['action'];
        
        // PHP 7 Compatible Switch
        $badgeClass = 'secondary';
        switch ($cRow['action']) {
            case 'V': $badgeClass = 'info'; break;
            case 'E': $badgeClass = 'warning'; break;
            case 'D': $badgeClass = 'danger'; break;
            case 'A': $badgeClass = 'success'; break;
        }

        $userName = $userMap[$cRow['user_id']] ?? 'Unknown';

        $data[] = [
            'page'    => htmlspecialchars($cRow['page']),
            'action'  => '<span class="badge badge-custom badge-'.$badgeClass.'">' . $actionLabel . '</span>',
            'message' => htmlspecialchars($cRow['action_message']),
            'user'    => htmlspecialchars($userName . " (ID:" . $cRow['user_id'] . ")"),
            'date'    => $dateStr,
            'time'    => $timeStr,
            'details' => [
                'query'   => $cRow['query'],
                'old'     => $cRow['old_value'],
                'new'     => $cRow['new_value'],
                'changes' => $cRow['changes']
            ]
        ];
    }

    echo json_encode([
        "draw" => intval($_GET['draw']),
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $totalRecords, 
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
                    <?php foreach ($auditActions as $code => $label): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="card-body">
            <table id="auditTable" class="table table-hover w-100" data-api-url="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?mode=data">
                <thead>
                    <tr>
                        <th style="width: 30px;"></th> 
                        <th>Page</th>
                        <th>Action</th>
                        <th>Message</th>
                        <th>User</th>
                        <th>Date</th>
                        <th>Time</th>
                    </tr>
                </thead>
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
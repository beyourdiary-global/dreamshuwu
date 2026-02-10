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

    // ==========================================
    // [SOLUTION] Use mysqli_query + fetch_assoc instead of prepared statements
    // This avoids the JSON + bind_result() issue completely
    // ==========================================
    
    $whereClauses = [];

    // 1. Search Logic (Split into 2 calls)
    if (!empty($_GET['search']['value'])) {
        $search = $conn->real_escape_string($_GET['search']['value']);
        
        // Call A: Find User IDs first
        $userIds = [];
        $uSql = "SELECT id FROM " . USR_LOGIN . " WHERE name LIKE '%{$search}%'";
        $uRes = $conn->query($uSql);
        
        if ($uRes) {
            while ($row = $uRes->fetch_assoc()) {
                $userIds[] = $row['id'];
            }
            $uRes->free();
        }

        // Call B: Build the main WHERE clause
        $orParts = [];
        $orParts[] = "page LIKE '%{$search}%'";
        $orParts[] = "action_message LIKE '%{$search}%'";
        
        // Only add the user_id check if we actually found matching users
        if (!empty($userIds)) {
            $idList = implode(',', array_map('intval', $userIds));
            $orParts[] = "user_id IN ({$idList})";
        }
        
        // Combine them: (page OR message OR user_id)
        $whereClauses[] = "(" . implode(' OR ', $orParts) . ")";
    }
    
    // 2. Filter Action
    if (!empty($_GET['filter_action'])) {
        $action = $conn->real_escape_string($_GET['filter_action']);
        $whereClauses[] = "action = '{$action}'";
    }

    // Build WHERE SQL
    $whereSQL = empty($whereClauses) ? '' : ' AND ' . implode(' AND ', $whereClauses);

    // 3. Count Query
    $countSql = "SELECT COUNT(*) as total FROM " . AUDIT_LOG . " WHERE 1=1" . $whereSQL;
    $countResult = $conn->query($countSql);
    
    if (!$countResult) {
        sendAuditTableError('Count query failed: ' . $conn->error);
    }
    
    $countRow = $countResult->fetch_assoc();
    $totalRecords = $countRow['total'];
    $countResult->free();

    // 4. Sort & Limit
    $sortCols = ['page', 'action', 'action_message', 'user_id', 'created_at', 'created_at']; 
    $colIdx = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 5;
    $realColIdx = ($colIdx > 0) ? $colIdx - 1 : 4;
    $colName = isset($sortCols[$realColIdx]) ? $sortCols[$realColIdx] : 'created_at';
    $dir = (isset($_GET['order'][0]['dir']) && $_GET['order'][0]['dir'] === 'asc') ? 'ASC' : 'DESC';

    $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
    $length = isset($_GET['length']) ? (int)$_GET['length'] : 10;

    // 5. Main Query - Using Direct Query
    $sql = "SELECT page, action, action_message, query, old_value, new_value, user_id, created_at 
            FROM " . AUDIT_LOG . " 
            WHERE 1=1" . $whereSQL . " 
            ORDER BY {$colName} {$dir} 
            LIMIT {$start}, {$length}";

    $result = $conn->query($sql);
    
    if (!$result) {
        sendAuditTableError('Main query failed: ' . $conn->error);
    }

    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $result->free();

    // 6. User Mapping
    $userIds = array_unique(array_filter(array_column($results, 'user_id')));
    $userMap = [];
    if (!empty($userIds)) {
        $idList = implode(',', array_map('intval', $userIds));
        $uRes = $conn->query("SELECT id, name FROM " . USR_LOGIN . " WHERE id IN ({$idList})");
        if ($uRes) {
            while ($uRow = $uRes->fetch_assoc()) {
                $userMap[$uRow['id']] = $uRow['name'];
            }
            $uRes->free();
        }
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

        $actionLabel = isset($auditActions[$actCode]) ? $auditActions[$actCode] : ($actCode ?: "Record");
        
        // Date Formatting
        $dateStr = ''; $timeStr = '';
        if (!empty($cRow['created_at'])) {
            $ts = strtotime($cRow['created_at']);
            $dateStr = date('Y-m-d', $ts);
            $timeStr = date('H:i:s', $ts);
        }

        // --- JSON DECODING ---
        $decodedOld = null;
        if (!empty($cRow['old_value'])) {
            $json = json_decode($cRow['old_value'], true);
            $decodedOld = (json_last_error() === JSON_ERROR_NONE) ? $json : $cRow['old_value'];
        }

        $decodedNew = null;
        if (!empty($cRow['new_value'])) {
            $json = json_decode($cRow['new_value'], true);
            $decodedNew = (json_last_error() === JSON_ERROR_NONE) ? $json : $cRow['new_value'];
        }

        $details = [
            'query' => $cRow['query'] ?? '',
            'old'   => $decodedOld,
            'new'   => $decodedNew,
        ];

        $data[] = [
            'page'    => htmlspecialchars($cRow['page'] ?? ''),
            'action'  => '<span class="badge badge-custom badge-'.$badgeClass.'">' . htmlspecialchars($actionLabel) . '</span>',
            'message' => htmlspecialchars($cRow['action_message'] ?? ''),
            'user'    => htmlspecialchars((isset($userMap[$cRow['user_id']]) ? $userMap[$cRow['user_id']] : 'Unknown') . " (ID:" . $cRow['user_id'] . ")"),
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
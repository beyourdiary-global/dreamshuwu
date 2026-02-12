<?php
// Path: src/pages/audit-log.php

require_once dirname(__DIR__, 2) . '/common.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$auditActions = ['V' => 'View', 'E' => 'Edit', 'A' => 'Add', 'D' => 'Delete'];

if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0); 
    error_reporting(0);

    // FIX: Handle Data Types "In Front" (Sanitize Early)
    // We force the types here so we don't need complex binding later.
    
    $draw   = (int)($_GET['draw'] ?? 0);
    $start  = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    
    // Clean strings immediately
    $searchRaw = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
    $actionRaw = isset($_GET['filter_action']) ? trim($_GET['filter_action']) : '';

    // Helper: Send Error
    if (!function_exists('sendAuditTableError')) {
        function sendAuditTableError($message, $draw) {
            echo safeJsonEncode(["draw"=>$draw, "recordsTotal"=>0, "recordsFiltered"=>0, "data"=>[], "error"=>$message]);
            exit();
        }
    }

    if (!isset($conn) || !$conn) sendAuditTableError('Database connection unavailable.', $draw);
    if (method_exists($conn, 'set_charset')) {
        $conn->set_charset('utf8mb4');
    }

    // STEP 2: PREPARE FILTERS (2-Query Logic)
    
    $whereClauses = [];

    // A. SEARCH (Find User IDs -> Then Filter Log)
    if ($searchRaw !== '') {
        $safeSearch = $conn->real_escape_string($searchRaw);
        
        // Query 1: Get User IDs
        $userIds = [];
        $uSql = "SELECT id FROM " . USR_LOGIN . " WHERE name LIKE '%{$safeSearch}%'";
        $uRes = $conn->query($uSql);
        if ($uRes) {
            while ($row = $uRes->fetch_assoc()) $userIds[] = (int)$row['id'];
            $uRes->free();
        }

        // Query 2: Build Clause
        $orParts = [];
        $orParts[] = "page LIKE '%{$safeSearch}%'";
        $orParts[] = "action_message LIKE '%{$safeSearch}%'";
        if (!empty($userIds)) {
            $idList = implode(',', $userIds);
            $orParts[] = "user_id IN ({$idList})";
        }
        $whereClauses[] = "(" . implode(' OR ', $orParts) . ")";
    }
    
    // B. FILTER ACTION
    if ($actionRaw !== '') {
        $safeAction = $conn->real_escape_string($actionRaw);
        $whereClauses[] = "action = '{$safeAction}'";
    }

    $whereSQL = empty($whereClauses) ? '' : ' AND ' . implode(' AND ', $whereClauses);

    // STEP 3: EXECUTE (Direct Query - Treat as String)
    
    // 1. Count
    $countSql = "SELECT COUNT(*) as total FROM " . AUDIT_LOG . " WHERE 1=1" . $whereSQL;
    $countResult = $conn->query($countSql);
    if (!$countResult) sendAuditTableError('Count Failed: ' . $conn->error, $draw);
    $countRow = $countResult->fetch_assoc();
    $totalRecords = (int)$countRow['total'];
    $countResult->free();

    // 2. Sort Logic
    $sortCols = ['page', 'action', 'action_message', 'user_id', 'created_at', 'created_at']; 
    $colIdx = (int)($_GET['order'][0]['column'] ?? 5); 
    $colName = $sortCols[($colIdx > 0) ? $colIdx - 1 : 4] ?? 'created_at';
    $dir = ($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

    // 3. Fetch Data (Directly use sanitized $start/$length)
    $sql = "SELECT page, action, action_message, query, old_value, new_value, user_id, created_at FROM " . AUDIT_LOG . " WHERE 1=1" . $whereSQL . " ORDER BY {$colName} {$dir} LIMIT {$start}, {$length}";

    $result = $conn->query($sql);
    if (!$result) sendAuditTableError('Data Failed: ' . $conn->error, $draw);

    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $result->free();

    // STEP 4: CONVERT IN PHP

    // User Map
    $userIds = array_unique(array_column($results, 'user_id'));
    $userMap = [];
    if (!empty($userIds)) {
        $uList = implode(',', array_map('intval', $userIds));
        $uRes = $conn->query("SELECT id, name FROM " . USR_LOGIN . " WHERE id IN ({$uList})");
        if($uRes) while ($uRow = $uRes->fetch_assoc()) $userMap[$uRow['id']] = $uRow['name'];
    }

    try {
        $data = [];
        foreach ($results as $cRow) {
            $actCode = trim((string)($cRow['action'] ?? ''));
            $badgeClass = ($actCode=='D')?'danger':(($actCode=='E')?'warning':(($actCode=='A')?'success':'info'));
            
            $dateStr = ''; $timeStr = '';
            if (!empty($cRow['created_at'])) {
                try {
                    $dt = new DateTime($cRow['created_at']);
                    $dateStr = $dt->format('Y-m-d');
                    $timeStr = $dt->format('H:i:s');
                } catch(Exception $e) {}
            }

            $old = null;
            if (!empty($cRow['old_value'])) {
                $rawOld = sanitizeUtf8($cRow['old_value']);
                $decodedOld = json_decode($rawOld, true);
                $old = (json_last_error() === JSON_ERROR_NONE) ? sanitizeUtf8($decodedOld) : $rawOld;
            }

            $new = null;
            if (!empty($cRow['new_value'])) {
                $rawNew = sanitizeUtf8($cRow['new_value']);
                $decodedNew = json_decode($rawNew, true);
                $new = (json_last_error() === JSON_ERROR_NONE) ? sanitizeUtf8($decodedNew) : $rawNew;
            }

            $queryStr = '';
            if (!empty($cRow['query'])) {
                $queryStr = sanitizeUtf8($cRow['query']);
            }

            $pageLabel = sanitizeUtf8($cRow['page'] ?? '');
            $messageLabel = sanitizeUtf8($cRow['action_message'] ?? '');
            $userLabel = sanitizeUtf8(($userMap[$cRow['user_id']] ?? 'Unknown') . " (ID:" . $cRow['user_id'] . ")");
            $actionLabel = sanitizeUtf8($auditActions[$actCode] ?? $actCode);

            $data[] = [
                'page'    => htmlspecialchars((string)$pageLabel, ENT_QUOTES, 'UTF-8'),
                'action'  => '<span class="badge badge-custom badge-'.$badgeClass.'">' . htmlspecialchars((string)$actionLabel, ENT_QUOTES, 'UTF-8') . '</span>',
                'message' => htmlspecialchars((string)$messageLabel, ENT_QUOTES, 'UTF-8'),
                'user'    => htmlspecialchars((string)$userLabel, ENT_QUOTES, 'UTF-8'),
                'date'    => $dateStr,
                'time'    => $timeStr,
                'details' => ['query' => $queryStr, 'old' => $old, 'new' => $new]
            ];
        }

        echo safeJsonEncode(["draw"=>$draw, "recordsTotal"=>$totalRecords, "recordsFiltered"=>$totalRecords, "data"=>$data]);
    } catch (Throwable $e) {
        sendAuditTableError('Audit processing failed.', $draw);
    }
    exit();
}

$pageMetaKey = 'audit_log';
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

<main class="dashboard-main">
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

</main>
<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/audit-log.js?v=<?php echo time(); ?>"></script>
</body>
</html>

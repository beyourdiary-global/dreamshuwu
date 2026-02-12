<?php
// Path: src/pages/audit-log.php

require_once dirname(__DIR__, 2) . '/common.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$auditActions = ['V' => 'View', 'E' => 'Edit', 'A' => 'Add', 'D' => 'Delete'];

if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
    // 1. Ensure clean JSON output (no HTML errors mixed in)
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0); 
    error_reporting(0);

    try {
        // 2. DataTables Parameters
        $draw   = (int)($_GET['draw'] ?? 0);
        $start  = (int)($_GET['start'] ?? 0);
        $length = (int)($_GET['length'] ?? 10);
        
        $searchRaw = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';
        $actionRaw = isset($_GET['filter_action']) ? trim($_GET['filter_action']) : '';

        if (!isset($conn) || !$conn) {
            throw new Exception('Database connection unavailable.');
        }
        if (method_exists($conn, 'set_charset')) {
            $conn->set_charset('utf8mb4');
        }

        // 3. Build Query Filters
        $whereClauses = [];

        // A. Search
        if ($searchRaw !== '') {
            $safeSearch = $conn->real_escape_string($searchRaw);
            
            // Find users matching search
            $userIds = [];
            $uSql = "SELECT id FROM " . USR_LOGIN . " WHERE name LIKE '%{$safeSearch}%'";
            $uRes = $conn->query($uSql);
            if ($uRes) {
                while ($row = $uRes->fetch_assoc()) $userIds[] = (int)$row['id'];
                $uRes->free();
            }

            $orParts = [];
            $orParts[] = "page LIKE '%{$safeSearch}%'";
            $orParts[] = "action_message LIKE '%{$safeSearch}%'";
            if (!empty($userIds)) {
                $idList = implode(',', $userIds);
                $orParts[] = "user_id IN ({$idList})";
            }
            $whereClauses[] = "(" . implode(' OR ', $orParts) . ")";
        }
        
        // B. Filter Action
        if ($actionRaw !== '') {
            $safeAction = $conn->real_escape_string($actionRaw);
            $whereClauses[] = "action = '{$safeAction}'";
        }

        $whereSQL = empty($whereClauses) ? '' : ' AND ' . implode(' AND ', $whereClauses);

        // 4. Get Total Count
        $countSql = "SELECT COUNT(*) as total FROM " . AUDIT_LOG . " WHERE 1=1" . $whereSQL;
        $countResult = $conn->query($countSql);
        if (!$countResult) throw new Exception('Count Failed: ' . $conn->error);
        
        $countRow = $countResult->fetch_assoc();
        $totalRecords = (int)$countRow['total'];
        $countResult->free();

        // 5. Sorting
        $sortCols = ['page', 'action', 'action_message', 'user_id', 'created_at', 'created_at']; 
        $colIdx = (int)($_GET['order'][0]['column'] ?? 5); 
        $colName = $sortCols[($colIdx > 0) ? $colIdx - 1 : 4] ?? 'created_at';
        $dir = ($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        // 6. Fetch Data
        $sql = "SELECT page, action, action_message, query, old_value, new_value, user_id, created_at 
                FROM " . AUDIT_LOG . " 
                WHERE 1=1" . $whereSQL . " 
                ORDER BY {$colName} {$dir} 
                LIMIT {$start}, {$length}";

        $result = $conn->query($sql);
        if (!$result) throw new Exception('Data Failed: ' . $conn->error);

        $results = [];
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $result->free();

        // 7. Map User IDs to Names
        $userIds = array_unique(array_column($results, 'user_id'));
        $userMap = [];
        if (!empty($userIds)) {
            $uList = implode(',', array_map('intval', $userIds));
            $uRes = $conn->query("SELECT id, name FROM " . USR_LOGIN . " WHERE id IN ({$uList})");
            if($uRes) while ($uRow = $uRes->fetch_assoc()) $userMap[$uRow['id']] = $uRow['name'];
        }

        // 8. Process Output (The Critical Fix)
        $data = [];
        foreach ($results as $cRow) {
            $actCode = trim((string)($cRow['action'] ?? ''));
            $badgeClass = ($actCode=='D')?'danger':(($actCode=='E')?'warning':(($actCode=='A')?'success':'info'));
            
            // Format Date
            $dateStr = ''; $timeStr = '';
            if (!empty($cRow['created_at'])) {
                try {
                    $dt = new DateTime($cRow['created_at']);
                    $dateStr = $dt->format('Y-m-d');
                    $timeStr = $dt->format('H:i:s');
                } catch(Exception $e) {}
            }

            // [FIXED LOGIC] 1. Try Decode First -> 2. Then Sanitize
            
            // Handle Old Value
            $oldVal = null;
            if (!empty($cRow['old_value'])) {
                // Attempt to decode the RAW string from DB first
                $decoded = json_decode($cRow['old_value'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // It's valid JSON (array/object), so sanitize the array structure
                    $oldVal = sanitizeUtf8($decoded);
                } else {
                    // It's just a string, sanitize the string
                    $oldVal = sanitizeUtf8($cRow['old_value']);
                }
            }

            // Handle New Value
            $newVal = null;
            if (!empty($cRow['new_value'])) {
                $decoded = json_decode($cRow['new_value'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $newVal = sanitizeUtf8($decoded);
                } else {
                    $newVal = sanitizeUtf8($cRow['new_value']);
                }
            }

            // Sanitize other simple strings
            $pageLabel    = sanitizeUtf8($cRow['page'] ?? '');
            $messageLabel = sanitizeUtf8($cRow['action_message'] ?? '');
            $queryStr     = sanitizeUtf8($cRow['query'] ?? '');
            $userName     = $userMap[$cRow['user_id']] ?? 'Unknown';
            $userLabel    = sanitizeUtf8($userName . " (ID:" . $cRow['user_id'] . ")");
            $actionLabel  = sanitizeUtf8($auditActions[$actCode] ?? $actCode);

            $data[] = [
                'page'    => htmlspecialchars((string)$pageLabel, ENT_QUOTES, 'UTF-8'),
                'action'  => '<span class="badge badge-custom badge-'.$badgeClass.'">' . htmlspecialchars((string)$actionLabel, ENT_QUOTES, 'UTF-8') . '</span>',
                'message' => htmlspecialchars((string)$messageLabel, ENT_QUOTES, 'UTF-8'),
                'user'    => htmlspecialchars((string)$userLabel, ENT_QUOTES, 'UTF-8'),
                'date'    => $dateStr,
                'time'    => $timeStr,
                // Pass the correctly processed array/string to the frontend
                'details' => ['query' => $queryStr, 'old' => $oldVal, 'new' => $newVal]
            ];
        }

        // Output using the safe encoder from functions.php
        echo safeJsonEncode([
            "draw"            => $draw, 
            "recordsTotal"    => $totalRecords, 
            "recordsFiltered" => $totalRecords, 
            "data"            => $data
        ]);

    } catch (Throwable $e) {
        // Return JSON error so DataTable handles it gracefully
        echo safeJsonEncode([
            "draw"            => $draw,
            "recordsTotal"    => 0,
            "recordsFiltered" => 0,
            "data"            => [],
            "error"           => "Error: " . $e->getMessage()
        ]);
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
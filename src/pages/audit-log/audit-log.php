<?php
// Path: src/pages/audit-log.php
require_once dirname(__DIR__, 3) . '/common.php';

// Auth Check
requireLogin();

// 2. Dynamic Permission Check
$currentUrl = '/audit-log.php'; 
$perm = hasPagePermission($conn, $currentUrl);

checkPermissionError('view', $perm);

// Dynamically fetch all unique actions that exist in the audit log
$auditActions = [];
$actionSql = "SELECT DISTINCT action FROM " . AUDIT_LOG . " WHERE action IS NOT NULL";
$actionRes = $conn->query($actionSql);
if ($actionRes) {
    // Fallback map for nice UI translations of standard codes
    while ($row = $actionRes->fetch_assoc()) {
        $code = strtoupper(trim($row['action']));
        if ($code !== '') {
            // Use the nice label if it exists, otherwise just show the raw code
            $auditActions[$code] = $labelMap[$code] ?? $code; 
        }
    }
    $actionRes->free();
}

// Use the new input() helper to safely check the mode
if (input('mode') === 'data') {
    // 1. Ensure clean JSON output (no HTML errors mixed in)
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0); 
    error_reporting(0);

    try {
        // 2. DataTables Parameters using Global Helpers
        $draw   = numberInput('draw');
        $start  = numberInput('start');
        
        // If length is empty/0, default to 10
        $lengthInput = numberInput('length');
        $length = $lengthInput > 0 ? $lengthInput : 10;
        
        // Top-level filter parameter
        $actionRaw = input('filter_action');

        // DataTables sends 'search' as an array. We bypass input() array-blocking 
        // by checking it directly and applying the global xssFilter()
        $searchVal = $_GET['search']['value'] ?? '';
        $searchRaw = xssFilter(trim($searchVal));

        if (!isset($conn) || !$conn) {
            throw new Exception('Database connection unavailable.');
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
                while ($row = $uRes->fetch_assoc()) $userIds[] = $row['id'];
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
        $totalRecords = $countRow['total'];
        $countResult->free();

        // 5. Sorting
        $sortCols = ['page', 'action', 'action_message', 'user_id', 'created_at', 'created_at']; 
        $colIdx = ($_GET['order'][0]['column'] ?? 5); 
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

        // 8. Process Output
        $data = [];
        foreach ($results as $cRow) {
            $actCode = trim($cRow['action'] ?? '');
            $badgeClass = ($actCode=='D')?'danger':(($actCode=='E')?'warning':(($actCode=='A')?'success':'info'));
            
            // Format Date using Global Helper
                $dateStr = ''; $timeStr = '';
                if (!empty($cRow['created_at'])) {
                // We use the PATTERN constants instead of hardcoding 'Y-m-d'
                $dateStr = formatDate($cRow['created_at'], DATE_FORMAT); 
                $timeStr = formatDate($cRow['created_at'], TIME_FORMAT); 
            }

            // Handle Old Value
            $oldVal = null;
            if (!empty($cRow['old_value'])) {
                $decoded = safeJsonDecode($cRow['old_value'], true, $decodeOk);
                if ($decodeOk && $decoded !== null) {
                    $oldVal = sanitizeUtf8($decoded);
                } else {
                    $oldVal = sanitizeUtf8($cRow['old_value']);
                }
            }

            // Handle New Value
            $newVal = null;
            if (!empty($cRow['new_value'])) {
                $decoded = safeJsonDecode($cRow['new_value'], true, $decodeOk);
                if ($decodeOk && $decoded !== null) {
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

$pageMetaKey = $currentUrl;
?>
<head></head></head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/responsive.bootstrap.min.css">
    <link rel="stylesheet" href="/src/pages/audit-log/css/audit-log.css?v=<?php echo time(); ?>">
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<main class="dashboard-main">
<div class="container-fluid mt-4" style="max-width: 1400px;">
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <?php echo generateBreadcrumb($conn, $currentUrl); ?>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-file-shield"></i> System Audit Log</h4>
            </div>
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
<script src="/src/pages/audit-log/js/audit-log.js?v=<?php echo time(); ?>"></script>
</body>
</html>
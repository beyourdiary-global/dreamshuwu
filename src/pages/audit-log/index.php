<?php
// Path: src/pages/audit-log.php
require_once dirname(__DIR__, 3) . '/common.php';

// Auth Check
requireLogin();

// 2. Dynamic Permission Check
$currentUrl = '/audit-log.php'; 
$perm = hasPagePermission($conn, $currentUrl);
$pageName = getDynamicPageName($conn, $perm, $currentUrl);

checkPermissionError('view', $perm);

// Fetch actions from page_action table for the filter dropdown
$auditActions = [];
$actionSql = "SELECT name FROM " . PAGE_ACTION . " WHERE status = 'A' ORDER BY id ASC";
$actionRes = $conn->query($actionSql);
if ($actionRes) {
    while ($row = $actionRes->fetch_assoc()) {
        $name = trim($row['name']);
        if ($name !== '') {
            $auditActions[$name] = $name;
        }
    }
    $actionRes->free();
}

// Custom audit-only actions (not stored in page_action table)
$customAuditActions = ['PAGE_ACTION_BIND', 'PAGE_ACTION_UPDATE', 'ROLE_PERM_BIND', 'ROLE_PERM_UPDATE'];
foreach ($customAuditActions as $customAction) {
    $auditActions[$customAction] = $customAction;
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
        $searchVal = getArray('search')['value'] ?? '';
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
            $normalizedAction = strtolower(trim($actionRaw));
            $legacyActionMap = [
                'view' => 'V',
                'add' => 'A',
                'edit' => 'E',
                'delete' => 'D',
            ];

            if (isset($legacyActionMap[$normalizedAction])) {
                $safeName = $conn->real_escape_string($actionRaw);
                $safeCode = $conn->real_escape_string($legacyActionMap[$normalizedAction]);
                // Support both legacy code logs and newer full-name logs
                $whereClauses[] = "(action = '{$safeCode}' OR action = '{$safeName}')";
            } else {
                $safeAction = $conn->real_escape_string($actionRaw);
                $whereClauses[] = "action = '{$safeAction}'";
            }
        }

        $whereSQL = empty($whereClauses) ? '' : ' AND ' . implode(' AND ', $whereClauses);

        // 4. Get Total Count
        $countSql = "SELECT COUNT(*) as total FROM " . AUDIT_LOG . " WHERE 1=1" . $whereSQL;
        $countResult = $conn->query($countSql);
        if (!$countResult) throw new Exception('Count Failed: ' . $conn->error);
        
        $countRow = $countResult->fetch_assoc();
        $totalRecords = $countRow['total'];
        $countResult->free();

        // 5. Sorting (Mapped to frontend table column indices)
        $sortCols = [
            2 => 'page', 
            3 => 'action', 
            4 => 'action_message', 
            5 => 'user_id', 
            6 => 'created_at', 
            7 => 'created_at'
        ]; 

        // Fetch the array safely through the global function
        $orderParams = getArray('order');

        // Default sort on Date (which is now Index 6)
        $colIdx = (int)($orderParams[0]['column'] ?? 6); 
        $colName = $sortCols[$colIdx] ?? 'created_at';
        $dir = ($orderParams[0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

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
        // Badge color mapping for action names
        $badgeMap = [
            // Danger (red)
            'D' => 'danger', 'Delete' => 'danger', 'Reject' => 'danger',
            'Remove_logo' => 'danger', 'Remove_favicon' => 'danger',
            // Warning (yellow)
            'E' => 'warning', 'Edit' => 'warning', 'Reset_defaults' => 'warning',
            // Success (green)
            'A' => 'success', 'Add' => 'success', 'Approve' => 'success', 'Save' => 'success',
        ];

        $data = [];
        foreach ($results as $cRow) {
            $actCode = trim($cRow['action'] ?? '');
            $badgeClass = $badgeMap[$actCode] ?? 'info';
            
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
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo SITEURL; ?>/src/pages/audit-log/css/audit-log.css?v=<?php echo time(); ?>">
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<main class="audit-container">
    <?php echo generateBreadcrumb($conn, $currentUrl); ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-file-shield"></i> <?php echo htmlspecialchars($pageName); ?></h4>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="text-muted small m-0">筛选:</label> <select id="actionFilter" class="form-select form-select-sm" style="width: 150px;">
                    <option value="">所有操作</option> <?php foreach ($auditActions as $code => $label): echo "<option value='$code'>$label</option>"; endforeach; ?>
                </select>
            </div>
        </div>
        <div class="card-body">
            <table id="auditTable" class="table table-hover w-100" data-api-url="<?php echo htmlspecialchars(getServer('PHP_SELF')); ?>?mode=data">
                <thead>
                <tr>
                    <th style="width: 30px;"></th>
                    <th style="width: 50px;">序号</th> <th>页面</th> <th>操作</th> <th>信息</th> <th>用户</th> <th>日期</th> <th>时间</th> </tr>
                </thead>
            </table>
        </div>
    </div>

</main>
<script src="<?php echo SITEURL; ?>/src/pages/audit-log/js/audit-log.js?v=<?php echo time(); ?>"></script>
</body>
</html>
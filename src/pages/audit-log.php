<?php
require_once __DIR__ . '/../../init.php';
require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}
//2. Array of Audit Actions for Filter Dropdown
$auditActions = [
    'V' => 'View',
    'E' => 'Edit',
    'A' => 'Add',
    'D' => 'Delete'
];

// ==========================================
//  BACKEND: JSON API Mode (for DataTable)
// ==========================================
if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
    header('Content-Type: application/json');

    $columns = ['id', 'page', 'action', 'action_message', 'user_id', 'created_at', 'query', 'old_value', 'new_value', 'changes'];
    
    // Using AUDIT_LOG Constant
    $sql = "SELECT a.*, u.name as user_name 
            FROM " . AUDIT_LOG . " a 
            LEFT JOIN " . USR_LOGIN . " u ON a.user_id = u.id";
            
    $whereSQL = " WHERE 1=1";
    $params = [];
    $types = "";

    // Search Logic
    if (!empty($_GET['search']['value'])) {
        $search = "%" . $_GET['search']['value'] . "%";
        $whereSQL .= " AND (a.page LIKE ? OR a.action_message LIKE ? OR u.name LIKE ?)";
        array_push($params, $search, $search, $search);
        $types .= "sss";
    }
    
    // Filter Logic
    if (!empty($_GET['filter_action'])) {
        $whereSQL .= " AND a.action = ?";
        array_push($params, $_GET['filter_action']);
        $types .= "s";
    }

    // Count Total
    $countSql = "SELECT COUNT(*) as count 
                 FROM " . AUDIT_LOG . " a 
                 LEFT JOIN " . USR_LOGIN . " u ON a.user_id = u.id " . $whereSQL;
                 
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->get_result()->fetch_assoc()['count'];

    // Sorting
    if (isset($_GET['order'])) {
        $columnIndex = $_GET['order'][0]['column'];
        $columnName = $columns[$columnIndex] ?? 'created_at';
        $dir = $_GET['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
        $sql .= $whereSQL . " ORDER BY " . $columnName . " " . $dir;
    } else {
        $sql .= $whereSQL . " ORDER BY created_at DESC";
    }

    // Pagination
    $start = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    $sql .= " LIMIT ?, ?";
    array_push($params, $start, $length);
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $dt = new DateTime($row['created_at']);
        
        // Use the central $auditActions array here
        $actionLabel = $auditActions[$row['action']] ?? $row['action'];

        $data[] = [
            htmlspecialchars($row['page']),
            '<span class="badge badge-'. getActionColor($row['action']) .'">' . $actionLabel . '</span>',
            htmlspecialchars($row['action_message']),
            htmlspecialchars(($row['user_name'] ?? 'Unknown') . " (ID:" . $row['user_id'] . ")"),
            $dt->format('Y-m-d'),
            $dt->format('H:i:s'),
            // Hidden columns for Details Row
            'query' => htmlspecialchars($row['query']),
            'old_value' => $row['old_value'],
            'new_value' => $row['new_value'],
            'changes' => $row['changes']
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

function getActionColor($code) {
    switch($code) {
        case 'V': return 'info';
        case 'E': return 'warning';
        case 'D': return 'danger';
        case 'A': return 'success';
        default: return 'secondary';
    }
}

$pageTitle = "System Audit Log - " . WEBSITE_NAME;
// CSS Loading Array
$customCSS = [
    "dataTables.bootstrap.min.css",
    "responsive.bootstrap.min.css",
    "buttons.bootstrap.min.css", 
    "audit-log.css"
];
?>

<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>

<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<div class="audit-container">
    <div class="card shadow-sm">
        
        <div class="card-header bg-white d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center py-3">
            <h4 class="m-0 mb-2 mb-sm-0 text-nowrap">
                <i class="fa-solid fa-file-shield text-primary"></i> System Audit Log
            </h4>
            
            <div class="d-flex gap-2 align-items-center">
                <label class="text-muted small mb-0 text-nowrap">Filter Action:</label>
                
                <select id="actionFilter" class="form-select form-select-sm" style="width: 200px;">
                    <option value="">All Actions</option>
                    <?php foreach ($auditActions as $code => $label): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>">
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="card-body">
            <table id="auditTable" 
                   class="table table-striped table-hover dt-responsive nowrap" 
                   style="width:100%"
                   data-api-url="<?php echo $_SERVER['PHP_SELF']; ?>?mode=data">
                <thead>
                    <tr>
                        <th class="all" style="width: 20px;"></th> <th class="all">Page</th>
                        <th class="all">Action</th>
                        <th>Message</th>
                        <th>User</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th class="none">Details</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.responsive.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/responsive.bootstrap.min.js"></script>

<script src="<?php echo URL_ASSETS; ?>/js/dataTables.buttons.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/buttons.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/buttons.colVis.min.js"></script>

<script src="<?php echo URL_ASSETS; ?>/js/audit-log.js"></script>

</body>
</html>
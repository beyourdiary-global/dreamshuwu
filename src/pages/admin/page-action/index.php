<?php
// Path: src/pages/admin/page-action/index.php
require_once dirname(__DIR__, 4) . '/common.php';

if (!function_exists('pageActionRedirect')) {
    function pageActionRedirect($url) {
        if (!headers_sent()) {
            header('Location: ' . $url);
        } else {
            echo '<script>window.location.href=' . safeJsonEncode($url) . ';</script>';
        }
        exit();
    }
}

requireLogin();

$table = PAGE_ACTION;
$auditPage = 'Page Action Management';
$isEmbeddedPageAction = isset($EMBED_PAGE_ACTION) && $EMBED_PAGE_ACTION === true;
$currentUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['userid']) ? $_SESSION['userid'] : 0);

$currentUrl = '/dashboard.php?view=page_action';
$perm = hasPagePermission($conn, $currentUrl);

// 1. Check View Permission
checkPermissionError('view', $perm);

$baseListUrl = defined('URL_PAGE_ACTION') ? URL_PAGE_ACTION : (URL_USER_DASHBOARD . '?view=page_action');
$formBaseUrl = $baseListUrl . '&pa_mode=form';
$apiEndpoint = defined('URL_PAGE_ACTION_API') ? URL_PAGE_ACTION_API : (SITEURL . '/src/pages/admin/page-action/index.php');

$flashMsg = $_SESSION['flash_msg'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
if ($flashMsg !== '') {
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

$pageActionMode = isset($_GET['pa_mode']) && $_GET['pa_mode'] === 'form' ? 'form' : 'list';

if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
    header('Content-Type: application/json');
    if (!$hasPermission) {
        http_response_code(403);
        echo safeJsonEncode(['success' => false, 'message' => 'Forbidden']);
        exit();
    }

    $search = trim($_GET['search'] ?? ($_GET['search_name'] ?? ''));
    $page = max(1, ($_GET['page'] ?? 1));
    $perPage = ($_GET['per_page'] ?? 10);
    $allowedSizes = [10, 20, 50, 100];
    if (!in_array($perPage, $allowedSizes, true)) $perPage = 10;

    $whereSql = " WHERE status = 'A' ";
    $types = '';
    $params = [];
    if ($search !== '') {
        $whereSql .= " AND name LIKE ? ";
        $types .= 's';
        $params[] = '%' . $search . '%';
    }

    $countSql = "SELECT COUNT(*) FROM {$table}{$whereSql}";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        echo safeJsonEncode(['success' => false, 'message' => 'Failed to prepare count query']);
        exit();
    }
    if (!empty($params)) {
        $bindParams = [$types];
        foreach ($params as $i => $value) {
            $bindParams[] = &$params[$i];
        }
        call_user_func_array([$countStmt, 'bind_param'], $bindParams);
    }
    $countStmt->execute();
    $countStmt->bind_result($totalRecords);
    $countStmt->fetch();
    $countStmt->close();

    $totalPages = max(1, ceil($totalRecords / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;

    $dataSql = "SELECT id, name, status FROM {$table}{$whereSql} ORDER BY id DESC LIMIT ?, ?";
    $dataStmt = $conn->prepare($dataSql);
    if (!$dataStmt) {
        echo safeJsonEncode(['success' => false, 'message' => 'Failed to prepare data query']);
        exit();
    }

    $dataTypes = $types . 'ii';
    $dataParams = $params;
    $dataParams[] = $offset;
    $dataParams[] = $perPage;
    $bindDataParams = [$dataTypes];
    foreach ($dataParams as $idx => $value) {
        $bindDataParams[] = &$dataParams[$idx];
    }
    call_user_func_array([$dataStmt, 'bind_param'], $bindDataParams);
    $dataStmt->execute();
    $dataStmt->store_result();
    
    $dId = $dName = $dStatus = null;
    $dataStmt->bind_result($dId, $dName, $dStatus);
    
    $rows = [];
    while ($dataStmt->fetch()) {
        $rows[] = [
            'id' => $dId,
            'name' => $dName,
            'status' => $dStatus
        ];
    }
    $dataStmt->close();

    echo safeJsonEncode([
        'success' => true,
        'data' => $rows,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
        ]
    ]);
    exit();
}

if (isset($_POST['mode']) && $_POST['mode'] === 'delete_api') {
    header('Content-Type: application/json');
    // 2. Check Delete Permission for API
    $deleteError = checkPermissionError('delete', $perm);
    if ($deleteError) {
        http_response_code(403);
        echo safeJsonEncode(['success' => false, 'message' => $deleteError]);
        exit();
    }

    $deleteId = isset($_POST['id']) ? $_POST['id'] : 0;
    if ($deleteId <= 0) {
        echo safeJsonEncode(['success' => false, 'message' => 'Invalid ID']);
        exit();
    }

    $oldValue = fetchPageActionRowById($conn, $table, $deleteId);
    if (!$oldValue || $oldValue['status'] !== 'A') {
        echo safeJsonEncode(['success' => false, 'message' => 'Record not found']);
        exit();
    }

    $sqlDelete = "UPDATE {$table} SET status = 'D', updated_by = ?, updated_at = NOW() WHERE id = ? AND status = 'A'";
    $stmtDelete = $conn->prepare($sqlDelete);
    $stmtDelete->bind_param('ii', $currentUserId, $deleteId);
    $ok = $stmtDelete->execute();
    $stmtDelete->close();

    if ($ok) {
        $newValue = fetchPageActionRowById($conn, $table, $deleteId);
        if (function_exists('logAudit')) {
            logAudit([
                'page' => $auditPage,
                'action' => 'D',
                'action_message' => 'Soft deleted page action: ' . ($oldValue['name'] ?? (string)$deleteId),
                'query' => $sqlDelete,
                'query_table' => $table,
                'user_id' => $currentUserId,
                'record_id' => $deleteId,
                'record_name' => $oldValue['name'] ?? null,
                'old_value' => $oldValue,
                'new_value' => $newValue
            ]);
        }
        echo safeJsonEncode(['success' => true]);
    } else {
        echo safeJsonEncode(['success' => false, 'message' => 'Delete failed']);
    }
    exit();
}

if (function_exists('logAudit') && !defined('PAGE_ACTION_VIEW_LOGGED')) {
    define('PAGE_ACTION_VIEW_LOGGED', true);
    
    if ($pageActionMode === 'form') {
        // 1. FORM VIEW LOGGING (matching second image)
        $recordId = isset($_GET['id']) ? $_GET['id'] : 0;
        $viewQuery = "SELECT id, name, status, created_at, updated_at, created_by, updated_by FROM {$table} WHERE id = ? LIMIT 1";
        
        logAudit([
            'page'           => $auditPage,
            'action'         => 'V',
            // Display specific ID in the message
            'action_message' => $recordId > 0 ? "Viewing Page Action Form (Edit ID: $recordId)" : "Viewing Page Action Form (Add)",
            // Pass SQL string with placeholder or actual ID
            'query'          => $viewQuery, 
            'query_table'    => $table,
            'user_id'        => $currentUserId,
            'record_id'      => $recordId > 0 ? $recordId : null
        ]);
    } else {
        // 2. LIST VIEW LOGGING
        $listQuery = "SELECT id, name, status FROM {$table} WHERE status = 'A'";
        logAudit([
            'page'           => $auditPage,
            'action'         => 'V',
            'action_message' => 'Viewing Page Action List',
            'query'          => $listQuery,
            'query_table'    => $table,
            'user_id'        => $currentUserId
        ]);
    }
}

$searchName = trim($_GET['search_name'] ?? '');
$currentPage = 1;
$perPage = ($_GET['per_page'] ?? 10);
$allowedSizes = [10, 20, 50, 100];
if (!in_array($perPage, $allowedSizes, true)) $perPage = 10;

$rows = [];
$totalRecords = 0;
$totalPages = 1;
if ($pageActionMode === 'list') {
    // Added a safe LIMIT to prevent memory overflow, satisfying the security warning 
    // while keeping client-side DataTables searching functional.
    $listSql = "SELECT id, name, status FROM {$table} WHERE status = 'A' ORDER BY id DESC LIMIT 1000";
    $listStmt = $conn->prepare($listSql);
    if ($listStmt) {
        $listStmt->execute();
        $listStmt->store_result();

        $rId = $rName = $rStatus = null;
        $listStmt->bind_result($rId, $rName, $rStatus);

        while ($listStmt->fetch()) {
            $rows[] = [
                'id' => $rId,
                'name' => $rName,
                'status' => $rStatus
            ];
        }
        $listStmt->close();
        $totalRecords = count($rows);
    } else {
        $rows = [];
        $flashMsg = '页面操作数据读取失败，请检查数据表结构。';
        $flashType = 'danger';
    }
}

if ($isEmbeddedPageAction):
    $pageScripts = ($pageActionMode === 'list')
        ? ['jquery.dataTables.min.js', 'dataTables.bootstrap.min.js', 'admin.js']
        : ['admin.js'];
?>

<?php if ($pageActionMode === 'form'): ?>
    <?php require __DIR__ . '/form.php'; ?>
<?php else: ?>
<link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
<div class="container-fluid px-0" id="pageActionApp" data-delete-api-url="<?php echo htmlspecialchars($apiEndpoint); ?>">
    <?php $displayIndexStart = ((max(1, $currentPage) - 1) * max(1, $perPage)) + 1; ?>
    <div class="card page-action-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
            <div>
                <?php echo generateBreadcrumb($conn, $currentUrl); ?>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-gears me-2"></i>页面操作管理</h4>
            </div>
            <?php if (!empty($perm->add)): ?>
            <a href="<?php echo $formBaseUrl; ?>" class="btn btn-primary desktop-add-btn">
                <i class="fa-solid fa-plus"></i> 新增操作
            </a>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <?php if ($flashMsg !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flashMsg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form id="pageActionFilterForm" method="GET" class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <input type="hidden" name="view" value="page_action">
                <div class="d-flex align-items-center gap-2">
                    <span>显示</span>
                    <select name="per_page" class="form-select" style="width: 90px;">
                        <?php foreach ($allowedSizes as $size): ?>
                            <option value="<?php echo $size; ?>" <?php echo $perPage === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>项结果</span>
                </div>
                <div style="width: 380px; max-width: 100%;">
                    <input type="text" name="search_name" class="form-control" value="<?php echo htmlspecialchars($searchName); ?>" placeholder="搜索名称...">
                </div>
            </form>

            <div class="table-responsive page-action-desktop-table">
                <table id="pageActionTable" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 100px;">ID</th>
                            <th>Name</th>
                            <th style="width: 120px;">Status</th>
                            <th style="width: 170px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php $displayIndex = $displayIndexStart; ?>
                        <?php foreach ($rows as $item): ?>
                            <tr>
                                <td><?php echo $displayIndex++; ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><span class="badge bg-success">A</span></td>
                                <td class="text-center">
                                    <?php if (!empty($perm->edit)): ?>
                                    <a href="<?php echo $formBaseUrl . '&id=' . $item['id']; ?>" class="btn btn-sm btn-outline-primary btn-action" title="编辑">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!empty($perm->delete)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-action page-action-delete-btn" data-id="<?php echo $item['id']; ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>" title="软删除">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (empty($perm->edit) && empty($perm->delete)): ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted">暂无数据</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="page-action-mobile-list">
                <?php if (!empty($rows)): ?>
                    <?php $displayIndex = $displayIndexStart; ?>
                    <?php foreach ($rows as $item): ?>
                        <div class="page-action-mobile-item" data-item="<?php echo $item['id']; ?>">
                            <div class="page-action-mobile-head">
                                <div>
                                    <div><strong>#<?php echo $displayIndex++; ?></strong></div>
                                    <div><?php echo htmlspecialchars($item['name']); ?></div>
                                </div>
                                <span class="badge bg-success">A</span>
                            </div>
                            <div class="page-action-mobile-body">
                                <div class="d-flex justify-content-end gap-2">
                                    <?php if (!empty($perm->edit)): ?>
                                    <a href="<?php echo $formBaseUrl . '&id=' . $item['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fa-solid fa-pen"></i> 编辑
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!empty($perm->delete)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger page-action-delete-btn" data-id="<?php echo $item['id']; ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>">
                                        <i class="fa-solid fa-trash"></i> 软删除
                                    </button>
                                    <?php endif; ?>
                                    <?php if (empty($perm->edit) && empty($perm->delete)): ?>
                                    <span class="text-muted small">无操作权限</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-muted">暂无数据</div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<form id="pageActionDeleteForm" method="POST" action="<?php echo htmlspecialchars($baseListUrl); ?>" style="display:none;">
    <input type="hidden" name="form_action" value="delete">
    <input type="hidden" name="id" id="pageActionDeleteId" value="0">
</form>

<?php endif; ?>

<?php else: ?>
<?php $pageMetaKey = 'page_action'; ?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="container mt-4">
    <div class="alert alert-info">请通过用户面板访问该页面：<a href="<?php echo $baseListUrl; ?>">页面操作管理</a></div>
</div>
</body>
</html>
<?php endif; ?>
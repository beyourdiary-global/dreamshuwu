<?php
// Path: src/pages/admin/user-role/index.php
require_once dirname(__DIR__, 4) . '/common.php';

// Configuration & Constants
$tableRole = USER_ROLE;
$tableRolePermission = USER_ROLE_PERMISSION;
$auditPage = 'User Role Management';
$isEmbedded = isset($EMBED_USER_ROLE) && $EMBED_USER_ROLE === true;
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// URL Paths
$baseListUrl = URL_USER_ROLE;
$formBaseUrl = URL_USER_ROLE . '&mode=form';
$apiEndpoint = defined('URL_USER_ROLE_API') ? URL_USER_ROLE_API : (SITEURL . '/src/pages/admin/user-role/index.php');

// Redirect Helper
if (!function_exists('userRoleRedirect')) {
    function userRoleRedirect($url) {
        if (!headers_sent()) {
            header('Location: ' . $url);
        } else {
            echo '<script>window.location.href=' . safeJsonEncode($url) . ';</script>';
        }
        exit();
    }
}

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (isset($_GET['api_mode'])) {
        header('Content-Type: application/json');
        echo safeJsonEncode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    userRoleRedirect(URL_LOGIN);
}

// 2. Permission Check
$currentUrl = '/dashboard.php?view=user_role';
$perm = hasPagePermission($conn, $currentUrl);
$hasPermission = !empty($perm) && !empty($perm->view);

if (!$hasPermission) {
    denyAccess("权限不足：您没有访问用户角色管理的权限。");
}

// 3. POST Handling (Delete Action)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionType = $_POST['action_type'] ?? '';

    if ($actionType === 'delete') {
        if (empty($perm->delete)) {
            denyAccess("权限不足：您没有删除用户角色的权限。");
        }

        $delId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($delId > 0) {
            // Fetch old data for audit
            $oldValue = fetchUserRoleById($conn, $delId);

            // Perform Soft Delete
            $sql = "UPDATE {$tableRole} SET status = 'D', updated_by = ?, updated_at = NOW() WHERE id = ? AND status = 'A'";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $updatedBy = (string)$currentUserId;
                $stmt->bind_param('si', $updatedBy, $delId);
                if ($stmt->execute()) {
                    $newValue = fetchUserRoleById($conn, $delId);
                    $_SESSION['flash_msg'] = '删除成功';
                    $_SESSION['flash_type'] = 'success';

                    // Audit Log
                    if (function_exists('logAudit')) {
                        logAudit([
                            'page' => $auditPage,
                            'action' => 'D',
                            'action_message' => "Soft deleted User Role ID: {$delId}",
                            'query' => $sql,
                            'query_table' => $tableRole,
                            'user_id' => $currentUserId,
                            'record_id' => $delId,
                            'old_value' => $oldValue,
                            'new_value' => $newValue,
                        ]);
                    }
                } else {
                    $_SESSION['flash_msg'] = '删除失败';
                    $_SESSION['flash_type'] = 'danger';
                }
                $stmt->close();
            } else {
                $_SESSION['flash_msg'] = '删除失败';
                $_SESSION['flash_type'] = 'danger';
            }
        }

        userRoleRedirect($baseListUrl);
    }
}

// 4. View Logging (Updated to include Form View)
$viewMode = $_GET['mode'] ?? 'list';
if (function_exists('logAudit') && !defined('USER_ROLE_VIEW_LOGGED')) {
    define('USER_ROLE_VIEW_LOGGED', true);

    if ($viewMode === 'form') {
        // [New] Log Form Viewing (Add or Edit)
        $recordId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $viewQuery = "SELECT * FROM {$tableRole} WHERE id = ? LIMIT 1";

        logAudit([
            'page'           => $auditPage,
            'action'         => 'V',
            'action_message' => $recordId > 0 ? "Viewing User Role Form (Edit ID: {$recordId})" : "Viewing User Role Form (Add)",
            'query'          => $recordId > 0 ? $viewQuery : null,
            'query_table'    => $tableRole,
            'user_id'        => $currentUserId,
            'record_id'      => $recordId > 0 ? $recordId : null
        ]);
    } elseif (!isset($_GET['search']) && !isset($_GET['page'])) {
        // Log List View
        $viewSql = "SELECT id, name_en, name_cn, status FROM {$tableRole} WHERE status = 'A'";
        logAudit([
            'page'           => $auditPage,
            'action'         => 'V',
            'action_message' => 'Viewing User Role List',
            'query'          => $viewSql,
            'query_table'    => $tableRole,
            'user_id'        => $currentUserId,
        ]);
    }
}

// 5. Data Fetching (List Mode)
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

if ($isEmbedded):
    // Flash Messages
    if (isset($_SESSION['flash_msg'])) {
        echo '<div class="alert alert-' . htmlspecialchars($_SESSION['flash_type'] ?? 'info') . ' alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>' . htmlspecialchars($_SESSION['flash_msg']) . '</div>';
        unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
    }

    // Router: Show Form or List
    if ($viewMode === 'form') {
        require __DIR__ . '/form.php';
    } else {
        // List Logic
        $where = "WHERE status = 'A'";
        $params = [];
        $types = '';

        if ($search !== '') {
            $where .= ' AND (name_en LIKE ? OR name_cn LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $types .= 'ss';
        }

        // Count Total Records
        $totalRecords = 0;
        $countSql = "SELECT COUNT(*) FROM {$tableRole} {$where}";
        $stmtCount = $conn->prepare($countSql);
        if ($stmtCount) {
            if (!empty($params)) {
                $stmtCount->bind_param($types, ...$params);
            }
            $stmtCount->execute();
            $stmtCount->bind_result($totalRecords);
            $stmtCount->fetch();
            $stmtCount->close();
        }

        // Pagination Logic
        $totalPages = max(1, (int)ceil($totalRecords / $perPage));
        $currentPage = min($page, $totalPages);
        $offset = ($currentPage - 1) * $perPage;

        // Fetch Data Rows
        $rows = [];
        $sql = "SELECT id, name_en, name_cn, description, status FROM {$tableRole} {$where} ORDER BY id DESC LIMIT ?, ?";
        $stmtList = $conn->prepare($sql);
        if ($stmtList) {
            $listParams = $params;
            $listParams[] = $offset;
            $listParams[] = $perPage;
            $listTypes = $types . 'ii';

            $bindRef = [$listTypes];
            foreach ($listParams as $k => $v) {
                $bindRef[] = &$listParams[$k];
            }
            call_user_func_array([$stmtList, 'bind_param'], $bindRef);

            $stmtList->execute();
            $stmtList->store_result();
            $stmtList->bind_result($rId, $rNameEn, $rNameCn, $rDesc, $rStatus);

            while ($stmtList->fetch()) {
                $rows[] = [
                    'id' => $rId,
                    'name_en' => $rNameEn,
                    'name_cn' => $rNameCn,
                    'description' => $rDesc,
                    'status' => $rStatus,
                ];
            }
            $stmtList->close();
        } else {
            error_log('Failed to prepare user role list statement: ' . $conn->error);
        }
?>
<div class="container-fluid px-0">
    <div class="card page-action-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
            <div>
                <div class="page-action-breadcrumb text-muted mb-1">Admin / User Role</div>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-shield me-2"></i>用户角色管理</h4>
            </div>
            <?php if (!empty($perm->add)): ?>
            <a href="<?php echo $formBaseUrl; ?>" class="btn btn-primary desktop-add-btn"><i class="fa-solid fa-plus"></i> 新增角色</a>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="view" value="user_role">
                <div class="col-md-4 ms-auto">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="搜索角色名称...">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i></button>
                    </div>
                </div>
            </form>

            <div class="table-responsive page-action-desktop-table">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Role Name (CN)</th>
                            <th>Role Name (EN)</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-center text-muted">暂无数据</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['name_cn']); ?></td>
                                <td><?php echo htmlspecialchars($row['name_en']); ?></td>
                                <td><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td class="text-center">
                                    <?php if (!empty($perm->edit)): ?>
                                    <a href="<?php echo $formBaseUrl . '&id=' . (int)$row['id']; ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fa-solid fa-pen"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($perm->delete)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo (int)$row['id']; ?>)"><i class="fa-solid fa-trash"></i></button>
                                    <?php endif; ?>
                                    <?php if (empty($perm->edit) && empty($perm->delete)): ?>
                                    <span class="text-muted small">无操作权限</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="page-action-mobile-list">
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                        <div class="page-action-mobile-item" data-item="<?php echo (int)$row['id']; ?>">
                            <div class="page-action-mobile-head">
                                <div>
                                    <div><strong>#<?php echo (int)$row['id']; ?></strong></div>
                                    <div><?php echo htmlspecialchars($row['name_en']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($row['name_cn']); ?></div>
                                </div>
                                <span class="badge bg-success">Active</span>
                            </div>
                            <div class="page-action-mobile-body">
                                <?php if (!empty($perm->edit)): ?>
                                <a href="<?php echo $formBaseUrl . '&id=' . (int)$row['id']; ?>" class="btn btn-sm btn-outline-primary me-2"><i class="fa-solid fa-pen"></i> 编辑</a>
                                <?php endif; ?>
                                <?php if (!empty($perm->delete)): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo (int)$row['id']; ?>)"><i class="fa-solid fa-trash"></i> 删除</button>
                                <?php endif; ?>
                                <?php if (empty($perm->edit) && empty($perm->delete)): ?>
                                <span class="text-muted small">无操作权限</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-muted">暂无数据</div>
                <?php endif; ?>
            </div>

            <?php if ($totalPages >= 1): ?>
                <?php
                $startItem = $totalRecords > 0 ? (($currentPage - 1) * $perPage + 1) : 0;
                $endItem = $totalRecords > 0 ? min($currentPage * $perPage, $totalRecords) : 0;

                $pagerQuery = [
                    'view' => 'user_role',
                    'search' => $search,
                ];
                $prevQuery = http_build_query(array_merge($pagerQuery, ['page' => max(1, $currentPage - 1)]));
                $nextQuery = http_build_query(array_merge($pagerQuery, ['page' => min($totalPages, $currentPage + 1)]));
                ?>
                <div class="dataTables_wrapper">
                    <div class="row mt-3 align-items-center">
                        <div class="col-sm-12 col-md-5">
                            <div class="dataTables_info" role="status" aria-live="polite">
                                显示 <?php echo $startItem; ?> 至 <?php echo $endItem; ?> 项，共 <?php echo (int)$totalRecords; ?> 项
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-7">
                            <div class="dataTables_paginate paging_simple_numbers">
                                <a href="<?php echo $currentPage <= 1 ? 'javascript:void(0);' : ($baseListUrl . '&' . $prevQuery); ?>" class="paginate_button previous <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">上页</a>
                                <span class="paginate_button current"><?php echo $currentPage; ?></span>
                                <a href="<?php echo $currentPage >= $totalPages ? 'javascript:void(0);' : ($baseListUrl . '&' . $nextQuery); ?>" class="paginate_button next <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">下页</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" action="<?php echo htmlspecialchars($baseListUrl); ?>" style="display:none;">
    <input type="hidden" name="action_type" value="delete">
    <input type="hidden" name="id" id="deleteId" value="0">
</form>

<script src="<?php echo URL_ASSETS; ?>/js/admin.js"></script>
<?php
    }
else:
?>
<?php $pageMetaKey = 'user_role'; ?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="container mt-4">
    <div class="alert alert-info">请通过用户面板访问该页面：<a href="<?php echo $baseListUrl; ?>">用户角色管理</a></div>
</div>
</body>
</html>
<?php endif; ?>
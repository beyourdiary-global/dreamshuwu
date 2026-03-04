<?php
// Path: src/pages/admin/user-role/index.php
require_once dirname(__DIR__, 4) . '/common.php';

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
requireLogin();

// Configuration & Constants
$tableRole = USER_ROLE;
$tableRolePermission = USER_ROLE_PERMISSION;
$auditPage = 'User Role Management';
$currentUserId = sessionInt('user_id');

// URL Paths
$baseListUrl = URL_USER_ROLE;
$formBaseUrl = URL_USER_ROLE . '?mode=form';
$apiEndpoint = defined('URL_USER_ROLE_API') ? URL_USER_ROLE_API : (SITEURL . '/src/pages/admin/user-role/index.php');

// 2. Permission Check
$currentUrl = parse_url(URL_USER_ROLE, PHP_URL_PATH) ?: '/admin/user-role.php';
$perm = hasPagePermission($conn, $currentUrl);
$pageName = getDynamicPageName($conn, $perm, $currentUrl);
// Check View Permission for the list
checkPermissionError('view', $perm);

// 3. POST Handling (Delete Action)
if (isPostRequest()) {
    $actionType = post('action_type');
    $mode = post('mode');

    // [FIX] Listen for both legacy and API delete modes
    if ($actionType === 'delete' || $mode === 'delete_api') {
        // Check Delete Permission
        checkPermissionError('delete', $perm);

        $delId = (int)post('id');
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
                    $stmt->close();

                    // [FIX] Return JSON if it's an AJAX request
                    if ($mode === 'delete_api') respondJsonAndExit(true, '删除成功');

                    setSession('flash_msg', '删除成功');
                    setSession('flash_type', 'success');
                    userRoleRedirect($baseListUrl);
                } else {
                    $stmt->close();
                    if ($mode === 'delete_api') respondJsonAndExit(false, '删除失败');
                    setSession('flash_msg', '删除失败');
                    setSession('flash_type', 'danger');
                    userRoleRedirect($baseListUrl);
                }
            } else {
                if ($mode === 'delete_api') respondJsonAndExit(false, '删除失败');
                setSession('flash_msg', '删除失败');
                setSession('flash_type', 'danger');
                userRoleRedirect($baseListUrl);
            }
        } else {
            if ($mode === 'delete_api') respondJsonAndExit(false, '无效的ID');
            userRoleRedirect($baseListUrl);
        }
    }
}

// 4. View Logging (Updated to include Form View)
$viewMode = input('mode') ?: 'list';
if (function_exists('logAudit') && !defined('USER_ROLE_VIEW_LOGGED')) {
    define('USER_ROLE_VIEW_LOGGED', true);

    if ($viewMode === 'form') {
        // [New] Log Form Viewing (Add or Edit)
        $recordId = (int)numberInput('id');
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
    } elseif (input('search') === '' && numberInput('page') === '') {
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
$search = searchInput('search');
$page = 1;
$perPage = (int)(numberInput('per_page') ?: 10);
$allowedSizes = [10, 20, 50, 100];
if (!in_array($perPage, $allowedSizes, true)) $perPage = 10;

$pageMetaKey = $currentUrl;
$customCSS[] = 'src/pages/admin/css/admin.css';
?>
<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<?php
    // Flash Messages
    if (hasSession('flash_msg')) {
        echo '<div class="alert alert-' . htmlspecialchars(session('flash_type') ?: 'info') . ' alert-dismissible fade show">' . htmlspecialchars(session('flash_msg')) . '</div>';
        unsetSession('flash_msg');
        unsetSession('flash_type');
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

        // Fetch Data Rows
        $rows = [];
        $sql = "SELECT id, name_en, name_cn, description, status FROM {$tableRole} {$where} ORDER BY id DESC";
        $stmtList = $conn->prepare($sql);
        if ($stmtList) {
            if (!empty($params)) {
                $bindRef = [$types];
                foreach ($params as $k => $v) {
                    $bindRef[] = &$params[$k];
                }
                call_user_func_array([$stmtList, 'bind_param'], $bindRef);
            }

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
<div class="app-page-shell" id="pageActionApp" data-delete-api-url="<?php echo htmlspecialchars($apiEndpoint); ?>">
    <?php $displayIndexStart = (((int) max(1, (int) $page) - 1) * (int) max(1, (int) $perPage)) + 1; ?>
    <div class="card page-action-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
            <div>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-shield me-2"></i><?php echo htmlspecialchars($pageName); ?></h4>
            </div>
            <?php if (!empty($perm->add)): ?>
            <a href="<?php echo $formBaseUrl; ?>" class="btn btn-primary desktop-add-btn"><i class="fa-solid fa-plus"></i> 新增角色</a>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <form id="userRoleFilterForm" method="GET" class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
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
                    <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="搜索角色名称...">
                </div>
            </form>

            <div class="table-responsive page-action-desktop-table">
                <table id="userRoleTable" class="table table-hover align-middle">
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
                        <?php $displayIndex = $displayIndexStart; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo $displayIndex++; ?></td>
                                <td><?php echo htmlspecialchars($row['name_cn']); ?></td>
                                <td><?php echo htmlspecialchars($row['name_en']); ?></td>
                                <td><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td class="text-center">
                                    <?php if (!empty($perm->edit)): ?>
                                    <a href="<?php echo $formBaseUrl . '&id=' . $row['id']; ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fa-solid fa-pen"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($perm->delete)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger page-action-delete-btn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['name_cn']); ?>"><i class="fa-solid fa-trash"></i></button>
                                    <?php endif; ?>
                                    <?php if (empty($perm->edit) && empty($perm->delete)): ?>
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
                    <?php $displayIndex = $displayIndexStart; ?>
                    <?php foreach ($rows as $row): ?>
                        <div class="page-action-mobile-item" data-item="<?php echo $row['id']; ?>">
                            <div class="page-action-mobile-head">
                                <div>
                                    <div><strong>#<?php echo $displayIndex++; ?></strong></div>
                                    <div><?php echo htmlspecialchars($row['name_en']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($row['name_cn']); ?></div>
                                </div>
                                <span class="badge bg-success">Active</span>
                            </div>
                            <div class="page-action-mobile-body">
                                <?php if (!empty($perm->edit)): ?>
                                <a href="<?php echo $formBaseUrl . '&id=' . $row['id']; ?>" class="btn btn-sm btn-outline-primary me-2"><i class="fa-solid fa-pen"></i> 编辑</a>
                                <?php endif; ?>
                                <?php if (!empty($perm->delete)): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger page-action-delete-btn" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['name_cn']); ?>"><i class="fa-solid fa-trash"></i> 删除</button>
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

        </div>
    </div>
</div>

<?php
    }
?>

<?php if ($viewMode !== 'form'): ?>
<?php endif; ?>
<script src="<?php echo SITEURL; ?>/src/pages/admin/js/admin.js"></script>
</body>
</html>
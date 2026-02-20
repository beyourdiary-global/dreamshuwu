<?php
// Path: src/pages/admin/page-information-list/index.php
require_once dirname(__DIR__, 4) . '/common.php';

$tableInfo = PAGE_INFO_LIST;
$tableMaster = ACTION_MASTER;
$auditPage = 'Page Information Management';
$isEmbedded = isset($EMBED_PAGE_INFO) && $EMBED_PAGE_INFO === true;
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$baseListUrl = URL_USER_DASHBOARD . '?view=page_info';
$formBaseUrl = URL_USER_DASHBOARD . '?view=page_info&mode=form';
$apiEndpoint = defined('URL_PAGE_INFO_API') ? URL_PAGE_INFO_API : (SITEURL . '/src/pages/admin/page-information-list/index.php');

if (!function_exists('pageInfoRedirect')) {
    function pageInfoRedirect($url) {
        if (!headers_sent()) {
            header('Location: ' . $url);
        } else {
            echo '<script>window.location.href=' . safeJsonEncode($url) . ';</script>';
        }
        exit();
    }
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (isset($_GET['api_mode'])) {
        header('Content-Type: application/json');
        echo safeJsonEncode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    pageInfoRedirect(URL_LOGIN);
}

$currentUrl = '/dashboard.php?view=page_info';
$perm = hasPagePermission($conn, $currentUrl);
$hasPermission = !empty($perm) && !empty($perm->view);

if (!$hasPermission) {
    denyAccess("权限不足：您没有访问页面信息列表的权限。");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionType = $_POST['action_type'] ?? '';

    if ($actionType === 'delete') {
        if (empty($perm->delete)) {
            denyAccess("权限不足：您没有删除页面信息的权限。");
        }

        $delId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($delId > 0) {
            $oldValue = fetchPageInfoRowById($conn, $tableInfo, $delId);

            $sql = "UPDATE {$tableInfo} SET status = 'D', updated_by = ?, updated_at = NOW() WHERE id = ? AND status = 'A'";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ii', $currentUserId, $delId);
                if ($stmt->execute()) {
                    $newValue = fetchPageInfoRowById($conn, $tableInfo, $delId);
                    $_SESSION['flash_msg'] = '删除成功';
                    $_SESSION['flash_type'] = 'success';

                    if (function_exists('logAudit')) {
                        logAudit([
                            'page' => $auditPage,
                            'action' => 'D',
                            'action_message' => "Soft deleted Page ID: {$delId}",
                            'query' => $sql,
                            'query_table' => $tableInfo,
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

        pageInfoRedirect($baseListUrl);
    }
}

$viewMode = $_GET['mode'] ?? 'list';
if (function_exists('logAudit') && !defined('PAGE_INFO_LIST_VIEW_LOGGED')) {
    define('PAGE_INFO_LIST_VIEW_LOGGED', true);

    if ($viewMode === 'form') {
        $idInUrl = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $viewQuery = "SELECT * FROM {$tableInfo} WHERE id = ?";
        
        logAudit([
            'page'           => $auditPage,
            'action'         => 'V',
            'action_message' => $idInUrl > 0 ? "Viewing Page Information Form (Edit ID: $idInUrl)" : "Viewing Page Information Form (Add)",
            'query'          => $idInUrl > 0 ? $viewQuery : null,
            'query_table'    => $tableInfo,
            'user_id'        => $currentUserId,
            'record_id'      => $idInUrl > 0 ? $idInUrl : null
        ]);
    } else if (!isset($_GET['search']) && !isset($_GET['page'])) {
        $viewSql = "SELECT id, name_en, name_cn, public_url, status FROM {$tableInfo} WHERE status = 'A'";
        logAudit([
            'page' => $auditPage,
            'action' => 'V',
            'action_message' => 'Viewing Page Information List',
            'query' => $viewSql,
            'query_table' => $tableInfo,
            'user_id' => $currentUserId
        ]);
    }
}

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

if ($isEmbedded):
    if (isset($_SESSION['flash_msg'])) {
        echo '<div class="alert alert-' . htmlspecialchars($_SESSION['flash_type'] ?? 'info') . ' alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>' . htmlspecialchars($_SESSION['flash_msg']) . '</div>';
        unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
    }

    if ($viewMode === 'form') {
        require __DIR__ . '/form.php';
    } else {
        $where = "WHERE status = 'A'";
        $params = [];
        $types = '';

        if ($search !== '') {
            $where .= ' AND (name_en LIKE ? OR name_cn LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $types .= 'ss';
        }

        $totalRecords = 0;
        $countSql = "SELECT COUNT(*) FROM {$tableInfo} {$where}";
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

        $totalPages = max(1, (int)ceil($totalRecords / $perPage));
        $currentPage = min($page, $totalPages);
        $offset = ($currentPage - 1) * $perPage;

        $rows = [];
        $sql = "SELECT id, name_en, name_cn, public_url, status FROM {$tableInfo} {$where} ORDER BY id DESC LIMIT ?, ?";
                $rows = [];
        $stmtList = $conn->prepare($sql);
        if ($stmtList) {
            $listParams = $params;
            $listParams[] = $offset; 
            $listParams[] = $perPage;
            $listTypes = $types . "ii";
            $bindRef = [];
            foreach ($listParams as $k => $v) {
                $bindRef[] = &$listParams[$k];
            }
            array_unshift($bindRef, $listTypes);
            call_user_func_array([$stmtList, 'bind_param'], $bindRef);
            $stmtList->execute();
            $stmtList->store_result();
            $stmtList->bind_result($rId, $rNameEn, $rNameCn, $rUrl, $rStatus);
            while ($stmtList->fetch()) {
                $rows[] = [
                    'id'       => $rId,
                    'name_en'  => $rNameEn,
                    'name_cn'  => $rNameCn,
                    'url'      => $rUrl,
                    'status'   => $rStatus
                ];
            }
            $stmtList->close();
        } else {
            error_log('Failed to prepare page info list statement: ' . $conn->error);
        }
?>
<div class="container-fluid px-0">
    <div class="card page-action-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
            <div>
                <div class="page-action-breadcrumb text-muted mb-1">Admin / Page Info</div>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-file-signature me-2"></i>页面信息列表</h4>
            </div>
            <?php if (!empty($perm->add)): ?>
            <a href="<?php echo $formBaseUrl; ?>" class="btn btn-primary desktop-add-btn"><i class="fa-solid fa-plus"></i> 新增页面</a>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="view" value="page_info">
                <div class="col-md-4 ms-auto">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="搜索页面名称...">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i></button>
                    </div>
                </div>
            </form>

            <div class="table-responsive page-action-desktop-table">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name (EN/CN)</th>
                            <th>Public URL</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="text-center text-muted">暂无数据</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['name_en']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($row['name_cn']); ?></div>
                                </td>
                                <td><code><?php echo htmlspecialchars($row['url']); ?></code></td>
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
                                    <div class="small text-muted"><code><?php echo htmlspecialchars($row['url']); ?></code></div>
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
                    'view' => 'page_info',
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

<script>
function confirmDelete(id) {
    if (confirm('确认删除该页面信息吗？')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
<script src="<?php echo URL_ASSETS; ?>/js/admin.js"></script>
<?php
    }
else:
?>
<?php $pageMetaKey = 'page_info'; ?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="container mt-4">
    <div class="alert alert-info">请通过用户面板访问该页面：<a href="<?php echo $baseListUrl; ?>">页面信息列表</a></div>
</div>
</body>
</html>
<?php endif; ?>

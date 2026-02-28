<?php
// Path: src/pages/admin/page-information-list/index.php
require_once dirname(__DIR__, 4) . '/common.php';

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

requireLogin();

$tableInfo = PAGE_INFO_LIST;
$tableMaster = ACTION_MASTER;
$auditPage = 'Page Information Management';
$isEmbedded = isset($EMBED_PAGE_INFO) && $EMBED_PAGE_INFO === true;
$currentUserId = sessionInt('user_id');

$baseListUrl = URL_USER_DASHBOARD . '?view=page_info';
$formBaseUrl = URL_USER_DASHBOARD . '?view=page_info&mode=form';
$apiEndpoint = defined('URL_PAGE_INFO_API') ? URL_PAGE_INFO_API : (SITEURL . '/src/pages/admin/page-information-list/index.php');

$currentUrl = '/dashboard.php?view=page_info';
$perm = hasPagePermission($conn, $currentUrl);
$pageName = getDynamicPageName($conn, $perm, $currentUrl);

// 1. Check View Permission for the list
checkPermissionError('view', $perm);

if (isPostRequest()) {
    // [FIX] Use global post method
    $actionType = post('action_type');

    if ($actionType === 'delete') {
        // 2. Check Delete Permission
        checkPermissionError('delete', $perm);

        // [FIX] Use global post method with int casting
        $delId = (int)post('id');
        if ($delId > 0) {
            $oldValue = fetchPageInfoRowById($conn, $tableInfo, $delId);

            $sql = "UPDATE {$tableInfo} SET status = 'D', updated_by = ?, updated_at = NOW() WHERE id = ? AND status = 'A'";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ii', $currentUserId, $delId);
                if ($stmt->execute()) {
                    $newValue = fetchPageInfoRowById($conn, $tableInfo, $delId);
                    setSession('flash_msg', '删除成功');
                    setSession('flash_type', 'success');

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
                    setSession('flash_msg', '删除失败');
                    setSession('flash_type', 'danger');
                }
                $stmt->close();
            } else {
                setSession('flash_msg', '删除失败');
                setSession('flash_type', 'danger');
            }
        }

        pageInfoRedirect($baseListUrl);
    }
}

// [FIX] Use global input method
$viewMode = input('mode') ?: 'list';
if (function_exists('logAudit') && !defined('PAGE_INFO_LIST_VIEW_LOGGED')) {
    define('PAGE_INFO_LIST_VIEW_LOGGED', true);

    if ($viewMode === 'form') {
        // [FIX] Use global numberInput method
        $idInUrl = (int)numberInput('id');
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
    } else if (input('search') === '' && numberInput('page') === '') {
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

// [FIX] Use global searchInput and numberInput methods
$search = searchInput('search');
$page = 1;
$perPage = (int)(numberInput('per_page') ?: 10);
$allowedSizes = [10, 20, 50, 100];
if (!in_array($perPage, $allowedSizes, true)) $perPage = 10;

if ($isEmbedded):
    $pageScripts = ($viewMode === 'form')
        ? ['admin.js']
        : ['jquery.dataTables.min.js', 'dataTables.bootstrap.min.js', 'admin.js'];

    if (hasSession('flash_msg')) {
        echo '<div class="alert alert-' . htmlspecialchars(session('flash_type') ?: 'info') . ' alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>' . htmlspecialchars(session('flash_msg')) . '</div>';
        unsetSession('flash_msg');
        unsetSession('flash_type');
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

        $rows = [];
        $sql = "SELECT id, name_en, name_cn, public_url, status FROM {$tableInfo} {$where} ORDER BY id DESC";
        $rows = [];
        $stmtList = $conn->prepare($sql);
        if ($stmtList) {
            if (!empty($params)) {
                $bindRef = [];
                foreach ($params as $k => $v) {
                    $bindRef[] = &$params[$k];
                }
                array_unshift($bindRef, $types);
                call_user_func_array([$stmtList, 'bind_param'], $bindRef);
            }
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
<link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
<div class="container-fluid px-0">
    <?php $displayIndexStart = ((max(1, $page) - 1) * max(1, $perPage)) + 1; ?>
    <div class="card page-action-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
            <div>
                <?php echo generateBreadcrumb($conn, $currentUrl); ?>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-file-signature me-2"></i><?php echo htmlspecialchars($pageName); ?></h4>
            </div>
            <?php if (!empty($perm->add)): ?>
            <a href="<?php echo $formBaseUrl; ?>" class="btn btn-primary desktop-add-btn"><i class="fa-solid fa-plus"></i> 新增页面</a>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <form id="pageInfoFilterForm" method="GET" class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <input type="hidden" name="view" value="page_info">
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
                    <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="搜索页面名称...">
                </div>
            </form>

            <div class="table-responsive page-action-desktop-table">
                <table id="pageInfoTable" class="table table-hover align-middle">
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
                        <?php $displayIndex = $displayIndexStart; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo $displayIndex++; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['name_en']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($row['name_cn']); ?></div>
                                </td>
                                <td><code><?php echo htmlspecialchars($row['url']); ?></code></td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td class="text-center">
                                    <?php if (!empty($perm->edit)): ?>
                                    <a href="<?php echo $formBaseUrl . '&id=' . $row['id']; ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fa-solid fa-pen"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($perm->delete)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $row['id']; ?>)"><i class="fa-solid fa-trash"></i></button>
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
                                    <div class="small text-muted"><code><?php echo htmlspecialchars($row['url']); ?></code></div>
                                </div>
                                <span class="badge bg-success">Active</span>
                            </div>
                            <div class="page-action-mobile-body">
                                <?php if (!empty($perm->edit)): ?>
                                <a href="<?php echo $formBaseUrl . '&id=' . $row['id']; ?>" class="btn btn-sm btn-outline-primary me-2"><i class="fa-solid fa-pen"></i> 编辑</a>
                                <?php endif; ?>
                                <?php if (!empty($perm->delete)): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $row['id']; ?>)"><i class="fa-solid fa-trash"></i> 删除</button>
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

<form id="deleteForm" method="POST" action="<?php echo htmlspecialchars($baseListUrl); ?>" style="display:none;">
    <input type="hidden" name="action_type" value="delete">
    <input type="hidden" name="id" id="deleteId" value="0">
</form>

<?php
    }
else:
?>
<?php $pageMetaKey = 'page_info'; ?>
<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="container mt-4">
    <div class="alert alert-info">请通过用户面板访问该页面：<a href="<?php echo $baseListUrl; ?>"><?php echo htmlspecialchars($pageName); ?></a></div>
</div>
</body>
</html>
<?php endif; ?>

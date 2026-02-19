<?php
// Path: src/pages/admin/page-information-list/form.php

$recordId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditMode = $recordId > 0;
$formRow = [
    'id' => 0,
    'name_en' => '',
    'name_cn' => '',
    'description' => '',
    'public_url' => '',
    'file_path' => ''
];
$boundActions = [];

// RBAC Permission Check for Form
if (!$canView) {
    $_SESSION['flash_msg'] = 'Access Denied: You cannot view this form.';
    $_SESSION['flash_type'] = 'danger';
    pageInfoRedirect($baseListUrl);
}

if ($isEditMode && !$canEdit) {
    $_SESSION['flash_msg'] = 'Access Denied: You do not have permission to edit page information.';
    $_SESSION['flash_type'] = 'danger';
    pageInfoRedirect($baseListUrl);
}

if (!$isEditMode && !$canAdd) {
    $_SESSION['flash_msg'] = 'Access Denied: You do not have permission to add page information.';
    $_SESSION['flash_type'] = 'danger';
    pageInfoRedirect($baseListUrl);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['mode'])) {

    $formAction = $_POST['action_type'] ?? '';
    if ($formAction === 'save') {
        $recordId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name_en = trim($_POST['name_en'] ?? '');
        $name_cn = trim($_POST['name_cn'] ?? '');
        $public_url = trim($_POST['public_url'] ?? '');
        $file_path = trim($_POST['file_path'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $selectedActions = isset($_POST['action_ids']) && is_array($_POST['action_ids'])
            ? array_map('intval', $_POST['action_ids'])
            : [];
        sort($selectedActions);

        $redirectTo = $recordId > 0 ? ($formBaseUrl . '&id=' . $recordId) : $formBaseUrl;

        if ($name_en === '' || $name_cn === '' || $public_url === '') {
            $_SESSION['flash_msg'] = 'Required fields cannot be empty.';
            $_SESSION['flash_type'] = 'danger';
            pageInfoRedirect($redirectTo);
        }

        // [UPDATED] Regex now allows ?, =, and & for query parameters
        if ($public_url[0] !== '/' || !preg_match('#^/[A-Za-z0-9/_\-.?=&]*$#', $public_url)) {
            $_SESSION['flash_msg'] = 'Public URL 格式无效，必须以 / 开头且仅包含字母、数字、/、_、-、. 以及 ? = &';
            $_SESSION['flash_type'] = 'danger';
            pageInfoRedirect($redirectTo);
        }

        $dupSql = "SELECT id FROM {$tableInfo} WHERE (name_en = ? OR name_cn = ? OR public_url = ?) AND status = 'A' "
            . ($recordId > 0 ? 'AND id != ?' : '')
            . ' LIMIT 1';
        $dupStmt = $conn->prepare($dupSql);
        if (!$dupStmt) {
            $_SESSION['flash_msg'] = '数据库错误：无法检查重复数据';
            $_SESSION['flash_type'] = 'danger';
            pageInfoRedirect($redirectTo);
        }
        if ($recordId > 0) {
            $dupStmt->bind_param('sssi', $name_en, $name_cn, $public_url, $recordId);
        } else {
            $dupStmt->bind_param('sss', $name_en, $name_cn, $public_url);
        }
        $dupStmt->execute();
        $dupStmt->store_result();
        $dupExists = $dupStmt->num_rows > 0;
        $dupStmt->close();

        if ($dupExists) {
            $_SESSION['flash_msg'] = 'Name (EN/CN) 或 Public URL 已存在。';
            $_SESSION['flash_type'] = 'danger';
            pageInfoRedirect($redirectTo);
        }

        $oldPageInfo = null;
        $oldActionIds = [];
        if ($recordId > 0) {
            $oldPageInfo = fetchPageInfoRowById($conn, $tableInfo, $recordId);
            if (!$oldPageInfo || $oldPageInfo['status'] !== 'A') {
                $_SESSION['flash_msg'] = '记录不存在或已删除';
                $_SESSION['flash_type'] = 'warning';
                pageInfoRedirect($baseListUrl);
            }

            $oldActStmt = $conn->prepare("SELECT action_id FROM {$tableMaster} WHERE page_id = ?");
            if ($oldActStmt) {
                $oldActStmt->bind_param('i', $recordId);
                $oldActStmt->execute();
                $oldActStmt->bind_result($aid);
                while ($oldActStmt->fetch()) {
                    $oldActionIds[] = (int)$aid;
                }
                $oldActStmt->close();
                sort($oldActionIds);
            }
        }

        $conn->begin_transaction();
        try {
            $targetId = 0;
            $mainActionType = '';
            $executedSql = '';

            if ($recordId > 0) {
                $executedSql = "UPDATE {$tableInfo} SET name_en=?, name_cn=?, description=?, public_url=?, file_path=?, updated_by=?, updated_at=NOW() WHERE id=? AND status='A'";
                $stmt = $conn->prepare($executedSql);
                $stmt->bind_param('sssssii', $name_en, $name_cn, $description, $public_url, $file_path, $currentUserId, $recordId);
                $stmt->execute();
                $stmt->close();

                $deleteSql = "DELETE FROM {$tableMaster} WHERE page_id = ?";
                $deleteStmt = $conn->prepare($deleteSql);
                $deleteStmt->bind_param('i', $recordId);
                $deleteStmt->execute();
                $deleteStmt->close();

                $targetId = $recordId;
                $mainActionType = 'E';
            } else {
                $executedSql = "INSERT INTO {$tableInfo} (name_en, name_cn, description, public_url, file_path, status, created_by, updated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'A', ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($executedSql);
                $stmt->bind_param('sssssii', $name_en, $name_cn, $description, $public_url, $file_path, $currentUserId, $currentUserId);
                $stmt->execute();
                $targetId = (int)$conn->insert_id;
                $stmt->close();
                $mainActionType = 'A';
            }

            if (!empty($selectedActions)) {
                $bindSql = "INSERT INTO {$tableMaster} (page_id, action_id, created_by) VALUES (?, ?, ?)";
                $bindStmt = $conn->prepare($bindSql);
                foreach ($selectedActions as $actId) {
                    $actIdInt = (int)$actId;
                    $bindStmt->bind_param('iii', $targetId, $actIdInt, $currentUserId);
                    $bindStmt->execute();
                }
                $bindStmt->close();
            }

            $conn->commit();

            $newPageInfo = fetchPageInfoRowById($conn, $tableInfo, $targetId);
            if (function_exists('logAudit')) {
                logAudit([
                    'page' => $auditPage,
                    'action' => $mainActionType,
                    'action_message' => ($mainActionType === 'A' ? 'Added' : 'Updated') . " Page Info: {$name_en}",
                    'query' => $executedSql,
                    'query_table' => $tableInfo,
                    'user_id' => $currentUserId,
                    'record_id' => $targetId,
                    'old_value' => $oldPageInfo,
                    'new_value' => $newPageInfo
                ]);

                if ($mainActionType === 'A' || $oldActionIds !== $selectedActions) {
                    logAudit([
                        'page' => $auditPage,
                        'action' => $mainActionType === 'A' ? 'PAGE_ACTION_BIND' : 'PAGE_ACTION_UPDATE',
                        'action_message' => "Permissions updated for: {$name_en}",
                        'user_id' => $currentUserId,
                        'record_id' => $targetId,
                        'old_value' => ['action_ids' => $oldActionIds],
                        'new_value' => ['action_ids' => $selectedActions]
                    ]);
                }
            }

            $_SESSION['flash_msg'] = '保存成功.';
            $_SESSION['flash_type'] = 'success';
            pageInfoRedirect($baseListUrl);
        } catch (Exception $e) {
            $conn->rollback();
            error_log('Database error in page-information-list form: ' . $e->getMessage());
            $_SESSION['flash_msg'] = '数据库错误，请稍后重试或联系管理员。';
            $_SESSION['flash_type'] = 'danger';
            pageInfoRedirect($redirectTo);
        }
    }
}

if ($isEditMode) {
    $loaded = fetchPageInfoRowById($conn, $tableInfo, $recordId);
    if ($loaded && $loaded['status'] === 'A') {
        $formRow = $loaded;
    } else {
        $_SESSION['flash_msg'] = 'Record not found.';
        $_SESSION['flash_type'] = 'warning';
        pageInfoRedirect($baseListUrl);
    }

    $stmt = $conn->prepare("SELECT action_id FROM {$tableMaster} WHERE page_id = ?");
    $stmt->bind_param('i', $recordId);
    $stmt->execute();
    $stmt->bind_result($actId);
    while ($stmt->fetch()) {
        $boundActions[] = (int)$actId;
    }
    $stmt->close();
}

$allActions = [];
$actSql = 'SELECT id, name FROM ' . PAGE_ACTION . " WHERE status = 'A' ORDER BY id ASC";
$res = $conn->query($actSql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $allActions[] = $row;
    }
    $res->free();
} else {
    // Proper error handling
    error_log("Database Error (Fetch Actions): " . $conn->error);
}
?>

<div class="container-fluid px-0">
    <div class="card page-action-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <div>
                <div class="page-action-breadcrumb text-muted mb-1">Admin / Page Info</div>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-file-pen me-2"></i><?php echo $isEditMode ? '编辑页面信息' : '新增页面信息'; ?></h4>
            </div>
            <a href="<?php echo $baseListUrl; ?>" class="btn btn-outline-secondary">返回列表</a>
        </div>

        <div class="card-body">
            <?php if (isset($_SESSION['flash_msg'])): ?>
                <div class="alert alert-<?php echo htmlspecialchars($_SESSION['flash_type'] ?? 'info'); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($_SESSION['flash_msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_msg'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($formBaseUrl . ($isEditMode ? '&id=' . $recordId : '')); ?>">
                <input type="hidden" name="action_type" value="save">
                <?php if ($isEditMode): ?><input type="hidden" name="id" value="<?php echo (int)$recordId; ?>"><?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Name (EN) <span class="text-danger">*</span></label>
                            <input type="text" name="name_en" class="form-control" required value="<?php echo htmlspecialchars($formRow['name_en']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Name (CN) <span class="text-danger">*</span></label>
                            <input type="text" name="name_cn" class="form-control" required value="<?php echo htmlspecialchars($formRow['name_cn']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Public URL <span class="text-danger">*</span></label>
                            <input type="text" name="public_url" class="form-control" required placeholder="/admin/example" value="<?php echo htmlspecialchars($formRow['public_url']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">File Path</label>
                            <input type="text" name="file_path" class="form-control" placeholder="/src/pages/..." value="<?php echo htmlspecialchars($formRow['file_path']); ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($formRow['description']); ?></textarea>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3 text-dark"><i class="fa-solid fa-lock me-2"></i>Page Actions / 页面权限绑定</h5>
                <div class="p-4 bg-light border rounded">
                    <?php if (empty($allActions)): ?>
                        <div class="text-muted small">暂无可用操作。请先在 "页面操作管理" 中添加。</div>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($allActions as $act): ?>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="action_ids[]"
                                        value="<?php echo (int)$act['id']; ?>"
                                        id="act_<?php echo (int)$act['id']; ?>"
                                        <?php echo in_array((int)$act['id'], $boundActions, true) ? 'checked' : ''; ?>
                                    >
                                    <label class="form-check-label user-select-none" for="act_<?php echo (int)$act['id']; ?>">
                                        <?php echo htmlspecialchars($act['name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end mt-4 gap-2">
                    <a href="<?php echo $baseListUrl; ?>" class="btn btn-light">取消</a>
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-save"></i> 保存设置</button>
                </div>
            </form>
        </div>
    </div>
</div>
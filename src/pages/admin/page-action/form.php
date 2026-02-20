<?php
$recordId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditMode = $recordId > 0;
$formRow = ['id' => 0, 'name' => '', 'status' => 'A'];

if (empty($perm) || !$perm->view) {
    denyAccess("权限不足：您没有访问页面操作表单的权限。");
}

if ($isEditMode && empty($perm->edit)) {
    denyAccess("权限不足：您没有编辑页面操作的权限。");
} elseif (!$isEditMode && empty($perm->add)) {
    denyAccess("权限不足：您没有新增页面操作的权限。");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['mode'])) {
    $formAction = $_POST['form_action'] ?? '';
    if ($formAction === 'save') {
        $recordId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $isEditMode = $recordId > 0;

        if ($isEditMode && empty($perm->edit)) {
            denyAccess("权限不足：您没有编辑页面操作的权限。");
        }
        if (!$isEditMode && empty($perm->add)) {
            denyAccess("权限不足：您没有新增页面操作的权限。");
        }

        $name = trim($_POST['name'] ?? '');
        $redirectTo = $recordId > 0 ? ($formBaseUrl . '&id=' . $recordId) : $formBaseUrl;

        if ($name === '') {
            $_SESSION['flash_msg'] = '名称不能为空';
            $_SESSION['flash_type'] = 'danger';
            pageActionRedirect($redirectTo);
        }

        if ($recordId > 0) {
            $oldValue = fetchPageActionRowById($conn, $table, $recordId);
            if (!$oldValue || $oldValue['status'] !== 'A') {
                $_SESSION['flash_msg'] = '记录不存在';
                $_SESSION['flash_type'] = 'warning';
                pageActionRedirect($baseListUrl);
            }

            checkNoChangesAndRedirect(['name' => $name], $oldValue, null, $redirectTo);

            $dupSql = "SELECT id FROM {$table} WHERE name = ? AND status = 'A' AND id != ? LIMIT 1";
            $dupStmt = $conn->prepare($dupSql);
            $dupStmt->bind_param('si', $name, $recordId);
            $dupStmt->execute();
            $dupStmt->store_result();
            $dupExists = $dupStmt->num_rows > 0;
            $dupStmt->close();

            if ($dupExists) {
                $_SESSION['flash_msg'] = '名称已存在，请使用其他名称';
                $_SESSION['flash_type'] = 'danger';
                pageActionRedirect($redirectTo);
            }

            $sqlUpdate = "UPDATE {$table} SET name = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND status = 'A'";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param('sii', $name, $currentUserId, $recordId);
            $ok = $stmtUpdate->execute();
            $stmtUpdate->close();

            if ($ok) {
                $newValue = fetchPageActionRowById($conn, $table, $recordId);
                if (function_exists('logAudit')) {
                    logAudit([
                        'page' => $auditPage,
                        'action' => 'E',
                        'action_message' => 'Updated page action: ' . $name,
                        'query' => $sqlUpdate,
                        'query_table' => $table,
                        'user_id' => $currentUserId,
                        'record_id' => $recordId,
                        'record_name' => $name,
                        'old_value' => $oldValue,
                        'new_value' => $newValue
                    ]);
                }
                $_SESSION['flash_msg'] = '保存成功';
                $_SESSION['flash_type'] = 'success';
                pageActionRedirect($baseListUrl);
            }

            $_SESSION['flash_msg'] = '保存失败，请稍后重试';
            $_SESSION['flash_type'] = 'danger';
            pageActionRedirect($redirectTo);
        }

        $dupSql = "SELECT id FROM {$table} WHERE name = ? AND status = 'A' LIMIT 1";
        $dupStmt = $conn->prepare($dupSql);
        $dupStmt->bind_param('s', $name);
        $dupStmt->execute();
        $dupStmt->store_result();
        $dupExists = $dupStmt->num_rows > 0;
        $dupStmt->close();

        if ($dupExists) {
            $_SESSION['flash_msg'] = '名称已存在，请使用其他名称';
            $_SESSION['flash_type'] = 'danger';
            pageActionRedirect($redirectTo);
        }

        $sqlInsert = "INSERT INTO {$table} (name, status, created_by, updated_by, created_at, updated_at) VALUES (?, 'A', ?, ?, NOW(), NOW())";
        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->bind_param('sii', $name, $currentUserId, $currentUserId);
        $ok = $stmtInsert->execute();
        $newId = $conn->insert_id;
        $stmtInsert->close();

        if ($ok) {
            $newValue = fetchPageActionRowById($conn, $table, $newId);
            if (function_exists('logAudit')) {
                logAudit([
                    'page' => $auditPage,
                    'action' => 'A',
                    'action_message' => 'Added page action: ' . $name,
                    'query' => $sqlInsert,
                    'query_table' => $table,
                    'user_id' => $currentUserId,
                    'record_id' => $newId,
                    'record_name' => $name,
                    'new_value' => $newValue
                ]);
            }
            $_SESSION['flash_msg'] = '新增成功';
            $_SESSION['flash_type'] = 'success';
            pageActionRedirect($baseListUrl);
        }

        $_SESSION['flash_msg'] = '新增失败，请稍后重试';
        $_SESSION['flash_type'] = 'danger';
        pageActionRedirect($redirectTo);
    }
}

if ($isEditMode) {
    $loaded = fetchPageActionRowById($conn, $table, $recordId);
    if ($loaded && $loaded['status'] === 'A') {
        $formRow = $loaded;
    } else {
        $_SESSION['flash_msg'] = '记录不存在或已删除';
        $_SESSION['flash_type'] = 'warning';
        pageActionRedirect($baseListUrl);
    }
}
?>
<div class="container-fluid px-0">
    <div class="card page-action-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
            <div>
                <div class="page-action-breadcrumb text-muted mb-1">Admin / Page Action</div>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-gears me-2"></i><?php echo $isEditMode ? '编辑页面操作' : '新增页面操作'; ?></h4>
            </div>
            <a href="<?php echo $baseListUrl; ?>" class="btn btn-outline-secondary">返回列表</a>
        </div>

        <div class="card-body">
            <?php if ($flashMsg !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flashMsg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($formBaseUrl . ($isEditMode ? '&id=' . (int)$formRow['id'] : '')); ?>" autocomplete="off">
                <input type="hidden" name="form_action" value="save">
                <?php if ($isEditMode): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$formRow['id']; ?>">
                   <div class="mb-3 row">
                       <label class="col-md-3 col-form-label text-md-end form-label">ID</label>
                       <div class="col-md-9">
                           <input type="text" class="form-control" value="<?php echo (int)$formRow['id']; ?>" readonly>
                       </div>
                   </div>
                <?php endif; ?>

                <div class="mb-3 row">
                    <label class="col-md-3 col-form-label text-md-end form-label">Name</label>
                    <div class="col-md-9">
                        <input type="text" name="name" class="form-control" maxlength="255" required value="<?php echo htmlspecialchars($formRow['name']); ?>" placeholder="请输入操作名称">
                    </div>
                </div>

                <div class="mb-4 row">
                    <label class="col-md-3 col-form-label text-md-end form-label">Status</label>
                    <div class="col-md-9">
                        <input type="text" class="form-control" value="A" readonly>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="<?php echo $baseListUrl; ?>" class="btn btn-light">取消</a>
                    <?php if (($isEditMode && !empty($perm->edit)) || (!$isEditMode && !empty($perm->add))): ?>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="fa-solid fa-save"></i> <?php echo $isEditMode ? '更新操作' : '保存操作'; ?>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

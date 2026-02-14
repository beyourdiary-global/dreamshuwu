<?php
// Path: src/pages/admin/page-information-list/form.php

$recordId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditMode = $recordId > 0;
$formRow = ['id' => 0, 'name_en' => '', 'name_cn' => '', 'description' => '', 'public_url' => '', 'file_path' => ''];
$boundActions = [];

// [Audit] VIEW LOGGING for Form Access
if (function_exists('logAudit') && !isset($_POST['action_type'])) {
    $viewFormSql = "SELECT * FROM {$tableInfo} WHERE id = $recordId";
    logAudit([
        'page' => $auditPage,
        'action' => 'V',
        'action_message' => $isEditMode ? "Viewing Page Information Form (Edit ID: $recordId)" : "Viewing Page Information Form (Add)",
        'query' => $isEditMode ? $viewFormSql : null,
        'query_table' => $tableInfo,
        'user_id' => $currentUserId
    ]);
}

// Handle POST Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['mode'])) {
    // Permission Check
    if (isset($hasPermission) && !$hasPermission) {
        $_SESSION['flash_msg'] = '权限不足：仅允许管理员组访问';
        $_SESSION['flash_type'] = 'danger';
        pageInfoRedirect($baseListUrl);
    }

    $formAction = $_POST['action_type'] ?? '';
    
    if ($formAction === 'save') {
        $recordId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name_en = trim($_POST['name_en'] ?? '');
        $name_cn = trim($_POST['name_cn'] ?? '');
        $public_url = trim($_POST['public_url'] ?? '');
        $file_path = trim($_POST['file_path'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        $selectedActions = isset($_POST['action_ids']) && is_array($_POST['action_ids']) ? array_map('intval', $_POST['action_ids']) : [];
        sort($selectedActions);

        $redirectTo = $recordId > 0 ? ($formBaseUrl . '&id=' . $recordId) : $formBaseUrl;

        // Validation
        if (empty($name_en) || empty($name_cn) || empty($public_url)) {
            $_SESSION['flash_msg'] = "Required fields cannot be empty.";
            $_SESSION['flash_type'] = "danger";
            pageInfoRedirect($redirectTo);
        }

        // Duplicate Check
        $dupSql = "SELECT id FROM {$tableInfo} WHERE (name_en = ? OR name_cn = ?) AND status = 'A' " . ($recordId > 0 ? "AND id != ?" : "") . " LIMIT 1";
        $stmt = $conn->prepare($dupSql);
        if ($recordId > 0) $stmt->bind_param("ssi", $name_en, $name_cn, $recordId);
        else $stmt->bind_param("ss", $name_en, $name_cn);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['flash_msg'] = "Name (EN or CN) already exists.";
            $_SESSION['flash_type'] = "danger";
            pageInfoRedirect($redirectTo);
        }
        $stmt->close();

        // 1. Fetch Old Data (For Audit)
        $oldPageInfo = null;
        $oldActionIds = [];
        if ($recordId > 0) {
            $oldPageInfo = fetchPageInfoRowById($conn, $tableInfo, $recordId);
            $stmt = $conn->prepare("SELECT action_id FROM {$tableMaster} WHERE page_id = ?");
            $stmt->bind_param("i", $recordId);
            $stmt->execute();
            $stmt->bind_result($aid);
            while($stmt->fetch()) { $oldActionIds[] = $aid; }
            $stmt->close();
            sort($oldActionIds);
        }

        // Transaction Start
        $conn->begin_transaction();
        try {
            $targetId = 0;
            $mainActionType = '';
            $executedSql = '';
            
            if ($recordId > 0) {
                // UPDATE
                $executedSql = "UPDATE {$tableInfo} SET name_en=?, name_cn=?, description=?, public_url=?, file_path=?, updated_by=?, updated_at=NOW() WHERE id=? AND status = 'A'";
                $stmt = $conn->prepare($executedSql);
                $stmt->bind_param("sssssii", $name_en, $name_cn, $description, $public_url, $file_path, $currentUserId, $recordId);
                $stmt->execute();
                $stmt->close();
                $conn->query("DELETE FROM {$tableMaster} WHERE page_id = $recordId");
                $targetId = $recordId;
                $mainActionType = 'E';
            } else {
                // INSERT
                $executedSql = "INSERT INTO {$tableInfo} (name_en, name_cn, description, public_url, file_path, status, created_by, updated_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'A', ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($executedSql);
                $stmt->bind_param("sssssii", $name_en, $name_cn, $description, $public_url, $file_path, $currentUserId, $currentUserId);
                $stmt->execute();
                $targetId = $conn->insert_id;
                $stmt->close();
                $mainActionType = 'A';
            }

            if (!empty($selectedActions)) {
                $bindSql = "INSERT INTO {$tableMaster} (page_id, action_id, created_by) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($bindSql);
                foreach ($selectedActions as $actId) {
                    $actIdInt = (int)$actId;
                    $stmt->bind_param("iii", $targetId, $actIdInt, $currentUserId);
                    $stmt->execute();
                }
                $stmt->close();
            }
            $conn->commit();
            
            // 2. Fetch New Data (For Audit)
            $newPageInfo = fetchPageInfoRowById($conn, $tableInfo, $targetId);

            // 3. Log Audit
            if (function_exists('logAudit')) {
                // Log Main Record
                logAudit([
                    'page' => $auditPage, 
                    'action' => $mainActionType, 
                    'action_message' => ($mainActionType == 'A' ? "Added" : "Updated") . " Page Info: $name_en", 
                    'query' => $executedSql,
                    'query_table' => $tableInfo,
                    'user_id' => $currentUserId,
                    'record_id' => $targetId,
                    'old_value' => $oldPageInfo,
                    'new_value' => $newPageInfo
                ]);

                // Log Permission Changes (Req 8.8)
                if ($mainActionType === 'A' || $oldActionIds !== $selectedActions) {
                    $bindActionType = ($mainActionType === 'A') ? 'PAGE_ACTION_BIND' : 'PAGE_ACTION_UPDATE';
                    logAudit([
                        'page' => $auditPage,
                        'action' => $bindActionType,
                        'action_message' => "Permissions updated for: $name_en",
                        'user_id' => $currentUserId,
                        'record_id' => $targetId,
                        'old_value' => ['action_ids' => $oldActionIds],
                        'new_value' => ['action_ids' => $selectedActions]
                    ]);
                }
            }

            $_SESSION['flash_msg'] = "保存成功.";
            $_SESSION['flash_type'] = "success";
            pageInfoRedirect($baseListUrl);

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_msg'] = "数据库错误: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
            pageInfoRedirect($redirectTo);
        }
    }
}

// Prepare Data for View
if ($isEditMode) {
    $loaded = fetchPageInfoRowById($conn, $tableInfo, $recordId);
    if ($loaded && $loaded['status'] === 'A') { $formRow = $loaded; } 
    else { $_SESSION['flash_msg'] = 'Record not found.'; $_SESSION['flash_type'] = 'warning'; pageInfoRedirect($baseListUrl); }

    $stmt = $conn->prepare("SELECT action_id FROM {$tableMaster} WHERE page_id = ?");
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $stmt->bind_result($actId);
    while($stmt->fetch()) { $boundActions[] = $actId; }
    $stmt->close();
}

$allActions = [];
$actSql = "SELECT id, name FROM " . PAGE_ACTION . " WHERE status = 'A' ORDER BY id ASC";
$res = $conn->query($actSql);
if ($res) { while($row = $res->fetch_assoc()) { $allActions[] = $row; } }
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
                <div class="alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($_SESSION['flash_msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_msg'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($formBaseUrl . ($isEditMode ? '&id=' . $recordId : '')); ?>">
                <input type="hidden" name="action_type" value="save">
                <?php if ($isEditMode): ?><input type="hidden" name="id" value="<?php echo $recordId; ?>"><?php endif; ?>

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
                                    <input class="form-check-input" type="checkbox" 
                                           name="action_ids[]" 
                                           value="<?php echo $act['id']; ?>"
                                           id="act_<?php echo $act['id']; ?>"
                                           <?php echo in_array($act['id'], $boundActions) ? 'checked' : ''; ?>
                                    >
                                    <label class="form-check-label user-select-none" for="act_<?php echo $act['id']; ?>">
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
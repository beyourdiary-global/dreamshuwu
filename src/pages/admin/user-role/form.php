<?php
// Path: src/pages/admin/user-role/form.php

// 1. Initialization
// Retrieve record ID from GET request to determine Add vs Edit mode
$recordId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEditMode = $recordId > 0;

// Initialize form data structure with defaults
$formRow = [
    'id' => 0,
    'name_cn' => '',
    'name_en' => '',
    'description' => '',
];
// Initialize permissions array (used for checkboxes)
$assignedPerms = [];

// 1. Check View Permission for the form page
checkPermissionError('view', $perm, '用户角色表单');

// 2. Check Add/Edit Permission for loading the form
$actionToCheck = $isEditMode ? 'edit' : 'add';
checkPermissionError($actionToCheck, $perm, '用户角色');

// 2. Form Submission Handling (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['mode'])) {
    $formAction = $_POST['action_type'] ?? '';
    
    // Handle 'save' action
    if ($formAction === 'save') {
        // Collect and sanitize input
        $recordId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $isEditMode = $recordId > 0;
        // 3. Check Add/Edit Permission for form submission
        $submitAction = $isEditMode ? 'edit' : 'add';
        checkPermissionError($submitAction, $perm, '用户角色');

        $name_cn = trim($_POST['name_cn'] ?? '');
        $name_en = trim($_POST['name_en'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Collect selected permissions (array of strings "pageId_actionId")
        $selectedPerms = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
        $selectedPerms = array_filter($selectedPerms); // Remove empty values

        // Determine redirect URL (back to edit if editing, else list)
        $redirectTo = $recordId > 0 ? ($formBaseUrl . '&id=' . $recordId) : $formBaseUrl;

        // Validation: Required fields
        if ($name_cn === '' || $name_en === '') {
            $_SESSION['flash_msg'] = 'Required fields cannot be empty.';
            $_SESSION['flash_type'] = 'danger';
            userRoleRedirect($redirectTo);
        }

        // Validation: Duplicate Name Check
        if (checkRoleNameDuplicate($conn, $name_cn, $name_en, $recordId)) {
            $_SESSION['flash_msg'] = 'Role name (EN or CN) already exists.';
            $_SESSION['flash_type'] = 'danger';
            userRoleRedirect($redirectTo);
        }

        // Fetch Old Data for Audit Logging (Edit Mode)
        $oldRoleData = null;
        $oldPerms = [];
        if ($recordId > 0) {
            $oldRoleData = fetchUserRoleById($conn, $recordId);
            if (!$oldRoleData || $oldRoleData['status'] !== 'A') {
                $_SESSION['flash_msg'] = 'Record not found or already deleted.';
                $_SESSION['flash_type'] = 'warning';
                userRoleRedirect($baseListUrl);
            }

            $oldPerms = fetchRolePermissions($conn, $recordId);
            // Sort permissions for consistent comparison
            usort($oldPerms, function($a, $b) {
                if ($a['page_id'] === $b['page_id']) {
                    return $a['action_id'] - $b['action_id'];
                }
                return $a['page_id'] - $b['page_id'];
            });
        }

        // Begin Database Transaction
        $conn->begin_transaction();
        try {
            $targetId = 0;
            $mainActionType = '';
            $executedSql = '';
            $updatedBy = (string)$currentUserId;

            if ($recordId > 0) {
                // UPDATE Logic
                $executedSql = "UPDATE {$tableRole} SET name_en=?, name_cn=?, description=?, updated_by=?, updated_at=NOW() WHERE id=? AND status='A'";
                $stmt = $conn->prepare($executedSql);
                $stmt->bind_param('ssssi', $name_en, $name_cn, $description, $updatedBy, $recordId);
                $stmt->execute();
                $stmt->close();

                // Clear existing permissions (simple replace strategy)
                $deletePerms = "DELETE FROM {$tableRolePermission} WHERE user_role_id = ?";
                $stmtDel = $conn->prepare($deletePerms);
                $stmtDel->bind_param('i', $recordId);
                $stmtDel->execute();
                $stmtDel->close();

                $targetId = $recordId;
                $mainActionType = 'E'; // Audit: Edit
            } else {
                // INSERT Logic
                $createdBy = (string)$currentUserId;
                $executedSql = "INSERT INTO {$tableRole} (name_en, name_cn, description, status, created_by, updated_by, created_at, updated_at) VALUES (?, ?, ?, 'A', ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($executedSql);
                $stmt->bind_param('sssss', $name_en, $name_cn, $description, $createdBy, $updatedBy);
                $stmt->execute();
                $targetId = (int)$conn->insert_id;
                $stmt->close();
                $mainActionType = 'A'; // Audit: Add
            }

            // Insert New Permissions (Bridge Table)
            if (!empty($selectedPerms)) {
                $bindSql = "INSERT INTO {$tableRolePermission} (user_role_id, page_id, action_id, created_by) VALUES (?, ?, ?, ?)";
                $bindStmt = $conn->prepare($bindSql);
                foreach ($selectedPerms as $permKey) {
                    // Parse "pageId_actionId" string
                    @list($pageId, $actionId) = explode('_', $permKey);
                    $pageId = (int)$pageId;
                    $actionId = (int)$actionId;
                    
                    $bindStmt->bind_param('iiii', $targetId, $pageId, $actionId, $currentUserId);
                    $bindStmt->execute();
                }
                $bindStmt->close();
            }

            // Commit Transaction
            $conn->commit();

            // Fetch New Data for Audit Logging
            $newRoleData = fetchUserRoleById($conn, $targetId);
            $newPerms = fetchRolePermissions($conn, $targetId);
            // Sort new permissions for comparison
            usort($newPerms, function($a, $b) {
                if ($a['page_id'] === $b['page_id']) {
                    return $a['action_id'] - $b['action_id'];
                }
                return $a['page_id'] - $b['page_id'];
            });

            // Perform Audit Logging
            if (function_exists('logAudit')) {
                // Log Main Role Change
                logAudit([
                    'page' => $auditPage,
                    'action' => $mainActionType,
                    'action_message' => ($mainActionType === 'A' ? 'Added' : 'Updated') . " User Role: {$name_en}",
                    'query' => $executedSql,
                    'query_table' => $tableRole,
                    'user_id' => $currentUserId,
                    'record_id' => $targetId,
                    'old_value' => $oldRoleData,
                    'new_value' => $newRoleData,
                ]);

                // Log Permission Changes (if added or changed)
                if ($mainActionType === 'A' || $oldPerms !== $newPerms) {
                    logAudit([
                        'page' => $auditPage,
                        'action' => $mainActionType === 'A' ? 'ROLE_PERM_BIND' : 'ROLE_PERM_UPDATE',
                        'action_message' => "Permissions updated for role: {$name_en}",
                        'user_id' => $currentUserId,
                        'record_id' => $targetId,
                        'old_value' => ['permissions' => $oldPerms],
                        'new_value' => ['permissions' => $newPerms],
                    ]);
                }
            }

            $_SESSION['flash_msg'] = '保存成功.';
            $_SESSION['flash_type'] = 'success';
            userRoleRedirect($baseListUrl);
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            error_log('Database error in user-role form: ' . $e->getMessage());
            $_SESSION['flash_msg'] = '数据库错误，请稍后重试或联系管理员。';
            $_SESSION['flash_type'] = 'danger';
            userRoleRedirect($redirectTo);
        }
    }
}

// 3. Load Data for Edit Mode
if ($isEditMode) {
    $loaded = fetchUserRoleById($conn, $recordId);
    if ($loaded && $loaded['status'] === 'A') {
        $formRow = $loaded;
    } else {
        $_SESSION['flash_msg'] = 'Record not found.';
        $_SESSION['flash_type'] = 'warning';
        userRoleRedirect($baseListUrl);
    }

    // Load existing permissions to pre-check checkboxes
    $assignedPerms = fetchRolePermissions($conn, $recordId);
}

// 4. Fetch Reference Data (Pages & Actions)
// Fetch all active pages for the permission matrix
$allPages = [];
$pageSql = 'SELECT id, name_en, name_cn, description FROM ' . PAGE_INFO_LIST . " WHERE status = 'A' ORDER BY id ASC";
$pageRes = $conn->query($pageSql);
if ($pageRes) {
    while ($row = $pageRes->fetch_assoc()) {
        $allPages[] = $row;
    }
}

// Fetch all active actions (view, add, edit, delete, etc.)
$allActions = [];
$actSql = 'SELECT id, name FROM ' . PAGE_ACTION . " WHERE status = 'A' ORDER BY id ASC";
$actRes = $conn->query($actSql);
if ($actRes) {
    while ($row = $actRes->fetch_assoc()) {
        $allActions[] = $row;
    }
}
?>

<div class="container-fluid px-0">
    <div class="card page-action-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <div>
                <div class="page-action-breadcrumb text-muted mb-1">Admin / User Role</div>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-shield-pen me-2"></i><?php echo $isEditMode ? '编辑用户角色' : '新增用户角色'; ?></h4>
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
                            <label class="form-label">Role Name (CN) <span class="text-danger">*</span></label>
                            <input type="text" name="name_cn" class="form-control" required value="<?php echo htmlspecialchars($formRow['name_cn']); ?>" placeholder="例如：管理员">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Role Name (EN) <span class="text-danger">*</span></label>
                            <input type="text" name="name_en" class="form-control" required value="<?php echo htmlspecialchars($formRow['name_en']); ?>" placeholder="e.g., Admin">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="角色描述"><?php echo htmlspecialchars($formRow['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3 text-dark"><i class="fa-solid fa-lock me-2"></i>Page Permissions / 页面权限绑定</h5>

                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllPermissions()">
                        <i class="fa-solid fa-check-double"></i> 全选
                    </button>
                    <div style="width: 250px;">
                        <input type="text" id="searchPages" class="form-control form-control-sm" placeholder="搜索页面..." onkeyup="filterPageCards()">
                    </div>
                </div>

                <div class="p-3 bg-light border rounded">
                    <?php if (empty($allPages)): ?>
                        <div class="text-muted small">暂无可用页面。请先在"页面信息列表"中添加。</div>
                    <?php else: ?>
                        <div class="row" id="pageCardsContainer">
                            <?php foreach ($allPages as $page): ?>
                                <div class="col-md-4 mb-3 page-card" data-page-name="<?php echo strtolower($page['name_en'] . ' ' . $page['name_cn']); ?>">
                                    <div class="card h-100">
                                        <div class="card-header bg-white cursor-pointer" role="button" onclick="togglePageCard(this)">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($page['name_en']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($page['name_cn']); ?></small>
                                                </div>
                                                <i class="fa-solid fa-chevron-down"></i>
                                            </div>
                                        </div>
                                        <div class="card-body" style="display:none;">
                                            <small class="text-muted d-block mb-2"><?php echo htmlspecialchars($page['description'] ?? ''); ?></small>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($allActions as $action): ?>
                                                    <?php
                                                    // Determine check status
                                                    $permKey = "{$page['id']}_{$action['id']}";
                                                    $isChecked = false;
                                                    foreach ($assignedPerms as $ap) {
                                                        if ($ap['page_id'] == $page['id'] && $ap['action_id'] == $action['id']) {
                                                            $isChecked = true;
                                                            break;
                                                        }
                                                    }
                                                    ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input perm-checkbox" type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($permKey); ?>" id="perm_<?php echo htmlspecialchars($permKey); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                                        <label class="form-check-label small user-select-none" for="perm_<?php echo htmlspecialchars($permKey); ?>">
                                                            <?php echo htmlspecialchars($action['name']); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end mt-4 gap-2">
                    <a href="<?php echo $baseListUrl; ?>" class="btn btn-light">取消</a>
                    <?php if (($isEditMode && !empty($perm->edit)) || (!$isEditMode && !empty($perm->add))): ?>
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-save"></i> 保存角色</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
// Path: src/pages/category/form.php
require_once dirname(__DIR__, 3) . '/common.php';

// Auth Check
requireLogin();

// 1. Use parent list page URL (single-page mode with cat_mode=form)
$currentUrl = parse_url(URL_NOVEL_CATS, PHP_URL_PATH) ?: '/category.php'; 

// [ADDED] Fetch dynamic permission object
$perm = hasPagePermission($conn, $currentUrl);

// 1. Check View Permission
checkPermissionError('view', $perm);
$pageName = getDynamicPageName($conn, $perm, $currentUrl);

$catTable  = NOVEL_CATEGORY;
$linkTable = CATEGORY_TAG;
$tagTable  = NOVEL_TAGS;
$auditPage = 'Category Management';

// =================================================================================
// PREDEFINED SQL QUERIES (Centralized)
// =================================================================================

// Write Queries
$insertQuery   = "INSERT INTO $catTable (name, created_by, updated_by) VALUES (?, ?, ?)";
$updateQuery   = "UPDATE $catTable SET name = ?, updated_by = ? WHERE id = ?";
$recoveryQuery = "UPDATE $catTable SET status = 'A', created_at = NOW(), updated_at = NOW(), created_by = ?, updated_by = ? WHERE id = ?";

// Read Queries
$selectByIdActiveQuery = "SELECT id, name, status, created_at, updated_at, created_by, updated_by FROM $catTable WHERE id = ? AND status = 'A'";
$selectByIdQuery       = "SELECT id, name, status, created_at, updated_at, created_by, updated_by FROM $catTable WHERE id = ?";

// Duplicate Check Queries
$checkDuplicateAddQuery  = "SELECT id, status FROM $catTable WHERE name = ?";
$checkDuplicateEditQuery = "SELECT id, status FROM $catTable WHERE name = ? AND id != ?";

// =================================================================================

// Context Detection
$id = (int)numberInput('id');
$id = $id !== null && $id > 0 ? $id : null;
$isEditMode = !empty($id);

// 2. Check Add/Edit Permission for initial load
$actionToCheck = $isEditMode ? 'edit' : 'add';
checkPermissionError($actionToCheck, $perm);

$listPageUrl = URL_NOVEL_CATS;
$formActionUrl = URL_NOVEL_CATS_FORM . ($isEditMode ? '&id=' . intval($id) : '');

// Define View Query for Audit Log
$viewQuery = $isEditMode
    ? "SELECT id, name, created_at, updated_at, created_by, updated_by FROM $catTable WHERE id = " . intval($id)
    : "SELECT id, name FROM $catTable WHERE status = 'A'";

$name = "";
$selectedTags = [];
$message = ""; $msgType = "";
$existingCatRow = null;

// [NEW] Flash Message Check (Reads message after redirect)
if (hasSession('flash_msg')) {
    $message = session('flash_msg');
    $msgType = session('flash_type');
    unsetSession('flash_msg');
    unsetSession('flash_type');
}

// Log "View" Action (Run only on GET request)
if (!isPostRequest()) {
    if (function_exists('logAudit')) {
        logAudit([
            'page'           => $auditPage,
            'action'         => 'V',
            'action_message' => $isEditMode ? "Viewing Edit Category Form (ID: $id)" : "Viewing Add Category Form",
            'query'          => $viewQuery,
            'query_table'    => $catTable,
            'user_id'        => sessionInt('user_id')
        ]);
    }
}

// 1. Fetch Existing Data
if ($isEditMode) {
    $stmt = $conn->prepare($selectByIdActiveQuery);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($cId, $cName, $cStatus, $cCreatedAt, $cUpdatedAt, $cCreatedBy, $cUpdatedBy);
    if ($stmt->fetch()) {
        $name = $cName;
        $existingCatRow = [
            'id' => $cId, 'name' => $cName, 'status' => $cStatus, 'created_at' => $cCreatedAt,
            'updated_at' => $cUpdatedAt, 'created_by' => $cCreatedBy, 'updated_by' => $cUpdatedBy
        ];
    } else { 
        $stmt->close();
        header("Location: $listPageUrl");
        exit(); 
    }
    $stmt->close();

    $tStmt = $conn->prepare("SELECT tag_id FROM $linkTable WHERE category_id = ?");
    $tStmt->bind_param("i", $id);
    $tStmt->execute();
    $tStmt->bind_result($tid);
    while ($tStmt->fetch()) { $selectedTags[] = $tid; }
    $tStmt->close();
}

// 2. Handle Submit
if (isPostRequest()) {

// Determine action and check permission using the common function
    $submitAction = $isEditMode ? 'edit' : 'add';
    $submitError = checkPermissionError($submitAction, $perm);
    
    if ($submitError) {
        // Instead of throwing an exception, we set the message to show in the UI
        $message = $submitError;
        $msgType = "danger";
    } else {
        $name    = postSpaceFilter('name');
        $rawTags = post('tags');
        $tagIds  = is_array($rawTags) ? $rawTags : [];
        $uid     = sessionInt('user_id');

        if (empty($name)) {
            $message = "分类名称不能为空"; $msgType = "danger";
        } elseif (empty($tagIds)) {
            $message = "请至少选择一个关联标签"; $msgType = "danger";
        } else {
            
            // Soft Delete & Duplicate Check
            $checkSql = $isEditMode ? $checkDuplicateEditQuery : $checkDuplicateAddQuery;
            $chk = $conn->prepare($checkSql);
            if ($isEditMode) {
                $chk->bind_param("si", $name, $id); 
            } else {
                $chk->bind_param("s", $name);
            }
            $chk->execute();
            $chk->store_result();
            
            $isRecoveryMode = false;
            $recoveryId = null;

            if ($chk->num_rows > 0) {
                $chk->bind_result($dupId, $dupStatus);
                $chk->fetch();
                
                if ($dupStatus === 'D' && !$isEditMode) {
                    $isRecoveryMode = true;
                    $recoveryId = $dupId;
                } else {
                    $message = "分类名称 '<strong>" . htmlspecialchars($name) . "</strong>' 已存在"; 
                    $msgType = "danger";
                }
            }
            $chk->close();

            if (empty($message)) {
                // Validate that ALL selected tags still exist AND are active
                $tagsValid = true;
                if (!empty($tagIds)) {
                    $safeTagIds = array_map('intval', $tagIds);
                    $idList = implode(',', $safeTagIds);
                    
                    $checkTags = $conn->query("SELECT COUNT(*) FROM $tagTable WHERE id IN ($idList) AND status = 'A'");
                    $foundCount = $checkTags ? $checkTags->fetch_row()[0] : 0;

                    if ($foundCount < count($safeTagIds)) {
                        $tagsValid = false;
                        $message = "操作失败：您选择的一个或多个标签已被其他用户删除。<br>页面已自动刷新，请检查标签列表后重新提交。";
                        $msgType = "danger";
                    }
                }

                if ($tagsValid) {
                    if ($isEditMode && empty($existingCatRow)) {
                        $fetchOld = $conn->prepare($selectByIdQuery);
                        $fetchOld->bind_param("i", $id);
                        $fetchOld->execute();
                        $fetchOld->bind_result($oId, $oName, $oStatus, $oCr, $oUp, $oCb, $oUb);
                        if ($fetchOld->fetch()) {
                            $existingCatRow = ['id' => $oId, 'name' => $oName, 'status' => $oStatus, 'created_at' => $oCr, 'updated_at' => $oUp, 'created_by' => $oCb, 'updated_by' => $oUb];
                        }
                        $fetchOld->close();
                    }

                    if ($isEditMode && !empty($existingCatRow)) {
                        $oldTags = $selectedTags; sort($oldTags);
                        $newTags = $tagIds; sort($newTags);
                        
                        $oldCompare = $existingCatRow;
                        $oldCompare['tags'] = implode(',', $oldTags);

                        checkNoChangesAndRedirect(
                            ['name' => $name, 'tags' => implode(',', $newTags)], 
                            $oldCompare
                        );
                    }

                    $conn->begin_transaction();
                    try {
                        // Prepare Insert/Update/Recovery
                        if ($isRecoveryMode) {
                            $upd = $conn->prepare($recoveryQuery);
                            $upd->bind_param("iii", $uid, $uid, $recoveryId);
                            $upd->execute();
                            $upd->close();
                            
                            // Delete old relations for the recovered ID
                            $del = $conn->prepare("DELETE FROM $linkTable WHERE category_id = ?");
                            $del->bind_param("i", $recoveryId);
                            $del->execute();
                            $del->close();

                            $targetId = $recoveryId;
                            $action = 'A';
                            $logMsg = "Recovered Soft-Deleted Category";
                        } else if ($isEditMode) {
                            $upd = $conn->prepare($updateQuery);
                            $upd->bind_param("sii", $name, $uid, $id);
                            $upd->execute();
                            $upd->close();
                            
                            $del = $conn->prepare("DELETE FROM $linkTable WHERE category_id = ?");
                            $del->bind_param("i", $id);
                            $del->execute();
                            $del->close();
                            
                            $targetId = $id;
                            $action = 'E';
                            $logMsg = "Updated Category";
                        } else {
                            $ins = $conn->prepare($insertQuery);
                            $ins->bind_param("sii", $name, $uid, $uid);
                            $ins->execute();
                            $targetId = $ins->insert_id;
                            $ins->close();
                            $action = 'A';
                            $logMsg = "Added New Category";
                        }

                        // Insert New Tags
                        if (!empty($tagIds)) {
                            $linkIns = $conn->prepare("INSERT INTO $linkTable (category_id, tag_id) VALUES (?, ?)");
                            foreach ($tagIds as $tid) {
                                $safeTid = intval($tid);
                                $linkIns->bind_param("ii", $targetId, $safeTid);
                                $linkIns->execute();
                            }
                            $linkIns->close();
                        }

                        $conn->commit();

                        // Fetch New Data for Audit Log
                        $newData = null;
                        $reload = $conn->prepare($selectByIdQuery);
                        if ($reload) {
                            $reload->bind_param("i", $targetId);
                            $reload->execute();
                            $reload->bind_result($nId, $nName, $nStatus, $nCreatedAt, $nUpdatedAt, $nCreatedBy, $nUpdatedBy);
                            if ($reload->fetch()) {
                                $newData = [
                                    'id' => $nId, 'name' => $nName, 'status' => $nStatus, 'created_at' => $nCreatedAt, 
                                    'updated_at' => $nUpdatedAt, 'created_by' => $nCreatedBy, 'updated_by' => $nUpdatedBy
                                ];
                            }
                            $reload->close();
                        }

                        if (empty($newData)) {
                            $now = date('Y-m-d H:i:s');
                            $newData = ['id' => $targetId, 'name' => $name, 'updated_at' => $now];
                        }

                        if ($isEditMode && empty($existingCatRow)) {
                            $existingCatRow = ['id' => $targetId, 'name' => $name];
                        }

                        if ($isRecoveryMode) {
                            $existingCatRow = $newData; 
                            $existingCatRow['status'] = 'D'; 
                        }

                        if (function_exists('logAudit')) {
                            logAudit([
                                'page'           => $auditPage,
                                'action'         => $action,
                                'action_message' => "$logMsg: $name",
                                'query'          => $isRecoveryMode ? $recoveryQuery : ($isEditMode ? $updateQuery : $insertQuery),
                                'query_table'    => $catTable,
                                'user_id'        => $uid,
                                'record_id'      => $targetId,
                                'record_name'    => $name,
                                'old_value'      => $existingCatRow,
                                'new_value'      => $newData
                            ]);
                        }
                        
                        setSession('flash_msg', '分类保存成功！');
                        setSession('flash_type', 'success');
                        header("Location: $listPageUrl");
                        exit();

                    } catch (Exception $e) {
                        $conn->rollback();
                        if ($conn->errno == 1452) {
                            $message = "操作失败：关联的标签不存在（可能已被删除）。请刷新页面重试。";
                        } else {
                            $message = "保存失败: " . $e->getMessage();
                        }
                        $msgType = "danger";
                    }
                } 
            }
        }
    }
}

// 5. [CRITICAL REFRESH] Fetch ONLY Active Tags for Checkboxes
$allTags = [];
$atRes = $conn->query("SELECT id, name FROM $tagTable WHERE status = 'A' ORDER BY name ASC");
if ($atRes) {
    while ($row = $atRes->fetch_assoc()) { $allTags[] = $row; }
}

?>
<?php $pageMetaKey = $currentUrl; ?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="category-container app-page-shell">
    <div class="card category-card">
        <div class="card-header bg-white py-3">
            <?php echo generateBreadcrumb($conn, $currentUrl); ?>
            <h4 class="m-0 text-primary"><i class="fa-solid fa-layer-group me-2"></i> <?php echo ($isEditMode ? '编辑' : '新增') . htmlspecialchars($pageName); ?></h4>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
                    <?php echo $message; ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form id="categoryForm" method="POST" action="<?php echo htmlspecialchars($formActionUrl); ?>" autocomplete="off" class="<?php echo $isEditMode ? 'check-changes' : ''; ?>">
                <div class="mb-4">
                    <label class="form-label text-muted">分类名称 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg" name="name" 
                           placeholder="例如：都市言情"
                           value="<?php echo htmlspecialchars($name); ?>" required>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted">关联标签 <span class="text-danger">*</span></label>
                    <div class="border rounded p-2 tag-checkbox-area">
                        <?php if (empty($allTags)): ?>
                            <div class="text-center text-muted py-3">暂无可用标签，请先去标签管理添加。</div>
                        <?php else: ?>
                            <div class="row g-2">
                                <?php foreach ($allTags as $tag): 
                                    $isChecked = in_array($tag['id'], $selectedTags) ? 'checked' : ''; 
                                ?>
                                <div class="col-6 col-sm-4 col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="tags[]" 
                                               value="<?php echo $tag['id']; ?>" 
                                               id="tag_<?php echo $tag['id']; ?>" <?php echo $isChecked; ?>>
                                        <label class="form-check-label" for="tag_<?php echo $tag['id']; ?>">
                                            <?php echo htmlspecialchars($tag['name']); ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-text">您可以为一个分类选择多个标签。</div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="<?php echo $listPageUrl; ?>" class="btn btn-light text-muted">取消</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">保存分类</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
</body>
</html>
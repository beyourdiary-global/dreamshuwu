<?php
// Path: src/pages/category/form.php
require_once dirname(__DIR__, 3) . '/common.php';

// Auth Check
requireLogin();

// 1. Use parent list page URL (single-page mode with cat_mode=form)
$currentUrl = '/dashboard.php?view=categories'; 

// [ADDED] Fetch dynamic permission object
$perm = hasPagePermission($conn, $currentUrl);

// 1. Check View Permission
checkPermissionError('view', $perm);
$pageName = getDynamicPageName($conn, $perm, $currentUrl);

$catTable  = NOVEL_CATEGORY;
$linkTable = CATEGORY_TAG;
$tagTable  = NOVEL_TAGS;
$auditPage = 'Category Management';
$insertQuery = "INSERT INTO $catTable (name, created_by, updated_by) VALUES (?, ?, ?)";
$updateQuery = "UPDATE $catTable SET name = ?, updated_by = ? WHERE id = ?";

// Context Detection
$isEmbeddedCatForm = isset($EMBED_CAT_FORM_PAGE) && $EMBED_CAT_FORM_PAGE === true;

$id = (int)numberInput('id');
$isEditMode = !empty($id);

// 2. Check Add/Edit Permission for initial load
$actionToCheck = $isEditMode ? 'edit' : 'add';
checkPermissionError($actionToCheck, $perm);

if ($isEmbeddedCatForm) {
    $listPageUrl = URL_NOVEL_CATS;
    $formActionUrl = URL_NOVEL_CATS_FORM . ($isEditMode ? '&id=' . intval($id) : ''); 
} else {
    $listPageUrl = URL_NOVEL_CATS; 
    $formActionUrl = '';
}

// Define View Query (Correctly interpolated)
$viewQuery = $isEditMode
    ? "SELECT id, name, created_at, updated_at, created_by, updated_by FROM $catTable WHERE id = " . intval($id)
    : "SELECT id, name FROM $catTable";

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

// [NEW] Log "View" Action (Run only on GET request)
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

// 1. Fetch Data
if ($isEditMode) {
    $stmt = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM $catTable WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($cId, $cName, $cCreatedAt, $cUpdatedAt, $cCreatedBy, $cUpdatedBy);
    if ($stmt->fetch()) {
        $name = $cName;
        $existingCatRow = [
            'id' => $cId, 'name' => $cName, 'created_at' => $cCreatedAt,
            'updated_at' => $cUpdatedAt, 'created_by' => $cCreatedBy, 'updated_by' => $cUpdatedBy
        ];
    } else { 
        $stmt->close();
        if ($isEmbeddedCatForm || headers_sent()) {
            echo "<script>window.location.href='$listPageUrl';</script>";
        } else {
            header("Location: $listPageUrl");
        }
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
    $name   = postSpaceFilter('name');
    // [FIX] Safely retrieve tags from POST data instead of GET
    $rawTags = post('tags');
    $tagIds  = is_array($rawTags) ? $rawTags : [];
    $uid    = sessionInt('user_id');

    if (empty($name)) {
        $message = "分类名称不能为空"; $msgType = "danger";
    } elseif (empty($tagIds)) {
        // [NEW] Backend validation to ensure at least one tag is submitted
        $message = "请至少选择一个关联标签"; $msgType = "danger";
    } else {
        // Check Duplicates
        $checkSql = $isEditMode ? "SELECT id FROM $catTable WHERE name = ? AND id != ?" : "SELECT id FROM $catTable WHERE name = ?";
        $chk = $conn->prepare($checkSql);
        if ($isEditMode) $chk->bind_param("si", $name, $id); else $chk->bind_param("s", $name);
        $chk->execute();
        $chk->store_result();
        
        if ($chk->num_rows > 0) {
            $message = "分类名称 '<strong>$name</strong>' 已存在"; $msgType = "danger";
            $chk->close();
        } else {
            $chk->close();

            // [NEW FIX] Validate that ALL selected tags still exist in the database
            $tagsValid = true;
            if (!empty($tagIds)) {
                // Convert IDs to integers for safety
                $safeTagIds = array_map('intval', $tagIds);
                // Create a comma-separated string of IDs
                $idList = implode(',', $safeTagIds);
                
                // Count how many of these IDs actually exist in the database
                $checkTags = $conn->query("SELECT COUNT(*) FROM $tagTable WHERE id IN ($idList)");
                $foundCount = $checkTags ? $checkTags->fetch_row()[0] : 0;

                if ($foundCount < count($safeTagIds)) {
                    $tagsValid = false;
                    $message = "操作失败：您选择的一个或多个标签已被其他用户删除。<br>页面已自动刷新，请检查标签列表后重新提交。";
                    $msgType = "danger";
                    // Note: We intentionally DO NOT proceed to save. 
                    // The page will continue to render below, fetching the *fresh* tag list automatically.
                }
            }

            if ($tagsValid) {
                // [CRITICAL] Ensure Old Value is captured if it was missed
                if ($isEditMode && empty($existingCatRow)) {
                    $fetchOld = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM $catTable WHERE id = ?");
                    $fetchOld->bind_param("i", $id);
                    $fetchOld->execute();
                    $fetchOld->bind_result($oId, $oName, $oCr, $oUp, $oCb, $oUb);
                    if ($fetchOld->fetch()) {
                        $existingCatRow = ['id' => $oId, 'name' => $oName, 'created_at' => $oCr, 'updated_at' => $oUp, 'created_by' => $oCb, 'updated_by' => $oUb];
                    }
                    $fetchOld->close();
                }

                // [REUSE] Check for Changes using Helper
                if ($isEditMode && !empty($existingCatRow)) {
                    // Prepare Tags for comparison (Sort to ignore order differences)
                    $oldTags = $selectedTags; 
                    sort($oldTags);
                    
                    $newTags = $tagIds; 
                    sort($newTags);

                    // Create comparison array
                    $oldCompare = $existingCatRow;
                    // [FIX] Convert arrays to strings to prevent "Array to string conversion" crash
                    $oldCompare['tags'] = implode(',', $oldTags);

                    // Pass name and tags to check safely
                    checkNoChangesAndRedirect(
                        ['name' => $name, 'tags' => implode(',', $newTags)], 
                        $oldCompare
                    );
                }

                $conn->begin_transaction();
                try {
                    // Prepare Insert/Update
                    if ($isEditMode) {
                        $upd = $conn->prepare($updateQuery);
                        $upd->bind_param("sii", $name, $uid, $id);
                        $upd->execute();
                        $upd->close();
                        
                        // Handle Tags
                        $del = $conn->prepare("DELETE FROM $linkTable WHERE category_id = ?");
                        $del->bind_param("i", $id);
                        $del->execute();
                        $del->close();
                        
                        $targetId = $id;
                        $action = 'E';
                    } else {
                        $ins = $conn->prepare($insertQuery);
                        $ins->bind_param("sii", $name, $uid, $uid);
                        $ins->execute();
                        $targetId = $ins->insert_id;
                        $ins->close();
                        $action = 'A';
                    }

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
                    $reload = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM $catTable WHERE id = ?");
                    if ($reload) {
                        $reload->bind_param("i", $targetId);
                        $reload->execute();
                        $reload->bind_result($nId, $nName, $nCreatedAt, $nUpdatedAt, $nCreatedBy, $nUpdatedBy);
                        if ($reload->fetch()) {
                            $newData = [
                                'id' => $nId, 
                                'name' => $nName, 
                                'created_at' => $nCreatedAt, 
                                'updated_at' => $nUpdatedAt, 
                                'created_by' => $nCreatedBy, 
                                'updated_by' => $nUpdatedBy
                            ];
                        }
                        $reload->close();
                    }

                    // Fallback: ensure new_value is not empty even if reload fails on some hosts
                    if (empty($newData)) {
                        $now = date('Y-m-d H:i:s');
                        $newData = [
                            'id' => $targetId,
                            'name' => $name,
                            'created_at' => $isEditMode ? ($existingCatRow['created_at'] ?? null) : $now,
                            'updated_at' => $now,
                            'created_by' => $isEditMode ? ($existingCatRow['created_by'] ?? $uid) : $uid,
                            'updated_by' => $uid,
                        ];
                    }

                    if ($isEditMode && empty($existingCatRow)) {
                        $existingCatRow = ['id' => $targetId, 'name' => $name];
                    }

                    if (function_exists('logAudit')) {
                        logAudit([
                            'page'           => $auditPage,
                            'action'         => $action,
                            'action_message' => "Saved Category: $name",
                            'query'          => $isEditMode ? $updateQuery : $insertQuery,
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
                    $redirectUrl = $listPageUrl;
                    if ($isEmbeddedCatForm || headers_sent()) {
                        echo "<script>window.location.href='$redirectUrl';</script>";
                    } else {
                        header("Location: $redirectUrl");
                    }
                    exit();

                } catch (Exception $e) {
                    $conn->rollback();
                    // [ENHANCED ERROR HANDLING] Catch foreign key errors specifically
                    if ($conn->errno == 1452) {
                        $message = "操作失败：关联的标签不存在（可能已被删除）。请刷新页面重试。";
                    } else {
                        $message = "保存失败: " . $e->getMessage();
                    }
                    $msgType = "danger";
                    }
                } // End if tagsValid
             }
        }
    }
}

// 5. [CRITICAL REFRESH] Fetch All Tags (Run this AFTER any POST logic)
// This ensures that if a deletion happened, the form re-renders with the UPDATED list.
$allTags = [];
$atRes = $conn->query("SELECT id, name FROM $tagTable ORDER BY name ASC");
if ($atRes) {
    while ($row = $atRes->fetch_assoc()) { $allTags[] = $row; }
}

if ($isEmbeddedCatForm): ?>
<div class="category-container">
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
<?php else: ?>
<?php endif; ?>
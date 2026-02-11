<?php
// Path: src/pages/category/form.php
require_once __DIR__ . '/../../../init.php';
defined('URL_HOME') || require_once BASE_PATH . 'urls.php';
require_once BASE_PATH . 'functions.php';


// Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (headers_sent()) {
        echo "<script>window.location.href='" . URL_LOGIN . "';</script>";
    } else {
        header("Location: " . URL_LOGIN);
    }
    exit();
}

$catTable  = NOVEL_CATEGORY;
$linkTable = CATEGORY_TAG;
$tagTable  = NOVEL_TAGS;
$auditPage = 'Category Management';
$insertQuery = "INSERT INTO $catTable (name, created_by, updated_by) VALUES (?, ?, ?)";
$updateQuery = "UPDATE $catTable SET name = ?, updated_by = ? WHERE id = ?";

// Context Detection
$isEmbeddedTagForm = isset($EMBED_CAT_FORM_PAGE) && $EMBED_CAT_FORM_PAGE === true;

if ($isEmbeddedTagForm) {
    $listPageUrl = URL_USER_DASHBOARD . '?view=categories';
    $formActionUrl = URL_USER_DASHBOARD . '?view=cat_form'; 
} else {
    $listPageUrl = URL_NOVEL_CATS; 
    $formActionUrl = '';
}

$id = $_GET['id'] ?? null;
$isEdit = !empty($id);

// Define View Query
$viewQuery = $isEdit
    ? "SELECT id, name, created_at, updated_at, created_by, updated_by FROM $catTable WHERE id = ?"
    : "SELECT id, name FROM $catTable";

$name = "";
$selectedTags = [];
$message = ""; $msgType = "";
$existingCatRow = null;

// 1. Fetch Data
if ($isEdit) {
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
        if ($isEmbeddedTagForm || headers_sent()) {
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
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $tagIds = $_POST['tags'] ?? []; 
    $uid = $_SESSION['user_id'];

    if (empty($name)) {
        $message = "分类名称不能为空"; $msgType = "danger";
    } else {
        // Check Duplicates
        $checkSql = $isEdit ? "SELECT id FROM $catTable WHERE name = ? AND id != ?" : "SELECT id FROM $catTable WHERE name = ?";
        $chk = $conn->prepare($checkSql);
        if ($isEdit) $chk->bind_param("si", $name, $id); else $chk->bind_param("s", $name);
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
                $foundCount = $checkTags ? (int)$checkTags->fetch_row()[0] : 0;

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
                if ($isEdit && empty($existingCatRow)) {
                    $fetchOld = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM $catTable WHERE id = ?");
                    $fetchOld->bind_param("i", $id);
                    $fetchOld->execute();
                    $fetchOld->bind_result($oId, $oName, $oCr, $oUp, $oCb, $oUb);
                    if ($fetchOld->fetch()) {
                        $existingCatRow = ['id' => $oId, 'name' => $oName, 'created_at' => $oCr, 'updated_at' => $oUp, 'created_by' => $oCb, 'updated_by' => $oUb];
                    }
                    $fetchOld->close();
                }

                $conn->begin_transaction();
                try {
                    // Prepare Insert/Update
                    if ($isEdit) {
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
                            'created_at' => $isEdit ? ($existingCatRow['created_at'] ?? null) : $now,
                            'updated_at' => $now,
                            'created_by' => $isEdit ? ($existingCatRow['created_by'] ?? $uid) : $uid,
                            'updated_by' => $uid,
                        ];
                    }

                    if (function_exists('logAudit')) {
                        logAudit([
                            'page'           => $auditPage,
                            'action'         => $action,
                            'action_message' => "Saved Category: $name",
                            'query'          => $isEdit ? $updateQuery : $insertQuery,
                            'query_table'    => $catTable,
                            'user_id'        => $uid,
                            'old_value'      => $existingCatRow,
                            'new_value'      => $newData
                        ]);
                    }
                    
                    $_SESSION['flash_msg'] = '分类保存成功！';
                    $_SESSION['flash_type'] = 'success';
                    $redirectUrl = $listPageUrl;
                    if ($isEmbeddedTagForm || headers_sent()) {
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
// 4. Handle Page View (GET) Audit Log
else {
    if (!defined('CAT_FORM_VIEW_LOGGED')) {
        define('CAT_FORM_VIEW_LOGGED', true);
        if (function_exists('logAudit')) {
            logAudit([
                'page'           => $auditPage,
                'action'         => 'V',
                'action_message' => $isEdit ? "Viewing Edit Category Form: $name" : "Viewing Add Category Form",
                'query'          => $viewQuery,
                'query_table'    => $catTable,
                'user_id'        => $_SESSION['user_id']
            ]);
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

$pageTitle = ($isEdit ? "编辑分类" : "新增分类") . " - " . WEBSITE_NAME;

if ($isEmbeddedTagForm): ?>
<div class="category-container">
    <div class="card category-card">
        <div class="card-header bg-white py-3">
            <h4 class="m-0 text-primary"><i class="fa-solid fa-layer-group me-2"></i> <?php echo $isEdit ? "编辑分类" : "新增分类"; ?></h4>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
                    <?php echo $message; ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($formActionUrl); ?>" autocomplete="off">
                <div class="mb-4">
                    <label class="form-label text-muted">分类名称 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg" name="name" 
                           placeholder="例如：都市言情"
                           value="<?php echo htmlspecialchars($name); ?>" required>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted">关联标签</label>
                    <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
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
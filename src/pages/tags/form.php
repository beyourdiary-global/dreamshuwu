<?php
require_once dirname(__DIR__, 3) . '/common.php';

requireLogin();

// 1. Use parent list page URL (single-page mode with tag_mode=form)
$currentUrl = parse_url(URL_NOVEL_TAGS, PHP_URL_PATH) ?: '/tags.php'; 

// [ADDED] Fetch dynamic permission object
$perm = hasPagePermission($conn, $currentUrl);

checkPermissionError('view', $perm);
$pageName = getDynamicPageName($conn, $perm, $currentUrl);

$tagTable = NOVEL_TAGS;
$auditPage = 'Tag Management';

// =================================================================================
// PREDEFINED SQL QUERIES (Centralized for clean code and easy maintenance)
// =================================================================================

// Write Queries
$insertQuery   = "INSERT INTO $tagTable (name, created_by, updated_by) VALUES (?, ?, ?)";
$updateQuery   = "UPDATE $tagTable SET name = ?, updated_by = ? WHERE id = ?";
$recoveryQuery = "UPDATE $tagTable SET status = 'A', created_at = NOW(), updated_at = NOW(), created_by = ?, updated_by = ? WHERE id = ?";

// Read Queries
$selectByIdActiveQuery = "SELECT id, name, status, created_at, updated_at, created_by, updated_by FROM $tagTable WHERE id = ? AND status = 'A'";
$selectByIdQuery       = "SELECT id, name, status, created_at, updated_at, created_by, updated_by FROM $tagTable WHERE id = ?";

// Duplicate Check Queries
$checkDuplicateAddQuery  = "SELECT id, status FROM $tagTable WHERE name = ?";
$checkDuplicateEditQuery = "SELECT id, status FROM $tagTable WHERE name = ? AND id != ?";

// =================================================================================

// 2. Context Detection
$tagId = (int)numberInput('id');
$tagId = $tagId !== null && $tagId > 0 ? $tagId : null;
$isEditMode = !empty($tagId);

// [ADDED] Specific Action Permission Check
$actionToCheck = $isEditMode ? 'edit' : 'add';
checkPermissionError($actionToCheck, $perm);

$listPageUrl = URL_NOVEL_TAGS;
$formActionUrl = URL_NOVEL_TAGS_FORM . ($isEditMode ? '&id=' . intval($tagId) : '');

// Define View Query for Audit Log (Correctly interpolated)
$viewQuery = $isEditMode
    ? "SELECT id, name, created_at, updated_at, created_by, updated_by FROM $tagTable WHERE id = " . intval($tagId)
    : "SELECT id, name FROM $tagTable WHERE status = 'A'";

$tagName = "";
$message = ""; 
$msgType = "";
$existingTagRow = null;

// [NEW] Flash Message Check (Reads message after redirect)
if (hasSession('flash_msg')) {
    $message = session('flash_msg');
    $msgType = session('flash_type');
    unsetSession('flash_msg');
    unsetSession('flash_type');
}

// Log "View" Action
if (!isPostRequest()) {
    if (function_exists('logAudit')) {
        logAudit([
            'page'           => $auditPage,
            'action'         => 'V',
            'action_message' => $isEditMode ? "Viewing Edit Tag Form (ID: $tagId)" : "Viewing Add Tag Form",
            'query'          => $viewQuery,
            'query_table'    => $tagTable,
            'user_id'        => sessionInt('user_id')
        ]);
    }
}

try {
    // 3. Load Existing Data (Initial Page Load - Must be Active)
    if ($isEditMode) {
        $stmt = $conn->prepare($selectByIdActiveQuery);
        $stmt->bind_param("i", $tagId);
        $stmt->execute();
        $stmt->bind_result($rowId, $rowName, $rowStatus, $rowCreatedAt, $rowUpdatedAt, $rowCreatedBy, $rowUpdatedBy);
        if ($stmt->fetch()) {
            $tagName = $rowName;
            $existingTagRow = [
                'id' => $rowId, 'name' => $rowName, 'status' => $rowStatus, 'created_at' => $rowCreatedAt,
                'updated_at' => $rowUpdatedAt, 'created_by' => $rowCreatedBy, 'updated_by' => $rowUpdatedBy,
            ];
        } else {
            $stmt->close();
            header("Location: " . $listPageUrl);
            exit();
        }
        $stmt->close();
    }

    // 4. Handle Form Submission (POST)
    if (isPostRequest()) {
        $submitAction = $isEditMode ? 'edit' : 'add';
        $submitError = checkPermissionError($submitAction, $perm);
        
        if ($submitError) {
            $message = $submitError;
            $msgType = "danger";
        } else {
            $tagName = postSpaceFilter('tag_name');
            $postedTagId = post('tag_id') ?: null;
            
            if ($postedTagId) {
                $tagId = $postedTagId;
                $isEditMode = true;
            }
            $currentUserId = sessionInt('user_id');

            if (!$conn || !($conn instanceof mysqli)) throw new Exception('Database connection is not available.');

            if (empty($tagName)) {
                $message = "标签名称不能为空"; $msgType = "danger";
            } else {
                $changeResult = false;
                if ($isEditMode && !empty($existingTagRow)) {
                    $changeResult = checkNoChangesAndRedirect(['name' => $tagName], $existingTagRow);
                }

                if (is_array($changeResult)) {
                    $message = $changeResult['message']; 
                    $msgType = $changeResult['type'];
                } else {
                    
                    // [REFACTORED] Duplicate Check & Soft Delete Recovery logic
                    $sql = $isEditMode ? $checkDuplicateEditQuery : $checkDuplicateAddQuery;
                    
                    $chk = $conn->prepare($sql);
                    if (!$chk) {
                        throw new Exception($conn->error ?: 'Failed to prepare duplicate check.');
                    }
                    if ($isEditMode) {
                        $chk->bind_param("si", $tagName, $tagId); 
                    } else {
                        $chk->bind_param("s", $tagName);
                    }
                    $chk->execute();
                    $chk->store_result();
                    
                    $isRecoveryMode = false;
                    $recoveryId = null;

                    if ($chk->num_rows > 0) {
                        $chk->bind_result($dupId, $dupStatus);
                        $chk->fetch();
                        
                        if ($dupStatus === 'D' && !$isEditMode) {
                            // Trigger recovery if it's a deleted tag and we are adding a new one
                            $isRecoveryMode = true;
                            $recoveryId = $dupId;
                        } else {
                            // Trigger duplicate error
                            $message = "标签 '<strong>" . htmlspecialchars($tagName) . "</strong>' 已存在"; 
                            $msgType = "danger";
                        }
                    }
                    $chk->close();

                    if (empty($message)) {
                        // Load Old Record if Edit Mode and old row wasn't pre-loaded
                        if ($isEditMode && empty($existingTagRow)) {
                            $fetchOld = $conn->prepare($selectByIdQuery);
                            $fetchOld->bind_param("i", $tagId);
                            $fetchOld->execute();
                            $fetchOld->bind_result($oId, $oName, $oStatus, $oCr, $oUp, $oCb, $oUb);
                            if ($fetchOld->fetch()) {
                                $existingTagRow = ['id' => $oId, 'name' => $oName, 'status' => $oStatus, 'created_at' => $oCr, 'updated_at' => $oUp, 'created_by' => $oCb, 'updated_by' => $oUb];
                            }
                            $fetchOld->close();
                        }

                        // Prepare Query Flow
                        if ($isRecoveryMode) {
                            $stmt = $conn->prepare($recoveryQuery);
                            $stmt->bind_param("iii", $currentUserId, $currentUserId, $recoveryId);
                            $action = 'A'; $logMsg = "Recovered Soft-Deleted Tag";
                            $targetId = $recoveryId;
                        } else if ($isEditMode) {
                            $stmt = $conn->prepare($updateQuery);
                            $stmt->bind_param("sii", $tagName, $currentUserId, $tagId);
                            $action = 'E'; $logMsg = "Updated Tag";
                            $targetId = $tagId;
                        } else {
                            $stmt = $conn->prepare($insertQuery);
                            $stmt->bind_param("sii", $tagName, $currentUserId, $currentUserId);
                            $action = 'A'; $logMsg = "Added New Tag";
                        }

                        if ($stmt->execute()) {
                            if (!$isEditMode && !$isRecoveryMode) {
                                $targetId = $conn->insert_id;
                            }
                            $stmt->close(); 

                            // [REFACTORED] Fetch Fresh Audit Data
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
                                $newData = ['id' => $targetId, 'name' => $tagName, 'updated_at' => $now];
                            }

                            if ($isEditMode && empty($existingTagRow)) {
                                $existingTagRow = ['id' => $targetId, 'name' => $tagName];
                            }

                            // If Recovery mode, explicitly track old value to show transition in Audit Log
                            if ($isRecoveryMode) {
                                $existingTagRow = $newData; 
                                $existingTagRow['status'] = 'D'; // Previous status was deleted
                            }

                            if (function_exists('logAudit')) {
                                logAudit([
                                    'page'           => $auditPage,
                                    'action'         => $action,
                                    'action_message' => "$logMsg: $tagName",
                                    'query'          => $isRecoveryMode ? $recoveryQuery : ($isEditMode ? $updateQuery : $insertQuery),
                                    'query_table'    => $tagTable,
                                    'user_id'        => $currentUserId,
                                    'record_id'      => $targetId,
                                    'record_name'    => $tagName,
                                    'old_value'      => $existingTagRow,
                                    'new_value'      => $newData
                                ]);
                            }
                            
                            setSession('flash_msg', '标签保存成功！');
                            setSession('flash_type', 'success');
                            $redirectUrl = $listPageUrl;
                            if (!headers_sent()) header("Location: " . $redirectUrl);
                            else echo "<script>window.location.href = '" . $redirectUrl . "';</script>";
                            exit();
                        } else {
                            throw new Exception($stmt->error);
                        }
                    }
                }
            }
        } 
    }
} catch (Exception $e) {
    $message = "System Error: " . $e->getMessage(); $msgType = "danger";
}

$pageMetaKey = $currentUrl;
?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="tag-container app-page-shell">
    <div class="card tag-card">
        <div class="card-header bg-white py-3">
            <?php echo generateBreadcrumb($conn, $currentUrl); ?>
            <h4 class="m-0 text-primary">
                <i class="fa-solid fa-tag me-2"></i> <?php echo ($isEditMode ? '编辑' : '新增') . htmlspecialchars($pageName); ?>
            </h4>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
                    <?php echo $message; ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <form method="POST" action="<?php echo htmlspecialchars($formActionUrl); ?>" autocomplete="off" class="check-changes">
                <?php if ($isEditMode): ?>
                    <input type="hidden" name="tag_id" value="<?php echo $tagId; ?>">
                <?php endif; ?>
                <div class="mb-4">
                    <label class="form-label text-muted">标签名称</label>
                    <input type="text" class="form-control form-control-lg" name="tag_name" 
                           placeholder="例如：现代言情" 
                           value="<?php echo htmlspecialchars($tagName); ?>" required>
                    <div class="form-text">标签名称必须唯一。</div>
                </div>
                <div class="d-flex justify-content-between">
                    <a href="<?php echo $listPageUrl; ?>" class="btn btn-light text-muted">取消</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">保存标签</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
</body>
</html>
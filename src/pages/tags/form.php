<?php
require_once dirname(__DIR__, 3) . '/common.php';

// 1. Identify this specific view's URL as registered in your DB
$currentUrl = '/dashboard.php?view=tag_form'; 

// [ADDED] Fetch dynamic permission object
$perm = hasPagePermission($conn, $currentUrl);

// 2. Base View Check (If they can't even view the form, block them)
if (empty($perm) || !$perm->view) {
    denyAccess("权限不足：您没有访问此表单的权限。");
}

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (headers_sent()) {
        echo "<script>window.location.href='" . URL_LOGIN . "';</script>";
    } else {
        header("Location: " . URL_LOGIN);
    }
    exit();
}

$tagTable = NOVEL_TAGS;
$auditPage = 'Tag Management';
$insertQuery = "INSERT INTO $tagTable (name, created_by, updated_by) VALUES (?, ?, ?)";
$updateQuery = "UPDATE $tagTable SET name = ?, updated_by = ? WHERE id = ?";

// 2. Context Detection
$isEmbeddedTagForm = isset($EMBED_TAG_FORM_PAGE) && $EMBED_TAG_FORM_PAGE === true;

$tagId = $_GET['id'] ?? null;
$tagId = $tagId !== null ? (int) $tagId : null;
$isEditMode = !empty($tagId);

// [ADDED] Specific Action Permission Check
// If Edit Mode: must have 'edit' permission. If Add Mode: must have 'add' permission.
if ($isEditMode && !$perm->edit) {
    denyAccess("权限不足：您没有编辑标签的权限。");
} elseif (!$isEditMode && !$perm->add) {
    denyAccess("权限不足：您没有新增标签的权限。");
}

if ($isEmbeddedTagForm) {
    $listPageUrl = URL_USER_DASHBOARD . '?view=tags';
    $formActionUrl = URL_USER_DASHBOARD . '?view=tag_form' . ($isEditMode ? '&id=' . intval($tagId) : ''); 
} else {
    $listPageUrl = defined('URL_NOVEL_TAGS') ? URL_NOVEL_TAGS : 'index.php';
    $formActionUrl = ''; 
}

// Define View Query for Audit Log (Correctly interpolated)
$viewQuery = $isEditMode
    ? "SELECT id, name, created_at, updated_at, created_by, updated_by FROM $tagTable WHERE id = " . intval($tagId)
    : "SELECT id, name FROM $tagTable";

$tagName = "";
$message = ""; 
$msgType = "";
$existingTagRow = null;

// [NEW] Flash Message Check (Reads message after redirect)
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    $msgType = $_SESSION['flash_type'];
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// [NEW] Log "View" Action (Run only on GET request)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    if (!defined('TAG_FORM_VIEW_LOGGED')) {
        define('TAG_FORM_VIEW_LOGGED', true);
        if (function_exists('logAudit')) {
            logAudit([
                'page'           => $auditPage,
                'action'         => 'V',
                'action_message' => $isEditMode ? "Viewing Edit Tag Form (ID: $tagId)" : "Viewing Add Tag Form",
                'query'          => $viewQuery,
                'query_table'    => $tagTable,
                'user_id'        => $_SESSION['user_id'] ?? 0
            ]);
        }
    }
}

try {
    // 3. Load Existing Data (Initial Page Load)
    if ($isEditMode) {
        $stmt = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $tagTable . " WHERE id = ?");
        $stmt->bind_param("i", $tagId);
        $stmt->execute();
        $stmt->bind_result($rowId, $rowName, $rowCreatedAt, $rowUpdatedAt, $rowCreatedBy, $rowUpdatedBy);
        if ($stmt->fetch()) {
            $tagName = $rowName;
            $existingTagRow = [
                'id' => $rowId, 'name' => $rowName, 'created_at' => $rowCreatedAt,
                'updated_at' => $rowUpdatedAt, 'created_by' => $rowCreatedBy, 'updated_by' => $rowUpdatedBy,
            ];
        } else {
            $stmt->close();
            if ($isEmbeddedTagForm || headers_sent()) {
                echo "<script>window.location.href='$listPageUrl';</script>";
            } else {
                header("Location: " . $listPageUrl);
            }
            exit();
        }
        $stmt->close();
    }

    // 4. Handle Form Submission (POST)
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // [ADDED] Re-verify strict permissions before DB transaction
    if ($isEditMode && !$perm->edit) {
        throw new Exception("Unauthorized: You do not have permission to edit records.");
    }
    if (!$isEditMode && !$perm->add) {
        throw new Exception("Unauthorized: You do not have permission to add records.");
    }
        $tagName = trim($_POST['tag_name'] ?? '');
        $postedTagId = isset($_POST['tag_id']) ? (int) $_POST['tag_id'] : null;
        
        if ($postedTagId) {
            $tagId = $postedTagId;
            $isEditMode = true;
        }
        $currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

        if (!$conn || !($conn instanceof mysqli)) throw new Exception('Database connection is not available.');

        if (empty($tagName)) {
            $message = "标签名称不能为空"; $msgType = "danger";
        } else {
            // [NEW] Use global helper to check changes and redirect
            if ($isEditMode && !empty($existingTagRow)) {
                checkNoChangesAndRedirect(['name' => $tagName], $existingTagRow);
            }

            // Check for duplicates
            $sql = $isEditMode ? "SELECT id FROM $tagTable WHERE name = ? AND id != ?" : "SELECT id FROM $tagTable WHERE name = ?";
            $chk = $conn->prepare($sql);
            if (!$chk) {
                throw new Exception($conn->error ?: 'Failed to prepare duplicate check.');
            }
            if ($isEditMode) $chk->bind_param("si", $tagName, $tagId); else $chk->bind_param("s", $tagName);
            $chk->execute();
            $chk->store_result();
            
            if ($chk->num_rows > 0) {
                $message = "标签 '<strong>" . htmlspecialchars($tagName) . "</strong>' 已存在"; $msgType = "danger";
                $chk->close();
            } else {
                $chk->close(); // Close duplicate check statement immediately

                // [CRITICAL] If Edit Mode, ensure we have Old Data for Audit Log BEFORE updating
                if ($isEditMode && empty($existingTagRow)) {
                    $fetchOld = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $tagTable . " WHERE id = ?");
                    $fetchOld->bind_param("i", $tagId);
                    $fetchOld->execute();
                    $fetchOld->bind_result($oId, $oName, $oCr, $oUp, $oCb, $oUb);
                    if ($fetchOld->fetch()) {
                        $existingTagRow = ['id' => $oId, 'name' => $oName, 'created_at' => $oCr, 'updated_at' => $oUp, 'created_by' => $oCb, 'updated_by' => $oUb];
                    }
                    $fetchOld->close();
                }

                // Prepare Insert/Update
                if ($isEditMode) {
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->bind_param("sii", $tagName, $currentUserId, $tagId);
                    $action = 'E'; $logMsg = "Updated Tag";
                } else {
                    $stmt = $conn->prepare($insertQuery);
                    $stmt->bind_param("sii", $tagName, $currentUserId, $currentUserId);
                    $action = 'A'; $logMsg = "Added New Tag";
                }

                if ($stmt->execute()) {
                    // [CRITICAL FIX] Capture ID and Close Statement immediately
                    $targetId = $isEditMode ? $tagId : $conn->insert_id;
                    $stmt->close(); 

                    // 1. [NEW] Prepare Data for Audit Log (Fetch fresh data)
                    $newData = null;
                    $reload = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $tagTable . " WHERE id = ?");
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

                    // Fallback if fetch failed
                    if (empty($newData)) {
                        $now = date('Y-m-d H:i:s');
                        $newData = ['id' => $targetId, 'name' => $tagName, 'updated_at' => $now];
                    }

                    // Ensure Old Value exists for Edit Mode
                    if ($isEditMode && empty($existingTagRow)) {
                        $existingTagRow = ['id' => $targetId, 'name' => $tagName];
                    }

                    // 2. [AUDIT] Log the Action (ONLY ONCE)
                    if (function_exists('logAudit')) {
                        logAudit([
                            'page'           => $auditPage,
                            'action'         => $action,
                            'action_message' => "$logMsg: $tagName",
                            'query'          => $isEditMode ? $updateQuery : $insertQuery,
                            'query_table'    => $tagTable,
                            'user_id'        => $currentUserId,
                            'record_id'      => $targetId,
                            'record_name'    => $tagName,
                            'old_value'      => $existingTagRow,
                            'new_value'      => $newData
                        ]);
                    }
                    
                    $_SESSION['flash_msg'] = '标签保存成功！';
                    $_SESSION['flash_type'] = 'success';
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

} catch (Exception $e) {
    $message = "System Error: " . $e->getMessage(); $msgType = "danger";
}


if ($isEmbeddedTagForm): ?>
    <div class="tag-container">
        <div class="card tag-card">
            <div class="card-header bg-white py-3">
                <h4 class="m-0 text-primary">
                    <i class="fa-solid fa-tag me-2"></i> <?php echo $isEditMode ? "编辑标签" : "新增标签"; ?>
                </h4>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
                        <?php echo $message; ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <form method="POST" action="<?php echo htmlspecialchars($formActionUrl); ?>" autocomplete="off">
                    <?php if ($isEditMode): ?>
                        <input type="hidden" name="tag_id" value="<?php echo (int) $tagId; ?>">
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
<?php else: ?>
<?php $pageMetaKey = 'tag_form'; ?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/global.css">
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="tag-container">
    <div class="card tag-card">
        <div class="card-header bg-white py-3">
            <h4 class="m-0 text-primary">
                <i class="fa-solid fa-tag me-2"></i> <?php echo $isEditMode ? "编辑标签" : "新增标签"; ?>
            </h4>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
                    <?php echo $message; ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <form method="POST" autocomplete="off">
                <?php if ($isEditMode): ?>
                    <input type="hidden" name="tag_id" value="<?php echo (int) $tagId; ?>">
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
<?php endif; ?>
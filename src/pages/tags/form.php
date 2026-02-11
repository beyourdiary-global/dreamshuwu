<?php
// Path: src/pages/tags/form.php
require_once __DIR__ . '/../../../init.php';
defined('URL_HOME') || require_once BASE_PATH . '/config/urls.php';
require_once BASE_PATH . 'functions.php';


// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (headers_sent()) {
        echo "<script>window.location.href='" . URL_LOGIN . "';</script>";
    } else {
        header("Location: " . URL_LOGIN);
    }
    exit();
}

$dbTable = defined('NOVEL_TAGS') ? NOVEL_TAGS : 'novel_tag';
$auditPage = 'Tag Management';
$insertQuery = "INSERT INTO $dbTable (name, created_by, updated_by) VALUES (?, ?, ?)";
$updateQuery = "UPDATE $dbTable SET name = ?, updated_by = ? WHERE id = ?";

// 2. Context Detection
$isEmbeddedTagForm = isset($EMBED_TAG_FORM_PAGE) && $EMBED_TAG_FORM_PAGE === true;

if ($isEmbeddedTagForm) {
    $listPageUrl = URL_USER_DASHBOARD . '?view=tags';
    $formActionUrl = URL_USER_DASHBOARD . '?view=tag_form'; 
} else {
    $listPageUrl = defined('URL_NOVEL_TAGS') ? URL_NOVEL_TAGS : 'index.php';
    $formActionUrl = ''; 
}

$tagId = $_GET['id'] ?? null;
$tagId = $tagId !== null ? (int) $tagId : null;
$isEditMode = !empty($tagId);

// Define View Query for Audit Log
$viewQuery = $isEditMode
    ? "SELECT id, name, created_at, updated_at, created_by, updated_by FROM $dbTable WHERE id = ?"
    : "SELECT id, name FROM $dbTable";

$tagName = "";
$message = ""; 
$msgType = "";
$existingTagRow = null;

try {
    // 3. Load Existing Data (Initial Page Load)
    if ($isEditMode) {
        $stmt = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $dbTable . " WHERE id = ?");
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
            // Check for duplicates
            $sql = $isEditMode ? "SELECT id FROM $dbTable WHERE name = ? AND id != ?" : "SELECT id FROM $dbTable WHERE name = ?";
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
                    $fetchOld = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $dbTable . " WHERE id = ?");
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
                    // [CRITICAL FIX] Capture ID and Close Statement immediately so we can run the next query
                    $targetId = $isEditMode ? $tagId : $conn->insert_id;
                    $stmt->close(); 

                    // Now it is safe to fetch New Data for Audit Log
                    $newData = null;
                    $reload = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $dbTable . " WHERE id = ?");
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
                            'name' => $tagName,
                            'created_at' => $isEditMode ? ($existingTagRow['created_at'] ?? null) : $now,
                            'updated_at' => $now,
                            'created_by' => $isEditMode ? ($existingTagRow['created_by'] ?? $currentUserId) : $currentUserId,
                            'updated_by' => $currentUserId,
                        ];
                    }

                    if (function_exists('logAudit')) {
                        logAudit([
                            'page'           => $auditPage,
                            'action'         => $action,
                            'action_message' => "$logMsg: $tagName",
                            'query'          => $isEditMode ? $updateQuery : $insertQuery,
                            'query_table'    => $dbTable,
                            'user_id'        => $currentUserId,
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
    // 5. Handle Page View (GET) Audit Log
    else {
        if (!defined('TAG_FORM_VIEW_LOGGED')) {
            define('TAG_FORM_VIEW_LOGGED', true);
            if (function_exists('logAudit')) {
                logAudit([
                    'page'           => $auditPage,
                    'action'         => 'V',
                    'action_message' => $isEditMode ? "Viewing Edit Tag Form: $tagName" : "Viewing Add Tag Form",
                    'query'          => $viewQuery,
                    'query_table'    => $dbTable,
                    'user_id'        => $_SESSION['user_id']
                ]);
            }
        }
    }

} catch (Exception $e) {
    $message = "System Error: " . $e->getMessage(); $msgType = "danger";
}

$pageTitle = ($isEditMode ? "编辑标签" : "新增标签") . " - " . WEBSITE_NAME;

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
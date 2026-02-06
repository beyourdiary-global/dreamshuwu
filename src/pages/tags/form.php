<?php
// Path: src/pages/tags/form.php
require_once __DIR__ . '/../../../init.php';
defined('URL_HOME') || require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN); exit();
}

$dbTable = defined('NOVEL_TAGS') ? NOVEL_TAGS : 'novel_tag';
// [FIX] Use defined constant for robustness
$listPageUrl = defined('URL_NOVEL_TAGS') ? URL_NOVEL_TAGS : 'index.php';

// When included from the user dashboard, we only render the inner form card
$isEmbeddedTagForm = isset($EMBED_TAG_FORM_PAGE) && $EMBED_TAG_FORM_PAGE === true;

$tagId = $_GET['id'] ?? null;
$isEditMode = !empty($tagId);
$tagName = "";
$message = ""; $msgType = "";
// Keep full existing row for audit log old_value
$existingTagRow = null;

try {
    if ($isEditMode) {
        // Load full row so we can log old_value in audit log
        $stmt = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $dbTable . " WHERE id = ?");
        $stmt->bind_param("i", $tagId);
        $stmt->execute();
        $stmt->bind_result($rowId, $rowName, $rowCreatedAt, $rowUpdatedAt, $rowCreatedBy, $rowUpdatedBy);
        if ($stmt->fetch()) {
            $tagName = $rowName;
            $existingTagRow = [
                'id'         => $rowId,
                'name'       => $rowName,
                'created_at' => $rowCreatedAt,
                'updated_at' => $rowUpdatedAt,
                'created_by' => $rowCreatedBy,
                'updated_by' => $rowUpdatedBy,
            ];
        } else {
            $stmt->close();
            header("Location: " . $listPageUrl); exit();
        }
        $stmt->close();
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $tagName = trim($_POST['tag_name'] ?? '');
        $currentUserId = $_SESSION['user_id'];

        if (empty($tagName)) {
            $message = "标签名称不能为空"; $msgType = "danger";
        } else {
            $sql = $isEditMode ? "SELECT id FROM $dbTable WHERE name = ? AND id != ?" : "SELECT id FROM $dbTable WHERE name = ?";
            $chk = $conn->prepare($sql);
            if ($isEditMode) {
                $chk->bind_param("si", $tagName, $tagId);
            } else {
                $chk->bind_param("s", $tagName);
            }
            $chk->execute();
            $chk->store_result();
            
            if ($chk->num_rows > 0) {
                $message = "标签 '<strong>" . htmlspecialchars($tagName) . "</strong>' 已存在"; $msgType = "danger";
            } else {
                if ($isEditMode) {
                    // Update tag name & track who last updated it
                    $stmt = $conn->prepare("UPDATE $dbTable SET name = ?, updated_by = ? WHERE id = ?");
                    $stmt->bind_param("sii", $tagName, $currentUserId, $tagId);
                    $action = 'E'; $logMsg = "更新标签";
                } else {
                    // Insert new tag and track creator / last updater
                    $stmt = $conn->prepare("INSERT INTO $dbTable (name, created_by, updated_by) VALUES (?, ?, ?)");
                    $stmt->bind_param("sii", $tagName, $currentUserId, $currentUserId);
                    $action = 'A'; $logMsg = "新增标签";
                }

                if ($stmt->execute()) {
                    // Build old/new values for audit log
                    $oldData = null;
                    $newData = null;

                    if ($isEditMode) {
                        // Old row from earlier select
                        $oldData = $existingTagRow;

                        // Reload updated row as new_value
                        $reload = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $dbTable . " WHERE id = ?");
                        $reload->bind_param("i", $tagId);
                        $reload->execute();
                        $reload->bind_result($nId, $nName, $nCreatedAt, $nUpdatedAt, $nCreatedBy, $nUpdatedBy);
                        if ($reload->fetch()) {
                            $newData = [
                                'id'         => $nId,
                                'name'       => $nName,
                                'created_at' => $nCreatedAt,
                                'updated_at' => $nUpdatedAt,
                                'created_by' => $nCreatedBy,
                                'updated_by' => $nUpdatedBy,
                            ];
                        }
                        $reload->close();
                    } else {
                        // For inserts, load the newly created row using insert_id
                        $newId = $conn->insert_id;
                        $reload = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $dbTable . " WHERE id = ?");
                        $reload->bind_param("i", $newId);
                        $reload->execute();
                        $reload->bind_result($nId, $nName, $nCreatedAt, $nUpdatedAt, $nCreatedBy, $nUpdatedBy);
                        if ($reload->fetch()) {
                            $newData = [
                                'id'         => $nId,
                                'name'       => $nName,
                                'created_at' => $nCreatedAt,
                                'updated_at' => $nUpdatedAt,
                                'created_by' => $nCreatedBy,
                                'updated_by' => $nUpdatedBy,
                            ];
                        }
                        $reload->close();
                    }

                    if (function_exists('logAudit')) {
                        logAudit([
                            'page'          => 'Tag',
                            'action'        => $action,
                            'action_message'=> "$logMsg: $tagName",
                            'query_table'   => $dbTable,
                            'user_id'       => $currentUserId,
                            'old_value'     => $oldData,
                            'new_value'     => $newData,
                        ]);
                    }
                    header("Location: $listPageUrl?msg=saved"); exit();
                } else {
                    throw new Exception($stmt->error);
                }
                $stmt->close();
            }
            $chk->close();
        }
    }
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage(); $msgType = "danger";
}

$pageTitle = ($isEditMode ? "编辑标签" : "新增标签") . " - " . WEBSITE_NAME;

// If embedded in dashboard, only render the inner form card (no full HTML shell)
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
                <form method="POST" autocomplete="off">
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
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/tag.css">
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
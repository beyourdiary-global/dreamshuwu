<?php
// Path: src/pages/tags/form.php
require_once __DIR__ . '/../../../init.php';
defined('URL_HOME') || require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Use JS redirect if headers are potentially sent
    echo "<script>window.location.href='" . URL_LOGIN . "';</script>";
    exit();
}

$dbTable = defined('NOVEL_TAGS') ? NOVEL_TAGS : 'novel_tag';

// 2. Context Detection & URL Setup
$isEmbeddedTagForm = isset($EMBED_TAG_FORM_PAGE) && $EMBED_TAG_FORM_PAGE === true;

if ($isEmbeddedTagForm) {
    // If inside Dashboard, redirect back to Dashboard Tag List
    $listPageUrl = URL_USER_DASHBOARD . '?view=tags';
    // Ensure form posts back to Dashboard
    $formActionUrl = URL_USER_DASHBOARD . '?view=tag_form'; 
} else {
    // If standalone, use the standard list URL
    $listPageUrl = defined('URL_NOVEL_TAGS') ? URL_NOVEL_TAGS : 'index.php';
    $formActionUrl = ''; // Post to self
}

$tagId = $_GET['id'] ?? null;
$isEditMode = !empty($tagId);

// Append ID to action URL if editing
if ($isEditMode && $isEmbeddedTagForm) {
    $formActionUrl .= '&id=' . intval($tagId);
}

$tagName = "";
$message = ""; 
$msgType = "";
$existingTagRow = null;

try {
    // 3. Load Existing Data (For Edit Mode & Audit Log)
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
            // Redirect if ID not found
            if ($isEmbeddedTagForm) {
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
        $currentUserId = $_SESSION['user_id'];

        if (empty($tagName)) {
            $message = "标签名称不能为空"; $msgType = "danger";
        } else {
            // Check for duplicates
            $sql = $isEditMode ? "SELECT id FROM $dbTable WHERE name = ? AND id != ?" : "SELECT id FROM $dbTable WHERE name = ?";
            $chk = $conn->prepare($sql);
            if ($isEditMode) $chk->bind_param("si", $tagName, $tagId); else $chk->bind_param("s", $tagName);
            $chk->execute();
            $chk->store_result();
            
            if ($chk->num_rows > 0) {
                $message = "标签 '<strong>" . htmlspecialchars($tagName) . "</strong>' 已存在"; $msgType = "danger";
            } else {
                // Insert or Update
                if ($isEditMode) {
                    $stmt = $conn->prepare("UPDATE $dbTable SET name = ?, updated_by = ? WHERE id = ?");
                    $stmt->bind_param("sii", $tagName, $currentUserId, $tagId);
                    $action = 'E'; $logMsg = "Updated Tag";
                } else {
                    $stmt = $conn->prepare("INSERT INTO $dbTable (name, created_by, updated_by) VALUES (?, ?, ?)");
                    $stmt->bind_param("sii", $tagName, $currentUserId, $currentUserId);
                    $action = 'A'; $logMsg = "Added New Tag";
                }

                if ($stmt->execute()) {
                    // Fetch new data for Audit Log
                    $targetId = $isEditMode ? $tagId : $conn->insert_id;
                    $newData = null;
                    
                    $reload = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $dbTable . " WHERE id = ?");
                    $reload->bind_param("i", $targetId);
                    $reload->execute();
                    $reload->bind_result($nId, $nName, $nCreatedAt, $nUpdatedAt, $nCreatedBy, $nUpdatedBy);
                    if ($reload->fetch()) {
                        $newData = ['id' => $nId, 'name' => $nName, 'created_at' => $nCreatedAt, 'updated_at' => $nUpdatedAt, 'created_by' => $nCreatedBy, 'updated_by' => $nUpdatedBy];
                    }
                    $reload->close();

                    // Log the Save Action
                    if (function_exists('logAudit')) {
                        logAudit([
                            'page' => 'Tag Management', 'action' => $action,
                            'action_message' => "$logMsg: $tagName",
                            'query' => $isEditMode ? "UPDATE..." : "INSERT...",
                            'query_table' => $dbTable,
                            'user_id' => $currentUserId,
                            'old_value' => $existingTagRow,
                            'new_value' => $newData
                        ]);
                    }
                    
                    // [CRITICAL FIX] Use JS Redirect to avoid "Headers already sent" error
                    $redirectUrl = $listPageUrl . (strpos($listPageUrl, '?') !== false ? '&' : '?') . "msg=saved";
                    echo "<script>window.location.href = '" . $redirectUrl . "';</script>";
                    exit();
                } else {
                    throw new Exception($stmt->error);
                }
                $stmt->close();
            }
            $chk->close();
        }
    } 
    // 5. Handle Page View (GET) Audit Log
    else {
        // [FIX] Log that the user VIEWED the page
        // We use a constant to prevent duplicate logs if included multiple times
        if (!defined('TAG_FORM_VIEW_LOGGED')) {
            define('TAG_FORM_VIEW_LOGGED', true);
            if (function_exists('logAudit')) {
                logAudit([
                    'page' => 'Tag Management', 
                    'action' => 'V',
                    'action_message' => $isEditMode ? "Viewing Edit Tag Form: $tagName" : "Viewing Add Tag Form",
                    'query_table' => $dbTable,
                    'user_id' => $_SESSION['user_id']
                ]);
            }
        }
    }

} catch (Exception $e) {
    $message = "System Error: " . $e->getMessage(); $msgType = "danger";
}

$pageTitle = ($isEditMode ? "编辑标签" : "新增标签") . " - " . WEBSITE_NAME;

// Render inner card if embedded (Dashboard mode)
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
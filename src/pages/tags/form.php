<?php
require_once __DIR__ . '/../../../init.php';
defined('URL_HOME') || require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$dbTable = defined('NOVEL_TAGS') ? NOVEL_TAGS : 'novel_tag';
$listPageUrl = SITEURL . '/src/pages/tags/index.php'; 

$tagId = $_GET['id'] ?? null;
$isEditMode = !empty($tagId);
$tagName = "";
$message = "";
$msgType = "";

if ($isEditMode) {
    $stmt = $conn->prepare("SELECT name FROM " . $dbTable . " WHERE id = ?");
    $stmt->bind_param("i", $tagId);
    $stmt->execute();
    $stmt->bind_result($fetchedName);
    if ($stmt->fetch()) {
        $tagName = $fetchedName;
    } else {
        $stmt->close();
        header("Location: " . $listPageUrl);
        exit();
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tagName = trim($_POST['tag_name'] ?? '');
    $currentUserId = $_SESSION['user_id'];

    if (empty($tagName)) {
        $message = "标签名称不能为空";
        $msgType = "danger";
    } else {
        // Check for duplicates
        if ($isEditMode) {
            $checkStmt = $conn->prepare("SELECT id FROM " . $dbTable . " WHERE name = ? AND id != ?");
            $checkStmt->bind_param("si", $tagName, $tagId);
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM " . $dbTable . " WHERE name = ?");
            $checkStmt->bind_param("s", $tagName);
        }
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $message = "标签 '<strong>" . htmlspecialchars($tagName) . "</strong>' 已存在";
            $msgType = "danger";
            $checkStmt->close();
        } else {
            $checkStmt->close();
            
            if ($isEditMode) {
                $stmt = $conn->prepare("UPDATE " . $dbTable . " SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $tagName, $tagId);
                $action = 'E'; $logMsg = "更新标签";
            } else {
                $stmt = $conn->prepare("INSERT INTO " . $dbTable . " (name) VALUES (?)");
                $stmt->bind_param("s", $tagName);
                $action = 'A'; $logMsg = "新增标签";
            }

            if ($stmt->execute()) {
                if (function_exists('logAudit')) {
                    logAudit([
                        'page' => 'Tag Management', 'action' => $action,
                        'action_message' => $logMsg . ": " . $tagName,
                        'query' => $isEditMode ? "UPDATE..." : "INSERT...", 
                        'query_table' => $dbTable,
                        'new_value' => ['name' => $tagName], 'user_id' => $currentUserId
                    ]);
                }
                header("Location: " . $listPageUrl . "?msg=saved");
                exit();
            } else {
                $message = "系统错误: " . $stmt->error;
                $msgType = "danger";
            }
            $stmt->close();
        }
    }
}

$pageTitle = ($isEditMode ? "编辑标签" : "新增标签") . " - " . WEBSITE_NAME;
?>
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
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
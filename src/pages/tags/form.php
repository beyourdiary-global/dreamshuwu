<?php
require_once __DIR__ . '/../../../init.php';
// Fallbacks if init doesn't load these
defined('URL_HOME') || require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

// 2. Configuration
$dbTable = defined('NOVEL_TAGS') ? NOVEL_TAGS : 'novel_tag';
$listPageUrl = SITEURL . '/src/pages/tags/index.php'; 

$tagId = $_GET['id'] ?? null;
$isEditMode = !empty($tagId);
$tagName = "";
$message = "";
$msgType = "";

// 3. Fetch Data (If Edit Mode)
if ($isEditMode) {
    $stmt = $conn->prepare("SELECT name FROM " . $dbTable . " WHERE id = ?");
    $stmt->bind_param("i", $tagId);
    $stmt->execute();
    
    // Universal Fetch
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

// 4. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tagName = trim($_POST['tag_name'] ?? '');
    $currentUserId = $_SESSION['user_id'];

    if (empty($tagName)) {
        $message = "标签名称不能为空";
        $msgType = "danger";
    } else {
        if ($isEditMode) {
            // Edit: Check duplicate (excluding self)
            $checkSql = "SELECT id FROM " . $dbTable . " WHERE name = ? AND id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("si", $tagName, $tagId);
            
            // [FIX] Removed 'updated_by' to match your table
            $sql = "UPDATE " . $dbTable . " SET name = ? WHERE id = ?";
            $types = "si";
            $params = [$tagName, $tagId];
            $action = 'E'; $logMsg = "更新标签";
        } else {
            // Add: Check duplicate (global)
            $checkSql = "SELECT id FROM " . $dbTable . " WHERE name = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("s", $tagName);

            // [FIX] Removed 'created_by', 'updated_by' to match your table
            $sql = "INSERT INTO " . $dbTable . " (name) VALUES (?)";
            $types = "s";
            $params = [$tagName];
            $action = 'A'; $logMsg = "新增标签";
        }

        // Execute Check
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $message = "标签 '<strong>" . htmlspecialchars($tagName) . "</strong>' 已存在";
            $msgType = "danger";
            $checkStmt->close();
        } else {
            $checkStmt->close();
            
            // Execute Save
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                // Audit Log
                if (function_exists('logAudit')) {
                    logAudit([
                        'page' => 'Tag Management', 'action' => $action,
                        'action_message' => $logMsg . ": " . $tagName,
                        'query' => $sql, 'query_table' => $dbTable,
                        'new_value' => ['name' => $tagName], 'user_id' => $currentUserId
                    ]);
                }
                header("Location: " . $listPageUrl . "?msg=saved");
                exit();
            } else {
                $message = "系统错误: 无法保存数据";
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
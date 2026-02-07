<?php
// Path: src/pages/tags/form.php
require_once __DIR__ . '/../../../init.php';
defined('URL_HOME') || require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

// 1. Auth Check (Safe Redirect)
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
$viewQuery = '';

// 2. Context Detection: Are we inside the Dashboard?
$isEmbeddedTagForm = isset($EMBED_TAG_FORM_PAGE) && $EMBED_TAG_FORM_PAGE === true;

if ($isEmbeddedTagForm) {
    // If inside Dashboard, redirect back to Dashboard Tag List
    $listPageUrl = URL_USER_DASHBOARD . '?view=tags';
    // Ensure form posts back to Dashboard Tag Form view
    $formActionUrl = URL_USER_DASHBOARD . '?view=tag_form'; 
} else {
    // If standalone, use the standard list URL
    $listPageUrl = defined('URL_NOVEL_TAGS') ? URL_NOVEL_TAGS : 'index.php';
    $formActionUrl = ''; // Post to self
}

$tagId = $_GET['id'] ?? null;
$tagId = $tagId !== null ? (int) $tagId : null;
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
            // Safe Redirect if ID not found
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

        if (!$conn || !($conn instanceof mysqli)) {
            throw new Exception('Database connection is not available.');
        }

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
            } else {
                // Insert or Update
                if ($isEditMode) {
                    $stmt = $conn->prepare($updateQuery);
                    if (!$stmt) {
                        throw new Exception($conn->error ?: 'Failed to prepare update statement.');
                    }
                    $stmt->bind_param("sii", $tagName, $currentUserId, $tagId);
                    $action = 'E'; $logMsg = "Updated Tag";
                } else {
                    $stmt = $conn->prepare($insertQuery);
                    if (!$stmt) {
                        throw new Exception($conn->error ?: 'Failed to prepare insert statement.');
                    }
                    $stmt->bind_param("sii", $tagName, $currentUserId, $currentUserId);
                    $action = 'A'; $logMsg = "Added New Tag";
                }

                if ($stmt->execute()) {
                    // Fetch new data for Audit Log
                    $targetId = $isEditMode ? $tagId : $conn->insert_id;
                    $newData = null;
                    
                    $reload = $conn->prepare("SELECT id, name, created_at, updated_at, created_by, updated_by FROM " . $dbTable . " WHERE id = ?");
                    if ($reload) {
                        $reload->bind_param("i", $targetId);
                        $reload->execute();
                        $reload->bind_result($nId, $nName, $nCreatedAt, $nUpdatedAt, $nCreatedBy, $nUpdatedBy);
                        if ($reload->fetch()) {
                            $newData = ['id' => $nId, 'name' => $nName, 'created_at' => $nCreatedAt, 'updated_at' => $nUpdatedAt, 'created_by' => $nCreatedBy, 'updated_by' => $nUpdatedBy];
                        }
                        $reload->close();
                    }

                    // Log the Save Action
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
                    
                    // [CRITICAL FIX] Use JS Redirect to fix "Headers already sent" / White Screen
                    $redirectUrl = $listPageUrl . (strpos($listPageUrl, '?') !== false ? '&' : '?') . "msg=saved";
                    if (!headers_sent()) {
                        header("Location: " . $redirectUrl);
                    } else {
                        echo "<script>window.location.href = '" . $redirectUrl . "';</script>";
                    }
                    exit(); // Stop execution immediately to ensure database save is final
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
        // Log that the user VIEWED the page
        // Use a constant to ensure we don't log twice if file is included multiple times
        if (!defined('TAG_FORM_VIEW_LOGGED')) {
            define('TAG_FORM_VIEW_LOGGED', true);
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
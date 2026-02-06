<?php
// Path: src/pages/category/form.php
require_once __DIR__ . '/../../../init.php';
defined('URL_HOME') || require_once BASE_PATH . 'config/urls.php';
require_once BASE_PATH . 'functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

// Use Constants Directly
$catTable  = NOVEL_CATEGORY;
$linkTable = CATEGORY_TAG;
$tagTable  = NOVEL_TAGS;

// [UPDATED] Use URL_CATEGORIES constant
$listPageUrl = URL_CATEGORIES; 

$id = $_GET['id'] ?? null;
$isEdit = !empty($id);
$name = "";
$selectedTags = [];
$message = ""; $msgType = "";

// 1. Fetch Data
if ($isEdit) {
    $stmt = $conn->prepare("SELECT name FROM $catTable WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($name);
    if (!$stmt->fetch()) { header("Location: $listPageUrl"); exit(); }
    $stmt->close();

    $tStmt = $conn->prepare("SELECT tag_id FROM $linkTable WHERE category_id = ?");
    $tStmt->bind_param("i", $id);
    $tStmt->execute();
    $tStmt->bind_result($tid);
    while ($tStmt->fetch()) { $selectedTags[] = $tid; }
    $tStmt->close();
}

// 2. Fetch Tags
$allTags = [];
$atRes = $conn->query("SELECT id, name FROM $tagTable ORDER BY name ASC");
if ($atRes) {
    while ($row = $atRes->fetch_assoc()) { $allTags[] = $row; }
}

// 3. Handle Submit
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $tagIds = $_POST['tags'] ?? []; 
    $uid = $_SESSION['user_id'];

    if (empty($name)) {
        $message = "分类名称不能为空"; $msgType = "danger";
    } else {
        $checkSql = $isEdit ? "SELECT id FROM $catTable WHERE name = ? AND id != ?" : "SELECT id FROM $catTable WHERE name = ?";
        $chk = $conn->prepare($checkSql);
        if ($isEdit) $chk->bind_param("si", $name, $id); else $chk->bind_param("s", $name);
        $chk->execute();
        $chk->store_result();
        
        if ($chk->num_rows > 0) {
            $message = "分类名称 '<strong>$name</strong>' 已存在"; $msgType = "danger";
        } else {
            $conn->begin_transaction();
            try {
                if ($isEdit) {
                    $upd = $conn->prepare("UPDATE $catTable SET name = ?, updated_by = ? WHERE id = ?");
                    $upd->bind_param("sii", $name, $uid, $id);
                    $upd->execute();
                    $upd->close();
                    
                    $del = $conn->prepare("DELETE FROM $linkTable WHERE category_id = ?");
                    $del->bind_param("i", $id);
                    $del->execute();
                    $del->close();
                    
                    $targetId = $id;
                    $action = 'E';
                } else {
                    $ins = $conn->prepare("INSERT INTO $catTable (name, created_by, updated_by) VALUES (?, ?, ?)");
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

                if (function_exists('logAudit')) {
                    logAudit(['page'=>'Category','action'=>$action,'action_message'=>"Saved Category: $name",'query_table'=>$catTable,'user_id'=>$uid]);
                }
                header("Location: $listPageUrl");
                exit();

            } catch (Exception $e) {
                $conn->rollback();
                $message = "保存失败: " . $e->getMessage(); $msgType = "danger";
            }
        }
        $chk->close();
    }
}
$pageTitle = ($isEdit ? "编辑分类" : "新增分类") . " - " . WEBSITE_NAME;
?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
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
            
            <form method="POST" autocomplete="off">
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
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
</body>
</html>
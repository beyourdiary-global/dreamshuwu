<?php
// Path: src/pages/settings/meta.php
require_once __DIR__ . '/common.php';

$message = ""; 
$msgType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mTitle  = $_POST['meta_title'] ?? '';
    $mDesc   = $_POST['meta_description'] ?? '';
    $ogTitle = $_POST['og_title'] ?? '';
    $ogDesc  = $_POST['og_description'] ?? '';
    $ogUrl   = $_POST['og_url'] ?? '';

    $sql = "INSERT INTO `meta-settings` (page_type, page_id, meta_title, meta_description, og_title, og_description, og_url) 
            VALUES ('global', 0, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            meta_title = VALUES(meta_title), 
            meta_description = VALUES(meta_description), 
            og_title = VALUES(og_title),
            og_description = VALUES(og_description),
            og_url = VALUES(og_url)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $mTitle, $mDesc, $ogTitle, $ogDesc, $ogUrl);
    
    // Check if the execution was successful
    if ($stmt->execute()) {
        $message = "设置保存成功！"; // Assignment here
        $msgType = "success";       // Assignment here
    } else {
        $message = "保存失败: " . $conn->error;
        $msgType = "danger";
    }
    
    $stmt->close();
}

// Fetch Current Settings
$current = [
    'meta_title' => '', 
    'meta_description' => '', 
    'og_title' => '', 
    'og_description' => '', 
    'og_url' => ''
];
$res = $conn->query("SELECT * FROM `meta-settings` WHERE page_type = 'global' AND page_id = 0 LIMIT 1");
if ($row = $res->fetch_assoc()) {
    $current = $row;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <?php require_once __DIR__ . '/include/header.php'; ?>
</head>
<body>
<?php require_once __DIR__ . '/common/menu/header.php'; ?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-white py-3">
            <h4 class="m-0 text-primary"><i class="fa-solid fa-globe"></i> Meta Settings (Whole Website)</h4>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msgType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="POST">

            <div class="mb-3">
            <label class="form-label fw-bold">Meta Title</label>
            <input type="text" name="meta_title" class="form-control" value="<?php echo htmlspecialchars($current['meta_title']); ?>">
            </div>

            <div class="mb-3">
            <label class="form-label fw-bold">Meta Description</label>
            <textarea name="meta_description" class="form-control" rows="2"><?php echo htmlspecialchars($current['meta_description']); ?></textarea>
            </div>

            <div class="mb-3">
            <label class="form-label fw-bold">OG Title</label>
            <input type="text" name="og_title" class="form-control" value="<?php echo htmlspecialchars($current['og_title']); ?>">
            </div>

            <div class="mb-3">
            <label class="form-label fw-bold">OG Description</label>
            <textarea name="og_description" class="form-control" rows="2"><?php echo htmlspecialchars($current['og_description']); ?></textarea>
            </div>

            <div class="mb-3">
            <label class="form-label fw-bold">OG Url</label>
            <input type="text" name="og_url" class="form-control" value="<?php echo htmlspecialchars($current['og_url']); ?>">
            </div>

            <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
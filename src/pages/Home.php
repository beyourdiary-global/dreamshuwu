<?php
require_once __DIR__ . '/../../init.php';
require_once BASE_PATH . '/config/urls.php';

// --- [BUG FIX] Session Integrity Check ---
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    
    $sessionUserId = $_SESSION['user_id'] ?? 0;
    $checkSql = "SELECT id FROM " . USR_LOGIN . " WHERE id = ? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    
    if ($checkStmt) {
        $checkStmt->bind_param("i", $sessionUserId);
        $checkStmt->execute();

        // [COMPATIBILITY FIX]
        // Use store_result() instead of get_result()
        $checkStmt->store_result();

        if ($checkStmt->num_rows === 0) {
            session_unset();     
            session_destroy();   
            header("Location: " . URL_LOGIN);
            exit();
        }
        $checkStmt->close();
    }
}
// -----------------------------------------

$pageTitle = "首页 - " . WEBSITE_NAME; 
?>

<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<?php require_once BASE_PATH . 'include/header.php'; ?>
<body>

<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<div class="container main-content" style="max-width: 1200px; margin: 20px auto; padding: 0 15px;">
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
        <div class="alert alert-success mt-3">
            欢迎回来, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></strong>!
        </div>
    <?php endif; ?>

    <h3 class="mt-4">首页内容区域</h3>
    <p>这里是公开内容，任何人都可以看到 (Banner, Rank, Categories)。</p>
</div>

</body>
</html>
<?php
require_once dirname(__DIR__, 2) . '/common.php';

// --- Session Integrity Check ---
if (hasSession('logged_in') && session('logged_in') === true) {
    
    $sessionUserId = sessionInt('user_id');
    $checkSql = "SELECT id FROM " . USR_LOGIN . " WHERE id = ? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    
    if ($checkStmt) {
        $checkStmt->bind_param("i", $sessionUserId);
        $checkStmt->execute();
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

$pageMetaKey = '/Home.php';
?>

<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>

<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<div class="container main-content" style="max-width: 1200px; margin: 20px auto; padding: 0 15px;">
    
    <?php 
    if (hasSession('flash_msg')) {
        $flashType = session('flash_type') ?: 'info';
        $flashMsg = session('flash_msg');
        // Clear the message so it doesn't show up again on refresh
        unsetSession('flash_msg');
        unsetSession('flash_type');
    ?>
        <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?php echo htmlspecialchars($flashMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php } ?>

    <?php if (hasSession('logged_in') && session('logged_in') === true): ?>
        <div class="alert alert-success mt-3 shadow-sm">
            欢迎回来, <strong><?php echo htmlspecialchars(session('user_name') ?: 'User'); ?></strong>!
        </div>
    <?php endif; ?>

    <h3 class="mt-4">首页内容区域</h3>
    <p>这里是公开内容，任何人都可以看到 (Banner, Rank, Categories)。</p>
</div>

<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>

</body>
</html>
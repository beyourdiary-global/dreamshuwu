<?php
require_once dirname(__DIR__, 3) . '/common.php';

$message = "";
$msgType = ""; // 'success', 'danger', or 'warning'
$isAjax = post('ajax') === '1';

// [FIX] Use getServer helper for server variables
$local_whitelist = defined('LOCAL_WHITELIST') ? explode(',', LOCAL_WHITELIST) : ['127.0.0.1', '::1', 'localhost'];
$isLocal = in_array(getServer('SERVER_NAME'), $local_whitelist);

if (isPostRequest()) {
    $email = postSpaceFilter('email');

    // 1. Validation
    if ($email === '') {
        $message = "请输入邮箱";
        $msgType = "danger";
    } elseif (!isValidEmail($email)) {
        $message = "请输入有效邮箱";
        $msgType = "danger";
    } else {
        // 2. Check if email exists in User table
        $stmt = $conn->prepare("SELECT id FROM " . USR_LOGIN . " WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $message = "该邮箱尚未注册";
            $msgType = "danger";
        } else {
            // 3. Generate Token & Expiry (30 mins)
            $token = bin2hex(random_bytes(32)); 
            $expires_at = date("Y-m-d H:i:s", time() + 1800); 

            // 4. Store in PWD_RESET table
            $delStmt = $conn->prepare("DELETE FROM " . PWD_RESET . " WHERE email = ?");
            $delStmt->bind_param("s", $email);
            $delStmt->execute();

            $insertStmt = $conn->prepare("INSERT INTO " . PWD_RESET . " (email, token, expires_at) VALUES (?, ?, ?)");
            $insertStmt->bind_param("sss", $email, $token, $expires_at);
            
            if ($insertStmt->execute()) {
                // 5. Send Email Logic
                $resetLink = URL_RESET_PWD . "?token=" . $token;
                
                $mailSent = sendPasswordResetEmail($email, $resetLink);

                // [FIX] Removed auto-redirects. Only show success message.
                if ($mailSent) {
                    $message = "重置链接已发送，请检查您的邮箱 (含垃圾箱)";
                    $msgType = "success";
                } else {
                    if ($isLocal) {
                        // Local dev fallback so you can click the link directly on your screen
                        $message = "【本地开发模式】重置链接已生成: <a href='" . htmlspecialchars($resetLink) . "'>点击这里重置</a>";
                        $msgType = "success";
                    } else {
                        $message = "系统错误，邮件发送失败，请稍后再试";
                        $msgType = "danger";
                    }
                }

                if ($isAjax) {
                    header('Content-Type: application/json');
                    // Return success WITHOUT a 'redirect' key
                    echo safeJsonEncode(['success' => ($msgType === 'success'), 'message' => $message]);
                    exit();
                }
            } else {
                $message = "系统错误，请稍后再试";
                $msgType = "danger";
            }
        }
        $stmt->close();
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo safeJsonEncode(['success' => false, 'message' => $message]);
        exit();
    }
}
?>

<?php $pageMetaKey = '/forgot_password.php'; ?>
<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/auth.css?v=<?php echo time(); ?>">
</head>
<body class="auth-page">
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<main class="auth-main">
    <div class="auth-layout">
        <div class="forgot-card">
            <h3>忘记密码？</h3>
            <p class="subtext">输入您的注册邮箱，我们将向您发送重置链接</p>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($msgType); ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div id="forgotAlert" class="alert alert-success" style="display: none; justify-content: space-between;">
                <span id="forgotAlertText"></span>
                <button type="button" style="background: none; border: none; font-size: 20px; cursor: pointer; padding: 0; color: inherit; line-height: 1;" onclick="this.parentElement.style.display='none';">&times;</button>
            </div>
            
            <form id="forgotForm" method="POST">
                <div class="auth-field mb-4">
                    <label class="form-label">邮箱地址</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">发送重置链接</button>

                <div class="footer-links">
                    <a href="<?php echo URL_LOGIN; ?>" class="text-decoration-none text-muted">返回登录</a>
                </div>
            </form>
        </div>
    </div>
</main>
<script src="<?php echo URL_ASSETS; ?>/js/auth.js"></script>
</body>
</html>
<?php
require_once __DIR__ . '/../../../init.php'; 
require_once BASE_PATH . '/config/urls.php'; 
require_once BASE_PATH . 'functions.php';


$pageTitle = "忘记密码 - " . WEBSITE_NAME;

$message = "";
$msgType = ""; // 'success', 'danger', or 'warning'

// [FIX] Convert Constant String to Array safely
$local_whitelist = defined('LOCAL_WHITELIST') ? explode(',', LOCAL_WHITELIST) : ['127.0.0.1', '::1', 'localhost'];
$isLocal = in_array($_SERVER['SERVER_NAME'], $local_whitelist);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? "");

    // 1. Validation
    if (empty($email)) {
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
            // Use time() for safer timestamp calculation
            $expires_at = date("Y-m-d H:i:s", time() + 1800); 

            // 4. Store in PWD_RESET table
            // Delete old tokens first
            $delStmt = $conn->prepare("DELETE FROM " . PWD_RESET . " WHERE email = ?");
            $delStmt->bind_param("s", $email);
            $delStmt->execute();

            // Insert new token
            $insertStmt = $conn->prepare("INSERT INTO " . PWD_RESET . " (email, token, expires_at) VALUES (?, ?, ?)");
            $insertStmt->bind_param("sss", $email, $token, $expires_at);
            
            if ($insertStmt->execute()) {
                // 5. Send Email Logic
                $resetLink = URL_RESET_PWD . "?token=" . $token;
                
                // Try to send email
                $mailSent = sendPasswordResetEmail($email, $resetLink);

                // [LOGIC IMPROVED]
                // If Localhost OR Email Failed, show the link directly for debugging
                if ($mailSent && !$isLocal) {
                    $message = "重置链接已发送，请检查您的邮箱 (含垃圾箱)";
                    $msgType = "success";
                } else {
                    // Fallback for Localhost or Failed Email
                    $message = "<strong>调试模式 (Email发送失败或本地环境):</strong><br>请点击此链接重置密码:<br><a href='" . $resetLink . "'>[点击这里重置密码]</a>";
                    $msgType = "warning";
                }
            } else {
                $message = "系统错误，请稍后再试";
                $msgType = "danger";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<?php require_once BASE_PATH . 'include/header.php'; ?>
<body class="auth-page">

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="login-card shadow-lg p-4 bg-white rounded">
                <div class="logo text-center mb-4">Star<span class="text-primary fw-bold">Admin</span></div>
                <h3 class="text-center">忘记密码？</h3>
                <p class="subtext text-center text-muted">输入您的注册邮箱，我们将向您发送重置链接</p>

                <div id="clientError" class="alert alert-danger" style="display:none;"></div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show text-break" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss=\"alert\" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form id="forgotForm" method="POST" autocomplete="off" novalidate>
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <label for="email">邮箱</label>
                    </div>
                    <button id="forgotBtn" type="submit" class="btn btn-primary w-100 py-2 fw-bold">发送重置链接</button>
                    <div class="text-center mt-4 border-top pt-3">
                        <a href="<?php echo URL_LOGIN; ?>" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left"></i> 返回登录</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo URL_ASSETS; ?>/js/forgot-password-script.js"></script>
</body>
</html>
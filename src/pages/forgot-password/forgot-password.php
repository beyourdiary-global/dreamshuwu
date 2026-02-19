<?php
require_once dirname(__DIR__, 3) . '/common.php';

$message = "";
$msgType = ""; // 'success', 'danger', or 'warning'
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

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
                if ($mailSent && !$isLocal) {
                    $message = "重置链接已发送，请检查您的邮箱 (含垃圾箱)";
                    $msgType = "success";
                    
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => $message, 'redirect' => URL_RESET_PWD]);
                        exit();
                    }
                    header("Location: " . URL_RESET_PWD);
                    exit();
                } else {
                    // Fallback for Localhost or Failed Email - redirect with token
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => '重置链接已生成', 'redirect' => $resetLink]);
                        exit();
                    }
                    header("Location: " . $resetLink);
                    exit();
                }
            } else {
                $message = "系统错误，请稍后再试";
                $msgType = "danger";
            }
        }
        $stmt->close();
    }

    // Return error response for AJAX
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    }
}
?>

<?php $pageMetaKey = 'forgot_password'; ?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<?php require_once BASE_PATH . 'include/header.php'; ?>
<body class="auth-page">
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<main class="dashboard-main">
    <div class="auth-layout">
        <div class="forgot-card">
            <h3>忘记密码？</h3>
            <p class="subtext">输入您的注册邮箱，我们将向您发送重置链接</p>

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
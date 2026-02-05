<?php
require_once __DIR__ . '/../../../init.php'; 
require_once BASE_PATH . 'config/urls.php'; 
require_once BASE_PATH . 'functions.php';

$pageTitle = "登录 - " . WEBSITE_NAME;
$dbTable = USR_LOGIN; 
$loginQuery = "SELECT * FROM " . $dbTable . " WHERE email = ?";
$auditPage = 'Login Page'; 

$message = "";
$errorCode = "";
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
$redirectCandidate = $_GET['redirect'] ?? ($_SESSION['redirect_after_login'] ?? '');
$redirectTarget = isSafeRedirect($redirectCandidate) ? $redirectCandidate : URL_HOME;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? "");
    $password = $_POST['password'] ?? "";
    $redirectFromPost = $_POST['redirect'] ?? "";

    if (isSafeRedirect($redirectFromPost)) {
        $redirectTarget = $redirectFromPost;
    }

    if ($email === "") $errorCode = "EMAIL_REQUIRED";
    elseif (!isValidEmail($email)) $errorCode = "INVALID_EMAIL";
    elseif ($password === "") $errorCode = "PASSWORD_REQUIRED";

    if ($errorCode === "") {
        // [FIXED DB LOGIC]
        $stmt = mysqli_prepare($conn, $loginQuery . " LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        
        // --- COMPATIBILITY FIX: REPLACEMENT FOR get_result() ---
        // This block grabs the data even if your server lacks mysqlnd
        $meta = $stmt->result_metadata();
        $row = [];
        $params = [];
        while ($field = $meta->fetch_field()) {
            $params[] = &$row[$field->name];
        }
        call_user_func_array(array($stmt, 'bind_result'), $params);
        
        if ($stmt->fetch()) {
            // Copy data to $user array so we can use it safely
            $user = array_map(function($v){ return $v; }, $row); 
        } else {
            $user = null;
        }
        $stmt->close();
        // -------------------------------------------------------

        if (!$user) {
            $errorCode = "EMAIL_NOT_FOUND";
        } else {
            if (!isUserActive($user)) {
                $errorCode = "ACCOUNT_DISABLED";
            } 
            // Note: Ensure your DB column is actually 'password' or 'password_hash'
            // We check both just in case
            elseif (!password_verify($password, $user['password_hash'] ?? ($user['password'] ?? ''))) {
                $errorCode = "PASSWORD_INCORRECT";
            } 
            else {
                if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'] ?? $email;
                $_SESSION['logged_in'] = true;
                
                // Audit Log (Safely)
                if (function_exists('logAudit')) {
                    logAudit([
                        'page' => $auditPage, 'action' => 'V',
                        'action_message' => 'User logged in successfully',
                        'query' => $loginQuery, 'query_table' => $dbTable,    
                        'user_id' => (int)$user['id']
                    ]);
                }
                
                unset($_SESSION['redirect_after_login']);

                if ($isAjax) {
                    header('Content-Type: application/json');
                    // Safety check for json_encode
                    if (function_exists('json_encode')) {
                        echo json_encode(['success' => true, 'redirect' => $redirectTarget]);
                    } else {
                        echo '{"success": true, "redirect": "' . $redirectTarget . '"}';
                    }
                    exit();
                }

                header("Location: " . $redirectTarget);
                exit();
            }
        }
    }

    $messageMap = [
        'EMAIL_REQUIRED'    => '请输入邮箱',
        'INVALID_EMAIL'     => '请输入有效邮箱',
        'PASSWORD_REQUIRED' => '请输入密码',
        'EMAIL_NOT_FOUND'   => '该邮箱尚未注册',
        'PASSWORD_INCORRECT'=> '密码错误，请重新输入',
        'ACCOUNT_DISABLED'  => '账号已被停用，请联系管理员',
        'LOGIN_FAILED'      => '登录失败，请稍后再试'
    ];
    $message = $messageMap[$errorCode] ?? '登录失败，请稍后再试';

    if ($isAjax) {
        header('Content-Type: application/json');
        $jsonStr = function_exists('json_encode') 
            ? json_encode(['success' => false, 'message' => $message])
            : '{"success": false, "message": "'.$message.'"}';
        echo $jsonStr;
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<?php require_once __DIR__ . '/../../../include/header.php'; ?>
<body class="auth-page">
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-12 col-md-7 col-lg-5">
            <div class="login-card shadow-lg p-4 bg-white rounded">
                <div class="logo text-center mb-4">Star<span class="text-primary fw-bold">Admin</span></div>
                <h3 class="text-center">欢迎回来</h3>
                <p class="subtext text-center text-muted">请登录您的管理后台</p>
                <div id="loginError" class="alert alert-danger" style="<?php echo $message ? '' : 'display:none;'; ?>">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <span class="error-text"><?php echo htmlspecialchars($message); ?></span>
                </div>
                <form id="loginForm" method="POST" autocomplete="off" novalidate>
                    <input type="hidden" name="redirect" id="redirect" value="<?php echo htmlspecialchars($redirectTarget); ?>">
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                        <label for="email">邮箱地址</label>
                    </div>
                    <div class="form-floating mb-3 password-field position-relative">
                        <input type="password" class="form-control" id="password" name="password" placeholder="密码" required>
                        <label for="password">密码</label>
                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-decoration-none me-2" id="togglePassword">显示</button>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold" id="loginBtn">立即登录</button>
                    <div class="action-links d-flex justify-content-between mt-4 border-top pt-3">
                        <a href="<?php echo URL_REGISTER; ?>" class="text-decoration-none small text-primary">注册新账号</a>
                        <a href="<?php echo URL_FORGOT_PWD; ?>" class="text-decoration-none small text-muted">忘记密码？</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/login-script.js"></script>
</body>
</html>
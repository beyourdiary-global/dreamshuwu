<?php
require_once __DIR__ . '/../../../init.php'; 

// 2. For everything else, use the BASE_PATH constant we just made
require_once BASE_PATH . 'config/urls.php'; 
require_once BASE_PATH . 'functions.php';

// Set page variables BEFORE including header
$pageTitle = "登录 - " . WEBSITE_NAME;

$message = "";
$errorCode = "";

// Check if request is AJAX
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

// Determine where to send the user after successful login
$redirectCandidate = $_GET['redirect'] ?? ($_SESSION['redirect_after_login'] ?? '');

// Use Constant URL_HOME for the default fallback
$redirectTarget = isSafeRedirect($redirectCandidate) ? $redirectCandidate : URL_HOME;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? "");
    $password = $_POST['password'] ?? "";
    $redirectFromPost = $_POST['redirect'] ?? "";

    // If a redirect was passed via POST, validate it
    if (isSafeRedirect($redirectFromPost)) {
        $redirectTarget = $redirectFromPost;
    }

    // 1. Basic Validation
    if ($email === "") {
        $errorCode = "EMAIL_REQUIRED";
    } elseif (!isValidEmail($email)) {
        $errorCode = "INVALID_EMAIL";
    } elseif ($password === "") {
        $errorCode = "PASSWORD_REQUIRED";
    }

    if ($errorCode === "") {
        // 2. Database Lookup
        // Use Constant USR_LOGIN instead of hardcoded table name "users"
        $query = "SELECT * FROM " . USR_LOGIN . " WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = $result ? mysqli_fetch_assoc($result) : null;

        if (!$user) {
            $errorCode = "EMAIL_NOT_FOUND";
        } else {
            // 3. Status Check (Using our new function in functions.php)
            if (!isUserActive($user)) {
                $errorCode = "ACCOUNT_DISABLED";
            } 
            // 4. Password Verification
            elseif (!password_verify($password, $user['password_hash'] ?? '')) {
                $errorCode = "PASSWORD_INCORRECT";
            } 
            // 5. Successful Login
            else {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }

                // SECURITY: Prevent Session Fixation attacks
                session_regenerate_id(true);

                // Set Session Variables
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'] ?? $email;
                $_SESSION['logged_in'] = true;
                
                // Cleanup temporary redirect session
                unset($_SESSION['redirect_after_login']);

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'redirect' => $redirectTarget
                    ]);
                    exit();
                }

                header("Location: " . $redirectTarget);
                exit();
            }
        }
    }

    // 6. Error Handling
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
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
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
                        <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-decoration-none me-2" id="togglePassword">
                            显示
                        </button>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold" id="loginBtn">
                        立即登录
                    </button>

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
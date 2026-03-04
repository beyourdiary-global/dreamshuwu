<?php
require_once dirname(__DIR__, 3) . '/common.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isPostRequest() && hasSession('logged_in') && session('logged_in') === true) {
    header('Location: ' . URL_HOME);
    exit();
}

$dbTable = USR_LOGIN; 
$loginQuery = "SELECT * FROM " . $dbTable . " WHERE email = ?";
$auditPage = 'Login Page'; 

$message = "";
$errorCode = "";
$isAjax = post('ajax') === '1';
$redirectCandidate = input('redirect') ?: session('redirect_after_login');
$redirectTarget = isSafeRedirect($redirectCandidate) ? $redirectCandidate : URL_HOME;

if (isPostRequest()) {
    $email = postSpaceFilter('email');
    $password = post('password') ?? "";
    $redirectFromPost = post('redirect') ?? "";

    if (isSafeRedirect($redirectFromPost)) {
        $redirectTarget = $redirectFromPost;
    }

    if ($email === "") $errorCode = "EMAIL_REQUIRED";
    elseif (!isValidEmail($email)) $errorCode = "INVALID_EMAIL";
    elseif ($password === "") $errorCode = "PASSWORD_REQUIRED";

    if ($errorCode === "") {
        $stmt = mysqli_prepare($conn, $loginQuery . " LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        
        // COMPATIBILITY FIX: REPLACEMENT FOR get_result()
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

        if (!$user) {
            $errorCode = "EMAIL_NOT_FOUND";
        } else {
            if (!isUserActive($user)) {
                $errorCode = "ACCOUNT_DISABLED";
            } 
            elseif (!password_verify($password, $user['password_hash'] ?? ($user['password'] ?? ''))) {
                $errorCode = "PASSWORD_INCORRECT";
            } 
            else {
                if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                session_regenerate_id(true);

                if (!headers_sent() && session_name() !== '' && session_id() !== '') {
                    $lifetime = defined('SESSION_LIFETIME') ? (int)SESSION_LIFETIME : (60 * 60 * 24 * 30);
                    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                    setcookie(session_name(), session_id(), [
                        'expires' => time() + $lifetime,
                        'path' => '/',
                        'domain' => '',
                        'secure' => $isHttps,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                }

                // Set session variables including role_id
                setSession('user_id', $user['id']);
                setSession('user_name', $user['name'] ?? $email);
                setSession('role_id', isset($user['user_role_id']) ? $user['user_role_id'] : 0);
                setSession('logged_in', true);
                
                // Audit Log (Safely)
                if (function_exists('logAudit')) {
                    logAudit([
                        'page' => $auditPage, 'action' => 'V',
                        'action_message' => 'User logged in successfully',
                        'query' => $loginQuery, 'query_table' => $dbTable,    
                        'user_id' => $user['id']
                    ]);
                }
                
                unsetSession('redirect_after_login');

                if ($isAjax) {
                    header('Content-Type: application/json');
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
<?php $pageMetaKey = parse_url(URL_LOGIN, PHP_URL_PATH) ?: '/login.php'; ?>
<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/auth.css?v=<?php echo time(); ?>">
</head>
<body class="auth-page">
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<main class="auth-main">
    <div class="auth-layout">
        <div class="login-card">
            <h3>欢迎回来</h3>
            <p class="subtext">请登录您的管理后台</p>
            
            <div id="loginError" class="alert alert-danger native-alert" style="<?php echo $message ? '' : 'display:none;'; ?>">
                <span class="error-text"><?php echo htmlspecialchars($message); ?></span>
            </div>

            <form id="loginForm" method="POST" autocomplete="off" novalidate>
                <input type="hidden" name="redirect" id="redirect" value="<?php echo htmlspecialchars($redirectTarget); ?>">
                
                <div class="auth-field mb-3">
                    <label class="form-label">邮箱地址</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="请输入邮箱" required>
                </div>

                <div class="auth-field mb-3">
                    <label class="form-label">密码</label>
                    <div class="password-field">
                        <input type="password" class="form-control" id="password" name="password" placeholder="请输入密码" required>
                        <button type="button" class="toggle-password" data-target="password">
                            <i class="fa fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="loginBtn">立即登录</button>
                
                <div class="footer-links">
                    <a href="<?php echo URL_REGISTER; ?>" class="text-decoration-none">注册新账号</a>
                    <a href="<?php echo URL_FORGOT_PWD; ?>" class="text-decoration-none text-muted">忘记密码？</a>
                </div>
            </form>
        </div>
    </div>
</main>
<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/auth.js"></script>
<script>
window.addEventListener('pageshow', function (event) {
    var nav = performance.getEntriesByType('navigation');
    var isBackForward = nav && nav.length > 0 && nav[0].type === 'back_forward';
    if (event.persisted || isBackForward) {
        window.location.reload();
    }
});
</script>
</body>
</html>
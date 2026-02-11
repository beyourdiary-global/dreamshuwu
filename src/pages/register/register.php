<?php
require_once __DIR__ . '/../../../init.php'; 

require_once BASE_PATH . '/config/urls.php'; 
require_once BASE_PATH . 'functions.php';

// Set page variables BEFORE including header
$pageTitle = "注册 - " . WEBSITE_NAME;
$auditPage = 'Register Page';

$dbTable = USR_LOGIN;
$insertQuery = "INSERT INTO " . $dbTable . " (name, email, password_hash, gender, birthday) VALUES (?, ?, ?, ?, ?)";
$checkQuery  = "SELECT id FROM " . $dbTable . " WHERE email = ?";

$message = "";
$name = "";
$email = "";
$gender = "";
$birthday = "";

// Process Form Submission (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $gender = $_POST['gender'] ?? "";
    $birthday = $_POST['birthday'] ?? "";

    // Validation Logic
    if (empty($name) || empty($email) || empty($password)) {
        $message = "请填写所有必填字段";
    } 
    elseif (!isValidEmail($email)) {
        $message = "邮箱格式不正确";
    } 
    elseif (!isStrongPassword($password)) {
        $message = "密码不符合要求 (最低" . MIN_PWD_LENGTH . "个字符，需包含大小写字母、数字和特殊字符)";
    } 
    // Simplified Age Validation
    elseif (!empty($birthday)) {
        $ageCheck = checkAgeRequirement($birthday, MIN_AGE_REQUIREMENT);

        if ($ageCheck === "future_date") {
            $message = "生日不能晚于今天";
        } elseif ($ageCheck === false) {
            $message = "您必须年满" . MIN_AGE_REQUIREMENT . "岁才能注册";
        }
    }

    // Database Operations ---
    if (empty($message)) {
        // Check if email already exists in the system
        $check = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $message = "该邮箱已被注册";
        } else {
            // Securely hash the password using Bcrypt
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $genderDb = $gender !== "" ? $gender : null;
            $birthdayDb = $birthday !== "" ? $birthday : null;

            // Prepare the insertion SQL
            // UPDATE: Used USR_LOGIN constant instead of hardcoded 'users'
            $stmt = mysqli_prepare(
                $conn,
                $insertQuery
            );
            mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $hash, $genderDb, $birthdayDb);
            $success = mysqli_stmt_execute($stmt);

            if ($success) {
                // Get the ID of the new user we just created
                $newUserId = mysqli_insert_id($conn);
                
                logAudit([
                    'page'           => $auditPage,
                    'action'         => 'A',             // A = Add
                    'action_message' => 'New user registered',
                    'query'          => $insertQuery,
                    'query_table'    => $dbTable,
                    'new_value'      => [
                        'name'     => $name,
                        'email'    => $email,
                        'gender'   => $gender,
                        'birthday' => $birthday
                    ],
                    'user_id'        => $newUserId
                ]);
            
            // ---------------------------------------------------------
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
                $userId = mysqli_insert_id($conn);
                $_SESSION['user_id'] = $userId ? (int)$userId : null;
                $_SESSION['user_name'] = $name;
                $_SESSION['logged_in'] = true;

                header("Location: " . URL_HOME);
                exit();
            } else {
                $message = "系统错误，请稍后再试";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<?php require_once __DIR__ . '/../../../include/header.php'; ?>
<body class="auth-page">
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<main class="dashboard-main">
    <div class="auth-layout">
        <div class="reg-card">
            <div class="logo">Star<span>Admin</span></div>
            <h3>新用户？</h3>
            <p style="color: #666;">简单几步即可完成注册</p>
            
            <?php if($message): ?>
                <div class="error-msg"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form id="regForm" method="POST" autocomplete="off">
                <div class="auth-field">
                    <label class="form-label" for="name">姓名</label>
                    <input
                        type="text"
                        class="form-control"
                        id="name"
                        name="name"
                        placeholder="姓名"
                        value="<?php echo htmlspecialchars($name); ?>"
                        required
                    >
                </div>

                <div class="auth-field">
                    <label class="form-label" for="email">邮箱地址</label>
                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        placeholder="请输入邮箱"
                        value="<?php echo htmlspecialchars($email); ?>"
                        required
                    >
                </div>

                <div class="auth-field">
                    <label class="form-label" for="password">密码</label>
                    <input
                        type="password"
                        class="form-control"
                        name="password"
                        id="password"
                        placeholder="请输入密码"
                        required
                    >
                    <div id="strength-meter">密码强度提示: <span id="strength-text">未填写</span></div>
                </div>

                <div class="auth-field">
                    <label class="form-label" for="gender">性别 (可选)</label>
                    <select class="form-control" id="gender" name="gender">
                        <?php foreach ($GENDER_OPTIONS as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($gender == $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="auth-field">
                    <label class="form-label" for="birthday">生日 (可选)</label>
                    <input
                        type="date"
                        class="form-control"
                        name="birthday"
                        id="birthday"
                        max="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo htmlspecialchars($birthday); ?>"
                        oninvalid="this.setCustomValidity('日期不能晚于今天')"
                        oninput="this.setCustomValidity('')"
                    >
                </div>

                <div class="auth-field">
                    <div class="terms-container">
                        <input type="checkbox" id="terms" required class="terms-checkbox">
                        <label for="terms" style="cursor:pointer;">我同意所有条款与条件</label>
                    </div>
                </div>

                <button type="submit" class="btn-reg" id="submitBtn" disabled>注册</button>
            </form>

           <div class="footer-links">
            已有账号？
            <a href="<?= URL_LOGIN ?>" style="color: #233dd2; text-decoration: none;">
                直接登录
            </a>
        </div>
        </div>
    </div>
</main>

<script src="<?php echo URL_ASSETS; ?>/js/register-script.js"></script>
</body>
</html>

<?php
$host = 'localhost';
$db   = 'star_admin';
$user = 'root'; 
$pass = ''; 

try {
    // Establishing a PDO connection with UTF-8 support
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}


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
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "邮箱格式不正确";
    } 
    elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $message = "密码不符合要求 (最低8个字符，需包含大小写字母、数字和特殊字符)";
    } 
    // Age Validation (If birthday is provided)
    elseif (!empty($birthday)) {
        $today = new DateTime();
        $birthDate = new DateTime($birthday);
        
        if ($birthDate > $today) {
            $message = "生日不能晚于今天";
        } else {
            // Check if user meets the 13-year-old minimum requirement
            $age = $today->diff($birthDate)->y;
            if ($age < 13) {
                $message = "您必须年满13岁才能注册";
            }
        }
    }

    // Database Operations ---
    if (empty($message)) {
        // Check if email already exists in the system
        $check = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $check->execute(['email' => $email]);
        
        if ($check->fetch()) {
            $message = "该邮箱已被注册"; 
        } else {
            // Securely hash the password using Bcrypt
            $hash = password_hash($password, PASSWORD_BCRYPT);
            
            // Prepare the insertion SQL
            $sql = "INSERT INTO users (name, email, password_hash, gender, birthday) 
                    VALUES (:name, :email, :pass, :gender, :bday)";
            
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                'name'   => $name,
                'email'  => $email,
                'pass'   => $hash,
                'gender' => $gender ?: null, // Insert NULL if empty
                'bday'   => $birthday ?: null // Insert NULL if empty
            ]);

            if ($success) {
                // AUTO LOGIN: Start a session and redirect to Welcome page
                session_start();
                $_SESSION['user_name'] = $name;
                $_SESSION['logged_in'] = true;
                
                header("Location: welcome.php");
                exit();
            } else {
                $message = "系统错误，请稍后再试";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StarAdmin - 注册</title>
    <link rel="stylesheet" href="register-style.css">
</head>
<body>
<div class="reg-card">
    <div class="logo">Star<span>Admin</span></div>
    <h3>新用户？</h3>
    <p style="color: #666;">简单几步即可完成注册</p>
    
    <?php if($message): ?>
        <div class="error-msg"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form id="regForm" method="POST" autocomplete="off">
        <input type="text" name="name" placeholder="姓名" 
               value="<?php echo htmlspecialchars($name); ?>" required>
        
        <input type="email" name="email" placeholder="请输入邮箱" 
               value="<?php echo htmlspecialchars($email); ?>" required>
        
        <input type="password" name="password" id="password" placeholder="请输入密码" required>
        <div id="strength-meter">密码强度提示: <span id="strength-text">未填写</span></div>
        
        <select name="gender">
            <option value="" <?php echo ($gender == '') ? 'selected' : ''; ?>>选择性别 (可选)</option>
            <option value="M" <?php echo ($gender == 'M') ? 'selected' : ''; ?>>男</option>
            <option value="F" <?php echo ($gender == 'F') ? 'selected' : ''; ?>>女</option>
            <option value="O" <?php echo ($gender == 'O') ? 'selected' : ''; ?>>其他</option>
        </select>
        
        <label style="font-size: 12px; color: #666; display: block; margin-top: 10px;">生日 (可选):</label>
        
        <input type="date" name="birthday" id="birthday"
               max="<?php echo date('Y-m-d'); ?>"
               value="<?php echo htmlspecialchars($birthday); ?>"
               oninvalid="this.setCustomValidity('日期不能晚于今天')"
               oninput="this.setCustomValidity('')">

        <div style="font-size: 13px; color: #666; margin-top: 10px;">
            <input type="checkbox" id="terms" required style="width: auto; margin-right: 5px;"> 我同意所有条款与条件
        </div>

        <button type="submit" class="btn-reg" id="submitBtn" disabled>注册</button>
    </form>

    <div class="footer-links">
        已有账号？ <a href="login.php" style="color: #233dd2; text-decoration: none;">直接登录</a>
    </div>
</div>

<script src="register-script.js"></script>
</body>
</html>
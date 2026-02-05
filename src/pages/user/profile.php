<?php
require_once __DIR__ . '/../../../init.php'; 
require_once BASE_PATH . 'config/urls.php'; 
require_once BASE_PATH . 'functions.php';

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$userId = $_SESSION['user_id'];
$message = "";
$msgType = ""; 

// --- CHECK FOR FLASH MESSAGES ---
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    $msgType = $_SESSION['flash_type'];
    unset($_SESSION['flash_msg']);
    unset($_SESSION['flash_type']);
}

// --- HANDLE FORM A: UPDATE PROFILE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'update_info') {
    
    $name = trim($_POST['display_name']);
    $email = trim($_POST['email']);
    $gender = $_POST['gender'] ?? null;
    $birthday = $_POST['birth_date'] ?: null; 

    if (empty($name) || empty($email)) {
        $message = "昵称和电子邮箱不能为空";
        $msgType = "danger";
    } elseif (!isValidEmail($email)) {
        $message = "请输入正确的电子邮箱";
        $msgType = "danger";
    } else {
        
        // Handle Upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['size'] > 0) {
            $uploadDir = BASE_PATH . 'assets/uploads/avatars/';
            $result = uploadImage($_FILES['avatar'], $uploadDir); 

            if ($result['success']) {
                // 1. FETCH OLD AVATAR (Before updating DB)
                $oldAvQuery = "SELECT avatar FROM " . USR_DASHBOARD . " WHERE user_id = ? LIMIT 1";
                $oldStmt = $conn->prepare($oldAvQuery);
                $oldStmt->bind_param("i", $userId);
                $oldStmt->execute();
                $oldRes = $oldStmt->get_result();
                
                if ($oldRow = $oldRes->fetch_assoc()) {
                    $oldFile = $oldRow['avatar'];
                    $oldFilePath = $uploadDir . $oldFile;
                    
                    // 2. DELETE OLD FILE (If it exists and is not empty)
                    if (!empty($oldFile) && file_exists($oldFilePath)) {
                        @unlink($oldFilePath); // Suppress errors if file missing
                    }
                }

                // 3. SAVE NEW AVATAR TO DB
                $avSql = "INSERT INTO " . USR_DASHBOARD . " (user_id, avatar) VALUES (?, ?) 
                          ON DUPLICATE KEY UPDATE avatar = VALUES(avatar)";
                $avStmt = $conn->prepare($avSql);
                $avStmt->bind_param("is", $userId, $result['filename']);
                $avStmt->execute();
            } else {
                $message = $result['message'];
                $msgType = "danger";
            }
        }

        if (empty($message) || $msgType !== 'danger') {
            $upSql = "UPDATE " . USR_LOGIN . " SET name = ?, email = ?, gender = ?, birthday = ? WHERE id = ?";
            $upStmt = $conn->prepare($upSql);
            $upStmt->bind_param("ssssi", $name, $email, $gender, $birthday, $userId);
            
            if ($upStmt->execute()) {
                $_SESSION['user_name'] = $name; 
                $_SESSION['flash_msg'] = "资料已更新";
                $_SESSION['flash_type'] = "success";
                header("Location: " . URL_PROFILE); 
                exit(); 
            } else {
                $message = "更新失败: " . $conn->error;
                $msgType = "danger";
            }
        }
    }
}

// --- HANDLE FORM B: CHANGE PASSWORD ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'change_pwd') {
    $currentPwd = $_POST['current_password'];
    $newPwd = $_POST['new_password'];
    $confirmPwd = $_POST['confirm_password'];

    $pwdSql = "SELECT password_hash FROM " . USR_LOGIN . " WHERE id = ? LIMIT 1";
    $pwdStmt = $conn->prepare($pwdSql);
    $pwdStmt->bind_param("i", $userId);
    $pwdStmt->execute();
    $pwdResult = $pwdStmt->get_result();
    $userRow = $pwdResult->fetch_assoc();

    // [Fix 1: Null Pointer Check]
    // Check if user exists before accessing password_hash to avoid crash
    if (!$userRow || !isset($userRow['password_hash'])) {
        $message = "系统错误: 无法获取用户信息";
        $msgType = "danger";
    } 
    // [Check Password Match]
    elseif (!password_verify($currentPwd, $userRow['password_hash'])) {
        $message = "当前密码错误";
        $msgType = "danger";
    } elseif ($newPwd !== $confirmPwd) {
        $message = "两次输入的密码不一致";
        $msgType = "danger";
    } elseif (!isStrongPassword($newPwd)) {
        $message = "新密码强度不足 (需包含大小写字母、数字和特殊字符)";
        $msgType = "danger";
    } else {
        $newHash = password_hash($newPwd, PASSWORD_DEFAULT);
        $upPwdSql = "UPDATE " . USR_LOGIN . " SET password_hash = ? WHERE id = ?";
        $upPwdStmt = $conn->prepare($upPwdSql);
        $upPwdStmt->bind_param("si", $newHash, $userId);
        
        if ($upPwdStmt->execute()) {
            session_destroy();
            echo "<script>alert('密码修改成功，请重新登录'); window.location.href='" . URL_LOGIN . "';</script>";
            exit();
        } else {
            $message = "系统错误";
            $msgType = "danger";
        }
    }
}

// --- FETCH DATA (Split Queries for Efficiency) ---

// 1. Fetch Basic Info from Users Table
$userSql = "SELECT name, email, gender, birthday FROM " . USR_LOGIN . " WHERE id = ? LIMIT 1";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userRow = $userStmt->get_result()->fetch_assoc();

// 2. Fetch Avatar from Dashboard Table
$dashSql = "SELECT avatar FROM " . USR_DASHBOARD . " WHERE user_id = ? LIMIT 1";
$dashStmt = $conn->prepare($dashSql);
$dashStmt->bind_param("i", $userId);
$dashStmt->execute();
$dashRow = $dashStmt->get_result()->fetch_assoc();

// 3. Merge results
$currentUser = array_merge($userRow, $dashRow ?? ['avatar' => null]);

$avatarUrl = URL_ASSETS . '/images/default-avatar.png'; // Default
if (!empty($currentUser['avatar'])) {
    $avatarUrl = URL_ASSETS . '/uploads/avatars/' . $currentUser['avatar'];
}

$pageTitle = "编辑个人资料 - " . WEBSITE_NAME;
$customCSS = "user-profile.css";
?>

<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/<?php echo $customCSS; ?>">
</head>
<body>

<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="profile-container">
    
    <div class="section-header">
        <div class="header-text-content">
            <h2>个人资料设置</h2>
            <small class="text-muted">User ID: <?php echo $userId; ?></small>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $msgType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div id="js-alert-box" class="alert alert-danger d-none"></div>

    <div class="profile-form-card">
        <div class="form-title"><i class="fa-solid fa-user-pen"></i> 编辑个人资料</div>
        
        <form method="POST" enctype="multipart/form-data" id="infoForm" novalidate>
            <input type="hidden" name="action" value="update_info">

            <div class="avatar-upload-wrapper">
                <img src="<?php echo $avatarUrl; ?>" alt="Avatar" class="avatar-preview" id="avatarPreview">
                <div class="upload-btn-wrapper">
                    <button type="button" class="btn-upload">更换头像</button>
                    <input type="file" 
                           name="avatar" 
                           id="avatarInput" 
                           accept="image/png, image/jpeg, image/jpg"
                           data-max-size="<?php echo AVATAR_UPLOAD_SIZE; ?>">
                </div>
                <small class="text-muted mt-2">支持 JPG/PNG, 最大 2MB</small>
            </div>

            <div class="form-row">
                <div class="col-half form-group">
                    <label>昵称 <span class="text-danger">*</span></label>
                    <input type="text" name="display_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['name']); ?>">
                </div>
                <div class="col-half form-group">
                    <label>电子邮箱 <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($currentUser['email']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="col-half form-group">
                    <label>性别</label>
                    <select name="gender" class="form-control">
                        <?php foreach ($GENDER_OPTIONS as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($currentUser['gender'] === $key) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-half form-group">
                    <label>生日</label>
                    <input type="date" name="birth_date" class="form-control" value="<?php echo $currentUser['birthday']; ?>">
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn-save">保存资料</button>
                <button type="reset" class="btn-cancel">取消</button>
            </div>
        </form>
    </div>

    <div class="profile-form-card">
        <div class="form-title"><i class="fa-solid fa-lock"></i> 修改密码</div>
        
        <form method="POST" id="passwordForm" novalidate>
            <input type="hidden" name="action" value="change_pwd">

            <div class="form-group">
                <label>当前密码 <span class="text-danger">*</span></label>
                <input type="password" name="current_password" class="form-control">
            </div>

            <div class="form-row">
                <div class="col-half form-group">
                    <label>新密码 <span class="text-danger">*</span></label>
                    <input type="password" name="new_password" id="new_password" class="form-control">
                </div>
                <div class="col-half form-group">
                    <label>确认新密码 <span class="text-danger">*</span></label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control">
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn-danger">修改密码</button>
                <button type="reset" class="btn-cancel">重置</button>
            </div>
        </form>
    </div>

</div>

<script src="<?php echo URL_ASSETS; ?>/js/user-profile.js"></script>
</body>
</html>
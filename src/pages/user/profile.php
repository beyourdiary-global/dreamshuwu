<?php
require_once __DIR__ . '/../../../init.php'; 
require_once BASE_PATH . '/config/urls.php'; 
require_once BASE_PATH . 'functions.php';

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$userId = $_SESSION['user_id'];
$message = "";
$msgType = ""; 

// Flash Message Check
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    $msgType = $_SESSION['flash_type'];
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// --- HANDLE FORM A: UPDATE INFO ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'update_info') {
    $name = trim($_POST['display_name']);
    $email = trim($_POST['email']);
    $gender = $_POST['gender'] ?? null;
    $birthday = $_POST['birth_date'] ?: null; 

    if (empty($name) || empty($email)) {
        $message = "昵称和电子邮箱不能为空"; $msgType = "danger";
    } elseif (!isValidEmail($email)) {
        $message = "请输入正确的电子邮箱"; $msgType = "danger";
    } else {
        // --- IMAGE UPLOAD LOGIC ---
        if (isset($_FILES['avatar']) && $_FILES['avatar']['size'] > 0) {
            $uploadDir = BASE_PATH . 'assets/uploads/avatars/';
            
            // 1. FIND OLD AVATAR FIRST
            $oldAvSql = "SELECT avatar FROM " . USR_DASHBOARD . " WHERE user_id = ? LIMIT 1";
            $oldStmt = $conn->prepare($oldAvSql);
            $oldStmt->bind_param("i", $userId);
            $oldStmt->execute();
            
            // Universal Fetch
            $oldRow = [];
            $meta = $oldStmt->result_metadata();
            $row = []; $params = [];
            while ($field = $meta->fetch_field()) { $params[] = &$row[$field->name]; }
            call_user_func_array(array($oldStmt, 'bind_result'), $params);
            if ($oldStmt->fetch()) { foreach($row as $k=>$v){ $oldRow[$k]=$v; } }
            $oldStmt->close();

            // 2. DELETE OLD FILE IF EXISTS
            if (!empty($oldRow['avatar'])) {
                $oldFilePath = $uploadDir . $oldRow['avatar'];
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath); // Delete the file
                }
            }

            // 3. UPLOAD NEW AVATAR
            $result = uploadImage($_FILES['avatar'], $uploadDir); 

            if ($result['success']) {
                $avSql = "INSERT INTO " . USR_DASHBOARD . " (user_id, avatar) VALUES (?, ?) ON DUPLICATE KEY UPDATE avatar = VALUES(avatar)";
                $avStmt = $conn->prepare($avSql);
                $avStmt->bind_param("is", $userId, $result['filename']);
                $avStmt->execute();
            } else {
                $message = $result['message']; $msgType = "danger";
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
                header("Location: " . URL_PROFILE); exit(); 
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
    
    $userRow = null;
    $meta = $pwdStmt->result_metadata();
    $row = []; $params = [];
    while ($field = $meta->fetch_field()) { $params[] = &$row[$field->name]; }
    call_user_func_array(array($pwdStmt, 'bind_result'), $params);
    if ($pwdStmt->fetch()) { foreach($row as $k=>$v){ $userRow[$k]=$v; } }
    $pwdStmt->close();

    if (!$userRow || !password_verify($currentPwd, $userRow['password_hash'])) {
        $message = "当前密码错误"; $msgType = "danger";
    } elseif ($newPwd !== $confirmPwd) {
        $message = "两次输入的密码不一致"; $msgType = "danger";
    } elseif (!isStrongPassword($newPwd)) {
        $message = "新密码强度不足"; $msgType = "danger";
    } else {
        $newHash = password_hash($newPwd, PASSWORD_DEFAULT);
        $upPwdSql = "UPDATE " . USR_LOGIN . " SET password_hash = ? WHERE id = ?";
        $upPwdStmt = $conn->prepare($upPwdSql);
        $upPwdStmt->bind_param("si", $newHash, $userId);
        
        if ($upPwdStmt->execute()) {
            session_destroy(); 
            ?>
            <!DOCTYPE html>
            <html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
            <head>
                <?php require_once BASE_PATH . 'include/header.php'; ?>
                <style>
                    body { background: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; }
                    .success-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; max-width: 400px; width: 100%; }
                    .icon-box { font-size: 50px; color: #198754; margin-bottom: 20px; }
                    .btn-login { background: #0d6efd; color: white; padding: 10px 30px; text-decoration: none; border-radius: 6px; display: inline-block; margin-top: 20px; transition: 0.2s; }
                    .btn-login:hover { background: #0b5ed7; color: white; }
                </style>
            </head>
            <body>
                <div class="success-card">
                    <div class="icon-box"><i class="fa-solid fa-circle-check"></i></div>
                    <h3>密码修改成功</h3>
                    <p class="text-muted">您的密码已更新，请使用新密码重新登录。</p>
                    <a href="<?php echo URL_LOGIN; ?>" class="btn-login">立即登录</a>
                </div>
            </body>
            </html>
            <?php
            exit(); 
        }
    }
}

// Fetch Data for View
$userSql = "SELECT name, email, gender, birthday FROM " . USR_LOGIN . " WHERE id = ? LIMIT 1";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userRow = [];
$meta = $userStmt->result_metadata();
$row = []; $params = [];
while ($field = $meta->fetch_field()) { $params[] = &$row[$field->name]; }
call_user_func_array(array($userStmt, 'bind_result'), $params);
if ($userStmt->fetch()) { foreach($row as $k=>$v){ $userRow[$k]=$v; } }
$userStmt->close();

$dashSql = "SELECT avatar FROM " . USR_DASHBOARD . " WHERE user_id = ? LIMIT 1";
$dashStmt = $conn->prepare($dashSql);
$dashStmt->bind_param("i", $userId);
$dashStmt->execute();
$dashRow = [];
$meta = $dashStmt->result_metadata();
$row = []; $params = [];
while ($field = $meta->fetch_field()) { $params[] = &$row[$field->name]; }
call_user_func_array(array($dashStmt, 'bind_result'), $params);
if ($dashStmt->fetch()) { foreach($row as $k=>$v){ $dashRow[$k]=$v; } }
$dashStmt->close();

$currentUser = array_merge($userRow, $dashRow ?? ['avatar' => null]);
$avatarUrl = !empty($currentUser['avatar']) ? URL_ASSETS . '/uploads/avatars/' . $currentUser['avatar'] : URL_ASSETS . '/images/default-avatar.png';
$pageTitle = "编辑个人资料 - " . WEBSITE_NAME;

$isEmbeddedProfile = defined('PROFILE_EMBEDDED') && PROFILE_EMBEDDED === true;

if (!$isEmbeddedProfile) {
    $_GET['view'] = 'profile';
    define('PROFILE_EMBEDDED', true);
    require BASE_PATH . 'src/pages/user/dashboard.php';
    exit();
}

$sidebarItems = [
    ['label' => '首页',     'url' => URL_USER_DASHBOARD, 'icon' => 'fa-solid fa-house-user', 'active' => false],
    ['label' => '账号中心', 'url' => URL_PROFILE,        'icon' => 'fa-solid fa-id-card',   'active' => true],
    ['label' => '写小说',   'url' => URL_AUTHOR_DASHBOARD, 'icon' => 'fa-solid fa-pen-nib',  'active' => false],
    ['label' => '小说分类', 'url' => URL_NOVEL_CATS,     'icon' => 'fa-solid fa-layer-group','active' => false],
    ['label' => '小说标签', 'url' => URL_NOVEL_TAGS,     'icon' => 'fa-solid fa-tags',      'active' => false]
];
?>

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
                    <input type="file" name="avatar" id="avatarInput" accept="image/png, image/jpeg, image/jpg" data-max-size="<?php echo AVATAR_UPLOAD_SIZE; ?>">
                </div>
                <small class="text-muted mt-2">支持 JPG/PNG, 最大 2MB</small>
            </div>
            <div class="form-row">
                <div class="col-half form-group">
                    <label>昵称 <span class="text-danger">*</span></label>
                    <input type="text" name="display_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>">
                </div>
                <div class="col-half form-group">
                    <label>电子邮箱 <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="col-half form-group">
                    <label>性别</label>
                    <select name="gender" class="form-control">
                        <?php foreach ($GENDER_OPTIONS as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo (isset($currentUser['gender']) && $currentUser['gender'] === $key) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-half form-group">
                    <label>生日</label>
                    <input type="date" name="birth_date" class="form-control" value="<?php echo $currentUser['birthday'] ?? ''; ?>">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn-save">保存资料</button>
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
            </div>
        </form>
    </div>
</div>

        </div>
                <input type="password" name="current_password" class="form-control">

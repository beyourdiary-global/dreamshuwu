<?php
require_once dirname(__DIR__, 3) . '/common.php';

// 1. Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$currentUrl = '/dashboard.php?view=profile';
$perm = hasPagePermission($conn, $currentUrl);

checkPermissionError('view', $perm, '个人资料页面');

$userId = $_SESSION['user_id'];
$message = "";
$msgType = ""; 
$auditPage = 'User Profile'; 
$passwordChangeSuccess = false;
$passwordRedirectUrl = '';

// Determine redirect URL based on context (embedded in dashboard or standalone)
$profileRedirectUrl = defined('PROFILE_EMBEDDED') ? URL_USER_DASHBOARD . '?view=profile' : URL_PROFILE;

// Flash Message Check (This reads the message after redirect)
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    $msgType = $_SESSION['flash_type'];
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// --- HANDLE FORM A: UPDATE INFO ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'update_info') {
    checkPermissionError('edit', $perm, '个人资料');

    $name = trim($_POST['display_name']);
    $email = trim($_POST['email']);
    $gender = $_POST['gender'] ?? null;
    $birthday = $_POST['birth_date'] ?: null; 

    if (empty($name) || empty($email)) {
        $message = "昵称和电子邮箱不能为空"; $msgType = "danger";
    } elseif (!isValidEmail($email)) {
        $message = "请输入正确的电子邮箱"; $msgType = "danger";
    } else {
        // [AUDIT & COMPARE] Fetch Old Data
        $oldUserData = [];
        $preStmt = $conn->prepare("SELECT name, email, gender, birthday FROM " . USR_LOGIN . " WHERE id = ?");
        $preStmt->bind_param("i", $userId);
        $preStmt->execute();
        $preStmt->bind_result($oName, $oEmail, $oGender, $oDob);
        
        if ($preStmt->fetch()) {
            $oldUserData = ['name' => $oName, 'email' => $oEmail, 'gender' => $oGender, 'birthday' => $oDob];
        }
        $preStmt->close();

        // [NEW] Use global helper to Check for Changes and Redirect
        // This will exit script if no changes found
        checkNoChangesAndRedirect(
            ['name' => $name, 'email' => $email, 'gender' => $gender, 'birthday' => $birthday], 
            $oldUserData, 
            'avatar', 
            $profileRedirectUrl
        );

        // --- CHANGES DETECTED - PROCEED ---
        
        // --- IMAGE UPLOAD LOGIC ---
        $newAvatarName = null;
        // Check manually for upload logic
        $hasAvatarUpload = (isset($_FILES['avatar']) && $_FILES['avatar']['size'] > 0);

        if ($hasAvatarUpload) {
            $uploadDir = BASE_PATH . 'assets/uploads/avatars/';
            
            // Find Old Avatar
            $oldAvSql = "SELECT avatar FROM " . USR_DASHBOARD . " WHERE user_id = ? LIMIT 1";
            $oldStmt = $conn->prepare($oldAvSql);
            $oldStmt->bind_param("i", $userId);
            $oldStmt->execute();
            
            $oldRow = [];
            $meta = $oldStmt->result_metadata();
            $row = []; $params = [];
            while ($field = $meta->fetch_field()) { $params[] = &$row[$field->name]; }
            call_user_func_array(array($oldStmt, 'bind_result'), $params);
            if ($oldStmt->fetch()) { foreach($row as $k=>$v){ $oldRow[$k]=$v; } }
            $oldStmt->close();

            // Delete Old File
            if (!empty($oldRow['avatar'])) {
                $oldFilePath = $uploadDir . $oldRow['avatar'];
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath); 
                }
                $oldUserData['avatar'] = $oldRow['avatar'];
            }

            // Upload New
            $result = uploadImage($_FILES['avatar'], $uploadDir); 

            if ($result['success']) {
                $newAvatarName = $result['filename'];
                $avSql = "INSERT INTO " . USR_DASHBOARD . " (user_id, avatar) VALUES (?, ?) ON DUPLICATE KEY UPDATE avatar = VALUES(avatar)";
                $avStmt = $conn->prepare($avSql);
                $avStmt->bind_param("is", $userId, $result['filename']);
                $avStmt->execute();
            } else {
                $message = $result['message']; $msgType = "danger";
            }
        }

        // Update Text Data (Only if no upload error)
        if (empty($message) || $msgType !== 'danger') {
            $upSql = "UPDATE " . USR_LOGIN . " SET name = ?, email = ?, gender = ?, birthday = ? WHERE id = ?";
            $upStmt = $conn->prepare($upSql);
            $upStmt->bind_param("ssssi", $name, $email, $gender, $birthday, $userId);
            if ($upStmt->execute()) {
                
                // [AUDIT] Log Update Action
                if (function_exists('logAudit')) {
                    $newUserData = ['name' => $name, 'email' => $email, 'gender' => $gender, 'birthday' => $birthday];
                    if ($newAvatarName) { $newUserData['avatar'] = $newAvatarName; }
                    
                    logAudit([
                        'page'           => $auditPage,
                        'action'         => 'E',
                        'action_message' => 'Updated Profile Info',
                        'query'          => $upSql,
                        'query_table'    => USR_LOGIN,
                        'user_id'        => $userId,
                        'record_id'      => $userId,
                        'old_value'      => $oldUserData,
                        'new_value'      => $newUserData
                    ]);
                }

                $_SESSION['user_name'] = $name; 
                $_SESSION['flash_msg'] = "资料已更新";
                $_SESSION['flash_type'] = "success";
                if (!headers_sent()) {
                    header("Location: " . $profileRedirectUrl);
                } else {
                    echo "<script>window.location.href='" . $profileRedirectUrl . "';</script>";
                }
                exit(); 
            }
        }
    }
}

// ... (Rest of the file remains unchanged) ...
// --- HANDLE FORM B: CHANGE PASSWORD ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'change_pwd') {
    checkPermissionError('edit', $perm, '密码');

    // ... existing password logic ...
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
            
            // [AUDIT] Log Password Change
            if (function_exists('logAudit')) {
                logAudit([
                    'page'           => $auditPage,
                    'action'         => 'E',
                    'action_message' => 'Changed Password',
                    'query'          => $upPwdSql,
                    'query_table'    => USR_LOGIN,
                    'user_id'        => $userId,
                    'record_id'      => $userId
                ]);
            }

            $_SESSION = [];
            $message = '密码修改成功，请使用新密码重新登录。';
            $msgType = 'success';
            $passwordChangeSuccess = true;
            $passwordRedirectUrl = URL_LOGIN;
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

// [AUDIT] Log View Action (Only for GET requests)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && function_exists('logAudit') && !defined('PROFILE_VIEW_LOGGED')) {
    define('PROFILE_VIEW_LOGGED', true);
    logAudit([
        'page'           => $auditPage,
        'action'         => 'V',
        'action_message' => 'Viewing User Profile',
        'query'          => $userSql,
        'query_table'    => USR_LOGIN,
        'user_id'        => $userId
    ]);
}
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

    <?php if ($passwordChangeSuccess): ?>
        <div id="pwd-redirect" data-url="<?php echo htmlspecialchars($passwordRedirectUrl, ENT_QUOTES, 'UTF-8'); ?>" data-delay="1500"></div>
        <div class="profile-form-card text-center">
            <div class="form-title"><i class="fa-solid fa-circle-check text-success"></i> 密码修改成功</div>
            <p class="text-muted mb-3">请使用新密码重新登录，页面即将自动跳转。</p>
            <a href="<?php echo htmlspecialchars($passwordRedirectUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">立即登录</a>
        </div>
    <?php endif; ?>

    <div id="js-alert-box" class="alert alert-danger d-none"></div>

    <?php if (!$passwordChangeSuccess): ?>
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
                <?php if (!empty($perm->edit)): ?>
                <button type="submit" class="btn-save">保存资料</button>
                <?php endif; ?>
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
                <?php if (!empty($perm->edit)): ?>
                <button type="submit" class="btn-danger">修改密码</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

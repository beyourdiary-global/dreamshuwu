<?php
// Path: src/pages/author/author-register.php
require_once dirname(__DIR__, 3) . '/common.php';

// Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

// View Permission Check
$currentUrl = '/author/author-register.php';
$perm = hasPagePermission($conn, $currentUrl);

// Check page view permission, redirect to dashboard if denied
checkPermissionError('view', $perm, '作者注册页面');

$userId = $_SESSION['user_id'];

// Define all SQL queries at the top for cleaner code management
$sqlGetImages = "SELECT id_photo_front, id_photo_back, avatar FROM " . AUTHOR_PROFILE . " WHERE user_id = ? LIMIT 1";

$sqlInsertProfile = "INSERT INTO " . AUTHOR_PROFILE . " 
                     (user_id, real_name, id_number, id_photo_front, id_photo_back, contact_phone, contact_email, 
                      bank_account_name, bank_name, bank_country, bank_swift_code, bank_account_number, 
                      pen_name, avatar, bio, verification_status, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'A')";

$sqlUpdateProfile = "UPDATE " . AUTHOR_PROFILE . " SET 
                     real_name=?, id_number=?, id_photo_front=?, id_photo_back=?, contact_phone=?, contact_email=?, 
                     bank_account_name=?, bank_name=?, bank_country=?, bank_swift_code=?, bank_account_number=?, 
                     pen_name=?, avatar=?, bio=?, verification_status='pending', updated_at=NOW() 
                     WHERE user_id=?";

$sqlGetProfileData = "SELECT * FROM " . AUTHOR_PROFILE . " WHERE user_id = ? AND status = 'A' LIMIT 1";

// Handle Form Submission (POST) API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // API Check: Return JSON for permission denial
    if (empty($perm->add) && empty($perm->edit)) {
        header('Content-Type: application/json; charset=utf-8');
        echo safeJsonEncode(['success' => false, 'message' => "权限不足：您没有提交或修改作者资料的权限。"]);
        exit();
    }

    $errorMsg = "";

    // Query database first to determine if this is an 'add' or 'edit' operation
    $existingData = [];
    $eStmt = $conn->prepare($sqlGetImages);
    $eStmt->bind_param("i", $userId);
    $eStmt->execute();
    $eRes = $eStmt->get_result();
    if ($eRes && $eRes->num_rows > 0) {
        $existingData = $eRes->fetch_assoc();
    }
    $eStmt->close();

    // Collect and sanitize input data
    $realName = trim($_POST['real_name'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    
    $penName = trim($_POST['pen_name'] ?? '');
    if (empty($penName)) {
        // Auto-generate pen name if left empty
        $penName = uniqid('Author_'); 
    }
    
    $bio = trim($_POST['bio'] ?? '');
    $bankAccountName = trim($_POST['bank_account_name'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $bankCountry = trim($_POST['bank_country'] ?? '');
    $bankSwift = trim($_POST['bank_swift_code'] ?? '');
    $bankAccNum = trim($_POST['bank_account_number'] ?? '');

    // Handle File Uploads & Cleanup Old Files
    $uploadDir = BASE_PATH . 'assets/uploads/authors/';
    
    // Initialize with existing values
    $idFront = $existingData['id_photo_front'] ?? '';
    $idBack  = $existingData['id_photo_back'] ?? '';
    $avatar  = $existingData['avatar'] ?? '';

    // Process ID Front Photo
    if (isset($_FILES['id_photo_front']) && $_FILES['id_photo_front']['size'] > 0) {
        $res = uploadImage($_FILES['id_photo_front'], $uploadDir);
        if ($res['success']) {
            // Delete old file if it exists and a new one was uploaded
            if (!empty($idFront) && file_exists($uploadDir . $idFront)) {
                unlink($uploadDir . $idFront);
            }
            $idFront = $res['filename'];
        } else {
            $errorMsg = "正面身份证上传失败: " . $res['message'];
        }
    }
    
    // Process ID Back Photo
    if (empty($errorMsg) && isset($_FILES['id_photo_back']) && $_FILES['id_photo_back']['size'] > 0) {
        $res = uploadImage($_FILES['id_photo_back'], $uploadDir);
        if ($res['success']) {
            // Delete old file
            if (!empty($idBack) && file_exists($uploadDir . $idBack)) {
                unlink($uploadDir . $idBack);
            }
            $idBack = $res['filename'];
        } else {
            $errorMsg = "反面身份证上传失败: " . $res['message'];
        }
    }
    
    // Process Avatar Photo
    if (empty($errorMsg) && isset($_FILES['avatar']) && $_FILES['avatar']['size'] > 0) {
        $res = uploadImage($_FILES['avatar'], $uploadDir);
        if ($res['success']) {
             // Delete old file
             if (!empty($avatar) && file_exists($uploadDir . $avatar)) {
                unlink($uploadDir . $avatar);
            }
            $avatar = $res['filename'];
        } else {
            $errorMsg = "头像上传失败: " . $res['message'];
        }
    }

    // Validate required images
    if (empty($errorMsg) && (empty($idFront) || empty($idBack))) {
        $errorMsg = "请上传完整的身份证正反面照片。";
    }

    // Execute Database Transaction
    if (empty($errorMsg)) {
        $conn->begin_transaction();
        try {
            if (empty($existingData)) {
                // Insert new record using the predefined SQL variable
                $stmt = $conn->prepare($sqlInsertProfile);
                $stmt->bind_param("issssssssssssss", 
                    $userId, $realName, $idNumber, $idFront, $idBack, $contactPhone, $contactEmail,
                    $bankAccountName, $bankName, $bankCountry, $bankSwift, $bankAccNum,
                    $penName, $avatar, $bio
                );
                if (!$stmt->execute()) throw new Exception("Insert failed: " . $stmt->error);
                $stmt->close();
                $successMsg = "资料已提交！请等待管理员审核。";
            } else {
                // Update existing record using the predefined SQL variable
                $stmt = $conn->prepare($sqlUpdateProfile);
                $stmt->bind_param("ssssssssssssssi", 
                    $realName, $idNumber, $idFront, $idBack, $contactPhone, $contactEmail,
                    $bankAccountName, $bankName, $bankCountry, $bankSwift, $bankAccNum,
                    $penName, $avatar, $bio, $userId
                );
                if (!$stmt->execute()) throw new Exception("Update failed: " . $stmt->error);
                $stmt->close();
                $successMsg = "资料已更新！请等待管理员重新审核。";
            }

            $conn->commit();
            
            // Standard JSON Response for Success
            header('Content-Type: application/json; charset=utf-8');
            echo safeJsonEncode(['success' => true, 'message' => $successMsg]);
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = "系统错误: " . $e->getMessage();
            if (strpos($errorMsg, 'unique_pen_name') !== false) {
                $errorMsg = "该笔名已被其他作者占用，请更换一个笔名。";
            }
        }
    }

    // Standard JSON Response for Error
    header('Content-Type: application/json; charset=utf-8');
    echo safeJsonEncode(['success' => false, 'message' => $errorMsg]);
    exit();
}

// Fetch the latest author_profile status to display on the form
$stmt = $conn->prepare($sqlGetProfileData);
$stmt->bind_param("i", $userId);
$stmt->execute();

$authorData = [];
$meta = $stmt->result_metadata();
if ($meta) {
    $row = []; $params = [];
    while ($field = $meta->fetch_field()) { $params[] = &$row[$field->name]; }
    call_user_func_array(array($stmt, 'bind_result'), $params);
    if ($stmt->fetch()) { 
        foreach($row as $k => $v) { $authorData[$k] = $v; } 
    }
}
$stmt->close();

$authorStatus = $authorData['verification_status'] ?? null;

// Redirect to dashboard if already approved
if ($authorStatus === 'approved') {
    header("Location: " . URL_AUTHOR_DASHBOARD);
    exit();
}

$pageMetaKey = $currentUrl;
?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/author.css?v=<?php echo time(); ?>">
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<main class="dashboard-main bg-light py-5">
    <div class="container author-reg-container shadow-sm bg-white rounded-4 p-4 p-md-5">
        
        <?php if ($authorStatus === 'pending'): ?>
            <div class="alert alert-warning d-flex align-items-center mb-4">
                <i class="fa-solid fa-hourglass-half fa-lg me-3"></i> 
                <div><strong>审核中</strong><br>您的作者申请正在审核中，审核期间您仍可修改以下资料。</div>
            </div>
        <?php elseif ($authorStatus === 'rejected'): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4">
                <i class="fa-solid fa-circle-xmark fa-lg me-3"></i> 
                <div><strong>审核未通过</strong><br>抱歉，您的申请被驳回。请检查并重新提交您的资料。</div>
            </div>
        <?php else: ?>
            <div class="alert alert-info d-flex align-items-center mb-4">
                <i class="fa-solid fa-pen-nib fa-lg me-3"></i> 
                <div><strong>欢迎申请成为作者！</strong><br>请如实填写以下资料以完成实名认证。</div>
            </div>
        <?php endif; ?>

        <?php
        // Define Identity Fields (Text Inputs Only)
        $identityFields = [
            ['name' => 'real_name',     'label' => '真实姓名',   'type' => 'text',  'width' => 'col-md-6', 'required' => true],
            ['name' => 'id_number',     'label' => '身份证号码', 'type' => 'text',  'width' => 'col-md-6', 'required' => true],
            ['name' => 'contact_phone', 'label' => '手机号码',   'type' => 'tel',   'width' => 'col-md-6', 'required' => true],
            ['name' => 'contact_email', 'label' => '联系邮箱',   'type' => 'email', 'width' => 'col-md-6', 'required' => true],
        ];

        // Define Bank Fields
        $bankFields = [
            ['name' => 'bank_account_name',   'label' => '收款人姓名', 'type' => 'text', 'width' => 'col-md-6',  'required' => false],
            ['name' => 'bank_name',           'label' => '银行名称',   'type' => 'text', 'width' => 'col-md-6',  'required' => false],
            ['name' => 'bank_account_number', 'label' => '银行账号',   'type' => 'text', 'width' => 'col-md-12', 'required' => false],
            ['name' => 'bank_country',        'label' => '开户国家',   'type' => 'text', 'width' => 'col-md-6',  'required' => false],
            ['name' => 'bank_swift_code',     'label' => 'SWIFT Code', 'type' => 'text', 'width' => 'col-md-6',  'required' => false],
        ];
        ?>

        <form id="authorRegForm" method="POST" enctype="multipart/form-data">
            
            <h4 class="author-section-title mt-2">真实身份信息 (必填)</h4>
            <div class="row mb-4">
                
                <?php foreach ($identityFields as $field): ?>
                <div class="<?php echo $field['width']; ?> mb-3">
                    <label class="form-label">
                        <?php echo $field['label']; ?> 
                        <?php if ($field['required']): ?><span class="text-danger">*</span><?php endif; ?>
                    </label>
                    <input type="<?php echo $field['type']; ?>" 
                           name="<?php echo $field['name']; ?>" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($authorData[$field['name']] ?? ''); ?>" 
                           <?php echo $field['required'] ? 'required' : ''; ?>>
                </div>
                <?php endforeach; ?>

                <div class="col-md-6 mb-3">
                    <label class="form-label">身份证正面照片 <span class="text-danger">*</span></label>
                    <div class="id-photo-box" id="box_id_front" onclick="document.getElementById('id_photo_front').click();">
                        <div class="placeholder"><i class="fa-solid fa-address-card fa-2x mb-2"></i><br>点击上传正面</div>
                        <?php if(!empty($authorData['id_photo_front'])): ?>
                            <img src="<?php echo URL_ASSETS . '/uploads/authors/' . $authorData['id_photo_front']; ?>">
                        <?php endif; ?>
                    </div>
                    <input type="file" name="id_photo_front" id="id_photo_front" class="d-none" accept="image/jpeg, image/png" <?php echo empty($authorData['id_photo_front']) ? 'required' : ''; ?>>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">身份证反面照片 <span class="text-danger">*</span></label>
                    <div class="id-photo-box" id="box_id_back" onclick="document.getElementById('id_photo_back').click();">
                        <div class="placeholder"><i class="fa-solid fa-id-card-clip fa-2x mb-2"></i><br>点击上传反面</div>
                        <?php if(!empty($authorData['id_photo_back'])): ?>
                            <img src="<?php echo URL_ASSETS . '/uploads/authors/' . $authorData['id_photo_back']; ?>">
                        <?php endif; ?>
                    </div>
                    <input type="file" name="id_photo_back" id="id_photo_back" class="d-none" accept="image/jpeg, image/png" <?php echo empty($authorData['id_photo_back']) ? 'required' : ''; ?>>
                </div>
            </div>

            <h4 class="author-section-title mt-5">作者档案 (选填)</h4>
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">笔名</label>
                            <input type="text" name="pen_name" class="form-control" placeholder="若不填，系统将自动生成唯一笔名" value="<?php echo htmlspecialchars($authorData['pen_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">作者简介</label>
                            <textarea name="bio" class="form-control" rows="4" placeholder="向读者介绍一下自己吧..."><?php echo htmlspecialchars($authorData['bio'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 d-flex flex-column align-items-center">
                    <label class="form-label align-self-start">作者头像</label>
                    <div class="avatar-upload-box" id="box_avatar" onclick="document.getElementById('avatar_input').click();">
                        <div class="placeholder"><i class="fa-solid fa-camera fa-xl"></i></div>
                        <?php if(!empty($authorData['avatar'])): ?>
                            <img src="<?php echo URL_ASSETS . '/uploads/authors/' . $authorData['avatar']; ?>">
                        <?php endif; ?>
                    </div>
                    <input type="file" name="avatar" id="avatar_input" class="d-none" accept="image/jpeg, image/png">
                    <small class="text-muted mt-2">点击框内上传</small>
                </div>
            </div>

            <h4 class="author-section-title mt-5">收款账户信息 (选填 - 用于稿费结算)</h4>
            <div class="row mb-4">
                
                <?php foreach ($bankFields as $field): ?>
                <div class="<?php echo $field['width']; ?> mb-3">
                    <label class="form-label"><?php echo $field['label']; ?></label>
                    <input type="<?php echo $field['type']; ?>" 
                           name="<?php echo $field['name']; ?>" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($authorData[$field['name']] ?? ''); ?>">
                </div>
                <?php endforeach; ?>

            </div>

            <div class="text-center mt-5 pt-3 border-top">
                <?php if (!empty($perm->add) || !empty($perm->edit)): ?>
                    <?php 
                    // Dynamic Button Text and Icon based on Status
                    $buttonText = ($authorStatus === 'pending') ? '更新资料' : '提交申请';
                    $buttonIcon = ($authorStatus === 'pending') ? 'fa-rotate' : 'fa-paper-plane';
                    ?>
                    <button type="button" id="btnSubmitForm" class="btn btn-primary btn-lg px-5">
                        <i class="fa-solid <?php echo $buttonIcon; ?> me-2"></i> <?php echo $buttonText; ?>
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</main>

<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/author.js?v=<?php echo time(); ?>"></script>
</body>
</html>
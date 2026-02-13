<?php
// Path: src/pages/webSetting/index.php
require_once dirname(__DIR__, 3) . '/common.php';

// Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$auditPage = 'Web Settings';
$auditUserId = $_SESSION['user_id'] ?? 0;
$table = WEB_SETTINGS;

$message = ""; 
$msgType = "";

// --- DEFINED QUERIES ---
$sqlWebSettingsUpdate = "UPDATE $table SET 
    website_name = ?, website_logo = ?, website_favicon = ?, 
    theme_bg_color = ?, theme_text_color = ?, 
    button_color = ?, button_text_color = ?, background_color = ? 
    WHERE id = 1";

$sqlWebSettingsInsert = "INSERT INTO $table 
    (website_name, website_logo, website_favicon, theme_bg_color, theme_text_color, button_color, button_text_color, background_color, id) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";

// Query to Remove Logo
$sqlRemoveLogo = "UPDATE $table SET website_logo = '' WHERE id = 1";

// Query to Remove Favicon
$sqlRemoveFavicon = "UPDATE $table SET website_favicon = '' WHERE id = 1";

// Query to Reset Defaults
$sqlResetDefaults = "UPDATE $table SET 
    website_name = 'Website Name', 
    website_logo = '', 
    website_favicon = '', 
    theme_bg_color = '#ffffff', 
    theme_text_color = '#333333', 
    button_color = '#233dd2', 
    button_text_color = '#ffffff', 
    background_color = '#f4f7f6' 
    WHERE id = 1";
// -----------------------

// Flash Message Check
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    $msgType = $_SESSION['flash_type'];
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// Fetch Current Settings
$current = getWebSettings($conn);
if (!$current) {
    $current = array_fill_keys(['website_name', 'website_logo', 'website_favicon', 'theme_bg_color', 'theme_text_color', 'button_color', 'button_text_color', 'background_color'], '');
}

// Define Upload Directory
$uploadDir = dirname(__DIR__, 3) . '/assets/uploads/settings/';

// [AUDIT] Log View
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && function_exists('logAudit') && !defined('WEB_SETTINGS_VIEW_LOGGED')) {
    define('WEB_SETTINGS_VIEW_LOGGED', true);
    logAudit([
        'page'           => $auditPage,
        'action'         => 'V',
        'action_message' => 'Viewing Website Settings',
        'query'          => "SELECT * FROM $table WHERE id = 1",
        'query_table'    => $table,
        'user_id'        => $auditUserId
    ]);
}

// Context Detection
$isEmbeddedWebSetting = isset($EMBED_WEB_SETTING_PAGE) && $EMBED_WEB_SETTING_PAGE === true;
$webBaseUrl = $isEmbeddedWebSetting ? URL_WEB_SETTINGS : '?';

// ========== HANDLE POST REQUESTS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $actionType = $_POST['action_type'] ?? 'save'; 
    $redirectNeeded = false;
    $logActionCode = 'E';
    $logMessage = '';
    $logQuery = '';

    // [NEW] Sanitize and Validate Website Name length
    $rawWebsiteName = $_POST['website_name'] ?? '';
    $sanitizedWebsiteName = trim($rawWebsiteName);
    $maxWebsiteNameLength = 255;

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($sanitizedWebsiteName, 'UTF-8') > $maxWebsiteNameLength) {
            $sanitizedWebsiteName = mb_substr($sanitizedWebsiteName, 0, $maxWebsiteNameLength, 'UTF-8');
        }
    } else {
        if (strlen($sanitizedWebsiteName) > $maxWebsiteNameLength) {
            $sanitizedWebsiteName = substr($sanitizedWebsiteName, 0, $maxWebsiteNameLength);
        }
    }

    // [NEW] 2. Validate Hex Color helper function
    $validateHex = function($value, $default) {
    if (!is_string($value)) return $default;
    // Matches #RRGGBB format
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : $default;
   };

    // --- 1. RESET DEFAULTS ---
    if ($actionType === 'reset_defaults') {
        // [UPDATED] Use prepared statement for consistency
        $stmt = $conn->prepare($sqlResetDefaults);
        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = "系统设置已重置为默认值！";
            $_SESSION['flash_type'] = "warning";
            $redirectNeeded = true;
            $logActionCode = 'D'; 
            $logMessage = 'Reset Website Settings to Default';
            $logQuery = $sqlResetDefaults;
        } else {
            $message = "重置系统设置为默认值时发生错误，请稍后重试。";
            $msgType = "error";
        }
        
        $stmt->close();
    }
    // --- 2. REMOVE LOGO ---
    elseif ($actionType === 'remove_logo') {
        // [SECURE DELETE] Delete physical file with path validation
        if (!empty($current['website_logo'])) {
            $fileToDelete = $uploadDir . $current['website_logo'];
            $realBase = realpath($uploadDir);
            $realFile = realpath($fileToDelete);

            // Validate path traversal protection
            if ($realFile && strpos($realFile, $realBase) === 0 && file_exists($realFile)) {
                unlink($realFile);
            }
        }

        // [UPDATED] Use prepared statement
        $stmt = $conn->prepare($sqlRemoveLogo);
        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = "网站 Logo 已移除！";
            $_SESSION['flash_type'] = "success";
            $redirectNeeded = true;
            $logMessage = 'Removed Website Logo';
            $logQuery = $sqlRemoveLogo;
        }else {
            $_SESSION['flash_msg'] = "移除网站 Logo 失败，请稍后重试。";
            $_SESSION['flash_type'] = "danger";
        }

        $stmt->close();
    }
    // --- 3. REMOVE FAVICON ---
    elseif ($actionType === 'remove_favicon') {
        // [SECURE DELETE] Delete physical file with path validation
        if (!empty($current['website_favicon'])) {
            $fileToDelete = $uploadDir . $current['website_favicon'];
            $realBase = realpath($uploadDir);
            $realFile = realpath($fileToDelete);

            if ($realFile && strpos($realFile, $realBase) === 0 && file_exists($realFile)) {
                unlink($realFile);
            }
        }

        // [UPDATED] Use prepared statement
        $stmt = $conn->prepare($sqlRemoveFavicon);
        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = "网站 Favicon 已移除！";
            $_SESSION['flash_type'] = "success";
            $redirectNeeded = true;
            $logMessage = 'Removed Website Favicon';
            $logQuery = $sqlRemoveFavicon;
        }else {
            $_SESSION['flash_msg'] = "移除网站 Favicon 时出错，请稍后重试。";
            $_SESSION['flash_type'] = "danger";
        }
        
        $stmt->close();
    }

    // --- 4. SAVE SETTINGS (Default) ---
    else {
        // Prepare Data
        $newData = [
            'website_name'     => $sanitizedWebsiteName,
            'theme_bg_color'   => $validateHex($_POST['theme_bg_color'] ?? '', '#ffffff'),
            'theme_text_color' => $validateHex($_POST['theme_text_color'] ?? '', '#333333'),
            'button_color'     => $validateHex($_POST['button_color'] ?? '', '#233dd2'),
            'button_text_color'=> $validateHex($_POST['button_text_color'] ?? '', '#ffffff'),
            'background_color' => $validateHex($_POST['background_color'] ?? '', '#f4f7f6'),
        ];

        // Check for changes (Logic: If text changed OR any file uploaded)
        $hasFile = (!empty($_FILES['website_logo']['name']) || !empty($_FILES['website_favicon']['name']));
        
        if (!$hasFile) {
            checkNoChangesAndRedirect($newData, $current, null, $webBaseUrl);
        }

        // Handle File Uploads
        $logoName = $current['website_logo'];
        $favName = $current['website_favicon'];
        
        // [NEW] Array to collect all upload errors gracefully
        $uploadErrors = [];

        // Handle Logo Upload
        if (!empty($_FILES['website_logo']['name'])) {
            $upRes = uploadImage($_FILES['website_logo'], $uploadDir);
            if ($upRes['success']) {
                if (!empty($current['website_logo'])) {
                    $realBase = realpath($uploadDir);
                    $realOld = realpath($uploadDir . $current['website_logo']);
                    if ($realOld && strpos($realOld, $realBase) === 0 && file_exists($realOld)) {
                        unlink($realOld);
                    }
                }
                $logoName = $upRes['filename'];
            } else {
                $uploadErrors[] = "Logo Error: " . $upRes['message']; 
            }
        }

        // Handle Favicon Upload
        if (!empty($_FILES['website_favicon']['name'])) {
            $upRes = uploadImage($_FILES['website_favicon'], $uploadDir);
            if ($upRes['success']) {
                if (!empty($current['website_favicon'])) {
                    $realBase = realpath($uploadDir);
                    $realOld = realpath($uploadDir . $current['website_favicon']);
                    if ($realOld && strpos($realOld, $realBase) === 0 && file_exists($realOld)) {
                        unlink($realOld);
                    }
                }
                $favName = $upRes['filename'];
            } else { 
                $uploadErrors[] = "Favicon Error: " . $upRes['message']; 
            }
        }

        // [NEW] Graceful Error Display
        if (!empty($uploadErrors)) {
            $message = implode("<br>", $uploadErrors);
            $msgType = "danger";
        }

        if (empty($uploadErrors)) {
            // Check row existence
            $check = $conn->query("SELECT id FROM $table WHERE id = 1");
            $sql = ($check && $check->num_rows > 0) ? $sqlWebSettingsUpdate : $sqlWebSettingsInsert;
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", 
                $newData['website_name'], $logoName, $favName, 
                $newData['theme_bg_color'], $newData['theme_text_color'], 
                $newData['button_color'], $newData['button_text_color'], 
                $newData['background_color']
            );

            if ($stmt->execute()) {
                $_SESSION['flash_msg'] = "网站设置已更新！";
                $_SESSION['flash_type'] = "success";
                $redirectNeeded = true;
                $logMessage = 'Updated Website Settings';
                $logQuery = $sql;
                
                // Update new data with filenames for audit log
                $newData['website_logo'] = $logoName;
                $newData['website_favicon'] = $favName;
            } else {
                $message = "Save Failed: " . $conn->error;
                $msgType = "danger";
            }
            $stmt->close();
        }
    }

    // --- COMMON POST-PROCESSING ---
    if ($redirectNeeded) {
        if (function_exists('logAudit')) {
            logAudit([
                'page'           => $auditPage,
                'action'         => $logActionCode,
                'action_message' => $logMessage,
                'query'          => $logQuery,
                'query_table'    => $table,
                'user_id'        => $auditUserId,
                'record_id'      => 1,
                'record_name'    => 'Global Settings',
                'old_value'      => $current,
                'new_value'      => isset($newData) ? $newData : null 
            ]);
        }

        if (!headers_sent()) {
            header("Location: " . $webBaseUrl);
        } else {
            echo "<script>window.location.href = '" . $webBaseUrl . "';</script>";
        }
        exit();
    }
}

// ========== RENDER ==========
if ($isEmbeddedWebSetting): ?>
    <div class="web-settings-container" style="max-width: 1000px; margin: 0 auto;">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card meta-card">
                    <div class="card-header meta-card-header">
                        <h4 class="header-title"><i class="fa-solid fa-paintbrush"></i> Website Settings</h4>
                        <p class="header-subtitle">Customize the global appearance and branding of your site.</p>
                    </div>
                    <div class="card-body meta-card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <?php require __DIR__ . '/form.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="<?php echo URL_ASSETS; ?>/js/webSetting.js"></script>

<?php else: ?>
    <?php $pageMetaKey = 'web_settings'; ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <?php require_once BASE_PATH . 'include/header.php'; ?>
        <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/meta.css">
    </head>
    <body>
    <?php require_once BASE_PATH . 'common/menu/header.php'; ?>
    <div class="container mt-4" style="max-width: 1000px;">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card meta-card">
                    <div class="card-header meta-card-header">
                        <h4 class="header-title"><i class="fa-solid fa-paintbrush"></i> Website Settings</h4>
                        <p class="header-subtitle">Customize the global appearance and branding of your site.</p>
                    </div>
                    <div class="card-body meta-card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <?php require __DIR__ . '/form.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/webSetting.js"></script>
    </body>
    </html>
<?php endif; ?>
<?php
// Path: src/pages/webSetting/index.php
require_once dirname(__DIR__, 3) . '/common.php';

// Auth Check
requireLogin();

$currentUrl = '/dashboard.php?view=web_settings';
$perm = hasPagePermission($conn, $currentUrl);

// Get the dynamic page name straight from the DB permission object
$pageName = !empty($perm->page_name) ? $perm->page_name : '系统设置';

checkPermissionError('view', $perm);

$auditPage = 'Web Settings';
$auditUserId = $_SESSION['user_id'] ?? 0;
$table = WEB_SETTINGS;

$message = ""; 
$msgType = "";

// Removed all hardcoded DEFINED QUERIES.
// We will dynamically build the query based on changed fields below!

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

$webBaseUrl = URL_WEB_SETTINGS ?? '?';

// ========== HANDLE POST REQUESTS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Fetch allowed actions dynamically from the Database
    $validActions = [];
    $actionQuery = "SELECT name FROM " . PAGE_ACTION . " WHERE status = 'A'";
    $actionRes = $conn->query($actionQuery);
    if ($actionRes) {
        while ($row = $actionRes->fetch_assoc()) {
            $dbName = strtolower(trim($row['name']));
            $validActions[$dbName] = $dbName;
        }
        $actionRes->free();
    }
    
    // 2. Map Database actions to Variables
    $ACTION_SAVE    = $validActions['save'] ?? 'save'; 
    $ACTION_RESET   = $validActions['reset_defaults'] ?? null;
    $ACTION_RM_LOGO = $validActions['remove_logo'] ?? null;
    $ACTION_RM_FAV  = $validActions['remove_favicon'] ?? null;

    // 3. Get submitted action and Recheck/Validate it against the DB list
    $rawAction = strtolower(trim($_POST['action_type'] ?? $ACTION_SAVE)); 
    if (!isset($validActions[$rawAction])) {
        $_SESSION['flash_msg'] = "操作被拒绝：数据库页面权限 (Page Action) 中未定义此操作 ({$rawAction})。";
        $_SESSION['flash_type'] = "danger";
        
        if (!headers_sent()) {
            header("Location: " . $webBaseUrl);
        } else {
            echo "<script>window.location.href = '" . $webBaseUrl . "';</script>";
        }
        exit();
    }
    
    $actionType = $rawAction;

    // 4. Dynamic Role Permission Check
    if (empty($perm->$actionType)) {
        $_SESSION['flash_msg'] = "权限不足：您的角色没有执行此操作的权限。";
        $_SESSION['flash_type'] = "danger";
        
        if (!headers_sent()) {
            header("Location: " . $webBaseUrl);
        } else {
            echo "<script>window.location.href = '" . $webBaseUrl . "';</script>";
        }
        exit();
    }

    // 5. Setup Action Variables
    $newDataToSave = [];
    $uploadErrors = [];
    $logMessage = '';
    $actionTitle = '';
    $flashType = 'success';
    $logActionCode = 'E';

    // Validate Hex Color helper function
    $validateHex = function($value, $default) {
        if (!is_string($value)) return $default;
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? $value : $default;
    };

    // --- DETERMINE DATA PAYLOAD BASED ON ACTION ---
    
    if ($actionType === $ACTION_RESET && $ACTION_RESET !== null) {
        $newDataToSave = [
            'website_name' => 'Website Name',
            'website_logo' => '',
            'website_favicon' => '',
            'theme_bg_color' => '#ffffff',
            'theme_text_color' => '#333333',
            'button_color' => '#233dd2',
            'button_text_color' => '#ffffff',
            'background_color' => '#f4f7f6'
        ];
        
        // Clean up physical files safely
        if (!empty($current['website_logo'])) @unlink($uploadDir . $current['website_logo']);
        if (!empty($current['website_favicon'])) @unlink($uploadDir . $current['website_favicon']);

        $logMessage = 'Reset Website Settings to Default';
        $actionTitle = "{$pageName}已重置为默认值！";
        $flashType = 'warning';
        $logActionCode = 'D';

    } elseif ($actionType === $ACTION_RM_LOGO && $ACTION_RM_LOGO !== null) {
        if (!empty($current['website_logo'])) @unlink($uploadDir . $current['website_logo']);
        $newDataToSave = ['website_logo' => ''];
        $logMessage = 'Removed Website Logo';
        $actionTitle = "网站 Logo 已移除！";

    } elseif ($actionType === $ACTION_RM_FAV && $ACTION_RM_FAV !== null) {
        if (!empty($current['website_favicon'])) @unlink($uploadDir . $current['website_favicon']);
        $newDataToSave = ['website_favicon' => ''];
        $logMessage = 'Removed Website Favicon';
        $actionTitle = "网站 Favicon 已移除！";

    } else {
        // DEFAULT SAVE ACTION
        $sanitizedWebsiteName = mb_substr(trim($_POST['website_name'] ?? ''), 0, 255, 'UTF-8');
        
        $newDataToSave = [
            'website_name'     => $sanitizedWebsiteName,
            'theme_bg_color'   => $validateHex($_POST['theme_bg_color'] ?? '', '#ffffff'),
            'theme_text_color' => $validateHex($_POST['theme_text_color'] ?? '', '#333333'),
            'button_color'     => $validateHex($_POST['button_color'] ?? '', '#233dd2'),
            'button_text_color'=> $validateHex($_POST['button_text_color'] ?? '', '#ffffff'),
            'background_color' => $validateHex($_POST['background_color'] ?? '', '#f4f7f6'),
        ];

        // Process Uploads
        if (!empty($_FILES['website_logo']['name'])) {
            $upRes = uploadImage($_FILES['website_logo'], $uploadDir);
            if ($upRes['success']) {
                if (!empty($current['website_logo'])) @unlink($uploadDir . $current['website_logo']);
                $newDataToSave['website_logo'] = $upRes['filename'];
            } else {
                $uploadErrors[] = "Logo Error: " . $upRes['message']; 
            }
        }

        if (!empty($_FILES['website_favicon']['name'])) {
            $upRes = uploadImage($_FILES['website_favicon'], $uploadDir);
            if ($upRes['success']) {
                if (!empty($current['website_favicon'])) @unlink($uploadDir . $current['website_favicon']);
                $newDataToSave['website_favicon'] = $upRes['filename'];
            } else { 
                $uploadErrors[] = "Favicon Error: " . $upRes['message']; 
            }
        }

        $logMessage = 'Updated Website Settings';
        $actionTitle = "{$pageName}已更新！";
    }

    // --- DYNAMIC QUERY BUILDER & EXECUTION ---
    
    if (!empty($uploadErrors)) {
        $_SESSION['flash_msg'] = implode("<br>", $uploadErrors);
        $_SESSION['flash_type'] = "danger";
    } else {
        
        $fieldsToUpdate = [];
        $values = [];
        $types = '';

        foreach ($newDataToSave as $col => $val) {
            if (!array_key_exists($col, $current) || (string)$current[$col] !== (string)$val) {
                $fieldsToUpdate[] = "`{$col}` = ?";
                $values[] = $val;
                $types .= 's';
            }
        }

        $check = $conn->query("SELECT id FROM $table WHERE id = 1");
        $isEditMode = ($check && $check->num_rows > 0);

        if ($isEditMode && empty($fieldsToUpdate)) {

        $_SESSION['flash_msg'] = "没有修改，无需保存";
            $_SESSION['flash_type'] = "warning";
        } else {
            $success = false;
            $executedQuery = "";

            if ($isEditMode) {
                // Generate Dynamic UPDATE Query
                $sql = "UPDATE {$table} SET " . implode(', ', $fieldsToUpdate) . " WHERE id = 1";
                $executedQuery = $sql;
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($types, ...$values);
                    $success = $stmt->execute();
                    $stmt->close();
                }
            } else {
                // Generate Dynamic Initial INSERT Query
                $insertData = array_merge($current, $newDataToSave); // Fill missing fields with defaults
                $cols = array_keys($insertData);
                $placeholders = array_fill(0, count($cols), '?');
                
                $sql = "INSERT INTO {$table} (" . implode(', ', $cols) . ", id) VALUES (" . implode(', ', $placeholders) . ", 1)";
                $executedQuery = $sql;
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $insertVals = array_values($insertData);
                    $insertTypes = str_repeat('s', count($insertVals));
                    $stmt->bind_param($insertTypes, ...$insertVals);
                    $success = $stmt->execute();
                    $stmt->close();
                }
            }

            // --- FINALIZATION ---
            if ($success) {
                $_SESSION['flash_msg'] = $actionTitle;
                $_SESSION['flash_type'] = $flashType;

                if (function_exists('logAudit')) {
                    $auditNewData = array_merge($current, $newDataToSave);
                    logAudit([
                        'page'           => $auditPage,
                        'action'         => $logActionCode,
                        'action_message' => $logMessage,
                        'query'          => $executedQuery,
                        'query_table'    => $table,
                        'user_id'        => $auditUserId,
                        'record_id'      => 1,
                        'record_name'    => 'Global Settings',
                        'old_value'      => $current,
                        'new_value'      => $auditNewData 
                    ]);
                }
            } else {
                $_SESSION['flash_msg'] = "保存失败: " . $conn->error;
                $_SESSION['flash_type'] = "danger";
            }
        }
    }

    if (!headers_sent()) {
        header("Location: " . $webBaseUrl);
    } else {
        echo "<script>window.location.href = '" . $webBaseUrl . "';</script>";
    }
    exit();
}

// ========== RENDER ==========
?>

<link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/webSetting.css">
<div class="web-settings-container mt-4" style="max-width: 1000px; margin: 0 auto;">
    
    <div class="mb-3">
        <?php echo generateBreadcrumb($conn, $currentUrl); ?>
    </div>

    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-3 px-4">
                    <h4 class="header-title m-0 fw-bold"><i class="fa-solid fa-paintbrush me-2"></i> <?php echo htmlspecialchars($pageName); ?></h4>
                    <p class="header-subtitle mt-1 mb-0 text-muted">Customize the global appearance and branding of your site.</p>
                </div>
                <div class="card-body p-4">
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($msgType); ?> alert-dismissible fade show">
                            <i class="fa-solid fa-circle-exclamation me-2"></i>
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
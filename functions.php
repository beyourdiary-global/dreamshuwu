<?php
/**
 * functions.php
 * Reusable logic for validation and security.
 */

if (!function_exists('safeJsonEncode')) {
    /**
     * Encode data as JSON even when the json extension is disabled.
     */
    function safeJsonEncode($data) {
        if (function_exists('json_encode')) {
            $flags = defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0;
            $encoded = json_encode($data, $flags);
            if ($encoded !== false) {
                return $encoded;
            }
        }

        if (is_array($data)) {
            if ($data === []) {
                return '[]';
            }
            $isAssoc = array_keys($data) !== range(0, count($data) - 1);
            $items = [];
            foreach ($data as $key => $value) {
                $encodedValue = safeJsonEncode($value);
                if ($isAssoc) {
                    $items[] = '"' . addslashes((string)$key) . '":' . $encodedValue;
                } else {
                    $items[] = $encodedValue;
                }
            }
            return $isAssoc ? '{' . implode(',', $items) . '}' : '[' . implode(',', $items) . ']';
        }

        if (is_string($data)) {
            $search = defined('SAFE_JSON_ESCAPE_SEARCH') ? SAFE_JSON_ESCAPE_SEARCH : ["\\", "\"", "\n", "\r", "\t", "\f", "\b"];
            $replace = defined('SAFE_JSON_ESCAPE_REPLACE') ? SAFE_JSON_ESCAPE_REPLACE : ["\\\\", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b"];
            $escaped = str_replace($search, $replace, $data);
            return '"' . $escaped . '"';
        }

        if (is_bool($data)) {
            return $data ? 'true' : 'false';
        }

        if (is_null($data)) {
            return 'null';
        }

        return (string)$data;
    }
}

/**
 * Validates email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validates password strength
 */
function isStrongPassword($password) {
    // Minimum 8 chars, 1 Upper, 1 Lower, 1 Number, 1 Special
    $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{' . MIN_PWD_LENGTH . ',}$/';
    return preg_match($regex, $password);
}

/**
 * Calculates age and checks if it meets the requirement
 */
function checkAgeRequirement($birthday, $minAge = MIN_AGE_REQUIREMENT) {
    if (empty($birthday)) return false;
    
    try {
        $today = new DateTime();
        $birthDate = new DateTime($birthday);
        
        if ($birthDate > $today) {
            return "future_date"; 
        }
        
        $age = $today->diff($birthDate)->y;
        return ($age >= $minAge);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Security: Prevents Open Redirect Vulnerabilities
 */
function isSafeRedirect($url) {
    if (empty($url)) return false;
    
    // Decode characters like %3a to check for hidden "https://"
    $url = urldecode($url); 
    
    // 1. Block absolute URLs (containing ://)
    // 2. Block protocol-relative URLs (starting with //)
    if (strpos($url, '://') !== false || strpos($url, '//') === 0) {
        return false;
    }
    
    return true;
}

/**
 * Centralized User Status Check
 * Use this to verify if a user is allowed to log in.
 */
function isUserActive($user) {
    if (!$user) return false;

    $invalidStatuses = ['disabled', 'inactive', 'blocked', 'suspended', '0'];

    // Check all common status field variations
    if (isset($user['is_active']) && (int)$user['is_active'] === 0) return false;
    if (isset($user['disabled']) && (int)$user['disabled'] === 1) return false;
    
    if (isset($user['status']) && in_array(strtolower((string)$user['status']), $invalidStatuses, true)) return false;
    if (isset($user['account_status']) && in_array(strtolower((string)$user['account_status']), $invalidStatuses, true)) return false;

    return true;
}

/**
 * Simple Helper to format database dates for the UI
 */
function formatDate($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

/**
 * Send password reset email
 * Returns true if sent, false otherwise
 */
function sendPasswordResetEmail($email, $resetLink) {
    $subject = "【" . WEBSITE_NAME . "】重置您的密码";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">" . "\r\n";

    $emailContent = "
    <html>
    <head>
        <title>重置密码</title>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #233dd2; color: white; padding: 20px; text-align: center; border-radius: 5px; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { font-size: 12px; color: #999; margin-top: 20px; }
            .footer p { margin: 5px 0; }
            a.btn { display: inline-block; background: #233dd2; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>重置您的密码</h2>
            </div>
            <div class='content'>
                <h3>您好，</h3>
                <p>您最近提交了密码重置请求。请点击下方按钮来重置您的密码：</p>
                <p><a href='" . htmlspecialchars($resetLink) . "' class='btn'>重置密码</a></p>
                <p>或者复制此链接到浏览器：<br><small>" . htmlspecialchars($resetLink) . "</small></p>
                <p style='color: #999; font-size: 14px;'><strong>重要：</strong>此链接有效期为 30 分钟。如果您没有提出此请求，请忽略此邮件。</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " " . WEBSITE_NAME . ". 版权所有。</p>
            </div>
        </div>
    </body>
    </html>";

    return @mail($email, $subject, $emailContent, $headers);
}

/**
 * [NEW] Helper: Check for Changes and Redirect
 * Automatically checks if new form data matches old DB data.
 * If NO changes are found (and no file uploaded), sets a flash message and redirects.
 *
 * @param array  $newData       New data from form (key => value)
 * @param array  $oldData       Old data from DB (key => value)
 * @param string $fileInputName (Optional) The name attribute of a file input to check
 * @param string $redirectUrl   (Optional) Custom redirect URL. Defaults to current page.
 */
function checkNoChangesAndRedirect($newData, $oldData, $fileInputName = null, $redirectUrl = null) {
    // 1. Check for File Upload
    if ($fileInputName && isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['size'] > 0) {
        return; // File detected, proceed to save
    }

    // 2. Check for Text Changes
    foreach ($newData as $key => $newVal) {
        // Skip keys that don't exist in old data (or you can choose to treat them as changes)
        if (!array_key_exists($key, $oldData)) continue;

        // Use loose comparison (!=) to handle string vs int (e.g. "1" vs 1), and null vs ""
        if ($newVal != $oldData[$key]) {
            return; // Change detected, proceed to save
        }
    }

    // 3. No Changes Found -> Redirect
    if (session_status() === PHP_SESSION_NONE) session_start();

    $_SESSION['flash_msg'] = "没有修改，无需保存"; 
    $_SESSION['flash_type'] = "warning";

    $url = $redirectUrl ?? $_SERVER['REQUEST_URI'];

    if (!headers_sent()) {
        header("Location: " . $url);
    } else {
        echo "<script>window.location.href = '" . $url . "';</script>";
    }
    exit();
}

/**
 * Logs a database operation to the audit_log table.
 */
if (!function_exists('encodeAuditValue')) {
    function encodeAuditValue($value) {
        if ($value === null) return null;
        
        // If it's a string, trim it. If empty/null string, return null.
        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }
        
        // Use the global safeJsonEncode for arrays/objects
        return safeJsonEncode($value);
    }
}

// [UNIVERSAL FIX] Fetch using Query + Explicit Free
// This prevents "Commands out of sync" errors that block the INSERT
if (!function_exists('fetchAuditRow')) {
    function fetchAuditRow($conn, $table, $recordId) {
        if (empty($table) || empty($recordId)) return null;
        
        // Safety: Force ID to be an integer
        $safeId = (int) $recordId;
        
        // Direct Query
        $sql = "SELECT * FROM {$table} WHERE id = $safeId LIMIT 1";
        $result = $conn->query($sql);
        
        if ($result) {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $result->free(); // [CRITICAL] Unlock DB for the next query
                return $row;
            }
            $result->free(); // [CRITICAL] Unlock even if empty
        }
        return null;
    }
}

// [CRITICAL FIX] The Logger Logic
function logAudit($params) {
    global $conn;

    $page        = $params['page'] ?? 'Unknown';
    $action      = $params['action'] ?? 'V'; // Default to View
    $message     = $params['action_message'] ?? '';
    $query       = $params['query'] ?? '';
    $table       = $params['query_table'] ?? '';
    $userId      = $params['user_id'] ?? 0;
    
    $recordId    = $params['record_id'] ?? null;
    $recordName  = $params['record_name'] ?? null;
    $oldData     = $params['old_value'] ?? null;
    $newData     = $params['new_value'] ?? null;
    $changes     = null;

    // A. Auto-Detect ID for New Inserts
    if (empty($recordId) && ($action === 'A' || $action === 'ADD') && isset($conn->insert_id) && $conn->insert_id > 0) {
        $recordId = $conn->insert_id;
    }

    // B. Auto-Fetch Missing Data
    // Note: fetchAuditRow now properly frees memory, so this won't block the INSERT below
    if (($action === 'A' || $action === 'E') && empty($newData) && !empty($recordId) && !empty($table)) {
        $newData = fetchAuditRow($conn, $table, (int) $recordId);
    }
    if (($action === 'E' || $action === 'D') && empty($oldData) && !empty($recordId) && !empty($table)) {
        $oldData = fetchAuditRow($conn, $table, (int) $recordId);
    }

    // C. Fallback to ensure NO NULLS in DB
    if (empty($oldData) && ($action === 'E' || $action === 'D') && ($recordId || $recordName)) {
        $oldData = ['id' => $recordId, 'name' => $recordName, 'note' => 'Data fetch skipped'];
    }
    if (empty($newData) && ($action === 'A' || $action === 'E') && ($recordId || $recordName)) {
        $newData = ['id' => $recordId, 'name' => $recordName, 'note' => 'Data fetch skipped'];
    }

    // D. Calculate Changes
    if ($action === 'E' && is_array($oldData) && is_array($newData)) {
        $changes = [];
        foreach ($newData as $key => $value) {
            if (array_key_exists($key, $oldData) && $oldData[$key] != $value) {
                $changes[$key] = ['from' => $oldData[$key], 'to' => $value];
            }
        }
        if (empty($changes)) $changes = null;
    }

    // E. Save
    $jsonOld     = encodeAuditValue($oldData);
    $jsonNew     = encodeAuditValue($newData);
    $jsonChanges = encodeAuditValue($changes);

    $sql = "INSERT INTO " . AUDIT_LOG . " 
            (page, action, action_message, query, query_table, 
             old_value, new_value, changes, user_id, 
             created_at, updated_at, created_by, updated_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param(
            "ssssssssiii", 
            $page, $action, $message, $query, $table, 
            $jsonOld, $jsonNew, $jsonChanges, $userId, $userId, $userId
        );
        if (!$stmt->execute()) {
             // Log error to PHP error log if insert fails
             error_log("Audit Insert Failed: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Audit Prepare Failed: " . $conn->error);
    }
}

/**
 * Secure Image Upload Function
 * Handles avatar uploads safely.
 * * @param array $file The $_FILES['input_name'] array
 * @param string $targetDir The directory to save the file
 * @return array ['success' => bool, 'message' => string, 'filename' => string]
 */
function uploadImage($file, $targetDir) {
    // 1. Check for basic upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => '文件大小超过服务器限制',
            UPLOAD_ERR_FORM_SIZE  => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL    => '文件上传不完整',
            UPLOAD_ERR_NO_FILE    => '没有选择文件',
            UPLOAD_ERR_NO_TMP_DIR => '临时文件夹丢失',
            UPLOAD_ERR_CANT_WRITE => '无法写入文件',
            UPLOAD_ERR_EXTENSION  => '文件上传被扩展程序停止',
        ];
        $msg = $errorMessages[$file['error']] ?? '未知上传错误';
        return ['success' => false, 'message' => $msg];
    }

    // 2. Validate Image Extension
    $allowedTypes = ['jpg', 'jpeg', 'png'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => '只允许上传 JPG 和 PNG 格式的图片'];
    }

    // 3. Validate Mime Type (Security Check)
    // If finfo_open doesn't exist (some shared hosts), we skip this or use a fallback
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = ['image/jpeg', 'image/png'];
        if (!in_array($mime, $allowedMimes)) {
            return ['success' => false, 'message' => '文件内容无效 (MIME mismatch)'];
        }
    }

    // 4. Generate Safe Filename
    $newFilename = uniqid('av_') . '.' . $fileExt;
    
    // Ensure the target directory ends with a slash
    $targetDir = rtrim($targetDir, '/') . '/';
    $targetPath = $targetDir . $newFilename;

    // 5. Create Directory if missing
    if (!is_dir($targetDir)) {
        // Try to create directory with permissions
        if (!mkdir($targetDir, 0755, true)) {
            return ['success' => false, 'message' => '无法创建上传目录 (权限不足)'];
        }
    }

    // 6. Move File
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $newFilename];
    }

    return ['success' => false, 'message' => '无法保存文件 (Move failed)'];
}

/**
 * [NEW] Helper: Fetch SEO Meta Settings
 * Supports hyphenated table names using backticks.
 */
function getMetaSettings($conn, $type, $id) {
    if (!$conn) return null;

    $stmt = $conn->prepare("SELECT meta_title, meta_description, og_title, og_description, og_url FROM " . META_SETTINGS . " WHERE page_type = ? AND page_id = ? LIMIT 1");
    if (!$stmt) return null;

    $stmt->bind_param("si", $type, $id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $metaTitle = null;
    $metaDesc = null;
    $ogTitle = null;
    $ogDesc = null;
    $ogUrl = null;

    $stmt->bind_result($metaTitle, $metaDesc, $ogTitle, $ogDesc, $ogUrl);
    $stmt->fetch();
    $stmt->close();

    return [
        'meta_title' => $metaTitle,
        'meta_description' => $metaDesc,
        'og_title' => $ogTitle,
        'og_description' => $ogDesc,
        'og_url' => $ogUrl,
    ];
}

/**
 * [NEW] Helper: Fetch Per-Page SEO Meta Settings
 * Queries the meta_settings_page table by page_key.
 * Returns null if no custom meta is set for the page.
 */
function getPageMetaSettings($conn, $pageKey) {
    if (!$conn || empty($pageKey)) return null;

    $stmt = $conn->prepare(
        "SELECT meta_title, meta_description, og_title, og_description, og_url FROM " . META_SETTINGS_PAGE . " WHERE page_key = ? LIMIT 1"
    );
    if (!$stmt) return null;

    $stmt->bind_param("s", $pageKey);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $metaTitle = null;
    $metaDesc = null;
    $ogTitle = null;
    $ogDesc = null;
    $ogUrl = null;

    $stmt->bind_result($metaTitle, $metaDesc, $ogTitle, $ogDesc, $ogUrl);
    $stmt->fetch();
    $stmt->close();

    // Only return if at least one field is set
    if (empty($metaTitle) && empty($metaDesc) && empty($ogTitle) && empty($ogDesc) && empty($ogUrl)) {
        return null;
    }

    return [
        'meta_title' => $metaTitle,
        'meta_description' => $metaDesc,
        'og_title' => $ogTitle,
        'og_description' => $ogDesc,
        'og_url' => $ogUrl,
    ];
}

?>

<?php
/**
 * functions.php
 * Reusable logic for validation and security.
 */

/**
 * Encode data as JSON even when the json extension is disabled.
 */
function safeJsonEncode($data) {
        if (function_exists('json_encode')) {
            $flags = defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0;
            if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
                $flags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
            }
            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
            }
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

/**
 * Decode a JSON string even when the json extension is disabled.
 * Returns the decoded value, or null on failure.
 * Sets $success by reference so the caller knows if decoding worked.
 */
function safeJsonDecode($json, $assoc = true, &$success = null) {
        $success = false;
        if (!is_string($json)) {
            $success = true;
            return $json;
        }

        // 1. Use native json_decode if available
        if (function_exists('json_decode')) {
            $result = json_decode($json, $assoc);
            if (function_exists('json_last_error')) {
                $success = (json_last_error() === JSON_ERROR_NONE);
            } else {
                $success = ($result !== null || trim($json) === 'null');
            }
            return $result;
        }

        // 2. Fallback: handle simple literals without the json extension
        $trimmed = trim($json);
        if ($trimmed === 'null')  { $success = true; return null; }
        if ($trimmed === 'true')  { $success = true; return true; }
        if ($trimmed === 'false') { $success = true; return false; }
        if (is_numeric($trimmed)) { $success = true; return $trimmed + 0; }

        // 3. Quoted string
        if (strlen($trimmed) >= 2 && $trimmed[0] === '"' && substr($trimmed, -1) === '"') {
            $success = true;
            return stripslashes(substr($trimmed, 1, -1));
        }

        // 4. Object/Array: cannot parse without json_decode → return null, success=false
        //    The caller will fall back to using the raw string.
        $success = false;
        return null;
}

/**
     */
function sanitizeUtf8($value) {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = sanitizeUtf8($item);
            }
            return $sanitized;
        }

        if (is_object($value)) {
            $obj = clone $value;
            foreach ($obj as $k => $v) {
                $obj->$k = sanitizeUtf8($v);
            }
            return $obj;
        }

        if (!is_string($value)) {
            return $value;
        }

        if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding')) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
            return $value;
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                return $converted;
            }
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
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
 * Get website name from database settings
 * Falls back to WEBSITE_NAME constant if not found
 */
function getWebsiteName() {
    global $conn;
    
    if (!$conn) {
        return defined('WEBSITE_NAME') ? WEBSITE_NAME : 'StarAdmin';
    }
    
    // Query the correct column name from web_settings table
    $stmt = $conn->prepare("SELECT website_name FROM " . WEB_SETTINGS . " WHERE id = 1 LIMIT 1");
    if (!$stmt) {
        return defined('WEBSITE_NAME') ? WEBSITE_NAME : 'StarAdmin';
    }
    
    $stmt->execute();
    $stmt->bind_result($siteNameValue);
    
    if ($stmt->fetch()) {
        $stmt->close();
        return $siteNameValue ?: (defined('WEBSITE_NAME') ? WEBSITE_NAME : 'StarAdmin');
    }
    $stmt->close();
    
    return defined('WEBSITE_NAME') ? WEBSITE_NAME : 'StarAdmin';
}

/**
 * Send password reset email
 * Returns true if sent, false otherwise
 */
function sendPasswordResetEmail($email, $resetLink) {
    $websiteName = getWebsiteName();
    $subject = "重置您的密码 - " . $websiteName;
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">" . "\r\n";

    $emailContent = "
    <html>
    <head>
        <title>重置密码 - " . htmlspecialchars($websiteName) . "</title>
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
                <h2>" . htmlspecialchars($websiteName) . "</h2>
                <p>重置您的密码</p>
            </div>
            <div class='content'>
                <h3>您好，</h3>
                <p>您最近提交了密码重置请求。请点击下方按钮来重置您的密码：</p>
                <p><a href='" . htmlspecialchars($resetLink) . "' class='btn'>重置密码</a></p>
                <p>或者复制此链接到浏览器：<br><small>" . htmlspecialchars($resetLink) . "</small></p>
                <p style='color: #999; font-size: 14px;'><strong>重要：</strong>此链接有效期为 30 分钟。如果您没有提出此请求，请忽略此邮件。</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " " . htmlspecialchars($websiteName) . ". 版权所有。</p>
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
 * Encode audit value for JSON storage.
 */
function encodeAuditValue($value) {
        if ($value === null) return null;
        
        // If it's a string, trim it. If empty/null string, return null.
        if (is_string($value)) {
            $trimmed = trim(sanitizeUtf8($value));
            return $trimmed === '' ? null : $trimmed;
        }
        
        // Use the global safeJsonEncode for arrays/objects
        return safeJsonEncode(sanitizeUtf8($value));
}

/**
 * Fetch audit row from database using direct query.
 * This prevents "Commands out of sync" errors that block the INSERT
 */
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

// [CRITICAL FIX] The Logger Logic
function logAudit($params) {
    global $conn;

    $page        = $params['page'] ?? 'Unknown';
    $action      = $params['action'] ?? 'V'; // Default to View
    $message     = sanitizeUtf8($params['action_message'] ?? '');
    $query       = sanitizeUtf8($params['query'] ?? '');
    $table       = sanitizeUtf8($params['query_table'] ?? '');
    $userId      = $params['user_id'] ?? 0;
    
    $recordId    = $params['record_id'] ?? null;
    $recordName  = sanitizeUtf8($params['record_name'] ?? null);
    $oldData     = sanitizeUtf8($params['old_value'] ?? null);
    $newData     = sanitizeUtf8($params['new_value'] ?? null);
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
 * Helper: Fetch SEO Meta Settings
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
 * Helper: Fetch Per-Page SEO Meta Settings
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

/**
 * [NEW] Helper: Fetch Website Settings
 * Returns the single configuration row (id=1).
 */
function getWebSettings($conn) {
    if (!$conn) return null;

    // Use prepared statement style
    $stmt = $conn->prepare("SELECT website_name, website_logo, website_favicon, theme_bg_color, theme_text_color, button_color, button_text_color, background_color FROM " . WEB_SETTINGS . " WHERE id = 1 LIMIT 1");
    if (!$stmt) return null;

    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        // Return safe defaults if DB is empty
        return [
            'website_name' => 'Website Name',
            'website_logo' => '',
            'website_favicon' => '',
            'theme_bg_color' => '#ffffff',
            'theme_text_color' => '#333333',
            'button_color' => '#233dd2',
            'button_text_color' => '#ffffff',
            'background_color' => '#f4f7f6'
        ];
    }

    // Define variables to bind results to
    $websiteName = null;
    $websiteLogo = null;
    $websiteFavicon = null;
    $themeBgColor = null;
    $themeTextColor = null;
    $buttonColor = null;
    $buttonTextColor = null;
    $backgroundColor = null;

    $stmt->bind_result(
        $websiteName, 
        $websiteLogo, 
        $websiteFavicon, 
        $themeBgColor, 
        $themeTextColor, 
        $buttonColor, 
        $buttonTextColor, 
        $backgroundColor
    );
    
    $stmt->fetch();
    $stmt->close();

    return [
        'website_name' => $websiteName,
        'website_logo' => $websiteLogo,
        'website_favicon' => $websiteFavicon,
        'theme_bg_color' => $themeBgColor,
        'theme_text_color' => $themeTextColor,
        'button_color' => $buttonColor,
        'button_text_color' => $buttonTextColor,
        'background_color' => $backgroundColor
    ];
}

/**
 * Normalize user-group keys for permission checks.
 * Example: "Super Admin" => "super_admin"
 */
function normalizeGroupKey($groupValue) {
    $text = strtolower(trim((string)$groupValue));
    if ($text === '') return '';
    return str_replace([' ', '-', '.'], '_', $text);
}

/**
 * Fetch a single page_action row by id.
 */
function fetchPageActionRowById($conn, $table, $id) {
    $stmt = $conn->prepare("SELECT id, name, status, created_at, updated_at, created_by, updated_by FROM {$table} WHERE id = ? LIMIT 1");
    if (!$stmt) return null;

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $rowId = null; 
    $rowName = null; 
    $rowStatus = null; 
    $rowCreatedAt = null; 
    $rowUpdatedAt = null; 
    $rowCreatedBy = null; 
    $rowUpdatedBy = null;

    $stmt->bind_result($rowId, $rowName, $rowStatus, $rowCreatedAt, $rowUpdatedAt, $rowCreatedBy, $rowUpdatedBy);
    $stmt->fetch();
    $stmt->close();

    return [
        'id' => $rowId,
        'name' => $rowName,
        'status' => $rowStatus,
        'created_at' => $rowCreatedAt,
        'updated_at' => $rowUpdatedAt,
        'created_by' => $rowCreatedBy,
        'updated_by' => $rowUpdatedBy,
    ];
}

/**
 * Fetch a single page_information_list row by id.
 */
function fetchPageInfoRowById($conn, $table, $id) {
    $stmt = $conn->prepare("SELECT id, name_en, name_cn, description, public_url, file_path, status, created_at, updated_at, created_by, updated_by FROM {$table} WHERE id = ? LIMIT 1");
    if (!$stmt) return null;

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $rowId = null;
    $rowNameEn = null;
    $rowNameCn = null;
    $rowDesc = null;
    $rowUrl = null;
    $rowPath = null;
    $rowStatus = null;
    $rowCreatedAt = null;
    $rowUpdatedAt = null;
    $rowCreatedBy = null;
    $rowUpdatedBy = null;

    $stmt->bind_result(
        $rowId, 
        $rowNameEn, 
        $rowNameCn, 
        $rowDesc, 
        $rowUrl, 
        $rowPath, 
        $rowStatus, 
        $rowCreatedAt, 
        $rowUpdatedAt, 
        $rowCreatedBy, 
        $rowUpdatedBy
    );
    
    $stmt->fetch();
    $stmt->close();

    return [
        'id' => $rowId,
        'name_en' => $rowNameEn,
        'name_cn' => $rowNameCn,
        'description' => $rowDesc,
        'public_url' => $rowUrl,
        'file_path' => $rowPath,
        'status' => $rowStatus,
        'created_at' => $rowCreatedAt,
        'updated_at' => $rowUpdatedAt,
        'created_by' => $rowCreatedBy,
        'updated_by' => $rowUpdatedBy,
    ];
}

/**
 * [NEW] Fetch a single user_role row by id.
 */
function fetchUserRoleById($conn, $id) {
        $stmt = $conn->prepare("SELECT id, name_en, name_cn, description, status, created_at, updated_at, created_by, updated_by FROM " . USER_ROLE . " WHERE id = ? LIMIT 1");
        if (!$stmt) return null;

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $rowId = null;
        $rowNameEn = null;
        $rowNameCn = null;
        $rowDesc = null;
        $rowStatus = null;
        $rowCreatedAt = null;
        $rowUpdatedAt = null;
        $rowCreatedBy = null;
        $rowUpdatedBy = null;

        $stmt->bind_result($rowId, $rowNameEn, $rowNameCn, $rowDesc, $rowStatus, $rowCreatedAt, $rowUpdatedAt, $rowCreatedBy, $rowUpdatedBy);
        $stmt->fetch();
        $stmt->close();

        return [
            'id' => $rowId,
            'name_en' => $rowNameEn,
            'name_cn' => $rowNameCn,
            'description' => $rowDesc,
            'status' => $rowStatus,
            'created_at' => $rowCreatedAt,
            'updated_at' => $rowUpdatedAt,
            'created_by' => $rowCreatedBy,
            'updated_by' => $rowUpdatedBy,
        ];
}

/**
 * [NEW] Fetch all (page_id, action_id) pairs assigned to a specific user role.
 * @param int $roleId The user_role.id
 * @return array Array of ['page_id' => int, 'action_id' => int]
 */
function fetchRolePermissions($conn, $roleId) {
    $perms = [];
    $stmt = $conn->prepare("SELECT page_id, action_id FROM " . USER_ROLE_PERMISSION . " WHERE user_role_id = ? ORDER BY page_id, action_id");
    if (!$stmt) return $perms;

    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    
    $pageId = null;
    $actionId = null;
    $stmt->bind_result($pageId, $actionId);

    while ($stmt->fetch()) {
        $perms[] = [
            'page_id' => (int)$pageId,
            'action_id' => (int)$actionId,
        ];
    }
    $stmt->close();

    return $perms;
}

/**
 * [NEW] Check if user_role name (EN or CN) already exists (excluding a specific ID).
 * @param string $nameCn Chinese name
 * @param string $nameEn English name
 * @param int $excludeId Optional. If > 0, exclude this role from the check.
 * @return bool True if duplicate found, false otherwise.
 */
function checkRoleNameDuplicate($conn, $nameCn, $nameEn, $excludeId = 0) {
        $sql = "SELECT id FROM " . USER_ROLE . " WHERE (name_en = ? OR name_cn = ?) AND status = 'A'";
        $params = [$nameEn, $nameCn];
        $types = 'ss';

        if ($excludeId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }

    $sql .= " LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    if ($excludeId > 0) {
        $stmt->bind_param($types, $nameEn, $nameCn, $excludeId);
    } else {
        $stmt->bind_param($types, $nameEn, $nameCn);
    }

    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

/**
 * Get the default user role ID for new registrations.
 * Looks for a role named 'Member' or 'User'.
 */
function getDefaultUserRoleId($conn) {
    if (!$conn) return null;

    // 1. Prepare the SQL statement with placeholders
    $sql = "SELECT id FROM " . USER_ROLE . " 
            WHERE (name_en IN (?, ?) OR name_cn IN (?, ?)) 
            AND status = ? 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $name_en1 = 'Member';
        $name_en2 = 'User';
        $name_cn1 = '普通用户';
        $name_cn2 = '会员';
        $status = 'A';

        // 2. Bind the variables to the placeholders
        $stmt->bind_param("sssss", $name_en1, $name_en2, $name_cn1, $name_cn2, $status);

        // 3. Execute and get the result
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return (int)$row['id'];
        }

        $stmt->close();
    }

    return null;
}

/**
 * This will be used for Administrator accounts created by the Super Admin. 
 * It looks for a role named 'Administrator' or 'Super Administrator'.
 * 
 */
function getAdministratorRoleId($conn) {
    if (!$conn) return null;

    // 1. Prepare the SQL statement with placeholders
    $sql = "SELECT id FROM " . USER_ROLE . " 
            WHERE (name_en IN (?, ?) OR name_cn IN (?, ?)) 
            AND status = ? 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $name_en1 = 'Super Administrator';
        $name_en2 = 'Administrator';
        $name_cn1 = '超级管理员';
        $name_cn2 = '管理员';
        $status = 'A';

        // 2. Bind the variables to the placeholders
        $stmt->bind_param("sssss", $name_en1, $name_en2, $name_cn1, $name_cn2, $status);

        // 3. Execute and get the result
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return (int)$row['id'];
        }

        $stmt->close();
    }

    return null;
}

/**
 * Fully Dynamic Gatekeeper (Optimized: No JOINs)
 * 1. Fetches all possible actions from PAGE_ACTION.
 * 2. Fetches assigned actions from USER_ROLE_PERMISSION.
 * 3. Fetches enabled actions from ACTION_MASTER.
 * 4. Intersects them in PHP and returns a dynamic permission object.
 */
function hasPagePermission($conn, $pageUrl) {
    $perm = new stdClass();

    if (!$conn) return $perm;

    // 1. Initialize the object: Fetch ALL possible actions and default them to false
    // This ensures properties like $perm->view or $perm->delete always exist to prevent PHP warnings
    $allActionsRes = $conn->query("SELECT id, name FROM " . PAGE_ACTION . " WHERE status = 'A'");
    $actionMap = []; // Maps ID to Name (e.g., [1 => 'view', 2 => 'add'])
    if ($allActionsRes) {
        while ($row = $allActionsRes->fetch_assoc()) {
            $key = strtolower($row['name']);
            $perm->$key = false; 
            $actionMap[(int)$row['id']] = $key;
        }
        $allActionsRes->free();
    }

    if (empty($pageUrl)) return $perm;

    // 2. Get Role ID from Session
    $roleId = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;
    if ($roleId <= 0) return $perm;

    // 3. Get Page ID
    $pageId = 0;
    $stmt = $conn->prepare("SELECT id FROM " . PAGE_INFO_LIST . " WHERE public_url = ? AND status = 'A' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $pageUrl);
        $stmt->execute();
        $stmt->bind_result($pageId);
        $stmt->fetch();
        $stmt->close();
    }
    if (empty($pageId)) return $perm;

    // 4. Get User Role Permissions (The User's Keys)
    $roleActionIds = [];
    $stmt = $conn->prepare("SELECT action_id FROM " . USER_ROLE_PERMISSION . " WHERE user_role_id = ? AND page_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $roleId, $pageId);
        $stmt->execute();
        $aId = null;
        $stmt->bind_result($aId);
        while ($stmt->fetch()) {
            $roleActionIds[] = (int)$aId;
        }
        $stmt->close();
    }
    
    // Fast Failure: If role has zero permissions, return the default false object immediately
    if (empty($roleActionIds)) return $perm;

    // 5. Get Page Master Permissions (The Page's Locks)
    $masterActionIds = [];
    $stmt = $conn->prepare("SELECT action_id FROM " . ACTION_MASTER . " WHERE page_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $pageId);
        $stmt->execute();
        $mId = null;
        $stmt->bind_result($mId);
        while ($stmt->fetch()) {
            $masterActionIds[] = (int)$mId;
        }
        $stmt->close();
    }

    // 6. Calculate the Intersection in PHP
    // This strictly enforces that the ID must exist in BOTH tables.
    $validActionIds = array_intersect($roleActionIds, $masterActionIds);

    // 7. Update the dynamic permission object
    foreach ($validActionIds as $validId) {
        if (isset($actionMap[$validId])) {
            $keyName = $actionMap[$validId];
            $perm->$keyName = true;
        }
    }

    return $perm;
}

function renderTableActions($htmlString) {
    $minHeight = '32px'; // Matches standard btn-sm height
    if (!empty($htmlString)) {
        return '<div style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; min-height: '.$minHeight.';">' . $htmlString . '</div>';
    }
    // Returns a layout-stable placeholder if no permissions exist
    return '<div style="display: block; height: '.$minHeight.'; width: 100%;">&nbsp;</div>';
}

// Unified permission error handler (with forced redirection to dashboard home)
// @param string $actionType Operation type ('view', 'add', 'edit', 'delete')
// @param object $perm Permission object
// @param string $moduleName Module name for the error message
// @param bool $redirect Whether to redirect directly when access is denied
function checkPermissionError($actionType, $perm, $moduleName = '数据', $redirect = true) {
    
    // Force absolute path to the dashboard home page.
    $dashboardUrl = '/dashboard.php'; 
    $errorMessage = null;

    // Check if the permission object exists
    if (empty($perm)) {
        $errorMessage = "系统错误：无法获取 {$moduleName} 的权限信息。";
    } else {
        // Check specific permission based on the action type
        switch ($actionType) {
            case 'view':
                if (empty($perm->view)) $errorMessage = "权限不足：您没有访问 {$moduleName} 的权限。";
                break;
            case 'add':
                if (empty($perm->add)) $errorMessage = "权限不足：您没有新增 {$moduleName} 的权限。";
                break;
            case 'edit':
                if (empty($perm->edit)) $errorMessage = "权限不足：您没有编辑 {$moduleName} 的权限。";
                break;
            case 'delete':
                if (empty($perm->delete)) $errorMessage = "权限不足：您没有删除 {$moduleName} 的权限。";
                break;
            default:
                $errorMessage = "权限不足：未知的操作类型。";
        }
    }

    // If an error occurred and redirection is enabled
    if ($errorMessage && $redirect) {
        
        // If it's an AJAX request (e.g., DataTables), return JSON
        if (isset($_GET['mode']) && $_GET['mode'] === 'data') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit();
        }

        // [CRITICAL FIX]: Prevent Infinite Redirect Loop!
        // Check if we are currently on the Dashboard Home
        $currentView = $_GET['view'] ?? '';
        $isDashboardHome = (strpos($_SERVER['SCRIPT_NAME'], 'dashboard.php') !== false) && empty($currentView);

        // If the user lacks permission for the Dashboard Home itself, redirecting them TO the Dashboard Home causes a loop.
        if ($isDashboardHome || $moduleName === '仪表盘首页') {
            // Halt completely and show the static error UI instead of redirecting
            denyAccess($errorMessage);
        }

        // For all other sub-pages, it is safe to redirect back to the Dashboard Home
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash_msg'] = $errorMessage;
        $_SESSION['flash_type'] = 'danger';
        
        // Execute Redirect
        if (!headers_sent()) {
            header("Location: " . $dashboardUrl);
        } else {
            // Output a full-screen overlay to hide the broken layout before JS redirects
            echo '<div style="position:fixed; top:0; left:0; width:100vw; height:100vh; background:#ffffff; z-index:999999; display:flex; justify-content:center; align-items:center;">';
            echo '<h4 style="color:#dc3545;">权限不足，正在返回首页...</h4>';
            echo '</div>';
            
            // Use replace() so users cannot click 'Back' into the forbidden page
            echo "<script>window.location.replace('" . $dashboardUrl . "');</script>";
        }
        exit();
    }

    return $errorMessage;
}

/**
 * Fetch dynamic page registry for Meta Settings dropdown.
 * Uses public_url as the key so pages can easily map their current URL to their SEO settings.
 */
function getDynamicPageRegistry($conn) {
    $registry = [];
    if (!$conn) return $registry;
    
    // Fetch active pages using prepared statement and bind_result
    $stmt = $conn->prepare("SELECT public_url, name_en, name_cn FROM " . PAGE_INFO_LIST . " WHERE status = 'A' ORDER BY name_en ASC");
    
    if ($stmt) {
        $stmt->execute();
        
        $url = null;
        $nameEn = null;
        $nameCn = null;
        
        $stmt->bind_result($url, $nameEn, $nameCn);
        
        while ($stmt->fetch()) {
            $trimmedUrl = trim((string)$url);
            // Only add pages that have a valid public URL defined
            if (!empty($trimmedUrl)) {
                $registry[$trimmedUrl] = $nameCn . ' (' . $nameEn . ')';
            }
        }
        $stmt->close();
    }
    
    return $registry;
}
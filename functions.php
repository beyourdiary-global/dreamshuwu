<?php
/**
 * functions.php
 * Reusable logic for validation and security.
 */

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
 * Logs a database operation to the audit_log table.
 */
function logAudit($params) {
    global $conn;

    // 1. Set Defaults
    $page        = $params['page'] ?? 'Unknown';
    $action      = $params['action'] ?? 'V';
    $message     = $params['action_message'] ?? '';
    $query       = $params['query'] ?? '';
    $table       = $params['query_table'] ?? '';
    $userId      = $params['user_id'] ?? 0;
    
    // Arrays for JSON columns
    $oldData     = $params['old_value'] ?? null;
    $newData     = $params['new_value'] ?? null;
    $changes     = null;

    // 2. Calculate Changes (Improved Logic: From -> To)
    if ($action === 'E' && is_array($oldData) && is_array($newData)) {
        $changes = [];
        foreach ($newData as $key => $value) {
            // Check if key exists in old data AND is different
            if (array_key_exists($key, $oldData) && $oldData[$key] !== $value) {
                $changes[$key] = [
                    'from' => $oldData[$key],
                    'to'   => $value
                ];
            }
        }
        // If no actual changes found, set to null
        if (empty($changes)) {
            $changes = null;
        }
    }

    // 3. JSON Encode (WITH SAFETY CHECK)
    // This fixes the "Call to undefined function json_encode" error
    if (function_exists('json_encode')) {
        // Use standard JSON encode if server supports it
        $flags = defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0;
        
        $jsonOld     = !empty($oldData) ? json_encode($oldData, $flags) : null;
        $jsonNew     = !empty($newData) ? json_encode($newData, $flags) : null;
        $jsonChanges = !empty($changes) ? json_encode($changes, $flags) : null;
    } else {
        // FALLBACK: If JSON extension is disabled, send NULL to avoid crash
        // IMPORTANT: You should enable the "json" extension in CPanel
        $jsonOld     = null;
        $jsonNew     = null;
        $jsonChanges = null;
    }

    // 4. Prepare SQL
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
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("Audit Log SQL Error: " . $conn->error);
    }
}
?>
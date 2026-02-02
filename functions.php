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
?>
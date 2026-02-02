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
 * Validates password strength based on your regex requirement:
 * Minimum 8 characters, at least one uppercase, one lowercase, one number, and one special character.
 */
function isStrongPassword($password) {
    $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
    return preg_match($regex, $password);
}

/**
 * Calculates age and checks if it meets the requirement
 */
function checkAgeRequirement($birthday, $minAge = 13) {
    $today = new DateTime();
    $birthDate = new DateTime($birthday);
    
    if ($birthDate > $today) {
        return "future_date"; // Birthday is in the future
    }
    
    $age = $today->diff($birthDate)->y;
    return ($age >= $minAge);
}
?>
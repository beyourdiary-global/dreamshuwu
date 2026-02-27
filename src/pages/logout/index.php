<?php
// Path: src/pages/logout/index.php
require_once dirname(__DIR__, 3) . '/common.php';

// 1. Handle AJAX "Cancel" Request
if (isPostRequest()) {
    header('Content-Type: application/json');
    echo safeJsonEncode(['success' => true]);
    exit(); 
}

// 2. Handle Actual Logout (GET Request)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

clearSession();

header("Location: " . URL_LOGIN);
exit();
?>
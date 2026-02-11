<?php
// Path: src/pages/logout/index.php
require_once dirname(__DIR__, 3) . '/common.php';

// 1. Handle AJAX "Cancel" Request
// We must intercept POST requests here. If we don't, the script will 
// continue to the bottom and log the user out, even if they clicked "Cancel".
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // Simply return success without logging anything
    echo json_encode(['success' => true]);
    exit(); 
}

// 2. Handle Actual Logout (GET Request)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Standard Logout Procedure
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: " . URL_LOGIN);
exit();
?>
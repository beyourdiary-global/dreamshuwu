<?php
// Path: profile.php
// Redirect to dashboard profile view for proper layout
require_once __DIR__ . '/common.php';
header("Location: " . URL_USER_DASHBOARD . "?view=profile");
exit();

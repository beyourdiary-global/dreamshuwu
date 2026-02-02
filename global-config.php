<?php
/**
 * global-config.php
 * Centralized data and settings for the application.
 */

// 1. Gender Options Mapping
// Key = Database Value, Value = Display Text (Chinese)
$GENDER_OPTIONS = [
    ""  => "选择性别 (可选)",
    "M" => "男",
    "F" => "女",
    "O" => "其他"
];

// 2. Application Constants
define('MIN_AGE_REQUIREMENT', 13);
define('MIN_PWD_LENGTH', 8);

?>
<?php
/**
 * include/header.php
 * Shared HTML head component.
 */
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'StarAdmin'; ?></title>

    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/all.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/header.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/global.css">

    <?php 
    if (isset($customCSS)) {
        // Case 1: Multiple Files (Array) - e.g. Audit Log
        if (is_array($customCSS)) {
            foreach ($customCSS as $cssFile) {
                echo '<link rel="stylesheet" href="' . URL_ASSETS . '/css/' . $cssFile . '">' . PHP_EOL;
            }
        } 
        // Case 2: Single File (String) - e.g. Profile Page
        else {
            echo '<link rel="stylesheet" href="' . URL_ASSETS . '/css/' . $customCSS . '">' . PHP_EOL;
        }
    }
    ?>

    <script>
        window.StarAdminConfig = {
            emailRegex: new RegExp(<?php echo json_encode(defined('EMAIL_REGEX_PATTERN') ? EMAIL_REGEX_PATTERN : '^[^\s@]+@[^\s@]+\.[^\s@]+$'); ?>),
            pwdRegex: new RegExp(<?php echo json_encode(defined('PWD_REGEX_PATTERN') ? PWD_REGEX_PATTERN : '^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[\\W_]).{8,}$'); ?>)
        };
    </script>
</head>
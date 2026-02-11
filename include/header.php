<?php
/**
 * include/header.php
 * Shared HTML head component.
 */

$globalSeo = getMetaSettings($conn, 'global', 0);
$specificSeo = null;

if (isset($category['id'])) {
    $specificSeo = getMetaSettings($conn, 'category', $category['id']);
}

// Hierarchy: Specific Page -> Global Setting -> Site Default
$finalMetaTitle = !empty($specificSeo['meta_title']) ? $specificSeo['meta_title'] : ($globalSeo['meta_title'] ?? 'StarAdmin');
$finalMetaDesc  = !empty($specificSeo['meta_description']) ? $specificSeo['meta_description'] : ($globalSeo['meta_description'] ?? '');
$finalOgTitle   = !empty($specificSeo['og_title']) ? $specificSeo['og_title'] : ($globalSeo['og_title'] ?? $finalMetaTitle);
$finalOgDesc    = !empty($specificSeo['og_description']) ? $specificSeo['og_description'] : ($globalSeo['og_description'] ?? $finalMetaDesc);
$finalOgUrl     = !empty($specificSeo['og_url']) ? $specificSeo['og_url'] : ($globalSeo['og_url'] ?? '');
?>

<head>
    <title><?php echo htmlspecialchars($finalMetaTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($finalMetaDesc); ?>">
    
    <meta property="og:title" content="<?php echo htmlspecialchars($finalOgTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($finalOgDesc); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($finalOgUrl); ?>">

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
        emailRegex: new RegExp("<?php
            echo addslashes(
                defined('EMAIL_REGEX_PATTERN')
                    ? EMAIL_REGEX_PATTERN
                    : '^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$'
            );
        ?>"),
        pwdRegex: new RegExp("<?php
            echo addslashes(
                defined('PWD_REGEX_PATTERN')
                    ? PWD_REGEX_PATTERN
                    : '^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[\\W_]).{8,}$'
            );
        ?>")
    };
    </script>

</head>
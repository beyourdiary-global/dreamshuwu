<?php
/**
 * include/header.php
 * Shared HTML head component.
 */

// 1. Fetch Website Settings
$webSettings = getWebSettings($conn);

// Fallback defaults
if (!$webSettings) {
    $webSettings = [
        'website_name'      => 'Website Name',
        'website_favicon'   => '',
        'theme_bg_color'    => '#ffffff',
        'theme_text_color'  => '#333333',
        'button_color'      => '#233dd2',
        'button_text_color' => '#ffffff',
        'background_color'  => '#f4f7f6'
    ];
}

$globalSeo = getMetaSettings($conn, 'global', 0);
$specificSeo = null;

if (isset($pageMetaKey) && !empty($pageMetaKey)) {
    $specificSeo = getPageMetaSettings($conn, $pageMetaKey);
}
if (!$specificSeo && isset($category['id'])) {
    $specificSeo = getMetaSettings($conn, 'category', $category['id']);
}

$siteName = !empty($webSettings['website_name']) ? $webSettings['website_name'] : 'StarAdmin';

$finalMetaTitle = !empty($specificSeo['meta_title']) ? $specificSeo['meta_title'] : ($globalSeo['meta_title'] ?? $siteName);
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

    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <?php if (!empty($webSettings['website_favicon'])): ?>
        <link rel="icon" type="image/png" href="<?php echo URL_ASSETS . '/uploads/settings/' . htmlspecialchars($webSettings['website_favicon'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/all.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/header.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/global.css">

    <?php 
    if (isset($customCSS)) {
        if (is_array($customCSS)) {
            foreach ($customCSS as $cssFile) {
                echo '<link rel="stylesheet" href="' . URL_ASSETS . '/css/' . $cssFile . '">' . PHP_EOL;
            }
        } else {
            echo '<link rel="stylesheet" href="' . URL_ASSETS . '/css/' . $customCSS . '">' . PHP_EOL;
        }
    }
    ?>

    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/webSetting.css">

    <style>
        :root {
            --theme-bg-color: <?php echo htmlspecialchars($webSettings['theme_bg_color']); ?>;
            --theme-text-color: <?php echo htmlspecialchars($webSettings['theme_text_color']); ?>;
            --btn-color: <?php echo htmlspecialchars($webSettings['button_color']); ?>;
            --btn-text-color: <?php echo htmlspecialchars($webSettings['button_text_color']); ?>;
            --page-bg-color: <?php echo defined('CUSTOM_PAGE_BG') ? CUSTOM_PAGE_BG : htmlspecialchars($webSettings['background_color']); ?>;
        }
    </style>

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
    <script src="<?php echo URL_ASSETS; ?>/js/global.js"></script>
</head>
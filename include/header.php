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
        'background_color'  => '#f4f7f6',
        'sidebar_color'     => '#ffffff'
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

$siteName = !empty($webSettings['website_name']) ? $webSettings['website_name'] : 'Website Name';

$normalizeHexColor = function ($value, $default) {
    $candidate = is_string($value) ? trim($value) : '';
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $candidate) ? strtoupper($candidate) : $default;
};

$hexToRgbTriplet = function ($hexColor) {
    $cleanHex = ltrim($hexColor, '#');
    return [
        hexdec(substr($cleanHex, 0, 2)),
        hexdec(substr($cleanHex, 2, 2)),
        hexdec(substr($cleanHex, 4, 2))
    ];
};

$darkenHexColor = function ($hexColor, $ratio) use ($hexToRgbTriplet) {
    [$red, $green, $blue] = $hexToRgbTriplet($hexColor);
    $ratio = max(0, min(1, (float) $ratio));
    $newRed = (int) round($red * (1 - $ratio));
    $newGreen = (int) round($green * (1 - $ratio));
    $newBlue = (int) round($blue * (1 - $ratio));
    return sprintf('#%02X%02X%02X', $newRed, $newGreen, $newBlue);
};

$hexToRgba = function ($hexColor, $alpha) use ($hexToRgbTriplet) {
    [$red, $green, $blue] = $hexToRgbTriplet($hexColor);
    $alpha = max(0, min(1, (float) $alpha));
    return sprintf('rgba(%d, %d, %d, %.2f)', $red, $green, $blue, $alpha);
};

$resolvedThemeBgColor = $normalizeHexColor($webSettings['theme_bg_color'] ?? '', '#FFFFFF');
$resolvedThemeTextColor = $normalizeHexColor($webSettings['theme_text_color'] ?? '', '#333333');
$resolvedButtonColor = $normalizeHexColor($webSettings['button_color'] ?? '', '#233DD2');
$resolvedButtonTextColor = $normalizeHexColor($webSettings['button_text_color'] ?? '', '#FFFFFF');
$resolvedPageBgColor = defined('CUSTOM_PAGE_BG')
    ? (string) CUSTOM_PAGE_BG
    : $normalizeHexColor($webSettings['background_color'] ?? '', '#F4F7F6');
$resolvedSidebarBgColor = $normalizeHexColor($webSettings['sidebar_color'] ?? '', '#FFFFFF');

$resolvedPrimaryHoverColor = $darkenHexColor($resolvedButtonColor, 0.14);
$resolvedPrimarySoft05 = $hexToRgba($resolvedButtonColor, 0.05);
$resolvedPrimarySoft10 = $hexToRgba($resolvedButtonColor, 0.10);
$resolvedPrimarySoft15 = $hexToRgba($resolvedButtonColor, 0.15);

$finalMetaTitle = !empty($specificSeo['meta_title']) ? $specificSeo['meta_title'] : ($globalSeo['meta_title'] ?? $siteName);
$finalMetaDesc  = !empty($specificSeo['meta_description']) ? $specificSeo['meta_description'] : ($globalSeo['meta_description'] ?? '');
$finalOgTitle   = !empty($specificSeo['og_title']) ? $specificSeo['og_title'] : ($globalSeo['og_title'] ?? $finalMetaTitle);
$finalOgDesc    = !empty($specificSeo['og_description']) ? $specificSeo['og_description'] : ($globalSeo['og_description'] ?? $finalMetaDesc);
$finalOgUrl     = !empty($specificSeo['og_url']) ? $specificSeo['og_url'] : ($globalSeo['og_url'] ?? '');
?>

<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
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
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/responsive.bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/buttons.bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/header.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/sidebar.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/global.css">

    <?php 
    if (isset($customCSS)) {
        if (is_array($customCSS)) {
            foreach ($customCSS as $cssFile) {
                $cssFile = (string)$cssFile;
                if ($cssFile === '') continue;

                if (preg_match('#^https?://#i', $cssFile)) {
                    $cssHref = $cssFile;
                } elseif (strpos($cssFile, '/') === 0) {
                    $cssHref = SITEURL . $cssFile;
                } elseif (strpos($cssFile, '/') !== false) {
                    $cssHref = SITEURL . '/' . ltrim($cssFile, '/');
                } else {
                    $cssHref = URL_ASSETS . '/css/' . $cssFile;
                }

                echo '<link rel="stylesheet" href="' . $cssHref . '">' . PHP_EOL;
            }
        } else {
            $cssFile = (string)$customCSS;
            if ($cssFile !== '') {
                if (preg_match('#^https?://#i', $cssFile)) {
                    $cssHref = $cssFile;
                } elseif (strpos($cssFile, '/') === 0) {
                    $cssHref = SITEURL . $cssFile;
                } elseif (strpos($cssFile, '/') !== false) {
                    $cssHref = SITEURL . '/' . ltrim($cssFile, '/');
                } else {
                    $cssHref = URL_ASSETS . '/css/' . $cssFile;
                }

                echo '<link rel="stylesheet" href="' . $cssHref . '">' . PHP_EOL;
            }
        }
    }
    ?>

    <style>
        :root {
            --theme-bg-color: <?php echo htmlspecialchars($resolvedThemeBgColor, ENT_QUOTES, 'UTF-8'); ?>;
            --theme-text-color: <?php echo htmlspecialchars($resolvedThemeTextColor, ENT_QUOTES, 'UTF-8'); ?>;
            --btn-color: <?php echo htmlspecialchars($resolvedButtonColor, ENT_QUOTES, 'UTF-8'); ?>;
            --btn-text-color: <?php echo htmlspecialchars($resolvedButtonTextColor, ENT_QUOTES, 'UTF-8'); ?>;
            --page-bg-color: <?php echo htmlspecialchars($resolvedPageBgColor, ENT_QUOTES, 'UTF-8'); ?>;
            --sidebar-bg-color: <?php echo htmlspecialchars($resolvedSidebarBgColor, ENT_QUOTES, 'UTF-8'); ?>;
            --primary-color: <?php echo htmlspecialchars($resolvedButtonColor, ENT_QUOTES, 'UTF-8'); ?>;
            --primary-hover: <?php echo htmlspecialchars($resolvedPrimaryHoverColor, ENT_QUOTES, 'UTF-8'); ?>;
            --btn-hover-color: <?php echo htmlspecialchars($resolvedPrimaryHoverColor, ENT_QUOTES, 'UTF-8'); ?>;
            --primary-soft-05: <?php echo htmlspecialchars($resolvedPrimarySoft05, ENT_QUOTES, 'UTF-8'); ?>;
            --primary-soft-10: <?php echo htmlspecialchars($resolvedPrimarySoft10, ENT_QUOTES, 'UTF-8'); ?>;
            --primary-soft-15: <?php echo htmlspecialchars($resolvedPrimarySoft15, ENT_QUOTES, 'UTF-8'); ?>;
            --interactive-hover-bg: <?php echo htmlspecialchars($resolvedPrimarySoft10, ENT_QUOTES, 'UTF-8'); ?>;
        }
    </style>

    <meta name="email-regex" content="<?php echo htmlspecialchars(defined('EMAIL_REGEX_PATTERN') ? EMAIL_REGEX_PATTERN : '^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="pwd-regex" content="<?php echo htmlspecialchars(defined('PWD_REGEX_PATTERN') ? PWD_REGEX_PATTERN : '^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[\\W_]).{8,}$', ENT_QUOTES, 'UTF-8'); ?>">
    
    <script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
    
    <script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>

    <script src="<?php echo URL_ASSETS; ?>/js/global.js"></script>
</head>
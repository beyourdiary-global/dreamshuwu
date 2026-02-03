<?php
/**
 * header.php
 * Shared HTML head component.
 * Variables expected before inclusion:
 * @var string $pageTitle - The title shown in the browser tab.
 * @var string $customCSS - The path/filename of the stylesheet (e.g., 'style.css').
 */
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'StarAdmin'; ?></title>

    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/bootstrap.min.css">

    <?php if (isset($customCSS)): ?>
        <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/<?php echo $customCSS; ?>">
    <?php endif; ?>
</head>


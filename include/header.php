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

<?php if (isset($customCSS)): ?>
<link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/<?php echo $customCSS; ?>">
<?php endif; ?>

<script>
window.StarAdminConfig = {
emailRegex: new RegExp(<?php echo json_encode(defined('EMAIL_REGEX_PATTERN') ? EMAIL_REGEX_PATTERN : '^[^\s@]+@[^\s@]+\.[^\s@]+$'); ?>),
pwdRegex: new RegExp(<?php echo json_encode(defined('PWD_REGEX_PATTERN') ? PWD_REGEX_PATTERN : '^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[\\W_]).{8,}$'); ?>)
};
</script>
</head>
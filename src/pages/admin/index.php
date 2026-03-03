<?php
// Path: src/pages/admin/index.php

requireLogin();

$currentUrl = parse_url(URL_ADMIN_DASHBOARD, PHP_URL_PATH) ?: '/admin/dashboard.php';
$perm = hasPagePermission($conn, $currentUrl);
$pageName = getDynamicPageName($conn, $perm, $currentUrl);

// 2. Use the unified helper to handle view permission and redirection
// This handles the access check and redirects to /dashboard.php if denied
checkPermissionError('view', $perm);

$pageMetaKey = $currentUrl;
$customCSS[] = 'src/pages/admin/css/admin.css';

// 3. Fetch permissions for child cards
$pageActionPath = parse_url(URL_PAGE_ACTION, PHP_URL_PATH) ?: '/admin/page-action.php';
$pageInfoPath = parse_url(URL_PAGE_INFO, PHP_URL_PATH) ?: '/admin/page-information-list.php';
$userRolePath = parse_url(URL_USER_ROLE, PHP_URL_PATH) ?: '/admin/user-role.php';

$permPageAction = hasPagePermission($conn, $pageActionPath);
$permPageInfo   = hasPagePermission($conn, $pageInfoPath);
$permUserRole   = hasPagePermission($conn, $userRolePath);

$pageActionName = getDynamicPageName($conn, $permPageAction, $pageActionPath);
$pageInfoName = getDynamicPageName($conn, $permPageInfo, $pageInfoPath);
$userRoleName = getDynamicPageName($conn, $permUserRole, $userRolePath);

$permAuthorVerification = hasPagePermission($conn, '/author/author-verification.php');
if (empty($permAuthorVerification) || (isset($permAuthorVerification->view) && empty($permAuthorVerification->view))) {
    $authorVerificationLegacyPath = defined('PATH_AUTHOR_VERIFICATION_INDEX') ? ('/' . ltrim(PATH_AUTHOR_VERIFICATION_INDEX, '/')) : '/src/pages/author/author-verification/index.php';
    $permAuthorVerification = hasPagePermission($conn, $authorVerificationLegacyPath);
}

$authorVerificationName = getDynamicPageName($conn, $permAuthorVerification, '/author/author-verification.php');

$permEmailTemplate = hasPagePermission($conn, '/author/email-template.php');
if (empty($permEmailTemplate) || (isset($permEmailTemplate->view) && empty($permEmailTemplate->view))) {
    $emailTemplateLegacyPath = defined('PATH_EMAIL_TEMPLATE_INDEX') ? ('/' . ltrim(PATH_EMAIL_TEMPLATE_INDEX, '/')) : '/src/pages/author/email-template/index.php';
    $permEmailTemplate = hasPagePermission($conn, $emailTemplateLegacyPath);
}
$emailTemplateName = getDynamicPageName($conn, $permEmailTemplate, '/author/email-template.php');
?>
<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="admin-dashboard-container app-page-shell">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <?php echo generateBreadcrumb($conn, $currentUrl); ?>
            <h3 class="text-dark mb-0"><?php echo htmlspecialchars($pageName); ?></h3>
        </div>
    </div>

    <div class="row">
        <?php if (!empty($permPageAction) && $permPageAction->view): ?>
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <a href="<?php echo URL_PAGE_ACTION; ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm action-card hover-lift">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle" style="width: 60px; height: 60px;">
                                <i class="fa-solid fa-gears fa-xl"></i>
                            </span>
                        </div>
                        <h5 class="card-title text-dark fw-bold"><?php echo htmlspecialchars($pageActionName); ?></h5>
                        <p class="card-text text-muted small">(Page Actions)</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($permPageInfo) && $permPageInfo->view): ?>
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <a href="<?php echo URL_PAGE_INFO; ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm action-card hover-lift">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info rounded-circle" style="width: 60px; height: 60px;">
                                <i class="fa-solid fa-file-signature fa-xl"></i>
                            </span>
                        </div>
                        <h5 class="card-title text-dark fw-bold"><?php echo htmlspecialchars($pageInfoName); ?></h5>
                        <p class="card-text text-muted small">(Page Info & Permissions)</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($permUserRole) && $permUserRole->view): ?>
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <a href="<?php echo URL_USER_ROLE; ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm action-card hover-lift">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle" style="width: 60px; height: 60px;">
                                <i class="fa-solid fa-shield fa-xl"></i>
                            </span>
                        </div>
                        <h5 class="card-title text-dark fw-bold"><?php echo htmlspecialchars($userRoleName); ?></h5>
                        <p class="card-text text-muted small">(User Roles)</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($permAuthorVerification) && $permAuthorVerification->view): ?>
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <a href="<?php echo URL_AUTHOR_VERIFICATION; ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm action-card hover-lift">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle" style="width: 60px; height: 60px;">
                                <i class="fa-solid fa-user-check fa-xl"></i>
                            </span>
                        </div>
                        <h5 class="card-title text-dark fw-bold"><?php echo htmlspecialchars($authorVerificationName); ?></h5>
                        <p class="card-text text-muted small">(Author Verification)</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($permEmailTemplate) && $permEmailTemplate->view): ?>
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <a href="<?php echo URL_EMAIL_TEMPLATE; ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm action-card hover-lift">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center bg-secondary bg-opacity-10 text-secondary rounded-circle" style="width: 60px; height: 60px;">
                                <i class="fa-solid fa-envelope-open-text fa-xl"></i>
                            </span>
                        </div>
                        <h5 class="card-title text-dark fw-bold"><?php echo htmlspecialchars($emailTemplateName); ?></h5>
                        <p class="card-text text-muted small">(Email Templates)</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo SITEURL; ?>/src/pages/admin/js/admin.js"></script>
</body>
</html>
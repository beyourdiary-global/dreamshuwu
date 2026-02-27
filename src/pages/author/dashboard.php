<?php
// Path: src/pages/author/dashboard.php
require_once dirname(__DIR__, 3) . '/common.php';

// 1. Basic login check
requireLogin();

$currentUserId = $_SESSION['user_id'];
$currentUrl = '/author/dashboard.php';
$auditPage = 'Author Dashboard';

// 2. Strict Author Profile Check (Must be 'approved')
// Using the centralized helper function from functions.php
requireApprovedAuthor($conn, $currentUserId);

// 3. System Permission Check for the dashboard itself (RBAC Fallback)
$perm = hasPagePermission($conn, $currentUrl);
checkPermissionError('view', $perm);

// 4. Define a logical view query for the audit log (Checking Author Profile)
$dashboardTable = defined('AUTHOR_PROFILE') ? AUTHOR_PROFILE : 'author_profile';
$viewQuery = "SELECT id, user_id, pen_name, verification_status FROM {$dashboardTable} WHERE user_id = ? LIMIT 1";

if (function_exists('logAudit')) {
        logAudit([
            'page'           => $auditPage,
            'action'         => 'V',
            'action_message' => 'User accessed Author Dashboard',
            'query'          => $viewQuery,
            'query_table'    => $dashboardTable,
            'user_id'        => $currentUserId
        ]);
}


// 5. Fetch permissions for child modules to determine menu visibility
// Dynamically extract the path from the defined URL to ensure it matches the database perfectly
$novelUrlPath = defined('URL_AUTHOR_NOVEL_MANAGEMENT') ? parse_url(URL_AUTHOR_NOVEL_MANAGEMENT, PHP_URL_PATH) : '/author/novel-management.php';

$permNovel = hasPagePermission($conn, $novelUrlPath);
if (empty($permNovel) || (isset($permNovel->view) && empty($permNovel->view))) {
    // Fallback check if the permission was registered using the physical file path
    $novelLegacyPath = defined('PATH_AUTHOR_NOVEL_MANAGEMENT') ? ('/' . ltrim(PATH_AUTHOR_NOVEL_MANAGEMENT, '/')) : '/src/pages/author/novel-management/index.php';
    $permNovel = hasPagePermission($conn, $novelLegacyPath);
}

?>
<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/author.css">
</head>
<body style="background-color: #f4f7f6;">

<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<div class="container main-content" style="max-width: 1200px; margin: 30px auto; padding: 0 20px; min-height: 80vh;">
    
    <?php 
    // Display flash messages if any exist
    if (isset($_SESSION['flash_msg'])) {
        $flashType = $_SESSION['flash_type'] ?? 'info';
        $flashMsg = $_SESSION['flash_msg'];
        unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
    ?>
        <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?php echo htmlspecialchars($flashMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php } ?>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <?php echo generateBreadcrumb($conn, $currentUrl, '作者专区'); ?>
            <h3 class="text-dark mb-0">作者专区 (Author Zone)</h3>
        </div>
    </div>

    <div class="row">
        
        <?php if (!empty($permNovel) && !empty($permNovel->view)): ?>
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <a href="<?php echo URL_AUTHOR_NOVEL_MANAGEMENT; ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm action-card hover-lift">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle" style="width: 60px; height: 60px;">
                                <i class="fa-solid fa-book-open fa-xl"></i>
                            </span>
                        </div>
                        <h5 class="card-title text-dark fw-bold">我的小说</h5>
                        <p class="card-text text-muted small">(Novel Management)</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>

</body>
</html>
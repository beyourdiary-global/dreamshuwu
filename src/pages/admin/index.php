<?php
// Path: src/pages/admin/index.php

// 1. Define the base path to prevent redundant hardcoding
$baseViewPath = '/dashboard.php?view=';

$currentUrl = $baseViewPath . 'admin';
$perm = hasPagePermission($conn, $currentUrl);

// 2. Use the unified helper to handle view permission and redirection
// This handles the access check and redirects to /dashboard.php if denied
checkPermissionError('view', $perm, '管理员面板');

// 3. Fetch permissions for child cards using the base path
$permPageAction = hasPagePermission($conn, $baseViewPath . 'page_action');
$permPageInfo   = hasPagePermission($conn, $baseViewPath . 'page_info');
$permUserRole   = hasPagePermission($conn, $baseViewPath . 'user_role');
?>
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h3 class="text-dark mb-0">管理员面板</h3>
    </div>

    <div class="row">
        <?php if (!empty($permPageAction) && $permPageAction->view): ?>
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <a href="<?php echo $baseViewPath . 'page_action'; ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm action-card hover-lift">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle" style="width: 60px; height: 60px;">
                                <i class="fa-solid fa-gears fa-xl"></i>
                            </span>
                        </div>
                        <h5 class="card-title text-dark fw-bold">页面操作管理</h5>
                        <p class="card-text text-muted small">(Page Actions)</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($permPageInfo) && $permPageInfo->view): ?>
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <a href="<?php echo $baseViewPath . 'page_info'; ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm action-card hover-lift">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center bg-info bg-opacity-10 text-info rounded-circle" style="width: 60px; height: 60px;">
                                <i class="fa-solid fa-file-signature fa-xl"></i>
                            </span>
                        </div>
                        <h5 class="card-title text-dark fw-bold">页面信息列表</h5>
                        <p class="card-text text-muted small">(Page Info & Permissions)</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($permUserRole) && $permUserRole->view): ?>
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <a href="<?php echo $baseViewPath . 'user_role'; ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm action-card hover-lift">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle" style="width: 60px; height: 60px;">
                                <i class="fa-solid fa-shield fa-xl"></i>
                            </span>
                        </div>
                        <h5 class="card-title text-dark fw-bold">用户角色管理</h5>
                        <p class="card-text text-muted small">(User Roles)</p>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
// Path: src/pages/admin/index.php

$currentUrl = '/dashboard.php?view=admin';
$perm = hasPagePermission($conn, $currentUrl);

if (empty($perm) || !$perm->view) {
    denyAccess("权限不足：您没有访问管理员面板的权限。");
}

$permPageAction = hasPagePermission($conn, '/dashboard.php?view=page_action');
$permPageInfo   = hasPagePermission($conn, '/dashboard.php?view=page_info');
$permUserRole   = hasPagePermission($conn, '/dashboard.php?view=user_role');
?>
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h3 class="text-dark mb-0">管理员面板</h3>
    </div>

    <div class="row">
        <?php if (!empty($permPageAction) && $permPageAction->view): ?>
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <a href="<?php echo URL_USER_DASHBOARD . '?view=page_action'; ?>" class="text-decoration-none">
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
            <a href="<?php echo URL_USER_DASHBOARD . '?view=page_info'; ?>" class="text-decoration-none">
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
            <a href="<?php echo URL_USER_ROLE; ?>" class="text-decoration-none">
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
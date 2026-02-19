<?php
// Path: src/pages/admin/index.php

// RBAC Permission Check
$currentUrl = '/dashboard.php?view=admin';
$allowedActions = getPageRuntimePermissions($currentUrl);
$canView = in_array('View', $allowedActions);

if (!$canView) {
    echo '
    <div class="container-fluid d-flex align-items-center justify-content-center" style="min-height: 400px;">
        <div class="text-center">
            <div class="mb-4">
                <i class="fa-solid fa-lock text-danger" style="font-size: 5rem; opacity: 0.2;"></i>
            </div>
            <h3 class="text-dark fw-bold">无权访问此页面</h3>
            <p class="text-muted">抱歉，您的角色没有权限查看"管理员面板"。请联系系统管理员进行授权。</p>
            <a href="' . URL_USER_DASHBOARD . '" class="btn btn-outline-primary mt-3">
                <i class="fa-solid fa-house me-2"></i>返回仪表盘
            </a>
        </div>
    </div>';
    return;
}

// Check sub-page permissions for conditional card visibility
$canViewPageAction = in_array('View', getPageRuntimePermissions('/dashboard.php?view=page_action'));
$canViewPageInfo   = in_array('View', getPageRuntimePermissions('/dashboard.php?view=page_info'));
$canViewUserRole   = in_array('View', getPageRuntimePermissions('/dashboard.php?view=user_role'));
?>
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h3 class="text-dark mb-0">管理员面板</h3>
    </div>

    <div class="row">
        <?php if ($canViewPageAction): ?>
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

        <?php if ($canViewPageInfo): ?>
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

        <?php if ($canViewUserRole): ?>
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
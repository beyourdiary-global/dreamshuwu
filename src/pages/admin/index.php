<?php
// Path: src/pages/admin/index.php
// Embedded View - No HTML/Body tags needed
?>
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h3 class="text-dark mb-0">管理员面板</h3>
    </div>

    <div class="row">
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
                        <p class="card-text text-muted small">
                            (Page Actions)
                        </p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
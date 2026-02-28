<?php
// Path: src/pages/author/novel-management/index.php
require_once dirname(__DIR__, 4) . '/common.php';

// Generate a CSRF token if it doesn't exist in the session
if (empty(session('csrf_token'))) {
    setSession('csrf_token', bin2hex(random_bytes(32)));
}

requireLogin();

$currentUserId = sessionInt('user_id');

// Verify if the current user is an approved author
requireApprovedAuthor($conn, $currentUserId);

$currentUrl = defined('URL_AUTHOR_NOVEL_MANAGEMENT') ? parse_url(URL_AUTHOR_NOVEL_MANAGEMENT, PHP_URL_PATH) : '/author/novel-management.php';
$auditPage = 'Author Novel Management';

// Strict permission check (RBAC role-based access validation)
$perm = hasPagePermission($conn, $currentUrl);
if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
    $legacyPath = defined('PATH_AUTHOR_NOVEL_MANAGEMENT') ? ('/' . ltrim(PATH_AUTHOR_NOVEL_MANAGEMENT, '/')) : '/src/pages/author/novel-management/index.php';
    $perm = hasPagePermission($conn, $legacyPath);
}
$pageName = getDynamicPageName($conn, $perm, $currentUrl);

// Check for view permission; block and redirect if denied
checkPermissionError('view', $perm);

// [REF] Define view query early for use in execution and logging
$catSql = "SELECT id, name FROM " . NOVEL_CATEGORY . " ORDER BY name ASC";
$categories = [];

if ($res = $conn->query($catSql)) {
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
    $res->free();
}

if (function_exists('logAudit')) {
        logAudit([
            'page'           => $auditPage,
            'action'         => 'V',
            'action_message' => 'Viewing Author Novel Management Page',
            'query'          => $catSql,
            'query_table'    => NOVEL_CATEGORY,
            'user_id'        => $currentUserId
        ]);
}

$apiEndpoint = defined('URL_AUTHOR_NOVEL_API') ? URL_AUTHOR_NOVEL_API : SITEURL . '/src/pages/author/novel-management/api.php';

// Define table columns dynamically for clean rendering
$tableColumns = [
    ['title' => '封面', 'style' => 'width: 80px;', 'class' => ''],
    ['title' => '书名', 'style' => '', 'class' => ''],
    ['title' => '分类', 'style' => '', 'class' => ''],
    ['title' => '标签', 'style' => '', 'class' => ''],
    ['title' => '状态', 'style' => '', 'class' => ''],
    ['title' => '创建时间', 'style' => '', 'class' => ''],
    ['title' => '操作', 'style' => 'width: 150px;', 'class' => 'text-center']
];
?>
<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/author.css">
</head>
<body style="background-color: #f4f7f6;">
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<div class="container main-content" id="novelManagementApp" style="max-width: 1200px; margin: 30px auto; padding: 0 20px; min-height: 80vh;"
     data-api-url="<?php echo htmlspecialchars($apiEndpoint); ?>"
     data-can-edit="<?php echo !empty($perm->edit) ? 1 : 0; ?>"
     data-can-delete="<?php echo !empty($perm->delete) ? 1 : 0; ?>">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <?php echo generateBreadcrumb($conn, $currentUrl, $pageName); ?>
            <h3 class="text-dark mb-0"><?php echo htmlspecialchars($pageName); ?></h3>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="novel-stat-card" style="background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);">
                <div class="stat-details"><p>总创建小说数</p><h3 id="statTotalNovels">0</h3></div>
                <i class="fa-solid fa-book stat-icon"></i>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="novel-stat-card" style="background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);">
                <div class="stat-details"><p>连载中小说数</p><h3 id="statOngoingNovels">0</h3></div>
                <i class="fa-solid fa-pen-nib stat-icon"></i>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="novel-stat-card" style="background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);">
                <div class="stat-details"><p>已完结小说数</p><h3 id="statCompletedNovels">0</h3></div>
                <i class="fa-solid fa-check-double stat-icon"></i>
            </div>
        </div>
    </div>

    <?php if (!empty($perm->add)): ?>
    <div class="card border-0 shadow-sm mb-5" style="border-radius: 12px;">
        <div class="card-header bg-white py-3" style="border-bottom: 1px solid #f0f2f5;">
            <h5 class="m-0 fw-bold text-primary"><i class="fa-solid fa-plus-circle me-2"></i>开新小说 (Create New Novel)</h5>
        </div>
        <div class="card-body p-4">
            <form id="novelForm" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="mode" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo session('csrf_token'); ?>">
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">书名 Book Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" maxlength="100" required placeholder="不可重复">
                                <div class="invalid-feedback">请输入书名</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">分类 Category <span class="text-danger">*</span></label>
                                <select name="category_id" id="categorySelect" class="form-select" required>
                                    <option value="">请选择分类...</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">请选择分类</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label d-block">标签 Tags <span class="text-muted small">(最多可选10个)</span> <span class="text-danger">*</span></label>
                            <div id="dynamicTagsContainer" class="p-3 bg-light rounded border d-flex flex-wrap gap-2">
                                <span class="text-muted small">请先选择分类 (Please select a category first)</span>
                            </div>
                            <div id="tagsError" class="text-danger small mt-1 d-none">请至少选择一个标签</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">简介 Introduction <span class="text-danger">*</span></label>
                            <textarea name="introduction" class="form-control" rows="5" maxlength="2000" required placeholder="请输入小说简介..."></textarea>
                            <div class="invalid-feedback">请输入小说简介</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">状态 Completion Status</label>
                            <select name="completion_status" class="form-select">
                                <option value="ongoing" selected>连载中 (Ongoing)</option>
                                <option value="completed">已完结 (Completed)</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-lg-4 d-flex flex-column align-items-center justify-content-start pt-3">
                        <label class="form-label text-center w-100">封面图 Cover Image <span class="text-danger">*</span></label>
                        <label for="cover_image_input" class="cover-upload-box mb-2" id="box_cover_image">
                            <div class="placeholder">
                                <i class="fa-solid fa-cloud-arrow-up fa-2x mb-2"></i><br>点击上传<br>(JPG/PNG, 建议3:4)
                            </div>
                        </label>
                        <input type="file" name="cover_image" id="cover_image_input" class="d-none" accept="image/jpeg, image/png" required>
                        <small class="text-muted mb-4 text-center">最大限制 2MB</small>
                        <div id="coverError" class="text-danger small mt-1 text-center d-none">请上传封面图片</div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="copyright_declaration" id="copyright_declaration" value="1" required>
                        <label class="form-check-label text-muted" for="copyright_declaration" style="font-size: 14px;">
                            <span class="text-danger">*</span> 我确认此封面图片不侵犯任何版权。
                        </label>
                        <div class="invalid-feedback">必须勾选确认版权声明</div>
                    </div>
                    <button type="button" class="btn btn-primary px-5 py-2 fw-bold" id="btnSubmitNovel">
                        <i class="fa-solid fa-paper-plane me-2"></i> 提交发布
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white py-3" style="border-bottom: 1px solid #f0f2f5;">
            <h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-list me-2"></i>我的作品列表 (My Novels)</h5>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="novelTable" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($tableColumns as $col): ?>
                                <th 
                                    <?php echo !empty($col['class']) ? 'class="' . htmlspecialchars($col['class']) . '"' : ''; ?> 
                                    <?php echo !empty($col['style']) ? 'style="' . htmlspecialchars($col['style']) . '"' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($col['title']); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="novelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">编辑小说 (Edit Novel)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="novelModalForm" enctype="multipart/form-data" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" name="novel_id" id="modal_novel_id" value="0">
                    <input type="hidden" name="mode" value="update">
                    <input type="hidden" name="csrf_token" value="<?php echo session('csrf_token'); ?>">
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">书名 Book Title <span class="text-danger req-star">*</span></label>
                                    <input type="text" name="title" id="modal_title" class="form-control" maxlength="100" required>
                                    <div class="invalid-feedback">请输入书名</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">分类 Category <span class="text-danger req-star">*</span></label>
                                    <select name="category_id" id="modalCategorySelect" class="form-select" required>
                                        <option value="">请选择分类...</option>
                                        <?php foreach($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">请选择分类</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label d-block">标签 Tags <span class="text-muted small">(最多可选10个)</span> <span class="text-danger req-star">*</span></label>
                                <div id="modalDynamicTagsContainer" class="p-3 bg-light rounded border d-flex flex-wrap gap-2">
                                    <span class="text-muted small">请先选择分类 (Please select a category first)</span>
                                </div>
                                <div id="modalTagsError" class="text-danger small mt-1 d-none">请至少选择一个标签</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">简介 Introduction <span class="text-danger req-star">*</span></label>
                                <textarea name="introduction" id="modal_introduction" class="form-control" rows="5" maxlength="2000" required></textarea>
                                <div class="invalid-feedback">请输入小说简介</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">状态 Completion Status</label>
                                <select name="completion_status" id="modal_completion_status" class="form-select">
                                    <option value="ongoing">连载中 (Ongoing)</option>
                                    <option value="completed">已完结 (Completed)</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-4 d-flex flex-column align-items-center justify-content-start pt-3">
                            <label class="form-label text-center w-100">封面图 Cover Image</label>
                            <label for="modal_cover_image_input" class="cover-upload-box mb-2" id="modal_box_cover_image">
                                <div class="placeholder">
                                    <i class="fa-solid fa-cloud-arrow-up fa-2x mb-2"></i><br>点击更换<br>(JPG/PNG)
                                </div>
                            </label>
                            <input type="file" name="cover_image" id="modal_cover_image_input" class="d-none" accept="image/jpeg, image/png">
                            <small class="text-muted mb-4 text-center">最大限制 2MB (不修改请留空)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消 (Cancel)</button>
                    <button type="button" class="btn btn-primary" id="btnUpdateNovel">保存修改 (Save Changes)</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/author.js"></script>

</body>
</html>
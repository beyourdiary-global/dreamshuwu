<?php
require_once dirname(__DIR__, 4) . '/common.php';

$isEmbeddedEmailTemplate = isset($EMBED_EMAIL_TEMPLATE) && $EMBED_EMAIL_TEMPLATE === true;
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : 0);
$currentUrl = '/author/email-template.php';
$auditPage = 'Email Template Management';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . URL_LOGIN);
    exit();
}

$perm = hasPagePermission($conn, $currentUrl);
if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
    $legacyPath = defined('PATH_EMAIL_TEMPLATE_INDEX') ? ('/' . ltrim(PATH_EMAIL_TEMPLATE_INDEX, '/')) : '/src/pages/author/email-template/index.php';
    $perm = hasPagePermission($conn, $legacyPath);
}
checkPermissionError('view', $perm, '邮件模板管理');
$apiEndpoint = defined('URL_EMAIL_TEMPLATE_API') ? URL_EMAIL_TEMPLATE_API : (SITEURL . '/src/pages/author/email-template/api.php');

if (function_exists('logAudit') && !defined('EMAIL_TEMPLATE_VIEW_LOGGED')) {
    define('EMAIL_TEMPLATE_VIEW_LOGGED', true);
    logAudit([
        'page' => $auditPage,
        'action' => 'V',
        'action_message' => 'Viewing email template list',
        'query' => 'SELECT * FROM ' . EMAIL_TEMPLATE . " WHERE status <> 'D'",
        'query_table' => EMAIL_TEMPLATE,
        'user_id' => $currentUserId
    ]);
}

if (!$isEmbeddedEmailTemplate) {
    if (!isset($customCSS) || !is_array($customCSS)) {
        $customCSS = [];
    }
    $customCSS[] = 'dataTables.bootstrap.min.css';
    $pageMetaKey = $currentUrl;
}

// Define the arrays for rendering the UI
$perPageOptions = [10, 20, 50, 100];

$tableHeaders = [
    ['label' => 'ID', 'width' => '70px', 'class' => ''],
    ['label' => '模板代码', 'width' => '160px', 'class' => ''],
    ['label' => '模板名称', 'width' => '', 'class' => ''],
    ['label' => '主题', 'width' => '', 'class' => ''],
    ['label' => '状态', 'width' => '90px', 'class' => ''],
    ['label' => '更新时间', 'width' => '170px', 'class' => ''],
    ['label' => '操作', 'width' => '170px', 'class' => 'text-center'],
];

ob_start();
?>
<div class="container-fluid px-0" id="emailTemplateApp" 
     data-api-url="<?php echo htmlspecialchars($apiEndpoint); ?>"
     data-csrf="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>"
     data-can-edit="<?php echo !empty($perm->edit) ? 1 : 0; ?>"
     data-can-delete="<?php echo !empty($perm->delete) ? 1 : 0; ?>">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
            <div>
                <?php echo generateBreadcrumb($conn, $currentUrl); ?>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-envelope-open-text me-2"></i>邮件模板管理</h4>
            </div>
            <?php if (!empty($perm->add)): ?>
            <button type="button" class="btn btn-primary" id="btnEmailTemplateAdd">
                <i class="fa-solid fa-plus me-1"></i>新增模板
            </button>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <form id="emailTemplateFilterForm" class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span>显示</span>
                    <select name="per_page" class="form-select" style="width: 90px;">
                        <?php foreach ($perPageOptions as $option): ?>
                            <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>项结果</span>
                </div>
                <div style="width: 380px; max-width: 100%;">
                    <input type="text" name="search" class="form-control" placeholder="搜索模板代码 / 名称 / 主题...">
                </div>
            </form>

            <div class="table-responsive">
                <table id="emailTemplateTable" class="table table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <?php foreach ($tableHeaders as $header): ?>
                                <?php 
                                $styleAttr = !empty($header['width']) ? ' style="width: ' . htmlspecialchars($header['width']) . ';"' : '';
                                $classAttr = !empty($header['class']) ? ' class="' . htmlspecialchars($header['class']) . '"' : '';
                                ?>
                                <th<?php echo $styleAttr . $classAttr; ?>><?php echo htmlspecialchars($header['label']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="emailTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">邮件模板</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="emailTemplateForm">
                <div class="modal-body">
                    <input type="hidden" name="id" value="0">
                    <input type="hidden" name="mode" value="create">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">模板代码 <span class="text-danger">*</span></label>
                            <input type="text" name="template_code" class="form-control" maxlength="50" required placeholder="例如：AUTHOR_APPROVED">
                            <small class="text-muted">建议仅使用大写字母、数字和下划线。</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">模板名称 <span class="text-danger">*</span></label>
                            <input type="text" name="template_name" class="form-control" maxlength="100" required placeholder="例如：作者审核通过通知">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">状态</label>
                            <select name="status" class="form-select">
                                <option value="A" selected>启用</option>
                                <option value="D">停用</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">邮件主题 <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control" maxlength="255" required placeholder="请输入邮件主题">
                        </div>
                        <div class="col-12">
                            <label class="form-label">邮件内容 <span class="text-danger">*</span></label>
                            <textarea name="content" class="form-control" rows="10" required placeholder="请输入邮件正文内容"></textarea>
                            <small class="text-muted d-block mt-1">可用变量：{{real_name}}、{{pen_name}}、{{reject_reason}}、{{dashboard_url}}</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$pageContent = ob_get_clean();

if ($isEmbeddedEmailTemplate) {
    echo $pageContent;
    return;
}
?>
<!DOCTYPE html>
<html lang="<?php echo defined('SITE_LANG') ? SITE_LANG : 'zh-CN'; ?>">
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
</head>
<body>
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>
<div class="container mt-4">
    <?php echo $pageContent; ?>
</div>
<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/admin.js"></script>
</body>
</html>
<?php
require_once dirname(__DIR__, 4) . '/common.php';

$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : 0);
$currentUrl = '/author/author-verification.php';
$auditPage = 'Author Verification Management';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . URL_LOGIN);
    exit();
}

// [NEW] Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$perm = hasPagePermission($conn, $currentUrl);
if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
    $legacyPath = defined('PATH_AUTHOR_VERIFICATION_INDEX') ? ('/' . ltrim(PATH_AUTHOR_VERIFICATION_INDEX, '/')) : '/src/pages/author/author-verification/index.php';
    $perm = hasPagePermission($conn, $legacyPath);
}
checkPermissionError('view', $perm, '作者审核管理');

$baseViewUrl = defined('URL_AUTHOR_VERIFICATION') ? URL_AUTHOR_VERIFICATION : (SITEURL . '/author/author-verification.php');
$apiEndpoint = defined('URL_AUTHOR_VERIFICATION_API') ? URL_AUTHOR_VERIFICATION_API : (SITEURL . '/src/pages/author/author-verification/api.php');

$totalApplications = 0;
$pendingApplications = 0;
$approvedAuthors = 0;
$rejectedApplications = 0;
$emailsSentToday = 0;

$countSummarySql = "SELECT "
    . "COUNT(*) AS total_applications, "
    . "SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) AS pending_applications, "
    . "SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) AS approved_authors, "
    . "SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_applications "
    . "FROM " . AUTHOR_PROFILE . " WHERE status = 'A'";
if ($res = $conn->query($countSummarySql)) {
    $row = $res->fetch_assoc();
    $totalApplications = (int)($row['total_applications'] ?? 0);
    $pendingApplications = (int)($row['pending_applications'] ?? 0);
    $approvedAuthors = (int)($row['approved_authors'] ?? 0);
    $rejectedApplications = (int)($row['rejected_applications'] ?? 0);
    $res->free();
}

$emailLogTable = defined('EMAIL_LOG') ? EMAIL_LOG : 'email_log';
$emailCountSql = "SELECT COUNT(*) AS total FROM " . $emailLogTable . " WHERE sent_status = 'success' AND DATE(created_at) = CURDATE()";
if ($res = $conn->query($emailCountSql)) {
    $row = $res->fetch_assoc();
    $emailsSentToday = (int)($row['total'] ?? 0);
    $res->free();
}

$approvalRate = $totalApplications > 0 ? round(($approvedAuthors / $totalApplications) * 100, 1) : 0;
$rejectionRate = $totalApplications > 0 ? round(($rejectedApplications / $totalApplications) * 100, 1) : 0;

$trendByDate = [];
$trendDateColumn = function_exists('columnExists') && columnExists($conn, AUTHOR_PROFILE, 'created_at') ? 'created_at' : 'updated_at';
if (function_exists('columnExists') && columnExists($conn, AUTHOR_PROFILE, $trendDateColumn)) {
    $trendSql = "SELECT DATE(" . $trendDateColumn . ") AS chart_date, COUNT(*) AS total "
        . "FROM " . AUTHOR_PROFILE . " "
        . "WHERE status = 'A' AND DATE(" . $trendDateColumn . ") >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) "
        . "GROUP BY DATE(" . $trendDateColumn . ")";
    if ($trendRes = $conn->query($trendSql)) {
        while ($trendRow = $trendRes->fetch_assoc()) {
            $key = (string)($trendRow['chart_date'] ?? '');
            if ($key !== '') {
                $trendByDate[$key] = (int)($trendRow['total'] ?? 0);
            }
        }
        $trendRes->free();
    }
}

$trendLabels = [];
$trendValues = [];
$today = new DateTime('today');
for ($offset = 29; $offset >= 0; $offset--) {
    $d = (clone $today)->sub(new DateInterval('P' . $offset . 'D'));
    $dayKey = $d->format('Y-m-d');
    $trendLabels[] = $d->format('m-d');
    $trendValues[] = isset($trendByDate[$dayKey]) ? (int)$trendByDate[$dayKey] : 0;
}
$trendMaxValue = max(1, !empty($trendValues) ? max($trendValues) : 0);

$isDashboardHidden = isset($_COOKIE['hide_author_verify_dashboard']) && $_COOKIE['hide_author_verify_dashboard'] === '1';

if (function_exists('logAudit') && !defined('AUTHOR_VERIFY_VIEW_LOGGED')) {
    define('AUTHOR_VERIFY_VIEW_LOGGED', true);
    logAudit([
        'page' => $auditPage,
        'action' => 'V',
        'action_message' => 'Viewing author verification dashboard',
        'query' => "SELECT * FROM " . AUTHOR_PROFILE . " WHERE status = 'A'",
        'query_table' => AUTHOR_PROFILE,
        'user_id' => $currentUserId
    ]);
}

if (!isset($customCSS) || !is_array($customCSS)) {
    $customCSS = [];
}
$customCSS[] = 'dataTables.bootstrap.min.css';
$customCSS[] = 'author.css';
$pageMetaKey = $currentUrl;

// --- Arrays for Rendering UI ---
$perPageOptions = [10, 20, 50, 100];

$statusFilterOptions = [
    'pending,rejected' => '待审核 + 驳回',
    'pending' => '仅待审核',
    'rejected' => '仅驳回',
    'approved' => '仅通过',
    'all' => '全部状态'
];

$tableHeaders = [
    ['label' => 'ID', 'width' => '70px', 'class' => ''],
    ['label' => '用户', 'width' => '', 'class' => ''],
    ['label' => '真实姓名', 'width' => '', 'class' => ''],
    ['label' => '笔名', 'width' => '', 'class' => ''],
    ['label' => '状态', 'width' => '110px', 'class' => ''],
    ['label' => '驳回原因', 'width' => '', 'class' => ''],
    ['label' => '通知次数', 'width' => '90px', 'class' => ''],
    ['label' => '更新时间', 'width' => '170px', 'class' => ''],
    ['label' => '操作', 'width' => '250px', 'class' => 'text-center']
];

$actionTypeOptions = [
    'approve' => '通过',
    'reject' => '驳回',
    'resend' => '重发通知'
];
// -------------------------------

ob_start();
?>
<div class="container-fluid px-0" id="authorVerificationApp" data-api-url="<?php echo htmlspecialchars($apiEndpoint); ?>" data-csrf="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
            <div>
                <?php echo generateBreadcrumb($conn, $currentUrl); ?>
                <h4 class="m-0 text-primary"><i class="fa-solid fa-user-check me-2"></i>作者审核管理</h4>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleAuthorVerifyDashboard">
                <?php echo $isDashboardHidden ? '显示统计面板' : '隐藏统计面板'; ?>
            </button>
        </div>

        <div class="card-body">
            <div id="authorVerifyDashboardPanel" style="display: <?php echo $isDashboardHidden ? 'none' : 'block'; ?>;">
                <div class="row g-3 mb-3">
                    <div class="col-md-6 col-lg-4 col-xl">
                        <div class="card border-primary h-100">
                            <div class="card-body">
                                <div class="text-muted small mb-1">Total Applications ｜ 总申请数</div>
                                <div class="h3 mb-0 text-primary"><?php echo (int)$totalApplications; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4 col-xl">
                        <div class="card border-warning h-100">
                            <div class="card-body">
                                <div class="text-muted small mb-1">Pending Applications ｜ 待审核数</div>
                                <div class="h3 mb-0 text-warning"><?php echo (int)$pendingApplications; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4 col-xl">
                        <div class="card border-success h-100">
                            <div class="card-body">
                                <div class="text-muted small mb-1">Approved Authors ｜ 已通过作者</div>
                                <div class="h3 mb-0 text-success"><?php echo (int)$approvedAuthors; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6 col-xl">
                        <div class="card border-danger h-100">
                            <div class="card-body">
                                <div class="text-muted small mb-1">Rejected Applications ｜ 已拒绝数</div>
                                <div class="h3 mb-0 text-danger"><?php echo (int)$rejectedApplications; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6 col-xl">
                        <div class="card border-info h-100">
                            <div class="card-body">
                                <div class="text-muted small mb-1">Emails Sent Today ｜ 今日发送邮件数</div>
                                <div class="h3 mb-0 text-info"><?php echo (int)$emailsSentToday; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">Application Trend (Last 30 Days) ｜ 近30天申请趋势</div>
                            <div class="card-body">
                                <div class="d-flex align-items-end justify-content-between" style="height: 140px; gap: 4px;">
                                    <?php foreach ($trendValues as $idx => $v): ?>
                                        <?php $barHeight = $v > 0 ? max(6, (int)round(($v / $trendMaxValue) * 120)) : 4; ?>
                                        <div class="bg-primary" title="<?php echo htmlspecialchars($trendLabels[$idx] . ': ' . $v); ?>" style="width: 100%; max-width: 16px; height: <?php echo $barHeight; ?>px; border-radius: 3px;"></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="d-flex justify-content-between mt-2 small text-muted">
                                    <span><?php echo htmlspecialchars($trendLabels[0] ?? ''); ?></span>
                                    <span><?php echo htmlspecialchars($trendLabels[count($trendLabels) - 1] ?? ''); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="card h-100">
                            <div class="card-header bg-light">Approval Rate ｜ 通过率</div>
                            <div class="card-body d-flex flex-column justify-content-center">
                                <div class="h2 mb-2 text-success"><?php echo number_format($approvalRate, 1); ?>%</div>
                                <div class="progress" role="progressbar" aria-label="Approval Rate" aria-valuenow="<?php echo (int)$approvalRate; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-success" style="width: <?php echo min(100, max(0, $approvalRate)); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="card h-100">
                            <div class="card-header bg-light">Rejection Rate ｜ 拒绝率</div>
                            <div class="card-body d-flex flex-column justify-content-center">
                                <div class="h2 mb-2 text-danger"><?php echo number_format($rejectionRate, 1); ?>%</div>
                                <div class="progress" role="progressbar" aria-label="Rejection Rate" aria-valuenow="<?php echo (int)$rejectionRate; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-danger" style="width: <?php echo min(100, max(0, $rejectionRate)); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form id="authorVerifyFilterForm" class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span>显示</span>
                    <select name="per_page" class="form-select" style="width: 90px;">
                        <?php foreach ($perPageOptions as $option): ?>
                            <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>项结果</span>
                </div>
                <div class="d-flex align-items-center gap-2" style="width: 520px; max-width: 100%;">
                    <select name="status_filter" class="form-select" style="max-width: 200px;">
                        <?php foreach ($statusFilterOptions as $val => $label): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $val === 'pending,rejected' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="search" class="form-control" placeholder="搜索真实姓名 / 笔名 / 邮箱...">
                </div>
            </form>

            <div class="table-responsive">
                <table id="authorVerificationTable" class="table table-hover align-middle w-100">
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

<div class="modal fade" id="authorVerifyActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">作者审核操作</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="authorVerifyActionForm">
                <div class="modal-body">
                    <input type="hidden" name="id" value="0">
                    <div class="mb-3">
                        <label class="form-label">操作类型</label>
                        <select name="action_type" class="form-select" required>
                            <?php foreach ($actionTypeOptions as $val => $label): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="authorVerifyRejectReasonWrap" style="display:none;">
                        <label class="form-label">驳回原因 <span class="text-danger">*</span></label>
                        <textarea name="reject_reason" class="form-control" rows="4" placeholder="请填写驳回原因"></textarea>
                    </div>
                    <div class="alert alert-light border" id="authorVerifyActionHint">请确认审核操作后提交。</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">确认提交</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$pageContent = ob_get_clean();
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
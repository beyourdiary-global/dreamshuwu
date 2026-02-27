<?php
// Path: src/pages/author/chapter-management/index.php
require_once dirname(__DIR__, 4) . '/common.php';

if (empty(session('csrf_token'))) {
    setSession('csrf_token', bin2hex(random_bytes(32)));
}

requireLogin();

$currentUserId = sessionInt('user_id');
requireApprovedAuthor($conn, $currentUserId);

$auditPage = 'Chapter Management';

// Permission Checking Logic
if (defined('URL_AUTHOR_CHAPTER_MANAGEMENT')) {
    $menuUrl = str_replace('{id}/', '', parse_url(URL_AUTHOR_CHAPTER_MANAGEMENT, PHP_URL_PATH));
} else {
    $menuUrl = '/author/novel/chapters/';
}

$perm = hasPagePermission($conn, $menuUrl);

// Fallback 1: Check Physical Path
if (empty($perm)) {
    $systemPath = defined('PATH_AUTHOR_CHAPTER_MANAGEMENT') ? PATH_AUTHOR_CHAPTER_MANAGEMENT : '/src/pages/author/chapter-management/index.php';
    $perm = hasPagePermission($conn, $systemPath);
}

// Fallback 2: Check Novel Management
if (empty($perm) || (isset($perm->view) && empty($perm->view))) {
    $legacyPath = defined('PATH_AUTHOR_NOVEL_MANAGEMENT') ? ('/' . ltrim(PATH_AUTHOR_NOVEL_MANAGEMENT, '/')) : '/src/pages/author/novel-management/index.php';
    $perm = hasPagePermission($conn, $legacyPath);
}

// Block access if no View permission
if (empty($perm) || empty($perm->view)) {
    $fallbackUrl = defined('URL_AUTHOR_DASHBOARD') ? URL_AUTHOR_DASHBOARD : '/author/dashboard.php';
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>权限不足</title><script src="' . URL_ASSETS . '/js/sweetalert2@11.js"></script><style>body{background:#f4f7f6;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}</style></head><body><script>Swal.fire({icon:"error",title:"权限不足",text:"您没有权限访问此页面 (No View Permission)。",confirmButtonText:"返回首页",confirmButtonColor:"#dc3545",allowOutsideClick:false,allowEscapeKey:false}).then(function(){window.location.href="'.$fallbackUrl.'";});</script></body></html>';
    exit();
}

$canAdd = !empty($perm->add);
$canEdit = !empty($perm->edit);
$canDelete = !empty($perm->delete);

$novelId = (int)numberInput('novel_id');
if ($novelId <= 0) {
    die("无效的小说ID (Invalid Novel ID)");
}

// Pre-define view query for execution and audit logging
$novelSql = "SELECT title, cover_image, completion_status, tags, introduction FROM " . NOVEL . " WHERE id = ? AND author_id = ? AND status = 'A' LIMIT 1";
$stmt = $conn->prepare($novelSql);
if (!$stmt) {
    die("Database Error: 无法准备小说查询语句。请检查线上数据库 NOVEL 表中是否存在 'cover_image', 'completion_status', 'tags', 'introduction' 这些列。错误信息: " . $conn->error);
}
$stmt->bind_param("ii", $novelId, $currentUserId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    $fallbackUrl = defined('URL_AUTHOR_NOVEL_MANAGEMENT') ? URL_AUTHOR_NOVEL_MANAGEMENT : '/author/dashboard.php';
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>访问受限</title><script src="' . URL_ASSETS . '/js/sweetalert2@11.js"></script><style>body { background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }</style></head><body><script>Swal.fire({icon: "error",title: "访问受限",text: "该小说不存在或您无权访问 (Access Denied)。",confirmButtonText: "返回我的小说",confirmButtonColor: "#dc3545",allowOutsideClick: false,allowEscapeKey: false}).then(function() {window.location.href = "' . $fallbackUrl . '";});</script></body></html>';
    exit();
}

$nTitle = $nCover = $nStatus = $nTags = $nIntro = null;
$stmt->bind_result($nTitle, $nCover, $nStatus, $nTags, $nIntro);
$stmt->fetch();
$stmt->close();

$coverUrl = $nCover ? (URL_ASSETS . '/uploads/novel_covers/' . htmlspecialchars($nCover)) : (URL_ASSETS . '/images/no-cover.png');

// Log the "View" action dynamically
if (function_exists('logAudit')) {
        logAudit([
            'page'           => $auditPage,
            'action'         => 'V',
            'action_message' => "Viewing Chapter Management for Novel ID: $novelId",
            'query'          => $novelSql,
            'query_table'    => NOVEL,
            'user_id'        => $currentUserId
        ]);
    }

// Fetch Chapter Statistics
$statSql = "SELECT 
    COUNT(id) as total_chapters, 
    SUM(word_count) as total_words,
    SUM(CASE WHEN publish_status = 'published' THEN 1 ELSE 0 END) as published_count,
    SUM(CASE WHEN publish_status = 'draft' THEN 1 ELSE 0 END) as draft_count
    FROM " . CHAPTER . " WHERE novel_id = ? AND status = 'A'";
$stmt = $conn->prepare($statSql);
if (!$stmt) {
    die("Database Error: 无法准备章节统计查询。请检查线上数据库 CHAPTER 表中是否存在 'publish_status' 或 'word_count' 这些列。错误信息: " . $conn->error);
}
$stmt->bind_param("i", $novelId);
$stmt->execute();
$statTotalChapters = $statTotalWords = $statPublished = $statDrafts = 0;
$stmt->bind_result($statTotalChapters, $statTotalWords, $statPublished, $statDrafts);
$stmt->fetch();
$stmt->close();

$statTotalChapters = $statTotalChapters ?? 0;
$statTotalWords = $statTotalWords ?? 0;
$statPublished = $statPublished ?? 0;
$statDrafts = $statDrafts ?? 0;

$apiEndpoint = SITEURL . '/src/pages/author/chapter-management/api.php';
$currentUrl = '/author/novel/' . $novelId . '/chapters/';
?>
<!DOCTYPE html>
<head>
    <?php require_once BASE_PATH . 'include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/dataTables.bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/author.css">
    <style>
        .strict-textarea { resize: vertical; font-family: monospace; font-size: 15px; line-height: 1.6; }
        .word-warning { color: #dda20a; }
        .word-danger { color: #dc3545; }
    </style>
    <script>
        const PERM_CAN_ADD = <?php echo $canAdd ? 'true' : 'false'; ?>;
        const PERM_CAN_EDIT = <?php echo $canEdit ? 'true' : 'false'; ?>;
        const PERM_CAN_DELETE = <?php echo $canDelete ? 'true' : 'false'; ?>;
    </script>
</head>
<body style="background-color: #f4f7f6;">
<?php require_once BASE_PATH . 'common/menu/header.php'; ?>

<div class="container main-content" id="chapterApp" style="max-width: 1200px; margin: 30px auto; padding: 0 20px; min-height: 80vh;"
     data-api-url="<?php echo htmlspecialchars($apiEndpoint); ?>"
     data-novel-id="<?php echo $novelId; ?>">

    <?php echo generateBreadcrumb($conn, $currentUrl, '章节管理'); ?>
    
    <div class="card border-0 shadow-sm mb-4 mt-3" style="border-radius: 12px; overflow: hidden;">
        <div class="card-body p-4 d-flex flex-column flex-md-row align-items-start align-items-md-center">
            <img src="<?php echo $coverUrl; ?>" alt="Cover" class="rounded shadow-sm mb-3 mb-md-0 me-md-4" style="width: 100px; height: 133px; object-fit: cover;">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center mb-2">
                    <h4 class="text-dark fw-bold mb-0 me-3">《<?php echo htmlspecialchars($nTitle); ?>》</h4>
                    <?php if($nStatus === 'ongoing'): ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">连载中</span>
                    <?php else: ?>
                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">已完结</span>
                    <?php endif; ?>
                </div>
                <div class="mb-2">
                    <?php 
                    $tagsArr = explode(',', (string)$nTags);
                    foreach($tagsArr as $tag) {
                        if(trim($tag) !== '') echo '<span class="badge bg-light text-secondary border me-1">'.htmlspecialchars(trim($tag)).'</span>';
                    }
                    ?>
                </div>
                <p class="text-muted small mb-0" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                    <?php echo htmlspecialchars($nIntro); ?>
                </p>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
            <div class="card border-0 shadow-sm text-center h-100 py-3" style="border-radius: 12px; border-bottom: 4px solid #4e73df !important;">
                <div class="text-muted small fw-bold mb-1">总字数 (Total Words)</div>
                <h3 class="text-dark fw-bold mb-0"><?php echo number_format($statTotalWords); ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
            <div class="card border-0 shadow-sm text-center h-100 py-3" style="border-radius: 12px; border-bottom: 4px solid #1cc88a !important;">
                <div class="text-muted small fw-bold mb-1">总章节 (Total Chapters)</div>
                <h3 class="text-dark fw-bold mb-0"><?php echo number_format($statTotalChapters); ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
            <div class="card border-0 shadow-sm text-center h-100 py-3" style="border-radius: 12px; border-bottom: 4px solid #36b9cc !important;">
                <div class="text-muted small fw-bold mb-1">已发布 (Published)</div>
                <h3 class="text-dark fw-bold mb-0"><?php echo number_format($statPublished); ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
            <div class="card border-0 shadow-sm text-center h-100 py-3" style="border-radius: 12px; border-bottom: 4px solid #858796 !important;">
                <div class="text-muted small fw-bold mb-1">草稿箱 (Drafts)</div>
                <h3 class="text-dark fw-bold mb-0"><?php echo number_format($statDrafts); ?></h3>
            </div>
        </div>
    </div>

    <?php if ($canAdd || $canEdit): ?>
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #f0f2f5;">
            <h5 class="m-0 fw-bold text-primary"><i class="fa-solid fa-pen-nib me-2"></i>章节编辑器 (Strict Editor)</h5>
            <span id="autoSaveIndicator" class="badge bg-light text-muted border d-none"><i class="fa-solid fa-clock-rotate-left me-1"></i> 已自动保存 <span id="autoSaveTime"></span></span>
        </div>
        <div class="card-body p-4">
            <form id="chapterForm">
                <input type="hidden" name="csrf_token" value="<?php echo session('csrf_token'); ?>">
                <input type="hidden" name="novel_id" value="<?php echo $novelId; ?>">
                <input type="hidden" name="chapter_id" id="edit_chapter_id" value="0">
                
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">章节标题 Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="chapter_title" class="form-control" maxlength="255" required placeholder="例如：第1章 归来">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">章节排序 Number <span class="text-danger">*</span></label>
                        <input type="number" name="chapter_number" id="chapter_number" class="form-control" required min="1" value="1">
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-end mb-2">
                        <label class="form-label fw-bold m-0">纯文本正文 Content <span class="text-danger">*</span></label>
                        <div class="small fw-bold" id="wordCountBox">字数统计: <span id="wordCountNum">0</span> 词</div>
                    </div>
                    <textarea name="content" id="chapter_content" class="form-control strict-textarea" rows="15" required placeholder="请在此输入章节正文。禁止使用HTML、链接或特殊表情..."></textarea>
                    <small id="wordWarningText" class="mt-1 d-block text-muted">字数限制：最少300字，超过5000字建议拆分，最大限制50,000字。</small>
                </div>

                <div class="row align-items-center bg-light p-3 rounded">
                    <div class="col-md-4 mb-2 mb-md-0">
                        <label class="form-label mb-1">发布选项 Publish Options</label>
                        <select name="publish_status" id="publish_status" class="form-select">
                            <option value="draft">保存为草稿 (Draft)</option>
                            <option value="published">立即发布 (Publish Now)</option>
                            <option value="scheduled">定时发布 (Scheduled)</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2 mb-md-0 d-none" id="scheduleTimeContainer">
                        <label class="form-label mb-1">定时时间 Scheduled Time</label>
                        <input type="datetime-local" name="scheduled_publish_at" id="scheduled_publish_at" class="form-control">
                    </div>
                    <div class="col-md-4 text-end mt-auto">
                        <button type="button" class="btn btn-secondary me-2" id="btnResetEditor"><i class="fa-solid fa-eraser"></i> 清空</button>
                        <button type="button" class="btn btn-primary fw-bold" id="btnSaveChapter"><i class="fa-solid fa-cloud-arrow-up"></i> 保存章节</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white py-3" style="border-bottom: 1px solid #f0f2f5;">
            <h5 class="m-0 fw-bold text-dark"><i class="fa-solid fa-list me-2"></i>章节列表 (Chapter List)</h5>
        </div>
        <div class="card-body p-4">
            <table id="chapterTable" class="table table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">排序</th>
                        <th>章节标题</th>
                        <th>字数</th>
                        <th>状态</th>
                        <th>定时时间</th>
                        <th>版本</th>
                        <th>最后更新</th>
                        <th class="text-center" style="width: 150px;">操作</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="versionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">历史版本 (Version History)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>版本号</th>
                            <th>时间</th>
                            <th>字数</th>
                            <th class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody id="versionTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo URL_ASSETS; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/chapter.js"></script>
</body>
</html>
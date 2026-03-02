<?php
// Path: src/pages/metaSetting/index.php
require_once dirname(__DIR__, 3) . '/common.php';

// Auth Check
requireLogin();

$currentUrl = '/dashboard.php?view=meta_settings'; 
$perm = hasPagePermission($conn, $currentUrl);
checkPermissionError('view', $perm);

$auditPage = 'Meta Settings';
$auditUserId = sessionInt('user_id');

$message = ""; 
$msgType = "";
$pageMessage = "";
$pageMsgType = "";

$metaTable = defined('META_SETTINGS') ? META_SETTINGS : 'meta_settings';
$pageMetaTable = defined('META_SETTINGS_PAGE') ? META_SETTINGS_PAGE : 'meta_settings_page';
$PAGE_META_REGISTRY = getDynamicPageRegistry($conn);

// --- DEFINED QUERIES ---
$sqlGlobalCheck  = "SELECT meta_title, meta_description, og_title, og_description, og_url FROM $metaTable WHERE page_type = 'global' AND page_id = 0 LIMIT 1";
$sqlGlobalUpdate = "UPDATE $metaTable SET meta_title = ?, meta_description = ?, og_title = ?, og_description = ?, og_url = ? WHERE page_type = 'global' AND page_id = 0";
$sqlGlobalInsert = "INSERT INTO $metaTable (page_type, page_id, meta_title, meta_description, og_title, og_description, og_url) VALUES ('global', 0, ?, ?, ?, ?, ?)";

$sqlPageCheck    = "SELECT meta_title, meta_description, og_title, og_description, og_url FROM $pageMetaTable WHERE page_key = ? LIMIT 1";
$sqlPageUpdate   = "UPDATE $pageMetaTable SET meta_title = ?, meta_description = ?, og_title = ?, og_description = ?, og_url = ? WHERE page_key = ?";
$sqlPageInsert   = "INSERT INTO $pageMetaTable (page_key, meta_title, meta_description, og_title, og_description, og_url) VALUES (?, ?, ?, ?, ?, ?)";
$sqlPageDelete   = "DELETE FROM $pageMetaTable WHERE page_key = ?";

$seoFields = [
    'meta_title' => ['label' => 'Meta Title', 'type' => 'input'],
    'meta_description' => ['label' => 'Meta Description', 'type' => 'textarea'],
    'og_title' => ['label' => 'OG Title', 'type' => 'input'],
    'og_description' => ['label' => 'OG Description', 'type' => 'textarea'],
    'og_url' => ['label' => 'OG Url', 'type' => 'input']
];

$activeSection = input('section') ?: 'global';
$formType = (string) post('form_type');
if (input('page') !== '' || strpos($formType, 'page') !== false) {
    $activeSection = 'page';
}

if (session('flash_msg') !== '') {
    if ($activeSection === 'page') {
        $pageMessage = session('flash_msg');
        $pageMsgType = session('flash_type');
    } else {
        $message = session('flash_msg');
        $msgType = session('flash_type');
    }
    unsetSession('flash_msg');
    unsetSession('flash_type');
}

if (!isPostRequest() && function_exists('logAudit')){
    $viewQuery = $activeSection === 'page'
        ? "SELECT meta_title, meta_description, og_title, og_description, og_url FROM $pageMetaTable WHERE page_key = ?"
        : "SELECT meta_title, meta_description, og_title, og_description, og_url FROM $metaTable WHERE page_type = 'global' AND page_id = 0";

    $viewMessage = $activeSection === 'page'
        ? "Viewing Page Meta Settings (page_key: " . input('page') . ")"
        : "Viewing Global Meta Settings";

    logAudit([
        'page'           => $auditPage,
        'action'         => 'V',
        'action_message' => $viewMessage,
        'query'          => $viewQuery,
        'query_table'    => $activeSection === 'page' ? $pageMetaTable : $metaTable,
        'user_id'        => $auditUserId
    ]);
}

$isEmbeddedMeta = ($EMBED_META_PAGE ?? false) === true;
$metaBaseUrl = $isEmbeddedMeta ? (URL_USER_DASHBOARD . '?view=meta_settings') : URL_META_SETTINGS;

// ========== HANDLE POST REQUESTS ==========
if (isPostRequest()) {
    
    // 1. GLOBAL POST
    if (post('form_type') === 'global') {
        checkPermissionError('edit', $perm);

        $reqFields = ['meta_title', 'meta_description', 'og_title', 'og_description', 'og_url'];
        $emptyCount = 0;
        foreach ($reqFields as $f) {
            if (postSpaceFilter($f) === '') $emptyCount++;
        }

        if ($emptyCount === 5) {
            $message = "保存失败: 不能保存空数据 (Cannot save empty data)。";
            $msgType = "danger";
        } elseif ($emptyCount > 0) {
            $message = "保存失败: 所有字段都是必填的 (All fields are compulsory)。";
            $msgType = "danger";
        } else {
            $oldGlobal = null;
            $hasGlobal = false;
            
            $checkStmt = $conn->prepare($sqlGlobalCheck);
            if ($checkStmt) {
                $checkStmt->execute();
                $checkStmt->store_result();
                if ($checkStmt->num_rows > 0) {
                    $hasGlobal = true;
                    $oTitle = $oDesc = $oOgTitle = $oOgDesc = $oOgUrl = null;
                    $checkStmt->bind_result($oTitle, $oDesc, $oOgTitle, $oOgDesc, $oOgUrl);
                    $checkStmt->fetch();
                    $oldGlobal = ['meta_title' => $oTitle, 'meta_description' => $oDesc, 'og_title' => $oOgTitle, 'og_description' => $oOgDesc, 'og_url' => $oOgUrl];
                }
                $checkStmt->close();
            }

            // Prepare Clean Data for usage
            $newGlobal = [
                'meta_title' => post('meta_title'),
                'meta_description' => post('meta_description'),
                'og_title' => post('og_title'),
                'og_description' => post('og_description'),
                'og_url' => post('og_url'),
            ];

            if ($hasGlobal) {
                checkNoChangesAndRedirect($newGlobal, $oldGlobal, null);
            }

            $sql = $hasGlobal ? $sqlGlobalUpdate : $sqlGlobalInsert;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $newGlobal['meta_title'], $newGlobal['meta_description'], $newGlobal['og_title'], $newGlobal['og_description'], $newGlobal['og_url']);
            
            if ($stmt->execute()) {
                $message = "全局设置保存成功！";
                $msgType = "success";

                if (function_exists('logAudit')) {
                    logAudit([
                        'page' => $auditPage,
                        'action' => $hasGlobal ? 'E' : 'A',
                        'action_message' => $hasGlobal ? 'Updated Global Meta Settings' : 'Added Global Meta Settings',
                        'query' => $sql,
                        'query_table' => $metaTable,
                        'user_id' => $auditUserId,
                        'record_id' => 0,
                        'record_name' => 'global',
                        'old_value' => $oldGlobal,
                        'new_value' => $newGlobal
                    ]);
                }
            } else {
                $message = "保存失败: " . $conn->error;
                $msgType = "danger";
            }
            $stmt->close();
        }
    }

    // 2. PAGE POST
    if (post('form_type') === 'page') {
        checkPermissionError('edit', $perm);

        $pKey = post('page_key');
        if ($pKey !== '' && array_key_exists($pKey, $PAGE_META_REGISTRY)) {

            $reqFields = ['page_meta_title', 'page_meta_description', 'page_og_title', 'page_og_description', 'page_og_url'];
            $emptyCount = 0;
            foreach ($reqFields as $f) {
                if (postSpaceFilter($f) === '') $emptyCount++;
            }

            if ($emptyCount === 5) {
                $pageMessage = "保存失败: 不能保存空数据 (Cannot save empty data)。";
                $pageMsgType = "danger";
            } elseif ($emptyCount > 0) {
                $pageMessage = "保存失败: 所有字段都是必填的 (All fields are compulsory)。";
                $pageMsgType = "danger";
            } else {
                $oldPage = null;
                $hasPage = false;
                
                $checkStmt = $conn->prepare($sqlPageCheck);
                if ($checkStmt) {
                    $checkStmt->bind_param("s", $pKey);
                    $checkStmt->execute();
                    $checkStmt->store_result();
                    if ($checkStmt->num_rows > 0) {
                        $hasPage = true;
                        $oTitle = $oDesc = $oOgTitle = $oOgDesc = $oOgUrl = null;
                        $checkStmt->bind_result($oTitle, $oDesc, $oOgTitle, $oOgDesc, $oOgUrl);
                        $checkStmt->fetch();
                        $oldPage = ['page_key' => $pKey, 'meta_title' => $oTitle, 'meta_description' => $oDesc, 'og_title' => $oOgTitle, 'og_description' => $oOgDesc, 'og_url' => $oOgUrl];
                    }
                    $checkStmt->close();
                }

                // Prepare Clean Data for usage
                $newPage = [
                    'meta_title' => post('page_meta_title'),
                    'meta_description' => post('page_meta_description'),
                    'og_title' => post('page_og_title'),
                    'og_description' => post('page_og_description'),
                    'og_url' => post('page_og_url'),
                ];

                if ($hasPage) {
                    checkNoChangesAndRedirect($newPage, $oldPage, null);
                }

                if ($hasPage) {
                    $sql = $sqlPageUpdate;
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssss", $newPage['meta_title'], $newPage['meta_description'], $newPage['og_title'], $newPage['og_description'], $newPage['og_url'], $pKey);
                } else {
                    $sql = $sqlPageInsert;
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssss", $pKey, $newPage['meta_title'], $newPage['meta_description'], $newPage['og_title'], $newPage['og_description'], $newPage['og_url']);
                }
                
                if ($stmt->execute()) {
                    $pageMessage = "页面设置保存成功！";
                    $pageMsgType = "success";

                    if (function_exists('logAudit')) {
                        $auditData = $newPage;
                        $auditData['page_key'] = $pKey;
                        logAudit([
                            'page' => $auditPage,
                            'action' => $hasPage ? 'E' : 'A',
                            'action_message' => $hasPage ? 'Updated Page Meta Settings' : 'Added Page Meta Settings',
                            'query' => $sql,
                            'query_table' => $pageMetaTable,
                            'user_id' => $auditUserId,
                            'record_id' => 0,
                            'record_name' => $pKey,
                            'old_value' => $oldPage,
                            'new_value' => $auditData
                        ]);
                    }
                } else {
                    $pageMessage = "保存失败: " . $conn->error;
                    $pageMsgType = "danger";
                }
                $stmt->close();
            }
        }
    }

    // 3. DELETE PAGE POST
    if (post('form_type') === 'delete_page') {
        checkPermissionError('delete', $perm);

        $delKey = post('page_key');
        if ($delKey !== '' && array_key_exists($delKey, $PAGE_META_REGISTRY)) {
            $oldPage = null;
            $checkStmt = $conn->prepare($sqlPageCheck);
            if ($checkStmt) {
                $checkStmt->bind_param("s", $delKey);
                $checkStmt->execute();
                $checkStmt->store_result();
                if ($checkStmt->num_rows > 0) {
                    $oTitle = $oDesc = $oOgTitle = $oOgDesc = $oOgUrl = null;
                    $checkStmt->bind_result($oTitle, $oDesc, $oOgTitle, $oOgDesc, $oOgUrl);
                    $checkStmt->fetch();
                    $oldPage = ['page_key' => $delKey, 'meta_title' => $oTitle, 'meta_description' => $oDesc, 'og_title' => $oOgTitle, 'og_description' => $oOgDesc, 'og_url' => $oOgUrl];
                }
                $checkStmt->close();
            }

            $sql = $sqlPageDelete;
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $delKey);
                if ($stmt->execute()) {
                    $pageMessage = "已恢复为全局默认设置！";
                    $pageMsgType = "success";

                    if (function_exists('logAudit')) {
                        logAudit([
                            'page' => $auditPage,
                            'action' => 'D',
                            'action_message' => 'Deleted Page Meta Settings',
                            'query' => $sql,
                            'query_table' => $pageMetaTable,
                            'user_id' => $auditUserId,
                            'record_id' => 0,
                            'record_name' => $delKey,
                            'old_value' => $oldPage
                        ]);
                    }
                } else {
                    $pageMessage = "删除失败: " . $conn->error;
                    $pageMsgType = "danger";
                }
                $stmt->close();
            }
        }
    }
}

// ========== FETCH DATA ==========
$current = array_fill_keys(array_keys($seoFields), '');
$res = $conn->query("SELECT * FROM $metaTable WHERE page_type = 'global' AND page_id = 0 LIMIT 1");
if ($res && $row = $res->fetch_assoc()) $current = $row;

$selectedPageKey = post('page_key') ?: input('page');
$pageCurrent = array_fill_keys(array_keys($seoFields), '');

if ($selectedPageKey !== '' && function_exists('getPageMetaSettings')) {
    $pm = getPageMetaSettings($conn, $selectedPageKey);
    if ($pm) $pageCurrent = $pm;
}

$customizedPages = [];
$cpRes = $conn->query("SELECT page_key FROM $pageMetaTable");
while ($cpRes && $row = $cpRes->fetch_assoc()) $customizedPages[] = $row['page_key'];

// ========== RENDER ==========
if ($isEmbeddedMeta):
    $pageScripts = ['src/pages/meta/js/meta.js'];
?>
    <link rel="stylesheet" href="<?php echo SITEURL; ?>/src/pages/meta/css/meta.css">
    <div class="meta-settings-container" style="max-width: 1000px; margin: 0 auto;">

        <?php echo generateBreadcrumb($conn, $currentUrl); ?>

        <div class="d-flex justify-content-center">
            <div class="nav nav-pills nav-pills-container mb-4">
                <a class="nav-link <?php echo $activeSection === 'global' ? 'active' : ''; ?>" 
                   href="<?php echo $metaBaseUrl; ?>&section=global">
                   <i class="fa-solid fa-globe"></i> Whole Website
                </a>
                <a class="nav-link <?php echo $activeSection === 'page' ? 'active' : ''; ?>" 
                   href="<?php echo $metaBaseUrl; ?>&section=page">
                   <i class="fa-solid fa-file-lines"></i> Each Page
                </a>
            </div>
        </div>

        <?php if ($activeSection === 'global'): ?>
            <?php require __DIR__ . '/metaSetting/globalMetaSetting.php'; ?>
        <?php endif; ?>

        <?php if ($activeSection === 'page'): ?>
            <?php require __DIR__ . '/metaSetting/pageMetaSetting.php'; ?>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!DOCTYPE html>
    <head>
        <?php require_once BASE_PATH . 'include/header.php'; ?>
        <link rel="stylesheet" href="<?php echo SITEURL; ?>/src/pages/meta/css/meta.css">
    </head>
    <body>
    <?php require_once BASE_PATH . 'common/menu/header.php'; ?>

    <div class="container mt-4" style="max-width: 1000px;">
        <?php echo generateBreadcrumb($conn, $currentUrl); ?>
        
        <div class="d-flex justify-content-center">
            <div class="nav nav-pills nav-pills-container mb-4">
                <a class="nav-link <?php echo $activeSection === 'global' ? 'active' : ''; ?>" 
                   href="?section=global">
                   <i class="fa-solid fa-globe"></i> Whole Website
                </a>
                <a class="nav-link <?php echo $activeSection === 'page' ? 'active' : ''; ?>" 
                   href="?section=page">
                   <i class="fa-solid fa-file-lines"></i> Each Page
                </a>
            </div>
        </div>

        <?php if ($activeSection === 'global'): ?>
            <?php require __DIR__ . '/metaSetting/globalMetaSetting.php'; ?>
        <?php endif; ?>

        <?php if ($activeSection === 'page'): ?>
            <?php require __DIR__ . '/metaSetting/pageMetaSetting.php'; ?>
        <?php endif; ?>

    </div>

    <script src="<?php echo SITEURL; ?>/src/pages/meta/js/meta.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
<?php endif; ?>
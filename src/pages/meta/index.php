<?php
// Path: src/pages/metaSetting/index.php
require_once dirname(__DIR__, 3) . '/common.php';

// 1. Identify this specific view's URL as registered in your DB
$currentUrl = '/dashboard.php?view=meta_settings'; 

// [ADDED] Fetch the dynamic permission object for this page
$perm = hasPagePermission($conn, $currentUrl);

// 2. Check if the user has View permission for this view
if (empty($perm) || !$perm->view) {
    denyAccess("权限不足：您没有访问 Meta 设置页面的权限。");
}

// Auth Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: " . URL_LOGIN);
    exit();
}

$auditPage = 'Meta Settings';
$auditUserId = $_SESSION['user_id'] ?? 0;

$message = ""; 
$msgType = "";
$pageMessage = "";
$pageMsgType = "";

// Ensure constants are defined
$metaTable = defined('META_SETTINGS') ? META_SETTINGS : 'meta_settings';
$pageMetaTable = defined('META_SETTINGS_PAGE') ? META_SETTINGS_PAGE : 'meta_settings_page';

// [ADDED] Dynamically fetch the Page Registry using our new bind_result helper
$PAGE_META_REGISTRY = getDynamicPageRegistry($conn);

// --- DEFINED QUERIES  ---
// Global Queries
$sqlGlobalCheck  = "SELECT meta_title, meta_description, og_title, og_description, og_url FROM $metaTable WHERE page_type = 'global' AND page_id = 0 LIMIT 1";
$sqlGlobalUpdate = "UPDATE $metaTable SET meta_title = ?, meta_description = ?, og_title = ?, og_description = ?, og_url = ? WHERE page_type = 'global' AND page_id = 0";
$sqlGlobalInsert = "INSERT INTO $metaTable (page_type, page_id, meta_title, meta_description, og_title, og_description, og_url) VALUES ('global', 0, ?, ?, ?, ?, ?)";

// Page Specific Queries
$sqlPageCheck    = "SELECT meta_title, meta_description, og_title, og_description, og_url FROM $pageMetaTable WHERE page_key = ? LIMIT 1";
$sqlPageUpdate   = "UPDATE $pageMetaTable SET meta_title = ?, meta_description = ?, og_title = ?, og_description = ?, og_url = ? WHERE page_key = ?";
$sqlPageInsert   = "INSERT INTO $pageMetaTable (page_key, meta_title, meta_description, og_title, og_description, og_url) VALUES (?, ?, ?, ?, ?, ?)";
$sqlPageDelete   = "DELETE FROM $pageMetaTable WHERE page_key = ?";
// ------------------------------------------

// SEO Fields Config
$seoFields = [
    'meta_title' => ['label' => 'Meta Title', 'type' => 'input'],
    'meta_description' => ['label' => 'Meta Description', 'type' => 'textarea'],
    'og_title' => ['label' => 'OG Title', 'type' => 'input'],
    'og_description' => ['label' => 'OG Description', 'type' => 'textarea'],
    'og_url' => ['label' => 'OG Url', 'type' => 'input']
];

// Determine Active Section (Default to 'global')
$activeSection = $_GET['section'] ?? 'global';
// If a page is selected or page form submitted, force section to 'page'
if (isset($_GET['page']) || (isset($_POST['form_type']) && strpos($_POST['form_type'], 'page') !== false)) {
    $activeSection = 'page';
}

// Flash Message Check (Reads message after redirect)
if (isset($_SESSION['flash_msg'])) {
    if ($activeSection === 'page') {
        $pageMessage = $_SESSION['flash_msg'];
        $pageMsgType = $_SESSION['flash_type'];
    } else {
        $message = $_SESSION['flash_msg'];
        $msgType = $_SESSION['flash_type'];
    }
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// [AUDIT] Log View Action (Only for GET requests)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && function_exists('logAudit') && !defined('META_SETTINGS_VIEW_LOGGED')) {
    define('META_SETTINGS_VIEW_LOGGED', true);
    $viewQuery = $activeSection === 'page'
        ? "SELECT meta_title, meta_description, og_title, og_description, og_url FROM $pageMetaTable WHERE page_key = ?"
        : "SELECT meta_title, meta_description, og_title, og_description, og_url FROM $metaTable WHERE page_type = 'global' AND page_id = 0";

    $viewMessage = $activeSection === 'page'
        ? "Viewing Page Meta Settings (page_key: " . ($_GET['page'] ?? '') . ")"
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

// Context Detection - embedded in dashboard or standalone
$isEmbeddedMeta = isset($EMBED_META_PAGE) && $EMBED_META_PAGE === true;

if ($isEmbeddedMeta) {
    $metaBaseUrl = URL_USER_DASHBOARD . '?view=meta_settings';
} else {
    $metaBaseUrl = URL_META_SETTINGS;
}

// ========== HANDLE POST REQUESTS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. GLOBAL POST
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'global') {
        // [ADDED] Re-verify Edit Permission
        if (!$perm->edit) {
            denyAccess("权限不足：您没有修改全局 Meta 设置的权限。");
        }

        $oldGlobal = null;
        $hasGlobal = false;
        
        // REUSED QUERY VAR
        $checkStmt = $conn->prepare($sqlGlobalCheck);
        if ($checkStmt) {
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows > 0) {
                $hasGlobal = true;
                $oTitle = null; $oDesc = null; $oOgTitle = null; $oOgDesc = null; $oOgUrl = null;
                $checkStmt->bind_result($oTitle, $oDesc, $oOgTitle, $oOgDesc, $oOgUrl);
                $checkStmt->fetch();
                $oldGlobal = [
                    'meta_title' => $oTitle,
                    'meta_description' => $oDesc,
                    'og_title' => $oOgTitle,
                    'og_description' => $oOgDesc,
                    'og_url' => $oOgUrl,
                ];
            }
            $checkStmt->close();
        }

        if ($hasGlobal) {
            $redirectUrl = $isEmbeddedMeta ? ($metaBaseUrl . '&section=global') : ($metaBaseUrl . '?section=global');
            checkNoChangesAndRedirect(
                [
                    'meta_title' => $_POST['meta_title'] ?? '',
                    'meta_description' => $_POST['meta_description'] ?? '',
                    'og_title' => $_POST['og_title'] ?? '',
                    'og_description' => $_POST['og_description'] ?? '',
                    'og_url' => $_POST['og_url'] ?? '',
                ],
                $oldGlobal,
                null,
                $redirectUrl
            );
        }

        if ($hasGlobal) {
            // REUSED QUERY VAR
            $sql = $sqlGlobalUpdate;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $_POST['meta_title'], $_POST['meta_description'], $_POST['og_title'], $_POST['og_description'], $_POST['og_url']);
        } else {
            // REUSED QUERY VAR
            $sql = $sqlGlobalInsert;
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $_POST['meta_title'], $_POST['meta_description'], $_POST['og_title'], $_POST['og_description'], $_POST['og_url']);
        }
        
        if ($stmt->execute()) {
            $message = "全局设置保存成功！";
            $msgType = "success";

            if (function_exists('logAudit')) {
                $newGlobal = [
                    'meta_title' => $_POST['meta_title'] ?? '',
                    'meta_description' => $_POST['meta_description'] ?? '',
                    'og_title' => $_POST['og_title'] ?? '',
                    'og_description' => $_POST['og_description'] ?? '',
                    'og_url' => $_POST['og_url'] ?? '',
                ];

                logAudit([
                    'page'           => $auditPage,
                    'action'         => $hasGlobal ? 'E' : 'A',
                    'action_message' => $hasGlobal ? 'Updated Global Meta Settings' : 'Added Global Meta Settings',
                    'query'          => $sql,
                    'query_table'    => $metaTable,
                    'user_id'        => $auditUserId,
                    'record_id'      => 0,
                    'record_name'    => 'global',
                    'old_value'      => $oldGlobal,
                    'new_value'      => $newGlobal
                ]);
            }
        } else {
            $message = "保存失败: " . $conn->error;
            $msgType = "danger";
        }
        $stmt->close();
    }

    // 2. PAGE POST
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'page') {
        // [ADDED] Re-verify Edit Permission
        if (!$perm->edit) {
            denyAccess("权限不足：您没有修改页面 Meta 设置的权限。");
        }

        $pKey = $_POST['page_key'] ?? '';
        if (!empty($pKey) && array_key_exists($pKey, $PAGE_META_REGISTRY)) {
            $oldPage = null;
            $hasPage = false;
            
            // REUSED QUERY VAR
            $checkStmt = $conn->prepare($sqlPageCheck);
            if ($checkStmt) {
                $checkStmt->bind_param("s", $pKey);
                $checkStmt->execute();
                $checkStmt->store_result();
                if ($checkStmt->num_rows > 0) {
                    $hasPage = true;
                    $oTitle = null; $oDesc = null; $oOgTitle = null; $oOgDesc = null; $oOgUrl = null;
                    $checkStmt->bind_result($oTitle, $oDesc, $oOgTitle, $oOgDesc, $oOgUrl);
                    $checkStmt->fetch();
                    $oldPage = [
                        'page_key' => $pKey,
                        'meta_title' => $oTitle,
                        'meta_description' => $oDesc,
                        'og_title' => $oOgTitle,
                        'og_description' => $oOgDesc,
                        'og_url' => $oOgUrl,
                    ];
                }
                $checkStmt->close();
            }

            if ($hasPage) {
                $redirectUrl = $isEmbeddedMeta
                    ? ($metaBaseUrl . '&section=page&page=' . urlencode($pKey))
                    : ($metaBaseUrl . '?section=page&page=' . urlencode($pKey));

                checkNoChangesAndRedirect(
                    [
                        'meta_title' => $_POST['page_meta_title'] ?? '',
                        'meta_description' => $_POST['page_meta_description'] ?? '',
                        'og_title' => $_POST['page_og_title'] ?? '',
                        'og_description' => $_POST['page_og_description'] ?? '',
                        'og_url' => $_POST['page_og_url'] ?? '',
                    ],
                    $oldPage,
                    null,
                    $redirectUrl
                );
            }

            if ($hasPage) {
                // REUSED QUERY VAR
                $sql = $sqlPageUpdate;
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssss", $_POST['page_meta_title'], $_POST['page_meta_description'], $_POST['page_og_title'], $_POST['page_og_description'], $_POST['page_og_url'], $pKey);
            } else {
                // REUSED QUERY VAR
                $sql = $sqlPageInsert;
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssss", $pKey, $_POST['page_meta_title'], $_POST['page_meta_description'], $_POST['page_og_title'], $_POST['page_og_description'], $_POST['page_og_url']);
            }
            
            if ($stmt->execute()) {
                $pageMessage = "页面设置保存成功！";
                $pageMsgType = "success";

                if (function_exists('logAudit')) {
                    $newPage = [
                        'page_key' => $pKey,
                        'meta_title' => $_POST['page_meta_title'] ?? '',
                        'meta_description' => $_POST['page_meta_description'] ?? '',
                        'og_title' => $_POST['page_og_title'] ?? '',
                        'og_description' => $_POST['page_og_description'] ?? '',
                        'og_url' => $_POST['page_og_url'] ?? '',
                    ];

                    logAudit([
                        'page'           => $auditPage,
                        'action'         => $hasPage ? 'E' : 'A',
                        'action_message' => $hasPage ? 'Updated Page Meta Settings' : 'Added Page Meta Settings',
                        'query'          => $sql,
                        'query_table'    => $pageMetaTable,
                        'user_id'        => $auditUserId,
                        'record_id'      => 0,
                        'record_name'    => $pKey,
                        'old_value'      => $oldPage,
                        'new_value'      => $newPage
                    ]);
                }
            } else {
                $pageMessage = "保存失败: " . $conn->error;
                $pageMsgType = "danger";
            }
            $stmt->close();
        }
    }

    // 3. DELETE PAGE POST
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'delete_page') {
        // [ADDED] Re-verify Delete Permission
        if (!$perm->delete) {
            denyAccess("权限不足：您没有删除(重置)页面 Meta 设置的权限。");
        }

        $delKey = $_POST['page_key'] ?? '';
        if (!empty($delKey) && array_key_exists($delKey, $PAGE_META_REGISTRY)) {
            $oldPage = null;
            if (function_exists('logAudit')) {
                // REUSED QUERY VAR (Using Page Check since it selects the same columns)
                $checkStmt = $conn->prepare($sqlPageCheck);
                if ($checkStmt) {
                    $checkStmt->bind_param("s", $delKey);
                    $checkStmt->execute();
                    $checkStmt->store_result();
                    if ($checkStmt->num_rows > 0) {
                        $oTitle = null; $oDesc = null; $oOgTitle = null; $oOgDesc = null; $oOgUrl = null;
                        $checkStmt->bind_result($oTitle, $oDesc, $oOgTitle, $oOgDesc, $oOgUrl);
                        $checkStmt->fetch();
                        $oldPage = [
                            'page_key' => $delKey,
                            'meta_title' => $oTitle,
                            'meta_description' => $oDesc,
                            'og_title' => $oOgTitle,
                            'og_description' => $oOgDesc,
                            'og_url' => $oOgUrl,
                        ];
                    }
                    $checkStmt->close();
                }
            }

            // REUSED QUERY VAR
            $sql = $sqlPageDelete;
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $delKey);
                if ($stmt->execute()) {
                    $pageMessage = "已恢复为全局默认设置！";
                    $pageMsgType = "success";

                    if (function_exists('logAudit')) {
                        logAudit([
                            'page'           => $auditPage,
                            'action'         => 'D',
                            'action_message' => 'Deleted Page Meta Settings',
                            'query'          => $sql,
                            'query_table'    => $pageMetaTable,
                            'user_id'        => $auditUserId,
                            'record_id'      => 0,
                            'record_name'    => $delKey,
                            'old_value'      => $oldPage
                        ]);
                    }
                } else {
                    $pageMessage = "删除失败: " . $conn->error;
                    $pageMsgType = "danger";
                }
                $stmt->close();
            } else {
                $pageMessage = "删除失败: 无法准备删除语句。";
                $pageMsgType = "danger";
            }
        }
    }
}

// ========== FETCH DATA ==========
// Global Data
$current = array_fill_keys(array_keys($seoFields), '');
$res = $conn->query("SELECT * FROM $metaTable WHERE page_type = 'global' AND page_id = 0 LIMIT 1");
if ($res && $row = $res->fetch_assoc()) $current = $row;

// Page Data
$selectedPageKey = $_POST['page_key'] ?? ($_GET['page'] ?? '');
$pageCurrent = array_fill_keys(array_keys($seoFields), '');

if (!empty($selectedPageKey) && function_exists('getPageMetaSettings')) {
    $pm = getPageMetaSettings($conn, $selectedPageKey);
    if ($pm) $pageCurrent = $pm;
}

// Get Customized Pages List
$customizedPages = [];
$cpRes = $conn->query("SELECT page_key FROM $pageMetaTable");
while ($cpRes && $row = $cpRes->fetch_assoc()) $customizedPages[] = $row['page_key'];

// ========== RENDER ==========
if ($isEmbeddedMeta): ?>
    <div class="meta-settings-container" style="max-width: 1000px; margin: 0 auto;">

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
    <html lang="zh-CN">
    <head>
        <?php require_once BASE_PATH . 'include/header.php'; ?>
        <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/meta.css">
    </head>
    <body>
    <?php require_once BASE_PATH . 'common/menu/header.php'; ?>

    <div class="container mt-4" style="max-width: 1000px;">
        
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

    <script src="<?php echo URL_ASSETS; ?>/js/meta.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
<?php endif; ?>
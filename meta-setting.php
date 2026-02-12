<?php
// Path: meta-setting.php
require_once __DIR__ . '/common.php';

$message = ""; 
$msgType = "";
$pageMessage = "";
$pageMsgType = "";

// Ensure constants are defined
$metaTable = defined('META_SETTINGS') ? META_SETTINGS : 'meta_settings';
$pageMetaTable = defined('META_SETTINGS_PAGE') ? META_SETTINGS_PAGE : 'meta_settings_page';

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

// ========== HANDLE POST REQUESTS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. GLOBAL POST
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'global') {
        $sql = "INSERT INTO $metaTable (page_type, page_id, meta_title, meta_description, og_title, og_description, og_url) 
                VALUES ('global', 0, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), 
                og_title = VALUES(og_title), og_description = VALUES(og_description), og_url = VALUES(og_url)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $_POST['meta_title'], $_POST['meta_description'], $_POST['og_title'], $_POST['og_description'], $_POST['og_url']);
        
        if ($stmt->execute()) {
            $message = "全局设置保存成功！";
            $msgType = "success";
        } else {
            $message = "保存失败: " . $conn->error;
            $msgType = "danger";
        }
        $stmt->close();
    }

    // 2. PAGE POST
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'page') {
        $pKey = $_POST['page_key'] ?? '';
        if (!empty($pKey) && array_key_exists($pKey, $PAGE_META_REGISTRY)) {
            $sql = "INSERT INTO $pageMetaTable (page_key, meta_title, meta_description, og_title, og_description, og_url) 
                    VALUES (?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), 
                    og_title = VALUES(og_title), og_description = VALUES(og_description), og_url = VALUES(og_url)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $pKey, $_POST['page_meta_title'], $_POST['page_meta_description'], $_POST['page_og_title'], $_POST['page_og_description'], $_POST['page_og_url']);
            
            if ($stmt->execute()) {
                $pageMessage = "页面设置保存成功！";
                $pageMsgType = "success";
            } else {
                $pageMessage = "保存失败: " . $conn->error;
                $pageMsgType = "danger";
            }
            $stmt->close();
        }
    }

    // 3. DELETE PAGE POST
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'delete_page') {
        $delKey = $_POST['page_key'] ?? '';
        if (!empty($delKey) && array_key_exists($delKey, $PAGE_META_REGISTRY)) {
            $sql = "DELETE FROM $pageMetaTable WHERE page_key = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $delKey);
                if ($stmt->execute()) {
                    $pageMessage = "已恢复为全局默认设置！";
                    $pageMsgType = "success";
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

?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <?php require_once __DIR__ . '/include/header.php'; ?>
    <link rel="stylesheet" href="<?php echo URL_ASSETS; ?>/css/meta.css">
    </head>
<body>
<?php require_once __DIR__ . '/common/menu/header.php'; ?>

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
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card meta-card">
                <div class="card-header meta-card-header">
                    <h4 class="header-title">Global Meta Settings</h4>
                    <p class="header-subtitle">These settings apply to every page on your site unless overridden.</p>
                </div>
                <div class="card-body meta-card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="form_type" value="global">

                        <?php foreach ($seoFields as $key => $config): ?>
                            <div class="mb-4 row">
                                <label class="col-md-3 col-form-label text-md-end form-label"><?php echo htmlspecialchars($config['label']); ?></label>
                                <div class="col-md-9">
                                    <?php if ($config['type'] === 'textarea'): ?>
                                        <textarea name="<?php echo $key; ?>" class="form-control" rows="3"><?php echo htmlspecialchars($current[$key]); ?></textarea>
                                    <?php else: ?>
                                        <input type="text" name="<?php echo $key; ?>" class="form-control" value="<?php echo htmlspecialchars($current[$key]); ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="row mt-4">
                            <div class="col-md-9 offset-md-3">
                                <button type="submit" class="btn btn-primary px-5 fw-bold"><i class="fa-solid fa-save"></i> Save Global Settings</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <?php if ($activeSection === 'page'): ?>
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card meta-card">
                <div class="card-header meta-card-header">
                    <h4 class="header-title">Page Specific Settings</h4>
                    <p class="header-subtitle">Select a specific page below to override the global defaults.</p>
                </div>
                <div class="card-body meta-card-body">
                    <?php if ($pageMessage): ?>
                        <div class="alert alert-<?php echo $pageMsgType; ?> alert-dismissible fade show">
                            <?php echo $pageMessage; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="bg-light p-4 rounded mb-4 border">
                        <label class="form-label mb-2 d-block text-start">Select Page to Edit / 选择页面</label>
                        <form method="GET" id="pageSelectForm" class="d-flex">
                            <input type="hidden" name="section" value="page">
                            <input type="hidden" name="page" id="pageSelectValue" value="<?php echo htmlspecialchars($selectedPageKey); ?>">

                            <div class="page-select-wrap">
                                <button type="button" class="page-select-toggle" id="pageSelectToggle">
                                    <?php
                                    if (!empty($selectedPageKey) && array_key_exists($selectedPageKey, $PAGE_META_REGISTRY)) {
                                        echo htmlspecialchars($PAGE_META_REGISTRY[$selectedPageKey]);
                                        if (in_array($selectedPageKey, $customizedPages)) {
                                            echo ' (✓)';
                                        }
                                    } else {
                                        echo '-- Click to Select a Page --';
                                    }
                                    ?>
                                    <span class="page-select-caret"><i class="fa-solid fa-chevron-down"></i></span>
                                </button>

                                <div class="page-select-menu" id="pageSelectMenu" aria-hidden="true">
                                    <button type="button" class="page-select-item" data-value="">
                                        -- Click to Select a Page --
                                    </button>
                                    <?php foreach ($PAGE_META_REGISTRY as $key => $label): ?>
                                        <button type="button" class="page-select-item" data-value="<?php echo $key; ?>">
                                            <?php echo htmlspecialchars($label); ?>
                                            <?php echo in_array($key, $customizedPages) ? ' (✓)' : ''; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                    <?php if (!empty($selectedPageKey) && array_key_exists($selectedPageKey, $PAGE_META_REGISTRY)): ?>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                            <h5 class="m-0" style="font-size: 16px;">
                                Editing: <span class="text-primary fw-bold"><?php echo htmlspecialchars($PAGE_META_REGISTRY[$selectedPageKey]); ?></span>
                                <?php if (in_array($selectedPageKey, $customizedPages)): ?>
                                    <span class="badge bg-success ms-2">Customized</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary ms-2">Using Global</span>
                                <?php endif; ?>
                            </h5>

                            <?php if (in_array($selectedPageKey, $customizedPages)): ?>
                            <form method="POST" class="reset-form">
                                <input type="hidden" name="form_type" value="delete_page">
                                <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($selectedPageKey, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="fa-solid fa-rotate-left"></i> Reset
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="form_type" value="page">
                            <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($selectedPageKey, ENT_QUOTES, 'UTF-8'); ?>">

                            <?php foreach ($seoFields as $key => $config): 
                                $fieldName = 'page_' . $key;
                                $fieldValue = $pageCurrent[$key] ?? '';
                                $globalValue = $current[$key] ?? '';
                            ?>
                                <div class="mb-4 row">
                                    <label class="col-md-3 col-form-label text-md-end form-label"><?php echo htmlspecialchars($config['label']); ?></label>
                                    <div class="col-md-9">
                                        <?php if ($config['type'] === 'textarea'): ?>
                                            <textarea name="<?php echo $fieldName; ?>" class="form-control" rows="3"
                                                placeholder="Global: <?php echo htmlspecialchars($globalValue); ?>"><?php echo htmlspecialchars($fieldValue); ?></textarea>
                                        <?php else: ?>
                                            <input type="text" name="<?php echo $fieldName; ?>" class="form-control" 
                                                value="<?php echo htmlspecialchars($fieldValue); ?>"
                                                placeholder="Global: <?php echo htmlspecialchars($globalValue); ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="row mt-4">
                                <div class="col-md-9 offset-md-3">
                                    <button type="submit" class="btn btn-success px-5 fw-bold"><i class="fa-solid fa-save"></i> Save Page Settings</button>
                                </div>
                            </div>
                        </form>

                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="empty-state-icon">
                                <i class="fa-solid fa-arrow-pointer"></i>
                            </div>
                            <h5 class="text-muted">Please select a page above to start editing.</h5>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="<?php echo URL_ASSETS; ?>/js/meta.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/sweetalert2@11.js"></script>
<script src="<?php echo URL_ASSETS; ?>/js/bootstrap.bundle.min.js"></script>
</body>
</html>
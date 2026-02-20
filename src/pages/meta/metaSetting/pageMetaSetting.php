<?php
// Path: src/pages/metaSetting/pageMetaSetting.php
// Page Specific Meta Settings Section - included by metaSetting/index.php
?>
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
                        <?php if (isset($isEmbeddedMeta) && $isEmbeddedMeta): ?>
                            <input type="hidden" name="view" value="meta_settings">
                        <?php endif; ?>
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
                        <?php if ($perm->delete): ?>
                        <form method="POST" class="reset-form">
                            <input type="hidden" name="form_type" value="delete_page">
                            <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($selectedPageKey, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                            </button>
                        </form>
                        <?php endif; ?>
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
                                <?php if ($perm->edit): ?>
                                <button type="submit" class="btn btn-success px-5 fw-bold"><i class="fa-solid fa-save"></i> Save Page Settings</button>
                                <?php endif; ?>
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
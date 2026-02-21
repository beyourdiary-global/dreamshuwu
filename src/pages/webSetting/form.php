<form method="POST" enctype="multipart/form-data" id="webSettingsForm">
    <input type="hidden" name="action_type" id="action_type_input" value="save">
    <?php $canSave = (!empty($perm->edit) || !empty($perm->add)); ?>
    
    <h5 class="mb-3 text-primary border-bottom pb-2">Branding</h5>
    
    <div class="mb-4 row">
        <label class="col-md-3 col-form-label text-md-end form-label">Website Name</label>
        <div class="col-md-9">
            <input type="text" name="website_name" class="form-control" value="<?php echo htmlspecialchars($current['website_name'] ?? ''); ?>" <?php if (!$canSave) echo 'disabled'; ?>>
        </div>
    </div>

    <div class="mb-4 row">
        <label class="col-md-3 col-form-label text-md-end form-label">Website Logo</label>
        <div class="col-md-9">
            <?php if(!empty($current['website_logo'])): ?>
                <div class="d-flex align-items-center mb-2 gap-3">
                    <div style="border: 1px solid #ddd; padding: 4px; border-radius: 4px; background: #fff;">
                        <img src="<?php echo htmlspecialchars(URL_ASSETS . '/uploads/settings/' . $current['website_logo']); ?>" alt="Logo" style="height: 50px;">
                    </div>
                    <?php if (!empty($perm->edit)): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmAction('remove_logo', '确定要移除 Logo 吗？')">
                        <i class="fa-solid fa-trash"></i> Remove
                    </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <input type="file" name="website_logo" class="form-control" accept=".jpg,.jpeg,.png,.svg" <?php if (!$canSave) echo 'disabled'; ?>>
            <small class="text-muted">Recommended height: 50px. Formats: JPG, JPEG, PNG, SVG.</small>
        </div>
    </div>

    <div class="mb-4 row">
        <label class="col-md-3 col-form-label text-md-end form-label">Website Favicon</label>
        <div class="col-md-9">
            <?php if(!empty($current['website_favicon'])): ?>
                <div class="d-flex align-items-center mb-2 gap-3">
                    <div style="border: 1px solid #ddd; padding: 4px; border-radius: 4px; background: #fff;">
                        <img src="<?php echo htmlspecialchars(URL_ASSETS . '/uploads/settings/' . $current['website_favicon']); ?>" alt="Favicon" style="width: 32px; height: 32px;">
                    </div>
                    <?php if (!empty($perm->edit)): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmAction('remove_favicon', '确定要移除 Favicon 吗？')">
                        <i class="fa-solid fa-trash"></i> Remove
                    </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <input type="file" name="website_favicon" class="form-control" accept=".jpg,.jpeg,.png,.svg" <?php if (!$canSave) echo 'disabled'; ?>>
            <small class="text-muted">Recommended size: 32x32. Formats: JPG, JPEG, PNG, SVG.</small>
        </div>
    </div>

    <h5 class="mb-3 mt-5 text-primary border-bottom pb-2">Theme Colors</h5>

    <div class="row">
        <div class="col-md-6">
            <div class="mb-4 row">
                <label class="col-md-6 col-form-label text-md-end form-label">Theme Background</label>
                <div class="col-md-6 d-flex align-items-center">
                    <input type="color" name="theme_bg_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($current['theme_bg_color'] ?? '#ffffff'); ?>" title="Choose color" <?php if (!$canSave) echo 'disabled'; ?>>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-4 row">
                <label class="col-md-6 col-form-label text-md-end form-label">Theme Text Color</label>
                <div class="col-md-6 d-flex align-items-center">
                    <input type="color" name="theme_text_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($current['theme_text_color'] ?? '#333333'); ?>" title="Choose color" <?php if (!$canSave) echo 'disabled'; ?>>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="mb-4 row">
                <label class="col-md-6 col-form-label text-md-end form-label">Button Color</label>
                <div class="col-md-6 d-flex align-items-center">
                    <input type="color" name="button_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($current['button_color'] ?? '#233dd2'); ?>" title="Choose color" <?php if (!$canSave) echo 'disabled'; ?>>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-4 row">
                <label class="col-md-6 col-form-label text-md-end form-label">Button Text Color</label>
                <div class="col-md-6 d-flex align-items-center">
                    <input type="color" name="button_text_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($current['button_text_color'] ?? '#ffffff'); ?>" title="Choose color" <?php if (!$canSave) echo 'disabled'; ?>>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4 row">
        <label class="col-md-3 col-form-label text-md-end form-label">Page Background</label>
        <div class="col-md-3 d-flex align-items-center">
            <input type="color" name="background_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($current['background_color'] ?? '#f4f7f6'); ?>" title="Choose color" <?php if (!$canSave) echo 'disabled'; ?>>
        </div>
    </div>

    <div class="row mt-5 pt-3 border-top">
        <div class="col-12 col-md-6 text-center text-md-start mb-3 mb-md-0">
            <?php if ($canSave): ?>
            <button type="submit" class="btn btn-primary px-5 fw-bold mobile-full-width">
                <i class="fa-solid fa-save"></i> Save Settings
            </button>
            <?php endif; ?>
        </div>
        
        <div class="col-12 col-md-6 text-center text-md-end">
            <?php if (!empty($perm->delete)): ?>
            <button type="button" class="btn btn-danger px-4 mobile-full-width" onclick="confirmAction('reset_defaults', '确定要重置所有设置吗？此操作无法撤销。', 'warning')">
                <i class="fa-solid fa-rotate-left"></i> Reset to Defaults
            </button>
            <?php endif; ?>
        </div>
    </div>
</form>
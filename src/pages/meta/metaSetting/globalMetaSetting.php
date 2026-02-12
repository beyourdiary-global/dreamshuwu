<?php
// Path: src/pages/metaSetting/globalMetaSetting.php
// Global Meta Settings Section - included by metaSetting/index.php
?>
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

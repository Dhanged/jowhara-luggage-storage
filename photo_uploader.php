<?php
/**
 * photo_uploader.php — Reusable Modern Photo Uploader Component
 *
 * Two-input approach for reliable cross-device camera capture:
 *  - A CAMERA input  (capture="environment", no name → not submitted)
 *    This directly opens the device camera on Android/iPhone.
 *  - A GALLERY input (no capture, has name → submitted with form)
 *    This opens the file picker for choosing from gallery.
 *  When a camera shot is taken, the file is copied via DataTransfer
 *  to the gallery/submit input so the backend receives it normally.
 */
function render_photo_uploader(
    string  $id,
    string  $label,
    ?string $existing_url = null,
    ?string $remove_name  = null,
    int     $slot_index   = 1
): void {
    $has_existing = !empty($existing_url);
    $uid = 'pu_' . $slot_index . '_' . preg_replace('/[^a-z0-9]/i', '_', $id);
    ?>
    <div class="photo-uploader-wrap" id="<?php echo $uid; ?>">

        <!-- GALLERY / SUBMIT input — file picker + actual form submission -->
        <input type="file"
               id="<?php echo $uid; ?>_file"
               name="<?php echo h($id); ?>"
               accept=".jpg,.jpeg,.png,.webp,.gif,image/*"
               class="d-none">

        <?php if ($remove_name): ?>
        <!-- Remove flag: JS sets value="1" when Remove is clicked -->
        <input type="hidden"
               id="<?php echo $uid; ?>_remove"
               name="<?php echo h($remove_name); ?>"
               value="">
        <?php endif; ?>

        <!-- Label -->
        <div class="pu-label"><?php echo h(__($label)); ?></div>

        <!-- DROP ZONE (shown when no photo selected/existing) -->
        <div class="pu-dropzone <?php echo $has_existing ? 'd-none' : ''; ?>"
             id="<?php echo $uid; ?>_dropzone">
            <div class="pu-dz-icon"><i class="bi bi-image-fill"></i></div>
            <div class="pu-dz-title"><?php echo __('Drag & drop image here'); ?></div>
            <div class="pu-dz-sub"><?php echo __('or choose an option below'); ?></div>
            <div class="pu-dz-badges">
                <span>JPG</span><span>PNG</span><span>WEBP</span><span>GIF</span>
                <span class="pu-dz-sep">·</span>
                <span><?php echo __('Max 3 MB'); ?></span>
            </div>
            <div class="pu-btn-row">
                <button type="button" class="pu-btn pu-btn-camera"
                        data-uid="<?php echo $uid; ?>" data-mode="camera">
                    <i class="bi bi-camera-fill"></i>
                    <?php echo __('Take Photo'); ?>
                </button>
                <button type="button" class="pu-btn pu-btn-gallery"
                        data-uid="<?php echo $uid; ?>" data-mode="gallery">
                    <i class="bi bi-images"></i>
                    <?php echo __('Choose from Gallery'); ?>
                </button>
            </div>
        </div>

        <!-- PREVIEW panel (shown after a photo is selected or existing exists) -->
        <div class="pu-preview <?php echo $has_existing ? '' : 'd-none'; ?>"
             id="<?php echo $uid; ?>_preview">
            <div class="pu-preview-img-wrap">
                <img id="<?php echo $uid; ?>_preview_img"
                     src="<?php echo $has_existing ? h($existing_url) : ''; ?>"
                     alt="<?php echo h($label); ?>"
                     class="pu-preview-img">
                <div class="pu-preview-badge">
                    <i class="bi bi-check-circle-fill"></i>
                    <?php echo __('Photo Ready'); ?>
                </div>
            </div>
            <div class="pu-preview-actions">
                <button type="button" class="pu-act-btn pu-act-camera"
                        data-uid="<?php echo $uid; ?>" data-mode="camera">
                    <i class="bi bi-arrow-repeat"></i> <?php echo __('Retake'); ?>
                </button>
                <button type="button" class="pu-act-btn pu-act-gallery"
                        data-uid="<?php echo $uid; ?>" data-mode="gallery">
                    <i class="bi bi-pencil-square"></i> <?php echo __('Change'); ?>
                </button>
                <button type="button" class="pu-act-btn pu-act-remove"
                        data-uid="<?php echo $uid; ?>"
                        data-remove-name="<?php echo h($remove_name ?? ''); ?>"
                        data-has-existing="<?php echo $has_existing ? '1' : '0'; ?>">
                    <i class="bi bi-trash3"></i> <?php echo __('Remove'); ?>
                </button>
            </div>
        </div>

        <!-- Inline validation error -->
        <div class="pu-error d-none" id="<?php echo $uid; ?>_error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span></span>
        </div>

    </div>
    <?php
}

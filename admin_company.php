<?php
require_once __DIR__ . "/layout.php";
require_admin();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $fields = ["company_name","company_address","company_phone","company_email"];
    foreach ($fields as $f) {
        $val = trim($_POST[$f] ?? "");
        db_execute("INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", "ss", [$f, $val]);
    }
    // Logo upload
    if (!empty($_FILES["company_logo"]["name"])) {
        try {
            $tmp  = $_FILES["company_logo"]["tmp_name"];
            $mime = mime_content_type($tmp);
            $allowed = ["image/jpeg","image/png","image/webp","image/gif"];
            if (!in_array($mime, $allowed)) throw new RuntimeException("Invalid image type.");
            if ($_FILES["company_logo"]["size"] > 2*1024*1024) throw new RuntimeException("Logo must be under 2 MB.");
            $dest = __DIR__ . "/assets/logo.jpg";
            move_uploaded_file($tmp, $dest);
            db_execute("INSERT INTO system_settings (setting_key, setting_value) VALUES ('company_logo','assets/logo.jpg') ON DUPLICATE KEY UPDATE setting_value='assets/logo.jpg'", "", []);
            log_action("Updated company logo", "settings", null, "Logo replaced");
        } catch (Throwable $e) {
            set_flash("danger", $e->getMessage());
            redirect("admin_company.php");
        }
    }
    log_action("Updated company settings", "settings", null, "Company info saved");
    set_flash("success", "Company settings saved successfully.");
    redirect("admin_company.php");
}

$company_name    = get_setting("company_name",    "Jowhara International Hotel");
$company_address = get_setting("company_address", "");
$company_phone   = get_setting("company_phone",   "");
$company_email   = get_setting("company_email",   "");
$company_logo    = get_setting("company_logo",    "assets/logo.jpg");

render_header("Company Settings", "admin_company");
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <span class="admin-page-icon"><i class="bi bi-building"></i></span>
            Company Settings
        </h1>
        <p class="text-secondary mb-0">Update your hotel branding, contact info, and logo.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Form -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-transparent border-bottom px-4 py-3">
                <h2 class="section-title mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Company Info</h2>
            </div>
            <div class="card-body px-4 py-4">
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Company / Hotel Name <span class="text-danger">*</span></label>
                        <input class="form-control form-control-lg" type="text" name="company_name" value="<?php echo h($company_name); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Address</label>
                        <textarea class="form-control" name="company_address" rows="2" placeholder="Street, City, Country"><?php echo h($company_address); ?></textarea>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input class="form-control" type="text" name="company_phone" value="<?php echo h($company_phone); ?>" placeholder="+252 …">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input class="form-control" type="email" name="company_email" value="<?php echo h($company_email); ?>" placeholder="info@hotel.com">
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Company Logo</label>
                        <input class="form-control" type="file" name="company_logo" id="logoInput" accept="image/*">
                        <div class="form-text">JPG, PNG, or WEBP · max 2 MB · replaces current logo everywhere.</div>
                        <!-- Live preview -->
                        <div class="mt-3" id="logoPreviewWrap" style="display:none;">
                            <div class="text-muted small mb-1">Preview:</div>
                            <img id="logoPreviewImg" src="#" alt="Logo preview" style="height:80px; border-radius:12px; object-fit:cover; border:2px solid var(--border);">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-check2-all me-2"></i>Save Company Settings
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Card -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-transparent border-bottom px-4 py-3">
                <h2 class="section-title mb-0"><i class="bi bi-eye me-2"></i>Live Preview</h2>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center px-4 py-4">
                <div class="company-preview-card text-center">
                    <img src="<?php echo h($company_logo); ?>?v=<?php echo time(); ?>" alt="Logo" class="company-preview-logo" id="previewLogo">
                    <div class="company-preview-name" id="previewName"><?php echo h($company_name); ?></div>
                    <div class="company-preview-detail" id="previewAddress"><?php echo h($company_address ?: "—"); ?></div>
                    <div class="company-preview-contacts">
                        <span id="previewPhone"><i class="bi bi-telephone-fill me-1"></i><?php echo h($company_phone ?: "—"); ?></span>
                        <span id="previewEmail"><i class="bi bi-envelope-fill me-1"></i><?php echo h($company_email ?: "—"); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.admin-page-icon { width:40px; height:40px; border-radius:12px; background:var(--brand-light); color:var(--brand); display:inline-flex; align-items:center; justify-content:center; font-size:1.2rem; }
.company-preview-card {
    background: linear-gradient(135deg, #1d6f6f 0%, #0a3535 100%);
    border-radius: 20px; padding: 2.5rem 2rem; color:#fff; width:100%; max-width:340px;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
}
.company-preview-logo {
    width:90px; height:90px; border-radius:18px; object-fit:cover;
    border:3px solid rgba(255,255,255,.35); margin-bottom:1rem;
    box-shadow: 0 8px 24px rgba(0,0,0,.3);
}
.company-preview-name { font-size:1.2rem; font-weight:800; margin-bottom:.5rem; letter-spacing:-.3px; }
.company-preview-detail { font-size:.82rem; opacity:.7; margin-bottom:.75rem; }
.company-preview-contacts { display:flex; flex-direction:column; gap:.3rem; font-size:.78rem; opacity:.8; }
</style>

<script>
// Live preview from form fields
const nameInput    = document.querySelector('[name="company_name"]');
const addrInput    = document.querySelector('[name="company_address"]');
const phoneInput   = document.querySelector('[name="company_phone"]');
const emailInput   = document.querySelector('[name="company_email"]');

function sync() {
    document.getElementById("previewName").textContent    = nameInput.value  || "Hotel Name";
    document.getElementById("previewAddress").textContent = addrInput.value  || "—";
    document.getElementById("previewPhone").innerHTML     = '<i class="bi bi-telephone-fill me-1"></i>' + (phoneInput.value || "—");
    document.getElementById("previewEmail").innerHTML     = '<i class="bi bi-envelope-fill me-1"></i>'  + (emailInput.value || "—");
}
[nameInput, addrInput, phoneInput, emailInput].forEach(el => el && el.addEventListener("input", sync));

// Logo live preview
document.getElementById("logoInput").addEventListener("change", function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById("logoPreviewImg").src = e.target.result;
        document.getElementById("logoPreviewWrap").style.display = "";
        document.getElementById("previewLogo").src = e.target.result;
    };
    reader.readAsDataURL(file);
});
</script>
<?php render_footer(); ?>

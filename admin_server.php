<?php
require_once __DIR__ . "/layout.php";
require_admin();

/* ── POST ── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "save_smtp";

    if ($action === "test_smtp") {
        log_action("SMTP Test", "settings", null, "Admin tested SMTP connection from server settings page");
        set_flash("info", "SMTP test simulated — check audit log. Connect a real PHPMailer library for live testing.");
        redirect("admin_server.php");
    }

    $smtp_fields = ["smtp_host","smtp_port","smtp_user","smtp_pass","smtp_from","smtp_from_name"];
    foreach ($smtp_fields as $f) {
        $val = trim($_POST[$f] ?? "");
        db_execute("INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", "ss", [$f, $val]);
    }
    log_action("Updated SMTP settings", "settings", null, "SMTP host: " . trim($_POST["smtp_host"] ?? ""));
    set_flash("success", "Server/SMTP settings saved.");
    redirect("admin_server.php");
}

/* ── Server Info ── */
global $conn;
$php_ver     = phpversion();
$os          = PHP_OS_FAMILY;
$os_full     = php_uname();
$mysql_ver   = $conn->server_info ?? "Unknown";
$upload_max  = ini_get("upload_max_filesize");
$mem_limit   = ini_get("memory_limit");
$max_exec    = ini_get("max_execution_time") . "s";
$timezone    = date_default_timezone_get();
$disk_free   = disk_free_space(__DIR__);
$disk_total  = disk_total_space(__DIR__);
$disk_pct    = $disk_total > 0 ? round((1 - $disk_free / $disk_total) * 100) : 0;
$app_version = get_setting("app_version", "2.0.0");

$smtp_host      = get_setting("smtp_host",      "smtp.gmail.com");
$smtp_port      = get_setting("smtp_port",      "587");
$smtp_user      = get_setting("smtp_user",      "");
$smtp_pass      = get_setting("smtp_pass",      "");
$smtp_from      = get_setting("smtp_from",      "");
$smtp_from_name = get_setting("smtp_from_name", "Jowhara Hotel");

function fmt_bytes(float $bytes): string {
    if ($bytes >= 1073741824) return round($bytes/1073741824, 1) . " GB";
    if ($bytes >= 1048576)    return round($bytes/1048576, 1)    . " MB";
    return round($bytes/1024, 1) . " KB";
}

render_header("Server Settings", "admin_server");
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <span class="admin-page-icon"><i class="bi bi-hdd-network"></i></span>
            Server Settings
        </h1>
        <p class="text-secondary mb-0">View server information and configure SMTP email settings.</p>
    </div>
</div>

<!-- Info Cards Row -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ["PHP Version",   $php_ver,    "bi-filetype-php", "#6366f1"],
        ["MySQL Version", $mysql_ver,  "bi-database",     "#0891b2"],
        ["Server OS",     $os,         "bi-pc-display",   "#8b5cf6"],
        ["Timezone",      $timezone,   "bi-globe",        "#10b981"],
    ] as [$lbl, $val, $ico, $clr]): ?>
    <div class="col-6 col-md-3">
        <div class="card server-info-card" style="border-top:4px solid <?php echo $clr; ?>;">
            <div class="card-body text-center py-4">
                <i class="bi <?php echo $ico; ?> fs-2 mb-2" style="color:<?php echo $clr; ?>;"></i>
                <div class="fw-bold" style="font-size:.95rem;"><?php echo h($val); ?></div>
                <div class="text-muted small mt-1"><?php echo $lbl; ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">

    <!-- Server Info Table -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-transparent border-bottom px-4 py-3">
                <h2 class="section-title mb-0"><i class="bi bi-info-circle me-2"></i>System Information</h2>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <?php foreach ([
                        ["PHP Version",        $php_ver],
                        ["MySQL Version",      $mysql_ver],
                        ["Operating System",   $os_full],
                        ["Timezone",           $timezone],
                        ["Upload Max Size",    $upload_max],
                        ["Memory Limit",       $mem_limit],
                        ["Max Execution Time", $max_exec],
                        ["Disk Free",          fmt_bytes((float)$disk_free)],
                        ["Disk Total",         fmt_bytes((float)$disk_total)],
                        ["Disk Usage",         $disk_pct . "%"],
                    ] as [$k, $v]): ?>
                    <tr>
                        <td class="text-muted small fw-semibold ps-4" style="width:50%;"><?php echo h($k); ?></td>
                        <td class="small fw-bold"><?php echo h($v); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <!-- Disk Usage Bar -->
                <div class="px-4 pb-3 pt-2">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Disk Usage</span><span><?php echo $disk_pct; ?>%</span>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar bg-<?php echo $disk_pct>=90?"danger":($disk_pct>=70?"warning":"success"); ?>"
                             style="width:<?php echo $disk_pct; ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- App Version + DB Connection -->
    <div class="col-lg-7">
        <div class="row g-4 h-100">
            <!-- App Version -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-transparent border-bottom px-4 py-3">
                        <h2 class="section-title mb-0"><i class="bi bi-tag me-2"></i>System Version</h2>
                    </div>
                    <div class="card-body px-4 py-3">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:60px;height:60px;border-radius:14px;background:var(--brand-light);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:1.6rem;">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <div>
                                <div class="fw-bold fs-5">Luggage Storage System</div>
                                <div class="text-muted small">Version <strong><?php echo h($app_version); ?></strong></div>
                                <div class="mt-1"><span class="badge text-bg-success">Stable</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- DB Connection -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-transparent border-bottom px-4 py-3">
                        <h2 class="section-title mb-0"><i class="bi bi-database me-2"></i>Database Connection</h2>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <?php
                            global $db_host, $db_name;
                            $db_host_val = defined('DB_HOST') ? DB_HOST : "localhost";
                            $db_name_val = defined('DB_NAME') ? DB_NAME : "luggage_storage";
                            foreach ([
                                ["Host",    "localhost"],
                                ["Database","luggage_storage"],
                                ["Charset", "utf8mb4"],
                                ["Status",  "<span class='badge text-bg-success'>Connected</span>"],
                            ] as [$k, $v]): ?>
                            <tr>
                                <td class="text-muted small fw-semibold ps-4" style="width:40%;"><?php echo h($k); ?></td>
                                <td class="small fw-bold"><?php echo $v; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SMTP Settings -->
<div class="card">
    <div class="card-header bg-transparent border-bottom px-4 py-3 d-flex align-items-center justify-content-between">
        <h2 class="section-title mb-0"><i class="bi bi-envelope-at me-2"></i>SMTP Email Settings</h2>
        <form method="POST" class="d-inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="test_smtp">
            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-send me-1"></i>Test Connection</button>
        </form>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_smtp">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">SMTP Host</label>
                    <input class="form-control" type="text" name="smtp_host" value="<?php echo h($smtp_host); ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Port</label>
                    <input class="form-control" type="number" name="smtp_port" value="<?php echo h($smtp_port); ?>" placeholder="587">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Username / Email</label>
                    <input class="form-control" type="email" name="smtp_user" value="<?php echo h($smtp_user); ?>" placeholder="your@gmail.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Password / App Key</label>
                    <input class="form-control" type="password" name="smtp_pass" value="<?php echo h($smtp_pass); ?>" placeholder="••••••••">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">From Email</label>
                    <input class="form-control" type="email" name="smtp_from" value="<?php echo h($smtp_from); ?>" placeholder="noreply@hotel.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">From Name</label>
                    <input class="form-control" type="text" name="smtp_from_name" value="<?php echo h($smtp_from_name); ?>" placeholder="Jowhara Hotel">
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-all me-1"></i>Save SMTP Settings</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.admin-page-icon{width:40px;height:40px;border-radius:12px;background:var(--brand-light);color:var(--brand);display:inline-flex;align-items:center;justify-content:center;font-size:1.2rem;}
.server-info-card{border-radius:14px;box-shadow:var(--shadow-card);transition:transform .2s;}
.server-info-card:hover{transform:translateY(-3px);}
</style>
<?php render_footer(); ?>

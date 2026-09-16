<?php
require_once __DIR__ . "/layout.php";
require_admin();

/* ── POST ── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";

    /* ── Backup: stream SQL file ── */
    if ($action === "backup") {
        global $conn;
        $tables = ["users","guests","luggage","audit_logs","login_history",
                   "storage_shelves","notifications","system_settings","import_history",
                   "luggage_transfers","shift_handovers","lost_found_items"];
        $filename = "backup_luggage_" . date("Ymd_His") . ".sql";

        ob_start();
        echo "-- Luggage Storage System Backup\n";
        echo "-- Generated: " . date("Y-m-d H:i:s") . "\n";
        echo "-- Version: " . get_setting("app_version","3.0.0") . "\n\n";
        echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $res = $conn->query("SHOW CREATE TABLE `$table`");
            if (!$res) continue;
            $row = $res->fetch_row();
            echo "-- Table: $table\n";
            echo "DROP TABLE IF EXISTS `$table`;\n";
            echo $row[1] . ";\n\n";

            $data = $conn->query("SELECT * FROM `$table`");
            if (!$data || $data->num_rows === 0) continue;
            $cols = [];
            $fi = $data->fetch_fields();
            foreach ($fi as $f) $cols[] = "`{$f->name}`";
            $col_str = implode(",", $cols);

            while ($drow = $data->fetch_row()) {
                $vals = array_map(fn($v) => $v === null ? "NULL" : "'" . $conn->real_escape_string($v) . "'", $drow);
                echo "INSERT INTO `$table` ($col_str) VALUES (" . implode(",", $vals) . ");\n";
            }
            echo "\n";
        }
        echo "SET FOREIGN_KEY_CHECKS=1;\n";
        $sql_content = ob_get_clean();

        // 1. Structure Verification
        if (strpos($sql_content, "CREATE TABLE") === false || strpos($sql_content, "users") === false) {
            set_flash("danger", "Backup generation failed structural verification test.");
            redirect("admin_database.php");
        }

        // 2. Automated Retention: Save local copy and prune >30-day backups
        $backup_dir = __DIR__ . "/storage/backups";
        if (!is_dir($backup_dir)) {
            @mkdir($backup_dir, 0775, true);
        }
        @file_put_contents($backup_dir . "/" . $filename, $sql_content);

        // Prune backups older than 30 days
        $threshold = time() - (30 * 86400);
        foreach (glob($backup_dir . "/backup_*.sql") as $old_file) {
            if (filemtime($old_file) < $threshold) {
                @unlink($old_file);
            }
        }

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-Length: " . strlen($sql_content));
        header("Pragma: no-cache");

        echo $sql_content;
        log_action("Database backup created", "database", null, "Full backup verified & saved: $filename");
        exit;
    }

    /* ── Clear Logs ── */
    if ($action === "clear_logs") {
        if (!empty($_POST["confirm_clear"])) {
            global $conn;
            $conn->query("TRUNCATE TABLE audit_logs");
            $conn->query("TRUNCATE TABLE login_history");
            log_action("Cleared all logs", "database", null, "audit_logs and login_history truncated");
            set_flash("success", "All audit and login logs have been cleared.");
        } else {
            set_flash("warning", "Please tick the confirmation checkbox to clear logs.");
        }
        redirect("admin_database.php");
    }

    /* ── Reset Database ── */
    if ($action === "reset_db") {
        $confirm_text = trim($_POST["confirm_text"] ?? "");
        $admin_pass   = (string)($_POST["admin_password"] ?? "");
        $totp_code    = trim($_POST["totp_code"] ?? "");

        $user = current_user();
        if (empty($user["password_hash"]) || !password_verify($admin_pass, $user["password_hash"])) {
            set_flash("danger", "Admin password verification failed.");
            redirect("admin_database.php");
        }

        if (!empty($user["totp_enabled"]) && !empty($user["totp_secret"])) {
            if (!totp_verify($user["totp_secret"], $totp_code)) {
                set_flash("danger", "Invalid 2FA verification code.");
                redirect("admin_database.php");
            }
        }

        if ($confirm_text !== "RESET DATABASE") {
            set_flash("danger", "You must type RESET DATABASE exactly to confirm.");
            redirect("admin_database.php");
        }
        global $conn;
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        foreach (["luggage","guests","notifications","import_history","luggage_transfers","shift_handovers","lost_found_items"] as $t) {
            $conn->query("TRUNCATE TABLE `$t`");
        }
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
        $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
        log_action("CRITICAL: RESET DATABASE", "database", null, "All operational data wiped by admin user: {$user['username']} from IP: {$ip}");
        set_flash("success", "Database reset complete. Users and settings were preserved.");
        redirect("admin_database.php");
    }
}

/* ── Table stats ── */
global $conn;
$all_tables_stat = [];
$res = $conn->query("SHOW TABLE STATUS");
while ($row = $res->fetch_assoc()) {
    $all_tables_stat[] = $row;
}

render_header("Database Management", "admin_db");
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <span class="admin-page-icon"><i class="bi bi-database-gear"></i></span>
            Database Management
        </h1>
        <p class="text-muted mb-0">Backup, restore, reset and maintain your database.</p>
    </div>
</div>

<!-- 4 Action Cards -->
<div class="row g-4 mb-5">

    <!-- Backup -->
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm db-card db-card--info">
            <div class="card-body p-4">
                <div class="db-card-icon" style="background:#0891b220;color:#0891b2;"><i class="bi bi-cloud-arrow-down"></i></div>
                <h3 class="fw-bold mt-3 mb-1">Backup Database</h3>
                <p class="text-muted small mb-4">Download a complete SQL dump of all tables including users, guests, luggage, and settings.</p>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-download"></i> Download SQL Backup
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Restore -->
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm db-card db-card--warning">
            <div class="card-body p-4">
                <div class="db-card-icon" style="background:#f59e0b20;color:#f59e0b;"><i class="bi bi-cloud-arrow-up"></i></div>
                <h3 class="fw-bold mt-3 mb-1">Restore Database</h3>
                <p class="text-muted small mb-4">Upload a previously downloaded .sql backup file to restore your data.</p>
                <div class="alert alert-warning py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    For large restores, use <strong>phpMyAdmin</strong> directly for best results.
                </div>
                <button class="btn btn-outline-warning w-100" disabled>
                    <i class="bi bi-upload me-1"></i> Restore (Use phpMyAdmin)
                </button>
            </div>
        </div>
    </div>

    <!-- Clear Logs -->
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm db-card db-card--orange">
            <div class="card-body p-4">
                <div class="db-card-icon" style="background:#f97316 20;color:#f97316;"><i class="bi bi-journal-x"></i></div>
                <h3 class="fw-bold mt-3 mb-1">Clear Logs</h3>
                <p class="text-muted small mb-3">Permanently delete all audit trail and login history records. Operational data is not affected.</p>
                <form method="POST" onsubmit="return document.getElementById('confirmClear').checked || (alert('Tick the checkbox to confirm!'), false);">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="clear_logs">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmClear" name="confirm_clear" value="1">
                        <label class="form-check-label small fw-semibold text-danger" for="confirmClear">
                            I understand this will permanently delete all logs
                        </label>
                    </div>
                    <button type="submit" class="btn btn-warning text-white w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-trash"></i> Clear All Logs
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Database -->
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm db-card db-card--danger">
            <div class="card-body p-4">
                <div class="db-card-icon" style="background:#ef444420;color:#ef4444;"><i class="bi bi-nuclear"></i></div>
                <h3 class="fw-bold mt-3 mb-1 text-danger">Reset Database</h3>
                <p class="text-muted small mb-1">⚠️ <strong>Danger zone.</strong> Deletes ALL guests, luggage, payments, and operational records.</p>
                <p class="text-muted small mb-3">User accounts and system settings are <strong>preserved</strong>.</p>
                <form method="POST" onsubmit="return confirm('Are you sure you want to PERMANENTLY reset all operational database records?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="reset_db">
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark mb-1">Admin Password</label>
                        <input class="form-control form-control-sm" type="password" name="admin_password" required placeholder="Confirm admin password" autocomplete="current-password">
                    </div>
                    <?php if (!empty(current_user()["totp_enabled"])): ?>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark mb-1">2FA Code</label>
                        <input class="form-control form-control-sm" type="text" name="totp_code" required placeholder="6-digit code" autocomplete="off">
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-danger mb-1">Type <code>RESET DATABASE</code> to confirm</label>
                        <input class="form-control form-control-sm border-danger" type="text" id="resetInput" name="confirm_text" placeholder="RESET DATABASE" autocomplete="off" required>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill"></i> Reset All Data
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<!-- Table Statistics -->
<div class="card">
    <div class="card-header bg-transparent border-bottom px-4 py-3">
        <h2 class="section-title mb-0"><i class="bi bi-table me-2"></i>Table Statistics</h2>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr>
                <th>Table Name</th><th>Rows (est.)</th><th>Data Size</th><th>Index Size</th><th>Engine</th><th>Collation</th>
            </tr></thead>
            <tbody>
            <?php foreach ($all_tables_stat as $ts): ?>
            <tr>
                <td class="fw-bold font-monospace small"><?php echo h($ts["Name"]); ?></td>
                <td><?php echo number_format((int)$ts["Rows"]); ?></td>
                <td class="small text-muted"><?php echo $ts["Data_length"] ? round((int)$ts["Data_length"]/1024, 1)." KB" : "—"; ?></td>
                <td class="small text-muted"><?php echo $ts["Index_length"] ? round((int)$ts["Index_length"]/1024, 1)." KB" : "—"; ?></td>
                <td><span class="badge text-bg-secondary"><?php echo h($ts["Engine"]); ?></span></td>
                <td class="small text-muted"><?php echo h($ts["Collation"] ?? "—"); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.admin-page-icon{width:40px;height:40px;border-radius:12px;background:var(--brand-light);color:var(--brand);display:inline-flex;align-items:center;justify-content:center;font-size:1.2rem;}
.db-card{border-radius:18px;transition:transform .2s,box-shadow .2s;}
.db-card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,.12)!important;}
.db-card-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;}
</style>
<?php render_footer(); ?>

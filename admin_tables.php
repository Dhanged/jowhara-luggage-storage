<?php
require_once __DIR__ . "/layout.php";
require_admin();

$tables_map = [
    "guests"        => ["label" => "Guests Table",       "icon" => "bi-person-lines-fill", "color" => "#3b82f6"],
    "luggage"       => ["label" => "Luggage Table",      "icon" => "bi-briefcase-fill",    "color" => "#10b981"],
    "users"         => ["label" => "Users Table",        "icon" => "bi-people-fill",       "color" => "#8b5cf6"],
    "audit_logs"    => ["label" => "Logs Table",         "icon" => "bi-journal-text",      "color" => "#f59e0b"],
    "login_history" => ["label" => "Login History",      "icon" => "bi-shield-lock",       "color" => "#0891b2"],
];

$selected = $_GET["table"] ?? "guests";
if (!array_key_exists($selected, $tables_map)) $selected = "guests";

/* ── Row counts for sidebar ── */
$counts = [];
foreach (array_keys($tables_map) as $t) {
    $counts[$t] = (int)db_scalar("SELECT COUNT(*) FROM `$t`");
}

/* ── Columns for selected table ── */
global $conn;
$columns = [];
$col_res = $conn->query("SHOW COLUMNS FROM `$selected`");
while ($c = $col_res->fetch_assoc()) {
    $columns[] = $c["Field"];
}

/* ── Rows (last 50) ── */
$rows = db_fetch_all("SELECT * FROM `$selected` ORDER BY 1 DESC LIMIT 50");
$total_count = $counts[$selected];
$info = $tables_map[$selected];

render_header("Tables Management", "admin_tables");
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <span class="admin-page-icon"><i class="bi bi-table"></i></span>
            Tables Management
        </h1>
        <p class="text-secondary mb-0">Browse and inspect database tables directly.</p>
    </div>
    <a href="export_excel.php" class="btn btn-outline-success btn-sm">
        <i class="bi bi-file-earmark-excel me-1"></i>Export Current
    </a>
</div>

<div class="row g-4">
    <!-- Sidebar nav -->
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header bg-transparent border-bottom px-3 py-3">
                <span class="fw-bold small text-muted text-uppercase" style="letter-spacing:.06em;">Tables</span>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($tables_map as $tkey => $tinfo): ?>
                <a href="admin_tables.php?table=<?php echo urlencode($tkey); ?>"
                   class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-2 py-3 px-3
                          <?php echo $selected === $tkey ? "active" : ""; ?>"
                   style="<?php echo $selected === $tkey ? "border-left:3px solid {$tinfo['color']};" : "border-left:3px solid transparent;"; ?>">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi <?php echo $tinfo["icon"]; ?>" style="color:<?php echo $tinfo["color"]; ?>;"></i>
                        <span class="fw-semibold small"><?php echo h($tinfo["label"]); ?></span>
                    </span>
                    <span class="badge rounded-pill" style="background:<?php echo $tinfo["color"]; ?>; color:#fff;">
                        <?php echo number_format($counts[$tkey]); ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Stats Card -->
        <div class="card mt-3">
            <div class="card-body py-3 px-3">
                <div class="small fw-bold text-muted text-uppercase mb-2" style="letter-spacing:.06em;">Quick Stats</div>
                <?php
                $total_guests  = $counts["guests"];
                $total_luggage = $counts["luggage"];
                $total_users   = $counts["users"];
                $total_logs    = $counts["audit_logs"];
                foreach ([
                    ["Guests",  $total_guests,  "#3b82f6"],
                    ["Luggage", $total_luggage, "#10b981"],
                    ["Users",   $total_users,   "#8b5cf6"],
                    ["Log Rows",$total_logs,    "#f59e0b"],
                ] as [$l, $v, $c]): ?>
                <div class="d-flex justify-content-between align-items-center py-1">
                    <span class="small text-muted"><?php echo $l; ?></span>
                    <span class="fw-bold small" style="color:<?php echo $c; ?>;"><?php echo number_format($v); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Table viewer -->
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header bg-transparent border-bottom px-4 py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi <?php echo h($info["icon"]); ?> fs-5" style="color:<?php echo h($info["color"]); ?>;"></i>
                    <h2 class="section-title mb-0"><?php echo h($info["label"]); ?></h2>
                    <span class="badge ms-1" style="background:<?php echo h($info["color"]); ?>; color:#fff;">
                        <?php echo number_format($total_count); ?> rows
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Showing last 50 rows</span>
                    <input class="form-control form-control-sm" type="search" id="tableSearch" placeholder="Search…" style="width:170px;">
                </div>
            </div>

            <?php if ($rows): ?>
            <div class="table-responsive" style="max-height:600px; overflow:auto;">
                <table class="table table-sm align-middle mb-0 table-hover" id="tableViewer" style="font-size:.8rem; white-space:nowrap;">
                    <thead style="position:sticky;top:0;z-index:1;background:var(--surface-soft);">
                        <tr>
                            <?php foreach ($columns as $col): ?>
                            <th class="px-3 py-2 fw-bold"><?php echo h($col); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($columns as $col):
                            $val = $row[$col] ?? null;
                            $display = $val === null ? "<span class='text-muted opacity-50'>NULL</span>" : h(mb_strimwidth((string)$val, 0, 80, "…"));
                            $is_id   = str_ends_with($col, "_id") || $col === "id";
                            $is_date = str_contains($col, "_at") || str_contains($col, "_date");
                        ?>
                        <td class="px-3 py-1 <?php echo $is_id?"fw-bold text-muted":($is_date?"text-muted":""); ?>"
                            title="<?php echo h((string)($val ?? "NULL")); ?>">
                            <?php echo $display; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state py-5">No rows found in this table.</div>
            <?php endif; ?>

            <div class="card-footer bg-transparent border-top px-4 py-2">
                <span class="small text-muted">
                    Table: <code><?php echo h($selected); ?></code> ·
                    <?php echo count($columns); ?> columns ·
                    <?php echo number_format($total_count); ?> total rows ·
                    Showing <?php echo count($rows); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<style>
.admin-page-icon{width:40px;height:40px;border-radius:12px;background:var(--brand-light);color:var(--brand);display:inline-flex;align-items:center;justify-content:center;font-size:1.2rem;}
.list-group-item.active{background:var(--surface-soft)!important;color:var(--ink)!important;border-color:var(--border)!important;}
</style>
<script>
document.getElementById("tableSearch").addEventListener("input", function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll("#tableViewer tbody tr").forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? "" : "none";
    });
});
</script>
<?php render_footer(); ?>

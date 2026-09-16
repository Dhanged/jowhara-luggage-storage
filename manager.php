<?php
require_once __DIR__ . "/layout.php";
require_permission("manager_dashboard");

$month = $_GET["month"] ?? date("Y-m");
$month_start = $month . "-01";
$month_end = date("Y-m-t", strtotime($month_start));

// Handle soft-delete archive purge action
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "archive_collected") {
    verify_csrf();
    $days = (int)($_POST["archive_days"] ?? 30);
    if ($days < 1) $days = 30;

    $count_row = db_fetch_one(
        "SELECT COUNT(*) AS total FROM luggage WHERE status = 'Collected' AND deleted_at IS NULL AND checkout_date < DATE_SUB(NOW(), INTERVAL ? DAY)",
        "i",
        [$days]
    );
    $total_to_archive = (int)($count_row["total"] ?? 0);

    if ($total_to_archive > 0) {
        db_execute(
            "UPDATE luggage SET deleted_at = NOW() WHERE status = 'Collected' AND deleted_at IS NULL AND checkout_date < DATE_SUB(NOW(), INTERVAL ? DAY)",
            "i",
            [$days]
        );
        log_audit("Archive Collected Luggage", "luggage", 0, "Archived $total_to_archive collected bags older than $days days");
        set_flash("success", sprintf(__("Successfully archived %d collected bags older than %d days."), $total_to_archive, $days));
    } else {
        set_flash("info", __("No collected luggage met the archiving criteria."));
    }
    redirect("manager.php?month=" . $month);
}

// Fetch number of collected bags archivable older than 30 days
$archivable_30 = db_fetch_one(
    "SELECT COUNT(*) AS total FROM luggage WHERE status = 'Collected' AND deleted_at IS NULL AND checkout_date < DATE_SUB(NOW(), INTERVAL 30 DAY)"
);
$archivable_count = (int)($archivable_30["total"] ?? 0);

$totals = db_fetch_one(
    "SELECT
        COUNT(*) AS total_rows,
        SUM(CASE WHEN status = 'Stored' THEN 1 ELSE 0 END) AS stored,
        SUM(CASE WHEN status = 'Collected' THEN 1 ELSE 0 END) AS collected,
        SUM(CASE WHEN status = 'Stored' AND expected_pickup_date IS NOT NULL AND expected_pickup_date < CURDATE() THEN 1 ELSE 0 END) AS overdue,
        SUM(CASE WHEN payment_status = 'Paid' THEN fee_amount ELSE 0 END) AS revenue,
        SUM(CASE WHEN payment_status = 'Unpaid' THEN fee_amount ELSE 0 END) AS unpaid
     FROM luggage
     WHERE deleted_at IS NULL AND DATE(checkin_date) BETWEEN ? AND ?",
    "ss",
    [$month_start, $month_end]
);

$busiest = db_fetch_all(
    "SELECT DATE(checkin_date) AS day, COUNT(*) AS total
     FROM luggage
     WHERE deleted_at IS NULL AND DATE(checkin_date) BETWEEN ? AND ?
     GROUP BY DATE(checkin_date)
     ORDER BY total DESC, day DESC
     LIMIT 10",
    "ss",
    [$month_start, $month_end]
);

$staff = db_fetch_all(
    "SELECT u.username,
        SUM(CASE WHEN l.created_by = u.user_id THEN 1 ELSE 0 END) AS checkins,
        SUM(CASE WHEN l.checked_out_by = u.user_id THEN 1 ELSE 0 END) AS checkouts
     FROM users u
     LEFT JOIN luggage l ON (l.created_by = u.user_id OR l.checked_out_by = u.user_id)
        AND l.deleted_at IS NULL
        AND DATE(l.checkin_date) BETWEEN ? AND ?
     GROUP BY u.user_id
     ORDER BY checkins DESC, checkouts DESC",
    "ss",
    [$month_start, $month_end]
);

render_header("Manager Dashboard", "manager");
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Manager Dashboard</h1>
        <p class="text-secondary mb-0">Monthly operational summary, revenue, overdue count, and staff activity.</p>
    </div>
    <form class="d-flex gap-2" method="GET">
        <input class="form-control" type="month" name="month" value="<?php echo h($month); ?>">
        <button class="btn btn-primary" type="submit">Load</button>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-2"><div class="card metric-card"><div class="card-body"><div class="text-secondary small">Total</div><div class="metric-value mt-2"><?php echo (int)$totals["total_rows"]; ?></div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card metric-card"><div class="card-body"><div class="text-secondary small">Stored</div><div class="metric-value mt-2"><?php echo (int)$totals["stored"]; ?></div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card metric-card"><div class="card-body"><div class="text-secondary small">Collected</div><div class="metric-value mt-2"><?php echo (int)$totals["collected"]; ?></div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card metric-card"><div class="card-body"><div class="text-secondary small">Overdue</div><div class="metric-value mt-2"><?php echo (int)$totals["overdue"]; ?></div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card metric-card"><div class="card-body"><div class="text-secondary small">Revenue</div><div class="metric-value mt-2">$<?php echo h(money($totals["revenue"])); ?></div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card metric-card"><div class="card-body"><div class="text-secondary small">Unpaid</div><div class="metric-value mt-2">$<?php echo h(money($totals["unpaid"])); ?></div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="section-title">Busiest Days</h2></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Date</th><th>Total Check-ins</th></tr></thead>
                    <tbody>
                    <?php foreach ($busiest as $day): ?>
                        <tr><td><?php echo h($day["day"]); ?></td><td><?php echo (int)$day["total"]; ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$busiest): ?><tr><td colspan="2"><div class="empty-state">No activity for this month.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="section-title">Staff Activity</h2></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Staff</th><th>Check-ins</th><th>Checkouts</th></tr></thead>
                    <tbody>
                    <?php foreach ($staff as $person): ?>
                        <tr><td><?php echo h($person["username"]); ?></td><td><?php echo (int)$person["checkins"]; ?></td><td><?php echo (int)$person["checkouts"]; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-4">
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between">
                <h2 class="section-title text-white mb-0"><i class="bi bi-archive-fill me-2"></i><?php echo __("Database Maintenance & Archiving"); ?></h2>
                <span class="badge bg-light text-danger fw-bold"><?php echo __("Manager Only"); ?></span>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <p class="mb-2 fw-semibold text-danger"><?php echo __("Archive Collected Bags (Soft-Delete)"); ?></p>
                        <p class="text-secondary small mb-0">
                            <?php echo __("To maintain peak performance, you can soft-delete historically collected bags. They will be excluded from active statistics but remain in the database logs. Currently, there are"); ?>
                            <span class="badge text-bg-warning"><?php echo $archivable_count; ?></span>
                            <?php echo __("collected bags checked out over 30 days ago that can be safely archived."); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <form method="POST" class="d-flex flex-wrap gap-2 justify-content-md-end" onsubmit="return confirm('<?php echo __("Are you sure you want to archive old collected bags? This operation is logged."); ?>');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="archive_collected">
                            <select name="archive_days" class="form-select form-select-sm w-auto d-inline-block" style="min-width: 140px;">
                                <option value="7">7 <?php echo __("Days"); ?></option>
                                <option value="14">14 <?php echo __("Days"); ?></option>
                                <option value="30" selected>30 <?php echo __("Days (Recommended)"); ?></option>
                                <option value="90">90 <?php echo __("Days"); ?></option>
                                <option value="180">180 <?php echo __("Days"); ?></option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="bi bi-trash3-fill me-1"></i><?php echo __("Purge & Archive"); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>


<?php
require_once __DIR__ . "/layout.php";
require_login();

// Handle shift handover acknowledgment
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "acknowledge_handover") {
    $handover_id = (int)$_POST["handover_id"];
    db_execute(
        "UPDATE shift_handovers SET status = 'Acknowledged', acknowledged_at = NOW()
         WHERE handover_id = ? AND incoming_user_id = ?",
        "ii",
        [$handover_id, (int)current_user()["user_id"]]
    );
    log_action("Shift Handover Acknowledged", "shift", null, "Handover ID $handover_id acknowledged");
    set_flash("success", __("Handover notes acknowledged."));
    redirect("dashboard.php");
}

// Pending handover for current user
$handover = db_fetch_one(
    "SELECT h.*, u.full_name AS outgoing_name, u.username AS outgoing_username
     FROM shift_handovers h
     JOIN users u ON u.user_id = h.outgoing_user_id
     WHERE h.incoming_user_id = ? AND h.status = 'Pending'
     ORDER BY h.created_at DESC LIMIT 1",
    "i",
    [(int)current_user()["user_id"]]
);

// KPI stats (consolidated aggregate query using index-friendly range scans)
$guest_count = (int)db_scalar("SELECT COUNT(*) FROM guests WHERE deleted_at IS NULL");
$staff_count = (int)db_scalar("SELECT COUNT(*) FROM users WHERE status = 'Active' AND deleted_at IS NULL");

$luggage_agg = db_fetch_one(
    "SELECT
        COUNT(*) AS total_luggage,
        SUM(CASE WHEN status = 'Stored' THEN 1 ELSE 0 END) AS stored_count,
        SUM(CASE WHEN status = 'Collected' THEN 1 ELSE 0 END) AS collected_count,
        SUM(CASE WHEN status = 'Stored' AND expected_pickup_date IS NOT NULL AND expected_pickup_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count,
        SUM(CASE WHEN payment_status = 'Paid' THEN fee_amount ELSE 0 END) AS total_revenue,
        SUM(CASE WHEN checkin_date >= CURDATE() AND checkin_date < CURDATE() + INTERVAL 1 DAY THEN 1 ELSE 0 END) AS today_checkins,
        SUM(CASE WHEN checkout_date >= CURDATE() AND checkout_date < CURDATE() + INTERVAL 1 DAY THEN 1 ELSE 0 END) AS today_checkouts,
        SUM(CASE WHEN payment_status = 'Paid' AND checkout_date >= CURDATE() AND checkout_date < CURDATE() + INTERVAL 1 DAY THEN fee_amount ELSE 0 END) AS today_revenue
     FROM luggage
     WHERE deleted_at IS NULL"
);

$today_checkins  = (int)($luggage_agg["today_checkins"] ?? 0);
$today_checkouts = (int)($luggage_agg["today_checkouts"] ?? 0);
$today_txn_count = $today_checkins + $today_checkouts;
$total_revenue   = (float)($luggage_agg["total_revenue"] ?? 0.0);
$paid_today      = (float)($luggage_agg["today_revenue"] ?? 0.0);
$overdue         = (int)($luggage_agg["overdue_count"] ?? 0);

$stats = [
    "Guests"              => $guest_count,
    "Total Luggage"       => (int)($luggage_agg["total_luggage"] ?? 0),
    "Stored"              => (int)($luggage_agg["stored_count"] ?? 0),
    "Collected"           => (int)($luggage_agg["collected_count"] ?? 0),
    "Overdue"             => $overdue,
    "Today's Revenue"     => $paid_today,
    "Active Staff"        => $staff_count,
    "Today Transactions"  => $today_txn_count,
    "Total Revenue"       => $total_revenue,
];

// Recent activities
$recent_activities = db_fetch_all(
    "SELECT a.log_id, a.action, a.entity_type, a.entity_id, a.details, a.ip_address, a.created_at, u.full_name, u.username
     FROM audit_logs a LEFT JOIN users u ON u.user_id = a.user_id
     ORDER BY a.log_id DESC LIMIT 10"
);

// Monthly trend
$monthly_trend_raw = db_fetch_all(
    "SELECT MONTH(checkin_date) AS m, COUNT(*) AS count
     FROM luggage WHERE YEAR(checkin_date) = YEAR(CURDATE()) AND deleted_at IS NULL
     GROUP BY MONTH(checkin_date)"
);
$monthly_trend = array_fill(1, 12, 0);
foreach ($monthly_trend_raw as $r) { $monthly_trend[(int)$r["m"]] = (int)$r["count"]; }

// Luggage types
$luggage_types_raw = db_fetch_all(
    "SELECT luggage_type, COUNT(*) as count FROM luggage WHERE deleted_at IS NULL
     GROUP BY luggage_type ORDER BY count DESC LIMIT 5"
);
$luggage_type_labels = $luggage_type_values = [];
foreach ($luggage_types_raw as $r) {
    $luggage_type_labels[] = $r["luggage_type"];
    $luggage_type_values[] = (int)$r["count"];
}
if (empty($luggage_type_labels)) {
    $luggage_type_labels = ["Suitcase","Backpack","Handbag","Box","Other"];
    $luggage_type_values = [0,0,0,0,0];
}

// Active guests
$active_guests_raw = db_fetch_all(
    "SELECT g.guest_name, COUNT(l.luggage_id) AS count
     FROM luggage l JOIN guests g ON g.guest_id = l.guest_id
     WHERE l.deleted_at IS NULL GROUP BY l.guest_id ORDER BY count DESC LIMIT 5"
);
$active_guest_names = $active_guest_counts = [];
foreach ($active_guests_raw as $r) {
    $active_guest_names[]  = $r["guest_name"];
    $active_guest_counts[] = (int)$r["count"];
}
if (empty($active_guest_names)) { $active_guest_names = ["No Data"]; $active_guest_counts = [0]; }

// Monthly revenue
$monthly_rev_raw = db_fetch_all(
    "SELECT MONTH(checkout_date) AS m, SUM(fee_amount) AS revenue
     FROM luggage WHERE YEAR(checkout_date) = YEAR(CURDATE()) AND payment_status = 'Paid' AND deleted_at IS NULL
     GROUP BY MONTH(checkout_date)"
);
$monthly_rev = array_fill(1, 12, 0.0);
foreach ($monthly_rev_raw as $r) { $monthly_rev[(int)$r["m"]] = (float)$r["revenue"]; }

// Shelf heatmap
$heatmap_data = db_fetch_all(
    "SELECT s.shelf_name, s.zone_name, s.capacity, COUNT(l.luggage_id) AS used_count
     FROM storage_shelves s
     LEFT JOIN luggage l ON l.storage_location = s.shelf_name AND l.status = 'Stored' AND l.deleted_at IS NULL
     WHERE s.status = 'Active'
     GROUP BY s.shelf_id ORDER BY s.zone_name, s.shelf_name"
);

// Shelf occupancy for analytics chart
$shelf_occupancy_labels = $shelf_occupancy_values = [];
foreach ($heatmap_data as $s) {
    $cap = (int)$s["capacity"];
    $shelf_occupancy_labels[] = $s["shelf_name"];
    $shelf_occupancy_values[] = $cap > 0 ? round(((int)$s["used_count"] / $cap) * 100) : 0;
}

// 7-day trend
$days = [];
for ($i = 6; $i >= 0; $i--) { $days[] = date("Y-m-d", strtotime("-$i days")); }

$checkin_counts  = db_fetch_all("SELECT DATE(checkin_date) as d, COUNT(*) as c FROM luggage WHERE checkin_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND deleted_at IS NULL GROUP BY DATE(checkin_date)");
$checkout_counts = db_fetch_all("SELECT DATE(checkout_date) as d, COUNT(*) as c FROM luggage WHERE checkout_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND deleted_at IS NULL GROUP BY DATE(checkout_date)");

$checkins_by_date = $checkouts_by_date = [];
foreach ($checkin_counts  as $r) { $checkins_by_date[$r['d']]  = (int)$r['c']; }
foreach ($checkout_counts as $r) { $checkouts_by_date[$r['d']] = (int)$r['c']; }

$chart_labels = $chart_checkins = $chart_checkouts = [];
foreach ($days as $day) {
    $chart_labels[]    = date("D, M j", strtotime($day));
    $chart_checkins[]  = $checkins_by_date[$day]  ?? 0;
    $chart_checkouts[] = $checkouts_by_date[$day] ?? 0;
}

// Recent luggage
$recent = db_fetch_all(
    "SELECT l.*, g.guest_name, g.room_no
     FROM luggage l JOIN guests g ON g.guest_id = l.guest_id
     WHERE l.deleted_at IS NULL ORDER BY l.luggage_id DESC LIMIT 8"
);

// L&F status counts
$lf_status_raw = db_fetch_all(
    "SELECT status, COUNT(*) as count FROM lost_found_items WHERE deleted_at IS NULL GROUP BY status"
);
$lf_status_counts = ["Found" => 0, "Claimed" => 0, "Disposed" => 0];
foreach ($lf_status_raw as $r) {
    if (isset($lf_status_counts[$r["status"]])) {
        $lf_status_counts[$r["status"]] = (int)$r["count"];
    }
}

// Most Active Users (actions in last 30 days)
$active_users_raw = db_fetch_all(
    "SELECT u.full_name, u.username, COUNT(a.log_id) AS count
     FROM audit_logs a
     JOIN users u ON u.user_id = a.user_id
     WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY a.user_id
     ORDER BY count DESC
     LIMIT 5"
);
$active_user_names = [];
$active_user_counts = [];
foreach ($active_users_raw as $r) {
    $active_user_names[] = $r["full_name"] ?: $r["username"];
    $active_user_counts[] = (int)$r["count"];
}
if (empty($active_user_names)) {
    $active_user_names = ["No Data"];
    $active_user_counts = [0];
}

// L&F trends (last 6 months)
$lf_months = [];
for ($i = 5; $i >= 0; $i--) {
    $lf_months[] = [
        "year_month" => date("Y-m", strtotime("-$i months")),
        "label" => date("M Y", strtotime("-$i months")),
        "found" => 0,
        "claimed" => 0
    ];
}
$found_by_month = db_fetch_all(
    "SELECT DATE_FORMAT(found_date, '%Y-%m') AS ym, COUNT(*) AS count
     FROM lost_found_items
     WHERE found_date >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 5 MONTH) AND deleted_at IS NULL
     GROUP BY DATE_FORMAT(found_date, '%Y-%m')"
);
$claimed_by_month = db_fetch_all(
    "SELECT DATE_FORMAT(claimed_date, '%Y-%m') AS ym, COUNT(*) AS count
     FROM lost_found_items
     WHERE status = 'Claimed' AND claimed_date >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 5 MONTH) AND deleted_at IS NULL
     GROUP BY DATE_FORMAT(claimed_date, '%Y-%m')"
);
$found_map = [];
foreach ($found_by_month as $r) { $found_map[$r["ym"]] = (int)$r["count"]; }
$claimed_map = [];
foreach ($claimed_by_month as $r) { $claimed_map[$r["ym"]] = (int)$r["count"]; }

$lf_trend_labels = [];
$lf_trend_found = [];
$lf_trend_claimed = [];
foreach ($lf_months as $m) {
    $lf_trend_labels[] = $m["label"];
    $lf_trend_found[] = $found_map[$m["year_month"]] ?? 0;
    $lf_trend_claimed[] = $claimed_map[$m["year_month"]] ?? 0;
}

// Today's activities
$today_activities = db_fetch_all(
    "SELECT a.log_id, a.action, a.entity_type, a.entity_id, a.details, a.created_at, u.full_name, u.username
     FROM audit_logs a
     LEFT JOIN users u ON u.user_id = a.user_id
     WHERE DATE(a.created_at) = CURDATE()
     ORDER BY a.log_id DESC"
);


$stat_icons = [
    "Guests"             => "bi-people-fill",
    "Total Luggage"      => "bi-briefcase-fill",
    "Stored"             => "bi-box-seam-fill",
    "Collected"          => "bi-check-circle-fill",
    "Overdue"            => "bi-exclamation-triangle-fill",
    "Today's Revenue"    => "bi-cash-coin",
    "Active Staff"       => "bi-person-badge-fill",
    "Today Transactions" => "bi-receipt",
    "Total Revenue"      => "bi-bank",
];

$stat_colors = [
    "Guests"             => "#3b82f6",
    "Total Luggage"      => "#10b981",
    "Stored"             => "#f59e0b",
    "Collected"          => "#06b6d4",
    "Overdue"            => "#ef4444",
    "Today's Revenue"    => "#10b981",
    "Active Staff"       => "#8b5cf6",
    "Today Transactions" => "#6366f1",
    "Total Revenue"      => "#0891b2",
];

$stat_is_money = ["Today's Revenue" => true, "Total Revenue" => true];

render_header("Dashboard", "dashboard");
?>

<?php if ($handover): ?>
<div class="handover-alert mb-4">
    <div class="handover-alert-icon">
        <i class="bi bi-arrow-left-right"></i>
    </div>
    <div class="handover-alert-body">
        <div class="handover-alert-title"><?php echo __("Shift Handover Alert"); ?></div>
        <div class="handover-alert-msg">
            <strong><?php echo h($handover['outgoing_name'] ?: $handover['outgoing_username']); ?></strong>:
            <em>"<?php echo h($handover['notes']); ?>"</em>
        </div>
        <div class="handover-alert-time">
            <i class="bi bi-clock me-1"></i><?php echo date("Y-m-d H:i", strtotime($handover['created_at'])); ?>
        </div>
    </div>
    <form method="POST" action="dashboard.php" class="ms-auto flex-shrink-0">
        <input type="hidden" name="action" value="acknowledge_handover">
        <input type="hidden" name="handover_id" value="<?php echo (int)$handover['handover_id']; ?>">
        <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-check-circle"></i> <?php echo __("Acknowledge"); ?>
        </button>
    </form>
</div>
<?php endif; ?>

<!-- ══════════════════ WELCOME BANNER ══════════════════ -->
<div class="welcome-banner mb-4">
    <div class="welcome-content">
        <div class="welcome-left">
            <div class="welcome-avatar-wrap">
                <img src="assets/logo.jpg" alt="Hotel Logo" class="welcome-avatar">
            </div>
            <div class="welcome-text">
                <h1 class="welcome-heading">
                    <?php echo __("Welcome back"); ?>,
                    <span class="welcome-name"><?php echo h(current_user()["full_name"] ?: current_user()["username"]); ?></span>
                </h1>
                <p class="welcome-sub"><?php echo __("Your luggage operations hub — store, track, and release."); ?></p>
            </div>
        </div>
        <div class="welcome-right d-none d-lg-flex">
            <div class="welcome-clock-block">
                <div class="welcome-clock" id="liveClock">--:--:--</div>
                <div class="welcome-date"><?php echo date("l, F j, Y"); ?></div>
            </div>
        </div>
    </div>
    <div class="welcome-actions">
        <a class="wbtn wbtn-ghost" href="scanner.php">
            <i class="bi bi-qr-code-scan"></i> <?php echo __("Scanner"); ?>
        </a>
        <?php if ($overdue > 0): ?>
        <a class="wbtn wbtn-danger" href="luggage.php?overdue=1">
            <i class="bi bi-exclamation-triangle"></i>
            <?php echo __("Overdue"); ?>
            <span class="wbtn-badge"><?php echo $overdue; ?></span>
        </a>
        <?php endif; ?>
        <?php if (user_can("create")): ?>
        <a class="wbtn wbtn-ghost" href="add_guest.php">
            <i class="bi bi-person-plus"></i> <?php echo __("Add Guest"); ?>
        </a>
        <a class="wbtn wbtn-accent" href="add_luggage.php">
            <i class="bi bi-bag-plus"></i> <?php echo __("Add Luggage"); ?>
        </a>
        <?php endif; ?>
    </div>
    <div class="welcome-deco"><i class="bi bi-hotel"></i></div>
</div>

<!-- ══════════════════ KPI METRICS ══════════════════ -->
<div class="kpi-grid mb-4">
    <?php foreach ($stats as $label => $value):
        $is_money = isset($stat_is_money[$label]);
        $fval     = $is_money ? "$" . money($value) : h($value);
        $color    = $stat_colors[$label] ?? "var(--brand)";
        $icon     = $stat_icons[$label] ?? "bi-circle";
    ?>
    <div class="kpi-card" style="--kpi-color:<?php echo $color; ?>;">
        <div class="kpi-icon-wrap">
            <i class="bi <?php echo $icon; ?>"></i>
        </div>
        <div class="kpi-body">
            <div class="kpi-label"><?php echo h(__($label)); ?></div>
            <div class="kpi-value"><?php echo $fval; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ══════════════════ TABS ══════════════════ -->
<ul class="nav nav-pills mb-4 gap-2 no-print" id="dashboardTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active d-flex align-items-center gap-2"
                id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab">
            <i class="bi bi-speedometer2"></i> <?php echo __("Live Overview"); ?>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link d-flex align-items-center gap-2"
                id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics-pane" type="button" role="tab">
            <i class="bi bi-bar-chart-line-fill"></i> <?php echo __("Advanced Analytics"); ?>
        </button>
    </li>
</ul>

<div class="tab-content" id="dashboardTabsContent">

    <!-- ── Tab 1: Live Overview ── -->
    <div class="tab-pane fade show active" id="overview-pane" role="tabpanel">

        <div class="row g-3 mb-4">
            <!-- 7-day chart -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between px-4 pt-3 pb-0">
                        <h2 class="section-title d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="bi bi-graph-up"></i></span>
                            <?php echo __("Activity Flow"); ?>
                        </h2>
                        <span class="text-muted small"><?php echo __("Last 7 days"); ?></span>
                    </div>
                    <div class="card-body px-4 pt-2 pb-3">
                        <canvas id="trendChart" height="220"></canvas>
                    </div>
                </div>
            </div>
            <!-- Donut -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between px-4 pt-3 pb-0">
                        <h2 class="section-title d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="bi bi-pie-chart"></i></span>
                            <?php echo __("Status"); ?>
                        </h2>
                        <a class="btn btn-sm btn-outline-secondary" href="reports.php"><?php echo __("Reports"); ?></a>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center px-4 pt-2 pb-3">
                        <div style="max-height:210px;width:100%;position:relative;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <!-- Recent luggage table -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2 px-4 py-3">
                        <h2 class="section-title d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="bi bi-clock-history"></i></span>
                            <?php echo __("Recent Luggage"); ?>
                        </h2>
                        <form class="d-flex gap-2" action="luggage.php" method="GET">
                            <input class="form-control form-control-sm" type="search" name="search"
                                   placeholder="<?php echo __('Search…'); ?>" style="min-width:150px;">
                            <button class="btn btn-primary btn-sm" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo __("Tag"); ?></th>
                                    <th><?php echo __("Guest"); ?></th>
                                    <th><?php echo __("Type"); ?></th>
                                    <th><?php echo __("Location"); ?></th>
                                    <th><?php echo __("Status"); ?></th>
                                    <th class="text-end"><?php echo __("Action"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $row): ?>
                                <tr>
                                    <td class="fw-bold" style="color:var(--brand);font-family:monospace;">
                                        <?php echo h($row["tag_code"]); ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small"><?php echo h($row["guest_name"]); ?></div>
                                        <div class="text-muted" style="font-size:.72rem;">
                                            <?php echo __("Room"); ?> <?php echo h($row["room_no"]); ?>
                                        </div>
                                    </td>
                                    <td class="small text-muted"><?php echo h($row["luggage_type"]); ?></td>
                                    <td>
                                        <span class="badge text-bg-secondary">
                                            <?php echo h(trim(($row["storage_zone"] ?? "") . " " . ($row["storage_location"] ?? ""))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-<?php echo h(status_badge($row["status"])); ?>">
                                            <?php echo h($row["status"]); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions justify-content-end">
                                            <a class="btn btn-sm btn-outline-secondary" href="tag.php?id=<?php echo (int)$row["luggage_id"]; ?>">
                                                <?php echo __("Tag"); ?>
                                            </a>
                                            <a class="btn btn-sm btn-outline-primary" href="receipt.php?id=<?php echo (int)$row["luggage_id"]; ?>">
                                                <?php echo __("Receipt"); ?>
                                            </a>
                                            <?php if ($row["status"] === "Stored" && user_can("checkout")): ?>
                                            <a class="btn btn-sm btn-success d-flex align-items-center gap-1"
                                               href="checkout.php?id=<?php echo (int)$row["luggage_id"]; ?>">
                                                <i class="bi bi-check-circle"></i> <?php echo __("Checkout"); ?>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (!$recent): ?>
                                <tr><td colspan="6"><div class="empty-state"><?php echo __("No luggage added yet."); ?></div></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Today's summary -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-bottom px-4 py-3">
                        <h2 class="section-title d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="bi bi-calendar-check"></i></span>
                            <?php echo __("Today's Summary"); ?>
                        </h2>
                    </div>
                    <div class="card-body p-0">
                        <?php
                        $summary_rows = [
                            ["label" => __("Checked in"),     "value" => $today_checkins,  "color" => "#10b981", "cls" => ""],
                            ["label" => __("Checked out"),    "value" => $today_checkouts, "color" => "#3b82f6", "cls" => ""],
                            ["label" => __("Overdue stored"), "value" => $overdue,          "color" => "#ef4444", "cls" => "text-danger fw-bold"],
                            ["label" => __("Revenue today"),  "value" => "$".money($paid_today), "color" => "#f59e0b", "cls" => "text-success fw-bold"],
                        ];
                        foreach ($summary_rows as $i => $sr):
                        ?>
                        <div class="summary-stat-row <?php echo $i === count($summary_rows)-1 ? 'last' : ''; ?>">
                            <div class="summary-dot" style="background:<?php echo $sr['color']; ?>;"></div>
                            <span class="summary-label"><?php echo $sr['label']; ?></span>
                            <span class="summary-value <?php echo $sr['cls']; ?>"><?php echo $sr['value']; ?></span>
                        </div>
                        <?php endforeach; ?>
                        <div class="p-3">
                            <a class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2"
                               href="luggage.php?status=Stored">
                                <i class="bi bi-folder-symlink"></i>
                                <?php echo __("Open Stored Luggage"); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Heatmap -->
        <div class="card mb-4">
            <div class="card-header bg-transparent border-bottom px-4 py-3 d-flex align-items-center justify-content-between">
                <h2 class="section-title d-flex align-items-center gap-2">
                    <span class="section-icon"><i class="bi bi-grid-3x3-gap"></i></span>
                    <?php echo __("Real-time Capacity Heatmap"); ?>
                </h2>
                <a class="btn btn-sm btn-outline-secondary" href="storage_map.php"><?php echo __("Full Map"); ?></a>
            </div>
            <div class="card-body px-4 py-3">
                <?php if ($heatmap_data): ?>
                <div class="row g-2">
                    <?php foreach ($heatmap_data as $shelf):
                        $used = (int)$shelf["used_count"];
                        $cap  = (int)$shelf["capacity"];
                        $pct  = $cap > 0 ? round(($used / $cap) * 100) : 0;
                        if ($pct >= 90)     { $cc = "danger";  $hex = "#ef4444"; }
                        elseif ($pct >= 50) { $cc = "warning"; $hex = "#f59e0b"; }
                        else                { $cc = "success"; $hex = "#10b981"; }
                    ?>
                    <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                        <div class="heatmap-shelf" style="border-color:<?php echo $hex; ?>40;">
                            <div class="heatmap-shelf-name"><?php echo h($shelf["shelf_name"]); ?></div>
                            <div class="heatmap-shelf-zone">Zone <?php echo h($shelf["zone_name"]); ?></div>
                            <div class="heatmap-shelf-pct">
                                <span class="badge text-bg-<?php echo $cc; ?>"><?php echo $pct; ?>%</span>
                            </div>
                            <div class="heatmap-shelf-usage"><?php echo $used; ?>/<?php echo $cap; ?> <?php echo __("spaces"); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state"><?php echo __("No active shelves configured."); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══════════════════ RECENT ACTIVITIES ══════════════════ -->
        <div class="card mb-4">
            <div class="card-header bg-transparent border-bottom px-4 py-3 d-flex align-items-center justify-content-between">
                <h2 class="section-title d-flex align-items-center gap-2">
                    <span class="section-icon"><i class="bi bi-activity"></i></span>
                    <?php echo __("Recent Activities"); ?>
                </h2>
                <?php if (is_admin()): ?>
                <a class="btn btn-sm btn-outline-secondary" href="admin_audit.php"><?php echo __("View All"); ?></a>
                <?php else: ?>
                <a class="btn btn-sm btn-outline-secondary" href="audit_log.php"><?php echo __("View All"); ?></a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($recent_activities): ?>
                <ul class="activity-feed list-unstyled mb-0">
                    <?php foreach ($recent_activities as $act):
                        $act_lower = strtolower($act['action']);
                        if (str_contains($act_lower,'login'))       { $aicon='bi-box-arrow-in-right'; $acolor='#3b82f6'; }
                        elseif (str_contains($act_lower,'creat'))   { $aicon='bi-plus-circle';        $acolor='#10b981'; }
                        elseif (str_contains($act_lower,'delet'))   { $aicon='bi-trash';              $acolor='#ef4444'; }
                        elseif (str_contains($act_lower,'updat')||str_contains($act_lower,'edit')) { $aicon='bi-pencil'; $acolor='#f59e0b'; }
                        elseif (str_contains($act_lower,'check'))   { $aicon='bi-briefcase';          $acolor='#8b5cf6'; }
                        elseif (str_contains($act_lower,'payment')) { $aicon='bi-cash';               $acolor='#0891b2'; }
                        else                                        { $aicon='bi-dot';                $acolor='#64748b'; }
                        $actor = $act['full_name'] ?: ($act['username'] ?? 'System');
                        $when  = date('M j, H:i', strtotime($act['created_at']));
                    ?>
                    <li class="activity-item">
                        <div class="activity-dot" style="background:<?php echo $acolor; ?>;"><i class="bi <?php echo $aicon; ?>"></i></div>
                        <div class="activity-body">
                            <div class="activity-action"><?php echo h($act['action']); ?>
                                <?php if ($act['entity_type']): ?>
                                <span class="badge text-bg-secondary ms-1" style="font-size:.65rem;"><?php echo h($act['entity_type']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="activity-meta">
                                <span class="activity-user"><i class="bi bi-person-fill"></i> <?php echo h($actor); ?></span>
                                <span class="activity-sep">·</span>
                                <span class="activity-time"><i class="bi bi-clock"></i> <?php echo $when; ?></span>
                                <?php if ($act['ip_address']): ?>
                                <span class="activity-sep">·</span>
                                <span class="text-muted" style="font-size:.7rem;"><?php echo h($act['ip_address']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="empty-state"><?php echo __("No recent activity."); ?></div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /overview-pane -->

    <!-- ── Tab 2: Advanced Analytics ── -->
    <div class="tab-pane fade" id="analytics-pane" role="tabpanel">

        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-0 px-4 pt-3 pb-0">
                        <h3 class="section-title d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="bi bi-calendar3"></i></span>
                            <?php echo __("Monthly Storage Trend"); ?> (<?php echo date("Y"); ?>)
                        </h3>
                    </div>
                    <div class="card-body px-4 pt-2 pb-3">
                        <div style="height:250px;position:relative;"><canvas id="monthlyTrendChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-0 px-4 pt-3 pb-0">
                        <h3 class="section-title d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="bi bi-tag-fill"></i></span>
                            <?php echo __("Luggage by Type"); ?>
                        </h3>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center px-4 pt-2 pb-3">
                        <div style="height:220px;width:100%;position:relative;"><canvas id="luggageTypeChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-0 px-4 pt-3 pb-0">
                        <h3 class="section-title d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="bi bi-percent"></i></span>
                            <?php echo __("Shelf Occupancy"); ?>
                        </h3>
                    </div>
                    <div class="card-body px-4 pt-2 pb-3">
                        <div style="height:230px;position:relative;"><canvas id="occupancyChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-0 px-4 pt-3 pb-0">
                        <h3 class="section-title d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="bi bi-people-fill"></i></span>
                            <?php echo __("Most Active Guests"); ?>
                        </h3>
                    </div>
                    <div class="card-body px-4 pt-2 pb-3">
                        <div style="height:230px;position:relative;"><canvas id="activeGuestsChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-0 px-4 pt-3 pb-0">
                        <h3 class="section-title d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="bi bi-cash-stack"></i></span>
                            <?php echo __("Monthly Revenue"); ?>
                        </h3>
                    </div>
                    <div class="card-body px-4 pt-2 pb-3">
                        <div style="height:230px;position:relative;"><canvas id="monthlyRevChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Lost & Found Status, L&F Trend, Active Users -->
        <div class="row g-3 mb-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-0 px-4 pt-3 pb-0">
                        <h3 class="section-title d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="bi bi-shield-check"></i></span>
                            <?php echo __("Lost & Found Status"); ?>
                        </h3>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center px-4 pt-2 pb-3">
                        <div style="height:230px;width:100%;position:relative;"><canvas id="lfStatusChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-0 px-4 pt-3 pb-0">
                        <h3 class="section-title d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="bi bi-graph-up-arrow"></i></span>
                            <?php echo __("Lost & Found Trends"); ?>
                        </h3>
                    </div>
                    <div class="card-body px-4 pt-2 pb-3">
                        <div style="height:230px;position:relative;"><canvas id="lfTrendsChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-0 px-4 pt-3 pb-0">
                        <h3 class="section-title d-flex align-items-center gap-2">
                            <span class="section-icon"><i class="bi bi-people-fill"></i></span>
                            <?php echo __("Most Active Users"); ?> (30d)
                        </h3>
                    </div>
                    <div class="card-body px-4 pt-2 pb-3">
                        <div style="height:230px;position:relative;"><canvas id="activeUsersChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 4: Daily Activity Timeline -->
        <div class="card mb-4">
            <div class="card-header bg-transparent border-bottom px-4 py-3">
                <h3 class="section-title d-flex align-items-center gap-2 mb-0">
                    <span class="section-icon"><i class="bi bi-calendar-day"></i></span>
                    <?php echo __("Daily Activity Timeline"); ?>
                </h3>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?php echo __("Time"); ?></th>
                            <th><?php echo __("User"); ?></th>
                            <th><?php echo __("Action"); ?></th>
                            <th><?php echo __("Target"); ?></th>
                            <th><?php echo __("Details"); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_activities as $act):
                            $actor = $act['full_name'] ?: ($act['username'] ?? 'System');
                            $time  = date('H:i:s', strtotime($act['created_at']));
                        ?>
                        <tr>
                            <td class="fw-semibold text-secondary" style="font-size: .85rem;"><?php echo $time; ?></td>
                            <td class="fw-bold" style="font-size: .85rem;"><?php echo h($actor); ?></td>
                            <td>
                                <span class="badge bg-secondary"><?php echo h($act['action']); ?></span>
                            </td>
                            <td>
                                <?php if ($act['entity_type']): ?>
                                    <span class="badge bg-light text-dark border"><?php echo h($act['entity_type']); ?> #<?php echo (int)$act['entity_id']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary"><?php echo h($act['details']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$today_activities): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state py-4 text-center text-muted">
                                    <i class="bi bi-clock-history fs-2 d-block mb-2"></i>
                                    <?php echo __("No activities recorded today yet."); ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /analytics-pane -->

</div><!-- /tab-content -->

<!-- ══════════════════ PAGE-SCOPED STYLES ══════════════════ -->
<style>
/* Welcome Banner */
.welcome-banner {
    position: relative;
    overflow: hidden;
    border-radius: 20px;
    background: linear-gradient(140deg, #1d6f6f 0%, #0a3535 100%);
    padding: 1.75rem 2rem 1rem;
    color: #fff;
}
.welcome-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
    position: relative;
    z-index: 2;
}
.welcome-left  { display:flex; align-items:center; gap:1rem; }
.welcome-right { display:flex; align-items:flex-end; flex-direction:column; }
.welcome-avatar-wrap {
    width:60px; height:60px; flex-shrink:0;
}
.welcome-avatar {
    width:60px; height:60px;
    border-radius:14px;
    object-fit:cover;
    border:2px solid rgba(255,255,255,.3);
    box-shadow:0 4px 14px rgba(0,0,0,.35);
}
.welcome-heading {
    font-size:1.25rem; font-weight:800;
    letter-spacing:-.3px; color:#fff;
    margin:0 0 .25rem;
    line-height:1.2;
}
.welcome-name { color:#6ee7b7; }
.welcome-sub  { font-size:.82rem; color:rgba(255,255,255,.55); margin:0; }
.welcome-clock { font-size:1.6rem; font-weight:800; color:#fff; letter-spacing:-.5px; line-height:1; }
.welcome-date  { font-size:.78rem; color:rgba(255,255,255,.5); margin-top:.2rem; text-align:right; }
.welcome-clock-block { text-align:right; }

.welcome-actions {
    display:flex; gap:.5rem; flex-wrap:wrap;
    margin-top:1.2rem;
    position:relative; z-index:2;
}
.wbtn {
    display:inline-flex; align-items:center; gap:.4rem;
    padding:.4rem .9rem;
    border-radius:9px;
    font-size:.82rem; font-weight:600;
    text-decoration:none;
    border:1px solid transparent;
    transition:all .2s ease;
    cursor:pointer;
}
.wbtn:hover { transform:translateY(-1px); }
.wbtn-ghost  { background:rgba(255,255,255,.12); color:#fff; border-color:rgba(255,255,255,.2); }
.wbtn-ghost:hover  { background:rgba(255,255,255,.22); color:#fff; }
.wbtn-danger { background:rgba(239,68,68,.22); color:#fca5a5; border-color:rgba(239,68,68,.35); }
.wbtn-danger:hover { background:rgba(239,68,68,.38); color:#fff; }
.wbtn-accent { background:#fff; color:var(--brand); border-color:#fff; }
.wbtn-accent:hover { background:#e0fdf4; color:var(--brand-strong); }
.wbtn-badge {
    background:rgba(255,255,255,.25); color:#fff;
    font-size:.7rem; font-weight:700;
    padding:.1em .45em; border-radius:999px;
}
.welcome-deco {
    position:absolute; right:-40px; top:-50px;
    font-size:12rem; color:#fff; opacity:.04;
    pointer-events:none; line-height:1;
    z-index:1;
}

/* KPI Grid */
.kpi-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(155px,1fr));
    gap:.75rem;
}
.kpi-card {
    background:var(--surface-card);
    border:1px solid var(--border);
    border-left:4px solid var(--kpi-color, var(--brand));
    border-radius:14px;
    padding:1rem 1.1rem .85rem;
    position:relative;
    overflow:hidden;
    transition:transform .22s ease, box-shadow .22s ease;
    box-shadow:var(--shadow-card);
}
.kpi-card:hover {
    transform:translateY(-4px);
    box-shadow:var(--shadow-raised);
}
.kpi-icon-wrap {
    position:absolute;
    right:10px; bottom:8px;
    font-size:2.4rem;
    opacity:.09;
    color:var(--kpi-color,var(--brand));
    line-height:1;
    pointer-events:none;
}
.kpi-label {
    font-size:.68rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.06em;
    color:var(--ink-muted);
}
.kpi-value {
    font-size:1.9rem; font-weight:800;
    letter-spacing:-1px; line-height:1.1;
    color:var(--ink);
    margin-top:.3rem;
}

/* Section icon pill */
.section-icon {
    display:inline-flex; align-items:center; justify-content:center;
    width:28px; height:28px;
    background:var(--brand-light);
    color:var(--brand);
    border-radius:8px;
    font-size:.95rem;
    flex-shrink:0;
}

/* Summary stats */
.summary-stat-row {
    display:flex; align-items:center; gap:.75rem;
    padding:.8rem 1.1rem;
    border-bottom:1px solid var(--border);
}
.summary-stat-row.last { border-bottom:none; }
.summary-dot {
    width:8px; height:8px; border-radius:50%; flex-shrink:0;
}
.summary-label { font-size:.85rem; color:var(--ink-muted); flex:1; }
.summary-value { font-size:1.1rem; font-weight:700; color:var(--ink); }

/* Heatmap shelf card */
.heatmap-shelf {
    background:var(--surface-card);
    border:1px solid var(--border);
    border-radius:12px;
    padding:.85rem .65rem .7rem;
    text-align:center;
    transition:transform .2s ease, border-color .2s ease;
    cursor:default;
}
.heatmap-shelf:hover { transform:scale(1.04); }
.heatmap-shelf-name  { font-size:.82rem; font-weight:700; color:var(--ink); }
.heatmap-shelf-zone  { font-size:.68rem; color:var(--ink-muted); margin-top:.1rem; }
.heatmap-shelf-pct   { margin-top:.5rem; }
.heatmap-shelf-usage { font-size:.68rem; color:var(--ink-muted); margin-top:.35rem; }

/* Handover alert */
.handover-alert {
    display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
    background:var(--brand-light);
    border:1px solid var(--brand-mid);
    border-left:4px solid var(--brand);
    border-radius:14px;
    padding:.9rem 1.1rem;
}
.handover-alert-icon {
    width:40px; height:40px; flex-shrink:0;
    background:var(--brand-mid);
    color:var(--brand);
    border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.1rem;
}
.handover-alert-body { flex:1; min-width:0; }
.handover-alert-title { font-size:.75rem; font-weight:700; color:var(--brand); text-transform:uppercase; letter-spacing:.05em; }
.handover-alert-msg   { font-size:.875rem; margin-top:.2rem; }
.handover-alert-time  { font-size:.72rem; color:var(--ink-muted); margin-top:.2rem; }

/* Activity Feed */
.activity-feed { margin:0; padding:0; }
.activity-item {
    display:flex; align-items:flex-start; gap:1rem;
    padding:.85rem 1.25rem;
    border-bottom:1px solid var(--border);
    transition:background .15s;
}
.activity-item:last-child { border-bottom:none; }
.activity-item:hover { background:var(--surface-soft); }
.activity-dot {
    width:34px; height:34px; flex-shrink:0;
    border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:.9rem;
}
.activity-body { flex:1; min-width:0; }
.activity-action { font-size:.875rem; font-weight:600; color:var(--ink); }
.activity-meta { display:flex; align-items:center; gap:.4rem; margin-top:.25rem; flex-wrap:wrap; }
.activity-user, .activity-time { font-size:.75rem; color:var(--ink-muted); display:flex; align-items:center; gap:.2rem; }
.activity-sep { color:var(--border); font-size:.8rem; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Live clock
(function tick() {
    var el = document.getElementById("liveClock");
    if (el) el.textContent = new Date().toLocaleTimeString([], {hour:"2-digit",minute:"2-digit",second:"2-digit"});
    setTimeout(tick, 1000);
})();

// Chart global defaults
Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
Chart.defaults.font.size   = 12;

// Helper: detect dark mode
function isDark() { return document.documentElement.getAttribute("data-bs-theme") === "dark"; }
function gridColor()   { return isDark() ? "rgba(255,255,255,.06)"  : "rgba(0,0,0,.05)"; }
function tickColor()   { return isDark() ? "#94a3b8" : "#64748b"; }

// Status donut
new Chart(document.getElementById("statusChart"), {
    type:"doughnut",
    data:{
        labels:["<?php echo __('Stored'); ?>","<?php echo __('Collected'); ?>"],
        datasets:[{
            data:[<?php echo (int)$stats["Stored"]; ?>,<?php echo (int)$stats["Collected"]; ?>],
            backgroundColor:["#1d6f6f","#854d0e"],
            borderWidth:0, hoverOffset:8
        }]
    },
    options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{legend:{position:"bottom",labels:{boxWidth:12,padding:14}}},
        cutout:"72%"
    }
});

// 7-day trend
new Chart(document.getElementById("trendChart"), {
    type:"line",
    data:{
        labels:<?php echo json_encode($chart_labels); ?>,
        datasets:[
            {
                label:"<?php echo __('Check-ins'); ?>",
                data:<?php echo json_encode($chart_checkins); ?>,
                borderColor:"#1d6f6f", backgroundColor:"rgba(29,111,111,.08)",
                fill:true, tension:.38, borderWidth:2.5, pointRadius:3, pointHoverRadius:5
            },
            {
                label:"<?php echo __('Check-outs'); ?>",
                data:<?php echo json_encode($chart_checkouts); ?>,
                borderColor:"#854d0e", backgroundColor:"rgba(133,77,14,.06)",
                fill:true, tension:.38, borderWidth:2.5, pointRadius:3, pointHoverRadius:5
            }
        ]
    },
    options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{legend:{position:"top",labels:{boxWidth:12,padding:14}}},
        scales:{
            y:{beginAtZero:true, ticks:{precision:0,color:tickColor()}, grid:{color:gridColor()}, border:{display:false}},
            x:{ticks:{color:tickColor()}, grid:{display:false}, border:{display:false}}
        }
    }
});

// Analytics — lazy
var analyticsReady = false;
function initAnalytics() {
    if (analyticsReady) return;
    analyticsReady = true;

    new Chart(document.getElementById("monthlyTrendChart"),{
        type:"bar",
        data:{
            labels:["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
            datasets:[{label:"<?php echo __('Bags'); ?>",
                data:<?php echo json_encode(array_values($monthly_trend)); ?>,
                backgroundColor:"rgba(29,111,111,.82)", borderRadius:6, borderSkipped:false}]
        },
        options:{responsive:true,maintainAspectRatio:false,
            scales:{y:{beginAtZero:true,ticks:{precision:0,color:tickColor()},grid:{color:gridColor()},border:{display:false}},
                    x:{ticks:{color:tickColor()},grid:{display:false},border:{display:false}}}}
    });

    new Chart(document.getElementById("luggageTypeChart"),{
        type:"doughnut",
        data:{
            labels:<?php echo json_encode($luggage_type_labels); ?>,
            datasets:[{data:<?php echo json_encode($luggage_type_values); ?>,
                backgroundColor:["#1d6f6f","#854d0e","#b91c1c","#15803d","#6b7280"],borderWidth:0,hoverOffset:6}]
        },
        options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{position:"right",labels:{boxWidth:12}}},cutout:"60%"}
    });

    new Chart(document.getElementById("occupancyChart"),{
        type:"bar",
        data:{
            labels:<?php echo json_encode($shelf_occupancy_labels); ?>,
            datasets:[{label:"<?php echo __('Occupancy %'); ?>",
                data:<?php echo json_encode($shelf_occupancy_values); ?>,
                backgroundColor:"#d97706",borderRadius:4,borderSkipped:false}]
        },
        options:{indexAxis:"y",responsive:true,maintainAspectRatio:false,
            scales:{x:{beginAtZero:true,max:100,ticks:{color:tickColor()},grid:{color:gridColor()},border:{display:false}},
                    y:{ticks:{color:tickColor()},grid:{display:false},border:{display:false}}}}
    });

    new Chart(document.getElementById("activeGuestsChart"),{
        type:"bar",
        data:{
            labels:<?php echo json_encode($active_guest_names); ?>,
            datasets:[{label:"<?php echo __('Total Bags'); ?>",
                data:<?php echo json_encode($active_guest_counts); ?>,
                backgroundColor:"#3b82f6",borderRadius:4,borderSkipped:false}]
        },
        options:{indexAxis:"y",responsive:true,maintainAspectRatio:false,
            scales:{x:{beginAtZero:true,ticks:{precision:0,color:tickColor()},grid:{color:gridColor()},border:{display:false}},
                    y:{ticks:{color:tickColor()},grid:{display:false},border:{display:false}}}}
    });

    new Chart(document.getElementById("monthlyRevChart"),{
        type:"line",
        data:{
            labels:["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
            datasets:[{label:"<?php echo __('Revenue ($)'); ?>",
                data:<?php echo json_encode(array_values($monthly_rev)); ?>,
                borderColor:"#10b981",backgroundColor:"rgba(16,185,129,.07)",
                fill:true,tension:.38,borderWidth:2.5,pointRadius:3}]
        },
        options:{responsive:true,maintainAspectRatio:false,
            scales:{y:{beginAtZero:true,ticks:{color:tickColor()},grid:{color:gridColor()},border:{display:false}},
                    x:{ticks:{color:tickColor()},grid:{display:false},border:{display:false}}}}
    });

    new Chart(document.getElementById("lfStatusChart"),{
        type:"doughnut",
        data:{
            labels:["<?php echo __('Found'); ?>","<?php echo __('Claimed'); ?>","<?php echo __('Disposed'); ?>"],
            datasets:[{
                data:[<?php echo $lf_status_counts["Found"]; ?>,<?php echo $lf_status_counts["Claimed"]; ?>,<?php echo $lf_status_counts["Disposed"]; ?>],
                backgroundColor:["#eab308","#10b981","#ef4444"],
                borderWidth:0,hoverOffset:6
            }]
        },
        options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{position:"bottom",labels:{boxWidth:12}}},cutout:"60%"}
    });

    new Chart(document.getElementById("lfTrendsChart"),{
        type:"line",
        data:{
            labels:<?php echo json_encode($lf_trend_labels); ?>,
            datasets:[
                {
                    label:"<?php echo __('Found'); ?>",
                    data:<?php echo json_encode($lf_trend_found); ?>,
                    borderColor:"#eab308",backgroundColor:"rgba(234,179,8,.05)",
                    fill:true,tension:.38,borderWidth:2.5,pointRadius:3
                },
                {
                    label:"<?php echo __('Claimed'); ?>",
                    data:<?php echo json_encode($lf_trend_claimed); ?>,
                    borderColor:"#10b981",backgroundColor:"rgba(16,185,129,.05)",
                    fill:true,tension:.38,borderWidth:2.5,pointRadius:3
                }
            ]
        },
        options:{responsive:true,maintainAspectRatio:false,
            scales:{y:{beginAtZero:true,ticks:{precision:0,color:tickColor()},grid:{color:gridColor()},border:{display:false}},
                    x:{ticks:{color:tickColor()},grid:{display:false},border:{display:false}}}}
    });

    new Chart(document.getElementById("activeUsersChart"),{
        type:"bar",
        data:{
            labels:<?php echo json_encode($active_user_names); ?>,
            datasets:[{label:"<?php echo __('Actions'); ?>",
                data:<?php echo json_encode($active_user_counts); ?>,
                backgroundColor:"#8b5cf6",borderRadius:4,borderSkipped:false}]
        },
        options:{indexAxis:"y",responsive:true,maintainAspectRatio:false,
            scales:{x:{beginAtZero:true,ticks:{precision:0,color:tickColor()},grid:{color:gridColor()},border:{display:false}},
                    y:{ticks:{color:tickColor()},grid:{display:false},border:{display:false}}}}
    });
}

document.addEventListener("DOMContentLoaded", function () {
    var analyticsTabBtn = document.getElementById("analytics-tab");
    if (analyticsTabBtn) analyticsTabBtn.addEventListener("shown.bs.tab", initAnalytics);
});
</script>
<?php render_footer(); ?>

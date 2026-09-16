<?php
require_once __DIR__ . "/layout.php";
require_permission("shift");

$date = $_GET["date"] ?? date("Y-m-d");
$user_id = (int)($_GET["user_id"] ?? 0);
$users = db_fetch_all("SELECT user_id, username, full_name FROM users ORDER BY username");

$where_user_created = $user_id > 0 ? "AND l.created_by = ?" : "";
$where_user_checked = $user_id > 0 ? "AND l.checked_out_by = ?" : "";
$types = $user_id > 0 ? "si" : "s";
$params = $user_id > 0 ? [$date, $user_id] : [$date];

$checkins = db_fetch_all(
    "SELECT l.*, g.guest_name, g.room_no, u.username AS staff
     FROM luggage l
     JOIN guests g ON g.guest_id = l.guest_id
     LEFT JOIN users u ON u.user_id = l.created_by
     WHERE DATE(l.checkin_date) = ? AND l.deleted_at IS NULL $where_user_created
     ORDER BY l.checkin_date DESC",
    $types,
    $params
);

$params_checkout = $user_id > 0 ? [$date, $user_id] : [$date];
$checkouts = db_fetch_all(
    "SELECT l.*, g.guest_name, g.room_no, u.username AS staff
     FROM luggage l
     JOIN guests g ON g.guest_id = l.guest_id
     LEFT JOIN users u ON u.user_id = l.checked_out_by
     WHERE DATE(l.checkout_date) = ? AND l.deleted_at IS NULL $where_user_checked
     ORDER BY l.checkout_date DESC",
    $types,
    $params_checkout
);

$paid = 0;
foreach ($checkouts as $row) {
    if ($row["payment_status"] === "Paid") {
        $paid += (float)$row["fee_amount"];
    }
}

render_header("Shift Report", "shift");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Daily Shift Report</h1>
        <p class="text-secondary mb-0">Check-in, checkout, and paid totals by day or staff member.</p>
    </div>
    <button class="btn btn-outline-primary no-print" onclick="window.print()" type="button">Print Shift</button>
</div>

<div class="card mb-3 no-print">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-md-4">
                <label class="form-label" for="date">Date</label>
                <input class="form-control" type="date" id="date" name="date" value="<?php echo h($date); ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label" for="user_id">Staff</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="0">All Staff</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo (int)$user["user_id"]; ?>" <?php echo $user_id === (int)$user["user_id"] ? "selected" : ""; ?>>
                            <?php echo h($user["full_name"] ?: $user["username"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-grid align-items-end">
                <button class="btn btn-primary" type="submit">Load Shift</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card metric-card"><div class="card-body"><div class="text-secondary small">Checked In</div><div class="metric-value mt-2"><?php echo count($checkins); ?></div></div></div></div>
    <div class="col-md-4"><div class="card metric-card"><div class="card-body"><div class="text-secondary small">Checked Out</div><div class="metric-value mt-2"><?php echo count($checkouts); ?></div></div></div></div>
    <div class="col-md-4"><div class="card metric-card"><div class="card-body"><div class="text-secondary small">Paid Collected</div><div class="metric-value mt-2">$<?php echo h(money($paid)); ?></div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="section-title">Checked In</h2></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Time</th><th>Tag</th><th>Guest</th><th>Storage</th><th>Staff</th></tr></thead>
                    <tbody>
                    <?php foreach ($checkins as $row): ?>
                        <tr>
                            <td><?php echo h(substr($row["checkin_date"], 11, 5)); ?></td>
                            <td><?php echo h($row["tag_code"]); ?></td>
                            <td><?php echo h($row["guest_name"]); ?></td>
                            <td><?php echo h($row["storage_location"]); ?></td>
                            <td><?php echo h($row["staff"]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$checkins): ?><tr><td colspan="5"><div class="empty-state">No check-ins.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h2 class="section-title">Checked Out</h2></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Time</th><th>Tag</th><th>Guest</th><th>Payment</th><th>Staff</th></tr></thead>
                    <tbody>
                    <?php foreach ($checkouts as $row): ?>
                        <tr>
                            <td><?php echo h(substr($row["checkout_date"], 11, 5)); ?></td>
                            <td><?php echo h($row["tag_code"]); ?></td>
                            <td><?php echo h($row["guest_name"]); ?></td>
                            <td><?php echo h($row["payment_status"]); ?> $<?php echo h(money($row["fee_amount"])); ?></td>
                            <td><?php echo h($row["staff"]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$checkouts): ?><tr><td colspan="5"><div class="empty-state">No checkouts.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>

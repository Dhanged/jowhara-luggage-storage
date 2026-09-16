<?php
require_once __DIR__ . "/layout.php";
require_login();

$user = current_user();

// Calculate financial metrics since last handover
$last_handover = db_fetch_one("SELECT created_at FROM shift_handovers ORDER BY handover_id DESC LIMIT 1");
$since_time = $last_handover ? $last_handover['created_at'] : date("Y-m-d 00:00:00");

$payments = db_fetch_all(
    "SELECT payment_method, SUM(fee_amount) as total
     FROM luggage
     WHERE payment_status = 'Paid' AND deleted_at IS NULL AND updated_at >= ?
     GROUP BY payment_method",
    "s",
    [$since_time]
);

$cash_collected = 0.0;
$card_collected = 0.0;
foreach ($payments as $p) {
    if (strtolower($p['payment_method']) === 'cash') {
        $cash_collected += (float)$p['total'];
    } else {
        $card_collected += (float)$p['total'];
    }
}
$total_collected = $cash_collected + $card_collected;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    try {
        $incoming_user_id = (int)($_POST["incoming_user_id"] ?? 0);
        $opening_cash = max(0, (float)($_POST["opening_cash"] ?? 0));
        $actual_cash = max(0, (float)($_POST["actual_cash"] ?? 0));
        $notes = trim($_POST["handover_notes"] ?? "");

        if ($incoming_user_id <= 0) {
            throw new RuntimeException(__("Please select an incoming staff member."));
        }

        $expected_cash = $cash_collected;
        $variance = $actual_cash - ($opening_cash + $expected_cash);

        db_execute(
            "INSERT INTO shift_handovers
             (outgoing_user_id, incoming_user_id, opening_cash, expected_cash, actual_cash, cash_variance, card_total, total_collected, handover_notes, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Acknowledged', NOW())",
            "iidddddds",
            [$user['user_id'], $incoming_user_id, $opening_cash, $expected_cash, $actual_cash, $variance, $card_collected, $total_collected, $notes]
        );

        $handover_id = db_insert_id();
        log_action("Shift Handover Completed", "shift_handovers", $handover_id, "Shift closed by {$user['username']}. Cash Variance: $" . money($variance));

        set_flash("success", __("Shift handover and cash balancing completed successfully."));
        redirect("print_shift_zreport.php?id=" . $handover_id);
    } catch (Throwable $e) {
        set_flash("danger", $e->getMessage());
        redirect("shift_handover.php");
    }
}

// Fetch list of staff users
$users = db_fetch_all("SELECT user_id, username, role FROM users WHERE status = 'Active' ORDER BY username ASC");
$history = db_fetch_all(
    "SELECT h.*, u1.username as outgoing_name, u2.username as incoming_name
     FROM shift_handovers h
     LEFT JOIN users u1 ON u1.user_id = h.outgoing_user_id
     LEFT JOIN users u2 ON u2.user_id = h.incoming_user_id
     ORDER BY h.handover_id DESC LIMIT 15"
);

render_header("Shift Handover & Cash Register", "shift");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Shift Handover & Cash Balancing"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Reconcile register cash float, calculate variance, and close shift with Z-Report."); ?></p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-transparent border-bottom">
                <h2 class="h5 fw-bold mb-0"><i class="bi bi-cash-stack text-success"></i> <?php echo __("Close Shift Register"); ?></h2>
            </div>
            <div class="card-body">
                <form method="POST" id="shiftForm">
                    <?php echo csrf_field(); ?>
                    
                    <div class="mb-3">
                        <label class="form-label" for="incoming_user_id"><?php echo __("Incoming Staff Member"); ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="incoming_user_id" name="incoming_user_id" required>
                            <option value=""><?php echo __("-- Select Staff --"); ?></option>
                            <?php foreach ($users as $u): ?>
                                <?php if ($u['user_id'] != $user['user_id']): ?>
                                    <option value="<?php echo (int)$u['user_id']; ?>"><?php echo h($u['username']); ?> (<?php echo h($u['role']); ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="p-3 mb-3 rounded bg-light border">
                        <div class="d-flex justify-content-between small text-secondary mb-1">
                            <span>Shift System Cash Sales:</span>
                            <strong class="text-dark">$<?php echo h(money($cash_collected)); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small text-secondary mb-1">
                            <span>Shift Card/Electronic Sales:</span>
                            <strong class="text-dark">$<?php echo h(money($card_collected)); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-2 mt-2 fw-bold text-dark">
                            <span>Total Shift Revenue:</span>
                            <span class="text-primary">$<?php echo h(money($total_collected)); ?></span>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="opening_cash"><?php echo __("Opening Float ($)"); ?></label>
                            <input class="form-control" type="number" step="0.01" id="opening_cash" name="opening_cash" value="0.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="actual_cash"><?php echo __("Counted Cash ($)"); ?> <span class="text-danger">*</span></label>
                            <input class="form-control" type="number" step="0.01" id="actual_cash" name="actual_cash" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="handover_notes"><?php echo __("Handover Notes / Remarks"); ?></label>
                        <textarea class="form-control" id="handover_notes" name="handover_notes" rows="2" placeholder="<?php echo __("Key inventory updates, pending pickups, or cash notes..."); ?>"></textarea>
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
                        <i class="bi bi-check-circle-fill"></i> <?php echo __("Submit & Print Z-Report"); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-transparent border-bottom">
                <h2 class="h5 fw-bold mb-0"><i class="bi bi-journal-text"></i> <?php echo __("Shift Reconciliation History"); ?></h2>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Outgoing / Incoming</th>
                                <th>Collected</th>
                                <th>Variance</th>
                                <th class="text-end">Z-Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td class="small fw-semibold"><?php echo date("d/m H:i", strtotime($h['created_at'])); ?></td>
                                    <td class="small">
                                        <span class="text-dark fw-bold"><?php echo h($h['outgoing_name']); ?></span>
                                        <i class="bi bi-arrow-right text-muted mx-1"></i>
                                        <span class="text-muted"><?php echo h($h['incoming_name']); ?></span>
                                    </td>
                                    <td class="small fw-bold">$<?php echo h(money($h['total_collected'])); ?></td>
                                    <td>
                                        <?php if ((float)$h['cash_variance'] == 0): ?>
                                            <span class="badge text-bg-success">Balanced</span>
                                        <?php elseif ((float)$h['cash_variance'] > 0): ?>
                                            <span class="badge text-bg-info">+$<?php echo h(money($h['cash_variance'])); ?></span>
                                        <?php else: ?>
                                            <span class="badge text-bg-danger">-$<?php echo h(money(abs($h['cash_variance']))); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-outline-secondary btn-sm" href="print_shift_zreport.php?id=<?php echo (int)$h['handover_id']; ?>" target="_blank">
                                            <i class="bi bi-printer"></i> Z-Report
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$history): ?>
                                <tr><td colspan="5" class="text-center p-4 text-muted">No shift handovers logged yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>

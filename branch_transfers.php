<?php
require_once __DIR__ . "/layout.php";
require_permission("luggage");

$user = current_user();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";

    if ($action === "initiate") {
        try {
            $luggage_id = (int)($_POST["luggage_id"] ?? 0);
            $to_branch_id = (int)($_POST["to_branch_id"] ?? 0);
            $courier_name = trim($_POST["courier_name"] ?? "");
            $courier_phone = trim($_POST["courier_phone"] ?? "");
            $reason = trim($_POST["reason"] ?? "");

            $luggage = db_fetch_one("SELECT * FROM luggage WHERE luggage_id = ? AND deleted_at IS NULL", "i", [$luggage_id]);
            if (!$luggage) {
                throw new RuntimeException(__("Selected luggage record not found."));
            }
            if ($to_branch_id <= 0 || $to_branch_id == $luggage['branch_id']) {
                throw new RuntimeException(__("Please select a valid destination branch different from the current location."));
            }

            db_execute(
                "INSERT INTO luggage_transfers
                 (luggage_id, from_branch_id, to_branch_id, from_zone, from_location, courier_name, courier_phone, status, reason, moved_by, moved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'In Transit', ?, ?, NOW())",
                "iiissssii",
                [$luggage_id, $luggage['branch_id'], $to_branch_id, $luggage['storage_zone'], $luggage['storage_location'], $courier_name, $courier_phone, $reason, $user['user_id']]
            );

            $transfer_id = db_insert_id();
            log_action("Initiated Branch Transfer", "luggage_transfers", $transfer_id, "Luggage {$luggage['tag_code']} dispatched to Branch #{$to_branch_id} via {$courier_name}");

            set_flash("success", __("Inter-branch luggage transfer initiated successfully."));
            redirect("branch_transfers.php");
        } catch (Throwable $e) {
            set_flash("danger", $e->getMessage());
            redirect("branch_transfers.php");
        }
    } elseif ($action === "receive") {
        try {
            $transfer_id = (int)($_POST["transfer_id"] ?? 0);
            $transfer = db_fetch_one("SELECT * FROM luggage_transfers WHERE transfer_id = ? AND status = 'In Transit'", "i", [$transfer_id]);
            if (!$transfer) {
                throw new RuntimeException(__("Active transfer record not found."));
            }

            global $conn;
            $conn->begin_transaction();

            // Mark transfer received
            db_execute(
                "UPDATE luggage_transfers
                 SET status = 'Received', received_by = ?, received_at = NOW()
                 WHERE transfer_id = ?",
                "ii",
                [$user['user_id'], $transfer_id]
            );

            // Update luggage record's branch ID
            db_execute(
                "UPDATE luggage SET branch_id = ? WHERE luggage_id = ?",
                "ii",
                [$transfer['to_branch_id'], $transfer['luggage_id']]
            );

            log_action("Received Branch Transfer", "luggage_transfers", $transfer_id, "Luggage #{$transfer['luggage_id']} received at destination branch.");

            $conn->commit();

            set_flash("success", __("Luggage transfer confirmed and marked as received at destination branch."));
            redirect("branch_transfers.php");
        } catch (Throwable $e) {
            if (isset($conn) && $conn->in_transaction) {
                $conn->rollback();
            }
            set_flash("danger", $e->getMessage());
            redirect("branch_transfers.php");
        }
    }
}

// Fetch active luggage items for dropdown
$available_luggage = db_fetch_all(
    "SELECT l.luggage_id, l.tag_code, g.guest_name, b.branch_name
     FROM luggage l
     JOIN guests g ON g.guest_id = l.guest_id
     LEFT JOIN branches b ON b.branch_id = l.branch_id
     WHERE l.status = 'Stored' AND l.deleted_at IS NULL
     ORDER BY l.luggage_id DESC LIMIT 100"
);

$branches = db_fetch_all("SELECT * FROM branches ORDER BY branch_name ASC");

// Fetch active transfers
$transfers = db_fetch_all(
    "SELECT t.*, l.tag_code, g.guest_name,
            b1.branch_name as from_branch_name,
            b2.branch_name as to_branch_name,
            u1.username as dispatcher_name,
            u2.username as receiver_name
     FROM luggage_transfers t
     JOIN luggage l ON l.luggage_id = t.luggage_id
     JOIN guests g ON g.guest_id = l.guest_id
     LEFT JOIN branches b1 ON b1.branch_id = t.from_branch_id
     LEFT JOIN branches b2 ON b2.branch_id = t.to_branch_id
     LEFT JOIN users u1 ON u1.user_id = t.moved_by
     LEFT JOIN users u2 ON u2.user_id = t.received_by
     ORDER BY t.transfer_id DESC LIMIT 30"
);

render_header("Inter-Branch Transfers", "transfers");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Multi-Branch Luggage Transfers"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Track and manage luggage movement between hotel branches & airport pickup points."); ?></p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-bottom">
                <h2 class="h5 fw-bold mb-0"><i class="bi bi-truck text-primary"></i> <?php echo __("Initiate Transfer"); ?></h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="initiate">

                    <div class="mb-3">
                        <label class="form-label" for="luggage_id"><?php echo __("Select Luggage Tag"); ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="luggage_id" name="luggage_id" required>
                            <option value=""><?php echo __("-- Select Stored Luggage --"); ?></option>
                            <?php foreach ($available_luggage as $l): ?>
                                <option value="<?php echo (int)$l['luggage_id']; ?>">
                                    <?php echo h($l['tag_code']); ?> - <?php echo h($l['guest_name']); ?> (<?php echo h($l['branch_name']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="to_branch_id"><?php echo __("Destination Branch"); ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="to_branch_id" name="to_branch_id" required>
                            <option value=""><?php echo __("-- Select Destination Branch --"); ?></option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?php echo (int)$b['branch_id']; ?>"><?php echo h($b['branch_name']); ?> (<?php echo h($b['city'] ?? ''); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="courier_name"><?php echo __("Driver / Courier Name"); ?></label>
                        <input class="form-control" type="text" id="courier_name" name="courier_name" placeholder="Driver name or transport ref">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="courier_phone"><?php echo __("Driver Phone Number"); ?></label>
                        <input class="form-control" type="text" id="courier_phone" name="courier_phone" placeholder="+252 ...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="reason"><?php echo __("Transfer Reason / Notes"); ?></label>
                        <textarea class="form-control" id="reason" name="reason" rows="2" placeholder="e.g. Guest moving to Beach Branch..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-send-fill"></i> <?php echo __("Dispatch Transfer"); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-bottom">
                <h2 class="h5 fw-bold mb-0"><i class="bi bi-clock-history"></i> <?php echo __("Transfer Log & In-Transit Status"); ?></h2>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tag & Guest</th>
                                <th>From → To Branch</th>
                                <th>Courier Details</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transfers as $t): ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark"><?php echo h($t['tag_code']); ?></strong>
                                        <div class="small text-secondary"><?php echo h($t['guest_name']); ?></div>
                                    </td>
                                    <td class="small">
                                        <span class="badge text-bg-secondary"><?php echo h($t['from_branch_name']); ?></span>
                                        <i class="bi bi-arrow-right mx-1"></i>
                                        <span class="badge text-bg-primary"><?php echo h($t['to_branch_name']); ?></span>
                                    </td>
                                    <td class="small">
                                        <div><?php echo h($t['courier_name'] ?: 'Internal Staff'); ?></div>
                                        <div class="text-muted"><?php echo h($t['courier_phone'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($t['status'] === 'In Transit'): ?>
                                            <span class="badge text-bg-warning text-dark"><i class="bi bi-truck"></i> In Transit</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-success"><i class="bi bi-check-circle-fill"></i> Received</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($t['status'] === 'In Transit'): ?>
                                            <form method="POST" style="display:inline;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="receive">
                                                <input type="hidden" name="transfer_id" value="<?php echo (int)$t['transfer_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success fw-bold">
                                                    <i class="bi bi-box-arrow-in-down"></i> Receive
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">Confirmed by <?php echo h($t['receiver_name'] ?: 'Staff'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$transfers): ?>
                                <tr><td colspan="5" class="text-center p-4 text-muted">No inter-branch transfers logged.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>

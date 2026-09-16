<?php
require_once __DIR__ . "/layout.php";
require_login();

// Restrict to Managers and Admins
if (!user_can("manager_dashboard")) {
    set_flash("danger", __("Access denied. Managers only."));
    redirect("dashboard.php");
}

$current_user_id = (int)current_user()["user_id"];

// Handle Actions (Approve / Reject)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";
    $request_id = (int)($_POST["request_id"] ?? 0);
    $notes = trim($_POST["notes"] ?? "");

    $request = db_fetch_one(
        "SELECT r.*, l.tag_code, l.luggage_id 
         FROM approval_requests r
         JOIN luggage l ON l.luggage_id = r.entity_id
         WHERE r.id = ? AND r.status = 'Pending'",
        "i",
        [$request_id]
    );

    if ($request) {
        if ($action === "approve") {
            db_execute(
                "UPDATE approval_requests SET status = 'Approved', approved_by = ?, notes = ? WHERE id = ?",
                "isi",
                [$current_user_id, $notes, $request_id]
            );
            log_action("Approved checkout request", "luggage", $request["luggage_id"], "Request ID $request_id approved: $notes");
            
            // Notify the requester
            push_notification(
                (int)$request["requested_by"],
                __("Checkout Approved"),
                sprintf(__("Manager approved checkout for luggage tag %s"), $request["tag_code"]),
                "checkout.php?id=" . $request["luggage_id"],
                "success"
            );
            
            set_flash("success", __("Request approved successfully."));
        } elseif ($action === "reject") {
            db_execute(
                "UPDATE approval_requests SET status = 'Rejected', approved_by = ?, notes = ? WHERE id = ?",
                "isi",
                [$current_user_id, $notes, $request_id]
            );
            log_action("Rejected checkout request", "luggage", $request["luggage_id"], "Request ID $request_id rejected: $notes");
            
            // Notify the requester
            push_notification(
                (int)$request["requested_by"],
                __("Checkout Rejected"),
                sprintf(__("Manager rejected checkout for luggage tag %s. Reason: %s"), $request["tag_code"], h($notes)),
                "checkout.php?id=" . $request["luggage_id"],
                "danger"
            );
            
            set_flash("success", __("Request rejected successfully."));
        }
    } else {
        set_flash("warning", __("Request not found or already processed."));
    }
    redirect("approvals.php");
}

// Fetch stats
$stats = db_fetch_one(
    "SELECT 
        SUM(status = 'Pending') AS pending_count,
        SUM(status = 'Approved') AS approved_count,
        SUM(status = 'Rejected') AS rejected_count
     FROM approval_requests"
);

// Fetch Pending
$pending_requests = db_fetch_all(
    "SELECT r.*, l.tag_code, l.declared_value, l.declared_items_desc, g.guest_name, g.room_no, u.full_name AS requester_name, u.username AS requester_username
     FROM approval_requests r
     JOIN luggage l ON l.luggage_id = r.entity_id
     JOIN guests g ON g.guest_id = l.guest_id
     JOIN users u ON u.user_id = r.requested_by
     WHERE r.status = 'Pending'
     ORDER BY r.created_at ASC"
);

// Fetch History
$history_requests = db_fetch_all(
    "SELECT r.*, l.tag_code, g.guest_name, u.full_name AS requester_name, u.username AS requester_username,
            approver.full_name AS approver_name, approver.username AS approver_username
     FROM approval_requests r
     JOIN luggage l ON l.luggage_id = r.entity_id
     JOIN guests g ON g.guest_id = l.guest_id
     JOIN users u ON u.user_id = r.requested_by
     LEFT JOIN users approver ON approver.user_id = r.approved_by
     WHERE r.status <> 'Pending'
     ORDER BY r.created_at DESC LIMIT 50"
);

render_header("Approvals", "manager");
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Approval Requests Desk"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Review, authorize, or reject checkout requests for High-Value luggage items."); ?></p>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <?php foreach ([
        [__("Pending Approvals"), (int)($stats["pending_count"] ?? 0), "bi-hourglass-split", "warning"],
        [__("Approved Requests"), (int)($stats["approved_count"] ?? 0), "bi-check-circle-fill", "success"],
        [__("Rejected Requests"), (int)($stats["rejected_count"] ?? 0), "bi-x-circle-fill", "danger"],
    ] as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-md-4">
            <div class="card h-100 shadow-sm border-0 border-start border-4 border-<?php echo $color; ?>">
                <div class="card-body py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium"><?php echo $label; ?></div>
                        <h3 class="fw-extrabold text-dark mt-1 mb-0"><?php echo $value; ?></h3>
                    </div>
                    <i class="bi <?php echo $icon; ?> text-<?php echo $color; ?> fs-3"></i>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Tabs -->
<ul class="nav nav-pills mb-4 gap-2" id="approvalsTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active d-flex align-items-center gap-2" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-pane" type="button" role="tab">
            <i class="bi bi-shield-exclamation"></i> <?php echo __("Pending Actions"); ?>
            <?php if (count($pending_requests) > 0): ?>
                <span class="badge text-bg-warning rounded-pill"><?php echo count($pending_requests); ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link d-flex align-items-center gap-2" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab">
            <i class="bi bi-clock-history"></i> <?php echo __("Approval History"); ?>
        </button>
    </li>
</ul>

<div class="tab-content" id="approvalsTabsContent">
    
    <!-- Tab 1: Pending Actions -->
    <div class="tab-pane fade show active" id="pending-pane" role="tabpanel">
        <?php if ($pending_requests): ?>
            <div class="row g-3">
                <?php foreach ($pending_requests as $req): ?>
                    <div class="col-md-6 col-xxl-4">
                        <div class="card shadow-sm h-100 border border-warning border-opacity-25" style="background: rgba(245, 158, 11, 0.01);">
                            <div class="card-header bg-transparent d-flex justify-content-between align-items-center border-0 pt-3 pb-0 px-3">
                                <span class="badge text-bg-warning">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo __("High-Value Checkout"); ?>
                                </span>
                                <span class="text-secondary small font-monospace"><?php echo date("Y-m-d H:i", strtotime($req["created_at"])); ?></span>
                            </div>
                            <div class="card-body p-3">
                                <div class="text-center mb-3">
                                    <span class="fs-4 fw-bold text-primary font-monospace bg-light border px-3 py-1 rounded">
                                        <?php echo h($req["tag_code"]); ?>
                                    </span>
                                </div>
                                <dl class="row mb-0 small">
                                    <dt class="col-4"><?php echo __("Guest"); ?></dt>
                                    <dd class="col-8"><?php echo h($req["guest_name"]); ?><?php echo $req["room_no"] ? " (Room " . h($req["room_no"]) . ")" : ""; ?></dd>
                                    <dt class="col-4"><?php echo __("Declared Value"); ?></dt>
                                    <dd class="col-8 text-warning-emphasis fw-bold">$<?php echo h(money($req["declared_value"])); ?></dd>
                                    <dt class="col-4"><?php echo __("Description"); ?></dt>
                                    <dd class="col-8 text-secondary"><?php echo h($req["declared_items_desc"] ?: __("No description")); ?></dd>
                                    <dt class="col-4"><?php echo __("Requested By"); ?></dt>
                                    <dd class="col-8 text-dark fw-semibold"><?php echo h($req["requester_name"] ?: $req["requester_username"]); ?></dd>
                                </dl>
                            </div>
                            <div class="card-footer bg-transparent border-0 d-flex gap-2 p-3 pt-0">
                                <button type="button" class="btn btn-success btn-sm flex-fill d-flex align-items-center justify-content-center gap-1"
                                        data-bs-toggle="modal" data-bs-target="#processRequestModal"
                                        data-id="<?php echo $req["id"]; ?>" data-tag="<?php echo h($req["tag_code"]); ?>" data-action="approve">
                                    <i class="bi bi-check-circle"></i> <?php echo __("Approve"); ?>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm flex-fill d-flex align-items-center justify-content-center gap-1"
                                        data-bs-toggle="modal" data-bs-target="#processRequestModal"
                                        data-id="<?php echo $req["id"]; ?>" data-tag="<?php echo h($req["tag_code"]); ?>" data-action="reject">
                                    <i class="bi bi-x-circle"></i> <?php echo __("Reject"); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card py-5 text-center text-muted">
                <div class="card-body">
                    <i class="bi bi-shield-check fs-1 mb-3 text-secondary"></i>
                    <h5><?php echo __("No pending approval requests"); ?></h5>
                    <p class="small text-secondary mb-0"><?php echo __("All checkouts are authorized."); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab 2: History -->
    <div class="tab-pane fade" id="history-pane" role="tabpanel">
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?php echo __("Date"); ?></th>
                            <th><?php echo __("Tag"); ?></th>
                            <th><?php echo __("Guest"); ?></th>
                            <th><?php echo __("Requested By"); ?></th>
                            <th><?php echo __("Status"); ?></th>
                            <th><?php echo __("Processed By"); ?></th>
                            <th><?php echo __("Notes / Reason"); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history_requests as $req): 
                            $status_class = $req["status"] === "Approved" ? "success" : "danger";
                        ?>
                            <tr>
                                <td class="small text-secondary font-monospace"><?php echo date("Y-m-d H:i", strtotime($req["created_at"])); ?></td>
                                <td class="fw-bold font-monospace text-primary"><?php echo h($req["tag_code"]); ?></td>
                                <td><?php echo h($req["guest_name"]); ?></td>
                                <td class="small fw-semibold"><?php echo h($req["requester_name"] ?: $req["requester_username"]); ?></td>
                                <td><span class="badge text-bg-<?php echo $status_class; ?>"><?php echo h(__($req["status"])); ?></span></td>
                                <td class="small fw-semibold"><?php echo h($req["approver_name"] ?: $req["approver_username"]); ?></td>
                                <td class="small text-secondary"><?php echo h($req["notes"] ?: "-"); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$history_requests): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state text-center py-4 text-muted">
                                        <i class="bi bi-clock-history fs-2 d-block mb-2"></i>
                                        <?php echo __("No processed request history found."); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Modal for Approving / Rejecting -->
<div class="modal fade" id="processRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="processRequestForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="request_id" id="modalRequestId">
                <input type="hidden" name="action" id="modalAction">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><?php echo __("Process Request"); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="modalInstructions" class="alert mb-3"></div>
                    <div class="mb-3">
                        <label for="modalNotes" class="form-label" id="modalNotesLabel"><?php echo __("Decision Notes / Notes"); ?></label>
                        <textarea class="form-control" name="notes" id="modalNotes" rows="3" placeholder="<?php echo __("Provide additional details or reasons..."); ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __("Cancel"); ?></button>
                    <button type="submit" class="btn" id="modalSubmitBtn"><?php echo __("Confirm"); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const processRequestModal = document.getElementById("processRequestModal");
    if (processRequestModal) {
        processRequestModal.addEventListener("show.bs.modal", function (event) {
            const button = event.relatedTarget;
            const requestId = button.getAttribute("data-id");
            const tagCode = button.getAttribute("data-tag");
            const action = button.getAttribute("data-action");

            const modalSubmitBtn = document.getElementById("modalSubmitBtn");
            const modalTitle = document.getElementById("modalTitle");
            const modalInstructions = document.getElementById("modalInstructions");
            const modalActionInput = document.getElementById("modalAction");
            const modalRequestIdInput = document.getElementById("modalRequestId");
            const modalNotes = document.getElementById("modalNotes");

            modalRequestIdInput.value = requestId;
            modalActionInput.value = action;
            modalNotes.value = ""; // clear notes

            if (action === "approve") {
                modalTitle.innerText = "<?php echo __("Approve Checkout"); ?>: " + tagCode;
                modalInstructions.className = "alert alert-success";
                modalInstructions.innerHTML = "<i class='bi bi-check-circle-fill me-2'></i><?php echo __("You are about to authorize the checkout release of this High-Value item. You can add notes below."); ?>";
                modalSubmitBtn.className = "btn btn-success";
                modalSubmitBtn.innerText = "<?php echo __("Authorize & Approve"); ?>";
                modalNotes.required = false;
            } else {
                modalTitle.innerText = "<?php echo __("Reject Checkout"); ?>: " + tagCode;
                modalInstructions.className = "alert alert-danger";
                modalInstructions.innerHTML = "<i class='bi bi-exclamation-octagon-fill me-2'></i><?php echo __("You are rejecting this checkout request. A rejection reason/notes is required."); ?>";
                modalSubmitBtn.className = "btn btn-danger";
                modalSubmitBtn.innerText = "<?php echo __("Confirm Rejection"); ?>";
                modalNotes.required = true;
            }
        });
    }
});
</script>
<?php
render_footer();
?>

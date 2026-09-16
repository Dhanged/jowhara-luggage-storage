<?php
require_once __DIR__ . "/layout.php";
require_login();

$luggage_id = (int)($_GET["id"] ?? 0);
$luggage = db_fetch_one(
    "SELECT l.*, g.guest_name, g.phone AS guest_phone, g.room_no, g.email AS guest_email,
            creator.full_name AS creator_name, creator.username AS creator_username,
            checkout_user.full_name AS checkout_name, checkout_user.username AS checkout_username
     FROM luggage l
     JOIN guests g ON g.guest_id = l.guest_id
     LEFT JOIN users creator ON creator.user_id = l.created_by
     LEFT JOIN users checkout_user ON checkout_user.user_id = l.checked_out_by
     WHERE l.luggage_id = ? AND l.deleted_at IS NULL",
    "i",
    [$luggage_id]
);

if (!$luggage) {
    set_flash("warning", __("Luggage not found."));
    redirect("luggage.php");
}

// Fetch transfers
$transfers = db_fetch_all(
    "SELECT t.*, u.username, u.full_name
     FROM luggage_transfers t
     JOIN users u ON u.user_id = t.moved_by
     WHERE t.luggage_id = ?
     ORDER BY t.moved_at DESC",
    "i",
    [$luggage_id]
);

// Fetch audit logs for this entity
$audits = db_fetch_all(
    "SELECT a.*, u.username, u.full_name
     FROM audit_logs a
     LEFT JOIN users u ON u.user_id = a.user_id
     WHERE a.entity_type = 'luggage' AND a.entity_id = ?
     ORDER BY a.created_at DESC",
    "i",
    [$luggage_id]
);

// Merge into a single timeline
$timeline = [];
foreach ($transfers as $t) {
    $timeline[] = [
        "timestamp" => $t["moved_at"],
        "user" => $t["full_name"] ?: $t["username"],
        "event" => __("Location Transfer"),
        "details" => sprintf(
            __("Moved storage location from %s to %s. Reason: %s"),
            "<strong>" . h(trim(($t["from_zone"] ?? "") . " " . ($t["from_location"] ?? ""))) . "</strong>",
            "<strong>" . h(trim(($t["to_zone"] ?? "") . " " . ($t["to_location"] ?? ""))) . "</strong>",
            "<em>" . h($t["reason"]) . "</em>"
        ),
        "icon" => "bi-arrow-left-right",
        "color" => "info"
    ];
}
foreach ($audits as $a) {
    $icon = "bi-info-circle";
    $color = "secondary";
    $action_lower = strtolower($a["action"]);
    if (str_contains($action_lower, "checkin") || str_contains($action_lower, "created")) {
        $icon = "bi-box-arrow-in-down";
        $color = "success";
    } elseif (str_contains($action_lower, "checkout") || str_contains($action_lower, "collected")) {
        $icon = "bi-box-arrow-up";
        $color = "danger";
    } elseif (str_contains($action_lower, "payment")) {
        $icon = "bi-cash-coin";
        $color = "warning";
    } elseif (str_contains($action_lower, "update") || str_contains($action_lower, "edit")) {
        $icon = "bi-pencil-square";
        $color = "primary";
    }
    
    $timeline[] = [
        "timestamp" => $a["created_at"],
        "user" => $a["full_name"] ?: ($a["username"] ?: __("System")),
        "event" => __($a["action"]),
        "details" => h($a["details"]),
        "icon" => $icon,
        "color" => $color
    ];
}

// Sort timeline descending chronologically
usort($timeline, function($a, $b) {
    return strcmp($b["timestamp"], $a["timestamp"]);
});

render_header("Chain of Custody", "luggage");
?>
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Chain of Custody Tracking"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Complete immutable historical audit log and custody transfer timeline."); ?></p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="window.print();">
            <i class="bi bi-printer"></i> <?php echo __("Print"); ?>
        </button>
        <a class="btn btn-outline-primary" href="edit_luggage.php?id=<?php echo $luggage_id; ?>">
            <i class="bi bi-pencil"></i> <?php echo __("Edit Record"); ?>
        </a>
        <a class="btn btn-outline-secondary" href="luggage.php">
            <?php echo __("Back"); ?>
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Luggage Meta Card -->
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-transparent border-bottom py-3">
                <h5 class="fw-bold mb-0 text-dark"><?php echo __("Item Specifications"); ?></h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <span class="fs-1 fw-bold font-monospace text-primary bg-light px-3 py-1 rounded border d-inline-block">
                        <?php echo h($luggage["tag_code"]); ?>
                    </span>
                    <div class="mt-2">
                        <span class="badge text-bg-<?php echo h(status_badge($luggage["status"])); ?> fs-6">
                            <?php echo h($luggage["status"]); ?>
                        </span>
                    </div>
                </div>

                <div class="mb-3 text-center">
                    <?php if ($luggage["photo"]): ?>
                        <img src="<?php echo h($luggage["photo"]); ?>" alt="Luggage thumbnail" class="zoomable img-thumbnail" style="max-height: 140px; object-fit: cover;">
                    <?php endif; ?>
                </div>

                <hr>

                <div class="lh-base">
                    <div class="mb-2"><strong><?php echo __("Guest Name"); ?>:</strong> <?php echo h($luggage["guest_name"]); ?></div>
                    <div class="mb-2"><strong><?php echo __("Room No"); ?>:</strong> <?php echo h($luggage["room_no"] ?: "-"); ?></div>
                    <div class="mb-2"><strong><?php echo __("Expected Pickup"); ?>:</strong> <?php echo h($luggage["expected_pickup_date"] ?: "-"); ?></div>
                    <div class="mb-2"><strong><?php echo __("Current Storage Location"); ?>:</strong> <span class="badge text-bg-secondary"><?php echo h(trim(($luggage["storage_zone"] ?? "") . " " . ($luggage["storage_location"] ?? ""))); ?></span></div>
                    <div class="mb-2"><strong><?php echo __("Weight Class"); ?>:</strong> <?php echo h(__($luggage["weight_class"] ?? "Light")); ?><?php echo $luggage["weight_kg"] !== null ? " (" . h($luggage["weight_kg"]) . " kg)" : ""; ?></div>
                    <div class="mb-2"><strong><?php echo __("Security Seal Tag Code"); ?>:</strong> <?php echo h($luggage["security_seal_code"] ?: "-"); ?></div>
                    <div class="mb-2"><strong><?php echo __("Fee Amount"); ?>:</strong> $<?php echo h(money($luggage["fee_amount"])); ?> (<?php echo h(__($luggage["payment_status"])); ?>)</div>
                    <div class="mb-2"><strong><?php echo __("Check-in Date"); ?>:</strong> <?php echo h(date("Y-m-d H:i:s", strtotime($luggage["checkin_date"]))); ?> <?php echo $luggage["creator_name"] ? "by " . h($luggage["creator_name"]) : ""; ?></div>
                    <?php if ($luggage["checkout_date"]): ?>
                        <div class="mb-2"><strong><?php echo __("Checkout Date"); ?>:</strong> <?php echo h(date("Y-m-d H:i:s", strtotime($luggage["checkout_date"]))); ?> <?php echo $luggage["checkout_name"] ? "by " . h($luggage["checkout_name"]) : ""; ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline Timeline -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-transparent border-bottom py-3">
                <h5 class="fw-bold mb-0 text-dark"><?php echo __("Custody & Transfer Timeline"); ?></h5>
            </div>
            <div class="card-body">
                <div class="timeline-container px-3 py-2">
                    <?php if ($timeline): ?>
                        <div class="custody-timeline">
                            <?php foreach ($timeline as $event): ?>
                                <div class="timeline-item">
                                    <div class="timeline-badge bg-<?php echo h($event["color"]); ?>">
                                        <i class="bi <?php echo h($event["icon"]); ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="fw-bold text-dark mb-0"><?php echo h($event["event"]); ?></h6>
                                            <span class="text-muted small fw-semibold font-monospace"><?php echo date("Y-m-d H:i:s", strtotime($event["timestamp"])); ?></span>
                                        </div>
                                        <div class="mb-2 text-secondary small"><?php echo $event["details"]; ?></div>
                                        <div class="d-flex align-items-center gap-1 text-muted" style="font-size: 0.78rem;">
                                            <i class="bi bi-person-circle"></i>
                                            <span><?php echo __("Responsible Officer"); ?>: <strong><?php echo h($event["user"]); ?></strong></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-clock-history fs-1 d-block mb-3"></i>
                            <?php echo __("No activities logged for this item."); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom Timeline Style */
.custody-timeline {
    position: relative;
    padding-left: 30px;
    margin-left: 10px;
    border-left: 2px solid var(--border);
}
.timeline-item {
    position: relative;
    padding-bottom: 2rem;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-badge {
    position: absolute;
    left: -42px;
    top: 2px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    box-shadow: 0 0 0 4px var(--surface-card);
}
.timeline-content {
    background: var(--surface-soft);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1rem;
    position: relative;
    transition: transform 0.2s ease;
}
.timeline-content:hover {
    transform: translateY(-2px);
}
/* Print View Styling */
@media print {
    .no-print { display: none !important; }
    body { background: #fff !important; color: #000 !important; font-size: 12px !important; }
    .card { border: none !important; box-shadow: none !important; }
    .col-lg-4, .col-lg-8 { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }
    .timeline-content { background: #fff !important; border: 1px solid #ddd !important; }
}
</style>

<?php
render_footer();
?>

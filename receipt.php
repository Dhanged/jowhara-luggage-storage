<?php
require_once __DIR__ . "/layout.php";
require_login();

$luggage_id = (int)($_GET["id"] ?? 0);
$row = db_fetch_one(
    "SELECT l.*, g.guest_name, g.phone, g.room_no, g.email, g.id_type, g.id_number, u.username AS checkout_user
     FROM luggage l
     JOIN guests g ON g.guest_id = l.guest_id
     LEFT JOIN users u ON u.user_id = l.checked_out_by
     WHERE l.luggage_id = ?",
    "i",
    [$luggage_id]
);

if (!$row) {
    set_flash("warning", __("Receipt not found."));
    redirect("luggage.php");
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "send_email") {
    verify_csrf();
    if ($row["email"]) {
        $sent = send_receipt_email($row);
        log_notification($luggage_id, (int)$row["guest_id"], "Email", $row["email"], receipt_message($row), $sent ? "Sent" : "Prepared");
        if ($sent) {
            db_execute("UPDATE luggage SET email_sent_at = NOW() WHERE luggage_id = ?", "i", [$luggage_id]);
        }
        set_flash($sent ? "success" : "warning", $sent ? __("Email receipt sent.") : __("Email was prepared, but XAMPP mail may not be configured."));
    }
    redirect("receipt.php?id=" . $luggage_id);
}

$conditions = parse_condition_flags($row["condition_flags"]);
$checkout_conditions = parse_condition_flags($row["checkout_condition_flags"] ?? "[]");
$wa_link = whatsapp_link($row["phone"], receipt_message($row));

$transfers = db_fetch_all(
    "SELECT t.*, u.username
     FROM luggage_transfers t
     JOIN users u ON u.user_id = t.moved_by
     WHERE t.luggage_id = ?
     ORDER BY t.moved_at DESC",
    "i",
    [$luggage_id]
);

$layout = $_GET["layout"] ?? "a4";
if (!in_array($layout, ["a4", "thermal-80", "thermal-58"], true)) {
    $layout = "a4";
}

render_header("Receipt", "luggage");
?>
<script>
document.body.classList.add('layout-<?php echo h($layout); ?>');
</script>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 no-print">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Receipt"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Printable receipt for"); ?> <?php echo h($row["tag_code"]); ?>.</p>
    </div>
    
    <!-- Receipt Layout Switcher Buttons -->
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="small text-secondary fw-semibold me-1"><i class="bi bi-printer me-1"></i><?php echo __("Receipt Layout"); ?>:</span>
        <div class="btn-group me-2">
            <a class="btn btn-sm <?php echo $layout === "a4" ? "btn-dark" : "btn-outline-dark"; ?>" href="receipt.php?id=<?php echo $luggage_id; ?>&layout=a4"><?php echo __("A4 Layout"); ?></a>
            <a class="btn btn-sm <?php echo $layout === "thermal-80" ? "btn-dark" : "btn-outline-dark"; ?>" href="receipt.php?id=<?php echo $luggage_id; ?>&layout=thermal-80"><?php echo __("Thermal 80mm Layout"); ?></a>
            <a class="btn btn-sm <?php echo $layout === "thermal-58" ? "btn-dark" : "btn-outline-dark"; ?>" href="receipt.php?id=<?php echo $luggage_id; ?>&layout=thermal-58"><?php echo __("Thermal 58mm Layout"); ?></a>
        </div>
        
        <a class="btn btn-outline-secondary" href="luggage.php"><?php echo __("Back"); ?></a>
        <?php if ($wa_link): ?>
            <a class="btn btn-outline-success" target="_blank" href="<?php echo h($wa_link); ?>"><?php echo __("WhatsApp Receipt"); ?></a>
        <?php endif; ?>
        <?php if ($row["email"]): ?>
            <form method="POST" class="d-inline">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="send_email">
                <button class="btn btn-outline-primary" type="submit"><?php echo __("Email Receipt"); ?></button>
            </form>
        <?php endif; ?>
        <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i><?php echo __("Print Receipt"); ?></button>
    </div>
</div>

<?php if ($layout === "a4"): ?>
    <!-- A4 Standard Layout with Side-by-Side checkout comparison -->
    <?php
    $is_high_val = (int)$row["is_high_value"] === 1;
    $receipt_style = $is_high_val ? 'border: 4px solid #d4af37 !important; box-shadow: 0 10px 40px rgba(212, 175, 55, 0.2) !important; background: linear-gradient(to bottom, #ffffff, #fffdf3); position: relative;' : '';
    ?>
    <section class="print-sheet shadow-sm border p-4" style="<?php echo $receipt_style; ?>">
        <?php if ($is_high_val): ?>
            <div class="mb-3 text-start no-print-badge">
                <span class="badge text-bg-danger text-uppercase px-3 py-2 border border-danger fw-bold" style="font-size: 0.8rem;">
                    <i class="bi bi-shield-fill-exclamation me-1"></i><?php echo __("High Value Declared"); ?>
                </span>
            </div>
        <?php endif; ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom pb-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <img src="assets/logo.jpg" alt="Jowhara Hotel Logo" class="receipt-logo" style="height: 54px; width: auto; border-radius: 4px;">
                <div>
                    <h2 class="h4 fw-bold mb-0">Jowhara International Hotel</h2>
                    <div class="text-secondary small"><?php echo __("Luggage Storage Desk"); ?></div>
                </div>
            </div>
            <div class="text-md-end">
                <div class="fw-bold text-primary fs-5"><?php echo h($row["tag_code"]); ?></div>
                <div class="text-secondary small"><?php echo __("Printed"); ?>: <?php echo h(date("Y-m-d H:i")); ?></div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <h3 class="h6 fw-bold border-bottom pb-2 mb-3 text-secondary"><?php echo __("Guest Details"); ?></h3>
                <dl class="row small mb-0">
                    <dt class="col-sm-4"><?php echo __("Guests"); ?></dt><dd class="col-sm-8"><?php echo h($row["guest_name"]); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Phone"); ?></dt><dd class="col-sm-8"><?php echo h($row["phone"] ?: "-"); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Email"); ?></dt><dd class="col-sm-8"><?php echo h($row["email"] ?: "-"); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Room"); ?></dt><dd class="col-sm-8"><?php echo h($row["room_no"] ?: "-"); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Guest ID"); ?></dt><dd class="col-sm-8"><?php echo h(trim(($row["id_type"] ?? "") . " " . ($row["id_number"] ?? ""))); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Booking"); ?></dt><dd class="col-sm-8"><?php echo h($row["reservation_no"] ?: "-"); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Luggage Type"); ?></dt><dd class="col-sm-8"><?php echo h($row["luggage_type"]); ?> (Qty <?php echo (int)$row["quantity"]; ?>)</dd>
                    <dt class="col-sm-4"><?php echo __("Color"); ?></dt><dd class="col-sm-8"><?php echo h($row["color"] ?: "-"); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Fee Amount"); ?></dt><dd class="col-sm-8">$<?php echo h(money($row["fee_amount"])); ?>, <?php echo h(__($row["payment_status"])); ?> <?php echo h($row["payment_method"] ? " via " . $row["payment_method"] : ""); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Weight Class"); ?></dt><dd class="col-sm-8"><?php echo h(__($row["weight_class"])); ?><?php echo $row["weight_kg"] !== null ? " (" . h($row["weight_kg"]) . " kg)" : ""; ?></dd>
                    <?php if ($row["security_seal_code"]): ?>
                        <dt class="col-sm-4"><?php echo __("Security Seal Tag Code"); ?></dt><dd class="col-sm-8"><span class="badge text-bg-secondary"><i class="bi bi-shield-lock me-1"></i><?php echo h($row["security_seal_code"]); ?></span></dd>
                    <?php endif; ?>
                    <?php if ($is_high_val): ?>
                        <dt class="col-sm-4 text-warning"><i class="bi bi-shield-fill-exclamation me-1"></i><?php echo __("Declared Value ($)"); ?></dt><dd class="col-sm-8 text-warning fw-bold">$<?php echo h(money($row["declared_value"])); ?> (<?php echo h($row["declared_items_desc"]); ?>)</dd>
                    <?php endif; ?>
                    <dt class="col-sm-4"><?php echo __("Status"); ?></dt><dd class="col-sm-8"><span class="badge text-bg-<?php echo h(status_badge($row["status"])); ?>"><?php echo h(__($row["status"])); ?></span></dd>
                </dl>
            </div>
            <div class="col-md-4 text-md-end d-flex flex-column align-items-md-end justify-content-between">
                <img class="qr-img mb-3" src="<?php echo h(qr_url(luggage_qr_payload($row))); ?>" alt="QR code" style="width: 130px; height: 130px;">
                <div class="text-center w-100">
                    <?php echo render_code39($row["tag_code"]); ?>
                </div>
            </div>
        </div>

        <!-- Side-by-Side Luggage Condition Checklist & Protection -->
        <div class="row g-4 border-top pt-4 mb-4">
            <div class="col-md-6 border-end">
                <h3 class="h6 fw-bold border-bottom pb-2 mb-3 text-primary"><i class="bi bi-box-arrow-in-right me-1"></i><?php echo __("Check In Details"); ?></h3>
                <dl class="row small mb-0">
                    <dt class="col-5"><?php echo __("Check In"); ?></dt><dd class="col-7"><?php echo h($row["checkin_date"]); ?></dd>
                    <dt class="col-5"><?php echo __("Condition Checklist"); ?></dt><dd class="col-7"><?php echo $conditions ? h(implode(", ", array_map("__", $conditions))) : __("None"); ?></dd>
                    <dt class="col-5"><?php echo __("Storage Zone"); ?></dt><dd class="col-7"><?php echo h(__($row["storage_zone"])); ?></dd>
                    <dt class="col-5"><?php echo __("Location"); ?></dt><dd class="col-7"><?php echo h($row["storage_location"]); ?></dd>
                    <dt class="col-5"><?php echo __("Expected Pickup"); ?></dt><dd class="col-7"><?php echo h($row["expected_pickup_date"] ?: "-"); ?></dd>
                </dl>
                <?php
                $photos = [];
                if ($row["photo"]) $photos[] = $row["photo"];
                if ($row["photo_2"]) $photos[] = $row["photo_2"];
                if ($row["photo_3"]) $photos[] = $row["photo_3"];
                ?>
                <?php if ($photos): ?>
                    <div class="mt-3">
                        <div class="small text-secondary mb-2 fw-semibold text-uppercase tracking-wider"><i class="bi bi-images me-1"></i><?php echo __("Check-In Photo Gallery"); ?></div>
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <?php foreach ($photos as $idx => $p): ?>
                                <a href="<?php echo h($p); ?>" target="_blank">
                                    <img class="img-fluid rounded border shadow-sm" style="max-height: 110px; width: 110px; object-fit: cover; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'" src="<?php echo h($p); ?>" alt="Baggage Photo <?php echo $idx+1; ?>">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-6">
                <h3 class="h6 fw-bold border-bottom pb-2 mb-3 text-success"><i class="bi bi-box-arrow-left me-1"></i><?php echo __("Check Out Details"); ?></h3>
                <?php if ($row["status"] === "Collected"): ?>
                    <dl class="row small mb-0">
                        <dt class="col-5"><?php echo __("Check Out"); ?></dt><dd class="col-7"><?php echo h($row["checkout_date"]); ?></dd>
                        <dt class="col-5"><?php echo __("Checkout Condition Checklist"); ?></dt><dd class="col-7"><?php echo $checkout_conditions ? h(implode(", ", array_map("__", $checkout_conditions))) : __("None"); ?></dd>
                        <dt class="col-5"><?php echo __("Pickup Person"); ?></dt><dd class="col-7"><?php echo h($row["pickup_person_name"]); ?> (<?php echo h($row["pickup_person_id"] ?: "-"); ?>)</dd>
                        <dt class="col-5"><?php echo __("Pickup Phone"); ?></dt><dd class="col-7"><?php echo h($row["pickup_person_phone"] ?: "-"); ?></dd>
                        <dt class="col-5"><?php echo __("Checked Out By"); ?></dt><dd class="col-7"><?php echo h($row["checkout_user"] ?: "-"); ?></dd>
                    </dl>
                    <?php if ($row["checkout_photo"]): ?>
                        <div class="mt-3 text-center">
                            <div class="small text-secondary mb-1 fw-semibold"><?php echo __("Checkout Photo"); ?></div>
                            <img class="img-fluid rounded border shadow-sm" style="max-height: 150px; object-fit: cover;" src="<?php echo h($row["checkout_photo"]); ?>" alt="Checkout Photo">
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-warning py-3 mb-0 small text-center">
                        <i class="bi bi-info-circle fs-5 d-block mb-1"></i>
                        <?php echo __("This luggage has not been checked out yet."); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Luggage Lifecycle dot-timeline -->
        <div class="row mt-4 border-top pt-4">
            <div class="col-12">
                <h3 class="h6 fw-bold mb-3 text-uppercase tracking-wider text-secondary"><i class="bi bi-clock-history me-1"></i><?php echo __("Luggage Journey Timeline / Diiwaanka Shandada"); ?></h3>
                <ul class="timeline">
                    <!-- Checkout Milestone -->
                    <?php if ($row["status"] === "Collected"): ?>
                        <li class="timeline-item completed">
                            <div class="timeline-marker" style="background: #10b981; border: 2px solid #fff;"></div>
                            <div class="timeline-title fw-bold text-success"><?php echo __("Baggage Collected / Shandada Waa La Qaatay"); ?></div>
                            <div class="timeline-desc text-secondary small">
                                <?php echo __("Checkout processed. Recipient:"); ?> <strong><?php echo h($row["pickup_person_name"]); ?></strong> (<?php echo h($row["pickup_person_id"] ?: "-"); ?>).
                                <br><span class="text-muted"><?php echo __("Checkout conditions:"); ?> <?php echo $checkout_conditions ? h(implode(", ", array_map("__", $checkout_conditions))) : __("None"); ?>.</span>
                            </div>
                            <div class="timeline-time text-primary small fw-semibold"><?php echo h($row["checkout_date"]); ?> (by <?php echo h($row["checkout_user"] ?: "System"); ?>)</div>
                        </li>
                    <?php endif; ?>

                    <!-- Repositioning transfers -->
                    <?php foreach ($transfers as $transfer): ?>
                        <li class="timeline-item completed">
                            <div class="timeline-marker" style="background: #f59e0b; border: 2px solid #fff;"></div>
                            <div class="timeline-title fw-bold text-warning"><?php echo __("Internal Repositioning / Wareejin"); ?></div>
                            <div class="timeline-desc text-secondary small">
                                <?php echo __("Moved from"); ?> <strong><?php echo h($transfer["from_zone"] . " " . $transfer["from_location"]); ?></strong> 
                                <?php echo __("to"); ?> <strong><?php echo h($transfer["to_zone"] . " " . $transfer["to_location"]); ?></strong>.
                                <br><span class="text-muted"><?php echo __("Reason:"); ?> <?php echo h(__($transfer["reason"])); ?></span>
                            </div>
                            <div class="timeline-time text-primary small fw-semibold"><?php echo h($transfer["moved_at"]); ?> (by <?php echo h($transfer["username"]); ?>)</div>
                        </li>
                    <?php endforeach; ?>

                    <!-- Check-in Milestone -->
                    <li class="timeline-item completed">
                        <div class="timeline-marker" style="background: #1d6f6f; border: 2px solid #fff;"></div>
                        <div class="timeline-title fw-bold text-primary"><?php echo __("Checked In & Stored / La Diiwaangaliyey"); ?></div>
                        <div class="timeline-desc text-secondary small">
                            <?php echo __("Registered and secured in zone"); ?> <strong><?php echo h(__($row["storage_zone"])); ?> <?php echo h($row["storage_location"]); ?></strong>.
                            <br><span class="text-muted"><?php echo __("Condition Checklist:"); ?> <?php echo $conditions ? h(implode(", ", array_map("__", $conditions))) : __("None"); ?></span>
                        </div>
                        <div class="timeline-time text-primary small fw-semibold"><?php echo h($row["checkin_date"]); ?></div>
                    </li>
                </ul>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-6">
                <div class="border-top pt-2 fw-semibold text-secondary small"><?php echo __("Guest Signature"); ?></div>
                <?php if ($row["signature_path"]): ?>
                    <img class="signature-preview mt-2" src="<?php echo h($row["signature_path"]); ?>" alt="Guest signature">
                <?php endif; ?>
            </div>
            <div class="col-6 text-end">
                <div class="border-top pt-2 fw-semibold text-secondary small"><?php echo __("Reception Signature"); ?></div>
                <div class="mt-4 border-bottom d-inline-block" style="width: 150px; height: 40px;"></div>
            </div>
        </div>
    </section>
<?php else: ?>
    <!-- Thermal Receipt 80mm or 58mm layouts -->
    <section class="thermal-receipt <?php echo $layout === "thermal-80" ? "thermal-80" : "thermal-58"; ?> print-sheet shadow-sm border p-3">
        <div class="text-center">
            <h2 class="h5 fw-bold mb-0">JOWHARA HOTEL</h2>
            <div class="small text-uppercase" style="font-size: 0.8rem;"><?php echo __("Luggage Storage Desk"); ?></div>
            <div class="small" style="font-size: 0.75rem;">MOGADISHU, SOMALIA</div>
            <div class="thermal-receipt-divider"></div>
            <div class="fw-bold mb-1" style="font-size: 1.15rem;"><?php echo h($row["tag_code"]); ?></div>
            <div class="small"><?php echo __("Printed"); ?>: <?php echo h(date("Y-m-d H:i")); ?></div>
        </div>
        
        <div class="thermal-receipt-divider"></div>
        
        <div class="thermal-receipt-row">
            <span class="thermal-receipt-label"><?php echo __("Guest"); ?>:</span>
            <span class="thermal-receipt-value"><?php echo h($row["guest_name"]); ?></span>
        </div>
        <div class="thermal-receipt-row">
            <span class="thermal-receipt-label"><?php echo __("Room"); ?>:</span>
            <span class="thermal-receipt-value"><?php echo h($row["room_no"] ?: "-"); ?></span>
        </div>
        <div class="thermal-receipt-row">
            <span class="thermal-receipt-label"><?php echo __("Luggage"); ?>:</span>
            <span class="thermal-receipt-value"><?php echo h($row["luggage_type"]); ?> (x<?php echo (int)$row["quantity"]; ?>)</span>
        </div>
        <div class="thermal-receipt-row">
            <span class="thermal-receipt-label"><?php echo __("Color"); ?>:</span>
            <span class="thermal-receipt-value"><?php echo h($row["color"] ?: "-"); ?></span>
        </div>
        <div class="thermal-receipt-row">
            <span class="thermal-receipt-label"><?php echo __("Storage"); ?>:</span>
            <span class="thermal-receipt-value"><?php echo h($row["storage_zone"]); ?> <?php echo h($row["storage_location"]); ?></span>
        </div>
        
        <div class="thermal-receipt-divider"></div>
        
        <div class="thermal-receipt-row">
            <span class="thermal-receipt-label"><?php echo __("Check In"); ?>:</span>
            <span class="thermal-receipt-value"><?php echo h($row["checkin_date"]); ?></span>
        </div>
        <div class="thermal-receipt-row">
            <span class="thermal-receipt-label"><?php echo __("Condition"); ?>:</span>
            <span class="thermal-receipt-value"><?php echo $conditions ? h(implode(", ", array_map("__", $conditions))) : __("None"); ?></span>
        </div>
        
        <?php if ($row["status"] === "Collected"): ?>
            <div class="thermal-receipt-divider"></div>
            <div class="thermal-receipt-row">
                <span class="thermal-receipt-label"><?php echo __("Check Out"); ?>:</span>
                <span class="thermal-receipt-value"><?php echo h($row["checkout_date"]); ?></span>
            </div>
            <div class="thermal-receipt-row">
                <span class="thermal-receipt-label"><?php echo __("Checkout Condition"); ?>:</span>
                <span class="thermal-receipt-value"><?php echo $checkout_conditions ? h(implode(", ", array_map("__", $checkout_conditions))) : __("None"); ?></span>
            </div>
            <div class="thermal-receipt-row">
                <span class="thermal-receipt-label"><?php echo __("Pickup Person"); ?>:</span>
                <span class="thermal-receipt-value"><?php echo h($row["pickup_person_name"]); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="thermal-receipt-divider"></div>
        
        <div class="thermal-receipt-row">
            <span class="thermal-receipt-label"><?php echo __("Fee"); ?>:</span>
            <span class="thermal-receipt-value fw-bold">$<?php echo h(money($row["fee_amount"])); ?></span>
        </div>
        <div class="thermal-receipt-row">
            <span class="thermal-receipt-label"><?php echo __("Payment Status"); ?>:</span>
            <span class="thermal-receipt-value fw-bold"><?php echo h(__($row["payment_status"])); ?></span>
        </div>
        
        <div class="thermal-receipt-divider"></div>
        
        <div class="text-center my-3">
            <img class="qr-img" src="<?php echo h(qr_url(luggage_qr_payload($row), 110)); ?>" alt="QR code" style="width: 110px; height: 110px;">
        </div>
        
        <div class="text-center mb-3">
            <?php echo render_code39($row["tag_code"]); ?>
        </div>
        
        <div class="text-center small mt-2">
            <?php echo __("Thank you for staying at Jowhara International Hotel!"); ?>
        </div>
    </section>
<?php endif; ?>

<?php render_footer(); ?>

<?php
require_once __DIR__ . "/layout.php";
require_permission("checkout");

$luggage_id = (int)($_GET["id"] ?? 0);
$row = db_fetch_one(
    "SELECT l.*, g.guest_name, g.phone, g.room_no, g.email, g.id_type, g.id_number
     FROM luggage l
     JOIN guests g ON g.guest_id = l.guest_id
     WHERE l.luggage_id = ? AND l.deleted_at IS NULL",
    "i",
    [$luggage_id]
);

if (!$row) {
    set_flash("warning", __("Luggage not found."));
    redirect("luggage.php");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    try {
        if ($row["status"] !== "Stored") {
            set_flash("warning", __("This luggage has already been collected."));
            redirect("luggage.php");
        }

        // Verify security seal code if one was registered
        if (!empty($row["security_seal_code"])) {
            $verify_seal = trim($_POST["verify_seal_code"] ?? "");
            if (strcasecmp($verify_seal, $row["security_seal_code"]) !== 0) {
                throw new RuntimeException(__("Security verification failed: Entered seal code does not match the registered code."));
            }
        }

        $pickup_name = trim($_POST["pickup_person_name"] ?? "");
        $pickup_phone = trim($_POST["pickup_person_phone"] ?? "");
        $pickup_id = trim($_POST["pickup_person_id"] ?? "");
        $fee_amount = max(0, (float)($_POST["fee_amount"] ?? 0));
        $payment_status = $_POST["payment_status"] ?? "Unpaid";
        $payment_method = trim($_POST["payment_method"] ?? "");
        $payment_reference = trim($_POST["payment_reference"] ?? "");
        $signature_data = $_POST["signature_data"] ?? "";
        
        $discount_code = trim($_POST["discount_code"] ?? "");
        $discount_amount = max(0.0, (float)($_POST["discount_amount"] ?? 0.0));
        $currency_settled = trim($_POST["currency_settled"] ?? "USD");
        if (!in_array($currency_settled, ["USD", "SOS", "AED"], true)) {
            $currency_settled = "USD";
        }
        
        $exchange_rate = 1.0000;
        if ($currency_settled === "SOS") {
            $exchange_rate = 26000.0000;
        } elseif ($currency_settled === "AED") {
            $exchange_rate = 3.6725;
        }
        
        if ($discount_code === "") {
            $discount_code = null;
        } else {
            $discount_code = strtoupper($discount_code);
        }
        
        $checkout_condition_flags = condition_flags_to_db($_POST["checkout_condition_flags"] ?? []);
        $checkout_photo = upload_checkout_photo("checkout_photo");

        if (!in_array($payment_status, ["Unpaid", "Paid", "Waived"], true)) {
            $payment_status = "Unpaid";
        }
        if ($pickup_name === "") {
            throw new RuntimeException(__("Pickup person name is required."));
        }

        $signature_path = save_signature_image($signature_data, $row["signature_path"]);
        if (!$signature_path) {
            throw new RuntimeException(__("Guest signature is required."));
        }

        $user_id = (int)current_user()["user_id"];
        global $conn;
        $conn->begin_transaction();

        db_execute(
            "UPDATE luggage
             SET status = 'Collected', checkout_date = NOW(), checked_out_by = ?,
                 pickup_person_name = ?, pickup_person_phone = ?, pickup_person_id = ?,
                 fee_amount = ?, payment_status = ?, payment_method = ?, payment_reference = ?,
                 signature_path = ?, checkout_photo = ?, checkout_condition_flags = ?,
                 discount_code = ?, discount_amount = ?, currency_settled = ?, exchange_rate = ?
             WHERE luggage_id = ?",
            "isssdsssssssdsdi",
            [$user_id, $pickup_name, $pickup_phone, $pickup_id, $fee_amount, $payment_status,
                $payment_method, $payment_reference, $signature_path, $checkout_photo, $checkout_condition_flags,
                $discount_code, $discount_amount, $currency_settled, $exchange_rate, $luggage_id]
        );

        trigger_api_notification($luggage_id, "checkout");

        $updated = db_fetch_one(
            "SELECT l.*, g.guest_name, g.phone, g.room_no, g.email
             FROM luggage l JOIN guests g ON g.guest_id = l.guest_id
             WHERE l.luggage_id = ?",
            "i",
            [$luggage_id]
        );
        $message = receipt_message($updated);

        if (!empty($_POST["send_email"]) && !empty($updated["email"])) {
            $sent = send_receipt_email($updated);
            log_notification($luggage_id, (int)$updated["guest_id"], "Email", $updated["email"], $message, $sent ? "Sent" : "Prepared");
            if ($sent) {
                db_execute("UPDATE luggage SET email_sent_at = NOW() WHERE luggage_id = ?", "i", [$luggage_id]);
            }
        }

        if (!empty($_POST["prepare_whatsapp"])) {
            log_notification($luggage_id, (int)$updated["guest_id"], "WhatsApp", $updated["phone"], $message, "Prepared");
            db_execute("UPDATE luggage SET sms_sent_at = NOW() WHERE luggage_id = ?", "i", [$luggage_id]);
        }

        log_action("Checked out luggage", "luggage", $luggage_id, "Tag {$row["tag_code"]} checked out by pickup person $pickup_name");

        $conn->commit();

        set_flash("success", __("Luggage checked out. Receipt is ready to print."));
        redirect("receipt.php?id=" . $luggage_id);
    } catch (Throwable $e) {
        if (isset($conn) && $conn->in_transaction) {
            $conn->rollback();
        }
        set_flash("danger", $e->getMessage());
        redirect("checkout.php?id=" . $luggage_id);
    }
}

$days_overdue = 0;
$penalty_fee = 0.0;
$daily_penalty_rate = (float)get_setting("daily_penalty_fee", "5.00");
$grace_hours = (int)get_setting("grace_hours", "2");

if ($row["expected_pickup_date"] && strtotime($row["expected_pickup_date"]) < strtotime(date("Y-m-d"))) {
    $expected_time = strtotime($row["expected_pickup_date"] . " 23:59:59");
    $expected_time_with_grace = $expected_time + ($grace_hours * 3600);
    
    if (time() > $expected_time_with_grace) {
        $current_time = time();
        $diff = $current_time - $expected_time;
        $days_overdue = max(0, ceil($diff / (60 * 60 * 24)));
        $penalty_fee = $days_overdue * $daily_penalty_rate;
    }
}
$suggested_fee = (float)$row["fee_amount"] + $penalty_fee;

$conditions = parse_condition_flags($row["condition_flags"]);
$wa_link = whatsapp_link($row["phone"], receipt_message($row));

render_header("Checkout Luggage", "luggage");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Checkout Luggage"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Confirm collection, payment, pickup person, and signature for tag"); ?> <?php echo h($row["tag_code"]); ?>.</p>
    </div>
    <a class="btn btn-outline-secondary" href="luggage.php"><?php echo __("Back"); ?></a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h2 class="section-title"><?php echo __("Luggage Summary"); ?></h2></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4"><?php echo __("Guests"); ?></dt>
                    <dd class="col-sm-8"><?php echo h($row["guest_name"]); ?>, Room <?php echo h($row["room_no"]); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Guest ID"); ?></dt>
                    <dd class="col-sm-8"><?php echo h(trim(($row["id_type"] ?? "") . " " . ($row["id_number"] ?? ""))); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Luggage"); ?></dt>
                    <dd class="col-sm-8"><?php echo h($row["luggage_type"]); ?>, Qty <?php echo (int)$row["quantity"]; ?>, <?php echo h($row["color"]); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Storage"); ?></dt>
                    <dd class="col-sm-8"><?php echo h(__($row["storage_zone"])); ?> <?php echo h($row["storage_location"]); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Condition Checklist"); ?></dt>
                    <dd class="col-sm-8"><?php echo $conditions ? h(implode(", ", array_map("__", $conditions))) : __("None"); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Expected"); ?></dt>
                    <dd class="col-sm-8"><?php echo h($row["expected_pickup_date"] ?: "-"); ?></dd>
                    <dt class="col-sm-4"><?php echo __("Fee"); ?></dt>
                    <dd class="col-sm-8">$<?php echo h(money($row["fee_amount"])); ?>, <?php echo h(__($row["payment_status"])); ?></dd>
                    <?php if ($penalty_fee > 0): ?>
                        <dt class="col-sm-4 text-danger"><?php echo __("Overdue Penalty Engine"); ?></dt>
                        <dd class="col-sm-8 text-danger fw-bold">+$<?php echo h(money($penalty_fee)); ?> (<?php echo $days_overdue . " " . __("days overdue"); ?>)</dd>
                    <?php endif; ?>
                    <dt class="col-sm-4"><?php echo __("Weight Class"); ?></dt>
                    <dd class="col-sm-8"><?php echo h(__($row["weight_class"])); ?><?php echo $row["weight_kg"] !== null ? " (" . h($row["weight_kg"]) . " kg)" : ""; ?></dd>
                    <?php if ($row["security_seal_code"]): ?>
                        <dt class="col-sm-4"><?php echo __("Security Seal Tag Code"); ?></dt>
                        <dd class="col-sm-8"><span class="badge text-bg-secondary"><i class="bi bi-shield-lock me-1"></i><?php echo h($row["security_seal_code"]); ?></span></dd>
                    <?php endif; ?>
                    <?php if ((int)$row["is_high_value"] === 1): ?>
                        <dt class="col-sm-4 text-warning"><i class="bi bi-shield-fill-exclamation me-1"></i><?php echo __("High Value Declared"); ?></dt>
                        <dd class="col-sm-8 text-warning fw-bold">$<?php echo h(money($row["declared_value"])); ?> (<?php echo h($row["declared_items_desc"]); ?>)</dd>
                    <?php endif; ?>
                    <dt class="col-sm-4"><?php echo __("Status"); ?></dt>
                    <dd class="col-sm-8"><span class="badge text-bg-<?php echo h(status_badge($row["status"])); ?>"><?php echo h(__($row["status"])); ?></span></dd>
                </dl>
                <?php if ($wa_link): ?>
                    <a class="btn btn-outline-success w-100 mt-3" target="_blank" href="<?php echo h($wa_link); ?>"><?php echo __("Preview WhatsApp Message"); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h2 class="section-title"><?php echo __("Collection Details"); ?></h2></div>
            <div class="card-body">
                <?php if ($row["status"] === "Stored"): ?>
                    <form class="row g-3" method="POST" id="checkoutForm" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <?php if ((int)$row["is_high_value"] === 1): ?>
                            <div class="col-12">
                                <div class="alert alert-warning border-warning d-flex align-items-center gap-2 mb-0" style="background: rgba(245, 158, 11, 0.05);">
                                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                                    <span class="text-warning-emphasis small fw-bold">
                                        <?php echo __("ATTENTION: This is a high-value declared bag. Confirm guest identity carefully and check the item contents description."); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <label class="form-label" for="pickup_person_name"><?php echo __("Pickup Person"); ?></label>
                            <input class="form-control" type="text" id="pickup_person_name" name="pickup_person_name" value="<?php echo h($row["guest_name"]); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pickup_person_phone"><?php echo __("Pickup Phone"); ?></label>
                            <input class="form-control" type="text" id="pickup_person_phone" name="pickup_person_phone" value="<?php echo h($row["phone"]); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pickup_person_id"><?php echo __("Pickup ID Number"); ?></label>
                            <input class="form-control" type="text" id="pickup_person_id" name="pickup_person_id" value="<?php echo h($row["id_number"]); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-primary fw-bold" for="fee_amount"><?php echo __("Final Fee (USD)"); ?></label>
                            <input class="form-control fw-bold border-primary" type="number" min="0" step="0.01" id="fee_amount" name="fee_amount" value="<?php echo h($suggested_fee); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-success fw-bold" for="discount_code"><?php echo __("Promo Code"); ?></label>
                            <input class="form-control border-success text-uppercase" type="text" id="discount_code" name="discount_code" placeholder="e.g. VIP100">
                            <input type="hidden" name="discount_amount" id="discount_amount" value="0.00">
                            <div id="promo_badge_container" class="mt-1"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-info fw-bold" for="currency_settled"><?php echo __("Currency"); ?></label>
                            <select class="form-select border-info fw-semibold text-info" id="currency_settled" name="currency_settled">
                                <option value="USD" selected>USD ($)</option>
                                <option value="SOS">SOS (Sh.So.)</option>
                                <option value="AED">AED (Dhs)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="payment_status"><?php echo __("Payment Status"); ?></label>
                            <select class="form-select" id="payment_status" name="payment_status">
                                <?php foreach (["Unpaid", "Paid", "Waived"] as $status): ?>
                                    <option value="<?php echo h($status); ?>" <?php echo $row["payment_status"] === $status ? "selected" : ""; ?>><?php echo h(__($status)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="payment_method"><?php echo __("Method"); ?></label>
                            <input class="form-control" type="text" id="payment_method" name="payment_method" value="<?php echo h($row["payment_method"]); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="payment_reference"><?php echo __("Reference"); ?></label>
                            <input class="form-control" type="text" id="payment_reference" name="payment_reference" value="<?php echo h($row["payment_reference"]); ?>">
                        </div>

                        <!-- Dynamic Multi-Currency / Discount Calculation Banner -->
                        <div class="col-12">
                            <div class="p-3 bg-light border rounded d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div>
                                    <span class="text-secondary small d-block"><?php echo __("Base suggested fee"); ?>: <strong class="text-dark">$<?php echo h(money($suggested_fee)); ?></strong></span>
                                    <span class="text-secondary small d-block" id="applied_discount_text"><?php echo __("Discount applied"); ?>: <strong class="text-success">-$0.00</strong></span>
                                </div>
                                <div class="text-md-end">
                                    <span class="text-secondary small d-block"><?php echo __("Final Amount to Collect"); ?>:</span>
                                    <span class="fs-4 fw-extrabold text-primary" id="converted_amount_display">$<?php echo h(money($suggested_fee)); ?> USD</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Verify Security Seal tag code if set -->
                        <?php if (!empty($row["security_seal_code"])): ?>
                            <div class="col-md-12">
                                <label class="form-label text-danger fw-semibold" for="verify_seal_code">
                                    <i class="bi bi-shield-lock-fill me-1"></i>
                                    <?php echo __("Verify Security Seal Tag Code"); ?> <span class="text-danger">*</span>
                                </label>
                                <input class="form-control border-danger" type="text" id="verify_seal_code" name="verify_seal_code" required placeholder="<?php echo __("Type the security seal code to verify"); ?>">
                                <div class="form-hint text-danger mt-1">
                                    <?php echo __("Security verification: Type the code on the plastic seal tag to confirm it hasn't been opened."); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Checkout Photo upload field -->
                        <div class="col-md-12">
                            <label class="form-label" for="checkout_photo"><?php echo __("Checkout Photo"); ?></label>
                            <input class="form-control" type="file" id="checkout_photo" name="checkout_photo" accept="image/*">
                            <div class="form-hint mt-1"><?php echo __("JPG, PNG, WEBP, or GIF. Max 3MB."); ?></div>
                        </div>

                        <!-- Checkout Condition Checklist (pre-populated with check-in conditions) -->
                        <div class="col-12">
                            <label class="form-label"><?php echo __("Checkout Condition Checklist"); ?></label>
                            <div class="condition-grid">
                                <?php foreach (condition_options() as $option): ?>
                                    <label class="form-check">
                                        <input class="form-check-input" type="checkbox" name="checkout_condition_flags[]" value="<?php echo h($option); ?>" <?php echo in_array($option, $conditions, true) ? "checked" : ""; ?>>
                                        <span class="form-check-label"><?php echo h(__($option)); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label"><?php echo __("Guest Signature"); ?></label>
                            <canvas class="signature-pad" id="signaturePad"></canvas>
                            <input type="hidden" name="signature_data" id="signatureData">
                            <button class="btn btn-outline-secondary btn-sm mt-2" type="button" id="clearSignature"><?php echo __("Clear Signature"); ?></button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_email" value="1" <?php echo $row["email"] ? "" : "disabled"; ?>>
                                <span class="form-check-label"><?php echo $row["email"] ? __("Email receipt") . " to " . h($row["email"]) : "(" . __("no email") . ")"; ?></span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="prepare_whatsapp" value="1" <?php echo $row["phone"] ? "" : "disabled"; ?>>
                                <span class="form-check-label"><?php echo __("Prepare WhatsApp/SMS receipt"); ?></span>
                            </label>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-success" type="submit"><?php echo __("Confirm Checkout"); ?></button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info mb-0"><?php echo __("This luggage was already collected on"); ?> <?php echo h($row["checkout_date"]); ?>.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const canvas = document.getElementById("signaturePad");
    const hidden = document.getElementById("signatureData");
    if (!canvas || !hidden) return;
    const ctx = canvas.getContext("2d");
    let drawing = false;
    let signed = false;

    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2;
        ctx.lineCap = "round";
        ctx.strokeStyle = "#111827";
    }
    function point(event) {
        const rect = canvas.getBoundingClientRect();
        const touch = event.touches ? event.touches[0] : event;
        return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
    }
    function start(event) {
        event.preventDefault();
        drawing = true;
        signed = true;
        const p = point(event);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }
    function move(event) {
        if (!drawing) return;
        event.preventDefault();
        const p = point(event);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    }
    function stop() {
        drawing = false;
        hidden.value = signed ? canvas.toDataURL("image/png") : "";
    }

    resizeCanvas();
    window.addEventListener("resize", resizeCanvas);
    canvas.addEventListener("mousedown", start);
    canvas.addEventListener("mousemove", move);
    document.addEventListener("mouseup", stop);
    canvas.addEventListener("touchstart", start, { passive: false });
    canvas.addEventListener("touchmove", move, { passive: false });
    canvas.addEventListener("touchend", stop);
    document.getElementById("clearSignature").addEventListener("click", function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        signed = false;
        hidden.value = "";
    });
    document.getElementById("checkoutForm").addEventListener("submit", function (event) {
        hidden.value = signed ? canvas.toDataURL("image/png") : "";
        if (!hidden.value) {
            event.preventDefault();
            alert("Signature is required before checkout.");
        }
    });
}());
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const feeInput = document.getElementById("fee_amount");
    const promoInput = document.getElementById("discount_code");
    const discountAmountInput = document.getElementById("discount_amount");
    const currencySelect = document.getElementById("currency_settled");
    const promoBadgeContainer = document.getElementById("promo_badge_container");
    const appliedDiscountText = document.getElementById("applied_discount_text");
    const convertedDisplay = document.getElementById("converted_amount_display");

    const baseFee = parseFloat("<?php echo (float)$suggested_fee; ?>") || 0.0;

    const rates = {
        USD: { name: "USD", symbol: "$", rate: 1.0000 },
        SOS: { name: "SOS", symbol: "Sh.So. ", rate: 26000.0000 },
        AED: { name: "AED", symbol: "AED ", rate: 3.6725 }
    };

    function recalculate() {
        let discount = 0.0;
        const promoCode = promoInput.value.trim().toUpperCase();

        if (promoCode === "VIP100") {
            discount = baseFee; // 100% off
            promoBadgeContainer.innerHTML = '<span class="badge text-bg-success"><i class="bi bi-tag-fill me-1"></i>VIP100 (100% OFF)</span>';
        } else if (promoCode === "WELCOME10") {
            discount = baseFee * 0.1; // 10% off
            promoBadgeContainer.innerHTML = '<span class="badge text-bg-success"><i class="bi bi-tag-fill me-1"></i>WELCOME10 (10% OFF)</span>';
        } else if (promoCode === "HOTEL5") {
            discount = Math.min(baseFee, 5.0); // $5 flat off
            promoBadgeContainer.innerHTML = '<span class="badge text-bg-success"><i class="bi bi-tag-fill me-1"></i>HOTEL5 ($5 OFF)</span>';
        } else if (promoCode !== "") {
            promoBadgeContainer.innerHTML = '<span class="badge text-bg-danger"><i class="bi bi-x-circle me-1"></i>Invalid Promo Code</span>';
        } else {
            promoBadgeContainer.innerHTML = '';
        }

        // Apply discount to compute final fee in USD
        let finalFeeUSD = Math.max(0, baseFee - discount);
        
        // Update inputs
        feeInput.value = finalFeeUSD.toFixed(2);
        discountAmountInput.value = discount.toFixed(2);

        // Update UI Texts
        appliedDiscountText.innerHTML = '<?php echo __("Discount applied"); ?>: <strong class="text-success">-$' + discount.toFixed(2) + '</strong>';

        // Apply currency conversion
        const currency = currencySelect.value;
        const rateInfo = rates[currency] || rates.USD;
        let convertedVal = finalFeeUSD * rateInfo.rate;

        // Custom formatting for SOS to look like standard currency
        let formattedVal = "";
        if (currency === "SOS") {
            formattedVal = rateInfo.symbol + Math.round(convertedVal).toLocaleString();
        } else {
            formattedVal = rateInfo.symbol + convertedVal.toFixed(2);
        }

        convertedDisplay.textContent = formattedVal + " (" + currency + ")";
    }

    promoInput.addEventListener("input", recalculate);
    currencySelect.addEventListener("change", recalculate);

    // Run initial on load
    recalculate();
});
</script>

<?php render_footer(); ?>

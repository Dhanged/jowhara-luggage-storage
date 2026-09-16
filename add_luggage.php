<?php
require_once __DIR__ . "/layout.php";
require_once __DIR__ . "/photo_uploader.php";
require_permission("create");

$selected_guest = (int)($_GET["guest_id"] ?? 0);
$guests = db_fetch_all("SELECT guest_id, guest_name, room_no, is_vip, is_blacklisted, blacklist_reason FROM guests WHERE deleted_at IS NULL ORDER BY guest_name");
$shelves = db_fetch_all("SELECT zone_name, shelf_name FROM storage_shelves WHERE status = 'Active' ORDER BY zone_name, shelf_name");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    try {
        $guest_id = (int)($_POST["guest_id"] ?? 0);
        $type = trim($_POST["luggage_type"] ?? "");
        $quantity = max(1, (int)($_POST["quantity"] ?? 1));
        $color = trim($_POST["color"] ?? "");
        $zone = trim($_POST["storage_zone"] ?? "");
        $location = trim($_POST["location"] ?? "");
        $reservation_no = trim($_POST["reservation_no"] ?? "");
        $expected_pickup = trim($_POST["expected_pickup_date"] ?? "");
        $expected_pickup = $expected_pickup !== "" ? $expected_pickup : null;
        $fee_amount = max(0, (float)($_POST["fee_amount"] ?? 0));
        $payment_status = $_POST["payment_status"] ?? "Unpaid";
        $payment_method = trim($_POST["payment_method"] ?? "");
        $payment_reference = trim($_POST["payment_reference"] ?? "");
        $notification_preference = $_POST["notification_preference"] ?? "None";
        $notes = trim($_POST["notes"] ?? "");
        $condition_flags = condition_flags_to_db($_POST["condition_flags"] ?? []);
        $photo = upload_luggage_photo("photo");
        $photo_2 = upload_luggage_photo("photo_2");
        $photo_3 = upload_luggage_photo("photo_3");
        $created_by = (int)current_user()["user_id"];

        // Phase II Parameters
        $security_seal_code = trim($_POST["security_seal_code"] ?? "");
        $security_seal_code = $security_seal_code !== "" ? $security_seal_code : null;
        $is_high_value = isset($_POST["is_high_value"]) ? 1 : 0;
        $declared_value = max(0, (float)($_POST["declared_value"] ?? 0));
        $declared_items_desc = trim($_POST["declared_items_desc"] ?? "");
        $declared_items_desc = $declared_items_desc !== "" ? $declared_items_desc : null;
        $weight_class = $_POST["weight_class"] ?? "Light";
        $weight_kg = trim($_POST["weight_kg"] ?? "");
        $weight_kg = $weight_kg !== "" ? (float)$weight_kg : null;

        if (!in_array($payment_status, ["Unpaid", "Paid", "Waived"], true)) {
            $payment_status = "Unpaid";
        }

        global $conn;
        $conn->begin_transaction();

        db_execute(
            "INSERT INTO luggage
             (guest_id, tag_code, luggage_type, quantity, color, storage_zone, storage_location, photo, photo_2, photo_3,
              condition_flags, reservation_no, expected_pickup_date, fee_amount, payment_status,
              payment_method, payment_reference, notification_preference, notes, status, created_by,
              security_seal_code, is_high_value, declared_value, declared_items_desc, weight_class, weight_kg)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Stored', ?, ?, ?, ?, ?, ?, ?)",
            "isisssssssssdsssssisidssd",
            [$guest_id, $type, $quantity, $color, $zone, $location, $photo, $photo_2, $photo_3, $condition_flags, $reservation_no,
                $expected_pickup, $fee_amount, $payment_status, $payment_method, $payment_reference,
                $notification_preference, $notes, $created_by,
                $security_seal_code, $is_high_value, $declared_value, $declared_items_desc, $weight_class, $weight_kg]
        );

        $luggage_id = db_insert_id();
        $tag = generate_tag_code($luggage_id);
        db_execute("UPDATE luggage SET tag_code = ? WHERE luggage_id = ?", "si", [$tag, $luggage_id]);
        log_action("Created luggage", "luggage", $luggage_id, "Tag $tag added");

        $conn->commit();

        trigger_api_notification($luggage_id, "checkin");
        set_flash("success", __("Luggage saved. Print the tag and attach it to the bag."));
        redirect("tag.php?id=" . $luggage_id);
    } catch (Throwable $e) {
        if (isset($conn) && $conn->in_transaction) {
            $conn->rollback();
        }
        set_flash("danger", $e->getMessage());
        redirect("add_luggage.php");
    }
}

render_header("Add Luggage", "luggage");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Add Luggage"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Create a storage tag with condition, booking, payment, and notification details."); ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="luggage.php"><?php echo __("Back"); ?></a>
</div>

<?php if (!$guests): ?>
    <div class="alert alert-warning"><?php echo __("Add an active guest before adding luggage."); ?> <a href="add_guest.php"><?php echo __("Create guest"); ?></a></div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <form class="row g-3" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="col-md-6">
                <label class="form-label" for="guest_id"><?php echo __("Guests"); ?></label>
                <select class="form-select text-dark" id="guest_id" name="guest_id" required>
                    <?php foreach ($guests as $guest): ?>
                        <?php
                        $vip_label = $guest["is_vip"] ? " [VIP]" : "";
                        $bl_label = $guest["is_blacklisted"] ? " [⚠️ BLACKLISTED]" : "";
                        ?>
                        <option value="<?php echo (int)$guest["guest_id"]; ?>" 
                                data-vip="<?php echo $guest["is_vip"]; ?>" 
                                data-blacklisted="<?php echo $guest["is_blacklisted"]; ?>"
                                data-reason="<?php echo h($guest["blacklist_reason"] ?? ""); ?>"
                                <?php echo $selected_guest === (int)$guest["guest_id"] ? "selected" : ""; ?>>
                            <?php echo h($guest["guest_name"]) . $vip_label . $bl_label; ?><?php echo $guest["room_no"] ? " - Room " . h($guest["room_no"]) : ""; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <!-- Real-time Blacklist Warning box -->
                <div id="blacklistAlert" class="alert alert-danger d-none my-2 p-2 border-danger shadow-sm">
                    <strong><i class="bi bi-slash-circle-fill text-danger me-1"></i>Warning: This guest is blacklisted!</strong> <span id="blacklistReason" class="d-block small mt-1 fst-italic"></span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="reservation_no"><?php echo __("Booking / Reservation No"); ?></label>
                <input class="form-control" type="text" id="reservation_no" name="reservation_no">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="expected_pickup_date"><?php echo __("Expected Pickup"); ?></label>
                <input class="form-control" type="date" id="expected_pickup_date" name="expected_pickup_date">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="luggage_type"><?php echo __("Luggage Type"); ?></label>
                <input class="form-control" type="text" id="luggage_type" name="luggage_type" placeholder="Suitcase, backpack, box" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="quantity"><?php echo __("Quantity"); ?></label>
                <input class="form-control" type="number" min="1" id="quantity" name="quantity" value="1" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="color"><?php echo __("Color"); ?></label>
                <input class="form-control" type="text" id="color" name="color">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="storage_zone"><?php echo __("Storage Zone"); ?></label>
                <input class="form-control" type="text" id="storage_zone" name="storage_zone" placeholder="A, B, VIP">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="location"><?php echo __("Storage Shelf / Location"); ?></label>
                <input class="form-control" list="shelfOptions" type="text" id="location" name="location" placeholder="A-1">
                <datalist id="shelfOptions">
                    <?php foreach ($shelves as $shelf): ?>
                        <option value="<?php echo h($shelf["shelf_name"]); ?>"><?php echo h($shelf["zone_name"]); ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <!-- Phase II: Specifications, Seals, and High-Value Fields -->
            <div class="col-md-3">
                <label class="form-label" for="weight_class"><?php echo __("Weight Class"); ?></label>
                <select class="form-select" id="weight_class" name="weight_class">
                    <option value="Light"><?php echo __("Light"); ?></option>
                    <option value="Medium"><?php echo __("Medium"); ?></option>
                    <option value="Heavy"><?php echo __("Heavy"); ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="weight_kg"><?php echo __("Weight (kg)"); ?></label>
                <input class="form-control" type="number" step="0.1" min="0" id="weight_kg" name="weight_kg" placeholder="e.g. 15.5">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="security_seal_code"><?php echo __("Security Seal Tag Code"); ?></label>
                <input class="form-control" type="text" id="security_seal_code" name="security_seal_code" placeholder="e.g. SEC-98213">
            </div>

            <!-- Smart Suggestion Alert Box for Heavy Weight class -->
            <div class="col-12 d-none" id="heavySuggestionBox">
                <div class="alert alert-info d-flex align-items-center gap-2 mb-0 border-info" style="background: rgba(13, 202, 240, 0.05);">
                    <i class="bi bi-info-circle-fill text-info fs-5"></i>
                    <span class="text-info-emphasis small fw-medium">
                        <?php echo __("Smart Suggestion: Heavy bags must be stored on lower shelves (e.g., A-1, A-2, B-1, B-2) for porter safety."); ?>
                    </span>
                </div>
            </div>

            <!-- High-Value Item declaration card -->
            <div class="col-12">
                <div class="card p-3 border-secondary border-opacity-25" style="background: rgba(29, 111, 111, 0.02);">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_high_value" name="is_high_value" value="1">
                        <label class="form-check-label fw-semibold text-dark" for="is_high_value">
                            <i class="bi bi-shield-fill-exclamation text-warning me-1"></i>
                            <?php echo __("High-Value Item Declaration"); ?>
                        </label>
                    </div>
                    <div id="highValueFormSection" class="row g-3 mt-1 d-none">
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-medium" for="declared_value"><?php echo __("Declared Value ($)"); ?></label>
                            <input class="form-control" type="number" min="0" step="0.01" id="declared_value" name="declared_value" value="0.00">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label text-secondary small fw-medium" for="declared_items_desc"><?php echo __("Declared Items Description"); ?></label>
                            <input class="form-control" type="text" id="declared_items_desc" name="declared_items_desc" placeholder="e.g. Macbook Pro, Laptop, Jewelry">
                        </div>
                        <div class="col-12 mt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="waiver_signed" name="waiver_signed" value="1">
                                <label class="form-check-label small text-dark fw-medium" for="waiver_signed">
                                    <span class="text-danger">*</span> <?php echo __("Guest Liability Waiver Accepted"); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="fee_amount"><?php echo __("Fee Amount"); ?></label>
                <input class="form-control" type="number" min="0" step="0.01" id="fee_amount" name="fee_amount" value="0.00">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="payment_status"><?php echo __("Payment Status"); ?></label>
                <select class="form-select" id="payment_status" name="payment_status">
                    <option value="Unpaid"><?php echo __("Unpaid"); ?></option>
                    <option value="Paid"><?php echo __("Paid"); ?></option>
                    <option value="Waived"><?php echo __("Waived"); ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="payment_method"><?php echo __("Payment Method"); ?></label>
                <input class="form-control" type="text" id="payment_method" name="payment_method" placeholder="Cash, card, mobile">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="payment_reference"><?php echo __("Payment Ref"); ?></label>
                <input class="form-control" type="text" id="payment_reference" name="payment_reference">
            </div>
            <div class="col-md-4">
                <?php render_photo_uploader('photo',   'Luggage Photo 1 (Front)', null, null, 1); ?>
            </div>
            <div class="col-md-4">
                <?php render_photo_uploader('photo_2', 'Luggage Photo 2 (Back)',  null, null, 2); ?>
            </div>
            <div class="col-md-4">
                <?php render_photo_uploader('photo_3', 'Luggage Photo 3 (Side)',  null, null, 3); ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="notification_preference"><?php echo __("Notification Preference"); ?></label>
                <select class="form-select" id="notification_preference" name="notification_preference">
                    <option value="None"><?php echo __("None"); ?></option>
                    <option value="WhatsApp">WhatsApp</option>
                    <option value="Email">Email</option>
                    <option value="Both">Both</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label"><?php echo __("Condition Checklist"); ?></label>
                <div class="condition-grid">
                    <?php foreach (condition_options() as $option): ?>
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="condition_flags[]" value="<?php echo h($option); ?>">
                            <span class="form-check-label"><?php echo h(__($option)); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="notes"><?php echo __("Notes"); ?></label>
                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit"><?php echo __("Save and Print Tag"); ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const weightClass = document.getElementById("weight_class");
    const heavySuggestionBox = document.getElementById("heavySuggestionBox");
    
    if (weightClass && heavySuggestionBox) {
        weightClass.addEventListener("change", function () {
            if (this.value === "Heavy") {
                heavySuggestionBox.classList.remove("d-none");
            } else {
                heavySuggestionBox.classList.add("d-none");
            }
        });
    }

    const isHighValue = document.getElementById("is_high_value");
    const highValueSection = document.getElementById("highValueFormSection");
    const waiverSigned = document.getElementById("waiver_signed");
    
    if (isHighValue && highValueSection) {
        isHighValue.addEventListener("change", function () {
            if (this.checked) {
                highValueSection.classList.remove("d-none");
                if (waiverSigned) waiverSigned.required = true;
            } else {
                highValueSection.classList.add("d-none");
                if (waiverSigned) {
                    waiverSigned.required = false;
                    waiverSigned.checked = false;
                }
            }
        });
    }
    const guestSelect = document.getElementById("guest_id");
    const blacklistAlert = document.getElementById("blacklistAlert");
    const blacklistReason = document.getElementById("blacklistReason");

    function checkBlacklist() {
        if (!guestSelect) return;
        const selectedOption = guestSelect.options[guestSelect.selectedIndex];
        if (selectedOption) {
            const isBlacklisted = selectedOption.getAttribute("data-blacklisted") === "1";
            const reason = selectedOption.getAttribute("data-reason");
            if (isBlacklisted) {
                blacklistAlert.classList.remove("d-none");
                blacklistReason.innerText = reason || "No reason specified.";
            } else {
                blacklistAlert.classList.add("d-none");
            }
        }
    }
    if (guestSelect) {
        guestSelect.addEventListener("change", checkBlacklist);
        checkBlacklist();
    }
});
</script>
<?php render_footer(); ?>

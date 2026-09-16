<?php
require_once __DIR__ . "/layout.php";
require_permission("edit");

$guest_id = (int)($_GET["id"] ?? 0);
$guest = db_fetch_one("SELECT * FROM guests WHERE guest_id = ?", "i", [$guest_id]);
if (!$guest) {
    set_flash("warning", "Guest not found.");
    redirect("guests.php");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    try {
        $name = trim($_POST["guest_name"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $room = trim($_POST["room_no"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $id_type = trim($_POST["id_type"] ?? "");
        $id_number = trim($_POST["id_number"] ?? "");
        $id_photo = $guest["id_photo"];

        if (!empty($_POST["remove_id_photo"])) {
            remove_luggage_photo($id_photo);
            $id_photo = null;
        }
        $id_photo = upload_guest_id_photo("id_photo", $id_photo);
        
        $is_vip = isset($_POST["is_vip"]) ? 1 : 0;
        $is_blacklisted = isset($_POST["is_blacklisted"]) ? 1 : 0;
        $blacklist_reason = trim($_POST["blacklist_reason"] ?? "");
        $blacklist_reason = $blacklist_reason !== "" ? $blacklist_reason : null;
        $group_code = trim($_POST["group_code"] ?? "");
        $group_code = $group_code !== "" ? $group_code : null;

        db_execute(
            "UPDATE guests
             SET guest_name = ?, is_vip = ?, is_blacklisted = ?, blacklist_reason = ?, group_code = ?, phone = ?, room_no = ?, email = ?, id_type = ?, id_number = ?, id_photo = ?
             WHERE guest_id = ?",
            "siissssssssi",
            [$name, $is_vip, $is_blacklisted, $blacklist_reason, $group_code, $phone, $room, $email, $id_type, $id_number, $id_photo, $guest_id]
        );
        log_action("Updated guest", "guest", $guest_id, "Guest $name updated");
        set_flash("success", "Guest updated.");
        redirect("guests.php");
    } catch (Throwable $e) {
        set_flash("danger", $e->getMessage());
        redirect("edit_guest.php?id=" . $guest_id);
    }
}

render_header("Edit Guest", "guests");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Edit Guest</h1>
        <p class="text-secondary mb-0"><?php echo h($guest["guest_name"]); ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="guests.php">Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form class="row g-3" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="col-md-6">
                <label class="form-label" for="guest_name">Guest Name</label>
                <input class="form-control" type="text" id="guest_name" name="guest_name" value="<?php echo h($guest["guest_name"]); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" type="text" id="phone" name="phone" value="<?php echo h($guest["phone"]); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="room_no">Room Number</label>
                <input class="form-control" type="text" id="room_no" name="room_no" value="<?php echo h($guest["room_no"]); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" value="<?php echo h($guest["email"]); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="id_type">ID Type</label>
                <select class="form-select" id="id_type" name="id_type">
                    <?php foreach (["" => "None", "Passport" => "Passport", "National ID" => "National ID", "Driving License" => "Driving License", "Hotel Booking ID" => "Hotel Booking ID"] as $value => $label): ?>
                        <option value="<?php echo h($value); ?>" <?php echo $guest["id_type"] === $value ? "selected" : ""; ?>><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="id_number">ID / Passport Number</label>
                <input class="form-control" type="text" id="id_number" name="id_number" value="<?php echo h($guest["id_number"]); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="id_photo">Replace ID Photo</label>
                <input class="form-control" type="file" id="id_photo" name="id_photo" accept="image/*">
                <?php if ($guest["id_photo"]): ?>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="remove_id_photo" name="remove_id_photo" value="1">
                        <label class="form-check-label" for="remove_id_photo">Remove current ID photo</label>
                    </div>
                    <img class="thumbnail mt-2" src="<?php echo h($guest["id_photo"]); ?>" alt="Guest ID photo">
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="group_code">Group Code / Crew ID (Optional)</label>
                <input class="form-control" type="text" id="group_code" name="group_code" value="<?php echo h($guest["group_code"] ?? ""); ?>" placeholder="e.g. EK-072 Crew">
            </div>
            <div class="col-md-3 d-flex align-items-center mt-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_vip" name="is_vip" value="1" <?php echo (int)$guest["is_vip"] === 1 ? "checked" : ""; ?>>
                    <label class="form-check-label fw-bold text-warning" for="is_vip"><i class="bi bi-star-fill me-1"></i>VIP Guest</label>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-center mt-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_blacklisted" name="is_blacklisted" value="1" onchange="toggleBlacklistReason()" <?php echo (int)$guest["is_blacklisted"] === 1 ? "checked" : ""; ?>>
                    <label class="form-check-label fw-bold text-danger" for="is_blacklisted"><i class="bi bi-slash-circle me-1"></i>Blacklisted</label>
                </div>
            </div>
            <div class="col-md-12 <?php echo (int)$guest["is_blacklisted"] === 1 ? "" : "d-none"; ?>" id="blacklistReasonContainer">
                <label class="form-label text-danger" for="blacklist_reason">Blacklist Reason</label>
                <textarea class="form-control border-danger" id="blacklist_reason" name="blacklist_reason" rows="2" placeholder="Describe the reason for blacklisting this guest..."><?php echo h($guest["blacklist_reason"] ?? ""); ?></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Update Guest</button>
            </div>
        </form>
    </div>
</div>
<script>
function toggleBlacklistReason() {
    const chk = document.getElementById("is_blacklisted");
    const container = document.getElementById("blacklistReasonContainer");
    if (chk && container) {
        if (chk.checked) {
            container.classList.remove("d-none");
        } else {
            container.classList.add("d-none");
        }
    }
}
</script>
<?php render_footer(); ?>

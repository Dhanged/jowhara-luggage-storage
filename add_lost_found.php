<?php
require_once __DIR__ . "/layout.php";
require_once __DIR__ . "/photo_uploader.php";
require_permission("lost_found");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    try {
        $name = trim($_POST["item_name"] ?? "");
        $category = trim($_POST["category"] ?? "");
        $desc = trim($_POST["description"] ?? "");
        $condition = trim($_POST["item_condition"] ?? "");
        $estimated_value = max(0, (float)($_POST["estimated_value"] ?? 0));
        $loc = trim($_POST["location_found"] ?? "");
        $secure_location = trim($_POST["secure_location"] ?? "");
        $by = trim($_POST["found_by"] ?? "");
        $finder_contact = trim($_POST["finder_contact"] ?? "");
        $owner_name = trim($_POST["owner_name"] ?? "");
        $owner_phone = trim($_POST["owner_phone"] ?? "");
        $date = trim($_POST["found_date"] ?? "");
        $date = str_replace("T", " ", $date);
        $date = $date !== "" ? $date : date("Y-m-d H:i:s");
        $created_by = (int)current_user()["user_id"];

        if ($name === "") {
            throw new RuntimeException(__("Item Name is required."));
        }

        // Upload lost found item photo
        $photo = null;
        if (!empty($_FILES["photo"]["name"])) {
            $photo = upload_image_file("photo", "uploads/lost_found", null, "Photo");
        }

        db_execute(
            "INSERT INTO lost_found_items
             (reference_no, item_name, category, description, item_condition, estimated_value,
              location_found, secure_location, found_by, finder_contact, found_date, photo_path,
              owner_name, owner_phone, status, created_by)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Found', ?)",
            "ssssdssssssssi",
            [$name, $category, $desc, $condition, $estimated_value, $loc, $secure_location, $by,
                $finder_contact, $date, $photo, $owner_name, $owner_phone, $created_by]
        );

        $insert_id = db_insert_id();
        $reference_no = "LF" . str_pad((string)$insert_id, 6, "0", STR_PAD_LEFT);
        db_execute("UPDATE lost_found_items SET reference_no = ? WHERE item_id = ?", "si", [$reference_no, $insert_id]);
        
        // Log to custody history
        $lfService = new \App\Services\LostFoundService();
        $lfService->appendCustody($insert_id, null, 'Found', "Registered found item: {$name}", $secure_location);
        
        log_action("Logged Lost & Found Item", "lost_found", $insert_id, "Item $name found at $loc by $by");
        set_flash("success", __("Lost and Found registry item added."));
        redirect("lost_found.php");
    } catch (Throwable $e) {
        set_flash("danger", $e->getMessage());
        redirect("add_lost_found.php");
    }
}

render_header("Report Found Item", "lost_found");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Report Found Item"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Register an item found on hotel premises."); ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="lost_found.php"><?php echo __("Back"); ?></a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form class="row g-3" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="col-md-4">
                <label class="form-label" for="item_name"><?php echo __("Item Name"); ?> <span class="text-danger">*</span></label>
                <input class="form-control" type="text" id="item_name" name="item_name" placeholder="e.g. iPhone 15, Leather Wallet, Keys" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="category"><?php echo __("Category"); ?></label>
                <select class="form-select" id="category" name="category">
                    <?php foreach (["Electronics", "Documents", "Wallet / Cash", "Jewelry", "Clothing", "Keys", "Bag", "Other"] as $cat): ?>
                        <option value="<?php echo h($cat); ?>"><?php echo h(__($cat)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="item_condition"><?php echo __("Condition"); ?></label>
                <select class="form-select" id="item_condition" name="item_condition">
                    <?php foreach (["Good", "Damaged", "Wet", "Locked", "Opened", "Unknown"] as $cond): ?>
                        <option value="<?php echo h($cond); ?>"><?php echo h(__($cond)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="location_found"><?php echo __("Location Found"); ?></label>
                <input class="form-control" type="text" id="location_found" name="location_found" placeholder="e.g. Room 302, Gym, Restaurant Pool side">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="secure_location"><?php echo __("Secure Storage Location"); ?></label>
                <input class="form-control" type="text" id="secure_location" name="secure_location" placeholder="e.g. Safe Box 2, LF Cabinet A">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="found_by"><?php echo __("Found By"); ?></label>
                <input class="form-control" type="text" id="found_by" name="found_by" placeholder="e.g. Housekeeping, Guest Name, Receptionist">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="finder_contact"><?php echo __("Finder Contact"); ?></label>
                <input class="form-control" type="text" id="finder_contact" name="finder_contact" placeholder="Phone or department extension">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="found_date"><?php echo __("Found Date"); ?></label>
                <input class="form-control" type="datetime-local" id="found_date" name="found_date" value="<?php echo date("Y-m-d\TH:i"); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="estimated_value"><?php echo __("Estimated Value"); ?></label>
                <input class="form-control" type="number" min="0" step="0.01" id="estimated_value" name="estimated_value" value="0.00">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="owner_name"><?php echo __("Suspected Owner"); ?></label>
                <input class="form-control" type="text" id="owner_name" name="owner_name" placeholder="Guest name if known">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="owner_phone"><?php echo __("Owner Phone"); ?></label>
                <input class="form-control" type="text" id="owner_phone" name="owner_phone" placeholder="Guest phone if known">
            </div>
            <div class="col-md-12">
                <?php render_photo_uploader('photo', 'Item Photo', null, null, 1); ?>
            </div>
            <div class="col-md-12">
                <label class="form-label" for="description"><?php echo __("Description"); ?></label>
                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Describe the item condition, color, brand, serial number, etc..."></textarea>
            </div>
            <div class="col-12 mt-4">
                <button class="btn btn-primary" type="submit"><?php echo __("Save"); ?></button>
            </div>
        </form>
    </div>
</div>
<?php
render_footer();
?>

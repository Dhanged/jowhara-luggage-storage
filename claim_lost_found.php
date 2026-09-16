<?php
require_once __DIR__ . "/layout.php";
require_permission("lost_found");

$item_id = (int)($_GET["id"] ?? 0);
$item = db_fetch_one("SELECT * FROM lost_found_items WHERE item_id = ? AND status = 'Found' AND deleted_at IS NULL", "i", [$item_id]);

if (!$item) {
    set_flash("warning", __("Lost & Found item not found or already claimed."));
    redirect("lost_found.php");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    try {
        $guest_name = trim($_POST["guest_name"] ?? "");
        $guest_phone = trim($_POST["guest_phone"] ?? "");
        $guest_id_number = trim($_POST["guest_id_number"] ?? "");
        $signature_data = $_POST["signature_data"] ?? "";

        if ($guest_name === "") {
            throw new RuntimeException(__("Pickup person name is required."));
        }

        // Mandatory ID validation for high-value items
        $is_high_value = ($item["estimated_value"] >= 100.0) || in_array($item["category"], ["Electronics", "Wallet / Cash", "Jewelry"], true);
        if ($is_high_value && $guest_id_number === "") {
            throw new RuntimeException(__("ID/Passport verification is mandatory for claiming high-value items."));
        }

        // Save signature
        $signature_path = save_signature_image($signature_data);
        if (!$signature_path) {
            throw new RuntimeException(__("Guest signature is required."));
        }

        // Process claim photo upload
        $claim_photo = null;
        if (!empty($_FILES["claim_photo"]["name"])) {
            $claim_photo = upload_image_file("claim_photo", "uploads/claims", null, "Photo");
        }

        global $conn;
        $conn->begin_transaction();

        $lfService = new \App\Services\LostFoundService();
        $lfService->recordClaim(
            $item_id,
            $guest_name,
            $guest_phone,
            $guest_id_number,
            null, // email
            null, // relationship
            $signature_path,
            $claim_photo
        );

        $conn->commit();

        set_flash("success", __("Lost and Found item claimed."));
        redirect("lost_found.php");
    } catch (Throwable $e) {
        if (isset($conn) && $conn->in_transaction) {
            $conn->rollback();
        }
        set_flash("danger", $e->getMessage());
        redirect("claim_lost_found.php?id=" . $item_id);
    }
}

render_header("Process Claim", "lost_found");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Process Claim"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Verify guest details and signature to claim found item."); ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="lost_found.php"><?php echo __("Back"); ?></a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h2 class="section-title"><?php echo __("Item Details"); ?></h2></div>
            <?php if ($item["photo_path"]): ?>
                <img src="<?php echo h($item["photo_path"]); ?>" class="card-img-top" alt="Item photo" style="height: 200px; object-fit: cover;">
            <?php endif; ?>
            <div class="card-body">
                <h3 class="h6 fw-bold text-primary mb-1"><?php echo h($item["item_name"]); ?></h3>
                <p class="text-secondary small mb-3"><?php echo h($item["description"] ?: __("No description provided.")); ?></p>
                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary"><?php echo __("Location Found"); ?></dt>
                    <dd class="col-7 text-dark fw-semibold"><?php echo h($item["location_found"] ?: "-"); ?></dd>
                    <dt class="col-5 text-secondary"><?php echo __("Found By"); ?></dt>
                    <dd class="col-7 text-dark"><?php echo h($item["found_by"] ?: "-"); ?></dd>
                    <dt class="col-5 text-secondary"><?php echo __("Found Date"); ?></dt>
                    <dd class="col-7 text-dark"><?php echo date("Y-m-d H:i", strtotime($item["found_date"])); ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h2 class="section-title"><?php echo __("Claim Verification"); ?></h2></div>
            <div class="card-body">
                <form class="row g-3" method="POST" id="claimForm" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div class="col-md-6">
                        <label class="form-label" for="guest_name"><?php echo __("Pickup Person"); ?> <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="guest_name" name="guest_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="guest_phone"><?php echo __("Pickup Phone"); ?> <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="guest_phone" name="guest_phone" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label" for="guest_id_number"><?php echo __("Pickup ID / Passport Number"); ?></label>
                        <input class="form-control" type="text" id="guest_id_number" name="guest_id_number" placeholder="<?php echo __("Record an ID number when available."); ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label" for="claim_photo"><?php echo __("Claim Visual Validation"); ?></label>
                        <input class="form-control" type="file" id="claim_photo" name="claim_photo" accept="image/*">
                        <div class="form-hint mt-1"><?php echo __("JPG, PNG, WEBP, or GIF. Max 3MB."); ?></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?php echo __("Guest Signature"); ?> <span class="text-danger">*</span></label>
                        <canvas class="signature-pad" id="signaturePad" style="border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; width: 100%; height: 160px; background: rgba(255,255,255,0.02); display: block;"></canvas>
                        <input type="hidden" name="signature_data" id="signatureData">
                        <button class="btn btn-outline-secondary btn-sm mt-2" type="button" id="clearSignature"><?php echo __("Clear Signature"); ?></button>
                    </div>
                    <div class="col-12 mt-4">
                        <button class="btn btn-success" type="submit"><?php echo __("Process Claim"); ?></button>
                    </div>
                </form>
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
        ctx.strokeStyle = "#fff"; // White stroke for dark mode readability
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
    document.getElementById("claimForm").addEventListener("submit", function (event) {
        hidden.value = signed ? canvas.toDataURL("image/png") : "";
        if (!hidden.value) {
            event.preventDefault();
            alert("Signature is required before claiming.");
        }
    });
}());
</script>
<?php
render_footer();
?>

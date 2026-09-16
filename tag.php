<?php
require_once __DIR__ . "/layout.php";
require_login();

$luggage_id = (int)($_GET["id"] ?? 0);
$row = db_fetch_one(
    "SELECT l.*, g.guest_name, g.phone, g.room_no
     FROM luggage l
     JOIN guests g ON g.guest_id = l.guest_id
     WHERE l.luggage_id = ?",
    "i",
    [$luggage_id]
);

if (!$row) {
    set_flash("warning", __("Tag not found."));
    redirect("luggage.php");
}

render_header("Barcode Tag", "luggage");
?>
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Barcode Tag"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Print and attach this tag to the luggage."); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="luggage.php"><?php echo __("Back"); ?></a>
        <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer-fill me-1"></i><?php echo __("Print Tag"); ?></button>
    </div>
</div>

<?php
$is_high_val = (int)$row["is_high_value"] === 1;
$tag_style = $is_high_val ? 'border: 4px solid #d4af37 !important; box-shadow: 0 10px 40px rgba(212, 175, 55, 0.2) !important; background: linear-gradient(to bottom, #ffffff, #fffdf3); position: relative;' : '';
?>
<section class="print-sheet tag-sheet text-center shadow-sm border p-4" style="<?php echo $tag_style; ?>">
    <?php if ($is_high_val): ?>
        <div class="mb-3">
            <span class="badge text-bg-danger text-uppercase px-3 py-2 border border-danger fw-bold" style="font-size: 0.8rem;">
                <i class="bi bi-shield-fill-exclamation me-1"></i><?php echo __("High Value Declared"); ?>
            </span>
        </div>
    <?php endif; ?>
    <img src="assets/logo.jpg" alt="Jowhara Hotel Logo" class="mb-2" style="height: 48px; width: auto; border-radius: 4px;">
    <h2 class="h5 fw-bold mb-1">Jowhara International Hotel</h2>
    <div class="text-secondary small mb-4"><?php echo __("Luggage Storage Tag"); ?></div>

    <div class="display-6 fw-bold mb-3"><?php echo h($row["tag_code"]); ?></div>
    <div class="d-flex justify-content-center mb-3">
        <?php echo render_code39($row["tag_code"]); ?>
    </div>

    <div class="my-4">
        <img class="qr-img" src="<?php echo h(qr_url(luggage_qr_payload($row))); ?>" alt="QR code" style="width: 140px; height: 140px;">
    </div>

    <dl class="row text-start mb-0 border-top pt-4">
        <dt class="col-5 text-secondary small text-uppercase fw-bold"><?php echo __("Guests"); ?></dt><dd class="col-7 fw-semibold"><?php echo h($row["guest_name"]); ?></dd>
        <dt class="col-5 text-secondary small text-uppercase fw-bold"><?php echo __("Room"); ?></dt><dd class="col-7 fw-semibold"><?php echo h($row["room_no"] ?: "-"); ?></dd>
        <dt class="col-5 text-secondary small text-uppercase fw-bold"><?php echo __("Booking"); ?></dt><dd class="col-7 fw-semibold"><?php echo h($row["reservation_no"] ?: "-"); ?></dd>
        <dt class="col-5 text-secondary small text-uppercase fw-bold"><?php echo __("Type"); ?></dt><dd class="col-7 fw-semibold"><?php echo h($row["luggage_type"]); ?> x<?php echo (int)$row["quantity"]; ?></dd>
        <dt class="col-5 text-secondary small text-uppercase fw-bold"><?php echo __("Location"); ?></dt><dd class="col-7 fw-semibold"><?php echo h(trim(($row["storage_zone"] ?? "") . " " . ($row["storage_location"] ?? ""))); ?></dd>
        <dt class="col-5 text-secondary small text-uppercase fw-bold"><?php echo __("Weight Class"); ?></dt><dd class="col-7 fw-semibold"><?php echo h(__($row["weight_class"])); ?><?php echo $row["weight_kg"] !== null ? " (" . h($row["weight_kg"]) . " kg)" : ""; ?></dd>
        <?php if ($row["security_seal_code"]): ?>
            <dt class="col-5 text-secondary small text-uppercase fw-bold"><?php echo __("Security Seal Tag Code"); ?></dt><dd class="col-7 fw-semibold"><span class="badge text-bg-secondary"><i class="bi bi-shield-lock me-1"></i><?php echo h($row["security_seal_code"]); ?></span></dd>
        <?php endif; ?>
        <?php if ($is_high_val): ?>
            <dt class="col-5 text-secondary small text-uppercase fw-bold"><?php echo __("Declared Value ($)"); ?></dt><dd class="col-7 fw-bold text-danger">$<?php echo h(money($row["declared_value"])); ?></dd>
        <?php endif; ?>
        <dt class="col-5 text-secondary small text-uppercase fw-bold"><?php echo __("Expected"); ?></dt><dd class="col-7 fw-semibold"><?php echo h($row["expected_pickup_date"] ?: "-"); ?></dd>
        <dt class="col-5 text-secondary small text-uppercase fw-bold"><?php echo __("Status"); ?></dt><dd class="col-7"><span class="badge text-bg-<?php echo h(status_badge($row["status"])); ?>"><?php echo h(__($row["status"])); ?></span></dd>
    </dl>
</section>
<?php render_footer(); ?>

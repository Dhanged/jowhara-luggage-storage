<?php
require_once __DIR__ . "/layout.php";
require_login();

$ids = array_values(array_filter(array_map("intval", explode(",", $_GET["ids"] ?? ""))));
if (!$ids) {
    set_flash("warning", "No tags selected.");
    redirect("luggage.php");
}

$rows = [];
foreach ($ids as $id) {
    $row = db_fetch_one(
        "SELECT l.*, g.guest_name, g.room_no
         FROM luggage l JOIN guests g ON g.guest_id = l.guest_id
         WHERE l.luggage_id = ?",
        "i",
        [$id]
    );
    if ($row) {
        $rows[] = $row;
    }
}

render_header("Bulk Tags", "luggage");
?>
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h1 class="h3 fw-bold mb-1">Bulk Tags</h1>
        <p class="text-secondary mb-0">Print multiple barcode tags in one batch.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="luggage.php">Back</a>
        <button class="btn btn-primary" type="button" onclick="window.print()">Print Tags</button>
    </div>
</div>

<?php foreach ($rows as $row): ?>
    <section class="print-sheet tag-sheet text-center mb-4">
        <h2 class="h4 fw-bold mb-1">Luggage Storage Tag</h2>
        <div class="display-6 fw-bold mb-3"><?php echo h($row["tag_code"]); ?></div>
        <?php echo render_code39($row["tag_code"]); ?>
        <div class="my-4"><img class="qr-img" src="<?php echo h(qr_url(luggage_qr_payload($row))); ?>" alt="QR code"></div>
        <dl class="row text-start mb-0">
            <dt class="col-5">Guest</dt><dd class="col-7"><?php echo h($row["guest_name"]); ?></dd>
            <dt class="col-5">Room</dt><dd class="col-7"><?php echo h($row["room_no"]); ?></dd>
            <dt class="col-5">Type</dt><dd class="col-7"><?php echo h($row["luggage_type"]); ?> x<?php echo (int)$row["quantity"]; ?></dd>
            <dt class="col-5">Location</dt><dd class="col-7"><?php echo h(trim(($row["storage_zone"] ?? "") . " " . ($row["storage_location"] ?? ""))); ?></dd>
        </dl>
    </section>
<?php endforeach; ?>
<?php render_footer(); ?>

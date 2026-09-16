<?php
require_once __DIR__ . "/layout.php";
require_login();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";
    $ids = array_values(array_filter(array_map("intval", $_POST["selected"] ?? [])));

    if (!$ids) {
        set_flash("warning", "Select at least one luggage record.");
        redirect("luggage.php");
    }

    if ($action === "bulk_tags") {
        redirect("bulk_tags.php?ids=" . implode(",", $ids));
    }

    if ($action === "bulk_archive") {
        require_permission("soft_delete");
        foreach ($ids as $id) {
            db_execute("UPDATE luggage SET deleted_at = NOW() WHERE luggage_id = ?", "i", [$id]);
            log_action("Archived luggage", "luggage", $id, "Bulk archive");
        }
        set_flash("success", count($ids) . " luggage record(s) archived.");
        redirect("luggage.php");
    }

    if ($action === "bulk_restore") {
        require_permission("restore");
        foreach ($ids as $id) {
            db_execute("UPDATE luggage SET deleted_at = NULL WHERE luggage_id = ?", "i", [$id]);
            log_action("Restored luggage", "luggage", $id, "Bulk restore");
        }
        set_flash("success", count($ids) . " luggage record(s) restored.");
        redirect("luggage.php?archive=only");
    }

    if ($action === "bulk_checkout") {
        require_permission("checkout");
        $user_id = (int)current_user()["user_id"];
        foreach ($ids as $id) {
            db_execute(
                "UPDATE luggage SET status = 'Collected', checkout_date = NOW(), checked_out_by = ?
                 WHERE luggage_id = ? AND status = 'Stored' AND deleted_at IS NULL",
                "ii",
                [$user_id, $id]
            );
            log_action("Checked out luggage", "luggage", $id, "Bulk checkout");
        }
        set_flash("success", "Selected stored luggage checked out.");
        redirect("luggage.php?status=Collected");
    }
}

$search = trim($_GET["search"] ?? "");
$status = $_GET["status"] ?? "";
$room = trim($_GET["room"] ?? "");
$zone = trim($_GET["zone"] ?? "");
$type = trim($_GET["type"] ?? "");
$payment = $_GET["payment"] ?? "";
$start = trim($_GET["start"] ?? "");
$end = trim($_GET["end"] ?? "");
$overdue = $_GET["overdue"] ?? "";
$archive = $_GET["archive"] ?? "";

$where = [];
$types = "";
$params = [];

if ($archive === "only") {
    $where[] = "l.deleted_at IS NOT NULL";
} elseif ($archive !== "with") {
    $where[] = "l.deleted_at IS NULL";
}

if ($search !== "") {
    $like = "%" . $search . "%";
    $where[] = "(l.tag_code LIKE ? OR g.guest_name LIKE ? OR g.phone LIKE ? OR g.room_no LIKE ? OR l.luggage_type LIKE ? OR l.color LIKE ? OR l.storage_location LIKE ? OR l.reservation_no LIKE ?)";
    $types .= "ssssssss";
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}
if (in_array($status, ["Stored", "Collected"], true)) {
    $where[] = "l.status = ?";
    $types .= "s";
    $params[] = $status;
}
if ($room !== "") {
    $where[] = "g.room_no LIKE ?";
    $types .= "s";
    $params[] = "%" . $room . "%";
}
if ($zone !== "") {
    $where[] = "(l.storage_zone LIKE ? OR l.storage_location LIKE ?)";
    $types .= "ss";
    $params[] = "%" . $zone . "%";
    $params[] = "%" . $zone . "%";
}
if ($type !== "") {
    $where[] = "l.luggage_type LIKE ?";
    $types .= "s";
    $params[] = "%" . $type . "%";
}
if (in_array($payment, ["Paid", "Unpaid", "Waived"], true)) {
    $where[] = "l.payment_status = ?";
    $types .= "s";
    $params[] = $payment;
}
if ($start !== "") {
    $where[] = "DATE(l.checkin_date) >= ?";
    $types .= "s";
    $params[] = $start;
}
if ($end !== "") {
    $where[] = "DATE(l.checkin_date) <= ?";
    $types .= "s";
    $params[] = $end;
}
if ($overdue === "1") {
    $where[] = "l.status = 'Stored' AND l.expected_pickup_date IS NOT NULL AND l.expected_pickup_date < CURDATE()";
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
$rows = db_fetch_all(
    "SELECT l.*, g.guest_name, g.phone, g.room_no, g.email, u.username AS checkout_user
     FROM luggage l
     JOIN guests g ON g.guest_id = l.guest_id
     LEFT JOIN users u ON u.user_id = l.checked_out_by
     $where_sql
     ORDER BY l.deleted_at IS NOT NULL, l.luggage_id DESC",
    $types,
    $params
);

if (($_GET["scan"] ?? "") === "1" && count($rows) === 1) {
    $target = $rows[0]["status"] === "Stored" ? "checkout.php?id=" : "receipt.php?id=";
    redirect($target . (int)$rows[0]["luggage_id"]);
}

if (isset($_GET["ajax"])) {
    ob_start();
    foreach ($rows as $row) {
        $is_overdue = $row["status"] === "Stored" && $row["expected_pickup_date"] && $row["expected_pickup_date"] < date("Y-m-d");
        ?>
        <tr>
            <td><input class="form-check-input row-check" type="checkbox" name="selected[]" value="<?php echo (int)$row["luggage_id"]; ?>"></td>
            <td>
                <?php if ($row["photo"]): ?>
                    <img class="thumbnail" src="<?php echo h($row["photo"]); ?>" alt="Luggage photo">
                <?php else: ?>
                    <span class="text-secondary small">No photo</span>
                <?php endif; ?>
            </td>
            <td class="fw-semibold">
                <?php echo h($row["tag_code"]); ?>
                <?php if ($row["reservation_no"]): ?><div class="small text-secondary">Booking <?php echo h($row["reservation_no"]); ?></div><?php endif; ?>
            </td>
            <td>
                <?php echo h($row["guest_name"]); ?><br>
                <span class="text-secondary small">Room <?php echo h($row["room_no"]); ?> | <?php echo h($row["phone"]); ?></span>
            </td>
            <td><?php echo h($row["luggage_type"]); ?> x<?php echo (int)$row["quantity"]; ?><br><span class="text-secondary small"><?php echo h($row["color"]); ?></span></td>
            <td>
                <?php echo h($row["storage_zone"]); ?> <?php echo h($row["storage_location"]); ?>
                <?php if ($is_overdue): ?><div class="badge text-bg-danger mt-1">Overdue</div><?php endif; ?>
            </td>
            <td>
                <span class="badge text-bg-<?php echo $row["payment_status"] === "Paid" ? "success" : ($row["payment_status"] === "Waived" ? "secondary" : "warning text-dark"); ?>"><?php echo h($row["payment_status"]); ?></span>
                <div class="small text-secondary">$<?php echo h(money($row["fee_amount"])); ?></div>
            </td>
            <td>
                <span class="badge text-bg-<?php echo h(status_badge($row["status"])); ?>"><?php echo h($row["status"]); ?></span>
                <?php if ($row["deleted_at"]): ?><div class="badge text-bg-secondary mt-1">Archived</div><?php endif; ?>
                <?php if ($row["checkout_user"]): ?><div class="text-secondary small">by <?php echo h($row["checkout_user"]); ?></div><?php endif; ?>
            </td>
            <td>
                <div class="table-actions justify-content-end">
                    <a class="btn btn-outline-secondary btn-sm" href="print_label.php?id=<?php echo (int)$row["luggage_id"]; ?>" target="_blank" title="Print Thermal Label"><i class="bi bi-printer"></i> Tag</a>
                    <a class="btn btn-outline-primary btn-sm" href="print_receipt.php?id=<?php echo (int)$row["luggage_id"]; ?>" target="_blank" title="Print Thermal Receipt"><i class="bi bi-receipt"></i> Receipt</a>
                    <a class="btn btn-outline-info btn-sm" href="custody_log.php?id=<?php echo (int)$row["luggage_id"]; ?>"><i class="bi bi-clock-history"></i> <?php echo __("Custody"); ?></a>
                    <?php if (user_can("edit")): ?>
                        <a class="btn btn-outline-dark btn-sm" href="edit_luggage.php?id=<?php echo (int)$row["luggage_id"]; ?>"><?php echo __("Edit"); ?></a>
                    <?php endif; ?>
                    <?php if ($row["status"] === "Stored" && !$row["deleted_at"] && user_can("checkout")): ?>
                        <a class="btn btn-success btn-sm" href="checkout.php?id=<?php echo (int)$row["luggage_id"]; ?>">Checkout</a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
    }
    if (!$rows) {
        echo '<tr><td colspan="9"><div class="empty-state">No luggage records found.</div></td></tr>';
    }
    $html = ob_get_clean();
    header("Content-Type: application/json");
    echo json_encode(["html" => $html, "count" => count($rows)]);
    exit;
}

render_header("Luggage", "luggage");
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Luggage"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Advanced search, scanner lookup, bulk actions, checkout, and receipt printing."); ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="scanner.php"><?php echo __("Open Scanner"); ?></a>
        <?php if (user_can("create")): ?>
            <a class="btn btn-primary" href="add_luggage.php"><?php echo __("Add Luggage"); ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-lg-4">
                <label class="form-label" for="search">Search / Barcode</label>
                <input class="form-control" type="search" id="search" name="search" value="<?php echo h($search); ?>" placeholder="Tag, guest, phone, room, type, location, booking">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="Stored" <?php echo $status === "Stored" ? "selected" : ""; ?>>Stored</option>
                    <option value="Collected" <?php echo $status === "Collected" ? "selected" : ""; ?>>Collected</option>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="room">Room</label>
                <input class="form-control" type="text" id="room" name="room" value="<?php echo h($room); ?>">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="zone">Zone/Shelf</label>
                <input class="form-control" type="text" id="zone" name="zone" value="<?php echo h($zone); ?>">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="type">Type</label>
                <input class="form-control" type="text" id="type" name="type" value="<?php echo h($type); ?>">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="payment">Payment</label>
                <select class="form-select" id="payment" name="payment">
                    <option value="">All</option>
                    <?php foreach (["Unpaid", "Paid", "Waived"] as $pay): ?>
                        <option value="<?php echo h($pay); ?>" <?php echo $payment === $pay ? "selected" : ""; ?>><?php echo h($pay); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="start">From</label>
                <input class="form-control" type="date" id="start" name="start" value="<?php echo h($start); ?>">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="end">To</label>
                <input class="form-control" type="date" id="end" name="end" value="<?php echo h($end); ?>">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="archive">Archive</label>
                <select class="form-select" id="archive" name="archive">
                    <option value="" <?php echo $archive === "" ? "selected" : ""; ?>>Active</option>
                    <option value="with" <?php echo $archive === "with" ? "selected" : ""; ?>>Active + Archived</option>
                    <option value="only" <?php echo $archive === "only" ? "selected" : ""; ?>>Archived Only</option>
                </select>
            </div>
            <div class="col-lg-2 d-flex align-items-end">
                <label class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="overdue" value="1" <?php echo $overdue === "1" ? "checked" : ""; ?>>
                    <span class="form-check-label">Overdue only</span>
                </label>
            </div>
            <div class="col-lg-2 d-grid align-items-end">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" id="bulkForm">
    <?php echo csrf_field(); ?>
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="section-title"><span id="luggageCount"><?php echo count($rows); ?></span> luggage record(s)</h2>
            <div class="d-flex flex-wrap gap-2">
                <select class="form-select form-select-sm" name="action" required>
                    <option value="">Bulk Action</option>
                    <option value="bulk_tags">Print Tags</option>
                    <?php if (user_can("checkout")): ?><option value="bulk_checkout">Checkout Stored</option><?php endif; ?>
                    <?php if (user_can("soft_delete")): ?><option value="bulk_archive">Archive</option><?php endif; ?>
                    <?php if (user_can("restore")): ?><option value="bulk_restore">Restore</option><?php endif; ?>
                </select>
                <button class="btn btn-outline-primary btn-sm" type="submit">Apply</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th><input class="form-check-input" type="checkbox" id="selectAll"></th>
                        <th><?php echo __("Photo"); ?></th>
                        <th><?php echo __("Tag"); ?></th>
                        <th><?php echo __("Guest"); ?></th>
                        <th><?php echo __("Type"); ?></th>
                        <th><?php echo __("Storage"); ?></th>
                        <th><?php echo __("Payment"); ?></th>
                        <th><?php echo __("Status"); ?></th>
                        <th class="text-end"><?php echo __("Action"); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $is_overdue = $row["status"] === "Stored" && $row["expected_pickup_date"] && $row["expected_pickup_date"] < date("Y-m-d"); ?>
                    <tr>
                        <td><input class="form-check-input row-check" type="checkbox" name="selected[]" value="<?php echo (int)$row["luggage_id"]; ?>"></td>
                        <td>
                            <?php if ($row["photo"]): ?>
                                <img class="thumbnail" src="<?php echo h($row["photo"]); ?>" alt="Luggage photo">
                            <?php else: ?>
                                <span class="text-secondary small">No photo</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold">
                            <?php echo h($row["tag_code"]); ?>
                            <?php if ($row["reservation_no"]): ?><div class="small text-secondary">Booking <?php echo h($row["reservation_no"]); ?></div><?php endif; ?>
                        </td>
                        <td>
                            <?php echo h($row["guest_name"]); ?><br>
                            <span class="text-secondary small">Room <?php echo h($row["room_no"]); ?> | <?php echo h($row["phone"]); ?></span>
                        </td>
                        <td><?php echo h($row["luggage_type"]); ?> x<?php echo (int)$row["quantity"]; ?><br><span class="text-secondary small"><?php echo h($row["color"]); ?></span></td>
                        <td>
                            <?php echo h($row["storage_zone"]); ?> <?php echo h($row["storage_location"]); ?>
                            <?php if ($is_overdue): ?><div class="badge text-bg-danger mt-1">Overdue</div><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge text-bg-<?php echo $row["payment_status"] === "Paid" ? "success" : ($row["payment_status"] === "Waived" ? "secondary" : "warning text-dark"); ?>"><?php echo h($row["payment_status"]); ?></span>
                            <div class="small text-secondary">$<?php echo h(money($row["fee_amount"])); ?></div>
                        </td>
                        <td>
                            <span class="badge text-bg-<?php echo h(status_badge($row["status"])); ?>"><?php echo h($row["status"]); ?></span>
                            <?php if ($row["deleted_at"]): ?><div class="badge text-bg-secondary mt-1">Archived</div><?php endif; ?>
                            <?php if ($row["checkout_user"]): ?><div class="text-secondary small">by <?php echo h($row["checkout_user"]); ?></div><?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions justify-content-end">
                                <a class="btn btn-outline-secondary btn-sm" href="print_label.php?id=<?php echo (int)$row["luggage_id"]; ?>" target="_blank" title="Print Thermal Label"><i class="bi bi-printer"></i> Tag</a>
                                <a class="btn btn-outline-primary btn-sm" href="print_receipt.php?id=<?php echo (int)$row["luggage_id"]; ?>" target="_blank" title="Print Thermal Receipt"><i class="bi bi-receipt"></i> Receipt</a>
                                <a class="btn btn-outline-info btn-sm" href="custody_log.php?id=<?php echo (int)$row["luggage_id"]; ?>"><i class="bi bi-clock-history"></i> <?php echo __("Custody"); ?></a>
                                <?php if (user_can("edit")): ?>
                                    <a class="btn btn-outline-dark btn-sm" href="edit_luggage.php?id=<?php echo (int)$row["luggage_id"]; ?>"><?php echo __("Edit"); ?></a>
                                <?php endif; ?>
                                <?php if ($row["status"] === "Stored" && !$row["deleted_at"] && user_can("checkout")): ?>
                                    <a class="btn btn-success btn-sm" href="checkout.php?id=<?php echo (int)$row["luggage_id"]; ?>">Checkout</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="9"><div class="empty-state">No luggage records found.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const searchForm = document.querySelector('form[method="GET"]');
    const tbody = document.querySelector('tbody');
    const countSpan = document.getElementById('luggageCount');
    const selectAllCheckbox = document.getElementById('selectAll');

    function rebindSelectAll() {
        selectAllCheckbox?.addEventListener("change", function () {
            document.querySelectorAll(".row-check").forEach((box) => { box.checked = this.checked; });
        });
    }
    rebindSelectAll();

    if (searchForm && tbody) {
        let debounceTimer;

        function performSearch() {
            const formData = new FormData(searchForm);
            // Include checkbox state for overdue if checked
            const overdueCheckbox = searchForm.querySelector('input[name="overdue"]');
            if (overdueCheckbox && !overdueCheckbox.checked) {
                formData.delete('overdue');
            }
            
            const params = new URLSearchParams(formData);
            params.set('ajax', '1');

            fetch(`luggage.php?${params.toString()}`)
                .then(r => r.json())
                .then(data => {
                    tbody.innerHTML = data.html;
                    if (countSpan) {
                        countSpan.innerText = data.count;
                    }
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = false;
                    }
                    rebindSelectAll();
                })
                .catch(err => console.error("Search failed:", err));
        }

        searchForm.querySelectorAll('input[type="search"], input[type="text"]').forEach(input => {
            input.addEventListener("input", function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(performSearch, 250);
            });
        });

        searchForm.querySelectorAll('select, input[type="date"], input[type="checkbox"]').forEach(input => {
            input.addEventListener("change", performSearch);
        });

        searchForm.addEventListener("submit", function (e) {
            e.preventDefault();
            performSearch();
        });
    }
});
</script>
<?php render_footer(); ?>

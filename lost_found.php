<?php
require_once __DIR__ . "/layout.php";
require_permission("lost_found");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";
    $item_id = (int)($_POST["item_id"] ?? 0);

    if ($action === "dispose" && $item_id > 0) {
        // Restrict authorization to manager/admin
        if (!in_array(current_user()["role"], ["admin", "manager"], true)) {
            set_flash("danger", __("Only managers or administrators are authorized to approve item disposal."));
            redirect("lost_found.php");
        }

        $reason = trim($_POST["disposal_reason"] ?? "");
        if ($reason === "") {
            set_flash("warning", __("Disposal reason is required."));
            redirect("lost_found.php");
        }

        $lfService = new \App\Services\LostFoundService();
        $lfService->recordDisposal($item_id, 'Discarded', $reason);

        set_flash("success", __("Lost and Found item marked as disposed."));
        redirect("lost_found.php");
    }
}

$search = trim($_GET["search"] ?? "");
$status = $_GET["status"] ?? "";
$category = trim($_GET["category"] ?? "");
$location = trim($_GET["location"] ?? "");
$sort = $_GET["sort"] ?? "date_desc";
$start = trim($_GET["start"] ?? "");
$end = trim($_GET["end"] ?? "");

$where = ["l.deleted_at IS NULL"];
$params = [];
$types = "";

if ($search !== "") {
    $where[] = "(l.reference_no LIKE ? OR l.item_name LIKE ? OR l.description LIKE ? OR l.location_found LIKE ? OR l.secure_location LIKE ? OR l.owner_name LIKE ? OR l.owner_phone LIKE ? OR l.guest_name LIKE ? OR l.guest_phone LIKE ?)";
    $search_param = "%" . $search . "%";
    for ($i = 0; $i < 9; $i++) {
        $params[] = $search_param;
    }
    $types .= "sssssssss";
}

if (in_array($status, ["Found", "Claimed", "Disposed"], true)) {
    $where[] = "l.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($category !== "") {
    $where[] = "l.category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($location !== "") {
    $where[] = "l.location_found = ?";
    $params[] = $location;
    $types .= "s";
}

if ($start !== "") {
    $where[] = "DATE(l.found_date) >= ?";
    $params[] = $start;
    $types .= "s";
}

if ($end !== "") {
    $where[] = "DATE(l.found_date) <= ?";
    $params[] = $end;
    $types .= "s";
}

$where_sql = "WHERE " . implode(" AND ", $where);

$order_by = "ORDER BY FIELD(l.status, 'Found', 'Claimed', 'Disposed'), l.found_date DESC, l.item_id DESC";
if ($sort === "date_asc") $order_by = "ORDER BY l.found_date ASC";
elseif ($sort === "value_desc") $order_by = "ORDER BY l.estimated_value DESC";
elseif ($sort === "status") $order_by = "ORDER BY l.status ASC, l.found_date DESC";

$items = db_fetch_all(
    "SELECT l.*, u.username AS claim_user, creator.username AS created_user
     FROM lost_found_items l
     LEFT JOIN users u ON u.user_id = l.claimed_by_user_id
     LEFT JOIN users creator ON creator.user_id = l.created_by
     $where_sql
     $order_by",
    $types,
    $params
);

$stats = db_fetch_one(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'Found') AS found_count,
        SUM(status = 'Claimed') AS claimed_count,
        SUM(status = 'Disposed') AS disposed_count,
        COALESCE(SUM(estimated_value), 0) AS estimated_total
     FROM lost_found_items
     WHERE deleted_at IS NULL"
);
$categories = db_fetch_all(
    "SELECT category, COUNT(*) AS total
     FROM lost_found_items
     WHERE deleted_at IS NULL AND category IS NOT NULL AND category <> ''
     GROUP BY category
     ORDER BY category"
);
$locations = db_fetch_all(
    "SELECT location_found, COUNT(*) AS total
     FROM lost_found_items
     WHERE deleted_at IS NULL AND location_found IS NOT NULL AND location_found <> ''
     GROUP BY location_found
     ORDER BY location_found"
);

render_header("Lost & Found", "lost_found");
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Lost & Found Control Desk"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Register, secure, claim, dispose, and audit guest property from one controlled workflow."); ?></p>
    </div>
    <a class="btn btn-primary d-flex align-items-center gap-1" href="add_lost_found.php">
        <i class="bi bi-plus-circle-fill"></i> <?php echo __("Report Found Item"); ?>
    </a>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        [__("Total Items"), (int)($stats["total"] ?? 0), "bi-archive", "primary"],
        [__("Currently Secured"), (int)($stats["found_count"] ?? 0), "bi-shield-lock", "warning"],
        [__("Returned"), (int)($stats["claimed_count"] ?? 0), "bi-check-circle", "success"],
        [__("Disposed"), (int)($stats["disposed_count"] ?? 0), "bi-x-octagon", "danger"],
    ] as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-xl-3">
            <div class="card metric-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <div class="text-secondary small fw-medium"><?php echo h($label); ?></div>
                        <div class="metric-value mt-2 fw-bold"><?php echo h($value); ?></div>
                    </div>
                    <i class="bi <?php echo h($icon); ?> text-<?php echo h($color); ?> fs-2"></i>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-2" method="GET" action="lost_found.php">
            <div class="col-lg-3">
                <label class="form-label" for="search"><?php echo __("Search"); ?></label>
                <input class="form-control" type="search" id="search" name="search" placeholder="<?php echo __("Reference, item, owner, phone, location"); ?>" value="<?php echo h($search); ?>">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="status"><?php echo __("Status"); ?></label>
                <select class="form-select" id="status" name="status">
                    <option value=""><?php echo __("All"); ?></option>
                    <?php foreach (["Found", "Claimed", "Disposed"] as $st): ?>
                        <option value="<?php echo h($st); ?>" <?php echo $status === $st ? "selected" : ""; ?>><?php echo h(__($st)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="category"><?php echo __("Category"); ?></label>
                <select class="form-select" id="category" name="category">
                    <option value=""><?php echo __("All"); ?></option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo h($cat["category"]); ?>" <?php echo $category === $cat["category"] ? "selected" : ""; ?>>
                            <?php echo h(__($cat["category"])); ?> (<?php echo (int)$cat["total"]; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="location"><?php echo __("Location"); ?></label>
                <select class="form-select" id="location" name="location">
                    <option value=""><?php echo __("All"); ?></option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo h($loc["location_found"]); ?>" <?php echo $location === $loc["location_found"] ? "selected" : ""; ?>>
                            <?php echo h(__($loc["location_found"])); ?> (<?php echo (int)$loc["total"]; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="sort"><?php echo __("Sort By"); ?></label>
                <select class="form-select" id="sort" name="sort">
                    <option value="date_desc" <?php echo $sort === "date_desc" ? "selected" : ""; ?>><?php echo __("Newest First"); ?></option>
                    <option value="date_asc" <?php echo $sort === "date_asc" ? "selected" : ""; ?>><?php echo __("Oldest First"); ?></option>
                    <option value="value_desc" <?php echo $sort === "value_desc" ? "selected" : ""; ?>><?php echo __("Highest Value"); ?></option>
                    <option value="status" <?php echo $sort === "status" ? "selected" : ""; ?>><?php echo __("Status"); ?></option>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="start"><?php echo __("From"); ?></label>
                <input class="form-control" type="date" id="start" name="start" value="<?php echo h($start); ?>">
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="end"><?php echo __("To"); ?></label>
                <input class="form-control" type="date" id="end" name="end" value="<?php echo h($end); ?>">
            </div>
            <div class="col-lg-1 d-grid align-items-end">
                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-transparent border-bottom p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="section-title mb-0"><?php echo count($items); ?> <?php echo __("Lost & Found Records"); ?></h2>
        <div class="text-secondary small"><?php echo __("Estimated held value"); ?>: <strong>$<?php echo h(money($stats["estimated_total"] ?? 0)); ?></strong></div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th><?php echo __("Photo"); ?></th>
                    <th><?php echo __("Item"); ?></th>
                    <th><?php echo __("Custody"); ?></th>
                    <th><?php echo __("Owner / Claim"); ?></th>
                    <th><?php echo __("Status"); ?></th>
                    <th class="text-end"><?php echo __("Action"); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td style="width:84px;">
                        <?php if ($item["photo_path"]): ?>
                            <img class="thumbnail" src="<?php echo h($item["photo_path"]); ?>" alt="Found item photo">
                        <?php else: ?>
                            <div class="thumbnail d-flex align-items-center justify-content-center bg-light"><i class="bi bi-camera text-secondary"></i></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="fw-bold text-dark"><?php echo h($item["item_name"]); ?></div>
                        <div class="small text-primary fw-semibold"><?php echo h($item["reference_no"] ?: "LF" . str_pad((string)$item["item_id"], 6, "0", STR_PAD_LEFT)); ?></div>
                        <div class="small text-secondary">
                            <?php echo h(__($item["category"] ?: "Other")); ?>
                            <?php if ($item["item_condition"]): ?> Â· <?php echo h(__($item["item_condition"])); ?><?php endif; ?>
                            <?php if ((float)$item["estimated_value"] > 0): ?> Â· $<?php echo h(money($item["estimated_value"])); ?><?php endif; ?>
                        </div>
                        <?php if ($item["description"]): ?>
                            <div class="small text-secondary mt-1"><?php echo h($item["description"]); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="small"><strong><?php echo __("Found"); ?>:</strong> <?php echo h($item["location_found"] ?: "-"); ?></div>
                        <div class="small"><strong><?php echo __("Secured"); ?>:</strong> <?php echo h($item["secure_location"] ?: "-"); ?></div>
                        <div class="small text-secondary"><?php echo h(date("Y-m-d H:i", strtotime($item["found_date"]))); ?> Â· <?php echo h($item["found_by"] ?: $item["created_user"] ?: "-"); ?></div>
                    </td>
                    <td>
                        <?php if ($item["status"] === "Claimed"): ?>
                            <div class="fw-semibold"><?php echo h($item["guest_name"]); ?></div>
                            <div class="small text-secondary"><?php echo h($item["guest_phone"] ?: "-"); ?></div>
                            <?php if (!empty($item["claimant_id_number"])): ?>
                                <div class="small text-secondary"><?php echo __("ID"); ?>: <?php echo h($item["claimant_id_number"]); ?></div>
                            <?php endif; ?>
                            <div class="small text-success"><?php echo h($item["claimed_date"]); ?> Â· <?php echo h($item["claim_user"] ?: "-"); ?></div>
                        <?php else: ?>
                            <div class="fw-semibold"><?php echo h($item["owner_name"] ?: __("Unknown")); ?></div>
                            <div class="small text-secondary"><?php echo h($item["owner_phone"] ?: "-"); ?></div>
                            <?php if ($item["status"] === "Disposed"): ?>
                                <div class="small text-danger"><?php echo h($item["disposal_date"]); ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?php echo h(lost_found_badge_class($item["status"])); ?>"><?php echo h(__($item["status"])); ?></span></td>
                    <td>
                        <div class="table-actions justify-content-end">
                            <?php if ($item["status"] === "Found"): ?>
                                <a class="btn btn-success btn-sm" href="claim_lost_found.php?id=<?php echo (int)$item["item_id"]; ?>">
                                    <i class="bi bi-check-circle"></i> <?php echo __("Claim"); ?>
                                </a>
                                <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#disposeModal-<?php echo (int)$item["item_id"]; ?>">
                                    <i class="bi bi-x-octagon"></i>
                                </button>
                            <?php elseif ($item["status"] === "Claimed" && ($item["signature_path"] || $item["claim_photo_path"])): ?>
                                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#claimModal-<?php echo (int)$item["item_id"]; ?>">
                                    <i class="bi bi-eye"></i> <?php echo __("Receipt"); ?>
                                </button>
                            <?php else: ?>
                                <span class="text-secondary small">-</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <div class="modal fade" id="disposeModal-<?php echo (int)$item["item_id"]; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="dispose">
                                <input type="hidden" name="item_id" value="<?php echo (int)$item["item_id"]; ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title"><?php echo __("Dispose Item"); ?>: <?php echo h($item["item_name"]); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <label class="form-label" for="disposal_reason_<?php echo (int)$item["item_id"]; ?>"><?php echo __("Reason"); ?></label>
                                    <textarea class="form-control" id="disposal_reason_<?php echo (int)$item["item_id"]; ?>" name="disposal_reason" rows="3" required></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __("Cancel"); ?></button>
                                    <button type="submit" class="btn btn-danger"><?php echo __("Confirm Disposal"); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($item["status"] === "Claimed"): ?>
                    <div class="modal fade" id="claimModal-<?php echo (int)$item["item_id"]; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><?php echo h($item["reference_no"]); ?> Â· <?php echo __("Claim Receipt"); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <div class="text-start small text-secondary mb-3">
                                        <strong><?php echo __("Item"); ?>:</strong> <?php echo h($item["item_name"]); ?><br>
                                        <strong><?php echo __("Claimed By"); ?>:</strong> <?php echo h($item["guest_name"]); ?> (<?php echo h($item["guest_phone"]); ?>)<br>
                                        <?php if (!empty($item["claimant_id_number"])): ?><strong><?php echo __("ID"); ?>:</strong> <?php echo h($item["claimant_id_number"]); ?><br><?php endif; ?>
                                        <strong><?php echo __("Processed By"); ?>:</strong> <?php echo h($item["claim_user"] ?: "-"); ?><br>
                                        <strong><?php echo __("Date"); ?>:</strong> <?php echo h($item["claimed_date"]); ?>
                                    </div>
                                    <?php if ($item["claim_photo_path"]): ?>
                                        <img class="img-fluid rounded border shadow-sm mb-3" style="max-height: 220px; object-fit: cover;" src="<?php echo h($item["claim_photo_path"]); ?>" alt="Claim visual verification">
                                    <?php endif; ?>
                                    <?php if ($item["signature_path"]): ?>
                                        <img class="img-fluid rounded border bg-light" style="max-height: 110px; max-width: 320px;" src="<?php echo h($item["signature_path"]); ?>" alt="Claim signature">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$items): ?>
                <tr><td colspan="6"><div class="empty-state"><?php echo __("No lost and found records match your filters."); ?></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_footer(); ?>

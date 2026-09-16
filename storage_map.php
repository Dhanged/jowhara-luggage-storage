<?php
require_once __DIR__ . "/layout.php";
require_login();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    require_permission("shelves");
    $zone = trim($_POST["zone_name"] ?? "");
    $shelf = trim($_POST["shelf_name"] ?? "");
    $capacity = max(1, (int)($_POST["capacity"] ?? 10));
    if ($zone !== "" && $shelf !== "") {
        db_execute(
            "INSERT INTO storage_shelves (zone_name, shelf_name, capacity, status) VALUES (?, ?, ?, 'Active')",
            "ssi",
            [$zone, $shelf, $capacity]
        );
        log_action("Created shelf", "shelf", db_insert_id(), "$zone $shelf capacity $capacity");
        set_flash("success", "Shelf added.");
    }
    redirect("storage_map.php");
}

$shelves = db_fetch_all(
    "SELECT s.*,
        COUNT(l.luggage_id) AS used_count,
        SUM(CASE WHEN l.expected_pickup_date IS NOT NULL AND l.expected_pickup_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count
     FROM storage_shelves s
     LEFT JOIN luggage l ON l.storage_location = s.shelf_name AND l.status = 'Stored' AND l.deleted_at IS NULL
     WHERE s.status = 'Active'
     GROUP BY s.shelf_id
     ORDER BY s.zone_name, s.shelf_name"
);

$unmapped = db_fetch_all(
    "SELECT storage_zone, storage_location, COUNT(*) AS used_count
     FROM luggage
     WHERE status = 'Stored' AND deleted_at IS NULL
       AND (storage_location IS NULL OR storage_location = '' OR storage_location NOT IN (SELECT shelf_name FROM storage_shelves WHERE status = 'Active'))
     GROUP BY storage_zone, storage_location
     ORDER BY storage_zone, storage_location"
);

render_header("Storage Map", "storage");
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Storage Shelf Map"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("See shelf capacity, stored bags, and overdue storage locations."); ?></p>
    </div>
    <a class="btn btn-outline-primary" href="luggage.php?status=Stored"><?php echo __("Stored Luggage"); ?></a>
</div>

<!-- Storage Map Color Legend -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-1 small text-secondary fw-semibold">
            <i class="bi bi-info-circle me-1"></i><?php echo __("Capacity status and occupancy indicators:"); ?>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="d-flex align-items-center gap-1 small fw-bold">
                <span class="d-inline-block rounded-circle bg-success border" style="width: 12px; height: 12px;"></span>
                <span class="text-success"><?php echo __("Available (Empty)"); ?></span>
            </span>
            <span class="d-flex align-items-center gap-1 small fw-bold">
                <span class="d-inline-block rounded-circle bg-warning border" style="width: 12px; height: 12px;"></span>
                <span class="text-warning"><?php echo __("Reserved / Partial"); ?></span>
            </span>
            <span class="d-flex align-items-center gap-1 small fw-bold">
                <span class="d-inline-block rounded-circle bg-danger border" style="width: 12px; height: 12px;"></span>
                <span class="text-danger"><?php echo __("Occupied (Full)"); ?></span>
            </span>
        </div>
    </div>
</div>

<?php if (user_can("shelves")): ?>
<div class="card mb-3">
    <div class="card-header"><h2 class="section-title">Add Shelf</h2></div>
    <div class="card-body">
        <form class="row g-2" method="POST">
            <?php echo csrf_field(); ?>
            <div class="col-md-3"><input class="form-control" type="text" name="zone_name" placeholder="Zone, e.g. A" required></div>
            <div class="col-md-3"><input class="form-control" type="text" name="shelf_name" placeholder="Shelf, e.g. A-5" required></div>
            <div class="col-md-3"><input class="form-control" type="number" min="1" name="capacity" value="10" required></div>
            <div class="col-md-3 d-grid"><button class="btn btn-primary" type="submit">Add Shelf</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="shelf-grid mb-4">
    <?php foreach ($shelves as $shelf): ?>
        <?php
            $used = (int)$shelf["used_count"];
            $capacity = max(1, (int)$shelf["capacity"]);
            $percent = min(100, round(($used / $capacity) * 100));
            
            if ($used === 0) {
                $class = "available";
                $badge_color = "success";
            } elseif ($used >= $capacity) {
                $class = "occupied";
                $badge_color = "danger";
            } else {
                $class = "reserved";
                $badge_color = "warning text-dark";
            }
        ?>
        <div class="shelf-card <?php echo h($class); ?>" onclick="openShelfDetails('<?php echo h($shelf['shelf_name']); ?>', <?php echo $used; ?>, <?php echo $capacity; ?>)">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-bold text-dark"><?php echo h($shelf["shelf_name"]); ?></div>
                    <div class="text-secondary small">Zone <?php echo h($shelf["zone_name"]); ?></div>
                </div>
                <span class="badge text-bg-<?php echo $badge_color; ?>"><?php echo $used; ?>/<?php echo $capacity; ?></span>
            </div>
            <div class="progress progress-thin mt-3" role="progressbar" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-<?php echo $badge_color === 'warning text-dark' ? 'warning' : $badge_color; ?>" style="width: <?php echo $percent; ?>%"></div>
            </div>
            <?php if ((int)$shelf["overdue_count"] > 0): ?>
                <div class="badge text-bg-danger mt-3"><?php echo (int)$shelf["overdue_count"]; ?> overdue</div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($unmapped): ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-transparent"><h2 class="section-title">Unmapped Stored Luggage</h2></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Zone</th><th>Location</th><th>Stored</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($unmapped as $row): ?>
                <tr>
                    <td><?php echo h($row["storage_zone"] ?: "-"); ?></td>
                    <td><?php echo h($row["storage_location"] ?: "No shelf"); ?></td>
                    <td><?php echo (int)$row["used_count"]; ?></td>
                    <td class="text-end"><a class="btn btn-outline-secondary btn-sm" href="luggage.php?status=Stored&search=<?php echo urlencode((string)$row["storage_location"]); ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Shelf Details Modal -->
<div class="modal fade" id="shelfModal" tabindex="-1" aria-labelledby="shelfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="shelfModalLabel"><i class="bi bi-box-seam text-primary me-2"></i>Shelf Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary">Capacity status: <strong id="modalCapacityText" class="text-dark"></strong></span>
                    <a id="viewAllBtn" href="#" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-right-short"></i> View in list</a>
                </div>
                <div id="modalLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="modalContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openShelfDetails(shelfName, used, capacity) {
    const modal = new bootstrap.Modal(document.getElementById('shelfModal'));
    document.getElementById('shelfModalLabel').innerHTML = `<i class="bi bi-box-seam text-primary me-2"></i>Shelf: ${shelfName}`;
    document.getElementById('modalCapacityText').innerText = `${used} / ${capacity} bags stored`;
    document.getElementById('viewAllBtn').href = `luggage.php?status=Stored&search=${encodeURIComponent(shelfName)}`;

    const loading = document.getElementById('modalLoading');
    const content = document.getElementById('modalContent');

    loading.classList.remove('d-none');
    content.innerHTML = '';

    modal.show();

    if (used === 0) {
        loading.classList.add('d-none');
        content.innerHTML = '<div class="alert alert-info my-2"><i class="bi bi-info-circle me-2"></i>This shelf is currently empty.</div>';
        return;
    }

    fetch(`ajax_shelf_luggage.php?shelf=${encodeURIComponent(shelfName)}`)
        .then(response => response.json())
        .then(data => {
            loading.classList.add('d-none');
            if (data.error) {
                content.innerHTML = `<div class="alert alert-danger my-2">${data.error}</div>`;
                return;
            }
            if (data.length === 0) {
                content.innerHTML = '<div class="alert alert-info my-2">No active bags found.</div>';
                return;
            }

            let html = `
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Guest</th>
                            <th>Luggage Type</th>
                            <th>Checkin Date</th>
                            <th>Pickup Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.forEach(item => {
                const overdueClass = (item.expected_pickup_date && new Date(item.expected_pickup_date) < new Date(new Date().setHours(0,0,0,0))) ? 'text-danger fw-bold' : '';
                html += `
                    <tr>
                        <td class="fw-semibold text-primary"><a href="receipt.php?id=${item.luggage_id}" class="text-decoration-none">${item.tag_code}</a></td>
                        <td>
                            <div class="fw-bold">${item.guest_name}</div>
                            <small class="text-secondary">Room ${item.room_no}</small>
                        </td>
                        <td>${item.luggage_type} (x${item.quantity})</td>
                        <td><small>${item.checkin_date}</small></td>
                        <td class="${overdueClass}">${item.expected_pickup_date || '-'}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary me-1" href="receipt.php?id=${item.luggage_id}">Receipt</a>
                            <a class="btn btn-sm btn-success" href="checkout.php?id=${item.luggage_id}">Checkout</a>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table></div>';
            content.innerHTML = html;
        })
        .catch(err => {
            loading.classList.add('d-none');
            content.innerHTML = `<div class="alert alert-danger my-2">Failed to load data. Error: ${err}</div>`;
        });
}
</script>
<?php render_footer(); ?>

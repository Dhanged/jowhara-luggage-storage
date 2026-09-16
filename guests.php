<?php
require_once __DIR__ . "/layout.php";
require_login();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";
    $guest_id = (int)($_POST["guest_id"] ?? 0);

    if ($action === "delete") {
        require_permission("soft_delete");
        db_execute("UPDATE guests SET deleted_at = NOW() WHERE guest_id = ?", "i", [$guest_id]);
        db_execute("UPDATE luggage SET deleted_at = NOW() WHERE guest_id = ? AND deleted_at IS NULL", "i", [$guest_id]);
        log_action("Archived guest", "guest", $guest_id, "Guest and linked luggage archived");
        set_flash("success", "Guest archived. You can restore it from archive view.");
        redirect("guests.php");
    }

    if ($action === "restore") {
        require_permission("restore");
        db_execute("UPDATE guests SET deleted_at = NULL WHERE guest_id = ?", "i", [$guest_id]);
        log_action("Restored guest", "guest", $guest_id, "Guest restored");
        set_flash("success", "Guest restored.");
        redirect("guests.php?archive=only");
    }
}

if (isset($_GET["history_guest_id"])) {
    $gid = (int)$_GET["history_guest_id"];
    $history = db_fetch_all(
        "SELECT l.*, u.username AS checkout_username
         FROM luggage l
         LEFT JOIN users u ON u.user_id = l.checked_out_by
         WHERE l.guest_id = ? AND l.deleted_at IS NULL
         ORDER BY l.luggage_id DESC",
        "i",
        [$gid]
    );
    header("Content-Type: application/json");
    echo json_encode($history);
    exit;
}

$search = trim($_GET["search"] ?? "");
$archive = $_GET["archive"] ?? "";
$where = [];
$types = "";
$params = [];

if ($archive === "only") {
    $where[] = "g.deleted_at IS NOT NULL";
} elseif ($archive !== "with") {
    $where[] = "g.deleted_at IS NULL";
}

if ($search !== "") {
    $like = "%" . $search . "%";
    $where[] = "(g.guest_name LIKE ? OR g.phone LIKE ? OR g.room_no LIKE ? OR g.email LIKE ? OR g.id_number LIKE ?)";
    $types .= "sssss";
    array_push($params, $like, $like, $like, $like, $like);
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
$guests = db_fetch_all(
    "SELECT g.*,
        SUM(CASE WHEN l.deleted_at IS NULL THEN 1 ELSE 0 END) AS luggage_count,
        SUM(CASE WHEN l.deleted_at IS NULL AND l.status = 'Stored' THEN 1 ELSE 0 END) AS stored_count
     FROM guests g
     LEFT JOIN luggage l ON l.guest_id = g.guest_id
     $where_sql
     GROUP BY g.guest_id
     ORDER BY g.guest_id DESC",
    $types,
    $params
);

if (isset($_GET["ajax"])) {
    foreach ($guests as $guest) {
        ?>
        <tr>
            <td>
                <?php if ($guest["id_photo"]): ?>
                    <img class="thumbnail" src="<?php echo h(secure_file_url($guest["id_photo"])); ?>" alt="Guest ID photo" loading="lazy">
                <?php else: ?>
                    <span class="text-secondary small"><?php echo h($guest["id_type"] ?: "No ID"); ?></span>
                <?php endif; ?>
                <div class="small text-secondary"><?php echo h($guest["id_number"]); ?></div>
            </td>
            <td class="fw-semibold">
                <a href="#" class="guest-name-link text-decoration-none text-dark fw-bold" onclick="openGuestHistory(<?php echo $guest['guest_id']; ?>, '<?php echo h($guest['guest_name']); ?>'); return false;">
                    <?php echo h($guest["guest_name"]); ?>
                </a>
                <?php if ($guest["is_vip"]): ?>
                    <span class="badge badge-vip ms-1"><i class="bi bi-star-fill me-1"></i>VIP</span>
                <?php endif; ?>
                <?php if ($guest["is_blacklisted"]): ?>
                    <span class="badge badge-blacklisted ms-1" title="<?php echo h($guest["blacklist_reason"]); ?>"><i class="bi bi-slash-circle me-1"></i>Blacklisted</span>
                <?php endif; ?>
                <?php if ($guest["group_code"]): ?>
                    <div class="small text-secondary mt-1"><i class="bi bi-people me-1"></i>Group: <span class="badge bg-light text-dark border"><?php echo h($guest["group_code"]); ?></span></div>
                <?php endif; ?>
            </td>
            <td><?php echo h($guest["phone"]); ?><br><span class="text-secondary small"><?php echo h($guest["email"]); ?></span></td>
            <td><?php echo h($guest["room_no"]); ?></td>
            <td><?php echo (int)$guest["luggage_count"]; ?> <span class="text-secondary small">(Stored <?php echo (int)$guest["stored_count"]; ?>)</span></td>
            <td>
                <?php if ($guest["deleted_at"]): ?>
                    <span class="badge text-bg-secondary">Archived</span>
                <?php else: ?>
                    <span class="badge text-bg-success">Active</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="table-actions justify-content-end">
                    <?php if (!$guest["deleted_at"] && user_can("create")): ?>
                        <a class="btn btn-outline-success btn-sm" href="add_luggage.php?guest_id=<?php echo (int)$guest["guest_id"]; ?>">Add Luggage</a>
                    <?php endif; ?>
                    <?php if (user_can("edit")): ?>
                        <a class="btn btn-outline-primary btn-sm" href="edit_guest.php?id=<?php echo (int)$guest["guest_id"]; ?>">Edit</a>
                    <?php endif; ?>
                    <?php if (!$guest["deleted_at"] && user_can("soft_delete")): ?>
                        <form method="POST" onsubmit="return confirm('Archive this guest and linked luggage?')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="guest_id" value="<?php echo (int)$guest["guest_id"]; ?>">
                            <button class="btn btn-outline-danger btn-sm" type="submit">Archive</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($guest["deleted_at"] && user_can("restore")): ?>
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="restore">
                            <input type="hidden" name="guest_id" value="<?php echo (int)$guest["guest_id"]; ?>">
                            <button class="btn btn-outline-success btn-sm" type="submit">Restore</button>
                        </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
    }
    if (!$guests) {
        echo '<tr><td colspan="7"><div class="empty-state">No guests found.</div></td></tr>';
    }
    exit;
}

render_header("Guests", "guests");
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Guests</h1>
        <p class="text-secondary mb-0">Search guests, store ID details, and archive safely.</p>
    </div>
    <?php if (user_can("create")): ?>
        <a class="btn btn-primary" href="add_guest.php">Add Guest</a>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-lg-8">
                <input class="form-control" type="search" name="search" value="<?php echo h($search); ?>" placeholder="Search name, phone, room, email, or ID number">
            </div>
            <div class="col-lg-2">
                <select class="form-select" name="archive">
                    <option value="" <?php echo $archive === "" ? "selected" : ""; ?>>Active</option>
                    <option value="with" <?php echo $archive === "with" ? "selected" : ""; ?>>Active + Archived</option>
                    <option value="only" <?php echo $archive === "only" ? "selected" : ""; ?>>Archived Only</option>
                </select>
            </div>
            <div class="col-lg-2 d-grid">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Room</th>
                    <th>Luggage</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($guests as $guest): ?>
                <tr>
                    <td>
                        <?php if ($guest["id_photo"]): ?>
                            <img class="thumbnail" src="<?php echo h($guest["id_photo"]); ?>" alt="Guest ID photo">
                        <?php else: ?>
                            <span class="text-secondary small"><?php echo h($guest["id_type"] ?: "No ID"); ?></span>
                        <?php endif; ?>
                        <div class="small text-secondary"><?php echo h($guest["id_number"]); ?></div>
                    </td>
                    <td class="fw-semibold">
                        <a href="#" class="guest-name-link text-decoration-none text-dark fw-bold" onclick="openGuestHistory(<?php echo $guest['guest_id']; ?>, '<?php echo h($guest['guest_name']); ?>'); return false;">
                            <?php echo h($guest["guest_name"]); ?>
                        </a>
                        <?php if ($guest["is_vip"]): ?>
                            <span class="badge bg-warning text-dark ms-1" style="background-color: #fbbf24 !important;"><i class="bi bi-star-fill me-1"></i>VIP</span>
                        <?php endif; ?>
                        <?php if ($guest["is_blacklisted"]): ?>
                            <span class="badge bg-danger ms-1" title="<?php echo h($guest["blacklist_reason"]); ?>"><i class="bi bi-slash-circle me-1"></i>Blacklisted</span>
                        <?php endif; ?>
                        <?php if ($guest["group_code"]): ?>
                            <div class="small text-secondary mt-1"><i class="bi bi-people me-1"></i>Group: <span class="badge bg-light text-dark border"><?php echo h($guest["group_code"]); ?></span></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo h($guest["phone"]); ?><br><span class="text-secondary small"><?php echo h($guest["email"]); ?></span></td>
                    <td><?php echo h($guest["room_no"]); ?></td>
                    <td><?php echo (int)$guest["luggage_count"]; ?> <span class="text-secondary small">(Stored <?php echo (int)$guest["stored_count"]; ?>)</span></td>
                    <td>
                        <?php if ($guest["deleted_at"]): ?>
                            <span class="badge text-bg-secondary">Archived</span>
                        <?php else: ?>
                            <span class="badge text-bg-success">Active</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="table-actions justify-content-end">
                            <?php if (!$guest["deleted_at"] && user_can("create")): ?>
                                <a class="btn btn-outline-success btn-sm" href="add_luggage.php?guest_id=<?php echo (int)$guest["guest_id"]; ?>">Add Luggage</a>
                            <?php endif; ?>
                            <?php if (user_can("edit")): ?>
                                <a class="btn btn-outline-primary btn-sm" href="edit_guest.php?id=<?php echo (int)$guest["guest_id"]; ?>">Edit</a>
                            <?php endif; ?>
                            <?php if (!$guest["deleted_at"] && user_can("soft_delete")): ?>
                                <form method="POST" onsubmit="return confirm('Archive this guest and linked luggage?')">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="guest_id" value="<?php echo (int)$guest["guest_id"]; ?>">
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Archive</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($guest["deleted_at"] && user_can("restore")): ?>
                                <form method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="guest_id" value="<?php echo (int)$guest["guest_id"]; ?>">
                                    <button class="btn btn-outline-success btn-sm" type="submit">Restore</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$guests): ?>
                <tr><td colspan="7"><div class="empty-state">No guests found.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Guest History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="historyModalLabel"><i class="bi bi-clock-history text-primary me-2"></i>Guest Luggage History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="historyLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="historyContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.querySelector('input[name="search"]');
    const archiveSelect = document.querySelector('select[name="archive"]');
    const tbody = document.querySelector('tbody');

    if (searchInput && tbody) {
        let debounceTimer;

        function performSearch() {
            const query = searchInput.value;
            const archive = archiveSelect ? archiveSelect.value : "";

            fetch(`guests.php?ajax=1&search=${encodeURIComponent(query)}&archive=${encodeURIComponent(archive)}`)
                .then(r => r.text())
                .then(html => {
                    tbody.innerHTML = html;
                });
        }

        searchInput.addEventListener("input", function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(performSearch, 250);
        });

        if (archiveSelect) {
            archiveSelect.addEventListener("change", performSearch);
        }

        const searchForm = searchInput.closest("form");
        if (searchForm) {
            searchForm.addEventListener("submit", function (e) {
                e.preventDefault();
                performSearch();
            });
        }
    }
});

function openGuestHistory(guestId, guestName) {
    const modal = new bootstrap.Modal(document.getElementById('historyModal'));
    document.getElementById('historyModalLabel').innerHTML = `<i class="bi bi-clock-history text-primary me-2"></i>Guest History: ${guestName}`;
    
    const loading = document.getElementById('historyLoading');
    const content = document.getElementById('historyContent');
    
    loading.classList.remove('d-none');
    content.innerHTML = '';
    
    modal.show();
    
    fetch(`guests.php?history_guest_id=${guestId}`)
        .then(response => response.json())
        .then(data => {
            loading.classList.add('d-none');
            if (data.length === 0) {
                content.innerHTML = '<div class="alert alert-info my-2">No luggage records found for this guest.</div>';
                return;
            }
            
            let html = `
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Luggage Type</th>
                            <th>Location</th>
                            <th>Check-in Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.forEach(item => {
                let statusBadge = '';
                if (item.status === 'Stored') {
                    statusBadge = '<span class="badge text-bg-warning">Stored</span>';
                } else {
                    statusBadge = '<span class="badge text-bg-success">Collected</span>';
                }
                
                html += `
                    <tr>
                        <td class="fw-semibold text-primary">${item.tag_code}</td>
                        <td>${item.luggage_type} (x${item.quantity})</td>
                        <td>${item.storage_zone || ''} ${item.storage_location || ''}</td>
                        <td><small>${item.checkin_date}</small></td>
                        <td>${statusBadge}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="receipt.php?id=${item.luggage_id}">Receipt</a>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            content.innerHTML = html;
        })
        .catch(err => {
            loading.classList.add('d-none');
            content.innerHTML = `<div class="alert alert-danger my-2">Failed to load history data.</div>`;
        });
}
</script>
<?php render_footer(); ?>

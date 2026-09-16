<?php
require_once __DIR__ . "/layout.php";
require_admin();

// Check if table exists
try {
    db_scalar("SELECT 1 FROM announcements LIMIT 1");
    $has_table = true;
} catch (Throwable $e) {
    $has_table = false;
}

if ($has_table && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $type = $_POST['type'] ?? 'info';
        $expires_at = $_POST['expires_at'] ?? null;
        if (empty($expires_at)) $expires_at = null;
        
        if (!in_array($type, ['info', 'warning', 'danger', 'success'])) $type = 'info';

        if ($title === '') {
            set_flash("danger", "Title is required.");
        } else {
            db_execute(
                "INSERT INTO announcements (title, body, type, created_by, expires_at) VALUES (?, ?, ?, ?, ?)",
                "sssis",
                [$title, $body, $type, (int)$_SESSION['user_id'], $expires_at]
            );
            $id = db_insert_id();
            log_action("Created announcement", "announcement", $id, "Title: $title");
            push_notification_all("New Announcement: " . $title, $body, "", "announcement");
            set_flash("success", "Announcement created successfully and pushed to all users.");
        }
        redirect("admin_announcements.php");
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db_execute("UPDATE announcements SET deleted_at = NOW() WHERE id = ?", "i", [$id]);
        log_action("Deleted announcement", "announcement", $id, "Soft deleted announcement ID $id");
        set_flash("success", "Announcement deleted.");
        redirect("admin_announcements.php");
    }
}

$announcements = [];
$total = 0;
$active = 0;
$expired = 0;

if ($has_table) {
    $announcements = db_fetch_all(
        "SELECT a.*, u.username as creator_name 
         FROM announcements a 
         LEFT JOIN users u ON u.user_id = a.created_by 
         WHERE a.deleted_at IS NULL 
         ORDER BY a.created_at DESC"
    );
    
    $total = count($announcements);
    $now = date('Y-m-d H:i:s');
    foreach ($announcements as $a) {
        if ($a['expires_at'] && $a['expires_at'] < $now) {
            $expired++;
        } else {
            $active++;
        }
    }
}

render_header("Announcements Management", "admin_announcements");

$badges = [
    'info' => 'primary',
    'warning' => 'warning text-dark',
    'danger' => 'danger',
    'success' => 'success'
];
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <span class="admin-page-icon"><i class="bi bi-megaphone-fill"></i></span>
            System Announcements
        </h1>
        <p class="text-secondary mb-0">Manage global broadcast banners shown to all users.</p>
    </div>
    <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddAnnouncement">
        <i class="bi bi-plus-lg"></i> New Announcement
    </button>
</div>

<?php if (!$has_table): ?>
<div class="alert alert-warning">The announcements table has not been created in the database yet.</div>
<?php else: ?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ["Total", $total, "#3b82f6", "bi-megaphone-fill"],
        ["Active", $active, "#10b981", "bi-check-circle-fill"],
        ["Expired", $expired, "#94a3b8", "bi-clock-history"],
    ] as [$lbl, $val, $clr, $ico]): ?>
    <div class="col-12 col-md-4">
        <div class="card admin-stat-card" style="--sc:#<?= ltrim($clr,'#') ?>; border-left:4px solid <?= $clr ?>;">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="admin-stat-icon" style="background:<?= $clr ?>20; color:<?= $clr ?>;"><i class="bi <?= $ico ?>"></i></div>
                <div>
                    <div class="admin-stat-label"><?= $lbl ?></div>
                    <div class="admin-stat-value"><?= $val ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom py-3">
        <h2 class="section-title mb-0">All Announcements</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Type</th>
                    <th>Title & Content</th>
                    <th>Status / Expiry</th>
                    <th>Created By</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($announcements as $a): 
                    $badge = $badges[$a['type']] ?? 'secondary';
                    $now = date('Y-m-d H:i:s');
                    $is_expired = $a['expires_at'] && $a['expires_at'] < $now;
                ?>
                <tr>
                    <td><span class="badge bg-<?= $badge ?> fs-6"><i class="bi bi-info-circle me-1"></i><?= ucfirst($a['type']) ?></span></td>
                    <td>
                        <div class="fw-bold mb-1"><?= h($a['title']) ?></div>
                        <div class="text-muted small text-truncate" style="max-width:300px;"><?= h($a['body']) ?></div>
                    </td>
                    <td>
                        <?php if ($is_expired): ?>
                            <span class="badge bg-secondary text-white">Expired</span>
                            <div class="small text-muted mt-1"><?= h($a['expires_at']) ?></div>
                        <?php else: ?>
                            <span class="badge bg-success">Active</span>
                            <?php if ($a['expires_at']): ?>
                                <div class="small text-muted mt-1">Ends <?= h($a['expires_at']) ?></div>
                            <?php else: ?>
                                <div class="small text-muted mt-1">Never expires</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= h($a['creator_name']) ?></div>
                        <div class="small text-muted"><?= date('M j, Y', strtotime($a['created_at'])) ?></div>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalDelete"
                                data-id="<?= $a['id'] ?>" data-title="<?= h($a['title']) ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($announcements)): ?>
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        <div style="font-size:2rem; margin-bottom:10px;"><i class="bi bi-megaphone"></i></div>
                        No announcements found.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add -->
<div class="modal fade" id="modalAddAnnouncement" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required maxlength="200" placeholder="e.g. System Maintenance Tomorrow">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Message Body</label>
                        <textarea class="form-control" name="body" rows="3" placeholder="Additional details..."></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Banner Type</label>
                            <select class="form-select" name="type">
                                <option value="info">Info (Blue)</option>
                                <option value="success">Success (Green)</option>
                                <option value="warning">Warning (Yellow)</option>
                                <option value="danger">Danger (Red)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Expires At (Optional)</label>
                            <input type="datetime-local" class="form-control" name="expires_at">
                        </div>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i> This will push a notification to all active users and show a banner at the top of the screen.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create & Broadcast</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Delete -->
<div class="modal fade" id="modalDelete" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-trash me-2"></i>Delete Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delId">
                <div class="modal-body">
                    Are you sure you want to delete the announcement: <br>
                    <strong id="delTitle"></strong>?
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('modalDelete').addEventListener('show.bs.modal', function(e) {
    var btn = e.relatedTarget;
    document.getElementById('delId').value = btn.dataset.id;
    document.getElementById('delTitle').textContent = btn.dataset.title;
});
</script>
<?php endif; ?>

<style>
.admin-page-icon {
    width:40px; height:40px; border-radius:12px;
    background:var(--brand-light); color:var(--brand);
    display:inline-flex; align-items:center; justify-content:center; font-size:1.2rem;
}
.admin-stat-card { border-radius:14px; box-shadow:var(--shadow-card); }
.admin-stat-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.admin-stat-label { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-muted); }
.admin-stat-value { font-size:1.6rem; font-weight:800; color:var(--ink); line-height:1; }
</style>

<?php render_footer(); ?>

<?php
require_once __DIR__ . "/layout.php";
require_login();

$user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Check if table exists
try {
    db_scalar("SELECT 1 FROM user_notifications LIMIT 1");
    $has_notifs = true;
} catch (Throwable $e) {
    $has_notifs = false;
}

if ($has_notifs && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    if ($action === 'mark_read') {
        $id = (int)($_POST['notif_id'] ?? 0);
        db_execute("UPDATE user_notifications SET read_at = NOW() WHERE notif_id = ? AND user_id = ?", "ii", [$id, $user_id]);
    } elseif ($action === 'mark_all_read') {
        db_execute("UPDATE user_notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL", "i", [$user_id]);
        set_flash("success", __("All notifications marked as read."));
    } elseif ($action === 'delete') {
        $id = (int)($_POST['notif_id'] ?? 0);
        db_execute("UPDATE user_notifications SET is_dismissed = 1, read_at = NOW() WHERE notif_id = ? AND user_id = ?", "ii", [$id, $user_id]);
    }
    
    redirect("notifications_center.php");
}

$filter = $_GET['filter'] ?? 'all';
$where = "user_id = $user_id AND is_dismissed = 0";
if ($filter === 'unread') {
    $where .= " AND read_at IS NULL";
} elseif (in_array($filter, ['overdue', 'capacity', 'handover', 'announcement'])) {
    $where .= " AND type = '" . db_execute("SELECT ?", "s", [$filter])->get_result()->fetch_column() . "'"; // Escape implicitly or safely hardcode
    // Actually better to just use proper binding for type
    $type_filter = $filter;
}

$notifications = [];
if ($has_notifs) {
    $sql = "SELECT * FROM user_notifications WHERE user_id = ? AND is_dismissed = 0";
    $types = "i";
    $params = [$user_id];
    
    if ($filter === 'unread') {
        $sql .= " AND read_at IS NULL";
    } elseif (in_array($filter, ['overdue', 'capacity', 'handover', 'announcement'])) {
        $sql .= " AND type = ?";
        $types .= "s";
        $params[] = $filter;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT 100";
    $notifications = db_fetch_all($sql, $types, $params);
}

render_header(__("Notification Center"), "notifications");

$icons = [
    'info' => 'bi-info-circle text-primary',
    'warning' => 'bi-exclamation-triangle text-warning',
    'danger' => 'bi-x-octagon text-danger',
    'success' => 'bi-check-circle text-success',
    'overdue' => 'bi-clock-history text-danger',
    'capacity' => 'bi-boxes text-warning',
    'handover' => 'bi-arrow-left-right text-info',
    'announcement' => 'bi-megaphone text-primary'
];

$borders = [
    'info' => 'border-primary',
    'warning' => 'border-warning',
    'danger' => 'border-danger',
    'success' => 'border-success',
    'overdue' => 'border-danger',
    'capacity' => 'border-warning',
    'handover' => 'border-info',
    'announcement' => 'border-primary'
];
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <span class="admin-page-icon"><i class="bi bi-bell-fill"></i></span>
            <?= __("Notification Center") ?>
        </h1>
        <p class="text-secondary mb-0"><?= __("View and manage your alerts and updates.") ?></p>
    </div>
    <?php if ($has_notifs): ?>
    <form method="POST" class="d-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
            <i class="bi bi-check2-all"></i> <?= __("Mark All Read") ?>
        </button>
    </form>
    <?php endif; ?>
</div>

<ul class="nav nav-pills mb-4 gap-2">
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'all' ? 'active' : 'bg-white border' ?>" href="?filter=all"><?= __("All") ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'unread' ? 'active' : 'bg-white border' ?>" href="?filter=unread"><?= __("Unread") ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'announcement' ? 'active' : 'bg-white border' ?>" href="?filter=announcement"><?= __("Announcements") ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'overdue' ? 'active' : 'bg-white border' ?>" href="?filter=overdue"><?= __("Overdue") ?></a>
    </li>
</ul>

<?php if (empty($notifications)): ?>
<div class="card shadow-sm border-0 py-5 text-center">
    <div class="card-body">
        <div style="font-size:3rem; color:var(--bs-gray-300); mb-3"><i class="bi bi-bell-slash"></i></div>
        <h4 class="fw-bold text-muted"><?= __("No notifications") ?></h4>
        <p class="text-secondary mb-0"><?= __("You're all caught up!") ?></p>
    </div>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($notifications as $n): 
        $is_unread = is_null($n['read_at']);
        $icon = $icons[$n['type']] ?? $icons['info'];
        $border = $borders[$n['type']] ?? $borders['info'];
    ?>
    <div class="card shadow-sm border-0 border-start border-4 <?= $border ?> <?= $is_unread ? 'bg-light' : '' ?>">
        <div class="card-body p-3 d-flex gap-3 align-items-start">
            <div class="fs-4 mt-1"><i class="bi <?= $icon ?>"></i></div>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h5 class="mb-0 fs-6 <?= $is_unread ? 'fw-bold' : 'fw-medium text-secondary' ?>">
                        <?= h(__($n['title'])) ?>
                        <?php if ($is_unread): ?><span class="badge bg-danger ms-2" style="font-size:0.65rem">NEW</span><?php endif; ?>
                    </h5>
                    <small class="text-muted"><?= time_ago($n['created_at']) ?></small>
                </div>
                <p class="mb-2 text-secondary small"><?= nl2br(h(__($n['body']))) ?></p>
                
                <div class="d-flex gap-2 align-items-center mt-2">
                    <?php if ($n['link']): ?>
                        <a href="<?= h($n['link']) ?>" class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size:0.75rem">View Details</a>
                    <?php endif; ?>
                    
                    <?php if ($is_unread): ?>
                    <form method="POST" class="d-inline m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="mark_read">
                        <input type="hidden" name="notif_id" value="<?= $n['notif_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-light text-secondary py-1 px-2 border" style="font-size:0.75rem">Mark Read</button>
                    </form>
                    <?php endif; ?>
                    
                    <form method="POST" class="d-inline m-0 ms-auto">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="notif_id" value="<?= $n['notif_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-link text-muted p-0" title="Dismiss"><i class="bi bi-x-lg"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.admin-page-icon {
    width:40px; height:40px; border-radius:12px;
    background:var(--brand-light); color:var(--brand);
    display:inline-flex; align-items:center; justify-content:center; font-size:1.2rem;
}
.nav-pills .nav-link.active {
    background-color: var(--brand);
    color: white;
}
</style>
<?php render_footer(); ?>

<?php
require_once __DIR__ . "/layout.php";
require_permission("audit");

$logs = db_fetch_all(
    "SELECT a.*, u.username, u.full_name
     FROM audit_logs a
     LEFT JOIN users u ON u.user_id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT 300"
);

render_header("Audit Log", "audit");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Audit Log</h1>
        <p class="text-secondary mb-0">Admin view of important user actions, including luggage checkout.</p>
    </div>
    <a class="btn btn-outline-secondary" href="reports.php">Reports</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Details</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo h($log["created_at"]); ?></td>
                    <td><?php echo h($log["username"] ?: "System"); ?></td>
                    <td><?php echo h($log["action"]); ?></td>
                    <td><?php echo h(trim(($log["entity_type"] ?? "") . " #" . ($log["entity_id"] ?? ""), " #")); ?></td>
                    <td><?php echo h($log["details"]); ?></td>
                    <td><?php echo h($log["ip_address"]); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
                <tr><td colspan="6"><div class="empty-state">No audit records yet.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_footer(); ?>

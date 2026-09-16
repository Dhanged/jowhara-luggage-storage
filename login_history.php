<?php
require_once __DIR__ . "/layout.php";
require_permission("audit");

$logs = db_fetch_all(
    "SELECT lh.*, u.full_name
     FROM login_history lh
     LEFT JOIN users u ON u.user_id = lh.user_id
     ORDER BY lh.created_at DESC
     LIMIT 300"
);

render_header("Login History", "logins");
?>
<div class="mb-4">
    <h1 class="h3 fw-bold mb-1">Login History</h1>
    <p class="text-secondary mb-0">Successful and failed sign-in attempts.</p>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Date</th><th>User</th><th>Status</th><th>IP</th><th>User Agent</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo h($log["created_at"]); ?></td>
                    <td><?php echo h($log["full_name"] ?: $log["username"]); ?></td>
                    <td><span class="badge text-bg-<?php echo (int)$log["success"] === 1 ? "success" : "danger"; ?>"><?php echo (int)$log["success"] === 1 ? "Success" : "Failed"; ?></span></td>
                    <td><?php echo h($log["ip_address"]); ?></td>
                    <td class="small"><?php echo h($log["user_agent"]); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="5"><div class="empty-state">No login history yet.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php render_footer(); ?>

<?php
require_once __DIR__ . "/layout.php";
require_admin();

$tab       = $_GET["tab"]         ?? "audit";
$filter_uid= (int)($_GET["user_id"] ?? 0);
$filter_act= trim($_GET["action_kw"] ?? "");
$filter_ent= trim($_GET["entity_type"] ?? "");
$start     = $_GET["start"]       ?? "";
$end       = $_GET["end"]         ?? "";
$page      = max(1, (int)($_GET["page"] ?? 1));
$per_page  = 25;
$offset    = ($page - 1) * $per_page;

/* ──────────────────────────────────────────────
   Build WHERE clauses
────────────────────────────────────────────── */
function audit_where(int $uid, string $act, string $ent, string $start, string $end): array {
    $w = []; $t = ""; $p = [];
    if ($uid > 0)    { $w[] = "a.user_id = ?";                $t .= "i"; $p[] = $uid; }
    if ($act !== "") { $w[] = "a.action LIKE ?";               $t .= "s"; $p[] = "%$act%"; }
    if ($ent !== "") { $w[] = "a.entity_type = ?";             $t .= "s"; $p[] = $ent; }
    if ($start !== ""){ $w[] = "DATE(a.created_at) >= ?";      $t .= "s"; $p[] = $start; }
    if ($end !== "")  { $w[] = "DATE(a.created_at) <= ?";      $t .= "s"; $p[] = $end; }
    return ["sql" => $w ? "WHERE " . implode(" AND ", $w) : "", "types" => $t, "params" => $p];
}

function login_where(int $uid, string $start, string $end): array {
    $w = []; $t = ""; $p = [];
    if ($uid > 0)    { $w[] = "lh.user_id = ?";               $t .= "i"; $p[] = $uid; }
    if ($start !== ""){ $w[] = "DATE(lh.created_at) >= ?";     $t .= "s"; $p[] = $start; }
    if ($end !== "")  { $w[] = "DATE(lh.created_at) <= ?";     $t .= "s"; $p[] = $end; }
    return ["sql" => $w ? "WHERE " . implode(" AND ", $w) : "", "types" => $t, "params" => $p];
}

/* ──────────────────────────────────────────────
   Data per tab
────────────────────────────────────────────── */
$rows       = [];
$total_rows = 0;

if ($tab === "audit" || $tab === "activity" || $tab === "changes") {
    $extra = "";
    if ($tab === "changes") {
        $extra = ($filter_uid > 0 || $filter_act !== "" || $filter_ent !== "" || $start !== "" || $end !== "") ? "" : "";
        // Always require entity_type IS NOT NULL for changes tab
        $ent_filter = $filter_ent ?: "";
        $f = audit_where($filter_uid, $filter_act, $ent_filter, $start, $end);
        $changes_where = $f["sql"] ? $f["sql"] . " AND a.entity_type IS NOT NULL" : "WHERE a.entity_type IS NOT NULL";
        $total_rows = (int)db_scalar("SELECT COUNT(*) FROM audit_logs a $changes_where", $f["types"], $f["params"]);
        $rows = db_fetch_all("SELECT a.*, u.full_name, u.username FROM audit_logs a LEFT JOIN users u ON u.user_id = a.user_id $changes_where ORDER BY a.log_id DESC LIMIT $per_page OFFSET $offset", $f["types"], $f["params"]);
    } else {
        $f = audit_where($filter_uid, $filter_act, $filter_ent, $start, $end);
        $total_rows = (int)db_scalar("SELECT COUNT(*) FROM audit_logs a {$f['sql']}", $f["types"], $f["params"]);
        $rows = db_fetch_all("SELECT a.*, u.full_name, u.username FROM audit_logs a LEFT JOIN users u ON u.user_id = a.user_id {$f['sql']} ORDER BY a.log_id DESC LIMIT $per_page OFFSET $offset", $f["types"], $f["params"]);
    }
} elseif ($tab === "logins") {
    $f = login_where($filter_uid, $start, $end);
    $total_rows = (int)db_scalar("SELECT COUNT(*) FROM login_history lh {$f['sql']}", $f["types"], $f["params"]);
    $rows = db_fetch_all("SELECT lh.*, u.full_name FROM login_history lh LEFT JOIN users u ON u.user_id = lh.user_id {$f['sql']} ORDER BY lh.login_id DESC LIMIT $per_page OFFSET $offset", $f["types"], $f["params"]);
}

$total_pages = max(1, (int)ceil($total_rows / $per_page));
$all_users   = db_fetch_all("SELECT user_id, full_name, username FROM users WHERE deleted_at IS NULL ORDER BY full_name");
$entity_types = db_fetch_all("SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type");

/* ──────────────────────────────────────────────
   RENDER
────────────────────────────────────────────── */
render_header("Audit Trail", "admin_audit");

function audit_url(array $extra = []): string {
    $p = array_merge($_GET, $extra);
    return "admin_audit.php?" . http_build_query($p);
}
?>

<!-- Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <span class="admin-page-icon"><i class="bi bi-journal-text"></i></span>
            Audit Trail
        </h1>
        <p class="text-secondary mb-0">Monitor all system activity, login logs, and record changes.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="export_excel.php?type=audit" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Export</a>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end" method="GET">
            <input type="hidden" name="tab" value="<?php echo h($tab); ?>">
            <div class="col-sm-3 col-md-2">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input class="form-control form-control-sm" type="date" name="start" value="<?php echo h($start); ?>">
            </div>
            <div class="col-sm-3 col-md-2">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input class="form-control form-control-sm" type="date" name="end" value="<?php echo h($end); ?>">
            </div>
            <div class="col-sm-4 col-md-2">
                <label class="form-label small fw-semibold mb-1">User</label>
                <select class="form-select form-select-sm" name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($all_users as $u): ?>
                    <option value="<?php echo (int)$u['user_id']; ?>" <?php echo $filter_uid === (int)$u['user_id'] ? 'selected' : ''; ?>>
                        <?php echo h($u['full_name'] ?: $u['username']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($tab !== "logins"): ?>
            <div class="col-sm-4 col-md-2">
                <label class="form-label small fw-semibold mb-1">Action Keyword</label>
                <input class="form-control form-control-sm" type="text" name="action_kw" value="<?php echo h($filter_act); ?>" placeholder="e.g. login, delete…">
            </div>
            <?php endif; ?>
            <?php if ($tab === "changes"): ?>
            <div class="col-sm-4 col-md-2">
                <label class="form-label small fw-semibold mb-1">Entity Type</label>
                <select class="form-select form-select-sm" name="entity_type">
                    <option value="">All</option>
                    <?php foreach ($entity_types as $et): ?>
                    <option value="<?php echo h($et['entity_type']); ?>" <?php echo $filter_ent === $et['entity_type'] ? 'selected' : ''; ?>>
                        <?php echo h($et['entity_type']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                <a class="btn btn-outline-secondary btn-sm ms-1" href="admin_audit.php?tab=<?php echo h($tab); ?>">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-0" id="auditTabs">
    <?php foreach ([
        ["audit",    "bi-journal-text",    "Audit Trail"],
        ["logins",   "bi-shield-lock",     "Login Logs"],
        ["activity", "bi-person-badge",    "User Activity"],
        ["changes",  "bi-clock-history",   "Record Changes"],
    ] as [$t, $ic, $lbl]): ?>
    <li class="nav-item">
        <a class="nav-link d-flex align-items-center gap-1 <?php echo $tab === $t ? 'active' : ''; ?>"
           href="admin_audit.php?tab=<?php echo $t; ?>&user_id=<?php echo $filter_uid; ?>&start=<?php echo urlencode($start); ?>&end=<?php echo urlencode($end); ?>">
            <i class="bi <?php echo $ic; ?>"></i> <?php echo $lbl; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<div class="card" style="border-top-left-radius:0; border-top-right-radius:0; border-top:0;">
    <div class="d-flex align-items-center justify-content-between px-4 py-2 border-bottom">
        <span class="small text-muted"><?php echo number_format($total_rows); ?> record<?php echo $total_rows !== 1 ? "s" : ""; ?> found</span>
        <span class="small text-muted">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
    </div>
    <div class="table-responsive">
        <?php if ($tab === "logins"): ?>
        <table class="table align-middle mb-0 audit-table">
            <thead><tr>
                <th>#</th><th>Username</th><th>User</th><th>Status</th><th>IP Address</th><th>Browser</th><th>Time</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr class="<?php echo $r['success'] ? '' : 'table-danger-subtle'; ?>">
                <td class="text-muted small"><?php echo (int)$r['login_id']; ?></td>
                <td class="fw-bold font-monospace"><?php echo h($r['username']); ?></td>
                <td class="small"><?php echo h($r['full_name'] ?? '—'); ?></td>
                <td>
                    <span class="badge text-bg-<?php echo $r['success'] ? 'success' : 'danger'; ?>">
                        <?php echo $r['success'] ? 'Success' : 'Failed'; ?>
                    </span>
                </td>
                <td class="small font-monospace"><?php echo h($r['ip_address'] ?? '—'); ?></td>
                <td class="small text-muted" style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo h($r['user_agent']); ?>">
                    <?php echo h(substr($r['user_agent'] ?? '', 0, 60)); ?>
                </td>
                <td class="small text-muted"><?php echo date("M j, Y H:i", strtotime($r['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7"><div class="empty-state">No login records found.</div></td></tr><?php endif; ?>
            </tbody>
        </table>

        <?php elseif ($tab === "changes"): ?>
        <table class="table align-middle mb-0 audit-table">
            <thead><tr>
                <th>#</th><th>Entity Type</th><th>Entity ID</th><th>Action</th><th>Changed By</th><th>Details</th><th>Time</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td class="text-muted small"><?php echo (int)$r['log_id']; ?></td>
                <td><span class="badge text-bg-secondary"><?php echo h($r['entity_type']); ?></span></td>
                <td class="font-monospace small"><?php echo h($r['entity_id'] ?? '—'); ?></td>
                <td class="fw-semibold small"><?php echo h($r['action']); ?></td>
                <td class="small"><?php echo h($r['full_name'] ?: ($r['username'] ?? 'System')); ?></td>
                <td class="small text-muted" style="max-width:250px;">
                    <span title="<?php echo h($r['details']); ?>"><?php echo h(substr($r['details'] ?? '', 0, 80)); ?><?php echo strlen($r['details'] ?? '') > 80 ? '…' : ''; ?></span>
                </td>
                <td class="small text-muted"><?php echo date("M j, Y H:i", strtotime($r['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7"><div class="empty-state">No record changes found.</div></td></tr><?php endif; ?>
            </tbody>
        </table>

        <?php else: /* audit + activity */ ?>
        <table class="table align-middle mb-0 audit-table">
            <thead><tr>
                <th>#</th><th>Action</th><th>Entity</th><th>Details</th><th>User</th><th>IP Address</th><th>Time</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td class="text-muted small"><?php echo (int)$r['log_id']; ?></td>
                <td class="fw-semibold small"><?php echo h($r['action']); ?></td>
                <td>
                    <?php if ($r['entity_type']): ?>
                    <span class="badge text-bg-secondary"><?php echo h($r['entity_type']); ?></span>
                    <?php if ($r['entity_id']): ?><span class="text-muted small ms-1">#<?php echo (int)$r['entity_id']; ?></span><?php endif; ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="small text-muted" style="max-width:250px;">
                    <span title="<?php echo h($r['details'] ?? ''); ?>"><?php echo h(substr($r['details'] ?? '', 0, 80)); ?><?php echo strlen($r['details'] ?? '') > 80 ? '…' : ''; ?></span>
                </td>
                <td class="small"><?php echo h($r['full_name'] ?: ($r['username'] ?? 'System')); ?></td>
                <td class="small font-monospace text-muted"><?php echo h($r['ip_address'] ?? '—'); ?></td>
                <td class="small text-muted"><?php echo date("M j, Y H:i", strtotime($r['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7"><div class="empty-state">No records found.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="d-flex justify-content-center py-3">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="<?php echo audit_url(['page' => $page-1]); ?>">‹ Prev</a></li>
            <?php endif; ?>
            <?php for ($i = max(1,$page-3); $i <= min($total_pages,$page+3); $i++): ?>
            <li class="page-item <?php echo $i===$page?'active':''; ?>">
                <a class="page-link" href="<?php echo audit_url(['page' => $i]); ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
            <li class="page-item"><a class="page-link" href="<?php echo audit_url(['page' => $page+1]); ?>">Next ›</a></li>
            <?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<style>
.admin-page-icon { width:40px; height:40px; border-radius:12px; background:var(--brand-light); color:var(--brand); display:inline-flex; align-items:center; justify-content:center; font-size:1.2rem; }
.audit-table tbody tr { transition:background .12s; }
.table-danger-subtle { background:rgba(239,68,68,.04); }
</style>
<?php render_footer(); ?>

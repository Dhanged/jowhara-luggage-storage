<?php
require_once __DIR__ . "/layout.php";
require_admin();

/* ── POST ── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";

    if ($action === "delete_txn") {
        $lid    = (int)($_POST["luggage_id"] ?? 0);
        $reason = trim($_POST["reason"] ?? "No reason given");
        db_execute("UPDATE luggage SET deleted_at=NOW() WHERE luggage_id=?", "i", [$lid]);
        log_action("Admin deleted transaction", "luggage", $lid, "Reason: $reason");
        set_flash("success", "Transaction deleted.");
        redirect("admin_transactions.php");
    }

    if ($action === "reverse_payment") {
        $lid = (int)($_POST["luggage_id"] ?? 0);
        db_execute("UPDATE luggage SET payment_status='Unpaid', payment_method=NULL, payment_reference=NULL WHERE luggage_id=?", "i", [$lid]);
        log_action("Reversed payment", "luggage", $lid, "Payment reversed to Unpaid by admin");
        set_flash("success", "Payment reversed to Unpaid.");
        redirect("admin_transactions.php");
    }
}

/* ── Filters ── */
$pay_status = $_GET["pay_status"] ?? "";
$start      = $_GET["start"]      ?? "";
$end        = $_GET["end"]        ?? "";
$search     = trim($_GET["search"] ?? "");
$page       = max(1, (int)($_GET["page"] ?? 1));
$per_page   = 25;
$offset     = ($page - 1) * $per_page;

$where = ["l.deleted_at IS NULL"]; $types = ""; $params = [];
if (in_array($pay_status, ["Paid","Unpaid","Waived"], true)) { $where[] = "l.payment_status=?"; $types .= "s"; $params[] = $pay_status; }
if ($start !== "") { $where[] = "DATE(l.checkin_date)>=?"; $types .= "s"; $params[] = $start; }
if ($end   !== "") { $where[] = "DATE(l.checkin_date)<=?"; $types .= "s"; $params[] = $end; }
if ($search !== "") { $where[] = "(g.guest_name LIKE ? OR l.tag_code LIKE ?)"; $types .= "ss"; $params[] = "%$search%"; $params[] = "%$search%"; }
$where_sql = "WHERE " . implode(" AND ", $where);

$total_rows  = (int)db_scalar("SELECT COUNT(*) FROM luggage l JOIN guests g ON g.guest_id=l.guest_id $where_sql", $types, $params);
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$rows = db_fetch_all(
    "SELECT l.*, g.guest_name, g.room_no, u.username AS created_user, co.username AS checkout_user
     FROM luggage l
     JOIN guests g ON g.guest_id=l.guest_id
     LEFT JOIN users u  ON u.user_id=l.created_by
     LEFT JOIN users co ON co.user_id=l.checked_out_by
     $where_sql ORDER BY l.luggage_id DESC LIMIT $per_page OFFSET $offset",
    $types, $params
);

/* ── Stats ── */
$sum_paid   = (float)db_scalar("SELECT COALESCE(SUM(fee_amount),0) FROM luggage WHERE payment_status='Paid' AND deleted_at IS NULL");
$sum_unpaid = (float)db_scalar("SELECT COALESCE(SUM(fee_amount),0) FROM luggage WHERE payment_status='Unpaid' AND deleted_at IS NULL");
$sum_waived = (float)db_scalar("SELECT COALESCE(SUM(fee_amount),0) FROM luggage WHERE payment_status='Waived' AND deleted_at IS NULL");
$cnt_total  = (int)db_scalar("SELECT COUNT(*) FROM luggage WHERE deleted_at IS NULL");

render_header("Transactions", "admin_txn");
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <span class="admin-page-icon"><i class="bi bi-credit-card-2-front"></i></span>
            Transaction Management
        </h1>
        <p class="text-secondary mb-0">View, reverse and manage all payment transactions.</p>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ["Total Transactions", $cnt_total,  "#6366f1", "bi-receipt",      false],
        ["Total Paid",         $sum_paid,   "#10b981", "bi-cash-coin",     true],
        ["Total Unpaid",       $sum_unpaid, "#f59e0b", "bi-hourglass-split",true],
        ["Total Waived",       $sum_waived, "#94a3b8", "bi-dash-circle",   true],
    ] as [$lbl, $val, $clr, $ico, $isMoney]): ?>
    <div class="col-6 col-md-3">
        <div class="card admin-stat-card" style="border-left:4px solid <?php echo $clr; ?>;">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="admin-stat-icon" style="background:<?php echo $clr; ?>20;color:<?php echo $clr; ?>;"><i class="bi <?php echo $ico; ?>"></i></div>
                <div>
                    <div class="admin-stat-label"><?php echo $lbl; ?></div>
                    <div class="admin-stat-value"><?php echo $isMoney ? "$".money($val) : $val; ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-sm-3 col-md-2">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input class="form-control form-control-sm" type="date" name="start" value="<?php echo h($start); ?>">
            </div>
            <div class="col-sm-3 col-md-2">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input class="form-control form-control-sm" type="date" name="end" value="<?php echo h($end); ?>">
            </div>
            <div class="col-sm-3 col-md-2">
                <label class="form-label small fw-semibold mb-1">Payment Status</label>
                <select class="form-select form-select-sm" name="pay_status">
                    <option value="">All</option>
                    <option value="Paid" <?php echo $pay_status==="Paid"?"selected":""; ?>>Paid</option>
                    <option value="Unpaid" <?php echo $pay_status==="Unpaid"?"selected":""; ?>>Unpaid</option>
                    <option value="Waived" <?php echo $pay_status==="Waived"?"selected":""; ?>>Waived</option>
                </select>
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label small fw-semibold mb-1">Search</label>
                <input class="form-control form-control-sm" type="text" name="search" value="<?php echo h($search); ?>" placeholder="Guest or tag…">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                <a class="btn btn-outline-secondary btn-sm ms-1" href="admin_transactions.php">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="d-flex align-items-center justify-content-between px-4 py-2 border-bottom">
        <span class="small text-muted"><?php echo number_format($total_rows); ?> records · Page <?php echo $page; ?>/<?php echo $total_pages; ?></span>
        <a href="export_excel.php" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Export</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr>
                <th>Tag</th><th>Guest</th><th>Check-in</th><th>Check-out</th>
                <th>Fee</th><th>Payment</th><th>Method</th><th>By</th><th class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $bg = $r["payment_status"]==="Paid" ? "rgba(16,185,129,.04)" : ($r["payment_status"]==="Unpaid" ? "rgba(245,158,11,.04)" : "");
            ?>
            <tr style="background:<?php echo $bg; ?>;">
                <td class="fw-bold font-monospace" style="color:var(--brand);"><?php echo h($r["tag_code"]); ?></td>
                <td>
                    <div class="fw-semibold small"><?php echo h($r["guest_name"]); ?></div>
                    <div class="text-muted" style="font-size:.72rem;">Room <?php echo h($r["room_no"]); ?></div>
                </td>
                <td class="small text-muted"><?php echo $r["checkin_date"] ? date("M j, Y", strtotime($r["checkin_date"])) : "—"; ?></td>
                <td class="small text-muted"><?php echo $r["checkout_date"] ? date("M j, Y", strtotime($r["checkout_date"])) : "—"; ?></td>
                <td class="fw-bold">$<?php echo money($r["fee_amount"]); ?></td>
                <td><span class="badge text-bg-<?php echo $r["payment_status"]==="Paid"?"success":($r["payment_status"]==="Waived"?"secondary":"warning text-dark"); ?>"><?php echo h($r["payment_status"]); ?></span></td>
                <td class="small text-muted"><?php echo h($r["payment_method"] ?? "—"); ?></td>
                <td class="small text-muted"><?php echo h($r["created_user"] ?? "—"); ?></td>
                <td>
                    <div class="table-actions justify-content-end gap-1">
                        <a class="btn btn-sm btn-outline-secondary" href="receipt.php?id=<?php echo (int)$r["luggage_id"]; ?>"><i class="bi bi-receipt"></i></a>
                        <?php if ($r["payment_status"] === "Paid"): ?>
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalReverse"
                            data-lid="<?php echo (int)$r['luggage_id']; ?>" data-tag="<?php echo h($r['tag_code']); ?>">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalDeleteTxn"
                            data-lid="<?php echo (int)$r['luggage_id']; ?>" data-tag="<?php echo h($r['tag_code']); ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="9"><div class="empty-state">No transactions found.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="d-flex justify-content-center py-3">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i = max(1,$page-3); $i <= min($total_pages,$page+3); $i++): ?>
            <li class="page-item <?php echo $i===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&pay_status=<?php echo urlencode($pay_status); ?>&start=<?php echo urlencode($start); ?>&end=<?php echo urlencode($end); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a></li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Reverse Payment -->
<div class="modal fade" id="modalReverse" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-counterclockwise me-2 text-warning"></i>Reverse Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reverse_payment">
                <input type="hidden" name="luggage_id" id="reverseId">
                <div class="modal-body">
                    <p>Reverse payment for tag <strong id="reverseTag"></strong>? This will set status back to <span class="badge text-bg-warning text-dark">Unpaid</span>.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white"><i class="bi bi-arrow-counterclockwise me-1"></i>Reverse</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Transaction -->
<div class="modal fade" id="modalDeleteTxn" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-trash-fill me-2"></i>Delete Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_txn">
                <input type="hidden" name="luggage_id" id="deleteTxnId">
                <div class="modal-body">
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>This permanently removes transaction <strong id="deleteTxnTag"></strong>.</div>
                    <label class="form-label fw-semibold">Reason for deletion</label>
                    <textarea class="form-control" name="reason" rows="2" placeholder="Explain why this record is being deleted…" required></textarea>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.admin-page-icon{width:40px;height:40px;border-radius:12px;background:var(--brand-light);color:var(--brand);display:inline-flex;align-items:center;justify-content:center;font-size:1.2rem;}
.admin-stat-card{border-radius:14px;box-shadow:var(--shadow-card);}
.admin-stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.admin-stat-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-muted);}
.admin-stat-value{font-size:1.4rem;font-weight:800;color:var(--ink);line-height:1;}
</style>
<script>
document.getElementById("modalReverse").addEventListener("show.bs.modal", e => {
    const b = e.relatedTarget;
    document.getElementById("reverseId").value = b.dataset.lid;
    document.getElementById("reverseTag").textContent = b.dataset.tag;
});
document.getElementById("modalDeleteTxn").addEventListener("show.bs.modal", e => {
    const b = e.relatedTarget;
    document.getElementById("deleteTxnId").value = b.dataset.lid;
    document.getElementById("deleteTxnTag").textContent = b.dataset.tag;
});
</script>
<?php render_footer(); ?>

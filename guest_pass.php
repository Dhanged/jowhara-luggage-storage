<?php
require_once __DIR__ . "/functions.php";

$tag = trim($_GET["tag"] ?? "");
$id  = (int)($_GET["id"] ?? 0);

$item = null;
if ($tag !== "") {
    $item = db_fetch_one(
        "SELECT l.*, g.guest_name, g.room_no, b.branch_name
         FROM luggage l
         JOIN guests g ON g.guest_id = l.guest_id
         LEFT JOIN branches b ON b.branch_id = l.branch_id
         WHERE l.tag_code = ? AND l.deleted_at IS NULL",
        "s",
        [$tag]
    );
} elseif ($id > 0) {
    $item = db_fetch_one(
        "SELECT l.*, g.guest_name, g.room_no, b.branch_name
         FROM luggage l
         JOIN guests g ON g.guest_id = l.guest_id
         LEFT JOIN branches b ON b.branch_id = l.branch_id
         WHERE l.luggage_id = ? AND l.deleted_at IS NULL",
        "i",
        [$id]
    );
}

if (!$item) {
    http_response_code(404);
    die("<!DOCTYPE html><html><body style='font-family:sans-serif;text-align:center;padding:50px;'><h2>Luggage Pass Not Found</h2><p>Please double check your tag code or link.</p></body></html>");
}

$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($item['tag_code']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Luggage Pass - <?php echo h($item['tag_code']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 15px; }
        .pass-card { background: #1e293b; border-radius: 24px; width: 100%; max-width: 420px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 40px rgba(0,0,0,0.5); overflow: hidden; }
        .pass-header { background: linear-gradient(135deg, #1d6f6f 0%, #145050 100%); padding: 24px 20px; text-align: center; color: #fff; }
        .hotel-brand { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; opacity: 0.9; }
        .pass-title { font-size: 20px; font-weight: 800; margin-top: 4px; }
        .pass-body { padding: 24px 20px; }
        .qr-card { background: #fff; padding: 16px; border-radius: 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 20px; }
        .qr-card img { width: 180px; height: 180px; }
        .tag-pill { background: #0f172a; color: #38bdf8; font-family: monospace; font-size: 18px; font-weight: 800; padding: 6px 16px; border-radius: 20px; margin-top: 10px; border: 1px solid rgba(56,189,248,0.3); }
        .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-weight: 700; font-size: 13px; text-transform: uppercase; }
        .status-Stored { background: rgba(34,197,94,0.2); color: #4ade80; border: 1px solid rgba(74,222,128,0.3); }
        .status-Collected { background: rgba(148,163,184,0.2); color: #cbd5e1; border: 1px solid rgba(203,213,225,0.3); }
        .detail-row { display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.1); padding: 10px 0; font-size: 14px; }
        .detail-label { color: #94a3b8; }
        .detail-value { font-weight: 600; color: #f8fafc; }
        .btn-notify { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #fff; font-weight: 700; border: none; padding: 14px; border-radius: 14px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; transition: transform 0.2s ease; }
        .btn-notify:active { transform: scale(0.98); }
    </style>
</head>
<body>

<div class="pass-card">
    <div class="pass-header">
        <div class="hotel-brand"><?php echo h(get_setting('company_name', 'JOWHARA HOTEL')); ?></div>
        <div class="pass-title">Digital Luggage Pass</div>
    </div>
    <div class="pass-body">
        <div class="text-center mb-3">
            <span class="status-badge status-<?php echo h($item['status']); ?>">
                <i class="bi bi-shield-check"></i> <?php echo h($item['status']); ?>
            </span>
        </div>

        <div class="qr-card">
            <img src="<?php echo $qr_url; ?>" alt="QR Pass">
            <div class="tag-pill"><?php echo h($item['tag_code']); ?></div>
        </div>

        <div class="detail-row">
            <span class="detail-label">Guest Name:</span>
            <span class="detail-value"><?php echo h($item['guest_name']); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Room Number:</span>
            <span class="detail-value"><?php echo h($item['room_no'] ?: '-'); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Luggage Type:</span>
            <span class="detail-value"><?php echo h($item['luggage_type'] ?: 'Bag'); ?> (x<?php echo (int)($item['quantity'] ?: 1); ?>)</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Check-In Date:</span>
            <span class="detail-value"><?php echo date("d M Y, H:i", strtotime($item['checkin_date'])); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Location Branch:</span>
            <span class="detail-value"><?php echo h($item['branch_name'] ?? 'Main Branch'); ?></span>
        </div>

        <?php if ($item['status'] === 'Stored'): ?>
        <div class="mt-4">
            <button id="notifyBtn" class="btn-notify">
                <i class="bi bi-bell-fill"></i> I'm Coming to Pick Up (10 Mins)
            </button>
            <div id="notifyStatus" class="mt-2 text-center small text-success d-none"></div>
        </div>
        <?php endif; ?>

        <div class="mt-4 text-center text-muted small" style="font-size: 11px;">
            Show this digital pass to the luggage counter staff to retrieve your items.
        </div>
    </div>
</div>

<script>
document.getElementById('notifyBtn')?.addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Notifying Front Desk...';

    const formData = new FormData();
    formData.append('tag', '<?php echo h($item['tag_code']); ?>');

    fetch('ajax_guest_notify.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const statusDiv = document.getElementById('notifyStatus');
        statusDiv.classList.remove('d-none');
        if (data.status === 'success') {
            statusDiv.className = 'mt-2 text-center small text-success fw-bold';
            statusDiv.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + data.message;
            btn.innerHTML = '<i class="bi bi-check2-all"></i> Alert Sent to Desk';
        } else {
            statusDiv.className = 'mt-2 text-center small text-danger';
            statusDiv.textContent = data.message;
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-bell-fill"></i> Try Again';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-bell-fill"></i> Try Again';
    });
});
</script>

</body>
</html>

<?php
require_once __DIR__ . "/functions.php";
require_login();

$id = (int)($_GET["id"] ?? 0);
$tag = trim($_GET["tag"] ?? "");

$item = null;
if ($id > 0) {
    $item = db_fetch_one(
        "SELECT l.*, g.guest_name, g.room_no, g.phone, b.branch_name
         FROM luggage l
         JOIN guests g ON g.guest_id = l.guest_id
         LEFT JOIN branches b ON b.branch_id = l.branch_id
         WHERE l.luggage_id = ? AND l.deleted_at IS NULL",
        "i",
        [$id]
    );
} elseif ($tag !== "") {
    $item = db_fetch_one(
        "SELECT l.*, g.guest_name, g.room_no, g.phone, b.branch_name
         FROM luggage l
         JOIN guests g ON g.guest_id = l.guest_id
         LEFT JOIN branches b ON b.branch_id = l.branch_id
         WHERE l.tag_code = ? AND l.deleted_at IS NULL",
        "s",
        [$tag]
    );
}

if (!$item) {
    die("Luggage item not found.");
}

$paper_size = $_GET["size"] ?? "58mm";
$qr_data = urlencode(get_setting('app_url', 'http://localhost/luggage_storage') . "/guest_pass.php?tag=" . $item['tag_code']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label - <?php echo h($item['tag_code']); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Courier New', Courier, monospace, sans-serif; }
        body { background: #f8f9fa; display: flex; flex-direction: column; align-items: center; padding: 20px; }
        
        .label-card {
            background: #fff;
            width: <?php echo $paper_size === '80mm' ? '76mm' : '54mm'; ?>;
            border: 2px dashed #000;
            padding: 8px;
            text-align: center;
            color: #000;
        }

        .hotel-title { font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px; }
        .branch-title { font-size: 10px; font-weight: bold; color: #333; margin-bottom: 6px; }

        .tag-box {
            border: 3px solid #000;
            padding: 4px;
            margin: 4px 0;
            background: #000;
            color: #fff;
        }
        .tag-code { font-size: 22px; font-weight: 900; letter-spacing: 2px; }

        .qr-wrap { margin: 6px 0; }
        .qr-wrap img { width: 110px; height: 110px; display: block; margin: 0 auto; }

        .info-table { width: 100%; border-collapse: collapse; margin-top: 6px; text-align: left; font-size: 11px; }
        .info-table td { padding: 3px 0; border-bottom: 1px dotted #ccc; }
        .info-table td.label { font-weight: bold; width: 40%; }

        .badge-hv { display: inline-block; background: #000; color: #fff; padding: 2px 6px; font-size: 10px; font-weight: bold; margin-top: 4px; }

        .no-print-bar { margin-bottom: 15px; display: flex; gap: 10px; }
        .btn { padding: 8px 16px; font-size: 14px; font-weight: bold; border-radius: 6px; border: 1px solid #333; cursor: pointer; background: #1d6f6f; color: #fff; }
        .btn-alt { background: #6c757d; }

        @media print {
            .no-print-bar { display: none !important; }
            body { background: none; padding: 0; margin: 0; }
            .label-card { border: none; width: 100%; padding: 4px; }
            @page { size: <?php echo $paper_size === '80mm' ? '80mm' : '58mm'; ?> auto; margin: 0; }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <button class="btn" onclick="window.print()">🖨️ Print Label</button>
    <button class="btn btn-alt" onclick="window.close()">Close</button>
</div>

<div class="label-card">
    <div class="hotel-title"><?php echo h(get_setting('company_name', 'JOWHARA HOTEL')); ?></div>
    <div class="branch-title"><?php echo h($item['branch_name'] ?? 'Main Branch'); ?> - LUGGAGE TAG</div>

    <div class="tag-box">
        <div class="tag-code"><?php echo h($item['tag_code'] ?: 'REF-' . $item['luggage_id']); ?></div>
    </div>

    <div class="qr-wrap">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $qr_data; ?>" alt="QR Code">
    </div>

    <?php if (!empty($item['is_high_value'])): ?>
        <div class="badge-hv">HIGH VALUE / SECURED</div>
    <?php endif; ?>

    <table class="info-table">
        <tr>
            <td class="label">Guest:</td>
            <td><strong><?php echo h($item['guest_name']); ?></strong></td>
        </tr>
        <tr>
            <td class="label">Room #:</td>
            <td><strong><?php echo h($item['room_no'] ?: '-'); ?></strong></td>
        </tr>
        <tr>
            <td class="label">Location:</td>
            <td><strong><?php echo h(($item['storage_zone'] ? $item['storage_zone'] . ' / ' : '') . ($item['storage_location'] ?: '-')); ?></strong></td>
        </tr>
        <tr>
            <td class="label">Type / Qty:</td>
            <td><?php echo h($item['luggage_type'] ?: 'Bag'); ?> (x<?php echo (int)($item['quantity'] ?: 1); ?>)</td>
        </tr>
        <tr>
            <td class="label">Check-in:</td>
            <td><?php echo date("d/m/Y H:i", strtotime($item['checkin_date'])); ?></td>
        </tr>
    </table>
</div>

<script>
    // Auto trigger print dialog on page load
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => { window.print(); }, 500);
    });
</script>

</body>
</html>

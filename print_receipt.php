<?php
require_once __DIR__ . "/functions.php";
require_login();

$id = (int)($_GET["id"] ?? 0);
$item = db_fetch_one(
    "SELECT l.*, g.guest_name, g.room_no, g.phone, g.email, b.branch_name, u.username as staff_name
     FROM luggage l
     JOIN guests g ON g.guest_id = l.guest_id
     LEFT JOIN branches b ON b.branch_id = l.branch_id
     LEFT JOIN users u ON u.user_id = l.created_by
     WHERE l.luggage_id = ? AND l.deleted_at IS NULL",
    "i",
    [$id]
);

if (!$item) {
    die("Luggage record not found.");
}

$qr_data = urlencode(get_setting('app_url', 'http://localhost/luggage_storage') . "/guest_pass.php?tag=" . $item['tag_code']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo h($item['tag_code']); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Courier New', Courier, monospace, sans-serif; }
        body { background: #eef2f5; display: flex; flex-direction: column; align-items: center; padding: 20px; }
        
        .receipt-card {
            background: #fff;
            width: 76mm;
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
            color: #000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .hotel-name { font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; }
        .hotel-sub { font-size: 10px; color: #444; margin-bottom: 8px; border-bottom: 1px dashed #000; padding-bottom: 6px; }

        .receipt-title { font-size: 12px; font-weight: bold; text-transform: uppercase; margin: 6px 0; background: #eee; padding: 3px; }

        .info-grid { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 11px; text-align: left; }
        .info-grid td { padding: 3px 0; border-bottom: 1px dotted #ccc; }
        .info-grid td.bold { font-weight: bold; }

        .tag-highlight { font-size: 20px; font-weight: 900; margin: 8px 0; border: 2px solid #000; padding: 4px; display: inline-block; width: 100%; background: #fafafa; }

        .fee-table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 11px; }
        .fee-table th { text-align: left; border-bottom: 1px solid #000; padding-bottom: 2px; }
        .fee-table td { padding: 3px 0; text-align: right; }
        .fee-table td.left { text-align: left; }

        .terms { font-size: 8.5px; color: #555; text-align: justify; margin: 10px 0; border-top: 1px dashed #000; padding-top: 6px; line-height: 1.2; }

        .qr-wrap { margin: 8px 0; }
        .qr-wrap img { width: 100px; height: 100px; }

        .no-print-bar { margin-bottom: 15px; display: flex; gap: 10px; }
        .btn { padding: 8px 16px; font-size: 14px; font-weight: bold; border-radius: 6px; border: none; cursor: pointer; background: #1d6f6f; color: #fff; }
        .btn-alt { background: #6c757d; }

        @media print {
            .no-print-bar { display: none !important; }
            body { background: none; padding: 0; margin: 0; }
            .receipt-card { border: none; width: 100%; padding: 4px; box-shadow: none; }
            @page { size: 80mm auto; margin: 0; }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <button class="btn" onclick="window.print()">🖨️ Print Receipt</button>
    <button class="btn btn-alt" onclick="window.close()">Close</button>
</div>

<div class="receipt-card">
    <div class="hotel-name"><?php echo h(get_setting('company_name', 'JOWHARA HOTEL')); ?></div>
    <div class="hotel-sub">
        <?php echo h($item['branch_name'] ?? 'Main Branch'); ?><br>
        Phone: <?php echo h(get_setting('company_phone', '+252 61 0000000')); ?>
    </div>

    <div class="receipt-title">LUGGAGE CLAIM RECEIPT</div>

    <div class="tag-highlight"><?php echo h($item['tag_code'] ?: 'REF-' . $item['luggage_id']); ?></div>

    <div class="qr-wrap">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $qr_data; ?>" alt="QR Code">
    </div>

    <table class="info-grid">
        <tr>
            <td class="bold">Guest Name:</td>
            <td><?php echo h($item['guest_name']); ?></td>
        </tr>
        <tr>
            <td class="bold">Room #:</td>
            <td><?php echo h($item['room_no'] ?: '-'); ?></td>
        </tr>
        <tr>
            <td class="bold">Phone:</td>
            <td><?php echo h($item['phone'] ?: '-'); ?></td>
        </tr>
        <tr>
            <td class="bold">Luggage Details:</td>
            <td><?php echo h($item['luggage_type'] ?: 'Bag'); ?> (x<?php echo (int)($item['quantity'] ?: 1); ?>)</td>
        </tr>
        <tr>
            <td class="bold">Check-In Date:</td>
            <td><?php echo date("d/m/Y H:i", strtotime($item['checkin_date'])); ?></td>
        </tr>
        <tr>
            <td class="bold">Expected Pickup:</td>
            <td><?php echo $item['expected_pickup_date'] ? date("d/m/Y", strtotime($item['expected_pickup_date'])) : 'Open'; ?></td>
        </tr>
        <tr>
            <td class="bold">Staff Agent:</td>
            <td><?php echo h($item['staff_name'] ?: 'Reception'); ?></td>
        </tr>
    </table>

    <table class="fee-table">
        <thead>
            <tr>
                <th class="left">Description</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="left">Storage Fee</td>
                <td>$<?php echo h(money($item['fee_amount'])); ?></td>
            </tr>
            <?php if ((float)$item['discount_amount'] > 0): ?>
            <tr>
                <td class="left">Discount</td>
                <td>-$<?php echo h(money($item['discount_amount'])); ?></td>
            </tr>
            <?php endif; ?>
            <tr style="font-weight: bold; border-top: 1px solid #000;">
                <td class="left">Payment Status:</td>
                <td><?php echo h(strtoupper($item['payment_status'])); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="terms">
        <strong>TERMS & CONDITIONS:</strong> Please present this receipt or digital QR code upon collecting your luggage. The hotel is not responsible for items left unclaimed beyond 30 days unless prior arrangements are made.
    </div>

    <div style="margin-top: 12px; font-size: 9px; color: #777;">
        Thank you for staying with Jowhara Hotel!
    </div>
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => { window.print(); }, 500);
    });
</script>

</body>
</html>

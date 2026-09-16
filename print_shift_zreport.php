<?php
require_once __DIR__ . "/functions.php";
require_login();

$id = (int)($_GET["id"] ?? 0);
$h = db_fetch_one(
    "SELECT h.*, u1.username as outgoing_name, u2.username as incoming_name
     FROM shift_handovers h
     LEFT JOIN users u1 ON u1.user_id = h.outgoing_user_id
     LEFT JOIN users u2 ON u2.user_id = h.incoming_user_id
     WHERE h.handover_id = ?",
    "i",
    [$id]
);

if (!$h) {
    die("Shift handover record not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Z-Report - Shift #<?php echo (int)$h['handover_id']; ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Courier New', Courier, monospace; }
        body { background: #eef2f5; display: flex; flex-direction: column; align-items: center; padding: 20px; }
        .z-card { background: #fff; width: 76mm; padding: 12px; border: 1px solid #ddd; text-align: center; color: #000; }
        .title { font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; }
        .subtitle { font-size: 10px; color: #555; border-bottom: 1px dashed #000; padding-bottom: 6px; margin-bottom: 8px; }
        .z-header { font-size: 13px; font-weight: bold; background: #000; color: #fff; padding: 4px; margin-bottom: 8px; }
        .row-table { width: 100%; border-collapse: collapse; font-size: 11px; margin: 6px 0; text-align: left; }
        .row-table td { padding: 3px 0; border-bottom: 1px dotted #ccc; }
        .row-table td.right { text-align: right; }
        .row-table td.bold { font-weight: bold; }
        .sig-block { margin-top: 20px; display: flex; justify-content: space-between; font-size: 9px; }
        .sig-line { border-top: 1px solid #000; width: 45%; text-align: center; padding-top: 4px; }
        .no-print-bar { margin-bottom: 15px; display: flex; gap: 10px; }
        .btn { padding: 8px 16px; font-size: 14px; font-weight: bold; border-radius: 6px; border: none; cursor: pointer; background: #1d6f6f; color: #fff; }
        .btn-alt { background: #6c757d; }
        @media print {
            .no-print-bar { display: none !important; }
            body { background: none; padding: 0; margin: 0; }
            .z-card { border: none; width: 100%; padding: 4px; }
            @page { size: 80mm auto; margin: 0; }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <button class="btn" onclick="window.print()">🖨️ Print Z-Report</button>
    <button class="btn btn-alt" onclick="window.close()">Close</button>
</div>

<div class="z-card">
    <div class="title"><?php echo h(get_setting('company_name', 'JOWHARA HOTEL')); ?></div>
    <div class="subtitle">SHIFT RECONCILIATION Z-REPORT</div>

    <div class="z-header">SHIFT Z-REPORT #<?php echo (int)$h['handover_id']; ?></div>

    <table class="row-table">
        <tr>
            <td class="bold">Date & Time:</td>
            <td class="right"><?php echo date("d/m/Y H:i", strtotime($h['created_at'])); ?></td>
        </tr>
        <tr>
            <td class="bold">Outgoing Staff:</td>
            <td class="right"><?php echo h($h['outgoing_name']); ?></td>
        </tr>
        <tr>
            <td class="bold">Incoming Staff:</td>
            <td class="right"><?php echo h($h['incoming_name']); ?></td>
        </tr>
    </table>

    <div style="font-weight:bold; font-size:11px; margin-top:8px; border-bottom:1px solid #000; text-align:left;">CASH REGISTER SUMMARY</div>

    <table class="row-table">
        <tr>
            <td>Opening Float:</td>
            <td class="right">$<?php echo h(money($h['opening_cash'])); ?></td>
        </tr>
        <tr>
            <td>System Cash Sales:</td>
            <td class="right">$<?php echo h(money($h['expected_cash'])); ?></td>
        </tr>
        <tr>
            <td>Card/Digital Sales:</td>
            <td class="right">$<?php echo h(money($h['card_total'])); ?></td>
        </tr>
        <tr style="font-weight: bold;">
            <td>Total Shift Revenue:</td>
            <td class="right">$<?php echo h(money($h['total_collected'])); ?></td>
        </tr>
        <tr style="font-weight: bold; border-top:1px solid #000;">
            <td>Physical Cash Count:</td>
            <td class="right">$<?php echo h(money($h['actual_cash'])); ?></td>
        </tr>
        <tr style="font-weight: bold;">
            <td>Variance (Short/Over):</td>
            <td class="right" style="color: <?php echo (float)$h['cash_variance'] < 0 ? 'red' : 'green'; ?>;">
                $<?php echo h(money($h['cash_variance'])); ?>
            </td>
        </tr>
    </table>

    <?php if ($h['handover_notes']): ?>
        <div style="font-size:10px; text-align:left; margin-top:8px; background:#f9f9f9; padding:4px; border:1px solid #ddd;">
            <strong>Notes:</strong> <?php echo h($h['handover_notes']); ?>
        </div>
    <?php endif; ?>

    <div class="sig-block">
        <div class="sig-line">
            <?php echo h($h['outgoing_name']); ?><br>(Outgoing Staff)
        </div>
        <div class="sig-line">
            <?php echo h($h['incoming_name']); ?><br>(Incoming Staff)
        </div>
    </div>
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => { window.print(); }, 500);
    });
</script>

</body>
</html>

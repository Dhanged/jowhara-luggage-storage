<?php
namespace App\Reports;

class ExecutiveReportGenerator
{
    /**
     * Generate HTML Executive Summary Report for EOD email.
     */
    public function generateDailyHtmlReport(string $date = null): string
    {
        $date = $date ?: date("Y-m-d");

        // 1. Luggage Volume
        $checkins = (int)\db_scalar("SELECT COUNT(*) FROM luggage WHERE DATE(checkin_date) = ? AND deleted_at IS NULL", "s", [$date]);
        $checkouts = (int)\db_scalar("SELECT COUNT(*) FROM luggage WHERE DATE(checkout_date) = ? AND deleted_at IS NULL", "s", [$date]);

        // 2. Revenue Breakdown
        $revenueRows = \db_fetch_all(
            "SELECT payment_method, SUM(fee_amount) as total FROM luggage WHERE payment_status = 'Paid' AND DATE(updated_at) = ? AND deleted_at IS NULL GROUP BY payment_method",
            "s",
            [$date]
        );
        $totalRev = 0.0;
        $revSummary = [];
        foreach ($revenueRows as $r) {
            $amt = (float)$r['total'];
            $totalRev += $amt;
            $revSummary[] = h($r['payment_method'] ?: 'Cash') . ": $" . money($amt);
        }

        // 3. Overdue & Lost & Found
        $overdueCount = (int)\db_scalar("SELECT COUNT(*) FROM luggage WHERE status = 'Stored' AND expected_pickup_date < ? AND deleted_at IS NULL", "s", [$date]);
        $lfFound = (int)\db_scalar("SELECT COUNT(*) FROM lost_found_items WHERE DATE(found_date) = ? AND deleted_at IS NULL", "s", [$date]);
        $lfClaimed = (int)\db_scalar("SELECT COUNT(*) FROM lost_found_items WHERE DATE(claimed_date) = ? AND deleted_at IS NULL", "s", [$date]);

        // 4. Shelf Utilization
        $shelf = \get_shelf_utilization_analytics();
        $capacityPct = $shelf['overall_utilization_pct'];

        $company = \get_setting('company_name', 'Jowhara Hotel');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px; }
                .container { max-width: 600px; background: #ffffff; margin: 0 auto; padding: 20px; border-radius: 8px; border: 1px solid #e1e4e8; }
                .header { background: #1d6f6f; color: #ffffff; padding: 16px; border-radius: 6px; text-align: center; }
                .header h1 { margin: 0; font-size: 20px; }
                .section { margin: 20px 0; }
                .section-title { font-size: 15px; font-weight: bold; border-bottom: 2px solid #1d6f6f; padding-bottom: 4px; margin-bottom: 10px; color: #1d6f6f; }
                .stat-grid { display: table; width: 100%; }
                .stat-box { display: table-cell; width: 50%; padding: 8px; text-align: center; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; }
                .stat-val { font-size: 22px; font-weight: bold; color: #1d6f6f; }
                .stat-lbl { font-size: 11px; color: #6c757d; text-transform: uppercase; margin-top: 2px; }
                .table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
                .table td { padding: 6px 0; border-bottom: 1px solid #eee; }
                .table td.right { text-align: right; font-weight: bold; }
                .footer { font-size: 11px; color: #999; text-align: center; margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?= h($company) ?></h1>
                    <div style="font-size: 12px; margin-top: 4px;">Executive End-Of-Day Report - <?= h($date) ?></div>
                </div>

                <div class="section">
                    <div class="section-title">Operations Summary</div>
                    <div class="stat-grid">
                        <div class="stat-box">
                            <div class="stat-val"><?= $checkins ?></div>
                            <div class="stat-lbl">Check-Ins Today</div>
                        </div>
                        <div class="stat-box" style="margin-left: 8px;">
                            <div class="stat-val"><?= $checkouts ?></div>
                            <div class="stat-lbl">Check-Outs Today</div>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">Financial Summary</div>
                    <table class="table">
                        <tr>
                            <td>Total Revenue Collected:</td>
                            <td class="right">$<?= money($totalRev) ?></td>
                        </tr>
                        <tr>
                            <td>Payment Breakdown:</td>
                            <td class="right"><?= implode(' | ', $revSummary) ?: 'No transactions' ?></td>
                        </tr>
                    </table>
                </div>

                <div class="section">
                    <div class="section-title">Capacity & Inventory Status</div>
                    <table class="table">
                        <tr>
                            <td>Shelf System Utilization:</td>
                            <td class="right"><?= $capacityPct ?>% Capacity</td>
                        </tr>
                        <tr>
                            <td>Overdue Luggage Count:</td>
                            <td class="right"><?= $overdueCount ?> Item(s)</td>
                        </tr>
                        <tr>
                            <td>Lost & Found Items Logged Today:</td>
                            <td class="right"><?= $lfFound ?> Found / <?= $lfClaimed ?> Claimed</td>
                        </tr>
                    </table>
                </div>

                <div class="footer">
                    Automated report generated by Jowhara Hotel Luggage System at <?= date("H:i:s") ?>.
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

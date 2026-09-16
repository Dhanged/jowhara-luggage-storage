<?php
namespace App\Analytics;

class InventoryAnalytics
{
    /**
     * Get check-ins and check-outs per day over the last N days.
     */
    public function getDailyVolume(int $days = 30): array
    {
        $days = max(1, $days);
        $volume = \db_fetch_all(
            "SELECT 
                DATE(checkin_date) as date_label,
                COUNT(CASE WHEN status = 'Stored' OR status = 'Collected' THEN 1 END) as checkins,
                COUNT(CASE WHEN status = 'Collected' AND checkout_date IS NOT NULL THEN 1 END) as checkouts
             FROM luggage
             WHERE checkin_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND deleted_at IS NULL
             GROUP BY DATE(checkin_date)
             ORDER BY DATE(checkin_date) ASC",
            "i",
            [$days]
        );
        return $volume;
    }

    /**
     * Get hourly distribution of check-in events.
     */
    public function getPeakHours(): array
    {
        $peakHours = \db_fetch_all(
            "SELECT 
                HOUR(checkin_date) as hour_label,
                COUNT(*) as count
             FROM luggage
             WHERE deleted_at IS NULL
             GROUP BY HOUR(checkin_date)
             ORDER BY count DESC"
        );
        return $peakHours;
    }

    /**
     * Get revenue breakdown by payment method and currency over the last N days.
     */
    public function getRevenueBreakdown(int $days = 30): array
    {
        $days = max(1, $days);
        $revenue = \db_fetch_all(
            "SELECT 
                COALESCE(payment_method, 'Unknown') as method,
                COALESCE(currency_settled, 'USD') as currency,
                SUM(fee_amount) as total_amount,
                COUNT(*) as transaction_count
             FROM luggage
             WHERE payment_status = 'Paid' AND deleted_at IS NULL AND checkin_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY payment_method, currency_settled",
            "i",
            [$days]
        );
        return $revenue;
    }

    /**
     * Get average storage duration (in hours) between check-in and checkout.
     */
    public function getAverageStorageDuration(): float
    {
        $avgHours = \db_scalar(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, checkin_date, checkout_date)) 
             FROM luggage 
             WHERE status = 'Collected' AND checkout_date IS NOT NULL AND deleted_at IS NULL"
        );
        return $avgHours !== null ? round((float)$avgHours, 1) : 0.0;
    }

    /**
     * Get a summary of overdue items.
     */
    public function getOverdueSummary(): array
    {
        $overdueCount = (int)\db_scalar(
            "SELECT COUNT(*) 
             FROM luggage 
             WHERE status = 'Stored' AND expected_pickup_date IS NOT NULL AND expected_pickup_date < CURDATE() AND deleted_at IS NULL"
        );

        $oldestOverdue = \db_fetch_one(
            "SELECT l.tag_code, g.guest_name, l.expected_pickup_date, TIMESTAMPDIFF(DAY, l.expected_pickup_date, CURDATE()) as days_overdue
             FROM luggage l
             JOIN guests g ON g.guest_id = l.guest_id
             WHERE l.status = 'Stored' AND l.expected_pickup_date IS NOT NULL AND l.expected_pickup_date < CURDATE() AND l.deleted_at IS NULL
             ORDER BY l.expected_pickup_date ASC
             LIMIT 1"
        );

        return [
            'count' => $overdueCount,
            'oldest' => $oldestOverdue ?: null
        ];
    }

    /**
     * Get Lost & Found item status totals.
     */
    public function getLostFoundStats(): array
    {
        $stats = \db_fetch_all(
            "SELECT status, COUNT(*) as count 
             FROM lost_found_items 
             WHERE deleted_at IS NULL
             GROUP BY status"
        );
        
        $totals = ['Found' => 0, 'Claimed' => 0, 'Disposed' => 0];
        foreach ($stats as $row) {
            if (isset($totals[$row['status']])) {
                $totals[$row['status']] = (int)$row['count'];
            }
        }
        return $totals;
    }

    /**
     * Get storage shelf utilization and overall capacity.
     */
    public function getShelfUtilization(): array
    {
        return \get_shelf_utilization_analytics();
    }
}

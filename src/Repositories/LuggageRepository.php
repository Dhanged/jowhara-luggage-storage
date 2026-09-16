<?php
namespace App\Repositories;

class LuggageRepository
{
    public function findById(int $id): ?array
    {
        return \db_fetch_one(
            "SELECT l.*, g.guest_name, g.phone, g.room_no, g.email, g.id_type, g.id_number
             FROM luggage l
             JOIN guests g ON g.guest_id = l.guest_id
             WHERE l.luggage_id = ? AND l.deleted_at IS NULL",
            "i",
            [$id]
        );
    }

    public function findByTag(string $tagCode): ?array
    {
        return \db_fetch_one(
            "SELECT l.*, g.guest_name, g.phone, g.room_no, g.email
             FROM luggage l
             JOIN guests g ON g.guest_id = l.guest_id
             WHERE l.tag_code = ? AND l.deleted_at IS NULL",
            "s",
            [$tagCode]
        );
    }

    public function updateTagCode(int $luggageId, string $tagCode): void
    {
        \db_execute("UPDATE luggage SET tag_code = ? WHERE luggage_id = ?", "si", [$tagCode, $luggageId]);
    }

    /**
     * Retrieve full payment history from the normalized ledger.
     */
    public function getPaymentHistory(int $luggageId): array
    {
        return \db_fetch_all(
            "SELECT p.*, u.username AS processed_by_name
             FROM luggage_payments p
             LEFT JOIN users u ON u.user_id = p.processed_by
             WHERE p.luggage_id = ?
             ORDER BY p.payment_date DESC",
            "i",
            [$luggageId]
        );
    }

    /**
     * Retrieve the most recent normalized checkout record.
     */
    public function getCheckoutRecord(int $luggageId): ?array
    {
        return \db_fetch_one(
            "SELECT c.*, u.username AS processed_by_name
             FROM luggage_checkouts c
             LEFT JOIN users u ON u.user_id = c.processed_by
             WHERE c.luggage_id = ?
             ORDER BY c.checkout_date DESC
             LIMIT 1",
            "i",
            [$luggageId]
        );
    }

    /**
     * Retrieve total paid amount from the normalized payment ledger.
     */
    public function getTotalPaid(int $luggageId): float
    {
        return (float)\db_scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM luggage_payments WHERE luggage_id = ? AND status = 'Paid'",
            "i",
            [$luggageId]
        );
    }
}

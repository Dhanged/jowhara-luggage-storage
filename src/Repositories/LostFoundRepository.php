<?php
namespace App\Repositories;

class LostFoundRepository
{
    public function findById(int $id): ?array
    {
        return \db_fetch_one(
            "SELECT * FROM lost_found_items WHERE item_id = ? AND deleted_at IS NULL",
            "i",
            [$id]
        );
    }

    /**
     * Retrieve all claim events filed against a lost item.
     */
    public function getClaims(int $itemId): array
    {
        return \db_fetch_all(
            "SELECT c.*, u.username AS processed_by_name
             FROM lf_claims c
             LEFT JOIN users u ON u.user_id = c.processed_by
             WHERE c.item_id = ?
             ORDER BY c.claimed_at DESC",
            "i",
            [$itemId]
        );
    }

    /**
     * Retrieve the full custody chain for a lost item.
     */
    public function getCustodyHistory(int $itemId): array
    {
        return \db_fetch_all(
            "SELECT h.*, u.username AS changed_by_name
             FROM lf_custody_history h
             LEFT JOIN users u ON u.user_id = h.changed_by
             WHERE h.item_id = ?
             ORDER BY h.changed_at ASC",
            "i",
            [$itemId]
        );
    }

    /**
     * Retrieve the disposal record for a lost item (if any).
     */
    public function getDisposal(int $itemId): ?array
    {
        return \db_fetch_one(
            "SELECT d.*, u.username AS authorized_by_name
             FROM lf_disposals d
             LEFT JOIN users u ON u.user_id = d.authorized_by
             WHERE d.item_id = ?
             ORDER BY d.disposal_date DESC
             LIMIT 1",
            "i",
            [$itemId]
        );
    }
}

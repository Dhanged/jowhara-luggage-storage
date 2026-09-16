<?php
namespace App\Services;

class LostFoundService
{
    /**
     * Record a claim event in the normalized lf_claims table
     * and update the legacy lost_found_items row for backward compatibility.
     */
    public function recordClaim(
        int     $itemId,
        ?string $claimantName    = null,
        ?string $claimantPhone   = null,
        ?string $claimantIdNo    = null,
        ?string $claimantEmail   = null,
        ?string $relationship    = null,
        ?string $signaturePath   = null,
        ?string $claimPhotoPath  = null,
        ?string $notes           = null
    ): int {
        $userId = $_SESSION['user_id'] ?? null;

        // Write normalized claim record
        \db_execute(
            "INSERT INTO lf_claims
             (item_id, claimant_name, claimant_phone, claimant_id_number,
              claimant_email, relationship, signature_path, claim_photo_path, notes, processed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            "issssssssi",
            [$itemId, $claimantName, $claimantPhone, $claimantIdNo,
             $claimantEmail, $relationship, $signaturePath, $claimPhotoPath, $notes, $userId]
        );
        $claimId = \db_insert_id();

        // Append custody chain entry
        $this->appendCustody($itemId, null, 'Claimed', 'Claimed by ' . ($claimantName ?? 'Unknown'));

        // Dual-write legacy row (backward compatibility)
        \db_execute(
            "UPDATE lost_found_items
             SET status             = 'Claimed',
                 owner_name         = ?,
                 owner_phone        = ?,
                 claimant_id_number = ?,
                 signature_path     = ?,
                 claim_photo_path   = ?,
                 claimed_date       = NOW(),
                 claimed_by_user_id = ?
             WHERE item_id = ?",
            "sssssii",
            [$claimantName, $claimantPhone, $claimantIdNo,
             $signaturePath, $claimPhotoPath, $userId, $itemId]
        );

        \log_action("Lost & Found item claimed", "lost_found", $itemId,
            "Claimed by: " . ($claimantName ?? 'Unknown'));

        return $claimId;
    }

    /**
     * Record a disposal in the normalized lf_disposals table
     * and update the legacy lost_found_items row.
     */
    public function recordDisposal(
        int     $itemId,
        string  $method        = 'Discarded',
        ?string $reason        = null,
        ?string $witnessName   = null,
        ?string $notes         = null
    ): int {
        $userId = $_SESSION['user_id'] ?? null;

        \db_execute(
            "INSERT INTO lf_disposals (item_id, method, reason, authorized_by, witness_name, notes)
             VALUES (?, ?, ?, ?, ?, ?)",
            "ississ",
            [$itemId, $method, $reason, $userId, $witnessName, $notes]
        );
        $disposalId = \db_insert_id();

        // Append custody chain entry
        $this->appendCustody($itemId, null, 'Disposed', "{$method}: {$reason}");

        // Dual-write legacy row
        \db_execute(
            "UPDATE lost_found_items
             SET status          = 'Disposed',
                 disposal_date   = NOW(),
                 disposal_reason = ?
             WHERE item_id = ?",
            "si",
            [$reason, $itemId]
        );

        \log_action("Lost & Found item disposed", "lost_found", $itemId, "{$method}: {$reason}");

        return $disposalId;
    }

    /**
     * Append a custody chain transition.
     */
    public function appendCustody(int $itemId, ?string $fromStatus, string $toStatus, ?string $notes = null, ?string $location = null): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        \db_execute(
            "INSERT INTO lf_custody_history (item_id, from_status, to_status, location, notes, changed_by)
             VALUES (?, ?, ?, ?, ?, ?)",
            "issssi",
            [$itemId, $fromStatus, $toStatus, $location, $notes, $userId]
        );
    }

    /**
     * Retrieve full custody chain for a lost item.
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
     * Retrieve all claims filed for a lost item.
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
}

<?php
namespace App\Services;

use App\Repositories\LuggageRepository;

class CheckoutService
{
    private LuggageRepository $luggageRepo;

    public function __construct(?LuggageRepository $luggageRepo = null)
    {
        $this->luggageRepo = $luggageRepo ?? new LuggageRepository();
    }

    /**
     * Record a checkout event in the normalized checkout table
     * and update the legacy luggage row for backward compatibility.
     */
    public function recordCheckout(
        int     $luggageId,
        ?string $pickupPersonName  = null,
        ?string $pickupPersonPhone = null,
        ?string $pickupPersonId    = null,
        ?string $signaturePath     = null,
        ?string $checkoutPhoto     = null,
        ?string $conditionFlags    = null,
        ?string $notes             = null
    ): int {
        $userId = $_SESSION['user_id'] ?? null;

        // Write immutable checkout record to normalized table
        \db_execute(
            "INSERT INTO luggage_checkouts
             (luggage_id, pickup_person_name, pickup_person_phone, pickup_person_id,
              signature_path, checkout_photo, condition_flags, notes, processed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            "isssssssi",
            [$luggageId, $pickupPersonName, $pickupPersonPhone, $pickupPersonId,
             $signaturePath, $checkoutPhoto, $conditionFlags, $notes, $userId]
        );
        $checkoutId = \db_insert_id();

        // Dual-write to legacy luggage row (backward compatibility)
        \db_execute(
            "UPDATE luggage
             SET status                   = 'Collected',
                 checkout_date            = NOW(),
                 pickup_person_name       = ?,
                 pickup_person_phone      = ?,
                 pickup_person_id         = ?,
                 signature_path           = ?,
                 checkout_photo           = ?,
                 checkout_condition_flags = ?,
                 checked_out_by           = ?
             WHERE luggage_id = ?",
            "ssssssii",
            [$pickupPersonName, $pickupPersonPhone, $pickupPersonId,
             $signaturePath, $checkoutPhoto, $conditionFlags, $userId, $luggageId]
        );

        \log_action("Luggage checked out", "luggage", $luggageId,
            "Collected by: " . ($pickupPersonName ?? "Guest"));

        return $checkoutId;
    }

    /**
     * Retrieve the normalized checkout record for a luggage item.
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
}

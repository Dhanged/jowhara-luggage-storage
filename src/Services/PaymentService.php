<?php
namespace App\Services;

use App\Repositories\LuggageRepository;

class PaymentService
{
    private LuggageRepository $luggageRepo;

    public function __construct(?LuggageRepository $luggageRepo = null)
    {
        $this->luggageRepo = $luggageRepo ?? new LuggageRepository();
    }

    /**
     * Record a payment transaction in the normalized ledger
     * and update the legacy luggage row for backward compatibility.
     */
    public function recordPayment(
        int     $luggageId,
        float   $amount,
        string  $method     = 'Cash',
        string  $status     = 'Paid',
        ?string $reference  = null,
        string  $currency   = 'USD',
        float   $exchangeRate = 1.0,
        ?string $discountCode   = null,
        float   $discountAmount = 0.0,
        ?string $notes          = null
    ): int {
        global $conn;

        $userId = $_SESSION['user_id'] ?? null;

        // Write to normalized ledger
        \db_execute(
            "INSERT INTO luggage_payments
             (luggage_id, amount, method, reference, currency, exchange_rate, discount_code, discount_amount, status, notes, processed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            "idsssdsdsi",
            [$luggageId, $amount, $method, $reference, $currency, $exchangeRate, $discountCode, $discountAmount, $status, $notes, $userId]
        );
        $paymentId = \db_insert_id();

        // Dual-write to legacy luggage row (backward compatibility)
        \db_execute(
            "UPDATE luggage
             SET payment_status    = ?,
                 fee_amount        = ?,
                 payment_method    = ?,
                 payment_reference = ?,
                 currency_settled  = ?,
                 exchange_rate     = ?,
                 discount_code     = ?,
                 discount_amount   = ?
             WHERE luggage_id = ?",
            "sdsssddi",
            [$status, $amount, $method, $reference, $currency, $exchangeRate, $discountCode, $discountAmount, $luggageId]
        );

        \log_action("Payment recorded", "luggage", $luggageId, "{$method} {$currency} {$amount} (ref: {$reference})");

        return $paymentId;
    }

    /**
     * Retrieve full payment history for a luggage item.
     */
    public function getHistory(int $luggageId): array
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
     * Get the total amount paid for a luggage item.
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

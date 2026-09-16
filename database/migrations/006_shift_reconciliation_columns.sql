-- Migration 006: Add Shift Cash Reconciliation Columns to shift_handovers

ALTER TABLE shift_handovers
    ADD COLUMN IF NOT EXISTS opening_cash    DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER incoming_user_id,
    ADD COLUMN IF NOT EXISTS expected_cash   DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER opening_cash,
    ADD COLUMN IF NOT EXISTS actual_cash     DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER expected_cash,
    ADD COLUMN IF NOT EXISTS cash_variance   DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER actual_cash,
    ADD COLUMN IF NOT EXISTS card_total      DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER cash_variance,
    ADD COLUMN IF NOT EXISTS total_collected DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER card_total;

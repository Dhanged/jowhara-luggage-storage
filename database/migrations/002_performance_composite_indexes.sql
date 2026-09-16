-- Phase 4 Migration: Composite Range Indexes for Dashboard and Reports

ALTER TABLE luggage ADD INDEX IF NOT EXISTS idx_luggage_checkin_range (deleted_at, checkin_date, status);
ALTER TABLE luggage ADD INDEX IF NOT EXISTS idx_luggage_checkout_range (deleted_at, checkout_date, payment_status);

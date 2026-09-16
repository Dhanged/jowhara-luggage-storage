-- Phase 1 Migration: Foreign Keys & Composite Performance Indexes

-- Composite performance indexes
ALTER TABLE luggage ADD INDEX IF NOT EXISTS idx_luggage_active_status (deleted_at, status);
ALTER TABLE luggage ADD INDEX IF NOT EXISTS idx_luggage_guest_active (guest_id, deleted_at);
ALTER TABLE login_history ADD INDEX IF NOT EXISTS idx_login_rate (username, success, created_at);
ALTER TABLE login_history ADD INDEX IF NOT EXISTS idx_login_ip_rate (ip_address, success, created_at);

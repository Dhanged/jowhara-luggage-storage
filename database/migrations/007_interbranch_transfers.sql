-- Migration 007: Add Inter-Branch Transfer Tracking Columns to luggage_transfers

ALTER TABLE luggage_transfers
    ADD COLUMN IF NOT EXISTS from_branch_id INT DEFAULT 1 AFTER luggage_id,
    ADD COLUMN IF NOT EXISTS to_branch_id   INT DEFAULT 1 AFTER from_branch_id,
    ADD COLUMN IF NOT EXISTS courier_name   VARCHAR(120) DEFAULT NULL AFTER to_location,
    ADD COLUMN IF NOT EXISTS courier_phone  VARCHAR(60)  DEFAULT NULL AFTER courier_name,
    ADD COLUMN IF NOT EXISTS status         VARCHAR(30)  NOT NULL DEFAULT 'In Transit' AFTER courier_phone,
    ADD COLUMN IF NOT EXISTS received_by    INT          DEFAULT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS received_at    DATETIME     DEFAULT NULL AFTER received_by;

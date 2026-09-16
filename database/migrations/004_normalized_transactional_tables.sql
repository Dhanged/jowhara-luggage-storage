-- ============================================================
-- Migration 004: Normalized Transactional Tables
-- Section 19 — Database Architecture Evolution
-- Zero-downtime additive migration (no columns dropped)
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- 1. luggage_payments — dedicated payment ledger per luggage
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS luggage_payments (
    payment_id        INT AUTO_INCREMENT PRIMARY KEY,
    luggage_id        INT NOT NULL,
    payment_date      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    method            VARCHAR(60)  NOT NULL DEFAULT 'Cash',
    reference         VARCHAR(120) DEFAULT NULL,
    currency          VARCHAR(10)  NOT NULL DEFAULT 'USD',
    exchange_rate     DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    discount_code     VARCHAR(40)  DEFAULT NULL,
    discount_amount   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status            VARCHAR(30)  NOT NULL DEFAULT 'Paid',
    notes             TEXT         DEFAULT NULL,
    processed_by      INT          DEFAULT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lp_luggage FOREIGN KEY (luggage_id) REFERENCES luggage(luggage_id) ON DELETE CASCADE,
    CONSTRAINT fk_lp_user   FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_lp_luggage (luggage_id),
    INDEX idx_lp_date    (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- 2. luggage_checkouts — immutable checkout event record
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS luggage_checkouts (
    checkout_id          INT AUTO_INCREMENT PRIMARY KEY,
    luggage_id           INT NOT NULL,
    checkout_date        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pickup_person_name   VARCHAR(160) DEFAULT NULL,
    pickup_person_phone  VARCHAR(60)  DEFAULT NULL,
    pickup_person_id     VARCHAR(120) DEFAULT NULL,
    signature_path       VARCHAR(255) DEFAULT NULL,
    checkout_photo       VARCHAR(255) DEFAULT NULL,
    condition_flags      TEXT         DEFAULT NULL,
    notes                TEXT         DEFAULT NULL,
    processed_by         INT          DEFAULT NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lco_luggage FOREIGN KEY (luggage_id) REFERENCES luggage(luggage_id) ON DELETE CASCADE,
    CONSTRAINT fk_lco_user   FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_lco_luggage (luggage_id),
    INDEX idx_lco_date    (checkout_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- 3. lf_claims — normalized Lost & Found claim events
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lf_claims (
    claim_id           INT AUTO_INCREMENT PRIMARY KEY,
    item_id            INT NOT NULL,
    claimed_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimant_name      VARCHAR(160) DEFAULT NULL,
    claimant_phone     VARCHAR(60)  DEFAULT NULL,
    claimant_id_number VARCHAR(120) DEFAULT NULL,
    claimant_email     VARCHAR(160) DEFAULT NULL,
    relationship       VARCHAR(80)  DEFAULT NULL,
    signature_path     VARCHAR(255) DEFAULT NULL,
    claim_photo_path   VARCHAR(255) DEFAULT NULL,
    notes              TEXT         DEFAULT NULL,
    processed_by       INT          DEFAULT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lfc_item FOREIGN KEY (item_id) REFERENCES lost_found_items(item_id) ON DELETE CASCADE,
    CONSTRAINT fk_lfc_user FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_lfc_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- 4. lf_custody_history — full custody chain for lost items
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lf_custody_history (
    custody_id     INT AUTO_INCREMENT PRIMARY KEY,
    item_id        INT NOT NULL,
    from_status    VARCHAR(30)  DEFAULT NULL,
    to_status      VARCHAR(30)  NOT NULL,
    location       VARCHAR(160) DEFAULT NULL,
    notes          TEXT         DEFAULT NULL,
    changed_by     INT          DEFAULT NULL,
    changed_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lfch_item FOREIGN KEY (item_id) REFERENCES lost_found_items(item_id) ON DELETE CASCADE,
    CONSTRAINT fk_lfch_user FOREIGN KEY (changed_by)  REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_lfch_item (item_id),
    INDEX idx_lfch_date (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- 5. lf_disposals — dedicated disposal authorization records
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lf_disposals (
    disposal_id      INT AUTO_INCREMENT PRIMARY KEY,
    item_id          INT NOT NULL,
    disposal_date    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    method           VARCHAR(80)  DEFAULT 'Discarded',
    reason           TEXT         DEFAULT NULL,
    authorized_by    INT          DEFAULT NULL,
    witness_name     VARCHAR(160) DEFAULT NULL,
    notes            TEXT         DEFAULT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lfd_item FOREIGN KEY (item_id) REFERENCES lost_found_items(item_id) ON DELETE CASCADE,
    CONSTRAINT fk_lfd_user FOREIGN KEY (authorized_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_lfd_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

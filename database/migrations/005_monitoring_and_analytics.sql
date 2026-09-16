-- ============================================================
-- Migration 005: Monitoring & Analytics Support Tables
-- ============================================================

-- Application health log
CREATE TABLE IF NOT EXISTS app_health_log (
    log_id       INT AUTO_INCREMENT PRIMARY KEY,
    checked_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    check_name   VARCHAR(80)  NOT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'OK',   -- OK | WARN | CRITICAL
    details      TEXT         DEFAULT NULL,
    INDEX idx_ahl_checked (checked_at),
    INDEX idx_ahl_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backup lifecycle log
CREATE TABLE IF NOT EXISTS backup_log (
    backup_id      INT AUTO_INCREMENT PRIMARY KEY,
    filename       VARCHAR(255) NOT NULL,
    size_bytes     BIGINT       DEFAULT NULL,
    checksum       VARCHAR(64)  DEFAULT NULL,
    status         VARCHAR(30)  NOT NULL DEFAULT 'Created',  -- Created | Verified | Failed | Pruned
    offsite_synced TINYINT(1)   NOT NULL DEFAULT 0,
    restore_tested TINYINT(1)   NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bl_created (created_at),
    INDEX idx_bl_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

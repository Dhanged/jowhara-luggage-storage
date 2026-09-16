-- Phase 5 Migration: Multi-Branch Readiness & Advanced Features

CREATE TABLE IF NOT EXISTS branches (
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(160) NOT NULL,
    branch_code VARCHAR(40) NOT NULL UNIQUE,
    address TEXT DEFAULT NULL,
    phone VARCHAR(60) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default main branch
INSERT INTO branches (branch_id, branch_name, branch_code, address) 
VALUES (1, 'Jowhara Hotel Main Branch', 'MAIN', 'Mogadishu, Somalia')
ON DUPLICATE KEY UPDATE branch_name = branch_name;

-- Add branch_id columns to core entities for multi-branch readiness
ALTER TABLE users ADD COLUMN IF NOT EXISTS branch_id INT NOT NULL DEFAULT 1 AFTER role;
ALTER TABLE guests ADD COLUMN IF NOT EXISTS branch_id INT NOT NULL DEFAULT 1 AFTER room_no;
ALTER TABLE luggage ADD COLUMN IF NOT EXISTS branch_id INT NOT NULL DEFAULT 1 AFTER guest_id;
ALTER TABLE storage_shelves ADD COLUMN IF NOT EXISTS branch_id INT NOT NULL DEFAULT 1 AFTER zone_name;

-- Multi-branch indexes
ALTER TABLE luggage ADD INDEX IF NOT EXISTS idx_luggage_branch (branch_id, status);
ALTER TABLE guests ADD INDEX IF NOT EXISTS idx_guests_branch (branch_id);

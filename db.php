<?php
// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($name, $_ENV) && getenv($name) === false) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

$db_host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? "localhost");
$db_port = (int)(getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 3306));
$db_user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? "root");
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($_ENV['DB_PASS'] ?? "");
$db_name = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? "luggage_storage");

mysqli_report(MYSQLI_REPORT_OFF);

try {
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    if ($conn->connect_errno) {
        throw new Exception($conn->connect_error, $conn->connect_errno);
    }
    $conn->set_charset("utf8mb4");
} catch (Throwable $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed. Please verify database configuration.");
}

function db_column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_column() > 0;
}

function db_index_exists(mysqli $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
    );
    $stmt->bind_param("ss", $table, $index);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_column() > 0;
}

function db_add_column(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!db_column_exists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN $definition");
    }
}

function db_ensure_schema(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            username VARCHAR(60) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(30) NOT NULL DEFAULT 'receptionist',
            status VARCHAR(20) NOT NULL DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db_add_column($conn, "users", "full_name", "full_name VARCHAR(120) NOT NULL DEFAULT '' AFTER user_id");
    db_add_column($conn, "users", "password_hash", "password_hash VARCHAR(255) NOT NULL DEFAULT '' AFTER username");
    db_add_column($conn, "users", "role", "role VARCHAR(30) NOT NULL DEFAULT 'receptionist' AFTER password_hash");
    db_add_column($conn, "users", "status", "status VARCHAR(20) NOT NULL DEFAULT 'Active' AFTER role");
    db_add_column($conn, "users", "created_at", "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

    $conn->query(
        "CREATE TABLE IF NOT EXISTS guests (
            guest_id INT AUTO_INCREMENT PRIMARY KEY,
            guest_name VARCHAR(160) NOT NULL,
            phone VARCHAR(60) DEFAULT NULL,
            room_no VARCHAR(40) DEFAULT NULL,
            email VARCHAR(160) DEFAULT NULL,
            id_type VARCHAR(60) DEFAULT NULL,
            id_number VARCHAR(120) DEFAULT NULL,
            id_photo VARCHAR(255) DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db_add_column($conn, "guests", "email", "email VARCHAR(160) DEFAULT NULL AFTER room_no");
    db_add_column($conn, "guests", "id_type", "id_type VARCHAR(60) DEFAULT NULL AFTER email");
    db_add_column($conn, "guests", "id_number", "id_number VARCHAR(120) DEFAULT NULL AFTER id_type");
    db_add_column($conn, "guests", "id_photo", "id_photo VARCHAR(255) DEFAULT NULL AFTER id_number");
    db_add_column($conn, "guests", "deleted_at", "deleted_at DATETIME DEFAULT NULL AFTER id_photo");
    db_add_column($conn, "guests", "created_at", "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    db_add_column($conn, "guests", "updated_at", "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    $conn->query(
        "CREATE TABLE IF NOT EXISTS luggage (
            luggage_id INT AUTO_INCREMENT PRIMARY KEY,
            guest_id INT NOT NULL,
            tag_code VARCHAR(40) DEFAULT NULL,
            luggage_type VARCHAR(120) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            color VARCHAR(80) DEFAULT NULL,
            storage_zone VARCHAR(80) DEFAULT NULL,
            storage_location VARCHAR(120) DEFAULT NULL,
            photo VARCHAR(255) DEFAULT NULL,
            condition_flags TEXT DEFAULT NULL,
            reservation_no VARCHAR(80) DEFAULT NULL,
            expected_pickup_date DATE DEFAULT NULL,
            fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_status VARCHAR(30) NOT NULL DEFAULT 'Unpaid',
            payment_method VARCHAR(60) DEFAULT NULL,
            payment_reference VARCHAR(120) DEFAULT NULL,
            pickup_person_name VARCHAR(160) DEFAULT NULL,
            pickup_person_phone VARCHAR(60) DEFAULT NULL,
            pickup_person_id VARCHAR(120) DEFAULT NULL,
            signature_path VARCHAR(255) DEFAULT NULL,
            notification_preference VARCHAR(30) DEFAULT 'None',
            email_sent_at DATETIME DEFAULT NULL,
            sms_sent_at DATETIME DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Stored',
            checkin_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            checkout_date DATETIME DEFAULT NULL,
            checked_out_by INT DEFAULT NULL,
            created_by INT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db_add_column($conn, "luggage", "tag_code", "tag_code VARCHAR(40) DEFAULT NULL AFTER guest_id");
    db_add_column($conn, "luggage", "storage_zone", "storage_zone VARCHAR(80) DEFAULT NULL AFTER color");
    db_add_column($conn, "luggage", "photo", "photo VARCHAR(255) DEFAULT NULL AFTER storage_location");
    db_add_column($conn, "luggage", "checkout_photo", "checkout_photo VARCHAR(255) DEFAULT NULL AFTER photo");
    db_add_column($conn, "luggage", "condition_flags", "condition_flags TEXT DEFAULT NULL AFTER photo");
    db_add_column($conn, "luggage", "checkout_condition_flags", "checkout_condition_flags TEXT DEFAULT NULL AFTER condition_flags");
    db_add_column($conn, "luggage", "reservation_no", "reservation_no VARCHAR(80) DEFAULT NULL AFTER condition_flags");
    db_add_column($conn, "luggage", "expected_pickup_date", "expected_pickup_date DATE DEFAULT NULL AFTER reservation_no");
    db_add_column($conn, "luggage", "fee_amount", "fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER expected_pickup_date");
    db_add_column($conn, "luggage", "payment_status", "payment_status VARCHAR(30) NOT NULL DEFAULT 'Unpaid' AFTER fee_amount");
    db_add_column($conn, "luggage", "payment_method", "payment_method VARCHAR(60) DEFAULT NULL AFTER payment_status");
    db_add_column($conn, "luggage", "payment_reference", "payment_reference VARCHAR(120) DEFAULT NULL AFTER payment_method");
    db_add_column($conn, "luggage", "pickup_person_name", "pickup_person_name VARCHAR(160) DEFAULT NULL AFTER payment_reference");
    db_add_column($conn, "luggage", "pickup_person_phone", "pickup_person_phone VARCHAR(60) DEFAULT NULL AFTER pickup_person_name");
    db_add_column($conn, "luggage", "pickup_person_id", "pickup_person_id VARCHAR(120) DEFAULT NULL AFTER pickup_person_phone");
    db_add_column($conn, "luggage", "signature_path", "signature_path VARCHAR(255) DEFAULT NULL AFTER pickup_person_id");
    db_add_column($conn, "luggage", "notification_preference", "notification_preference VARCHAR(30) DEFAULT 'None' AFTER signature_path");
    db_add_column($conn, "luggage", "email_sent_at", "email_sent_at DATETIME DEFAULT NULL AFTER notification_preference");
    db_add_column($conn, "luggage", "sms_sent_at", "sms_sent_at DATETIME DEFAULT NULL AFTER email_sent_at");
    db_add_column($conn, "luggage", "deleted_at", "deleted_at DATETIME DEFAULT NULL AFTER sms_sent_at");
    db_add_column($conn, "luggage", "notes", "notes TEXT DEFAULT NULL AFTER deleted_at");
    db_add_column($conn, "luggage", "status", "status VARCHAR(30) NOT NULL DEFAULT 'Stored'");
    db_add_column($conn, "luggage", "checkin_date", "checkin_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    db_add_column($conn, "luggage", "checkout_date", "checkout_date DATETIME DEFAULT NULL");
    db_add_column($conn, "luggage", "checked_out_by", "checked_out_by INT DEFAULT NULL");
    db_add_column($conn, "luggage", "created_by", "created_by INT DEFAULT NULL");
    db_add_column($conn, "luggage", "updated_at", "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    if (!db_index_exists($conn, "luggage", "luggage_tag_code_unique")) {
        try {
            $conn->query("CREATE UNIQUE INDEX luggage_tag_code_unique ON luggage(tag_code)");
        } catch (Throwable $e) {
            // Existing duplicate legacy data should not block the app from loading.
        }
    }

    // Performance indexes
    $indexes = [
        "luggage" => [
            "idx_luggage_guest" => "guest_id",
            "idx_luggage_status" => "status",
            "idx_luggage_checkin" => "checkin_date",
            "idx_luggage_deleted" => "deleted_at"
        ],
        "audit_logs" => [
            "idx_audit_user" => "user_id",
            "idx_audit_created" => "created_at"
        ],
        "login_history" => [
            "idx_login_user" => "user_id",
            "idx_login_created" => "created_at"
        ],
        "lost_found_items" => [
            "idx_lost_found_status" => "status",
            "idx_lost_found_deleted" => "deleted_at"
        ]
    ];

    foreach ($indexes as $table => $tbl_indexes) {
        foreach ($tbl_indexes as $idx_name => $columns) {
            if (!db_index_exists($conn, $table, $idx_name)) {
                try {
                    $conn->query("CREATE INDEX `$idx_name` ON `$table`($columns)");
                } catch (Throwable $e) {
                    // Ignore index creation failure if it already exists or fails due to permissions
                }
            }
        }
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS audit_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            action VARCHAR(120) NOT NULL,
            entity_type VARCHAR(60) DEFAULT NULL,
            entity_id INT DEFAULT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(60) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS login_history (
            login_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            username VARCHAR(60) DEFAULT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            ip_address VARCHAR(60) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS storage_shelves (
            shelf_id INT AUTO_INCREMENT PRIMARY KEY,
            zone_name VARCHAR(80) NOT NULL,
            shelf_name VARCHAR(120) NOT NULL,
            capacity INT NOT NULL DEFAULT 10,
            status VARCHAR(30) NOT NULL DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            luggage_id INT DEFAULT NULL,
            guest_id INT DEFAULT NULL,
            channel VARCHAR(30) NOT NULL,
            recipient VARCHAR(160) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Prepared',
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // New Enterprise Systems Tables
    $conn->query(
        "CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(80) PRIMARY KEY,
            setting_value TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $default_settings = [
        "daily_penalty_fee" => "5.00",
        "grace_hours" => "2",
        "sms_enabled" => "0",
        "sms_api_endpoint" => "https://api.sms.hormuud.com/v1/send",
        "sms_api_key" => "",
        "sms_sender_id" => "JOWHARA",
        "whatsapp_enabled" => "0",
        "whatsapp_api_endpoint" => "https://api.whatsapp.com/v1/send",
        "whatsapp_api_token" => ""
    ];
    foreach ($default_settings as $key => $val) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_key = setting_key");
        $stmt->bind_param("ss", $key, $val);
        $stmt->execute();
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS luggage_transfers (
            transfer_id INT AUTO_INCREMENT PRIMARY KEY,
            luggage_id INT NOT NULL,
            from_zone VARCHAR(80) DEFAULT NULL,
            from_location VARCHAR(120) DEFAULT NULL,
            to_zone VARCHAR(80) DEFAULT NULL,
            to_location VARCHAR(120) DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            moved_by INT NOT NULL,
            moved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS shift_handovers (
            handover_id INT AUTO_INCREMENT PRIMARY KEY,
            outgoing_user_id INT NOT NULL,
            incoming_user_id INT NOT NULL,
            handover_notes TEXT NOT NULL,
            status VARCHAR(30) DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            acknowledged_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Add specification & audit columns to luggage table
    db_add_column($conn, "guests", "is_vip", "is_vip TINYINT(1) DEFAULT 0 AFTER guest_name");
    db_add_column($conn, "guests", "is_blacklisted", "is_blacklisted TINYINT(1) DEFAULT 0 AFTER is_vip");
    db_add_column($conn, "guests", "blacklist_reason", "blacklist_reason TEXT DEFAULT NULL AFTER is_blacklisted");
    db_add_column($conn, "guests", "group_code", "group_code VARCHAR(80) DEFAULT NULL AFTER blacklist_reason");

    db_add_column($conn, "luggage", "security_seal_code", "security_seal_code VARCHAR(80) DEFAULT NULL AFTER tag_code");
    db_add_column($conn, "luggage", "is_high_value", "is_high_value TINYINT(1) DEFAULT 0 AFTER security_seal_code");
    db_add_column($conn, "luggage", "declared_value", "declared_value DECIMAL(10,2) DEFAULT 0.00 AFTER is_high_value");
    db_add_column($conn, "luggage", "declared_items_desc", "declared_items_desc TEXT DEFAULT NULL AFTER declared_value");
    db_add_column($conn, "luggage", "weight_class", "weight_class VARCHAR(30) DEFAULT 'Light' AFTER declared_items_desc");
    db_add_column($conn, "luggage", "weight_kg", "weight_kg DECIMAL(5,2) DEFAULT NULL AFTER weight_class");
    db_add_column($conn, "luggage", "photo_2", "photo_2 VARCHAR(255) DEFAULT NULL AFTER photo");
    db_add_column($conn, "luggage", "photo_3", "photo_3 VARCHAR(255) DEFAULT NULL AFTER photo_2");
    db_add_column($conn, "luggage", "discount_code", "discount_code VARCHAR(40) DEFAULT NULL AFTER fee_amount");
    db_add_column($conn, "luggage", "discount_amount", "discount_amount DECIMAL(10,2) DEFAULT 0.00 AFTER discount_code");
    db_add_column($conn, "luggage", "currency_settled", "currency_settled VARCHAR(10) DEFAULT 'USD' AFTER discount_amount");
    db_add_column($conn, "luggage", "exchange_rate", "exchange_rate DECIMAL(10,4) DEFAULT 1.0000 AFTER currency_settled");

    // Create Lost & Found Items table
    $conn->query(
        "CREATE TABLE IF NOT EXISTS lost_found_items (
            item_id INT AUTO_INCREMENT PRIMARY KEY,
            reference_no VARCHAR(40) DEFAULT NULL,
            item_name VARCHAR(160) NOT NULL,
            category VARCHAR(80) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            item_condition VARCHAR(80) DEFAULT NULL,
            estimated_value DECIMAL(10,2) DEFAULT 0.00,
            location_found VARCHAR(160) DEFAULT NULL,
            secure_location VARCHAR(160) DEFAULT NULL,
            found_by VARCHAR(120) DEFAULT NULL,
            finder_contact VARCHAR(120) DEFAULT NULL,
            found_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(30) NOT NULL DEFAULT 'Found',
            photo_path VARCHAR(255) DEFAULT NULL,
            owner_name VARCHAR(160) DEFAULT NULL,
            owner_phone VARCHAR(60) DEFAULT NULL,
            guest_name VARCHAR(160) DEFAULT NULL,
            guest_phone VARCHAR(60) DEFAULT NULL,
            claimant_id_number VARCHAR(120) DEFAULT NULL,
            claimed_date DATETIME DEFAULT NULL,
            claimed_by_user_id INT DEFAULT NULL,
            signature_path VARCHAR(255) DEFAULT NULL,
            claim_photo_path VARCHAR(255) DEFAULT NULL,
            disposal_date DATETIME DEFAULT NULL,
            disposal_reason TEXT DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    db_add_column($conn, "lost_found_items", "reference_no", "reference_no VARCHAR(40) DEFAULT NULL AFTER item_id");
    db_add_column($conn, "lost_found_items", "category", "category VARCHAR(80) DEFAULT NULL AFTER item_name");
    db_add_column($conn, "lost_found_items", "item_condition", "item_condition VARCHAR(80) DEFAULT NULL AFTER description");
    db_add_column($conn, "lost_found_items", "estimated_value", "estimated_value DECIMAL(10,2) DEFAULT 0.00 AFTER item_condition");
    db_add_column($conn, "lost_found_items", "secure_location", "secure_location VARCHAR(160) DEFAULT NULL AFTER location_found");
    db_add_column($conn, "lost_found_items", "finder_contact", "finder_contact VARCHAR(120) DEFAULT NULL AFTER found_by");
    db_add_column($conn, "lost_found_items", "owner_name", "owner_name VARCHAR(160) DEFAULT NULL AFTER photo_path");
    db_add_column($conn, "lost_found_items", "owner_phone", "owner_phone VARCHAR(60) DEFAULT NULL AFTER owner_name");
    db_add_column($conn, "lost_found_items", "claimant_id_number", "claimant_id_number VARCHAR(120) DEFAULT NULL AFTER guest_phone");
    db_add_column($conn, "lost_found_items", "disposal_date", "disposal_date DATETIME DEFAULT NULL AFTER claim_photo_path");
    db_add_column($conn, "lost_found_items", "disposal_reason", "disposal_reason TEXT DEFAULT NULL AFTER disposal_date");
    db_add_column($conn, "lost_found_items", "created_by", "created_by INT DEFAULT NULL AFTER disposal_reason");

    // Import history table
    $conn->query(
        "CREATE TABLE IF NOT EXISTS import_history (
            import_id INT AUTO_INCREMENT PRIMARY KEY,
            import_type VARCHAR(60) NOT NULL,
            filename VARCHAR(255) DEFAULT NULL,
            row_count INT DEFAULT 0,
            success_count INT DEFAULT 0,
            error_count INT DEFAULT 0,
            status VARCHAR(30) DEFAULT 'Completed',
            imported_by INT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Approval requests table
    $conn->query(
        "CREATE TABLE IF NOT EXISTS approval_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(60) NOT NULL,
            entity_id INT NOT NULL,
            requested_by INT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            approved_by INT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Extra user columns
    db_add_column($conn, 'users', 'deleted_at',     'deleted_at DATETIME DEFAULT NULL');
    db_add_column($conn, 'users', 'email',           'email VARCHAR(160) DEFAULT NULL');
    db_add_column($conn, 'users', 'phone',           'phone VARCHAR(60) DEFAULT NULL');
    db_add_column($conn, 'users', 'last_login_at',   'last_login_at DATETIME DEFAULT NULL');

    // Extra system_settings defaults
    $extra_settings = [
        'company_name'    => 'Jowhara International Hotel',
        'company_address' => '',
        'company_phone'   => '',
        'company_email'   => '',
        'company_logo'    => 'assets/logo.jpg',
        'smtp_host'       => 'smtp.gmail.com',
        'smtp_port'       => '587',
        'smtp_user'       => '',
        'smtp_pass'       => '',
        'smtp_from'       => 'noreply@jowharahotel.com',
        'smtp_from_name'  => 'Jowhara Hotel',
        'currency_symbol' => '$',
        'currency_code'   => 'USD',
        'date_format'     => 'Y-m-d',
        'app_version'     => '2.0.0',
        'auto_backup'     => '0',
    ];
    foreach ($extra_settings as $k => $v) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_key = setting_key");
        $stmt->bind_param('ss', $k, $v);
        $stmt->execute();
    }

    // Create the first administrator only when explicitly configured.
    $initial_admin_user = (string) (getenv('INIT_ADMIN_USER') ?: '');
    $initial_admin_password = (string) (getenv('INIT_ADMIN_PASSWORD') ?: '');
    if ($initial_admin_user !== '' && strlen($initial_admin_password) >= 12) {
        $result = $conn->query("SELECT COUNT(*) AS total FROM users");
        $user_count = (int) ($result->fetch_assoc()['total'] ?? 0);
        if ($user_count === 0) {
            $hash = password_hash($initial_admin_password, PASSWORD_DEFAULT);
            $name = 'System Admin';
            $role = 'admin';
            $stmt = $conn->prepare(
                "INSERT INTO users (full_name, username, password_hash, role, status)
                 VALUES (?, ?, ?, ?, 'Active')"
            );
            $stmt->bind_param('ssss', $name, $initial_admin_user, $hash, $role);
            $stmt->execute();
        }
    }

    $missing_tags = $conn->query(
        "SELECT luggage_id FROM luggage WHERE tag_code IS NULL OR tag_code = '' ORDER BY luggage_id"
    );
    while ($row = $missing_tags->fetch_assoc()) {
        $tag = "LS" . str_pad((string)$row["luggage_id"], 6, "0", STR_PAD_LEFT);
        $stmt = $conn->prepare("UPDATE luggage SET tag_code = ? WHERE luggage_id = ?");
        $stmt->bind_param("si", $tag, $row["luggage_id"]);
        $stmt->execute();
    }

    $shelf_count = (int)$conn->query("SELECT COUNT(*) FROM storage_shelves")->fetch_column();
    if ($shelf_count === 0) {
        $stmt = $conn->prepare(
            "INSERT INTO storage_shelves (zone_name, shelf_name, capacity, status) VALUES (?, ?, ?, 'Active')"
        );
        foreach (["A" => 12, "B" => 10, "C" => 8] as $zone => $capacity) {
            for ($i = 1; $i <= 4; $i++) {
                $shelf = $zone . "-" . $i;
                $stmt->bind_param("ssi", $zone, $shelf, $capacity);
                $stmt->execute();
            }
        }
    }

    // ── v3.0: In-App User Notifications ──────────────────────────────────
    $conn->query(
        "CREATE TABLE IF NOT EXISTS user_notifications (
            notif_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            body TEXT DEFAULT NULL,
            link VARCHAR(255) DEFAULT NULL,
            type VARCHAR(40) NOT NULL DEFAULT 'info',
            read_at DATETIME DEFAULT NULL,
            is_dismissed TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notif_user (user_id),
            INDEX idx_notif_read (read_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // ── v3.0: Announcement System ─────────────────────────────────────────
    $conn->query(
        "CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            body TEXT DEFAULT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'info',
            created_by INT DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // ── v3.0: 2FA columns on users ────────────────────────────────────────
    db_add_column($conn, 'users', 'totp_secret',  'totp_secret VARCHAR(64) DEFAULT NULL');
    db_add_column($conn, 'users', 'totp_enabled', 'totp_enabled TINYINT(1) NOT NULL DEFAULT 0');

    // ── v3.0: App version bump ────────────────────────────────────────────
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('app_version', '3.0.0') ON DUPLICATE KEY UPDATE setting_value = '3.0.0'");
    $stmt->execute();

    // Align table collations to prevent "Illegal mix of collations" errors during JOIN operations
    try {
        $conn->query("ALTER TABLE luggage CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->query("ALTER TABLE storage_shelves CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->query("ALTER TABLE lost_found_items CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->query("ALTER TABLE user_notifications CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->query("ALTER TABLE announcements CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->query("ALTER TABLE approval_requests CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        // Fallback or ignore if the database user doesn't have ALTER permissions
    }

    try {
        $flag_file = __DIR__ . '/storage/schema_initialized.flag';
        if (!file_exists($flag_file)) {
            @file_put_contents($flag_file, date('c'));
        }
    } catch (Throwable $e) {
        // Ignore flag write error
    }
}

// Only run full schema check if flag file is missing or explicitly forced
if (!file_exists(__DIR__ . '/storage/schema_initialized.flag') || isset($_GET['force_schema_sync'])) {
    db_ensure_schema($conn);
}

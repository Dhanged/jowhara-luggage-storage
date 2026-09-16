<?php
require_once __DIR__ . "/functions.php";

// Require admin privilege if accessed via HTTP browser, or allow CLI execution
if (php_sapi_name() !== 'cli') {
    require_admin();
}

global $conn;

echo "--- JOWHARA LUGGAGE SYSTEM MIGRATION RUNNER ---\n";

// Ensure schema initialization flag is set
$flag_file = __DIR__ . '/storage/schema_initialized.flag';
if (!file_exists($flag_file)) {
    @file_put_contents($flag_file, date('c'));
}

// 1. Ensure migrations tracking table exists
$conn->query(
    "CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$migration_dir = __DIR__ . '/database/migrations';
if (!is_dir($migration_dir)) {
    mkdir($migration_dir, 0775, true);
}

$files = glob($migration_dir . '/*.sql');
sort($files);

$executed = db_fetch_all("SELECT migration_name FROM migrations");
$executed_names = array_column($executed, 'migration_name');

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $executed_names, true)) {
        continue;
    }

    echo "Executing migration: {$name} ... ";
    $sql = file_get_contents($file);

    // Split SQL by semicolon or execute statements individually
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $conn->begin_transaction();
    try {
        foreach ($statements as $stmt_sql) {
            if (empty($stmt_sql)) continue;
            // Ignore single-line SQL comments
            if (strpos($stmt_sql, '--') === 0 && strpos($stmt_sql, "\n") === false) continue;
            $conn->query($stmt_sql);
        }

        $stmt = $conn->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();

        $conn->commit();
        echo "SUCCESS\n";
        $count++;
    } catch (Throwable $e) {
        $conn->rollback();
        echo "FAILED: " . $e->getMessage() . "\n";
        break;
    }
}

if ($count === 0) {
    echo "No pending migrations to run.\n";
} else {
    echo "Successfully executed {$count} migration(s).\n";
}

if (php_sapi_name() !== 'cli') {
    set_flash("success", "Database migrations executed successfully.");
    redirect("admin_database.php");
}

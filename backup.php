<?php
require_once __DIR__ . "/functions.php";
require_admin();

global $conn, $db_name;

log_action("Downloaded database backup", "database", null, "SQL backup generated");

$filename = "luggage_storage_backup_" . date("Ymd_His") . ".sql";
header("Content-Type: application/sql; charset=utf-8");
header("Content-Disposition: attachment; filename=$filename");
header("Pragma: no-cache");
header("Expires: 0");

echo "-- Luggage Storage Management System backup\n";
echo "-- Generated: " . date("Y-m-d H:i:s") . "\n\n";

$tables_result = $conn->query("SHOW TABLES");
while ($table_row = $tables_result->fetch_array()) {
    $table = $table_row[0];
    $create_result = $conn->query("SHOW CREATE TABLE `$table`")->fetch_assoc();
    $create_sql = $create_result["Create Table"];

    echo "DROP TABLE IF EXISTS `$table`;\n";
    echo $create_sql . ";\n\n";

    $rows = $conn->query("SELECT * FROM `$table`");
    while ($row = $rows->fetch_assoc()) {
        $columns = array_map(fn($col) => "`" . str_replace("`", "``", $col) . "`", array_keys($row));
        $values = array_map(function ($value) use ($conn) {
            if ($value === null) {
                return "NULL";
            }
            return "'" . $conn->real_escape_string((string)$value) . "'";
        }, array_values($row));

        echo "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
    }
    echo "\n";
}

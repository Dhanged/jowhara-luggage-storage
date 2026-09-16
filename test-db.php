<?php
/**
 * test-db.php — InfinityFree Shared Hosting Database Connection Test
 * Safe connection test script — never discloses sensitive credentials.
 */

// Load environment variables from .env if present
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
    
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Failed to set charset to utf8mb4");
    }

    $res = $conn->query("SELECT 1");
    if (!$res || $res->fetch_row()[0] != 1) {
        throw new Exception("SELECT 1 test query failed");
    }

    // Display strict required output format
    echo "SUCCESS:\nDatabase connection successful.\n";
    
    // Additional safe diagnostic details (no password exposed)
    echo "\nDiagnostics:\n";
    echo "- Hostname: " . htmlspecialchars($db_host) . "\n";
    echo "- Username: " . htmlspecialchars($db_user) . "\n";
    echo "- Database: " . htmlspecialchars($db_name) . "\n";
    echo "- Port:     " . (int)$db_port . "\n";
    echo "- Charset:  " . $conn->character_set_name() . "\n";
    echo "- Query:    SELECT 1 => OK\n";

} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR:\nDatabase connection failed.\n";
}

<?php
require_once __DIR__ . "/functions.php";

// Allow CLI execution or web access with token/session
if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    $secret = get_setting('monitoring_token', '');
    $isAdmin = false;
    
    // Check session as fallback for web administrators
    if (session_status() !== PHP_SESSION_NONE && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $isAdmin = true;
    }

    if (!$isAdmin && (empty($secret) || !hash_equals($secret, $token))) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['status' => 'ERROR', 'message' => 'Unauthorized health check access.']);
        exit;
    }
}

$checker = new \App\Monitoring\HealthChecker();
$report = $checker->runChecks();

if (php_sapi_name() === 'cli') {
    echo "=== JOWHARA HOTEL LUGGAGE STORAGE - HEALTH REPORT ===\n";
    echo "Overall Status: [" . $report['status'] . "]\n";
    echo "Checked At:     " . $report['timestamp'] . "\n\n";
    
    foreach ($report['checks'] as $name => $check) {
        $statusStr = str_pad("[" . $check['status'] . "]", 12);
        echo " - " . str_pad($name, 22) . ": {$statusStr} {$check['message']}\n";
    }
    echo "\n";
    if ($report['status'] === 'CRITICAL') {
        exit(1);
    }
    exit(0);
} else {
    header('Content-Type: application/json');
    if ($report['status'] === 'CRITICAL') {
        http_response_code(500);
    }
    echo json_encode($report, JSON_PRETTY_PRINT);
    exit;
}

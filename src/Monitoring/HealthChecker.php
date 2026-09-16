<?php
namespace App\Monitoring;

class HealthChecker
{
    /**
     * Run all health checks and return the results.
     */
    public function runChecks(): array
    {
        $results = [];

        // 1. Database connection check
        $results['database'] = $this->checkDatabase();

        // 2. Disk space check
        $results['disk_space'] = $this->checkDiskSpace();

        // 3. Error log check
        $results['error_log'] = $this->checkErrorLog();

        // 4. Backup recency check
        $results['backup_recency'] = $this->checkBackupRecency();

        // 5. Failed notifications check
        $results['failed_notifications'] = $this->checkFailedNotifications();

        // Determine overall status
        $overallStatus = 'OK';
        foreach ($results as $check) {
            if ($check['status'] === 'CRITICAL') {
                $overallStatus = 'CRITICAL';
                break;
            } elseif ($check['status'] === 'WARN') {
                $overallStatus = 'WARN';
            }
        }

        // Log results to DB
        foreach ($results as $name => $check) {
            $this->logHealthCheck($name, $check['status'], $check['message']);
        }

        return [
            'status' => $overallStatus,
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => $results
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $res = \db_scalar("SELECT 1");
            if ($res == 1) {
                return ['status' => 'OK', 'message' => 'Database connection is functional.'];
            }
            return ['status' => 'CRITICAL', 'message' => 'Database returned unexpected value.'];
        } catch (\Throwable $e) {
            return ['status' => 'CRITICAL', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    private function checkDiskSpace(): array
    {
        $path = __DIR__;
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        if ($total === false || $free === false) {
            return ['status' => 'WARN', 'message' => 'Unable to determine disk space.'];
        }

        $freePercent = ($free / $total) * 100;
        $freeGb = round($free / (1024 * 1024 * 1024), 2);
        $totalGb = round($total / (1024 * 1024 * 1024), 2);
        
        $msg = "{$freeGb}GB free of {$totalGb}GB (" . round($freePercent, 1) . "% free).";

        if ($freePercent < 10) {
            return ['status' => 'CRITICAL', 'message' => 'Disk space critically low: ' . $msg];
        } elseif ($freePercent < 15) {
            return ['status' => 'WARN', 'message' => 'Disk space low: ' . $msg];
        }
        return ['status' => 'OK', 'message' => $msg];
    }

    private function checkErrorLog(): array
    {
        $logFile = dirname(__DIR__, 2) . '/storage/logs/app_errors.log';
        if (!is_file($logFile)) {
            return ['status' => 'OK', 'message' => 'Error log file does not exist (no errors logged).'];
        }

        $size = filesize($logFile);
        $sizeMb = round($size / (1024 * 1024), 2);
        $msg = "Error log size is {$sizeMb}MB.";

        if ($size > 10 * 1024 * 1024) { // 10MB
            return ['status' => 'WARN', 'message' => $msg . ' File should be rotated or cleaned up.'];
        }
        return ['status' => 'OK', 'message' => $msg];
    }

    private function checkBackupRecency(): array
    {
        $backupDir = dirname(__DIR__, 2) . '/storage/backups';
        $backupFiles = glob($backupDir . '/backup_*.sql');
        if (empty($backupFiles)) {
            return ['status' => 'CRITICAL', 'message' => 'No database backup files found.'];
        }

        // Find newest file
        $newestTime = 0;
        foreach ($backupFiles as $file) {
            $mtime = filemtime($file);
            if ($mtime > $newestTime) {
                $newestTime = $mtime;
            }
        }

        $ageHours = (time() - $newestTime) / 3600;
        $msg = "Last backup is " . round($ageHours, 1) . " hour(s) old.";

        if ($ageHours > 25) { // 25 hours (daily + 1 hour margin)
            return ['status' => 'CRITICAL', 'message' => 'Backup is outdated: ' . $msg];
        }
        return ['status' => 'OK', 'message' => $msg];
    }

    private function checkFailedNotifications(): array
    {
        try {
            $count = (int)\db_scalar(
                "SELECT COUNT(*) FROM notifications WHERE status = 'Failed' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            
            $msg = "{$count} failed notification(s) in the last hour.";

            if ($count > 5) {
                return ['status' => 'WARN', 'message' => $msg];
            }
            return ['status' => 'OK', 'message' => $msg];
        } catch (\Throwable $e) {
            return ['status' => 'WARN', 'message' => 'Could not query notifications: ' . $e->getMessage()];
        }
    }

    private function logHealthCheck(string $name, string $status, string $message): void
    {
        try {
            \db_execute(
                "INSERT INTO app_health_log (check_name, status, details) VALUES (?, ?, ?)",
                "sss",
                [$name, $status, $message]
            );
        } catch (\Throwable $e) {
            // Silently ignore DB logging errors during health check logging to prevent infinite loops
        }
    }
}

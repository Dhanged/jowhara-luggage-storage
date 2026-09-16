<?php
namespace App\Repositories;

class UserRepository
{
    public function findById(int $id): ?array
    {
        return \db_fetch_one("SELECT * FROM users WHERE user_id = ? LIMIT 1", "i", [$id]);
    }

    public function findByUsername(string $username): ?array
    {
        return \db_fetch_one("SELECT * FROM users WHERE username = ? LIMIT 1", "s", [$username]);
    }

    public function updateLastLogin(int $userId): void
    {
        \db_execute("UPDATE users SET last_login_at = NOW() WHERE user_id = ?", "i", [$userId]);
    }

    public function recordLoginAttempt(?int $userId, string $username, bool $success): void
    {
        $ip = $_SERVER["REMOTE_ADDR"] ?? null;
        $agent = substr($_SERVER["HTTP_USER_AGENT"] ?? "CLI", 0, 255);
        $success_int = $success ? 1 : 0;
        \db_execute(
            "INSERT INTO login_history (user_id, username, success, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)",
            "isiss",
            [$userId, $username, $success_int, $ip, $agent]
        );
    }
}

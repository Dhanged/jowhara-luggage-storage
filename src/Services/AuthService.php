<?php
namespace App\Services;

use App\Repositories\UserRepository;

class AuthService
{
    private UserRepository $userRepo;

    public function __construct(?UserRepository $userRepo = null)
    {
        $this->userRepo = $userRepo ?? new UserRepository();
    }

    public function login(string $username, string $password)
    {
        $user = $this->userRepo->findByUsername($username);
        if (!$user || ($user["status"] ?? "Active") !== "Active") {
            $this->userRepo->recordLoginAttempt(null, $username, false);
            return false;
        }

        $ok = !empty($user["password_hash"]) && password_verify($password, $user["password_hash"]);

        if (!$ok && isset($user["password"]) && hash_equals((string)$user["password"], $password)) {
            $ok = true;
            $hash = password_hash($password, PASSWORD_DEFAULT);
            \db_execute("UPDATE users SET password_hash = ? WHERE user_id = ?", "si", [$hash, $user["user_id"]]);
        }

        if (!$ok) {
            $this->userRepo->recordLoginAttempt((int)$user["user_id"], $username, false);
            return false;
        }

        if (!empty($user['totp_enabled'])) {
            session_regenerate_id(true);
            $_SESSION['2fa_pending_uid'] = (int)$user["user_id"];
            $_SESSION['2fa_pending_time'] = time();
            $_SESSION['2fa_attempts'] = 0;
            return "2fa_required";
        }

        session_regenerate_id(true);
        $_SESSION["user_id"] = (int)$user["user_id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];
        $_SESSION["last_activity"] = time();
        $this->userRepo->recordLoginAttempt((int)$user["user_id"], $username, true);
        $this->userRepo->updateLastLogin((int)$user["user_id"]);
        \log_action("Logged in", "user", (int)$user["user_id"], "User signed in");
        return true;
    }
}

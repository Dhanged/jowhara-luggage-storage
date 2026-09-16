<?php
require_once __DIR__ . "/functions.php";

$uid = (int)($_SESSION['2fa_pending_uid'] ?? 0);
$time = (int)($_SESSION['2fa_pending_time'] ?? 0);

if (!$uid || time() - $time > 300) { // 5 minutes timeout
    unset($_SESSION['2fa_pending_uid'], $_SESSION['2fa_pending_time']);
    redirect("index.php?timeout=1");
}

$user = db_fetch_one("SELECT * FROM users WHERE user_id = ?", "i", [$uid]);
if (!$user || empty($user['totp_enabled']) || empty($user['totp_secret'])) {
    redirect("index.php");
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
    
    $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;
    
    if ($_SESSION['2fa_attempts'] > 5) {
        log_action("2FA Locked Out", "user", (int)$user["user_id"], "Exceeded 5 2FA verification attempts");
        unset($_SESSION['2fa_pending_uid'], $_SESSION['2fa_pending_time'], $_SESSION['2fa_attempts']);
        record_login_attempt((int)$user["user_id"], $user["username"], false);
        redirect("index.php?timeout=1");
    }

    if (totp_verify($user['totp_secret'], $code)) {
        // Verification success -> log them in
        session_regenerate_id(true);
        unset($_SESSION['2fa_pending_uid'], $_SESSION['2fa_pending_time'], $_SESSION['2fa_attempts']);
        
        $_SESSION["user_id"] = (int)$user["user_id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["role"] = $user["role"];
        $_SESSION["last_activity"] = time();
        record_login_attempt((int)$user["user_id"], $user["username"], true);
        db_execute("UPDATE users SET last_login_at = NOW() WHERE user_id = ?", "i", [(int)$user["user_id"]]);
        log_action("Logged in", "user", (int)$user["user_id"], "User signed in with 2FA");
        
        redirect(default_page_for_role($user["role"] ?? ""));
    } else {
        $remaining = 5 - $_SESSION['2fa_attempts'];
        $error = "Invalid verification code. " . ($remaining > 0 ? "You have {$remaining} attempt(s) remaining." : "Maximum attempts reached.");
        record_login_attempt((int)$user["user_id"], $user["username"], false);
    }
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Two-Factor Verification | Jowhara Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="login-page d-flex align-items-center justify-content-center bg-light" style="min-height: 100vh;">
    <div class="card login-card shadow-sm border-0" style="max-width:400px; width:100%;">
        <div class="card-body p-4 text-center">
            <div class="mb-4 text-primary" style="font-size:3rem;">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h2 class="h4 fw-bold mb-3">Two-Factor Authentication</h2>
            <p class="text-secondary small mb-4">Enter the 6-digit code from your authenticator app to continue.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger small d-flex align-items-center gap-2 text-start">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <input class="form-control form-control-lg text-center fw-bold font-monospace letter-spacing-2" 
                           type="text" id="code" name="code" required autofocus 
                           maxlength="6" minlength="6" pattern="[0-9]{6}" inputmode="numeric" 
                           placeholder="000000" autocomplete="one-time-code">
                </div>
                <button class="btn btn-primary btn-lg w-100 fw-bold" type="submit">Verify & Log In</button>
                <div class="mt-4">
                    <a href="index.php" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
                </div>
            </form>
        </div>
    </div>
    <style>.letter-spacing-2 { letter-spacing: 0.3em; }</style>
</body>
</html>

<?php
require_once __DIR__ . "/functions.php";

if ($user = current_user()) {
    redirect(default_page_for_role($user["role"]));
}

$error = "";
if (isset($_GET["timeout"])) {
    $error = "Session expired after inactivity. Please login again.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $username = trim($_POST["username"] ?? "");
    $password = (string)($_POST["password"] ?? "");

    if (!check_login_rate_limit($username)) {
        $error = "Too many failed login attempts. Please try again in 15 minutes.";
    } else {
        $login_res = login_user($username, $password);
        if ($login_res === "2fa_required") {
            redirect("verify_2fa.php");
        } elseif ($login_res === true) {
            redirect(default_page_for_role($_SESSION["role"] ?? ""));
        }
        $error = "Invalid username or password, or the user is inactive.";
    }
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Jowhara Hotel Luggage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="assets/app.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="card login-card">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <img src="assets/logo.jpg" alt="Jowhara Hotel Logo" class="login-logo">
                <h1 class="h4 fw-bold mb-1">Jowhara Hotel</h1>
                <p class="text-secondary mb-0">Luggage Storage Management System</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo h($error); ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control" type="text" id="username" name="username" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Login</button>
            </form>

           
        </div>
    </div>
</body>
</html>

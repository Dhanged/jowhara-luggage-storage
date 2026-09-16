<?php
require_once __DIR__ . "/layout.php";
require_login();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $current = (string)($_POST["current_password"] ?? "");
    $new = (string)($_POST["new_password"] ?? "");
    $confirm = (string)($_POST["confirm_password"] ?? "");
    $user = current_user();

    if (!password_verify($current, $user["password_hash"])) {
        set_flash("danger", "Current password is incorrect.");
        redirect("change_password.php");
    }
    if (strlen($new) < 6 || $new !== $confirm) {
        set_flash("danger", "New password must be at least 6 characters and match confirmation.");
        redirect("change_password.php");
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    db_execute("UPDATE users SET password_hash = ? WHERE user_id = ?", "si", [$hash, $user["user_id"]]);
    log_action("Changed password", "user", (int)$user["user_id"], "User changed own password");
    set_flash("success", "Password changed.");
    redirect(default_page_for_role($user["role"] ?? ""));
}

render_header("Change Password", "");
?>
<div class="mb-4">
    <h1 class="h3 fw-bold mb-1">Change Password</h1>
    <p class="text-secondary mb-0">Update your own login password.</p>
</div>

<div class="card" style="max-width: 560px;">
    <div class="card-body">
        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label" for="current_password">Current Password</label>
                <input class="form-control" type="password" id="current_password" name="current_password" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="new_password">New Password</label>
                <input class="form-control" type="password" id="new_password" name="new_password" minlength="8" required>
                <div class="progress mt-2" style="height: 5px;">
                    <div class="progress-bar" id="addPwBar" role="progressbar" style="width: 0%"></div>
                </div>
                <div id="addPwText" class="mt-1"></div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold" for="confirm_password">Confirm New Password</label>
                <input class="form-control" type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button class="btn btn-primary w-100 fw-bold" type="submit">Update Password</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('new_password');
    const bar = document.getElementById('addPwBar');
    const text = document.getElementById('addPwText');
    
    input.addEventListener('input', function() {
        const val = input.value;
        let strength = 0;
        if (val.length >= 8) strength += 25;
        if (val.match(/[A-Z]/)) strength += 25;
        if (val.match(/[0-9]/)) strength += 25;
        if (val.match(/[^A-Za-z0-9]/)) strength += 25;
        
        bar.style.width = strength + '%';
        if (val.length === 0) {
            bar.style.width = '0%';
            text.textContent = '';
            bar.className = 'progress-bar';
        } else if (strength < 50) {
            bar.className = 'progress-bar bg-danger';
            text.textContent = '<?= __("Weak") ?>';
            text.className = 'text-danger small fw-bold';
        } else if (strength < 75) {
            bar.className = 'progress-bar bg-warning text-dark';
            text.textContent = '<?= __("Fair") ?>';
            text.className = 'text-warning small fw-bold';
        } else if (strength < 100) {
            bar.className = 'progress-bar bg-info text-dark';
            text.textContent = '<?= __("Strong") ?>';
            text.className = 'text-info small fw-bold';
        } else {
            bar.className = 'progress-bar bg-success';
            text.textContent = '<?= __("Very Strong") ?>';
            text.className = 'text-success small fw-bold';
        }
    });
});
</script>
<?php render_footer(); ?>

<?php
require_once __DIR__ . "/layout.php";
require_login();

$user = current_user();
$uid  = (int)$user['user_id'];

/* ── Generate a new secret if none stored yet ── */
$secret = $user['totp_secret'] ?? '';
if (empty($secret)) {
    $secret = totp_generate_secret();
    db_execute("UPDATE users SET totp_secret = ? WHERE user_id = ?", "si", [$secret, $uid]);
}

$enabled = (int)($user['totp_enabled'] ?? 0);
$error   = '';
$success = '';

/* ── Handle POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'enable') {
        $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
        if (totp_verify($secret, $code)) {
            db_execute("UPDATE users SET totp_enabled = 1 WHERE user_id = ?", "i", [$uid]);
            log_action("Enabled 2FA", "user", $uid, "TOTP 2FA activated");
            set_flash("success", "Two-factor authentication has been enabled on your account.");
            redirect("setup_2fa.php");
        } else {
            $error = "Invalid verification code. Please try again.";
        }
    }

    if ($action === 'disable') {
        $code = preg_replace('/\D/', '', $_POST['code'] ?? '');
        if (totp_verify($secret, $code)) {
            db_execute("UPDATE users SET totp_enabled = 0, totp_secret = NULL WHERE user_id = ?", "i", [$uid]);
            log_action("Disabled 2FA", "user", $uid, "TOTP 2FA deactivated");
            set_flash("warning", "Two-factor authentication has been disabled.");
            redirect("setup_2fa.php");
        } else {
            $error = "Invalid code. 2FA was NOT disabled.";
        }
    }

    if ($action === 'regen') {
        db_execute("UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE user_id = ?", "i", [$uid]);
        log_action("Regenerated 2FA secret", "user", $uid, "New TOTP secret generated");
        set_flash("info", "A new secret has been generated. Please re-scan the QR code.");
        redirect("setup_2fa.php");
    }
}

$qr_url = totp_qr_url($user['username'], $secret);
$user   = db_fetch_one("SELECT * FROM users WHERE user_id = ?", "i", [$uid]); // refresh
$enabled = (int)($user['totp_enabled'] ?? 0);

render_header("Two-Factor Authentication", "settings");
?>
<div class="d-flex align-items-center gap-3 mb-4">
    <div style="width:48px;height:48px;border-radius:14px;background:var(--brand-light);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">
        <i class="bi bi-shield-lock-fill"></i>
    </div>
    <div>
        <h1 class="h3 fw-bold mb-0">Two-Factor Authentication</h1>
        <p class="text-muted mb-0 small">Add an extra layer of security to your account</p>
    </div>
    <?php if ($enabled): ?>
        <span class="ms-auto badge text-bg-success fs-6 px-3 py-2"><i class="bi bi-shield-check me-1"></i>2FA Enabled</span>
    <?php else: ?>
        <span class="ms-auto badge text-bg-secondary fs-6 px-3 py-2"><i class="bi bi-shield-x me-1"></i>2FA Disabled</span>
    <?php endif; ?>
</div>

<?php if ($error): ?>
<div class="alert alert-danger d-flex align-items-center gap-2"><i class="bi bi-x-circle-fill"></i><?= h($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left: QR / Instructions -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h2 class="section-title">Setup QR Code</h2></div>
            <div class="card-body text-center">
                <p class="text-muted small mb-3">Scan this QR code with <strong>Google Authenticator</strong>, <strong>Authy</strong>, or any TOTP app.</p>
                <img src="<?= h($qr_url) ?>" alt="2FA QR Code" class="img-fluid rounded-3 border p-2" style="max-width:200px;">
                <div class="mt-3">
                    <p class="text-muted small mb-1">Or enter this key manually:</p>
                    <code class="d-block bg-body-secondary rounded px-3 py-2 fw-bold letter-spacing-2 text-center"><?= h($secret) ?></code>
                </div>
                <form method="POST" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="regen">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('This will invalidate your current QR code. Continue?')">
                        <i class="bi bi-arrow-clockwise me-1"></i>Regenerate Secret
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right: Enable / Disable -->
    <div class="col-lg-7">
        <?php if (!$enabled): ?>
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary bg-opacity-10 border-primary"><h2 class="section-title text-primary">Enable 2FA</h2></div>
            <div class="card-body">
                <p class="text-muted">After scanning the QR code, enter the 6-digit code from your authenticator app to confirm and enable 2FA.</p>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="enable">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Verification Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control form-control-lg text-center fw-bold font-monospace"
                               maxlength="6" minlength="6" pattern="[0-9]{6}" inputmode="numeric"
                               placeholder="000000" autocomplete="one-time-code" autofocus required>
                        <div class="form-text">The code refreshes every 30 seconds.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-shield-lock me-1"></i>Enable Two-Factor Authentication
                    </button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card mb-4 border-success">
            <div class="card-header bg-success bg-opacity-10 border-success">
                <h2 class="section-title text-success"><i class="bi bi-check-circle-fill me-1"></i>2FA is Active</h2>
            </div>
            <div class="card-body">
                <p class="text-muted">Your account is protected with two-factor authentication. You'll need your authenticator app each time you log in.</p>
                <div class="alert alert-success d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check-fill fs-5"></i>
                    <div>Your account has enhanced security. Keep your authenticator app safe.</div>
                </div>
            </div>
        </div>
        <div class="card border-danger">
            <div class="card-header bg-danger bg-opacity-10 border-danger"><h2 class="section-title text-danger">Disable 2FA</h2></div>
            <div class="card-body">
                <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>Disabling 2FA will reduce your account security. Enter a valid code to confirm.</div>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="disable">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Verification Code</label>
                        <input type="text" name="code" class="form-control form-control-lg text-center fw-bold font-monospace"
                               maxlength="6" minlength="6" pattern="[0-9]{6}" inputmode="numeric"
                               placeholder="000000" autocomplete="one-time-code" required>
                    </div>
                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Are you sure you want to disable 2FA?')">
                        <i class="bi bi-shield-x me-1"></i>Disable Two-Factor Authentication
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mt-4">
            <div class="card-header"><h2 class="section-title"><i class="bi bi-info-circle me-1"></i>How It Works</h2></div>
            <div class="card-body">
                <ol class="mb-0 small text-muted">
                    <li class="mb-2">Install an authenticator app: <strong>Google Authenticator</strong>, <strong>Authy</strong>, or <strong>Microsoft Authenticator</strong>.</li>
                    <li class="mb-2">Scan the QR code on the left with your app.</li>
                    <li class="mb-2">Your app will show a 6-digit code that refreshes every 30 seconds.</li>
                    <li>When logging in, you'll be asked for this code after your password.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<style>
.letter-spacing-2 { letter-spacing: 0.2em; }
</style>
<?php render_footer(); ?>

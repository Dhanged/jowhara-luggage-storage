<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/src/bootstrap.php";

if (session_status() === PHP_SESSION_NONE) {
    $session_dir = __DIR__ . "/storage/sessions";
    if (!is_dir($session_dir)) {
        mkdir($session_dir, 0775, true);
    }
    session_save_path($session_dir);
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
    }
    session_start();
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function redirect(string $url): void
{
    $clean_url = $url;
    if (preg_match('#^(https?:)?//#i', $url)) {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        $current_host = $_SERVER['HTTP_HOST'] ?? '';
        if (strcasecmp($host, $current_host) !== 0) {
            $clean_url = "dashboard.php";
        }
    }
    header("Location: $clean_url");
    exit;
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; " .
           "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
           "img-src 'self' data: blob: https://api.qrserver.com https://cdn.jsdelivr.net; " .
           "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; " .
           "connect-src 'self'; " .
           "frame-ancestors 'none'; " .
           "base-uri 'self'; " .
           "form-action 'self';";

    header("Content-Security-Policy: {$csp}");
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}

function init_error_logging(): void
{
    $log_dir = __DIR__ . "/storage/logs";
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0775, true);
    }
    
    set_exception_handler(function (Throwable $e) {
        $log_file = __DIR__ . "/storage/logs/app_errors.log";
        $time = date("Y-m-d H:i:s");
        $user = $_SESSION["username"] ?? "guest";
        $ip = $_SERVER["REMOTE_ADDR"] ?? "cli";
        $msg = "[{$time}] [{$ip}] [User: {$user}] Exception in {$e->getFile()}:{$e->getLine()} - {$e->getMessage()}\nTrace: {$e->getTraceAsString()}\n";
        @file_put_contents($log_file, $msg, FILE_APPEND);
        
        if (!headers_sent()) {
            http_response_code(500);
        }
        if (ini_get('display_errors')) {
            echo "<div style='padding:20px;font-family:sans-serif;'><h2>Application Error</h2><p>" . h($e->getMessage()) . "</p></div>";
        } else {
            echo "<div style='padding:20px;font-family:sans-serif;'><h2>An internal error occurred</h2><p>Please try again or contact system administrator.</p></div>";
        }
        exit;
    });
}
init_error_logging();

function check_login_rate_limit(string $username): bool
{
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    if (empty($ip) && empty($username)) {
        return true;
    }
    
    // 1. IP Rate Limit: max 20 failed attempts in 15 minutes (accommodates shared hotel Wi-Fi)
    if (!empty($ip)) {
        $ip_failed = (int)db_scalar(
            "SELECT COUNT(*) FROM login_history 
             WHERE success = 0 AND ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            "s", [$ip]
        );
        if ($ip_failed >= 20) {
            return false;
        }
    }
    
    // 2. Targeted Username Rate Limit: max 5 failed attempts in 15 minutes
    if (!empty($username)) {
        $user_failed = (int)db_scalar(
            "SELECT COUNT(*) FROM login_history 
             WHERE success = 0 AND username = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            "s", [$username]
        );
        if ($user_failed >= 5) {
            return false;
        }
    }
    
    // 3. IP + Username Combo Limit: max 5 failed attempts in 15 minutes
    if (!empty($ip) && !empty($username)) {
        $combo_failed = (int)db_scalar(
            "SELECT COUNT(*) FROM login_history 
             WHERE success = 0 AND ip_address = ? AND username = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            "ss", [$ip, $username]
        );
        if ($combo_failed >= 5) {
            return false;
        }
    }
    
    return true;
}

function verify_admin_password(string $password): bool
{
    $user = current_user();
    if (!$user || !is_admin()) {
        return false;
    }
    return !empty($user["password_hash"]) && password_verify($password, $user["password_hash"]);
}

function validate_phone(string $phone): bool
{
    $clean = preg_replace('/[^\d+]/', '', $phone);
    return strlen($clean) >= 6 && strlen($clean) <= 20;
}

function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_date(string $date, string $format = 'Y-m-d'): bool
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function sanitize_string(?string $value): string
{
    return trim(strip_tags((string)$value));
}

function db_execute(string $sql, string $types = "", array $params = []): mysqli_stmt
{
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types !== "") {
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        array_unshift($refs, $types);
        call_user_func_array([$stmt, "bind_param"], $refs);
    }
    $stmt->execute();
    return $stmt;
}

function db_fetch_one(string $sql, string $types = "", array $params = []): ?array
{
    $stmt = db_execute($sql, $types, $params);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ?: null;
}

function db_fetch_all(string $sql, string $types = "", array $params = []): array
{
    $stmt = db_execute($sql, $types, $params);
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function db_scalar(string $sql, string $types = "", array $params = [])
{
    $stmt = db_execute($sql, $types, $params);
    return $stmt->get_result()->fetch_column();
}

function db_insert_id(): int
{
    global $conn;
    return (int)$conn->insert_id;
}

function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST["csrf_token"] ?? "";
    if (!$token || !hash_equals($_SESSION["csrf_token"] ?? "", $token)) {
        http_response_code(419);
        die("Invalid form token. Please go back and try again.");
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION["flash"] = ["type" => $type, "message" => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION["flash"])) {
        return null;
    }
    $flash = $_SESSION["flash"];
    unset($_SESSION["flash"]);
    return $flash;
}

function login_user(string $username, string $password)
{
    $user = db_fetch_one("SELECT * FROM users WHERE username = ? LIMIT 1", "s", [$username]);
    if (!$user || ($user["status"] ?? "Active") !== "Active") {
        record_login_attempt(null, $username, false);
        return false;
    }

    $ok = !empty($user["password_hash"]) && password_verify($password, $user["password_hash"]);

    if (!$ok && isset($user["password"]) && hash_equals((string)$user["password"], $password)) {
        $ok = true;
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db_execute("UPDATE users SET password_hash = ? WHERE user_id = ?", "si", [$hash, $user["user_id"]]);
    }

    if (!$ok) {
        record_login_attempt((int)$user["user_id"], $username, false);
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
    record_login_attempt((int)$user["user_id"], $username, true);
    db_execute("UPDATE users SET last_login_at = NOW() WHERE user_id = ?", "i", [(int)$user["user_id"]]);
    log_action("Logged in", "user", (int)$user["user_id"], "User signed in");
    return true;
}

function record_login_attempt(?int $user_id, string $username, bool $success): void
{
    $ip = $_SERVER["REMOTE_ADDR"] ?? null;
    $agent = substr($_SERVER["HTTP_USER_AGENT"] ?? "CLI", 0, 255);
    $success_int = $success ? 1 : 0;
    db_execute(
        "INSERT INTO login_history (user_id, username, success, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?)",
        "isiss",
        [$user_id, $username, $success_int, $ip, $agent]
    );
}

function current_user(): ?array
{
    if (empty($_SESSION["user_id"])) {
        return null;
    }
    static $user = null;
    if ($user === null || (int)$user["user_id"] !== (int)$_SESSION["user_id"]) {
        $user = db_fetch_one("SELECT * FROM users WHERE user_id = ? LIMIT 1", "i", [$_SESSION["user_id"]]);
    }
    return $user;
}

function require_login(): void
{
    $timeout = 30 * 60;
    if (!empty($_SESSION["user_id"]) && !empty($_SESSION["last_activity"]) && (time() - (int)$_SESSION["last_activity"]) > $timeout) {
        log_action("Auto logout", "user", (int)$_SESSION["user_id"], "Session expired after inactivity");
        session_destroy();
        redirect("index.php?timeout=1");
    }

    $user = current_user();
    if (!$user) {
        redirect("index.php");
    }

    $_SESSION["last_activity"] = time();

    // Check page access restrictions based on role
    check_page_access($user);
}

function check_page_access(array $user): void
{
    $role = $user["role"] ?? "";
    $page = basename($_SERVER['SCRIPT_NAME']);

    // Standard public/common logged-in actions that should bypass restriction checks
    $common_pages = [
        "change_password.php",
        "change_lang.php",
        "logout.php",
        "verify_2fa.php",
        "access_denied.php",
        "ajax_notif_count.php",
        "photo_uploader.php"
    ];

    if (in_array($page, $common_pages, true)) {
        return;
    }

    if ($role === "lost_found_officer") {
        $allowed = [
            "lost_found.php",
            "add_lost_found.php",
            "claim_lost_found.php",
            "edit_lost_found.php",
            "reports.php",
            "export_excel.php",
            "export_pdf.php"
        ];

        if (!in_array($page, $allowed, true)) {
            log_action("Access Denied", "security", null, "User {$user['username']} (role: $role) attempted to access restricted page: $page");
            redirect("access_denied.php");
        }

        // Export validation: Lost & Found Officers must only export Lost & Found data.
        if (($page === "export_excel.php" || $page === "export_pdf.php") && ($_GET["type"] ?? "") !== "lost_found") {
            log_action("Access Denied", "security", null, "User {$user['username']} (role: $role) attempted to export non-Lost-Found data via: $page");
            redirect("access_denied.php");
        }
    }
}

function default_page_for_role(string $role): string
{
    if ($role === "lost_found_officer") {
        return "lost_found.php";
    }
    return "dashboard.php";
}

function is_admin(): bool
{
    $role = current_user()["role"] ?? "";
    return $role === "admin" || $role === "super_admin";
}

function user_can(string $permission): bool
{
    $role = current_user()["role"] ?? "";
    if ($role === "super_admin" || $role === "admin") {
        return true;
    }

    static $perms = null;
    if ($perms === null) {
        $saved = get_setting("role_permissions");
        if ($saved) {
            $perms = json_decode($saved, true);
        }
    }

    $defaults = [
        "manager"            => [
            "view", "create", "edit", "checkout", "reports", "audit", "shelves",
            "soft_delete", "restore", "bulk", "notifications", "shift", "manager_dashboard"
        ],
        "staff"              => [
            "view", "create", "edit", "checkout", "reports", "bulk", "notifications", "shift"
        ],
        "receptionist"       => [
            "view", "create", "edit", "checkout", "reports", "bulk", "notifications", "shift"
        ],
        "security_officer"   => [
            "view", "lost_found", "audit", "reports", "shift"
        ],
        "lost_found_officer" => ["lost_found", "view"],
        "viewer"             => ["view", "reports", "shift"],
    ];

    $allowed = $perms[$role] ?? $defaults[$role] ?? [];
    return in_array("*", $allowed, true) || in_array($permission, $allowed, true);
}

function require_permission(string $permission): void
{
    require_login();
    if (!user_can($permission)) {
        http_response_code(403);
        redirect("access_denied.php");
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        redirect("access_denied.php");
    }
}

function log_action(string $action, ?string $entity_type = null, ?int $entity_id = null, ?string $details = null): void
{
    $user_id = $_SESSION["user_id"] ?? null;
    $ip = $_SERVER["REMOTE_ADDR"] ?? null;
    db_execute(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)",
        "ississ",
        [$user_id, $action, $entity_type, $entity_id, $details, $ip]
    );
}

function role_label(string $role): string
{
    return [
        "super_admin"        => "Super Admin",
        "admin"              => "Admin",
        "manager"            => "Manager",
        "staff"              => "Staff",
        "receptionist"       => "Receptionist",
        "security_officer"   => "Security Officer",
        "lost_found_officer" => "Lost & Found Officer",
        "viewer"             => "Viewer",
    ][$role] ?? ucfirst($role);
}

function generate_tag_code(int $luggage_id): string
{
    return "LS" . str_pad((string)$luggage_id, 6, "0", STR_PAD_LEFT);
}

function status_badge(string $status): string
{
    return strtolower($status) === "collected" ? "success" : "warning text-dark";
}

function lost_found_badge_class(string $status): string
{
    return [
        "Found" => "text-bg-warning text-dark",
        "Claimed" => "text-bg-success",
        "Disposed" => "text-bg-danger",
    ][$status] ?? "text-bg-secondary";
}

function upload_luggage_photo(string $field, ?string $old_path = null): ?string
{
    return upload_image_file($field, "uploads/luggage", $old_path, "Photo");
}

function upload_guest_id_photo(string $field, ?string $old_path = null): ?string
{
    return upload_image_file($field, "uploads/ids", $old_path, "ID photo");
}

function upload_image_file(string $field, string $relative_dir, ?string $old_path = null, string $label = "Photo"): ?string
{
    if (empty($_FILES[$field]["name"])) {
        return $old_path;
    }

    if ($_FILES[$field]["error"] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("$label upload failed.");
    }

    $allowed = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
        "image/gif" => "gif",
    ];

    $tmp = $_FILES[$field]["tmp_name"];
    $mime = mime_content_type($tmp);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException("Only JPG, PNG, WEBP, and GIF images are allowed.");
    }

    if ($_FILES[$field]["size"] > 3 * 1024 * 1024) {
        throw new RuntimeException("$label must be 3MB or smaller.");
    }

    $dir = __DIR__ . "/" . $relative_dir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $name = bin2hex(random_bytes(12)) . "." . $allowed[$mime];
    $target = $dir . "/" . $name;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException("Could not save uploaded photo.");
    }

    if ($old_path && is_file(__DIR__ . "/" . $old_path)) {
        unlink(__DIR__ . "/" . $old_path);
    }

    return trim($relative_dir, "/") . "/" . $name;
}

function remove_luggage_photo(?string $path): void
{
    if ($path && is_file(__DIR__ . "/" . $path)) {
        unlink(__DIR__ . "/" . $path);
    }
}

function secure_file_url(?string $path): string
{
    if (empty($path)) {
        return "";
    }
    return "secure_file.php?file=" . urlencode(ltrim(str_replace('\\', '/', $path), '/'));
}

function save_signature_image(string $data_url, ?string $old_path = null): ?string
{
    if (trim($data_url) === "") {
        return $old_path;
    }

    if (!preg_match('/^data:image\/png;base64,/', $data_url)) {
        throw new RuntimeException("Signature format is invalid.");
    }

    $raw = base64_decode(substr($data_url, strpos($data_url, ",") + 1), true);
    if ($raw === false || strlen($raw) < 100) {
        throw new RuntimeException("Signature is empty or invalid.");
    }

    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            throw new RuntimeException("Signature payload is not a valid image.");
        }
        imagedestroy($img);
    }

    $dir = __DIR__ . "/uploads/signatures";
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = bin2hex(random_bytes(12)) . ".png";
    $path = $dir . "/" . $name;
    file_put_contents($path, $raw);

    if ($old_path && is_file(__DIR__ . "/" . $old_path)) {
        unlink(__DIR__ . "/" . $old_path);
    }

    return "uploads/signatures/" . $name;
}

function condition_options(): array
{
    return ["Locked", "Fragile", "Heavy", "Damaged", "Wet", "Valuable Items", "Unlocked", "Needs Care"];
}

function parse_condition_flags($value): array
{
    if (is_array($value)) {
        return array_values(array_intersect(condition_options(), $value));
    }
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function condition_flags_to_db(array $flags): string
{
    return json_encode(array_values(array_intersect(condition_options(), $flags)));
}

function money($value): string
{
    return number_format((float)$value, 2);
}

function phone_for_whatsapp(?string $phone): string
{
    $digits = preg_replace("/\D+/", "", (string)$phone);
    return $digits ?: "";
}

function whatsapp_link(?string $phone, string $message): ?string
{
    $digits = phone_for_whatsapp($phone);
    if ($digits === "") {
        return null;
    }
    return "https://wa.me/" . $digits . "?text=" . urlencode($message);
}

function receipt_message(array $row): string
{
    $tracker_url = luggage_qr_payload($row);
    $is_collected = ($row["status"] === "Collected");
    
    $header = $is_collected 
        ? "👋 *LUGGAGE COLLECTED / SHANDADA WAA LA QAATAY*" 
        : "✅ *LUGGAGE STORED / SHANDADA WAA LA KAYDIYAY*";
        
    $time_label = $is_collected ? "📅 *COLLECTED / LA QAADAY:*" : "📅 *CHECK-IN / LA KEENAY:*";
    $time_val = $is_collected ? ($row["checkout_date"] ?? date("Y-m-d H:i")) : $row["checkin_date"];
    
    $msg = "🏨 *JOWHARA INTERNATIONAL HOTEL*\n"
         . "━━━━━━━━━━━━━━━━━━━━\n"
         . $header . "\n\n"
         . "🏷️ *TAG CODE / LAMBARKA:* " . $row["tag_code"] . "\n"
         . "👤 *GUEST / MARTIDA:* " . $row["guest_name"] . "\n"
         . "🔢 *ROOM / QOLKA:* " . ($row["room_no"] ?: "-") . "\n"
         . "📦 *QTY / TIRADA:* " . $row["quantity"] . " (" . $row["luggage_type"] . ")\n"
         . "⚖️ *WEIGHT / MIISAANKA:* " . __($row["weight_class"]) . " (" . ($row["weight_kg"] ?: "0.00") . " kg)\n";
         
    if (!empty($row["security_seal_code"])) {
        $msg .= "🔒 *SEAL / SHAMBADA:* " . $row["security_seal_code"] . "\n";
    }
    
    $msg .= "💰 *FEE / LACAGTA:* $" . money($row["fee_amount"]) . " (" . __($row["payment_status"]) . ")\n"
         . $time_label . " " . $time_val . "\n";
         
    if (!$is_collected && !empty($row["expected_pickup_date"])) {
        $msg .= "⏳ *EXPECTED / LA FILAYO:* " . $row["expected_pickup_date"] . "\n";
    }
    
    if ($is_collected && !empty($row["pickup_person_name"])) {
        $msg .= "🤝 *PICKUP BY / QOFKA QAADAY:* " . $row["pickup_person_name"] . "\n";
    }
    
    $msg .= "\n🔍 *LIVE TRACKING / KALA SOCO LIVE:*\n"
         . $tracker_url . "\n\n"
         . "━━━━━━━━━━━━━━━━━━━━\n"
         . "🙏 *Thank you for staying with us!*\n"
         . "*Waad ku mahadsantahay booqashadaada!*";
         
    return $msg;
}

function log_notification(int $luggage_id, int $guest_id, string $channel, ?string $recipient, string $message, string $status = "Prepared"): void
{
    $user_id = current_user()["user_id"] ?? null;
    db_execute(
        "INSERT INTO notifications (luggage_id, guest_id, channel, recipient, message, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        "iissssi",
        [$luggage_id, $guest_id, $channel, $recipient, $message, $status, $user_id]
    );
}

function send_receipt_email(array $row): bool
{
    if (empty($row["email"])) {
        return false;
    }

    $subject = "Luggage Receipt " . $row["tag_code"];
    $body = receipt_message($row) . "\n\nOpen receipt in the hotel system for full details.";
    $headers = "From: no-reply@localhost\r\n";
    return @mail($row["email"], $subject, $body, $headers);
}

function qr_url(string $payload, int $size = 180): string
{
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($payload);
}

function luggage_qr_payload(array $row): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $tag = urlencode($row["tag_code"] ?? "");
    return "{$protocol}://{$host}/luggage_storage/track.php?tag={$tag}";
}

function code39_clean(string $text): string
{
    $text = strtoupper($text);
    return preg_replace("/[^0-9A-Z \\-\\.\\$\\/\\+\\%]/", "", $text) ?: "LS000000";
}

function render_code39(string $text): string
{
    $patterns = [
        "0" => "nnnwwnwnn", "1" => "wnnwnnnnw", "2" => "nnwwnnnnw", "3" => "wnwwnnnnn",
        "4" => "nnnwwnnnw", "5" => "wnnwwnnnn", "6" => "nnwwwnnnn", "7" => "nnnwnnwnw",
        "8" => "wnnwnnwnn", "9" => "nnwwnnwnn", "A" => "wnnnnwnnw", "B" => "nnwnnwnnw",
        "C" => "wnwnnwnnn", "D" => "nnnnwwnnw", "E" => "wnnnwwnnn", "F" => "nnwnwwnnn",
        "G" => "nnnnnwwnw", "H" => "wnnnnwwnn", "I" => "nnwnnwwnn", "J" => "nnnnwwwnn",
        "K" => "wnnnnnnww", "L" => "nnwnnnnww", "M" => "wnwnnnnwn", "N" => "nnnnwnnww",
        "O" => "wnnnwnnwn", "P" => "nnwnwnnwn", "Q" => "nnnnnnwww", "R" => "wnnnnnwwn",
        "S" => "nnwnnnwwn", "T" => "nnnnwnwwn", "U" => "wwnnnnnnw", "V" => "nwwnnnnnw",
        "W" => "wwwnnnnnn", "X" => "nwnnwnnnw", "Y" => "wwnnwnnnn", "Z" => "nwwnwnnnn",
        "-" => "nwnnnnwnw", "." => "wwnnnnwnn", " " => "nwwnnnwnn", "$" => "nwnwnwnnn",
        "/" => "nwnwnnnwn", "+" => "nwnnnwnwn", "%" => "nnnwnwnwn", "*" => "nwnnwnwnn",
    ];

    $text = "*" . code39_clean($text) . "*";
    $html = '<div class="barcode-lines" aria-label="Barcode ' . h($text) . '">';
    foreach (str_split($text) as $char) {
        $pattern = $patterns[$char] ?? $patterns["0"];
        for ($i = 0; $i < strlen($pattern); $i++) {
            $is_bar = $i % 2 === 0;
            $width = $pattern[$i] === "w" ? 4 : 2;
            $class = $is_bar ? "bar" : "space";
            $html .= '<span class="' . $class . '" style="width:' . $width . 'px"></span>';
        }
        $html .= '<span class="space" style="width:2px"></span>';
    }
    $html .= "</div>";
    $html .= '<div class="barcode-text">' . h(code39_clean($text)) . '</div>';
    return $html;
}

function report_filters(): array
{
    $start = $_GET["start"] ?? "";
    $end = $_GET["end"] ?? "";
    $status = $_GET["status"] ?? "";
    $room = trim($_GET["room"] ?? "");
    $zone = trim($_GET["zone"] ?? "");
    $type = trim($_GET["type"] ?? "");
    $payment = $_GET["payment"] ?? "";
    $user_id = (int)($_GET["user_id"] ?? 0);
    $overdue = $_GET["overdue"] ?? "";
    $archive = $_GET["archive"] ?? "";

    $where = [];
    $types = "";
    $params = [];

    if ($archive === "only") {
        $where[] = "l.deleted_at IS NOT NULL";
    } elseif ($archive !== "with") {
        $where[] = "l.deleted_at IS NULL";
    }

    if ($start !== "") {
        $where[] = "DATE(l.checkin_date) >= ?";
        $types .= "s";
        $params[] = $start;
    }
    if ($end !== "") {
        $where[] = "DATE(l.checkin_date) <= ?";
        $types .= "s";
        $params[] = $end;
    }
    if (in_array($status, ["Stored", "Collected"], true)) {
        $where[] = "l.status = ?";
        $types .= "s";
        $params[] = $status;
    }
    if ($room !== "") {
        $where[] = "g.room_no LIKE ?";
        $types .= "s";
        $params[] = "%" . $room . "%";
    }
    if ($zone !== "") {
        $where[] = "(l.storage_zone LIKE ? OR l.storage_location LIKE ?)";
        $types .= "ss";
        $params[] = "%" . $zone . "%";
        $params[] = "%" . $zone . "%";
    }
    if ($type !== "") {
        $where[] = "l.luggage_type LIKE ?";
        $types .= "s";
        $params[] = "%" . $type . "%";
    }
    if (in_array($payment, ["Paid", "Unpaid", "Waived"], true)) {
        $where[] = "l.payment_status = ?";
        $types .= "s";
        $params[] = $payment;
    }
    if ($user_id > 0) {
        $where[] = "(l.created_by = ? OR l.checked_out_by = ?)";
        $types .= "ii";
        $params[] = $user_id;
        $params[] = $user_id;
    }
    if ($overdue === "1") {
        $where[] = "l.status = 'Stored' AND l.expected_pickup_date IS NOT NULL AND l.expected_pickup_date < CURDATE()";
    }

    return [
        "sql" => $where ? "WHERE " . implode(" AND ", $where) : "",
        "types" => $types,
        "params" => $params,
        "start" => $start,
        "end" => $end,
        "status" => $status,
        "room" => $room,
        "zone" => $zone,
        "type" => $type,
        "payment" => $payment,
        "user_id" => $user_id,
        "overdue" => $overdue,
        "archive" => $archive,
    ];
}

function luggage_report_rows(array $filters): array
{
    return db_fetch_all(
        "SELECT l.*, g.guest_name, g.phone, g.room_no, g.email, u.username AS checkout_user, creator.username AS created_user
         FROM luggage l
         JOIN guests g ON g.guest_id = l.guest_id
         LEFT JOIN users u ON u.user_id = l.checked_out_by
         LEFT JOIN users creator ON creator.user_id = l.created_by
         {$filters["sql"]}
         ORDER BY l.checkin_date DESC, l.luggage_id DESC",
        $filters["types"],
        $filters["params"]
    );
}

function __($text): string
{
    if ($text === null || $text === "") {
        return "";
    }
    $text = (string)$text;
    static $translations = null;
    if ($translations === null) {
        $lang = $_SESSION["lang"] ?? "en";
        if ($lang === "so") {
            $file = __DIR__ . "/lang/so.php";
            if (is_file($file)) {
                $translations = require $file;
            } else {
                $translations = [];
            }
        } else {
            $translations = [];
        }
    }
    return $translations[$text] ?? $text;
}

function upload_checkout_photo(string $field, ?string $old_path = null): ?string
{
    return upload_image_file($field, "uploads/checkout", $old_path, "Checkout Photo");
}

function get_active_alerts(): array
{
    $alerts = [];
    
    // 1. Overdue luggage alerts
    $overdue_items = db_fetch_all(
        "SELECT l.luggage_id, l.tag_code, g.guest_name, l.expected_pickup_date
         FROM luggage l
         JOIN guests g ON g.guest_id = l.guest_id
         WHERE l.status = 'Stored' AND l.expected_pickup_date IS NOT NULL AND l.expected_pickup_date < CURDATE() AND l.deleted_at IS NULL"
    );
    foreach ($overdue_items as $item) {
        $alerts[] = [
            "id" => "overdue-" . $item["luggage_id"],
            "type" => "overdue",
            "title" => __("Overdue Luggage Alert"),
            "message" => __("Guest") . " " . h($item["guest_name"]) . " (" . h($item["tag_code"]) . ") " . __("was expected to pick up luggage on") . " " . h($item["expected_pickup_date"]) . ".",
            "link" => "checkout.php?id=" . $item["luggage_id"]
        ];
    }
    
    // 2. Capacity alerts (>= 90%)
    $shelves = db_fetch_all(
        "SELECT s.shelf_name, s.zone_name, s.capacity, COUNT(l.luggage_id) AS used_count
         FROM storage_shelves s
         LEFT JOIN luggage l ON l.storage_location = s.shelf_name AND l.status = 'Stored' AND l.deleted_at IS NULL
         WHERE s.status = 'Active'
         GROUP BY s.shelf_id"
    );
    foreach ($shelves as $shelf) {
        $used = (int)$shelf["used_count"];
        $capacity = (int)$shelf["capacity"];
        if ($capacity > 0 && ($used / $capacity) >= 0.9) {
            $percent = round(($used / $capacity) * 100);
            $alerts[] = [
                "id" => "capacity-" . $shelf["shelf_name"],
                "type" => "capacity",
                "title" => __("Capacity Warning Alert"),
                "message" => __("Shelf") . " " . h($shelf["shelf_name"]) . " " . __("in Zone") . " " . h($shelf["zone_name"]) . " " . __("is at") . " " . $percent . "% " . __("capacity") . " (" . $used . "/" . $capacity . " " . __("spaces") . ").",
                "link" => "storage_map.php"
            ];
        }
    }
    
    return $alerts;
}

function get_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($key === '__refresh__') {
        $cache = null;
        return null;
    }
    if ($cache === null) {
        $cache = [];
        try {
            $rows = db_fetch_all("SELECT setting_key, setting_value FROM system_settings");
            foreach ($rows as $r) {
                $cache[$r["setting_key"]] = $r["setting_value"];
            }
        } catch (Throwable $e) {
            // Table uninitialized
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    db_execute(
        "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
        "sss",
        [$key, $value, $value]
    );
    get_setting('__refresh__');
}

function trigger_api_notification(int $luggage_id, string $action): void
{
    $row = db_fetch_one(
        "SELECT l.*, g.guest_name, g.phone, g.room_no, g.email
         FROM luggage l JOIN guests g ON g.guest_id = l.guest_id
         WHERE l.luggage_id = ?",
        "i",
        [$luggage_id]
    );
    if (!$row) return;

    $sms_enabled = get_setting("sms_enabled", "0") === "1";
    $whatsapp_enabled = get_setting("whatsapp_enabled", "0") === "1";
    
    $guest_phone = phone_for_whatsapp($row["phone"]);
    if (empty($guest_phone)) return;

    if ($action === "checkin" || $action === "checkout") {
        $message = receipt_message($row);
    }

    if ($sms_enabled) {
        $endpoint = get_setting("sms_api_endpoint");
        $key = get_setting("sms_api_key");
        $sender = get_setting("sms_sender_id", "JOWHARA");
        
        // Simulating the actual curl/API call to Hormuud/SomNet/Twilio
        // and logging it in the notifications table.
        log_notification($luggage_id, (int)$row["guest_id"], "SMS", $row["phone"], $message, "Sent");
        
        // Log simulated HTTP request payload in audit log
        log_action("Simulated SMS API Request", "settings", null, "Sent request to $endpoint for recipient $guest_phone with Sender ID $sender");
    }

    if ($whatsapp_enabled) {
        $endpoint = get_setting("whatsapp_api_endpoint");
        $token = get_setting("whatsapp_api_token");
        
        log_notification($luggage_id, (int)$row["guest_id"], "WhatsApp", $row["phone"], $message, "Sent");
        log_action("Simulated WhatsApp API Request", "settings", null, "Sent request to $endpoint for recipient $guest_phone");
    }
}

// ═══════════════════════════════════════════════════════════════════
// v3.0 — In-App Notification Push Helper
// ═══════════════════════════════════════════════════════════════════

function push_notification(int $user_id, string $title, string $body, string $link = '', string $type = 'info'): void
{
    try {
        db_execute(
            "INSERT INTO user_notifications (user_id, title, body, link, type) VALUES (?, ?, ?, ?, ?)",
            "issss",
            [$user_id, $title, $body, $link, $type]
        );
    } catch (Throwable $e) {
        // Silently ignore if table not yet created
    }
}

function push_notification_all(string $title, string $body, string $link = '', string $type = 'info'): void
{
    try {
        $users = db_fetch_all("SELECT user_id FROM users WHERE status = 'Active' AND deleted_at IS NULL");
        foreach ($users as $u) {
            push_notification((int)$u['user_id'], $title, $body, $link, $type);
        }
    } catch (Throwable $e) {
        // Silently ignore
    }
}

function get_unread_notification_count(): int
{
    $user = current_user();
    if (!$user) return 0;
    try {
        return (int)db_scalar(
            "SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND read_at IS NULL AND is_dismissed = 0",
            "i",
            [(int)$user['user_id']]
        );
    } catch (Throwable $e) {
        return 0;
    }
}

// ═══════════════════════════════════════════════════════════════════
// v3.0 — TOTP Two-Factor Authentication (RFC 6238, pure PHP)
// ═══════════════════════════════════════════════════════════════════

function totp_generate_secret(int $length = 16): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    $random = random_bytes($length);
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[ord($random[$i]) & 31];
    }
    return $secret;
}

function totp_base32_decode(string $secret): string
{
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper($secret);
    $buffer = 0;
    $bitsLeft = 0;
    $decoded = '';
    for ($i = 0; $i < strlen($secret); $i++) {
        $val = strpos($base32chars, $secret[$i]);
        if ($val === false) continue;
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $decoded .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $decoded;
}

function totp_get_code(string $secret, int $timeSlice = 0): string
{
    if ($timeSlice === 0) {
        $timeSlice = (int)floor(time() / 30);
    }
    $key = totp_base32_decode($secret);
    $msg = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $msg, $key, true);
    $offset = ord($hash[19]) & 0xF;
    $code = (
        ((ord($hash[$offset])     & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
         (ord($hash[$offset + 3]) & 0xFF)
    ) % 1000000;
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

function totp_verify(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    $timeSlice = (int)floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_get_code($secret, $timeSlice + $i), $code)) {
            return true;
        }
    }
    return false;
}

function totp_qr_url(string $username, string $secret, string $issuer = 'Jowhara Hotel'): string
{
    $label = rawurlencode($issuer . ':' . $username);
    $params = http_build_query([
        'secret' => $secret,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => '6',
        'period' => '30',
    ]);
    $otpauth = 'otpauth://totp/' . $label . '?' . $params;
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpauth);
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)  return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}

function render_notification_template(string $type, array $data): string
{
    $guest_name = $data["guest_name"] ?? "Guest";
    $tag_code = $data["tag_code"] ?? "";
    $expected_date = $data["expected_pickup_date"] ?? "";

    if ($type === "pickup_reminder") {
        return sprintf(
            __("Dear %s, a friendly reminder from Jowhara Hotel that your stored luggage (%s) is scheduled for pickup today. Thank you!"),
            $guest_name,
            $tag_code
        );
    }

    if ($type === "overdue_warning") {
        return sprintf(
            __("URGENT: Dear %s, your stored luggage (%s) at Jowhara Hotel is overdue since %s. Daily penalty fee is active. Please contact us!"),
            $guest_name,
            $tag_code,
            $expected_date
        );
    }

    if ($type === "feedback_request") {
        return sprintf(
            __("Dear %s, thank you for choosing Jowhara Hotel Luggage Storage! We would appreciate your feedback."),
            $guest_name
        );
    }

    return "Jowhara Hotel Luggage Notification for " . $guest_name;
}

function get_shelf_utilization_analytics(): array
{
    $rows = db_fetch_all(
        "SELECT 
            s.zone_name,
            SUM(s.capacity) AS total_capacity,
            COUNT(l.luggage_id) AS used_capacity
         FROM storage_shelves s
         LEFT JOIN luggage l ON l.storage_zone = s.zone_name AND l.status = 'Stored' AND l.deleted_at IS NULL
         WHERE s.status = 'Active'
         GROUP BY s.zone_name"
    );

    $zones = [];
    $total_system_capacity = 0;
    $total_system_used = 0;

    foreach ($rows as $r) {
        $cap = max(1, (int)$r["total_capacity"]);
        $used = (int)$r["used_capacity"];
        $pct = round(($used / $cap) * 100, 1);
        $total_system_capacity += $cap;
        $total_system_used += $used;

        $zones[] = [
            "zone_name" => $r["zone_name"],
            "total_capacity" => $cap,
            "used_capacity" => $used,
            "utilization_pct" => $pct,
            "status" => ($pct >= 90) ? "CRITICAL" : (($pct >= 75) ? "HIGH" : "NORMAL")
        ];
    }

    $overall_pct = $total_system_capacity > 0 ? round(($total_system_used / $total_system_capacity) * 100, 1) : 0;

    return [
        "total_capacity" => $total_system_capacity,
        "total_used" => $total_system_used,
        "overall_utilization_pct" => $overall_pct,
        "zones" => $zones
    ];
}


<?php
require_once __DIR__ . "/layout.php";
require_login();

// Only allow Admins and Managers to access system settings
if (!is_admin() && (current_user()["role"] ?? "") !== "manager") {
    http_response_code(403);
    die(__("Permission is required."));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    
    // Check if it's a simulation action
    if (isset($_POST["simulate_action"])) {
        $sim_action = $_POST["simulate_action"];
        
        if ($sim_action === "pickup_reminder") {
            $bags = db_fetch_all(
                "SELECT l.*, g.guest_name, g.phone, g.email
                 FROM luggage l
                 JOIN guests g ON g.guest_id = l.guest_id
                 WHERE l.status = 'Stored' AND l.deleted_at IS NULL AND DATE(l.expected_pickup_date) = CURDATE()"
            );
            $count = 0;
            foreach ($bags as $bag) {
                $msg = sprintf(
                    __("Dear %s, a friendly reminder from Jowhara Hotel that your stored luggage (%s) is scheduled for pickup today. Thank you!"),
                    $bag["guest_name"],
                    $bag["tag_code"]
                );
                log_notification($bag["luggage_id"], $bag["guest_id"], "SMS", $bag["phone"] ?: $bag["email"], $msg, "Sent");
                $count++;
            }
            if ($count > 0) {
                set_flash("success", sprintf(__("Simulated %d expected-pickup daily reminders sent successfully!"), $count));
            } else {
                set_flash("info", __("No stored bags are expected for pickup today to simulate."));
            }
        }
        
        elseif ($sim_action === "overdue_warning") {
            $bags = db_fetch_all(
                "SELECT l.*, g.guest_name, g.phone, g.email
                 FROM luggage l
                 JOIN guests g ON g.guest_id = l.guest_id
                 WHERE l.status = 'Stored' AND l.deleted_at IS NULL AND l.expected_pickup_date < CURDATE()"
            );
            $count = 0;
            foreach ($bags as $bag) {
                $msg = sprintf(
                    __("URGENT: Dear %s, your stored luggage (%s) at Jowhara Hotel is overdue since %s. Daily penalty fee is active. Please contact us!"),
                    $bag["guest_name"],
                    $bag["tag_code"],
                    $bag["expected_pickup_date"]
                );
                log_notification($bag["luggage_id"], $bag["guest_id"], "WhatsApp", $bag["phone"] ?: $bag["email"], $msg, "Sent");
                $count++;
            }
            if ($count > 0) {
                set_flash("success", sprintf(__("Simulated %d overdue warning notifications sent successfully!"), $count));
            } else {
                set_flash("info", __("No stored bags are currently overdue to simulate."));
            }
        }
        
        elseif ($sim_action === "feedback_request") {
            $bags = db_fetch_all(
                "SELECT l.*, g.guest_name, g.phone, g.email
                 FROM luggage l
                 JOIN guests g ON g.guest_id = l.guest_id
                 WHERE l.status = 'Collected' AND l.deleted_at IS NULL AND DATE(l.checkout_date) = CURDATE()"
            );
            $count = 0;
            foreach ($bags as $bag) {
                $msg = sprintf(
                    __("Dear %s, thank you for choosing Jowhara Hotel Luggage Storage! We would appreciate your feedback. Please rate us here: https://jowharahotel.com/feedback?ref=%s"),
                    $bag["guest_name"],
                    $bag["tag_code"]
                );
                log_notification($bag["luggage_id"], $bag["guest_id"], "Email", $bag["email"] ?: $bag["phone"], $msg, "Sent");
                $count++;
            }
            if ($count > 0) {
                set_flash("success", sprintf(__("Simulated %d post-checkout feedback requests sent successfully!"), $count));
            } else {
                set_flash("info", __("No bags were checked out/collected today to simulate."));
            }
        }
        
        redirect("settings.php");
    }

    try {
        $fields = [
            "daily_penalty_fee", "grace_hours", "sms_enabled", "sms_api_endpoint",
            "sms_api_key", "sms_sender_id", "whatsapp_enabled",
            "whatsapp_api_endpoint", "whatsapp_api_token",
            "currency_symbol", "currency_code", "date_format", "auto_backup",
            "company_name", "company_phone", "company_email", "company_address",
            "smtp_host", "smtp_port", "smtp_user", "smtp_pass", "smtp_from", "smtp_from_name"
        ];

        foreach ($fields as $field) {
            $value = trim($_POST[$field] ?? "");
            if (in_array($field, ["sms_enabled","whatsapp_enabled","auto_backup"])) {
                $value = $value === "1" ? "1" : "0";
            }
            if ($field === "daily_penalty_fee") {
                $value = number_format(max(0, (float)$value), 2, '.', '');
            }
            if ($field === "grace_hours") {
                $value = (string)max(0, (int)$value);
            }
            db_execute(
                "INSERT INTO system_settings (setting_key, setting_value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                 "ss", [$field, $value]
            );
        }

        log_action("Updated system settings", "settings", null, "Settings saved");
        set_flash("success", __("System settings updated successfully."));
        redirect("settings.php");
    } catch (Throwable $e) {
        set_flash("danger", $e->getMessage());
        redirect("settings.php");
    }
}

// Retrieve current configurations
$settings = [];
$rows = db_fetch_all("SELECT * FROM system_settings");
foreach ($rows as $r) {
    $settings[$r["setting_key"]] = $r["setting_value"];
}

$daily_penalty_fee    = $settings["daily_penalty_fee"]    ?? "5.00";
$grace_hours          = $settings["grace_hours"]          ?? "2";
$sms_enabled          = $settings["sms_enabled"]          ?? "0";
$sms_api_endpoint     = $settings["sms_api_endpoint"]     ?? "https://api.sms.hormuud.com/v1/send";
$sms_api_key          = $settings["sms_api_key"]          ?? "";
$sms_sender_id        = $settings["sms_sender_id"]        ?? "JOWHARA";
$whatsapp_enabled     = $settings["whatsapp_enabled"]     ?? "0";
$whatsapp_api_endpoint= $settings["whatsapp_api_endpoint"]?? "https://api.whatsapp.com/v1/send";
$whatsapp_api_token   = $settings["whatsapp_api_token"]   ?? "";
$currency_symbol      = $settings["currency_symbol"]      ?? "$";
$currency_code        = $settings["currency_code"]        ?? "USD";
$date_format          = $settings["date_format"]          ?? "Y-m-d";
$auto_backup          = $settings["auto_backup"]          ?? "0";

$active_tab = $_GET["tab"] ?? "notifications";

render_header("System Settings", "settings");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <span class="admin-page-icon"><i class="bi bi-gear-wide-connected"></i></span>
            <?php echo __("System Settings"); ?>
        </h1>
        <p class="text-secondary mb-0"><?php echo __("Configure notifications, currency, date format, and backup options."); ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="dashboard.php"><?php echo __("Back"); ?></a>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-0" id="settingsTabs">
    <li class="nav-item">
        <a class="nav-link d-flex align-items-center gap-1 <?php echo $active_tab==="company"?"active":""; ?>"
           href="settings.php?tab=company"><i class="bi bi-building"></i> <?php echo __("Company & SMTP"); ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link d-flex align-items-center gap-1 <?php echo $active_tab==="notifications"?"active":""; ?>"
           href="settings.php?tab=notifications"><i class="bi bi-bell"></i> <?php echo __("Notification Settings"); ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link d-flex align-items-center gap-1 <?php echo $active_tab==="currency"?"active":""; ?>"
           href="settings.php?tab=currency"><i class="bi bi-currency-exchange"></i> <?php echo __("Currency & Format"); ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link d-flex align-items-center gap-1 <?php echo $active_tab==="backup"?"active":""; ?>"
           href="settings.php?tab=backup"><i class="bi bi-cloud-arrow-up"></i> <?php echo __("Backup Settings"); ?></a>
    </li>
</ul>


<!-- Tab Content -->
<div class="tab-content">

<!-- ═══ TAB 1: Notification Settings ═══ -->
<div class="tab-pane <?php echo $active_tab==="notifications"?"show active":""; ?>" style="<?php echo $active_tab!=="notifications"?"display:none":""; ?>">
<form class="row g-4 mt-0 pt-3" method="POST" action="settings.php?tab=notifications">
    <?php echo csrf_field(); ?>

    <!-- Penalty Engine -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-transparent border-bottom p-3">
                <h2 class="section-title text-primary d-flex align-items-center gap-2">
                    <i class="bi bi-cash-coin"></i> <?php echo __("Overdue Penalty Engine"); ?>
                </h2>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="daily_penalty_fee"><?php echo __("Daily Penalty Fee"); ?> ($)</label>
                    <input class="form-control" type="number" min="0" step="0.01" id="daily_penalty_fee" name="daily_penalty_fee" value="<?php echo h($daily_penalty_fee); ?>" required>
                    <div class="form-text"><?php echo __("Charge added per day past the expected pickup date."); ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="grace_hours"><?php echo __("Grace Hours"); ?></label>
                    <input class="form-control" type="number" min="0" id="grace_hours" name="grace_hours" value="<?php echo h($grace_hours); ?>" required>
                    <div class="form-text"><?php echo __("Hours allowed past the pickup time before charges apply."); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- SMS Gateway -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-transparent border-bottom p-3 d-flex justify-content-between align-items-center">
                <h2 class="section-title text-success d-flex align-items-center gap-2">
                    <i class="bi bi-chat-left-dots"></i> <?php echo __("Somali SMS API Gateway"); ?>
                </h2>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="sms_enabled" name="sms_enabled" value="1" <?php echo $sms_enabled==="1"?"checked":""; ?>>
                    <label class="form-check-label text-secondary small fw-bold" for="sms_enabled"><?php echo __("Enable"); ?></label>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="sms_api_endpoint"><?php echo __("SMS API Endpoint"); ?></label>
                    <input class="form-control" type="url" id="sms_api_endpoint" name="sms_api_endpoint" value="<?php echo h($sms_api_endpoint); ?>">
                </div>
                <div class="row g-2">
                    <div class="col-md-8 mb-3">
                        <label class="form-label" for="sms_api_key"><?php echo __("SMS API Token / Key"); ?></label>
                        <input class="form-control" type="password" id="sms_api_key" name="sms_api_key" value="<?php echo h($sms_api_key); ?>" placeholder="••••••••">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="sms_sender_id"><?php echo __("Sender ID"); ?></label>
                        <input class="form-control" type="text" id="sms_sender_id" name="sms_sender_id" value="<?php echo h($sms_sender_id); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- WhatsApp Gateway -->
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-bottom p-3 d-flex justify-content-between align-items-center">
                <h2 class="section-title text-info d-flex align-items-center gap-2">
                    <i class="bi bi-whatsapp"></i> <?php echo __("WhatsApp Business Notification Gateway"); ?>
                </h2>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="whatsapp_enabled" name="whatsapp_enabled" value="1" <?php echo $whatsapp_enabled==="1"?"checked":""; ?>>
                    <label class="form-check-label text-secondary small fw-bold" for="whatsapp_enabled"><?php echo __("Enable"); ?></label>
                </div>
            </div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="whatsapp_api_endpoint"><?php echo __("WhatsApp API Endpoint"); ?></label>
                    <input class="form-control" type="url" id="whatsapp_api_endpoint" name="whatsapp_api_endpoint" value="<?php echo h($whatsapp_api_endpoint); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="whatsapp_api_token"><?php echo __("WhatsApp API Token"); ?></label>
                    <input class="form-control" type="password" id="whatsapp_api_token" name="whatsapp_api_token" value="<?php echo h($whatsapp_api_token); ?>" placeholder="••••••••">
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-12 text-end">
        <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check2-all me-1"></i><?php echo __("Save Settings"); ?></button>
    </div>
</form>
</div><!-- /tab notifications -->

<!-- ═══ TAB 2: Currency & Format ═══ -->
<div class="tab-pane <?php echo $active_tab==="currency"?"show active":""; ?>" style="<?php echo $active_tab!=="currency"?"display:none":""; ?>">
<form class="row g-4 pt-3" method="POST" action="settings.php?tab=currency">
    <?php echo csrf_field(); ?>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom p-3">
                <h2 class="section-title d-flex align-items-center gap-2">
                    <i class="bi bi-currency-exchange text-warning"></i> <?php echo __("Currency Settings"); ?>
                </h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold"><?php echo __("Currency Symbol"); ?></label>
                        <input class="form-control" type="text" name="currency_symbol" value="<?php echo h($currency_symbol); ?>" placeholder="$" maxlength="5">
                        <div class="form-text">e.g. $, €, £, SOS</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold"><?php echo __("Currency Code"); ?></label>
                        <input class="form-control" type="text" name="currency_code" value="<?php echo h($currency_code); ?>" placeholder="USD" maxlength="10">
                        <div class="form-text">e.g. USD, EUR, SOS</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom p-3">
                <h2 class="section-title d-flex align-items-center gap-2">
                    <i class="bi bi-calendar3 text-info"></i> <?php echo __("Date Format"); ?>
                </h2>
            </div>
            <div class="card-body">
                <label class="form-label fw-semibold"><?php echo __("Date Display Format"); ?></label>
                <select class="form-select" name="date_format">
                    <?php foreach ([
                        "Y-m-d"  => "2026-05-31 (ISO Standard)",
                        "d/m/Y"  => "31/05/2026 (European)",
                        "m/d/Y"  => "05/31/2026 (US)",
                        "d-m-Y"  => "31-05-2026 (Dashed)",
                        "M j, Y" => "May 31, 2026 (Readable)",
                    ] as $fmt => $label): ?>
                    <option value="<?php echo h($fmt); ?>" <?php echo $date_format===$fmt?"selected":""; ?>>
                        <?php echo h($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text mt-2">Current: <strong><?php echo date($date_format); ?></strong></div>
            </div>
        </div>
    </div>
    <div class="col-12 text-end">
        <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check2-all me-1"></i><?php echo __("Save Currency & Format"); ?></button>
    </div>
</form>
</div><!-- /tab currency -->

<!-- ═══ TAB 3: Backup Settings ═══ -->
<div class="tab-pane <?php echo $active_tab==="backup"?"show active":""; ?>" style="<?php echo $active_tab!=="backup"?"display:none":""; ?>">
<form class="row g-4 pt-3" method="POST" action="settings.php?tab=backup">
    <?php echo csrf_field(); ?>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom p-3">
                <h2 class="section-title d-flex align-items-center gap-2">
                    <i class="bi bi-hdd-stack text-success"></i> <?php echo __("Backup Configuration"); ?>
                </h2>
            </div>
            <div class="card-body">
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="auto_backup" name="auto_backup" value="1" <?php echo $auto_backup==="1"?"checked":""; ?>>
                    <label class="form-check-label fw-semibold" for="auto_backup"><?php echo __("Enable Auto Backup (reminder only)"); ?></label>
                </div>
                <div class="alert alert-info d-flex gap-2">
                    <i class="bi bi-info-circle-fill fs-5 flex-shrink-0"></i>
                    <div>
                        <div class="fw-semibold"><?php echo __("Storage Location"); ?></div>
                        <code class="small"><?php echo h(realpath(__DIR__ . "/uploads")); ?></code>
                    </div>
                </div>
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i><?php echo __("Save Backup Settings"); ?></button>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom p-3">
                <h2 class="section-title d-flex align-items-center gap-2">
                    <i class="bi bi-cloud-arrow-down text-primary"></i> <?php echo __("Manual Backup"); ?>
                </h2>
            </div>
            <div class="card-body">
                <p class="text-muted small"><?php echo __("Download a full SQL backup of your database right now."); ?></p>
                <a href="admin_database.php" class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-database-gear"></i> <?php echo __("Go to Database Management"); ?>
                </a>
            </div>
        </div>
    </div>
</form>
</div><!-- /tab backup -->

</div><!-- /tab-content -->

<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light border-bottom p-3">
                <h2 class="section-title text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-cpu-fill"></i> <?php echo __("Enterprise Automation Simulation Console"); ?>
                </h2>
            </div>
            <div class="card-body">
                <p class="text-secondary small mb-4">
                    <?php echo __("Simulate daily cron jobs and automated hospitality alerts. Triggering these will scan active database records, generate custom localized templates, and dispatch them to the notification engine logs."); ?>
                </p>
                <div class="row g-3">
                    <!-- Expected Pickup Simulation -->
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h3 class="h6 fw-bold mb-2 text-primary"><i class="bi bi-clock-history me-1"></i><?php echo __("Expected Pickup Reminders"); ?></h3>
                                <p class="text-secondary small mb-3">
                                    <?php echo __("Scan stored luggage expected for collection today and dispatch a friendly checkout reminder."); ?>
                                </p>
                            </div>
                            <form method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="simulate_action" value="pickup_reminder">
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-play-circle-fill me-1"></i><?php echo __("Simulate Reminders"); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Overdue Simulation -->
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h3 class="h6 fw-bold mb-2 text-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo __("Overdue Daily Warnings"); ?></h3>
                                <p class="text-secondary small mb-3">
                                    <?php echo __("Scan active storage items whose expected pickup date has expired, warning guests of penalty fees."); ?>
                                </p>
                            </div>
                            <form method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="simulate_action" value="overdue_warning">
                                <button type="submit" class="btn btn-sm btn-outline-warning w-100">
                                    <i class="bi bi-play-circle-fill me-1"></i><?php echo __("Simulate Overdue Alerts"); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Post-Checkout Feedback -->
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h3 class="h6 fw-bold mb-2 text-success"><i class="bi bi-chat-heart-fill me-1"></i><?php echo __("Post-Checkout Feedback Requests"); ?></h3>
                                <p class="text-secondary small mb-3">
                                    <?php echo __("Scan luggage collections completed today and dispatch a personalized guest feedback survey."); ?>
                                </p>
                            </div>
                            <form method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="simulate_action" value="feedback_request">
                                <button type="submit" class="btn btn-sm btn-outline-success w-100">
                                    <i class="bi bi-play-circle-fill me-1"></i><?php echo __("Simulate Survey Requests"); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<style>
.admin-page-icon{width:40px;height:40px;border-radius:12px;background:var(--brand-light);color:var(--brand);display:inline-flex;align-items:center;justify-content:center;font-size:1.2rem;}
</style>
<?php render_footer(); ?>

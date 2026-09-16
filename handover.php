<?php
require_once __DIR__ . "/layout.php";
require_login();

$current_user_id = (int)current_user()["user_id"];
$users = db_fetch_all("SELECT user_id, full_name, username, role FROM users WHERE status = 'Active' AND user_id != ? ORDER BY full_name", "i", [$current_user_id]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    try {
        $incoming_user_id = (int)($_POST["incoming_user_id"] ?? 0);
        $notes = trim($_POST["handover_notes"] ?? "");

        if ($incoming_user_id <= 0) {
            throw new RuntimeException(__("Please select the incoming receptionist taking over your shift."));
        }
        if ($notes === "") {
            throw new RuntimeException(__("Please write handover notes for the incoming receptionist."));
        }

        db_execute(
            "INSERT INTO shift_handovers (outgoing_user_id, incoming_user_id, handover_notes, status)
             VALUES (?, ?, ?, 'Pending')",
            "iis",
            [$current_user_id, $incoming_user_id, $notes]
        );

        log_action("Shift Handover Logged", "shift", null, "Outgoing user handed over shift to User ID $incoming_user_id");
        
        // Log out the user immediately for security
        set_flash("success", __("Shift handover logged. Logged out successfully."));
        redirect("logout.php");
    } catch (Throwable $e) {
        set_flash("danger", $e->getMessage());
        redirect("handover.php");
    }
}

render_header("Shift Handover", "shift");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Shift Handover"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Submit notes and hand over the active luggage storage desk to the next receptionist."); ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="dashboard.php"><?php echo __("Back"); ?></a>
</div>

<div class="card shadow-sm border-0 max-width-820 mx-auto">
    <div class="card-header bg-transparent border-bottom p-3">
        <h2 class="section-title text-primary d-flex align-items-center gap-2">
            <i class="bi bi-clock-history"></i> <?php echo __("Log Handover Details"); ?>
        </h2>
    </div>
    <div class="card-body p-4">
        <form method="POST" id="handoverForm">
            <?php echo csrf_field(); ?>
            
            <div class="mb-4">
                <label class="form-label" for="incoming_user_id"><?php echo __("Incoming Receptionist (Taking Over)"); ?></label>
                <select class="form-select" id="incoming_user_id" name="incoming_user_id" required>
                    <option value=""><?php echo __("-- Select Receptionist --"); ?></option>
                    <?php foreach ($users as $usr): ?>
                        <option value="<?php echo (int)$usr["user_id"]; ?>">
                            <?php echo h($usr["full_name"] ?: $usr["username"]); ?> (<?php echo h(role_label($usr["role"])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint mt-1"><?php echo __("Select the colleague who is taking over the desk."); ?></div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="handover_notes"><?php echo __("Handover Instructions / Notes"); ?></label>
                <textarea class="form-control" id="handover_notes" name="handover_notes" rows="6" placeholder="<?php echo __("Write notes here... (e.g. VIP guest in Room 202 expected late, check bag conditions for LS000212)"); ?>" required></textarea>
                <div class="form-hint mt-1"><?php echo __("Provide key info about active, overdue, or high-value bags."); ?></div>
            </div>

            <div class="alert alert-warning d-flex align-items-center gap-2 mb-4 small">
                <i class="bi bi-shield-lock-fill fs-5"></i>
                <span><?php echo __("Security notice: Submitting the shift handover will automatically log you out so your colleague can log in."); ?></span>
            </div>

            <div class="d-grid">
                <button class="btn btn-primary btn-lg" type="submit">
                    <i class="bi bi-box-arrow-right me-1"></i> <?php echo __("Submit Handover & Logout"); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php render_footer(); ?>

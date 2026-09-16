<?php
require_once __DIR__ . "/layout.php";
require_login();
render_header("Access Denied", "");
?>
<div class="d-flex flex-column align-items-center justify-content-center text-center py-5 my-5">
    <div class="mb-4 text-danger animate-bounce">
        <i class="bi bi-shield-slash" style="font-size: 5.5rem; display: inline-block;"></i>
    </div>
    <h1 class="h3 fw-bold text-dark mb-2"><?php echo __("Access Denied"); ?></h1>
    <p class="text-secondary mb-4 px-3" style="max-width: 480px; font-size: 0.95rem; line-height: 1.6;">
        <?php echo __("You do not have the required security permissions to access this page or module."); ?><br>
        <span class="small text-muted"><?php echo __("If you believe this is an error, please contact your administrator to check your role settings."); ?></span>
    </p>
    <div>
        <?php
        $default_page = (current_user()["role"] ?? "") === "lost_found_officer" ? "lost_found.php" : "dashboard.php";
        ?>
        <a class="btn btn-primary px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm" href="<?php echo h($default_page); ?>">
            <i class="bi bi-arrow-left-short fs-5"></i> <?php echo __("Go to My Dashboard"); ?>
        </a>
    </div>
</div>

<style>
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
.animate-bounce {
    animation: bounce 2s infinite ease-in-out;
}
</style>
<?php
render_footer();
?>

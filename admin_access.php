<?php
require_once __DIR__ . "/layout.php";
require_admin();

// Permission definitions
$all_permissions = [
    "view","create","edit","checkout","reports","audit","shelves",
    "soft_delete","restore","bulk","notifications","shift","manager_dashboard",
    "lost_found"
];
$all_roles = ["admin","manager","staff","receptionist","security_officer","lost_found_officer","viewer"];

$role_defaults = [
    "admin"              => ["*"],
    "manager"            => ["view","create","edit","checkout","reports","audit","shelves","soft_delete","restore","bulk","notifications","shift","manager_dashboard"],
    "staff"              => ["view","create","edit","checkout","reports","bulk","notifications","shift"],
    "receptionist"       => ["view","create","edit","checkout","reports","bulk","notifications","shift"],
    "security_officer"   => ["view","lost_found","audit","reports","shift"],
    "lost_found_officer" => ["lost_found"],
    "viewer"             => ["view","reports","shift"],
];

// Handle save
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $new_perms = [];
    foreach ($all_roles as $role) {
        if ($role === "admin") { $new_perms[$role] = ["*"]; continue; }
        $new_perms[$role] = [];
        foreach ($all_permissions as $perm) {
            if (!empty($_POST["perm"][$role][$perm])) {
                $new_perms[$role][] = $perm;
            }
        }
    }
    db_execute("INSERT INTO system_settings (setting_key, setting_value) VALUES ('role_permissions',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
        "s", [json_encode($new_perms)]);
    log_action("Updated role permissions", "settings", null, "Permission matrix saved");
    set_flash("success", "Role permissions updated successfully.");
    redirect("admin_access.php");
}

// Load saved or use defaults
$saved_raw = db_scalar("SELECT setting_value FROM system_settings WHERE setting_key='role_permissions'");
$perms = $saved_raw ? (json_decode($saved_raw, true) ?: $role_defaults) : $role_defaults;

// Menu map
$menu_items = [
    "Dashboard"        => ["admin","manager","staff","receptionist","security_officer","viewer"],
    "Reports"          => ["admin","manager","staff","receptionist","security_officer","viewer"],
    "L&F Reports"      => ["admin","manager","security_officer","lost_found_officer"],
    "Guests"           => ["admin","manager","staff","receptionist"],
    "Luggage"          => ["admin","manager","staff","receptionist"],
    "Scanner"          => ["admin","manager","staff","receptionist"],
    "Storage Map"      => ["admin","manager","staff","receptionist"],
    "Shift"            => ["admin","manager","staff","receptionist","viewer"],
    "Handover"         => ["admin","manager","staff","receptionist"],
    "Lost & Found"     => ["admin","manager","staff","receptionist","security_officer","lost_found_officer"],
    "Add L&F Item"     => ["admin","manager","staff","receptionist","security_officer","lost_found_officer"],
    "Manager"          => ["admin","manager"],
    "Settings"         => ["admin","manager"],
    "Administration"   => ["admin"],
];

// Page restrictions
$page_restrictions = [
    ["dashboard.php",         "require_login()",                        "All logged-in users (except L&F Officer)"],
    ["guests.php",            "require_login()",                        "All logged-in users (except L&F Officer)"],
    ["luggage.php",           "require_login()",                        "All logged-in users (except L&F Officer)"],
    ["reports.php",           "require_login()",                        "All logged-in users"],
    ["scanner.php",           "require_login()",                        "All logged-in users (except L&F Officer)"],
    ["storage_map.php",       "require_login()",                        "All logged-in users (except L&F Officer)"],
    ["lost_found.php",        "require_permission('lost_found')",       "L&F Officer, Security Officer, Admin, Manager"],
    ["add_lost_found.php",    "require_permission('lost_found')",       "L&F Officer, Security Officer, Admin, Manager"],
    ["edit_lost_found.php",   "require_permission('lost_found')",       "L&F Officer, Security Officer, Admin, Manager"],
    ["claim_lost_found.php",  "require_permission('lost_found')",       "L&F Officer, Security Officer, Admin, Manager"],
    ["settings.php",          "admin/manager",                          "Admin & Manager"],
    ["manager.php",           "manager_dashboard",                      "Manager & Admin"],
    ["audit_log.php",         "user_can('audit')",                      "Admin & Manager"],
    ["login_history.php",     "user_can('audit')",                      "Admin & Manager"],
    ["admin_users.php",       "require_admin()",                        "Admin only"],
    ["admin_access.php",      "require_admin()",                        "Admin only"],
    ["admin_company.php",     "require_admin()",                        "Admin only"],
    ["admin_database.php",    "require_admin()",                        "Admin only"],
    ["admin_server.php",      "require_admin()",                        "Admin only"],
    ["admin_tables.php",      "require_admin()",                        "Admin only"],
    ["admin_audit.php",       "require_admin()",                        "Admin only"],
    ["admin_import.php",      "require_admin()",                        "Admin only"],
    ["admin_transactions.php","require_admin()",                        "Admin only"],
];

render_header("Access Control", "admin_access");
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <span class="admin-page-icon"><i class="bi bi-shield-lock"></i></span>
            Access Control
        </h1>
        <p class="text-secondary mb-0">Manage role permissions, menu access, and page restrictions.</p>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-0" id="accessTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabPerms"><i class="bi bi-toggles me-1"></i>Role Permissions</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabMenu"><i class="bi bi-list-ul me-1"></i>Menu Permissions</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabPages"><i class="bi bi-file-lock me-1"></i>Page Restrictions</a></li>
</ul>

<div class="tab-content">

<!-- ── Tab 1: Role Permissions ── -->
<div class="tab-pane fade show active" id="tabPerms">
<form method="POST">
    <?php echo csrf_field(); ?>
    <div class="card" style="border-top-left-radius:0; border-top-right-radius:0; border-top:none;">
        <div class="card-header bg-transparent border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Permission Matrix</span>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2 me-1"></i>Save Permissions</button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 perm-matrix">
                <thead>
                    <tr>
                        <th style="min-width:180px;">Permission</th>
                        <?php foreach ($all_roles as $role): ?>
                        <th class="text-center">
                            <span class="badge text-bg-<?php echo ['admin'=>'danger','manager'=>'warning','staff'=>'info','receptionist'=>'primary','viewer'=>'secondary'][$role]??'secondary'; ?>">
                                <?php echo role_label($role); ?>
                            </span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($all_permissions as $perm): ?>
                <tr>
                    <td><span class="perm-label"><i class="bi bi-check2-circle me-1 text-muted"></i><?php echo h($perm); ?></span></td>
                    <?php foreach ($all_roles as $role): ?>
                    <td class="text-center">
                        <?php if ($role === "admin"): ?>
                            <i class="bi bi-check-circle-fill text-success fs-5" title="Always granted"></i>
                        <?php else:
                            $has = in_array("*", $perms[$role] ?? []) || in_array($perm, $perms[$role] ?? []);
                        ?>
                            <div class="form-check d-flex justify-content-center mb-0">
                                <input class="form-check-input perm-check" type="checkbox" 
                                    name="perm[<?php echo h($role); ?>][<?php echo h($perm); ?>]" value="1"
                                    <?php echo $has ? "checked" : ""; ?>>
                            </div>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>
</div>

<!-- ── Tab 2: Menu Permissions ── -->
<div class="tab-pane fade" id="tabMenu">
<div class="card" style="border-top-left-radius:0; border-top-right-radius:0; border-top:none;">
    <div class="card-header bg-transparent border-bottom px-4 py-3">
        <span class="fw-semibold text-muted small">Read-only view of which roles see each menu item</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr>
                <th style="min-width:160px;">Menu Item</th>
                <?php foreach ($all_roles as $r): ?>
                <th class="text-center">
                    <span class="badge text-bg-<?php echo ['admin'=>'danger','manager'=>'warning','staff'=>'info','receptionist'=>'primary','viewer'=>'secondary'][$r]??'secondary'; ?>">
                        <?php echo role_label($r); ?>
                    </span>
                </th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($menu_items as $menu => $allowed_roles): ?>
            <tr>
                <td class="fw-semibold small"><?php echo h($menu); ?></td>
                <?php foreach ($all_roles as $r): ?>
                <td class="text-center">
                    <?php if (in_array($r, $allowed_roles)): ?>
                        <i class="bi bi-check-circle-fill text-success"></i>
                    <?php else: ?>
                        <i class="bi bi-x-circle text-muted opacity-25"></i>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- ── Tab 3: Page Restrictions ── -->
<div class="tab-pane fade" id="tabPages">
<div class="card" style="border-top-left-radius:0; border-top-right-radius:0; border-top:none;">
    <div class="card-header bg-transparent border-bottom px-4 py-3">
        <span class="fw-semibold text-muted small">Page-level access requirements</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Page File</th><th>Guard Function</th><th>Access Level</th></tr></thead>
            <tbody>
            <?php foreach ($page_restrictions as [$file, $guard, $level]): ?>
            <tr>
                <td class="font-monospace small"><?php echo h($file); ?></td>
                <td><code class="small"><?php echo h($guard); ?></code></td>
                <td>
                    <?php
                    $lc = $level;
                    $bc = str_contains($lc, "Admin only") ? "danger" : (str_contains($lc, "Manager") ? "warning" : "secondary");
                    ?>
                    <span class="badge text-bg-<?php echo $bc; ?>"><?php echo h($level); ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

</div><!-- /tab-content -->

<style>
.admin-page-icon { width:40px; height:40px; border-radius:12px; background:var(--brand-light); color:var(--brand); display:inline-flex; align-items:center; justify-content:center; font-size:1.2rem; }
.perm-matrix thead th { background:var(--surface-soft); position:sticky; top:0; z-index:1; }
.perm-matrix tbody tr:hover { background:var(--surface-soft); }
.perm-label { font-size:.85rem; font-weight:600; font-family:monospace; }
.perm-check { width:1.15rem; height:1.15rem; cursor:pointer; }
</style>
<?php render_footer(); ?>

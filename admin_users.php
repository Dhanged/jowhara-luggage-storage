<?php
require_once __DIR__ . "/layout.php";
require_admin();

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   POST HANDLER
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";

    // â”€â”€ Create â”€â”€
    if ($action === "create") {
        $fn   = trim($_POST["full_name"] ?? "");
        $un   = trim($_POST["username"]  ?? "");
        $pw   = (string)($_POST["password"] ?? "");
        $role = $_POST["role"] ?? "receptionist";
        $em   = trim($_POST["email"] ?? "");
        $ph   = trim($_POST["phone"] ?? "");
        if (!in_array($role, ["admin","manager","staff","receptionist","security_officer","lost_found_officer","viewer"], true)) $role = "receptionist";
        if ($fn === "" || $un === "" || strlen($pw) < 6) {
            set_flash("danger", "Full name, username and password (min 6 chars) are required.");
        } else {
            $exists = db_fetch_one("SELECT user_id FROM users WHERE username = ? AND deleted_at IS NULL", "s", [$un]);
            if ($exists) {
                set_flash("warning", "Username already exists.");
            } else {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                db_execute("INSERT INTO users (full_name, username, password_hash, role, email, phone, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')", "ssssss", [$fn, $un, $hash, $role, $em, $ph]);
                $uid = db_insert_id();
                log_action("Created user", "user", $uid, "User $un created as " . role_label($role));
                set_flash("success", "User \"$fn\" created successfully.");
            }
        }
        redirect("admin_users.php");
    }

    // â”€â”€ Edit â”€â”€
    if ($action === "edit") {
        $uid  = (int)($_POST["user_id"] ?? 0);
        $fn   = trim($_POST["full_name"] ?? "");
        $role = $_POST["role"] ?? "receptionist";
        $em   = trim($_POST["email"] ?? "");
        $ph   = trim($_POST["phone"] ?? "");
        if (!in_array($role, ["admin","manager","staff","receptionist","security_officer","lost_found_officer","viewer"], true)) $role = "receptionist";
        db_execute("UPDATE users SET full_name=?, role=?, email=?, phone=? WHERE user_id=?", "ssssi", [$fn, $role, $em, $ph, $uid]);
        log_action("Updated user", "user", $uid, "Updated profile: role=$role");
        set_flash("success", "User updated.");
        redirect("admin_users.php");
    }

    // â”€â”€ Reset Password â”€â”€
    if ($action === "reset_password") {
        $uid = (int)($_POST["user_id"] ?? 0);
        $pw  = (string)($_POST["new_password"] ?? "");
        $admin_pass = (string)($_POST["admin_password"] ?? "");
        if (!verify_admin_password($admin_pass)) {
            set_flash("danger", "Admin password verification required to reset password.");
        } elseif (strlen($pw) < 6) {
            set_flash("danger", "Password must be at least 6 characters.");
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            db_execute("UPDATE users SET password_hash=? WHERE user_id=?", "si", [$hash, $uid]);
            log_action("Reset password", "user", $uid, "Password reset by admin with password verification");
            set_flash("success", "Password reset successfully.");
        }
        redirect("admin_users.php");
    }

    // â”€â”€ Toggle Status â”€â”€
    if ($action === "toggle") {
        $uid = (int)($_POST["user_id"] ?? 0);
        if ($uid === (int)current_user()["user_id"]) {
            set_flash("warning", "You cannot deactivate your own account.");
        } else {
            $u = db_fetch_one("SELECT username, status FROM users WHERE user_id=?", "i", [$uid]);
            if ($u) {
                $next = $u["status"] === "Active" ? "Inactive" : "Active";
                db_execute("UPDATE users SET status=? WHERE user_id=?", "si", [$next, $uid]);
                log_action("Toggled user status", "user", $uid, "{$u['username']} set to $next");
                set_flash("success", "User status updated to $next.");
            }
        }
        redirect("admin_users.php");
    }

    // â”€â”€ Delete (soft) â”€â”€
    if ($action === "delete") {
        $uid = (int)($_POST["user_id"] ?? 0);
        $admin_pass = (string)($_POST["admin_password"] ?? "");
        if (!verify_admin_password($admin_pass)) {
            set_flash("danger", "Admin password verification required to delete user.");
        } elseif ($uid === (int)current_user()["user_id"]) {
            set_flash("warning", "You cannot delete your own account.");
        } else {
            $u = db_fetch_one("SELECT username FROM users WHERE user_id=?", "i", [$uid]);
            db_execute("UPDATE users SET deleted_at=NOW(), status='Inactive' WHERE user_id=?", "i", [$uid]);
            log_action("Deleted user", "user", $uid, "Soft-deleted user " . ($u["username"] ?? "") . " with admin password verification");
            set_flash("success", "User deleted.");
        }
        redirect("admin_users.php");
    }
}

/* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
   DATA
â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
$users       = db_fetch_all("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY role, full_name");
$total       = count($users);
$active_cnt  = count(array_filter($users, fn($u) => $u["status"] === "Active"));
$inactive_cnt= $total - $active_cnt;
$admin_cnt   = count(array_filter($users, fn($u) => $u["role"] === "admin"));

render_header("User Management", "admin_users");
?>
<!-- Page Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <span class="admin-page-icon"><i class="bi bi-people-gear"></i></span>
            User Management
        </h1>
        <p class="text-secondary mb-0">Create and manage system user accounts and roles.</p>
    </div>
    <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddUser">
        <i class="bi bi-person-plus-fill"></i> Add User
    </button>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ["Total Users",   $total,        "#3b82f6", "bi-people-fill"],
        ["Active",        $active_cnt,   "#10b981", "bi-check-circle-fill"],
        ["Inactive",      $inactive_cnt, "#94a3b8", "bi-x-circle-fill"],
        ["Administrators",$admin_cnt,    "#ef4444", "bi-shield-fill"],
    ] as [$lbl, $val, $clr, $ico]): ?>
    <div class="col-6 col-md-3">
        <div class="card admin-stat-card" style="--sc:#<?php echo ltrim($clr,'#'); ?>; border-left:4px solid <?php echo $clr; ?>;">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="admin-stat-icon" style="background:<?php echo $clr; ?>20; color:<?php echo $clr; ?>;"><i class="bi <?php echo $ico; ?>"></i></div>
                <div>
                    <div class="admin-stat-label"><?php echo $lbl; ?></div>
                    <div class="admin-stat-value"><?php echo $val; ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header bg-transparent border-bottom px-4 py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h2 class="section-title mb-0">All Accounts</h2>
        <input class="form-control form-control-sm" type="search" id="userSearch" placeholder="Search usersâ€¦" style="max-width:220px;">
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0" id="usersTable">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>Contact</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <?php
                $initials = strtoupper(substr($u["full_name"] ?: $u["username"], 0, 2));
                $role_colors = ["admin"=>"danger","manager"=>"warning","staff"=>"info","receptionist"=>"primary","security_officer"=>"dark","lost_found_officer"=>"success","viewer"=>"secondary"];
                $rc = $role_colors[$u["role"]] ?? "secondary";
                $is_self = (int)$u["user_id"] === (int)current_user()["user_id"];
            ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="user-avatar" style="background:var(--brand);"><?php echo h($initials); ?></div>
                        <div>
                            <div class="fw-semibold"><?php echo h($u["full_name"]); ?></div>
                            <?php if ($u["email"]): ?><div class="text-muted small"><?php echo h($u["email"]); ?></div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td class="fw-bold font-monospace"><?php echo h($u["username"]); ?></td>
                <td class="text-muted small"><?php echo $u["phone"] ? h($u["phone"]) : "â€”"; ?></td>
                <td><span class="badge text-bg-<?php echo $rc; ?>"><?php echo h(role_label($u["role"])); ?></span></td>
                <td><span class="badge text-bg-<?php echo $u["status"]==="Active" ? "success" : "secondary"; ?>"><?php echo h($u["status"]); ?></span></td>
                <td class="text-muted small"><?php echo date("M j, Y", strtotime($u["created_at"])); ?></td>
                <td>
                    <div class="table-actions justify-content-end gap-1">
                        <!-- Edit -->
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditUser"
                            data-uid="<?php echo (int)$u['user_id']; ?>"
                            data-fn="<?php echo h($u['full_name']); ?>"
                            data-role="<?php echo h($u['role']); ?>"
                            data-email="<?php echo h($u['email']); ?>"
                            data-phone="<?php echo h($u['phone']); ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <!-- Reset PW -->
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalResetPw"
                            data-uid="<?php echo (int)$u['user_id']; ?>"
                            data-uname="<?php echo h($u['full_name'] ?: $u['username']); ?>">
                            <i class="bi bi-key"></i>
                        </button>
                        <!-- Toggle -->
                        <form method="POST" class="d-inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" <?php echo $is_self ? "disabled" : ""; ?> title="<?php echo $u['status']==='Active'?'Deactivate':'Activate'; ?>">
                                <i class="bi bi-<?php echo $u['status']==='Active'?'pause-circle':'play-circle'; ?>"></i>
                            </button>
                        </form>
                        <!-- Delete -->
                        <?php if (!$is_self): ?>
                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalDeleteUser"
                            data-uid="<?php echo (int)$u['user_id']; ?>"
                            data-uname="<?php echo h($u['full_name'] ?: $u['username']); ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
            <tr><td colspan="7"><div class="empty-state">No users found.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- â•â•â• MODAL: Add User â•â•â• -->
<div class="modal fade" id="modalAddUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="full_name" required placeholder="John Doe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="username" required placeholder="johndoe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input class="form-control" type="password" name="password" minlength="6" required placeholder="min 8 chars recommended" id="addPassword"><div class="progress mt-2" style="height: 5px;"><div class="progress-bar" id="addPwBar" role="progressbar" style="width: 0%"></div></div><div id="addPwText" class="mt-1"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input class="form-control" type="email" name="email" placeholder="user@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input class="form-control" type="text" name="phone" placeholder="+252â€¦">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role">
                                <option value="receptionist">Receptionist</option>
                                <option value="staff">Staff</option>
                                <option value="security_officer">Security Officer</option><option value="manager">Manager</option>
                                <option value="lost_found_officer">Lost & Found Officer</option>
                                <option value="viewer">Viewer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- â•â•â• MODAL: Edit User â•â•â• -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input class="form-control" type="text" name="full_name" id="editFullName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input class="form-control" type="email" name="email" id="editEmail">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input class="form-control" type="text" name="phone" id="editPhone">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Role</label>
                            <select class="form-select" name="role" id="editRole">
                                <option value="receptionist">Receptionist</option>
                                <option value="staff">Staff</option>
                                <option value="security_officer">Security Officer</option><option value="manager">Manager</option>
                                <option value="lost_found_officer">Lost & Found Officer</option>
                                <option value="viewer">Viewer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white"><i class="bi bi-check2 me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ❖ MODAL: Reset Password ❖ -->
<div class="modal fade" id="modalResetPw" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-key-fill me-2 text-warning"></i>Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetPwUserId">
                <div class="modal-body">
                    <p class="text-muted mb-3">Resetting password for: <strong id="resetPwUserName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                        <input class="form-control" type="password" name="new_password" minlength="6" required placeholder="Min 6 characters">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Your Admin Password <span class="text-danger">*</span></label>
                        <input class="form-control" type="password" name="admin_password" required placeholder="Enter your current password">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white"><i class="bi bi-key me-1"></i>Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ❖ MODAL: Delete User ❖ -->
<div class="modal fade" id="modalDeleteUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-trash-fill me-2"></i>Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="modal-body">
                    <div class="alert alert-danger d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        <div>Are you sure you want to delete <strong id="deleteUserName"></strong>? This action cannot be undone.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.admin-page-icon {
    width:40px; height:40px; border-radius:12px;
    background:var(--brand-light); color:var(--brand);
    display:inline-flex; align-items:center; justify-content:center; font-size:1.2rem;
}
.admin-stat-card { border-radius:14px; box-shadow:var(--shadow-card); }
.admin-stat-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.admin-stat-label { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-muted); }
.admin-stat-value { font-size:1.6rem; font-weight:800; color:var(--ink); line-height:1; }
.user-avatar {
    width:38px; height:38px; border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:.78rem; font-weight:800; flex-shrink:0;
}
</style>

<script>
// Live search filter
document.getElementById("userSearch").addEventListener("input", function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll("#usersTable tbody tr").forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? "" : "none";
    });
});

// Edit modal populate
document.getElementById("modalEditUser").addEventListener("show.bs.modal", function(e) {
    const btn = e.relatedTarget;
    document.getElementById("editUserId").value   = btn.dataset.uid;
    document.getElementById("editFullName").value = btn.dataset.fn;
    document.getElementById("editEmail").value    = btn.dataset.email;
    document.getElementById("editPhone").value    = btn.dataset.phone;
    document.getElementById("editRole").value     = btn.dataset.role;
});

// Reset PW modal
document.getElementById("modalResetPw").addEventListener("show.bs.modal", function(e) {
    const btn = e.relatedTarget;
    document.getElementById("resetPwUserId").value    = btn.dataset.uid;
    document.getElementById("resetPwUserName").textContent = btn.dataset.uname;
});

// Delete modal
document.getElementById("modalDeleteUser").addEventListener("show.bs.modal", function(e) {
    const btn = e.relatedTarget;
    document.getElementById("deleteUserId").value          = btn.dataset.uid;
    document.getElementById("deleteUserName").textContent  = btn.dataset.uname;

    // Password Strength Check
    function initPasswordStrength(inputId, barId, textId) {
        const input = document.getElementById(inputId);
        const bar = document.getElementById(barId);
        const text = document.getElementById(textId);
        if(!input || !bar || !text) return;
        
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
    }

    initPasswordStrength('addPassword', 'addPwBar', 'addPwText');
    initPasswordStrength('resetPassword', 'resetPwBar', 'resetPwText');
});
</script>
<?php render_footer(); ?>

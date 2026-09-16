<?php
require_once __DIR__ . "/functions.php";

function sidebar_link(string $href, string $label, string $active, string $key, string $icon = ""): string
{
    $is_active = $active === $key;
    $class     = $is_active ? "sidebar-link active" : "sidebar-link";
    $icon_html = $icon !== "" ? '<i class="bi ' . h($icon) . ' sidebar-icon"></i>' : '';
    $lbl_html  = '<span class="sidebar-label">' . h(__($label)) . '</span>';
    return '<a class="' . $class . '" href="' . h($href) . '" title="' . h(__($label)) . '">' . $icon_html . $lbl_html . '</a>';
}

function render_header(string $title, string $active = ""): void
{
    send_security_headers();
    $user  = current_user();
    $flash = get_flash();
    $lang_code = $_SESSION["lang"] ?? "en";
    $dir = ($lang_code === "ar") ? 'dir="rtl"' : '';
    ?>
<!doctype html>
<html lang="<?= h($lang_code) ?>" <?= $dir ?> data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h(__($title)); ?> | Jowhara Hotel</title>
    
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563eb">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">
    <link href="assets/app.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body class="skeleton-transition">

<?php if ($user): ?>
<!-- Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ TOP-BAR (utility only, no nav links) Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ -->
<header class="app-topbar" id="appTopbar">
    <div class="topbar-left">
        <!-- Hamburger: mobile sidebar toggle -->
        <button class="topbar-hamburger d-lg-none" id="mobileSidebarToggle" type="button" aria-label="Menu">
            <i class="bi bi-list"></i>
        </button>
        <!-- Desktop: collapse sidebar icon -->
        <button class="topbar-hamburger d-none d-lg-flex" id="desktopSidebarToggle" type="button" aria-label="Toggle Sidebar">
            <i class="bi bi-layout-sidebar"></i>
        </button>
        <?php
        $brand_url = ($user && $user["role"] === "lost_found_officer") ? "lost_found.php" : "dashboard.php";
        ?>
        <a class="topbar-brand" href="<?= $brand_url ?>">
            <img src="assets/logo.jpg" alt="Jowhara Logo" class="topbar-brand-logo">
            <span class="topbar-brand-name">Jowhara Hotel</span>
        </a>
    </div>

    <div class="topbar-right">
        <!-- PWA Install Button (hidden by default, shown via JS) -->
        <button id="pwaInstallBtn" class="btn btn-sm btn-outline-primary fw-bold d-none me-2">
            <i class="bi bi-download me-1"></i><?= __("Install App") ?>
        </button>

        <!-- Notifications Bell (v3.0) -->
        <div class="dropdown">
            <button class="topbar-icon-btn position-relative" type="button" data-bs-toggle="dropdown"
                    aria-expanded="false" id="bellDropdown" onclick="fetchNotifications()">
                <i class="bi bi-bell"></i>
                <span class="topbar-badge d-none" id="notifBadge">0</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0"
                aria-labelledby="bellDropdown"
                style="width:340px;max-height:400px;overflow-y:auto;border-radius:14px;">
                <li class="p-3 border-bottom d-flex justify-content-between align-items-center"
                    style="background:var(--surface-soft);border-radius:14px 14px 0 0;">
                    <span class="fw-bold small">
                        <i class="bi bi-bell-fill text-primary me-1"></i><?php echo __("Notifications"); ?>
                    </span>
                    <a href="notifications_center.php" class="small text-decoration-none fw-semibold">
                        <?php echo __("View All"); ?>
                    </a>
                </li>
                <div id="notifDropdownList">
                    <li class="p-4 text-center text-muted">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    </li>
                </div>
            </ul>
        </div>

        <!-- Language Switcher -->
        <?php
        $current_lang = $_SESSION["lang"] ?? "en";
        $switch_lang  = $current_lang === "en" ? "so" : "en";
        $switch_label = $current_lang === "en" ? "SO" : "EN";
        ?>
        <a class="topbar-icon-btn" href="change_lang.php?lang=<?php echo $switch_lang; ?>" title="Switch Language">
            <i class="bi bi-translate"></i>
            <span class="d-none d-sm-inline" style="font-size:.75rem;font-weight:700;"><?php echo $switch_label; ?></span>
        </a>

        <!-- Theme Toggle -->
        <button class="topbar-icon-btn" id="themeToggle" type="button" title="Toggle Theme">
            <i class="bi bi-moon-stars" id="themeIcon"></i>
        </button>

        <!-- Help Center -->
        <a class="topbar-icon-btn" href="help.php" title="<?php echo __('Help Center'); ?>">
            <i class="bi bi-question-circle"></i>
        </a>

        <!-- User Dropdown -->
        <div class="dropdown">
            <button class="topbar-user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="topbar-avatar">
                    <?php echo strtoupper(substr($user["full_name"] ?: $user["username"], 0, 1)); ?>
                </div>
                <span class="d-none d-md-block">
                    <span class="topbar-username"><?php echo h($user["full_name"] ?: $user["username"]); ?></span>
                    <span class="topbar-role"><?php echo h(role_label($user["role"])); ?></span>
                </span>
                <i class="bi bi-chevron-down topbar-chevron d-none d-md-block"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="min-width:180px;border-radius:14px;">
                <li>
                    <a class="dropdown-item rounded-3 d-flex align-items-center gap-2 py-2"
                       href="change_password.php">
                        <i class="bi bi-person-gear text-secondary"></i><?php echo __("My Profile"); ?>
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item rounded-3 d-flex align-items-center gap-2 py-2 text-danger"
                       href="logout.php">
                        <i class="bi bi-box-arrow-right"></i><?php echo __("Logout"); ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ SIDEBAR + CONTENT SHELL Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ -->
<div class="app-shell" id="appShell">

    <!-- Sidebar -->
    <aside class="app-sidebar" id="appSidebar">

        <nav class="sidebar-nav">

            <?php if (($user["role"] ?? "") === "lost_found_officer"): ?>

                <!-- LOST & FOUND OFFICER: Restricted Menu -->
                <div class="sidebar-group-label"><?php echo __("Lost & Found"); ?></div>
                <?php echo sidebar_link("lost_found.php",    "Lost & Found",      $active, "lost_found", "bi-search"); ?>
                <?php echo sidebar_link("add_lost_found.php","Report Found Item",  $active, "add_lf",    "bi-plus-circle"); ?>
                <?php echo sidebar_link("reports.php",       "L&F Reports",        $active, "reports",   "bi-graph-up"); ?>

            <?php else: ?>

                <!-- MAIN -->
                <div class="sidebar-group-label">Main</div>
                <?php echo sidebar_link("dashboard.php", "Dashboard", $active, "dashboard", "bi-speedometer2"); ?>
                <?php echo sidebar_link("reports.php",   "Reports",   $active, "reports",   "bi-graph-up");     ?>
                <?php echo sidebar_link("help.php",      "Help Center", $active, "help",      "bi-question-circle"); ?>

                <!-- OPERATIONS -->
                <div class="sidebar-group-label">Operations</div>
                <?php echo sidebar_link("guests.php",      "Guests",      $active, "guests",    "bi-people");           ?>
                <?php echo sidebar_link("luggage.php",     "Luggage",     $active, "luggage",   "bi-briefcase");        ?>
                <?php echo sidebar_link("scanner.php",     "Scanner",     $active, "scanner",   "bi-qr-code-scan");     ?>
                <?php echo sidebar_link("storage_map.php", "Storage Map", $active, "storage",   "bi-map");              ?>
                <?php echo sidebar_link("shift_report.php","Shift",       $active, "shift",     "bi-clock-history");    ?>
                <?php echo sidebar_link("handover.php",    "Handover",    $active, "handover",  "bi-arrow-left-right"); ?>
                <?php if (user_can("lost_found")): ?>
                    <?php echo sidebar_link("lost_found.php",  "Lost & Found",$active, "lost_found","bi-search");       ?>
                <?php endif; ?>

                <!-- MANAGEMENT -->
                <?php if (user_can("manager_dashboard")): ?>
                <div class="sidebar-group-label">Management</div>
                <?php echo sidebar_link("manager.php",   "Manager",   $active, "manager",   "bi-clipboard-data"); ?>
                <?php echo sidebar_link("approvals.php", "Approvals", $active, "approvals", "bi-shield-check"); ?>
                <?php echo sidebar_link("settings.php",  "Settings",  $active, "settings",  "bi-gear");           ?>
                <?php endif; ?>

                <!-- AUDIT -->
                <?php if (user_can("audit")): ?>
                <div class="sidebar-group-label">Audit</div>
                <?php echo sidebar_link("login_history.php", "Logins",    $active, "logins", "bi-shield-lock");   ?>
                <?php echo sidebar_link("audit_log.php",     "Audit Log", $active, "audit",  "bi-journal-text");  ?>
                <?php endif; ?>

                <!-- ADMIN MODULE -->
                <?php if (is_admin()): ?>
                <div class="sidebar-group-label">Administration</div>
                <?php echo sidebar_link("admin_users.php",        "User Management",   $active, "admin_users",   "bi-people-gear"); ?>
                <?php echo sidebar_link("admin_access.php",       "Access Control",    $active, "admin_access",  "bi-shield-lock"); ?>
                <?php echo sidebar_link("admin_company.php",      "Company Settings",  $active, "admin_company", "bi-building"); ?>
                <?php echo sidebar_link("admin_transactions.php", "Transactions",      $active, "admin_txn",     "bi-credit-card-2-front"); ?>
                <?php echo sidebar_link("admin_audit.php",        "Audit Trail",       $active, "admin_audit",   "bi-journal-text"); ?>
                <?php echo sidebar_link("admin_import.php",       "Import & Export",   $active, "admin_import",  "bi-cloud-arrow-up-down"); ?>
                <?php echo sidebar_link("settings.php",           "System Settings",   $active, "settings",      "bi-gear-wide-connected"); ?>
                <?php echo sidebar_link("admin_server.php",       "Server Settings",   $active, "admin_server",  "bi-hdd-network"); ?>
                <?php echo sidebar_link("admin_database.php",     "Database Mgmt",     $active, "admin_db",      "bi-database-gear"); ?>
                <?php echo sidebar_link("admin_tables.php",       "Tables",            $active, "admin_tables",  "bi-table"); ?>
                <?php endif; ?>

            <?php endif; ?>

        </nav>

        <!-- Sidebar footer: logout shortcut -->
        <div class="sidebar-footer">
            <a class="sidebar-link" href="logout.php" title="<?php echo __('Logout'); ?>">
                <i class="bi bi-box-arrow-right sidebar-icon"></i>
                <span class="sidebar-label"><?php echo __("Logout"); ?></span>
            </a>
        </div>
    </aside>

    <!-- Mobile overlay backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Main content -->
    <main class="app-main" id="appMain">
        <div class="app-content">

        <!-- Announcements Banner -->
        <?php
        try {
            $now = date('Y-m-d H:i:s');
            $announcements = db_fetch_all(
                "SELECT * FROM announcements 
                 WHERE deleted_at IS NULL 
                 AND (expires_at IS NULL OR expires_at > ?) 
                 ORDER BY created_at DESC", 
                "s", 
                [$now]
            );
            $abg = ['info'=>'primary', 'warning'=>'warning text-dark', 'danger'=>'danger', 'success'=>'success'];
            $aicon = ['info'=>'info-circle', 'warning'=>'exclamation-triangle', 'danger'=>'x-octagon', 'success'=>'check-circle'];
            
            foreach ($announcements as $a):
                $c = $abg[$a['type']] ?? 'primary';
                $i = $aicon[$a['type']] ?? 'info-circle';
        ?>
            <div class="alert alert-<?= $c ?> alert-dismissible fade show d-flex align-items-center gap-3 announcement-banner" 
                 role="alert" data-id="<?= $a['id'] ?>" style="display:none !important;">
                <i class="bi bi-<?= $i ?>-fill fs-4"></i>
                <div>
                    <h6 class="alert-heading mb-1 fw-bold"><?= h($a['title']) ?></h6>
                    <?php if ($a['body']): ?><p class="mb-0 small"><?= h($a['body']) ?></p><?php endif; ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="dismissAnnouncement(<?= $a['id'] ?>)"></button>
            </div>
        <?php endforeach; 
        } catch (Throwable $e) {} ?>

<?php else: ?>
<main class="app-main app-main--auth">
    <div class="app-content">
<?php endif; ?>

        <?php if ($flash): ?>
            <?php
            $alert_icon = [
                "success" => "bi-check-circle-fill",
                "danger"  => "bi-exclamation-triangle-fill",
                "warning" => "bi-exclamation-circle-fill",
                "info"    => "bi-info-circle-fill"
            ][$flash["type"]] ?? "bi-info-circle-fill";
            ?>
            <div class="alert alert-<?php echo h($flash["type"]); ?> alert-dismissible fade show mb-4 alert-flash d-flex align-items-center gap-2" role="alert">
                <i class="bi <?php echo $alert_icon; ?> fs-5 flex-shrink-0"></i>
                <div class="flex-grow-1"><?php echo h($flash["message"]); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
<?php
}

function render_footer(): void
{
    $user = current_user();
    ?>
        </div><!-- /.app-content -->
    </main><!-- /.app-main -->
<?php if ($user): ?>
</div><!-- /.app-shell -->
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    /* Auto dismiss flash messages */
    setTimeout(function() {
        document.querySelectorAll('.alert-flash').forEach(function(alertEl) {
            try {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                if (bsAlert) bsAlert.close();
            } catch(e) {}
        });
    }, 5000);

    /* Form double-submit prevention with loading spinner */
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (form.getAttribute('data-submitting') === 'true') {
                e.preventDefault();
                return false;
            }
            if (form.checkValidity && !form.checkValidity()) {
                return;
            }
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.classList.contains('no-spin')) {
                form.setAttribute('data-submitting', 'true');
                const origWidth = submitBtn.offsetWidth;
                submitBtn.style.minWidth = origWidth + 'px';
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + (submitBtn.getAttribute('data-loading-text') || 'Processing...');
            }
        });
    });
    /* Ã¢â€â‚¬Ã¢â€â‚¬ Theme Ã¢â€â‚¬Ã¢â€â‚¬ */
    const root   = document.documentElement;
    const themeBtn  = document.getElementById("themeToggle");
    const themeIcon = document.getElementById("themeIcon");
    const savedTheme = localStorage.getItem("luggage-theme") || "light";
    root.setAttribute("data-bs-theme", savedTheme);
    function applyThemeIcon(t) {
        if (!themeIcon) return;
        themeIcon.className = t === "dark" ? "bi bi-sun" : "bi bi-moon-stars";
    }
    applyThemeIcon(savedTheme);
    if (themeBtn) {
        themeBtn.addEventListener("click", function () {
            const next = root.getAttribute("data-bs-theme") === "dark" ? "light" : "dark";
            root.setAttribute("data-bs-theme", next);
            localStorage.setItem("luggage-theme", next);
            applyThemeIcon(next);
        });
    }

    /* Ã¢â€â‚¬Ã¢â€â‚¬ Inactivity Logout Ã¢â€â‚¬Ã¢â€â‚¬ */
    const timeoutMs = 30 * 60 * 1000;
    let inactivityTimer;
    function resetInactivityTimer() {
        window.clearTimeout(inactivityTimer);
        inactivityTimer = window.setTimeout(function () {
            window.location.href = "logout.php?timeout=1";
        }, timeoutMs);
    }
    ["click","keydown","mousemove","touchstart","scroll"].forEach(function (e) {
        document.addEventListener(e, resetInactivityTimer, { passive: true });
    });
    resetInactivityTimer();

    /* Ã¢â€â‚¬Ã¢â€â‚¬ Sidebar Ã¢â€â‚¬Ã¢â€â‚¬ */
    const sidebar   = document.getElementById("appSidebar");
    const shell     = document.getElementById("appShell");
    const backdrop  = document.getElementById("sidebarBackdrop");
    const desktopBtn= document.getElementById("desktopSidebarToggle");
    const mobileBtn = document.getElementById("mobileSidebarToggle");
    const CKEY      = "luggage-sidebar-collapsed";

    function isDesktop() { return window.innerWidth >= 992; }

    function setDesktopCollapsed(val) {
        if (!shell) return;
        shell.classList.toggle("sidebar-collapsed", val);
        localStorage.setItem(CKEY, val ? "1" : "0");
    }

    function openMobileSidebar() {
        if (sidebar)  sidebar.classList.add("mobile-open");
        if (backdrop) backdrop.classList.add("active");
        document.body.style.overflow = "hidden";
    }

    function closeMobileSidebar() {
        if (sidebar)  sidebar.classList.remove("mobile-open");
        if (backdrop) backdrop.classList.remove("active");
        document.body.style.overflow = "";
    }

    // Restore desktop collapse state
    if (isDesktop()) {
        setDesktopCollapsed(localStorage.getItem(CKEY) === "1");
    }

    if (desktopBtn) {
        desktopBtn.addEventListener("click", function () {
            const collapsed = shell && shell.classList.contains("sidebar-collapsed");
            setDesktopCollapsed(!collapsed);
        });
    }

    if (mobileBtn) {
        mobileBtn.addEventListener("click", openMobileSidebar);
    }

    if (backdrop) {
        backdrop.addEventListener("click", closeMobileSidebar);
    }

    window.addEventListener("resize", function () {
        if (isDesktop()) {
            closeMobileSidebar();
            setDesktopCollapsed(localStorage.getItem(CKEY) === "1");
        }
    });
}());
</script>
<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢<!-- Camera Mirroring Style -->
<style>
#puCameraVideo.mirror {
  transform: scaleX(-1);
}
</style>

<div class="modal fade" id="puCameraModal" tabindex="-1" aria-labelledby="puCameraModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px">
    <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius:18px">
      <div class="modal-header border-0 pb-0" style="background:linear-gradient(135deg,#0f766e,#1d6f6f)">
        <h5 class="modal-title text-white fw-bold" id="puCameraModalLabel">
          <i class="bi bi-camera-fill me-2"></i>Take Photo
        </h5>
        <button type="button" class="btn-close btn-close-white" id="puCameraClose" aria-label="Close"></button>
      </div>
      <!-- Live camera stream -->
      <div class="position-relative bg-black" style="aspect-ratio:4/3;overflow:hidden">
        <video id="puCameraVideo" autoplay playsinline muted
               style="width:100%;height:100%;object-fit:cover;display:block"></video>
        <!-- Viewfinder corners -->
        <div style="position:absolute;inset:0;pointer-events:none">
          <div style="position:absolute;top:16px;left:16px;width:40px;height:40px;border-top:3px solid rgba(255,255,255,.7);border-left:3px solid rgba(255,255,255,.7);border-radius:4px 0 0 0"></div>
          <div style="position:absolute;top:16px;right:16px;width:40px;height:40px;border-top:3px solid rgba(255,255,255,.7);border-right:3px solid rgba(255,255,255,.7);border-radius:0 4px 0 0"></div>
          <div style="position:absolute;bottom:16px;left:16px;width:40px;height:40px;border-bottom:3px solid rgba(255,255,255,.7);border-left:3px solid rgba(255,255,255,.7);border-radius:0 0 0 4px"></div>
          <div style="position:absolute;bottom:16px;right:16px;width:40px;height:40px;border-bottom:3px solid rgba(255,255,255,.7);border-right:3px solid rgba(255,255,255,.7);border-radius:0 0 4px 0"></div>
        </div>
        <!-- Permission error overlay -->
        <div id="puCameraErr" class="d-none position-absolute inset-0 d-flex flex-column align-items-center justify-content-center text-white text-center p-4"
             style="inset:0;background:rgba(0,0,0,.85)">
          <i class="bi bi-camera-video-off fs-1 mb-3 text-danger"></i>
          <p class="fw-semibold mb-1" id="puCameraErrMsg">Camera access denied or not available.</p>
          <p class="small opacity-75 mb-3">Please allow camera permission in your browser settings, or choose an image file from your device.</p>
          <button type="button" id="puCameraFallbackBtn" class="btn btn-light btn-sm fw-bold">
            <i class="bi bi-images me-1"></i>Choose from Gallery
          </button>
        </div>
      </div>
      <!-- Hidden canvas used for snapping -->
      <canvas id="puCameraCanvas" class="d-none"></canvas>
      <div class="modal-footer border-0 justify-content-center gap-3 py-3"
           style="background:#0f172a">
        <!-- Switch camera (mobile) -->
        <button type="button" id="puCameraFlip" class="btn btn-outline-light btn-sm rounded-circle d-none"
                style="width:42px;height:42px" title="Flip camera">
          <i class="bi bi-arrow-repeat"></i>
        </button>
        <!-- Snap button -->
        <button type="button" id="puSnapBtn"
                style="width:70px;height:70px;border-radius:50%;border:4px solid #fff;background:linear-gradient(135deg,#0d9488,#0f766e);box-shadow:0 0 0 3px rgba(13,148,136,.4)"
                class="d-flex align-items-center justify-content-center p-0">
          <i class="bi bi-camera-fill text-white fs-3"></i>
        </button>
        <!-- Cancel -->
        <button type="button" id="puCameraCancel" class="btn btn-outline-light btn-sm rounded-circle"
                style="width:42px;height:42px" title="Cancel">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
/* Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â
   PHOTO UPLOADER ENGINE Ã¢â‚¬â€ getUserMedia camera modal
   "Take Photo" Ã¢â€ â€™ opens live camera in modal Ã¢â€ â€™ snap Ã¢â€ â€™ preview
   "Gallery"    Ã¢â€ â€™ plain file picker
   Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â */
(function () {
    const MAX_BYTES   = 3 * 1024 * 1024;
    const ALLOWED     = ['image/jpeg','image/png','image/webp','image/gif'];
    const ALLOWED_EXT = ['jpg','jpeg','png','webp','gif'];

    /* Ã¢â€â‚¬Ã¢â€â‚¬ DOM helpers Ã¢â€â‚¬Ã¢â€â‚¬ */
    function $id(id) { return document.getElementById(id); }

    function getEls(uid) {
        return {
            wrap:         $id(uid),
            fileInput:    $id(uid + '_file'),
            dropzone:     $id(uid + '_dropzone'),
            preview:      $id(uid + '_preview'),
            img:          $id(uid + '_preview_img'),
            error:        $id(uid + '_error'),
            removeHidden: $id(uid + '_remove'),
        };
    }

    /* Ã¢â€â‚¬Ã¢â€â‚¬ Error display for individual uploader Ã¢â€â‚¬Ã¢â€â‚¬ */
    function showError(uid, msg) {
        const err = $id(uid + '_error');
        if (!err) return;
        err.querySelector('span').textContent = msg;
        err.classList.remove('d-none');
        setTimeout(() => err.classList.add('d-none'), 5500);
    }
    function clearError(uid) {
        const err = $id(uid + '_error');
        if (err) err.classList.add('d-none');
    }

    function compressImage(file, callback) {
        if (file.type === 'image/gif') {
            callback(file);
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            const img = new Image();
            img.onload = function () {
                const maxDim = 1200;
                let w = img.width;
                let h = img.height;
                
                if (w > maxDim || h > maxDim) {
                    if (w > h) {
                        h = Math.round((h * maxDim) / w);
                        w = maxDim;
                    } else {
                        w = Math.round((w * maxDim) / h);
                        h = maxDim;
                    }
                }
                
                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);
                
                canvas.toBlob(function (blob) {
                    if (blob) {
                        const compressedFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                        callback(compressedFile);
                    } else {
                        callback(file);
                    }
                }, 'image/jpeg', 0.80);
            };
            img.onerror = function () {
                callback(file);
            };
            img.src = e.target.result;
        };
        reader.onerror = function () {
            callback(file);
        };
        reader.readAsDataURL(file);
    }

    /* Ã¢â€â‚¬Ã¢â€â‚¬ Accept & preview a File object Ã¢â€â‚¬Ã¢â€â‚¬ */
    function acceptFile(uid, file) {
        if (!file) return;
        const ext  = (file.name || '').split('.').pop().toLowerCase();
        const mime = file.type || '';
        if (!ALLOWED.includes(mime) && !ALLOWED_EXT.includes(ext)) {
            showError(uid, 'Invalid file type. Allowed: JPG, JPEG, PNG, WEBP, GIF.');
            return;
        }
        
        compressImage(file, function (processedFile) {
            if (processedFile.size > MAX_BYTES) {
                showError(uid, 'File too large. Maximum size is 3 MB.');
                return;
            }
            clearError(uid);
            const e = getEls(uid);

            /* Show preview image */
            const reader = new FileReader();
            reader.onload = function (ev) {
                if (e.img) e.img.src = ev.target.result;
                if (e.dropzone) e.dropzone.classList.add('d-none');
                if (e.preview) e.preview.classList.remove('d-none');
                if (e.removeHidden) e.removeHidden.value = '';
            };
            reader.readAsDataURL(processedFile);

            /* Assign to the submit input */
            if (e.fileInput) {
                try {
                    const dt = new DataTransfer();
                    dt.items.add(processedFile);
                    e.fileInput.files = dt.files;
                } catch (_) {}
            }
        });
    }

    /* Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â
       CAMERA MODAL (getUserMedia)
       Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â */
    let currentStream   = null;
    let currentUid      = null;
    let facingMode      = 'environment'; /* rear camera default */
    let bsModal         = null;

    const video       = $id('puCameraVideo');
    const canvas      = $id('puCameraCanvas');
    const errPanel    = $id('puCameraErr');
    const errMsg      = $id('puCameraErrMsg');
    const snapBtn     = $id('puSnapBtn');
    const flipBtn     = $id('puCameraFlip');
    const closeBtn    = $id('puCameraClose');
    const cancelBtn   = $id('puCameraCancel');
    const fallbackBtn = $id('puCameraFallbackBtn');
    const modalEl     = $id('puCameraModal');

    /* Stop all camera tracks */
    function stopStream() {
        if (currentStream) {
            currentStream.getTracks().forEach(t => t.stop());
            currentStream = null;
        }
        if (video) {
            video.srcObject = null;
            video.classList.remove('mirror');
        }
    }

    /* Check if multiple video devices are available */
    function checkMultipleCameras() {
        if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
            navigator.mediaDevices.enumerateDevices()
                .then(devices => {
                    const videoDevices = devices.filter(d => d.kind === 'videoinput');
                    if (videoDevices.length > 1) {
                        if (flipBtn) flipBtn.classList.remove('d-none');
                    } else {
                        if (flipBtn) flipBtn.classList.add('d-none');
                    }
                })
                .catch(() => {
                    if (flipBtn) flipBtn.classList.add('d-none');
                });
        } else {
            if (flipBtn) flipBtn.classList.add('d-none');
        }
    }

    /* Start camera stream */
    function startStream() {
        if (!video) return;
        if (errPanel) errPanel.classList.add('d-none');
        stopStream();

        const constraints = {
            video: { facingMode: facingMode, width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: false
        };

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showCameraError("Your browser or connection is not secure (requires HTTPS or localhost) and does not support camera access.");
            return;
        }

        navigator.mediaDevices.getUserMedia(constraints)
            .then(function (stream) {
                currentStream = stream;
                video.srcObject = stream;
                video.play();
                
                // Mirror the preview if using the front/user camera
                if (facingMode === 'user') {
                    video.classList.add('mirror');
                } else {
                    video.classList.remove('mirror');
                }
                
                checkMultipleCameras();
            })
            .catch(function (err) {
                console.error("Camera access error:", err);
                let message = "Camera access denied or not available.";
                if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                    message = "Camera permission was denied. Please allow camera permissions in your browser settings.";
                } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                    message = "No camera hardware was detected on this device.";
                } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                    message = "Camera is already in use by another application.";
                }
                showCameraError(message);
            });
    }

    function showCameraError(message) {
        if (errMsg) errMsg.textContent = message;
        if (errPanel) errPanel.classList.remove('d-none');
        stopStream();
    }

    /* Open modal + start camera */
    function openCameraModal(uid) {
        currentUid = uid;
        if (!modalEl) return;
        if (!bsModal) {
            bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
        }
        bsModal.show();
        startStream();
    }

    /* Close modal + stop camera */
    function closeCameraModal() {
        stopStream();
        if (bsModal) bsModal.hide();
    }

    /* Snap photo from video Ã¢â€ â€™ File Ã¢â€ â€™ acceptFile */
    function snapPhoto() {
        if (!video || !canvas || !currentStream) return;
        const w = video.videoWidth  || 1280;
        const h = video.videoHeight || 720;
        canvas.width  = w;
        canvas.height = h;
        
        const ctx = canvas.getContext('2d');
        
        // Mirror the canvas image ONLY if front/user camera is active (to match mirrored preview)
        if (facingMode === 'user') {
            ctx.translate(w, 0);
            ctx.scale(-1, 1);
        }
        
        ctx.drawImage(video, 0, 0, w, h);
        
        // Reset transform
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        
        canvas.toBlob(function (blob) {
            if (blob) {
                const file = new File([blob], 'camera_photo.jpg', { type: 'image/jpeg' });
                closeCameraModal();
                if (currentUid) acceptFile(currentUid, file);
            }
        }, 'image/jpeg', 0.90);
    }

    /* Flip between front/rear camera */
    function flipCamera() {
        facingMode = (facingMode === 'environment') ? 'user' : 'environment';
        startStream();
    }

    /* Fallback to gallery upload directly from modal */
    function triggerFallback() {
        closeCameraModal();
        if (currentUid) {
            openGallery(currentUid);
        }
    }

    /* Button event listeners */
    if (snapBtn)     snapBtn.addEventListener('click',  snapPhoto);
    if (flipBtn)     flipBtn.addEventListener('click',  flipCamera);
    if (closeBtn)    closeBtn.addEventListener('click', closeCameraModal);
    if (cancelBtn)   cancelBtn.addEventListener('click', closeCameraModal);
    if (fallbackBtn) fallbackBtn.addEventListener('click', triggerFallback);

    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', stopStream);
    }

    /* Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â
       GALLERY (file picker)
       Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â */
    function openGallery(uid) {
        const e = getEls(uid);
        if (!e.fileInput) return;
        e.fileInput.value = '';
        e.fileInput.click();
    }

    /* Ã¢â€â‚¬Ã¢â€â‚¬ Remove photo Ã¢â€â‚¬Ã¢â€â‚¬ */
    function removePhoto(uid, hadExisting) {
        const e = getEls(uid);
        if (e.fileInput) e.fileInput.value = '';
        if (e.img)       e.img.src         = '';
        if (e.preview)   e.preview.classList.add('d-none');
        if (e.dropzone)  e.dropzone.classList.remove('d-none');
        clearError(uid);
        if (hadExisting === '1' && e.removeHidden) {
            e.removeHidden.value = '1';
        }
    }

    /* Ã¢â€â‚¬Ã¢â€â‚¬ Wire up one uploader widget Ã¢â€â‚¬Ã¢â€â‚¬ */
    function initUploader(uid) {
        const e = getEls(uid);
        if (!e.wrap) return;

        /* Gallery file input change */
        if (e.fileInput) {
            e.fileInput.addEventListener('change', function () {
                if (this.files && this.files[0]) acceptFile(uid, this.files[0]);
            });
        }

        /* Click on drop-zone background Ã¢â€ â€™ gallery */
        if (e.dropzone) {
            e.dropzone.addEventListener('click', function (ev) {
                if (!ev.target.closest('button')) openGallery(uid);
            });
            /* Drag & Drop */
            e.dropzone.addEventListener('dragover', function (ev) {
                ev.preventDefault();
                e.dropzone.classList.add('drag-over');
            });
            e.dropzone.addEventListener('dragleave', function () {
                e.dropzone.classList.remove('drag-over');
            });
            e.dropzone.addEventListener('drop', function (ev) {
                ev.preventDefault();
                e.dropzone.classList.remove('drag-over');
                const file = ev.dataTransfer.files[0];
                if (file) acceptFile(uid, file);
            });
        }
    }

    /* Ã¢â€â‚¬Ã¢â€â‚¬ Global click delegation Ã¢â€â‚¬Ã¢â€â‚¬ */
    document.addEventListener('click', function (ev) {
        const btn = ev.target.closest('[data-uid][data-mode]');
        if (btn) {
            ev.stopPropagation();
            if (btn.dataset.mode === 'camera') {
                openCameraModal(btn.dataset.uid);
            } else {
                openGallery(btn.dataset.uid);
            }
            return;
        }
        const removeBtn = ev.target.closest('.pu-act-remove[data-uid]');
        if (removeBtn) {
            removePhoto(removeBtn.dataset.uid, removeBtn.dataset.hasExisting);
        }
    });

    /* Ã¢â€â‚¬Ã¢â€â‚¬ Auto-init on DOM ready Ã¢â€â‚¬Ã¢â€â‚¬ */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.photo-uploader-wrap[id]').forEach(function (el) {
            initUploader(el.id);
        });
    });
    /* â”€â”€ v3.0: Skeleton Loading Transitions â”€â”€ */
    window.addEventListener('beforeunload', function() {
        document.body.classList.add('skeleton-transitioning');
    });

    /* â”€â”€ v3.0: Announcements Dismissal â”€â”€ */
    document.querySelectorAll('.announcement-banner').forEach(function(el) {
        const id = el.dataset.id;
        if (!localStorage.getItem('dismissed_announcement_' + id)) {
            el.style.setProperty('display', 'flex', 'important');
        }
    });
    window.dismissAnnouncement = function(id) {
        localStorage.setItem('dismissed_announcement_' + id, 'true');
    };

    /* â”€â”€ v3.0: AJAX Notifications â”€â”€ */
    window.fetchNotifications = function() {
        fetch('ajax_notif_count.php')
            .then(res => res.json())
            .then(data => {
                let html = '';
                if (data.items && data.items.length > 0) {
                    data.items.forEach(n => {
                        html += `<li><a class="dropdown-item p-3 border-bottom text-wrap" href="${n.link}">
                            <div class="fw-bold small text-dark">${n.title}</div>
                            <div class="text-secondary small mt-1">${n.body}</div>
                        </a></li>`;
                    });
                } else {
                    html = '<li class="p-4 text-center text-muted"><i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i><small><?= __("No active alerts.") ?></small></li>';
                }
                document.getElementById('notifDropdownList').innerHTML = html;
            });
    };
    function updateNotifBadge() {
        fetch('ajax_notif_count.php').then(r=>r.json()).then(d=>{
            const b = document.getElementById('notifBadge');
            if (b && d.count > 0) {
                b.textContent = d.count;
                b.classList.remove('d-none');
            }
        });
    }
    if (document.getElementById('notifBadge')) setInterval(updateNotifBadge, 60000);
    setTimeout(updateNotifBadge, 1000);

    /* â”€â”€ v3.0: PWA Install & Service Worker â”€â”€ */
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('sw.js').catch(function(err) {
                console.log('SW registration failed: ', err);
            });
        });
    }
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        const installBtn = document.getElementById('pwaInstallBtn');
        if (installBtn) {
            installBtn.classList.remove('d-none');
            installBtn.addEventListener('click', () => {
                installBtn.classList.add('d-none');
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    deferredPrompt = null;
                });
            });
        }
    });

    /* â”€â”€ v3.0: Lightbox Image Zoom â”€â”€ */
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('zoomable')) {
            const overlay = document.createElement('div');
            overlay.className = 'lightbox-overlay';
            const img = document.createElement('img');
            img.src = e.target.src;
            overlay.appendChild(img);
            document.body.appendChild(overlay);
            requestAnimationFrame(() => overlay.classList.add('show'));
            
            overlay.addEventListener('click', function() {
                overlay.classList.remove('show');
                setTimeout(() => overlay.remove(), 300);
            });
        }
    });
}());
</script>
</body>
</html>
<?php
}

<?php
require_once __DIR__ . "/functions.php";

// Set language switcher session
if (isset($_GET["lang"])) {
    $_SESSION["lang"] = $_GET["lang"] === "so" ? "so" : "en";
    $redirect_url = "track.php?tag=" . urlencode($_GET["tag"] ?? "");
    header("Location: $redirect_url");
    exit;
}

$current_lang = $_SESSION["lang"] ?? "en";
$switch_lang = $current_lang === "en" ? "so" : "en";
$switch_label = $current_lang === "en" ? "Somali" : "English";

$tag_code = trim($_GET["tag"] ?? "");
$row = null;
$transfers = [];

if ($tag_code !== "") {
    $row = db_fetch_one(
        "SELECT l.*, g.guest_name, g.room_no
         FROM luggage l
         JOIN guests g ON g.guest_id = l.guest_id
         WHERE l.tag_code = ? AND l.deleted_at IS NULL",
        "s",
        [$tag_code]
    );

    if ($row) {
        $transfers = db_fetch_all(
            "SELECT * FROM luggage_transfers
             WHERE luggage_id = ?
             ORDER BY moved_at ASC",
            "i",
            [(int)$row["luggage_id"]]
        );
    }
}
?>
<!doctype html>
<html lang="<?php echo $current_lang; ?>" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo __("Track Luggage"); ?> | Jowhara Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-glow: radial-gradient(circle at 50% 50%, #153e3e 0%, #090d16 100%);
            --brand: #1d6f6f;
            --brand-glow: 0 0 20px rgba(29, 111, 111, 0.4);
            --glass-bg: rgba(15, 23, 42, 0.65);
            --glass-border: rgba(255, 255, 255, 0.08);
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-glow);
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 10px;
        }
        .tracker-container {
            max-width: 480px;
            width: 100%;
        }
        .glass-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            padding: 30px 20px;
            position: relative;
            overflow: hidden;
        }
        .glass-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(29,111,111,0.05) 0%, transparent 70%);
            pointer-events: none;
        }
        .brand-logo {
            height: 50px;
            width: auto;
            border-radius: 10px;
            box-shadow: var(--brand-glow);
        }
        .tag-title {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #fff;
            text-shadow: var(--brand-glow);
        }
        .badge-custom {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 6px 16px;
            border-radius: 20px;
        }
        .badge-stored {
            background: rgba(245, 158, 11, 0.15) !important;
            color: #fbbf24 !important;
            border: 1px solid rgba(245, 158, 11, 0.3) !important;
        }
        .badge-collected {
            background: rgba(16, 185, 129, 0.15) !important;
            color: #34d399 !important;
            border: 1px solid rgba(16, 185, 129, 0.3) !important;
        }
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #f1f5f9;
        }
        .btn-lang {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.02);
            border-radius: 30px;
            padding: 4px 14px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-lang:hover {
            color: #fff;
            background: var(--brand);
            box-shadow: var(--brand-glow);
        }
        /* Visual Tracker Timeline Styles */
        .timeline {
            position: relative;
            padding-left: 30px;
            list-style: none;
        }
        .timeline::before {
            content: '';
            position: absolute;
            top: 5px;
            left: 10px;
            bottom: 5px;
            width: 2px;
            background: rgba(255, 255, 255, 0.1);
        }
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        .timeline-marker {
            position: absolute;
            left: -26px;
            top: 4px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #475569;
            border: 2px solid #090d16;
            transition: all 0.3s ease;
        }
        .timeline-item.active .timeline-marker {
            background: var(--brand);
            box-shadow: 0 0 10px var(--brand);
            border-color: #fff;
        }
        .timeline-item.completed .timeline-marker {
            background: #10b981;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
            border-color: #fff;
        }
        .timeline-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
        }
        .timeline-desc {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 2px;
        }
        .timeline-time {
            font-size: 0.7rem;
            color: var(--brand);
            font-weight: 700;
            margin-top: 4px;
        }
    </style>
</head>
<body>

<div class="tracker-container">
    <div class="d-flex justify-content-between align-items-center mb-3 px-2">
        <div class="d-flex align-items-center gap-2">
            <img src="assets/logo.jpg" alt="Jowhara Hotel Logo" class="brand-logo" style="height: 36px;">
            <span class="fw-bold text-white small">Jowhara Hotel</span>
        </div>
        <a class="btn-lang" href="track.php?tag=<?php echo urlencode($tag_code); ?>&lang=<?php echo $switch_lang; ?>">
            <i class="bi bi-translate me-1"></i><?php echo $switch_label; ?>
        </a>
    </div>

    <div class="glass-card">
        <?php if (!$row): ?>
            <!-- Search Form / No Tag Found view -->
            <div class="text-center py-4">
                <i class="bi bi-search fs-1 text-secondary mb-3"></i>
                <h3 class="h5 fw-bold mb-2"><?php echo __("Track Your Luggage"); ?></h3>
                <p class="text-secondary small mb-4"><?php echo __("Scan the QR code on your tag or enter the tag code below."); ?></p>
                
                <form method="GET" action="track.php" class="px-2">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control text-center bg-dark text-white border-secondary" name="tag" placeholder="e.g. LS000213" value="<?php echo h($tag_code); ?>" required style="border-radius: 10px 0 0 10px;">
                        <button class="btn btn-primary" type="submit" style="border-radius: 0 10px 10px 0; background: var(--brand); border-color: var(--brand);"><i class="bi bi-arrow-right"></i></button>
                    </div>
                </form>
                <?php if ($tag_code !== ""): ?>
                    <div class="alert alert-danger py-2 small mt-3 mx-2"><?php echo __("No luggage records found."); ?></div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Active Tracking View -->
            <?php
            $is_collected = $row["status"] === "Collected";
            $is_high_value = (int)$row["is_high_value"] === 1;
            ?>
            <div class="text-center border-bottom border-secondary border-opacity-25 pb-4 mb-4">
                <div class="tag-title"><?php echo h($row["tag_code"]); ?></div>
                <div class="mt-2">
                    <span class="badge-custom <?php echo $is_collected ? 'badge-collected' : 'badge-stored'; ?>">
                        <i class="bi <?php echo $is_collected ? 'bi-check-circle-fill' : 'bi-box-seam-fill'; ?> me-1"></i>
                        <?php echo __($row["status"]); ?>
                    </span>
                </div>
            </div>

            <!-- Bag Specifications & Details -->
            <div class="row g-3 mb-4 border-bottom border-secondary border-opacity-25 pb-4">
                <div class="col-6">
                    <div class="info-label"><?php echo __("Guests"); ?></div>
                    <div class="info-value text-truncate"><?php echo h($row["guest_name"]); ?></div>
                </div>
                <div class="col-6">
                    <div class="info-label"><?php echo __("Room"); ?></div>
                    <div class="info-value"><?php echo h($row["room_no"] ?: "-"); ?></div>
                </div>
                <div class="col-6">
                    <div class="info-label"><?php echo __("Luggage Type"); ?></div>
                    <div class="info-value text-truncate"><?php echo h($row["luggage_type"]); ?> (Qty <?php echo (int)$row["quantity"]; ?>)</div>
                </div>
                <div class="col-6">
                    <div class="info-label"><?php echo __("Weight Class"); ?></div>
                    <div class="info-value"><?php echo h(__($row["weight_class"])); ?></div>
                </div>
                <?php if ($row["security_seal_code"]): ?>
                    <div class="col-12">
                        <div class="info-label"><?php echo __("Security Seal Tag Code"); ?></div>
                        <div class="info-value text-warning"><i class="bi bi-shield-lock me-1"></i><?php echo h($row["security_seal_code"]); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($is_high_value): ?>
                    <div class="col-12">
                        <div class="alert alert-danger py-2 mb-0 d-flex align-items-center gap-2" style="background: rgba(220, 38, 38, 0.1); border-color: rgba(220, 38, 38, 0.2);">
                            <i class="bi bi-shield-fill-exclamation text-danger fs-5"></i>
                            <span class="text-danger small fw-bold text-uppercase">
                                <?php echo __("High Value Declared"); ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Visual tracking timeline -->
            <h4 class="h6 fw-bold mb-3 text-secondary text-uppercase tracking-wider"><?php echo __("Luggage Journey"); ?></h4>
            <ul class="timeline">
                
                <!-- Timeline Step 4: Collection (Checked Out) -->
                <?php if ($is_collected): ?>
                    <li class="timeline-item completed">
                        <div class="timeline-marker"></div>
                        <div class="timeline-title"><?php echo __("Baggage Collected"); ?></div>
                        <div class="timeline-desc"><?php echo __("Handed over to pickup person successfully."); ?></div>
                        <div class="timeline-time"><?php echo h($row["checkout_date"]); ?></div>
                    </li>
                <?php endif; ?>

                <!-- Timeline Step 3: Location Updates (Internal Movements) -->
                <?php
                // Reverse iterate to show the most recent transfers first, or normal order
                foreach (array_reverse($transfers) as $transfer) {
                    ?>
                    <li class="timeline-item completed">
                        <div class="timeline-marker"></div>
                        <div class="timeline-title"><?php echo __("Repositioned"); ?></div>
                        <div class="timeline-desc"><?php echo __("Moved for storage room optimization."); ?></div>
                        <div class="timeline-time"><?php echo h($transfer["moved_at"]); ?></div>
                    </li>
                    <?php
                }
                ?>

                <!-- Timeline Step 2: Storage Map Room (Active Storage) -->
                <li class="timeline-item <?php echo !$is_collected ? 'active' : 'completed'; ?>">
                    <div class="timeline-marker"></div>
                    <div class="timeline-title"><?php echo __("Stored in Vault"); ?></div>
                    <div class="timeline-desc"><?php echo __("Secured in room zone"); ?> <?php echo h(__($row["storage_zone"])); ?> <?php echo h($row["storage_location"]); ?>.</div>
                    <div class="timeline-time"><?php echo h($row["checkin_date"]); ?></div>
                </li>

                <!-- Timeline Step 1: Registered -->
                <li class="timeline-item completed">
                    <div class="timeline-marker"></div>
                    <div class="timeline-title"><?php echo __("Checked In"); ?></div>
                    <div class="timeline-desc"><?php echo __("Tag registered at front storage desk."); ?></div>
                    <div class="timeline-time"><?php echo h($row["checkin_date"]); ?></div>
                </li>
            </ul>

            <!-- Terms & Help footer -->
            <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 text-center text-secondary small">
                <i class="bi bi-telephone-fill me-1"></i><?php echo __("Need assistance? Call reception desk."); ?>
                <div class="fw-bold mt-1 text-white-50">Jowhara International Hotel</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
require_once __DIR__ . "/layout.php";
require_login();

$tag = trim($_GET["tag"] ?? "");
$result = null;

if ($tag !== "") {
    $result = db_fetch_one(
        "SELECT l.*, g.guest_name, g.phone, g.room_no
         FROM luggage l JOIN guests g ON g.guest_id = l.guest_id
         WHERE (l.tag_code = ? OR l.luggage_id = ?) AND l.deleted_at IS NULL
         LIMIT 1",
         "si",
         [$tag, (int)preg_replace("/\D+/", "", $tag)]
    );

    if ($result && ($_GET["go"] ?? "") === "1") {
        redirect(($result["status"] === "Stored" ? "checkout.php?id=" : "receipt.php?id=") . (int)$result["luggage_id"]);
    }
}

render_header("Scanner", "scanner");
?>
<!-- Load html5-qrcode library -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<div class="mb-4">
    <h1 class="h3 fw-bold mb-1">Barcode & QR Code Scanner</h1>
    <p class="text-secondary mb-0">Scan using your camera, USB scanner, or search by tag code.</p>
</div>

<div class="row g-3">
    <!-- Camera Scanner Block -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h2 class="section-title d-flex align-items-center gap-2">
                    <i class="bi bi-camera text-primary"></i> Camera Scanner
                </h2>
            </div>
            <div class="card-body text-center">
                <div id="reader" style="width: 100%; display: none;" class="mb-3 rounded border"></div>
                <div class="d-grid gap-2 col-md-8 mx-auto">
                    <button class="btn btn-success d-flex align-items-center justify-content-center gap-2 py-2" id="startScanBtn" onclick="startScanner()">
                        <i class="bi bi-camera-fill fs-5"></i> Start Camera Scanner
                    </button>
                    <button class="btn btn-danger d-flex align-items-center justify-content-center gap-2 py-2 d-none" id="stopScanBtn" onclick="stopScanner()">
                        <i class="bi bi-camera-video-off-fill fs-5"></i> Stop Camera Scanner
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Code Entry / Search Block -->
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h2 class="section-title d-flex align-items-center gap-2">
                    <i class="bi bi-search text-primary"></i> Search Tag
                </h2>
            </div>
            <div class="card-body">
                <form class="row g-2 mb-3" method="GET" id="scanForm">
                    <input type="hidden" name="go" value="1">
                    <div class="col-sm-9">
                        <input class="form-control scanner-input" type="search" name="tag" id="tagInput" value="<?php echo h($tag); ?>" placeholder="Scan tag code, e.g. LS000001" autocomplete="off" autofocus>
                    </div>
                    <div class="col-sm-3 d-grid">
                        <button class="btn btn-primary d-flex align-items-center justify-content-center gap-1" type="submit">
                            <i class="bi bi-search"></i> Open
                        </button>
                    </div>
                </form>

                <?php if ($tag !== "" && !$result): ?>
                    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>No active luggage found for <strong><?php echo h($tag); ?></strong>.</div>
                    </div>
                <?php endif; ?>

                <?php if ($result): ?>
                    <div class="card border border-light-subtle shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-primary"><i class="bi bi-info-circle me-1"></i> Scan Result</span>
                            <span class="badge text-bg-<?php echo h(status_badge($result["status"])); ?>"><?php echo h($result["status"]); ?></span>
                        </div>
                        <div class="card-body">
                            <div class="h4 fw-bold text-dark mb-3"><?php echo h($result["tag_code"]); ?></div>
                            <dl class="row mb-3">
                                <dt class="col-sm-4 text-secondary">Guest</dt>
                                <dd class="col-sm-8 fw-semibold"><?php echo h($result["guest_name"]); ?> (Room <?php echo h($result["room_no"]); ?>)</dd>
                                <dt class="col-sm-4 text-secondary">Luggage</dt>
                                <dd class="col-sm-8"><?php echo h($result["luggage_type"]); ?> x<?php echo (int)$result["quantity"]; ?></dd>
                                <dt class="col-sm-4 text-secondary">Storage Location</dt>
                                <dd class="col-sm-8"><span class="badge bg-light text-dark border"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?php echo h(trim(($result["storage_zone"] ?? "") . " " . ($result["storage_location"] ?? ""))); ?></span></dd>
                            </dl>
                            <div class="d-grid gap-2">
                                <?php if ($result["status"] === "Stored" && user_can("checkout")): ?>
                                    <a class="btn btn-success d-flex align-items-center justify-content-center gap-1" href="checkout.php?id=<?php echo (int)$result["luggage_id"]; ?>">
                                        <i class="bi bi-check-circle"></i> Checkout Luggage
                                    </a>
                                <?php endif; ?>
                                <a class="btn btn-outline-primary d-flex align-items-center justify-content-center gap-1" href="receipt.php?id=<?php echo (int)$result["luggage_id"]; ?>">
                                    <i class="bi bi-file-earmark-text"></i> View Receipt
                                </a>
                                <a class="btn btn-outline-secondary d-flex align-items-center justify-content-center gap-1" href="tag.php?id=<?php echo (int)$result["luggage_id"]; ?>">
                                    <i class="bi bi-printer"></i> Print Tag
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const input = document.getElementById("tagInput");
input?.focus();
input?.addEventListener("keydown", function (event) {
    if (event.key === "Enter") {
        document.getElementById("scanForm").submit();
    }
});

let html5QrcodeScanner = null;
let qrStarted = false;

function onScanSuccess(decodedText, decodedResult) {
    stopScanner().then(() => {
        const input = document.getElementById("tagInput");
        if (input) {
            input.value = decodedText;
            document.getElementById("scanForm").submit();
        }
    });
}

function onScanFailure(error) {
    // Failures happen during framing, we can safely ignore them.
}

async function startScanner() {
    document.getElementById("reader").style.display = "block";
    document.getElementById("startScanBtn").classList.add("d-none");
    document.getElementById("stopScanBtn").classList.remove("d-none");

    if (!html5QrcodeScanner) {
        html5QrcodeScanner = new Html5Qrcode("reader");
    }

    try {
        const devices = await Html5Qrcode.getCameras();
        if (devices && devices.length) {
            // Find back camera if available, otherwise use default
            let finalCameraId = devices[0].id;
            for (const device of devices) {
                if (device.label.toLowerCase().includes("back") || device.label.toLowerCase().includes("rear") || device.label.toLowerCase().includes("environment")) {
                    finalCameraId = device.id;
                    break;
                }
            }
            await html5QrcodeScanner.start(
                finalCameraId,
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                },
                onScanSuccess,
                onScanFailure
            );
            qrStarted = true;
        } else {
            alert("No cameras found on your device.");
            stopScanner();
        }
    } catch (err) {
        console.error("Camera access error:", err);
        alert("Camera access denied or failed to initialize.");
        stopScanner();
    }
}

async function stopScanner() {
    if (html5QrcodeScanner && qrStarted) {
        try {
            await html5QrcodeScanner.stop();
        } catch (e) {
            console.error("Error stopping scanner:", e);
        }
        qrStarted = false;
    }
    document.getElementById("reader").style.display = "none";
    document.getElementById("startScanBtn").classList.remove("d-none");
    document.getElementById("stopScanBtn").classList.add("d-none");
}
</script>
<?php render_footer(); ?>

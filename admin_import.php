<?php
require_once __DIR__ . "/layout.php";
require_admin();

/* ── Sample CSV downloads ── */
if (isset($_GET["sample"])) {
    $type = $_GET["sample"];
    header("Content-Type: text/csv");
    if ($type === "guests") {
        header("Content-Disposition: attachment; filename=\"sample_guests.csv\"");
        echo "guest_name,phone,room_no,email\n";
        echo "John Doe,+252611234567,101,johndoe@email.com\n";
        echo "Jane Smith,+252612345678,205,jane@email.com\n";
    } elseif ($type === "luggage") {
        header("Content-Disposition: attachment; filename=\"sample_luggage.csv\"");
        echo "guest_name,luggage_type,quantity,color,storage_zone,storage_location,fee_amount,payment_status,checkin_date\n";
        echo "John Doe,Suitcase,2,Black,A,A-1,10.00,Paid,2026-01-15\n";
        echo "Jane Smith,Backpack,1,Red,B,B-2,5.00,Unpaid,2026-01-16\n";
    }
    exit;
}

/* ── POST: import ── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $action = $_POST["action"] ?? "";

    if ($action === "import_guests") {
        if (empty($_FILES["csv_file"]["name"])) {
            set_flash("danger", "Please select a CSV file.");
            redirect("admin_import.php");
        }
        $tmp  = $_FILES["csv_file"]["tmp_name"];
        $fname= $_FILES["csv_file"]["name"];
        $handle = fopen($tmp, "r");
        $header = fgetcsv($handle); // skip header
        $success = 0; $errors = 0; $notes = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 1) continue;
            $name  = trim($row[0] ?? "");
            $phone = trim($row[1] ?? "");
            $room  = trim($row[2] ?? "");
            $email = trim($row[3] ?? "");
            if ($name === "") { $errors++; continue; }
            try {
                db_execute("INSERT INTO guests (guest_name, phone, room_no, email) VALUES (?,?,?,?)", "ssss", [$name,$phone,$room,$email]);
                $success++;
            } catch (Throwable $e) { $errors++; $notes[] = "Row error: " . $e->getMessage(); }
        }
        fclose($handle);
        $total = $success + $errors;
        db_execute("INSERT INTO import_history (import_type,filename,row_count,success_count,error_count,status,imported_by,notes) VALUES ('guests',?,?,?,?,?,?,?)",
            "siiiiss", [$fname, $total, $success, $errors, $errors > 0 ? "Partial" : "Completed", (int)current_user()["user_id"], implode("; ", array_slice($notes,0,5))]);
        log_action("Imported guests CSV", "import", null, "File: $fname — $success success, $errors errors");
        set_flash($errors > 0 ? "warning" : "success", "Import complete: $success rows added, $errors failed.");
        redirect("admin_import.php");
    }

    if ($action === "import_luggage") {
        if (empty($_FILES["csv_file"]["name"])) {
            set_flash("danger", "Please select a CSV file.");
            redirect("admin_import.php");
        }
        $tmp   = $_FILES["csv_file"]["tmp_name"];
        $fname = $_FILES["csv_file"]["name"];
        $handle = fopen($tmp, "r");
        fgetcsv($handle); // skip header
        $success = 0; $errors = 0; $notes = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;
            $gname       = trim($row[0] ?? "");
            $ltype       = trim($row[1] ?? "Suitcase");
            $qty         = max(1,(int)($row[2] ?? 1));
            $color       = trim($row[3] ?? "");
            $zone        = trim($row[4] ?? "");
            $location    = trim($row[5] ?? "");
            $fee         = (float)($row[6] ?? 0);
            $pay_status  = in_array(trim($row[7] ?? ""), ["Paid","Unpaid","Waived"]) ? trim($row[7]) : "Unpaid";
            $checkin     = trim($row[8] ?? date("Y-m-d"));
            // Find or create guest
            $guest = db_fetch_one("SELECT guest_id FROM guests WHERE guest_name=? AND deleted_at IS NULL LIMIT 1", "s", [$gname]);
            if (!$guest && $gname !== "") {
                db_execute("INSERT INTO guests (guest_name) VALUES (?)", "s", [$gname]);
                $guest_id = db_insert_id();
            } elseif ($guest) {
                $guest_id = (int)$guest["guest_id"];
            } else { $errors++; continue; }
            try {
                global $conn;
                $conn->begin_transaction();
                db_execute("INSERT INTO luggage (guest_id,tag_code,luggage_type,quantity,color,storage_zone,storage_location,fee_amount,payment_status,checkin_date,created_by)
                    VALUES (?,NULL,?,?,?,?,?,?,?,?,?)",
                    "isissdsi", [$guest_id,$ltype,$qty,$color,$zone,$location,$fee,$pay_status,$checkin,(int)current_user()["user_id"]]);
                $new_id = db_insert_id();
                $tag = generate_tag_code($new_id);
                db_execute("UPDATE luggage SET tag_code = ? WHERE luggage_id = ?", "si", [$tag, $new_id]);
                $conn->commit();
                $success++;
            } catch (Throwable $e) {
                if (isset($conn) && $conn->in_transaction) {
                    $conn->rollback();
                }
                $errors++;
                $notes[] = $e->getMessage();
            }
        }
        fclose($handle);
        $total = $success + $errors;
        db_execute("INSERT INTO import_history (import_type,filename,row_count,success_count,error_count,status,imported_by,notes) VALUES ('luggage',?,?,?,?,?,?,?)",
            "siiiiss", [$fname,$total,$success,$errors,$errors>0?"Partial":"Completed",(int)current_user()["user_id"],implode("; ",array_slice($notes,0,5))]);
        log_action("Imported luggage CSV", "import", null, "File: $fname — $success success, $errors errors");
        set_flash($errors > 0 ? "warning" : "success", "Import complete: $success rows added, $errors failed.");
        redirect("admin_import.php");
    }
}

$history = db_fetch_all(
    "SELECT ih.*, u.full_name, u.username FROM import_history ih
     LEFT JOIN users u ON u.user_id = ih.imported_by
     ORDER BY ih.import_id DESC LIMIT 50"
);

render_header("Import & Export", "admin_import");
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
            <span class="admin-page-icon"><i class="bi bi-cloud-arrow-up-down"></i></span>
            Import &amp; Export
        </h1>
        <p class="text-secondary mb-0">Bulk import guests/luggage from CSV and export data to Excel or PDF.</p>
    </div>
</div>

<div class="row g-4 mb-4">

    <!-- Import Guests -->
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header border-bottom px-4 py-3" style="background:linear-gradient(135deg,#3b82f620,#3b82f605);">
                <h2 class="section-title mb-0 text-primary"><i class="bi bi-person-lines-fill me-2"></i>Import Guests</h2>
            </div>
            <div class="card-body px-4 py-4">
                <p class="text-muted small mb-3">Upload a CSV with columns: <code>guest_name, phone, room_no, email</code></p>
                <a href="admin_import.php?sample=guests" class="btn btn-outline-primary btn-sm mb-3 d-flex align-items-center gap-1" style="width:fit-content;">
                    <i class="bi bi-download"></i> Download Sample CSV
                </a>
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="import_guests">
                    <div class="mb-3">
                        <input class="form-control" type="file" name="csv_file" accept=".csv,text/csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Import Guests</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Luggage -->
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header border-bottom px-4 py-3" style="background:linear-gradient(135deg,#10b98120,#10b98105);">
                <h2 class="section-title mb-0 text-success"><i class="bi bi-briefcase-fill me-2"></i>Import Luggage</h2>
            </div>
            <div class="card-body px-4 py-4">
                <p class="text-muted small mb-3">Upload a CSV with columns: <code>guest_name, luggage_type, quantity, color, zone, location, fee, payment_status, checkin_date</code></p>
                <a href="admin_import.php?sample=luggage" class="btn btn-outline-success btn-sm mb-3 d-flex align-items-center gap-1" style="width:fit-content;">
                    <i class="bi bi-download"></i> Download Sample CSV
                </a>
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="import_luggage">
                    <div class="mb-3">
                        <input class="form-control" type="file" name="csv_file" accept=".csv,text/csv" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100"><i class="bi bi-upload me-1"></i>Import Luggage</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Export Data -->
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header border-bottom px-4 py-3" style="background:linear-gradient(135deg,#8b5cf620,#8b5cf605);">
                <h2 class="section-title mb-0" style="color:#8b5cf6;"><i class="bi bi-cloud-download me-2"></i>Export Data</h2>
            </div>
            <div class="card-body px-4 py-4 d-flex flex-column gap-3">
                <a href="export_excel.php?type=guests" class="btn btn-outline-success d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-excel fs-5"></i>
                    <div class="text-start">
                        <div class="fw-semibold">Export Guests</div>
                        <div class="small text-muted">Download all guests as Excel</div>
                    </div>
                </a>
                <a href="export_excel.php" class="btn btn-outline-success d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-excel fs-5"></i>
                    <div class="text-start">
                        <div class="fw-semibold">Export Luggage</div>
                        <div class="small text-muted">Download all luggage records</div>
                    </div>
                </a>
                <a href="export_pdf.php" class="btn btn-outline-danger d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-pdf fs-5"></i>
                    <div class="text-start">
                        <div class="fw-semibold">Export to PDF</div>
                        <div class="small text-muted">Print-ready PDF report</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Import History -->
<div class="card">
    <div class="card-header bg-transparent border-bottom px-4 py-3">
        <h2 class="section-title mb-0"><i class="bi bi-clock-history me-2"></i>Import History</h2>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr>
                <th>#</th><th>Type</th><th>Filename</th><th>Rows</th><th>Success</th><th>Errors</th><th>Status</th><th>Imported By</th><th>Date</th>
            </tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
            <tr>
                <td class="text-muted small"><?php echo (int)$h["import_id"]; ?></td>
                <td><span class="badge text-bg-<?php echo $h["import_type"]==="guests"?"primary":"success"; ?>"><?php echo h(ucfirst($h["import_type"])); ?></span></td>
                <td class="small font-monospace text-muted"><?php echo h($h["filename"] ?? "—"); ?></td>
                <td><?php echo (int)$h["row_count"]; ?></td>
                <td class="text-success fw-bold"><?php echo (int)$h["success_count"]; ?></td>
                <td class="<?php echo (int)$h["error_count"] > 0 ? "text-danger fw-bold" : "text-muted"; ?>"><?php echo (int)$h["error_count"]; ?></td>
                <td><span class="badge text-bg-<?php echo $h["status"]==="Completed"?"success":($h["status"]==="Partial"?"warning":"danger"); ?>"><?php echo h($h["status"]); ?></span></td>
                <td class="small"><?php echo h($h["full_name"] ?: ($h["username"] ?? "—")); ?></td>
                <td class="small text-muted"><?php echo date("M j, Y H:i", strtotime($h["created_at"])); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$history): ?><tr><td colspan="9"><div class="empty-state">No import history yet.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.admin-page-icon{width:40px;height:40px;border-radius:12px;background:var(--brand-light);color:var(--brand);display:inline-flex;align-items:center;justify-content:center;font-size:1.2rem;}
</style>
<?php render_footer(); ?>

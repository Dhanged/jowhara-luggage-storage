<?php
require_once __DIR__ . "/functions.php";
require_login();

$type = $_GET["type"] ?? "luggage";

if ($type === "guests") {
    $rows = db_fetch_all("SELECT * FROM guests WHERE deleted_at IS NULL ORDER BY guest_id DESC");
    $filename = "guests_export_" . date("Ymd_His") . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr><th>Guest ID</th><th>Name</th><th>Phone</th><th>Room No</th><th>Email</th><th>ID Type</th><th>ID Number</th><th>Created At</th></tr>";
    foreach ($rows as $row) {
        echo "<tr>";
        echo "<td>" . (int)$row["guest_id"] . "</td>";
        echo "<td>" . h($row["guest_name"]) . "</td>";
        echo "<td>" . h($row["phone"] ?? "") . "</td>";
        echo "<td>" . h($row["room_no"] ?? "") . "</td>";
        echo "<td>" . h($row["email"] ?? "") . "</td>";
        echo "<td>" . h($row["id_type"] ?? "") . "</td>";
        echo "<td>" . h($row["id_number"] ?? "") . "</td>";
        echo "<td>" . h($row["created_at"]) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
} elseif ($type === "audit") {
    $rows = db_fetch_all(
        "SELECT a.*, u.full_name, u.username FROM audit_logs a
         LEFT JOIN users u ON u.user_id = a.user_id
         ORDER BY a.log_id DESC LIMIT 1000"
    );
    $filename = "audit_log_export_" . date("Ymd_His") . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr><th>Log ID</th><th>Action</th><th>Entity Type</th><th>Entity ID</th><th>Details</th><th>IP Address</th><th>User</th><th>Date</th></tr>";
    foreach ($rows as $row) {
        $actor = $row["full_name"] ?: ($row["username"] ?? "System");
        echo "<tr>";
        echo "<td>" . (int)$row["log_id"] . "</td>";
        echo "<td>" . h($row["action"]) . "</td>";
        echo "<td>" . h($row["entity_type"] ?? "") . "</td>";
        echo "<td>" . h($row["entity_id"] ?? "") . "</td>";
        echo "<td>" . h($row["details"] ?? "") . "</td>";
        echo "<td>" . h($row["ip_address"] ?? "") . "</td>";
        echo "<td>" . h($actor) . "</td>";
        echo "<td>" . h($row["created_at"]) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
} else {
    $filters = report_filters();
    $rows = luggage_report_rows($filters);
    $filename = "luggage_report_" . date("Ymd_His") . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr><th>Tag</th><th>Guest</th><th>Phone</th><th>Room</th><th>Email</th><th>Booking</th><th>Type</th><th>Quantity</th><th>Color</th><th>Zone</th><th>Location</th><th>Conditions</th><th>Expected Pickup</th><th>Fee</th><th>Payment</th><th>Pickup Person</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Created By</th><th>Checked Out By</th></tr>";
    foreach ($rows as $row) {
        $conditions = implode(", ", parse_condition_flags($row["condition_flags"]));
        echo "<tr>";
        echo "<td>" . h($row["tag_code"]) . "</td>";
        echo "<td>" . h($row["guest_name"]) . "</td>";
        echo "<td>" . h($row["phone"]) . "</td>";
        echo "<td>" . h($row["room_no"]) . "</td>";
        echo "<td>" . h($row["email"]) . "</td>";
        echo "<td>" . h($row["reservation_no"]) . "</td>";
        echo "<td>" . h($row["luggage_type"]) . "</td>";
        echo "<td>" . (int)$row["quantity"] . "</td>";
        echo "<td>" . h($row["color"]) . "</td>";
        echo "<td>" . h($row["storage_zone"]) . "</td>";
        echo "<td>" . h($row["storage_location"]) . "</td>";
        echo "<td>" . h($conditions) . "</td>";
        echo "<td>" . h($row["expected_pickup_date"]) . "</td>";
        echo "<td>" . h(money($row["fee_amount"])) . "</td>";
        echo "<td>" . h($row["payment_status"] . " " . $row["payment_method"] . " " . $row["payment_reference"]) . "</td>";
        echo "<td>" . h($row["pickup_person_name"] . " " . $row["pickup_person_id"]) . "</td>";
        echo "<td>" . h($row["status"]) . "</td>";
        echo "<td>" . h($row["checkin_date"]) . "</td>";
        echo "<td>" . h($row["checkout_date"]) . "</td>";
        echo "<td>" . h($row["created_user"]) . "</td>";
        echo "<td>" . h($row["checkout_user"]) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

<?php
require_once __DIR__ . "/functions.php";

// Ensure the user is logged in
if (!current_user()) {
    header("Content-Type: application/json");
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$shelf = trim($_GET["shelf"] ?? "");

if ($shelf === "") {
    header("Content-Type: application/json");
    http_response_code(400);
    echo json_encode(["error" => "Shelf parameter is required"]);
    exit;
}

// Fetch active stored luggage in this shelf location
$rows = db_fetch_all(
    "SELECT l.luggage_id, l.tag_code, l.luggage_type, l.quantity, l.expected_pickup_date, l.checkin_date, g.guest_name, g.room_no
     FROM luggage l
     JOIN guests g ON g.guest_id = l.guest_id
     WHERE l.storage_location = ? AND l.status = 'Stored' AND l.deleted_at IS NULL
     ORDER BY l.luggage_id DESC",
    "s",
    [$shelf]
);

header("Content-Type: application/json");
echo json_encode($rows);
exit;

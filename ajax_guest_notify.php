<?php
require_once __DIR__ . "/functions.php";

header("Content-Type: application/json");

$tag = trim($_POST["tag"] ?? "");
if ($tag === "") {
    echo json_encode(["status" => "error", "message" => "Tag code is required."]);
    exit;
}

$item = db_fetch_one(
    "SELECT l.*, g.guest_name, g.phone, g.room_no
     FROM luggage l
     JOIN guests g ON g.guest_id = l.guest_id
     WHERE l.tag_code = ? AND l.deleted_at IS NULL",
    "s",
    [$tag]
);

if (!$item) {
    echo json_encode(["status" => "error", "message" => "Luggage record not found."]);
    exit;
}

if ($item['status'] !== 'Stored') {
    echo json_encode(["status" => "error", "message" => "Luggage is not currently stored."]);
    exit;
}

// Push alert to all staff
push_notification_all(
    "Guest Pre-Checkout Alert ⚡",
    "Guest {$item['guest_name']} (Room {$item['room_no']}) is arriving in 10 mins to pick up luggage {$tag}!",
    "luggage.php?search=" . urlencode($tag),
    "warning"
);

log_action("Guest Pre-Checkout Alert", "luggage", $item['luggage_id'], "Guest {$item['guest_name']} requested pickup preparation.");

echo json_encode([
    "status" => "success",
    "message" => "Front desk notified! Our staff is preparing your luggage."
]);

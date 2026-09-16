<?php
require_once __DIR__ . "/functions.php";

header('Content-Type: application/json');

if (!current_user()) {
    echo json_encode(['count' => 0, 'items' => []]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    $count = (int)db_scalar(
        "SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND read_at IS NULL AND is_dismissed = 0",
        "i",
        [$user_id]
    );

    $items = db_fetch_all(
        "SELECT notif_id, title, body, link, type, created_at 
         FROM user_notifications 
         WHERE user_id = ? AND read_at IS NULL AND is_dismissed = 0
         ORDER BY created_at DESC LIMIT 5",
        "i",
        [$user_id]
    );

    echo json_encode(['count' => $count, 'items' => $items]);
} catch (Throwable $e) {
    echo json_encode(['count' => 0, 'items' => []]);
}

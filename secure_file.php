<?php
require_once __DIR__ . "/functions.php";

require_login();

$file = trim($_GET['file'] ?? '');

if ($file === '' || strpos($file, '..') !== false || strpos($file, "\0") !== false) {
    http_response_code(400);
    die("Invalid file request.");
}

$file = ltrim(str_replace('\\', '/', $file), '/');

$allowed_dirs = [
    'uploads/ids/',
    'uploads/signatures/',
    'uploads/checkout/',
    'uploads/luggage/',
    'uploads/claims/',
    'uploads/lost_found/',
    'storage/private/'
];

$is_allowed = false;
foreach ($allowed_dirs as $dir) {
    if (strpos($file, $dir) === 0) {
        $is_allowed = true;
        break;
    }
}

if (!$is_allowed) {
    http_response_code(403);
    die("Access denied to requested file directory.");
}

$full_path = __DIR__ . '/' . $file;

if (!is_file($full_path)) {
    http_response_code(404);
    die("File not found.");
}

$allowed_mimes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf'
];

$ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
$mime = $allowed_mimes[$ext] ?? mime_content_type($full_path);

if (!isset($allowed_mimes[$ext])) {
    http_response_code(415);
    die("Unsupported file format.");
}

header("Content-Type: " . $mime);
header("Content-Length: " . filesize($full_path));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, max-age=86400");
header("Content-Disposition: inline; filename=\"" . basename($full_path) . "\"");

readfile($full_path);
exit;

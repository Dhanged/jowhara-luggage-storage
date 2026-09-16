<?php
require_once __DIR__ . "/functions.php";

$lang = $_GET["lang"] ?? "en";
if (in_array($lang, ["en", "so", "ar"], true)) {
    $_SESSION["lang"] = $lang;
}

$user = current_user();
$default_page = $user ? default_page_for_role($user["role"]) : "index.php";

$referrer = $_SERVER["HTTP_REFERER"] ?? $default_page;
if (strpos($referrer, "change_lang.php") !== false) {
    $referrer = $default_page;
}

redirect($referrer);

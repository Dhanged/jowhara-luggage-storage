<?php
require_once __DIR__ . "/functions.php";

if (current_user()) {
    log_action("Logged out", "user", (int)current_user()["user_id"], "User signed out");
}

session_destroy();
redirect("index.php");

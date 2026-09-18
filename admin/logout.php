<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset(
    $_SESSION["admin_id"],
    $_SESSION["admin_name"],
    $_SESSION["admin_email"],
    $_SESSION["auth"]
);

if (isset($_SESSION["user_id"])) {
    unset($_SESSION["user_id"], $_SESSION["user_name"], $_SESSION["user_email"]);
}

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

session_destroy();

header("Location: login.php");
exit;
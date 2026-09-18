<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdminSession =
    isset($_SESSION["admin_id"]) ||
    (($_SESSION["auth"]["role"] ?? "") === "admin");

if (!$isAdminSession) {
    header("Location: ../login.php");
    exit;
}

<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAdminSession = isset($_SESSION["admin_id"]) || (($_SESSION["auth"]["role"] ?? "") === "admin");
$isCustomerSession = isset($_SESSION["user_id"]) || (($_SESSION["auth"]["role"] ?? "") === "customer");

if (!$isAdminSession && !$isCustomerSession) {
    header("Location: login.php");
    exit;
}

if ($isAdminSession) {
    header("Location: admin/index.php");
    exit;
}

header("Location: customer/index.php");
exit;

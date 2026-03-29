<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['staff_id'])) {
    header("Location: /yotribe-system/app/auth/login.php");
    exit;
}

function require_role(array $allowed_roles)
{
    // SUPER ADMIN OVERRIDE
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
        return;
    }

    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        echo "<h3>Access Denied: You do not have permission to view this page.</h3>";
        exit;
    }
}

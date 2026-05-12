<?php

/**
 * =========================================================
 * AUTH GUARD
 * =========================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * LOGIN CHECK
 */
if (!isset($_SESSION['staff_id'])) {

    header("Location: /yotribe-system/app/auth/login.php");
    exit;
}

/**
 * REQUIRE ROLE
 * Legacy support
 */
function require_role(array $allowed_roles): void
{
    /**
     * SUPER ADMIN OVERRIDE
     */
    if (
        isset($_SESSION['role']) &&
        $_SESSION['role'] === 'super_admin'
    ) {
        return;
    }

    if (
        !isset($_SESSION['role']) ||
        !in_array($_SESSION['role'], $allowed_roles, true)
    ) {

        http_response_code(403);

        die("
            <div style='padding:40px;font-family:Arial'>
                <h2>403 - Access Denied</h2>
                <p>You do not have permission to access this page.</p>
            </div>
        ");
    }
}

/**
 * REQUIRE PERMISSION
 * New enterprise permission system
 */
function require_permission(string $module): void
{
    /**
     * SUPER ADMIN OVERRIDE
     */
    if (
        isset($_SESSION['role']) &&
        $_SESSION['role'] === 'super_admin'
    ) {
        return;
    }

    require_once __DIR__ . '/../helpers/permission.php';

    if (!canAccess($module)) {

        http_response_code(403);

        die("
            <div style='padding:40px;font-family:Arial'>
                <h2>403 - Access Denied</h2>
                <p>You are not authorized to access this module.</p>
            </div>
        ");
    }
}
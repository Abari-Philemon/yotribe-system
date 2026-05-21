<?php

/**
 * =========================================================
 * FARM GUARD
 * =========================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * USER MUST LOGIN
 */
if (!isset($_SESSION['staff_id'])) {

    header("Location: /yotribe-system/app/auth/login.php");
    exit;
}

/**
 * CURRENT ROLE
 */
$role = $_SESSION['role'] ?? null;

/**
 * MULTI FARM USERS
 */
$multiFarmRoles = ['super_admin', 'owner'];

/**
 * =========================================================
 * AUTO FARM ASSIGNMENT
 * SINGLE FARM USERS
 * =========================================================
 */

if (
    !isset($_SESSION['active_farm_id']) &&
    !in_array($role, $multiFarmRoles, true)
) {

    /**
     * USE ASSIGNED FARM
     */
    if (!empty($_SESSION['farm_id'])) {

        $_SESSION['active_farm_id'] = (int) $_SESSION['farm_id'];

    } else {

        die("No farm assigned to this account.");
    }
}

/**
 * =========================================================
 * MULTI FARM USERS
 * MUST SELECT FARM
 * =========================================================
 */

if (
    in_array($role, $multiFarmRoles, true) &&
    !isset($_SESSION['active_farm_id'])
) {

    header("Location: /yotribe-system/app/modules/farms/select.php");
    exit;
}

/**
 * =========================================================
 * HELPERS
 * =========================================================
 */

if (!function_exists('farm_id')) {

    function farm_id(): int
    {
        return (int) ($_SESSION['active_farm_id'] ?? 0);
    }
}

if (!function_exists('farm_name')) {

    function farm_name(): string
    {
        return $_SESSION['active_farm_name'] ?? 'Farm';
    }
}
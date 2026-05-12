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
 * ROLE
 */
$role = $_SESSION['role'] ?? null;

/**
 * MULTI FARM USERS
 */
$multiFarmRoles = ['super_admin', 'owner'];

/**
 * AUTO SET FARM
 * FOR SINGLE FARM USERS
 */
if (
    !isset($_SESSION['active_farm_id']) &&
    !in_array($role, $multiFarmRoles)
) {

    /**
     * USE ASSIGNED FARM
     */
    if (!empty($_SESSION['farm_id'])) {

        $_SESSION['active_farm_id'] = (int) $_SESSION['farm_id'];

    } else {

        die("No farm assigned to account.");
    }
}

/**
 * MULTI FARM USERS
 * MUST SELECT FARM
 */
if (
    in_array($role, $multiFarmRoles) &&
    !isset($_SESSION['active_farm_id'])
) {

    header("Location: /yotribe-system/app/modules/farms/select.php");
    exit;
}

/**
 * FARM ID HELPER
 */
function farm_id(): int
{
    return (int) ($_SESSION['active_farm_id'] ?? 0);
}

/**
 * FARM NAME HELPER
 */
function farm_name(): string
{
    return $_SESSION['active_farm_name'] ?? 'Farm';
}
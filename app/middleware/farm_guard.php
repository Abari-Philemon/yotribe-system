<?php

// Ensure session exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensure active farm is selected
 */
if (!isset($_SESSION['active_farm_id'])) {
    header("Location: /yotribe-system/app/modules/farms/select.php");
    exit;
}

/**
 * Helper: get farm ID
 */
function farm_id() {
    return (int) ($_SESSION['active_farm_id'] ?? 0);
}

/**
 * Helper: get farm name
 */
function farm_name() {
    return $_SESSION['active_farm_name'] ?? 'Active Farm';
}
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


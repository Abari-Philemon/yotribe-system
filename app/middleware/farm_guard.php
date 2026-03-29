<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['active_farm_id'])) {
    header("Location: /yotribe-system/app/modules/farms/select.php");
    exit;
}

$farm_id = (int) $_SESSION['active_farm_id'];

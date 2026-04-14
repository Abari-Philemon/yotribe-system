<?php

if (!isset($_SESSION['active_farm_id'])) {
    header("Location: /yotribe-system/app/modules/farms/select.php");
    exit;
}

function farm_id() {
    return $_SESSION['active_farm_id'];
}
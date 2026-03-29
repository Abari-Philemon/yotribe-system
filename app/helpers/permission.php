<?php

function canAccess(string $module): bool
{
    if (!isset($_SESSION['role'])) {
        return false;
    }

    $permissions = require __DIR__ . '/../config/permissions.php';

    return in_array(
        $module,
        $permissions[$_SESSION['role']] ?? [],
        true
    );
}

<?php

/**
 * =========================================================
 * ROLE + MODULE ACCESS CONTROL
 * =========================================================
 */

function canAccess(string $module): bool
{
    if (!isset($_SESSION['role'])) {
        return false;
    }

    $permissions = require __DIR__ . '/../config/permissions.php';

    $role = $_SESSION['role'];

    /**
     * ROLE NOT FOUND
     */
    if (!isset($permissions[$role])) {
        return false;
    }

    /**
     * SUPER ADMIN BYPASS
     */
    if ($role === 'super_admin') {
        return true;
    }

    return in_array(
        $module,
        $permissions[$role],
        true
    );
}


/**
 * =========================================================
 * REQUIRE MODULE ACCESS
 * =========================================================
 */
function requireModuleAccess(string $module): void
{
    if (!canAccess($module)) {

        http_response_code(403);

        die("
            <div style='
                padding:40px;
                font-family:Arial;
                text-align:center;
            '>

                <h2>403 - Access Denied</h2>

                <p>
                    You do not have permission to access this module.
                </p>

                <a href='/yotribe-system/app/modules/dashboard/index.php'>
                    Back to Dashboard
                </a>

            </div>
        ");
    }
}
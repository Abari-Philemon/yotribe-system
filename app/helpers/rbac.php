<?php
 /**EQUIRE PERMISSION
 * Enterprise RBAC
 */
if (!function_exists('require_permission')) {

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
}
<?php
declare(strict_types=1);

/**
 * The existing IFMS uses direct PHP module entry points rather than a central router.
 * This file documents the route target and is included by index.php for consistency.
 */
return [
    'GET /app/modules/financess/index.php' => 'Chart of Accounts listing',
    'POST /app/modules/financess/index.php' => 'Create account / activate or deactivate account',
];

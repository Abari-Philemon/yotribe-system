<?php
require_once __DIR__ . '/../helpers/permission.php';

function authorize(string $module)
{
    if (!canAccess($module)) {
        http_response_code(403);
        require __DIR__ . '/../../public/errors/403.php';
        exit;
    }
}

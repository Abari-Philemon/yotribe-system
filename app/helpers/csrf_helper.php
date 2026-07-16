<?php

declare(strict_types=1);

if (!function_exists('validate_csrf')) {

    function validate_csrf(): void
    {
        if (
            empty($_POST['csrf_token']) ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals(
                $_SESSION['csrf_token'],
                $_POST['csrf_token']
            )
        ) {

            http_response_code(403);

            exit('Invalid CSRF token.');

        }
    }

}
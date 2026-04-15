<?php

if (!function_exists('csrf_token')) {

    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_verify')) {

    function csrf_verify($token) {
        if (
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $token)
        ) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid CSRF token'
            ]);
            exit;
        }
    }
}
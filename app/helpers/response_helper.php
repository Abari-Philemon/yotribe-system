<?php

declare(strict_types=1);

if (!function_exists('redirect')) {

    function redirect(
        string $url
    ): never {

        header("Location: {$url}");

        exit;

    }

}

if (!function_exists('back')) {

    function back(): never
    {
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

}
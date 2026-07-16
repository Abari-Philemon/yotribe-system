<?php

declare(strict_types=1);

if (!function_exists('display_date')) {

    function display_date(
        ?string $date
    ): string {

        if (!$date) {

            return '-';

        }

        return date(
            'd M Y',
            strtotime($date)
        );

    }

}

if (!function_exists('display_datetime')) {

    function display_datetime(
        ?string $date
    ): string {

        if (!$date) {

            return '-';

        }

        return date(
            'd M Y H:i',
            strtotime($date)
        );

    }

}
<?php

declare(strict_types=1);

if (!function_exists('money')) {

    function money(
        float $amount
    ): string {

        return number_format(
            $amount,
            2
        );

    }

}
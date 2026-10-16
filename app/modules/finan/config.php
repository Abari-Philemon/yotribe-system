<?php
declare(strict_types=1);

/**
 * Finance module tenant configuration.
 * The current IFMS login/farm context does not expose a company_id.
 * Set $_SESSION['company_id'] (or $_SESSION['active_company_id']) when company
 * tenancy is wired in. Until then this single-company installation uses 1.
 */
if (!defined('YOTRIBE_FINANCE_COMPANY_ID')) {
    define('YOTRIBE_FINANCE_COMPANY_ID', 1);
}

function finance_company_id(): int
{
    $value = $_SESSION['active_company_id'] ?? $_SESSION['company_id'] ?? YOTRIBE_FINANCE_COMPANY_ID;
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        throw new RuntimeException('Finance company context is invalid. Configure a valid company ID.');
    }
    return (int) $id;
}

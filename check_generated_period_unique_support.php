<?php
require_once __DIR__ . '/app/config/database.php';

$table = 'tmp_finance_period_unique_test';

try {
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS `$table`");

    $pdo->exec("
        CREATE TEMPORARY TABLE `$table` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id BIGINT UNSIGNED NOT NULL,
            financial_year_id BIGINT UNSIGNED NOT NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            current_financial_year_id BIGINT UNSIGNED
                GENERATED ALWAYS AS (
                    CASE WHEN is_current = 1 THEN financial_year_id ELSE NULL END
                ) PERSISTENT,
            UNIQUE KEY uq_test_current_year (company_id, current_financial_year_id)
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        INSERT INTO `$table` (company_id, financial_year_id, is_current)
        VALUES (1, 2026, 1), (1, 2026, 0), (1, 2027, 1), (2, 2026, 1)
    ");
    echo "PASS: Different years and companies can each have a current period." . PHP_EOL;

    try {
        $pdo->exec("
            INSERT INTO `$table` (company_id, financial_year_id, is_current)
            VALUES (1, 2026, 1)
        ");
        echo "FAIL: Duplicate current period was accepted." . PHP_EOL;
    } catch (PDOException $e) {
        echo "PASS: Duplicate current period for the same company/year was rejected." . PHP_EOL;
    }
} finally {
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS `$table`");
}

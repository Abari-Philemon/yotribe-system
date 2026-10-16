<?php
require_once __DIR__ . '/app/config/database.php';

$table = 'tmp_finance_generated_column_test';

try {
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS `$table`");

    $pdo->exec("
        CREATE TEMPORARY TABLE `$table` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            company_id BIGINT UNSIGNED NOT NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            current_company_id BIGINT UNSIGNED
                GENERATED ALWAYS AS (
                    CASE WHEN is_current = 1 THEN company_id ELSE NULL END
                ) PERSISTENT,
            UNIQUE KEY uq_test_current_company (current_company_id)
        ) ENGINE=InnoDB
    ");

    $pdo->exec("INSERT INTO `$table` (company_id, is_current) VALUES (1, 1), (2, 1), (1, 0)");
    echo "PASS: Generated column and unique index accepted." . PHP_EOL;

    try {
        $pdo->exec("INSERT INTO `$table` (company_id, is_current) VALUES (1, 1)");
        echo "FAIL: Duplicate current company was accepted." . PHP_EOL;
    } catch (PDOException $e) {
        echo "PASS: Duplicate current company rejected by unique index." . PHP_EOL;
    }
} finally {
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS `$table`");
}

<?php
require __DIR__ . '/app/config/database.php';

$sql = "
    SELECT id, account_code, account_name, account_type_id,
           account_class_id, normal_balance, allow_posting, is_active
    FROM accounts
    WHERE company_id = 1
      AND is_active = 1
      AND allow_posting = 1
    ORDER BY account_code
";

foreach ($pdo->query($sql)->fetchAll() as $row) {
    echo implode(' | ', $row) . PHP_EOL;
}

<?php
require __DIR__ . '/app/config/database.php';

$sql = "
    SELECT
        ba.id AS bank_account_id,
        ba.company_id AS bank_company_id,
        ba.bank_name,
        ba.account_name,
        ba.is_active AS bank_is_active,
        ba.ledger_account_id,
        a.company_id AS ledger_company_id,
        a.account_code AS ledger_account_code,
        a.account_name AS ledger_account_name,
        a.is_active AS ledger_is_active,
        a.allow_posting,
        a.is_bank_account,
        a.normal_balance
    FROM bank_accounts ba
    LEFT JOIN accounts a ON a.id = ba.ledger_account_id
    ORDER BY ba.company_id, ba.id
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($rows === []) {
    echo "No bank accounts currently exist." . PHP_EOL;
} else {
    echo json_encode(
        $rows,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    ) . PHP_EOL;
}

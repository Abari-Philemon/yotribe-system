<?php
require __DIR__ . '/app/config/database.php';

$sql = "SELECT id, account_code, account_name, is_active, allow_posting
        FROM accounts
        WHERE company_id = 1
          AND (is_active = 0 OR allow_posting = 0)
        ORDER BY id";

$stmt = $pdo->query($sql);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$accounts) {
    echo "No inactive or non-postable accounts found for company 1.\n";
    exit;
}

foreach ($accounts as $account) {
    printf(
        "ID: %d | Code: %s | Name: %s | Active: %s | Allow posting: %s\n",
        $account['id'],
        $account['account_code'],
        $account['account_name'],
        $account['is_active'],
        $account['allow_posting']
    );
}

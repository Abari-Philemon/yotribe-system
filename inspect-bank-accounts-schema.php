<?php
require __DIR__ . '/app/config/database.php';

$stmt = $pdo->query("SHOW CREATE TABLE bank_accounts");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    fwrite(STDERR, "ERROR: bank_accounts table was not found." . PHP_EOL);
    exit(1);
}

echo $row['Create Table'] . PHP_EOL;

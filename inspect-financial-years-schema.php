<?php
require __DIR__ . '/app/config/database.php';

$stmt = $pdo->query("SHOW CREATE TABLE financial_years");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    fwrite(STDERR, "ERROR: financial_years table was not found." . PHP_EOL);
    exit(1);
}

echo $row['Create Table'] . PHP_EOL;

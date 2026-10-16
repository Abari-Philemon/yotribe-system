<?php
require __DIR__ . '/app/config/database.php';

$stmt = $pdo->query("SHOW CREATE TABLE finance_settings");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    fwrite(STDERR, "ERROR: finance_settings table was not found." . PHP_EOL);
    exit(1);
}

echo $row['Create Table'] . PHP_EOL;

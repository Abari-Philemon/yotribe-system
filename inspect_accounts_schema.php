<?php
require __DIR__ . '/app/config/database.php';

$stmt = $pdo->query("SHOW CREATE TABLE accounts");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo $row['Create Table'] . PHP_EOL;
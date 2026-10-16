<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';

$stmt = $pdo->query("SHOW INDEX FROM accounts");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    printf(
        "Index: %s | Column: %s | Unique: %s | Sequence: %s\n",
        $row['Key_name'],
        $row['Column_name'],
        (int) $row['Non_unique'] === 0 ? 'YES' : 'NO',
        $row['Seq_in_index']
    );
}
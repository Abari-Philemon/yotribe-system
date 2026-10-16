<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';

$stmt = $pdo->query("SHOW COLUMNS FROM account_types");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
    printf(
        "%s | %s | Nullable: %s\n",
        $column['Field'],
        $column['Type'],
        $column['Null']
    );
}
<?php
declare(strict_types=1);
require __DIR__ . '/app/config/database.php';

foreach (['companies', 'finance_settings'] as $table) {
    $row = $pdo->query("SELECT COUNT(*) AS total FROM `$table`")->fetch(PDO::FETCH_ASSOC);
    printf("%s: %d\n", $table, (int) $row['total']);
}

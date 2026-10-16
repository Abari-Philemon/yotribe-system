<?php
require __DIR__ . '/app/config/database.php';

$checks = [
    'accounts' => ['current_balance', 'opening_balance', 'normal_balance'],
    'journal_entries' => [
        'status',
        'posted_by',
        'posted_at',
        'reversed_by',
        'reversed_at',
        'reversal_journal_id',
        'total_debit',
        'total_credit'
    ]
];

foreach ($checks as $table => $wantedColumns) {
    echo PHP_EOL . "=== {$table} ===" . PHP_EOL;

    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $column) {
        if (in_array($column['Field'], $wantedColumns, true)) {
            echo $column['Field'] . ' | '
                . $column['Type'] . ' | NULL='
                . $column['Null'] . ' | DEFAULT='
                . ($column['Default'] ?? 'NULL') . PHP_EOL;
        }
    }
}

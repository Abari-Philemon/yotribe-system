<?php
declare(strict_types=1);

$files = [
    'app/modules/finance/Services/AccountService.php',
    'app/modules/finance/Repositories/AccountRepository.php',
    'app/modules/finance/Repositories/AccountTypeRepository.php',
    'app/modules/finance/Requests/AccountRequest.php',
    'app/Core/Database.php',
];

foreach ($files as $file) {
    echo "\n--- {$file} ---\n";
    $source = file_get_contents(__DIR__ . '/' . $file);

    if ($source === false) {
        echo "FILE NOT READABLE\n";
        continue;
    }

    if (preg_match('/^namespace\s+[^;]+;/m', $source, $match)) {
        echo $match[0] . "\n";
    }

    if (preg_match('/public function __construct\s*\([^)]*\)/s', $source, $match)) {
        echo $match[0] . "\n";
    }

    if (strpos($file, 'AccountService.php') !== false) {
        foreach (['create', 'updateAccount', 'getAccount'] as $method) {
            if (preg_match('/public function ' . $method . '\s*\([^)]*\)\s*:\s*[^{\n]+/s', $source, $match)) {
                echo $match[0] . "\n";
            }
        }
    }
}
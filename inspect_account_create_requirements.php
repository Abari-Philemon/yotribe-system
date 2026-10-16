<?php
declare(strict_types=1);

$files = [
    'app/modules/finance/Services/AccountService.php',
    'app/modules/finance/Requests/AccountRequest.php',
];

foreach ($files as $file) {
    echo "\n--- {$file} ---\n";
    $source = file_get_contents(__DIR__ . '/' . $file);

    if ($source === false) {
        echo "FILE NOT READABLE\n";
        continue;
    }

    $patterns = [
        '/public function create\s*\([^)]*\)\s*:\s*array/s',
        '/public function validate\s*\([^)]*\).*?(?=\n\s*public function |\n})/s',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $source, $match)) {
            echo $match[0] . "\n";
        }
    }
}
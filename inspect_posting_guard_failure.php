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

    if (str_ends_with($file, 'AccountService.php')) {
        $start = strpos($source, 'public function updateAccount(');
        if ($start !== false) {
            echo substr($source, $start, min(8000, strlen($source) - $start));
        }
    } else {
        foreach (['function validateUpdate', 'allow_posting', 'is_control_account'] as $needle) {
            $position = strpos($source, $needle);
            if ($position !== false) {
                echo "\n[$needle]\n";
                echo substr($source, max(0, $position - 150), 900) . "\n";
            }
        }
    }
}
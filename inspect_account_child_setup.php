<?php
declare(strict_types=1);

$files = [
    'app/modules/finance/Services/AccountService.php',
    'app/modules/finance/Repositories/AccountRepository.php',
];

foreach ($files as $file) {
    echo "\n--- {$file} ---\n";
    $source = file_get_contents(__DIR__ . '/' . $file);

    if ($source === false) {
        echo "FILE NOT READABLE\n";
        continue;
    }

    foreach (['validateParentAccount', 'allow_posting', 'account_class_id'] as $needle) {
        $offset = 0;
        $count = 0;

        while (($position = stripos($source, $needle, $offset)) !== false && $count < 2) {
            echo "\n[$needle]\n";
            echo substr($source, max(0, $position - 180), 500) . "\n";
            $offset = $position + strlen($needle);
            $count++;
        }
    }
}
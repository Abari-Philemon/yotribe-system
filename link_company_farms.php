<?php
require __DIR__ . '/app/config/database.php';

$companyId = 1;
$farmIds = [1, 2];

try {
    $pdo->beginTransaction();

    $companyStmt = $pdo->prepare(
        "SELECT id FROM companies WHERE id = ? AND status = 'active' FOR UPDATE"
    );
    $companyStmt->execute([$companyId]);

    if (!$companyStmt->fetchColumn()) {
        throw new RuntimeException('Company 1 does not exist or is inactive.');
    }

    $farmStmt = $pdo->prepare(
        "SELECT id, name, status FROM farms WHERE id = ?"
    );

    $existingStmt = $pdo->prepare(
        "SELECT company_id FROM company_farms WHERE farm_id = ?"
    );

    $insertStmt = $pdo->prepare(
        "INSERT INTO company_farms (company_id, farm_id) VALUES (?, ?)"
    );

    foreach ($farmIds as $farmId) {
        $farmStmt->execute([$farmId]);
        $farm = $farmStmt->fetch();

        if (!$farm || $farm['status'] !== 'active') {
            throw new RuntimeException(
                "Farm {$farmId} does not exist or is inactive."
            );
        }

        $existingStmt->execute([$farmId]);
        $linkedCompanyId = $existingStmt->fetchColumn();

        if ($linkedCompanyId !== false) {
            if ((int)$linkedCompanyId !== $companyId) {
                throw new RuntimeException(
                    "Farm {$farmId} is already linked to another company."
                );
            }

            echo "{$farm['name']} is already linked to Company {$companyId}.\n";
            continue;
        }

        $insertStmt->execute([$companyId, $farmId]);
        echo "Linked {$farm['name']} (Farm {$farmId}) to Company {$companyId}.\n";
    }

    $pdo->commit();
    echo "\nCompany-farm associations completed.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

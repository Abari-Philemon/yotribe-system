<?php

declare(strict_types=1);

function audit_log(

    PDO $pdo,

    string $module,

    int $recordId,

    string $action,

    array $values,

    int $staffId

): void {

    $stmt = $pdo->prepare("

        INSERT INTO audit_logs(

            uuid,

            module,

            record_id,

            action,

            values_json,

            recorded_by

        )

        VALUES(

            ?,?,?,?,?,?

        )

    ");

    $stmt->execute([

        generateUuid(),

        $module,

        $recordId,

        $action,

        json_encode($values),

        $staffId

    ]);

}
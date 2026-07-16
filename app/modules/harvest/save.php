<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Harvest Management
 * Save Harvest
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

require_permission('harvest');

/*
|--------------------------------------------------------------------------
| Context
|--------------------------------------------------------------------------
*/

$farm_id  = farm_id();
$staff_id = $_SESSION['staff_id'];

/*
|--------------------------------------------------------------------------
| POST Request Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $_SESSION['error'] = 'Invalid request method.';

    header('Location: create.php');

    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Validation
|--------------------------------------------------------------------------
*/

$csrf_token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {

    $_SESSION['error'] = 'Invalid security token.';

    header('Location: create.php');

    exit;
}

/*
|--------------------------------------------------------------------------
| Collect Input
|--------------------------------------------------------------------------
*/

$harvest_no   = trim($_POST['harvest_no'] ?? '');
$harvest_date = trim($_POST['harvest_date'] ?? '');
$fish_batch_id = (int)($_POST['fish_batch_id'] ?? 0);
$remarks      = trim($_POST['remarks'] ?? '');

$pond_stocking_ids = $_POST['pond_stocking_id'] ?? [];
$pond_ids          = $_POST['pond_id'] ?? [];
$batch_ids         = $_POST['batch_id'] ?? [];

$harvest_starts = $_POST['harvest_start'] ?? [];
$harvest_ends   = $_POST['harvest_end'] ?? [];
$pond_remarks   = $_POST['pond_remarks'] ?? [];

/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/

$errors = [];

if ($harvest_no === '') {

    $errors[] = 'Harvest number is required.';
}

if ($harvest_date === '') {

    $errors[] = 'Harvest date is required.';
}

if ($fish_batch_id <= 0) {

    $errors[] = 'Please select a fish batch.';
}

if (empty($pond_stocking_ids)) {

    $errors[] = 'No participating ponds were submitted.';
}

/*
|--------------------------------------------------------------------------
| Validate Arrays
|--------------------------------------------------------------------------
*/

$rowCount = count($pond_stocking_ids);

if (
    $rowCount !== count($pond_ids) ||
    $rowCount !== count($batch_ids) ||
    $rowCount !== count($harvest_starts) ||
    $rowCount !== count($harvest_ends)
) {

    $errors[] = 'Harvest data is incomplete.';
}

/*
|--------------------------------------------------------------------------
| Validate Times
|--------------------------------------------------------------------------
*/

for ($i = 0; $i < $rowCount; $i++) {

    $start = trim($harvest_starts[$i] ?? '');
    $end   = trim($harvest_ends[$i] ?? '');

    if ($start === '' || $end === '') {

        $errors[] = "Harvest time is required for row " . ($i + 1);

        continue;
    }

    if ($end <= $start) {

        $errors[] = "Harvest end time must be later than start time (Row " . ($i + 1) . ").";
    }
}

/*
|--------------------------------------------------------------------------
| Stop If Validation Failed
|--------------------------------------------------------------------------
*/

if (!empty($errors)) {

    $_SESSION['error'] = implode('<br>', $errors);

    header('Location: create.php');

    exit;
}

/*
|--------------------------------------------------------------------------
| Verify Fish Batch
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        farm_id,
        status
    FROM fish_batches
    WHERE id = ?
      AND farm_id = ?
    LIMIT 1
");

$stmt->execute([
    $fish_batch_id,
    $farm_id
]);

$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {

    $_SESSION['error'] = 'Fish batch not found.';

    header('Location: create.php');

    exit;
}

if ($batch['status'] !== 'active') {

    $_SESSION['error'] = 'Only active batches can be harvested.';

    header('Location: create.php');

    exit;
}

/*
|--------------------------------------------------------------------------
| Ensure No Existing Open Harvest
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM harvests
    WHERE fish_batch_id = ?
      AND farm_id = ?
      AND is_open = 1
    LIMIT 1
");

$stmt->execute([
    $fish_batch_id,
    $farm_id
]);

if ($stmt->fetch()) {

    $_SESSION['error'] = 'This batch already has an open harvest.';

    header('Location: create.php');

    exit;
}

/*
|--------------------------------------------------------------------------
| Continue To Database Transaction...
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Database Transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Create Harvest
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO harvests (

            harvest_no,

            farm_id,

            fish_batch_id,

            harvest_date,

            status,

            is_open,

            remarks,

            created_by

        ) VALUES (

            :harvest_no,

            :farm_id,

            :fish_batch_id,

            :harvest_date,

            'selling',

            1,

            :remarks,

            :created_by

        )
    ");

    $stmt->execute([

        ':harvest_no'   => $harvest_no,

        ':farm_id'      => $farm_id,

        ':fish_batch_id'=> $fish_batch_id,

        ':harvest_date' => $harvest_date,

        ':remarks'      => $remarks !== '' ? $remarks : null,

        ':created_by'   => $staff_id

    ]);

    /*
    |--------------------------------------------------------------------------
    | Harvest ID
    |--------------------------------------------------------------------------
    */

    $harvest_id = (int)$pdo->lastInsertId();

    if ($harvest_id <= 0) {

        throw new Exception(
            'Unable to create harvest record.'
        );

    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Statements
    |--------------------------------------------------------------------------
    */

    $insertHarvestPond = $pdo->prepare("
        INSERT INTO harvest_ponds (

            harvest_id,

            pond_stocking_id,

            pond_id,

            batch_id,

            harvest_start,

            harvest_end,

            remarks

        ) VALUES (

            :harvest_id,

            :pond_stocking_id,

            :pond_id,

            :batch_id,

            :harvest_start,

            :harvest_end,

            :remarks

        )
    ");

    $updatePondStocking = $pdo->prepare("
        UPDATE pond_stocking
        SET

            status = 'harvested'

        WHERE

            id = :id

        AND farm_id = :farm_id

        LIMIT 1
    ");

    $insertHarvestLog = $pdo->prepare("
        INSERT INTO harvest_logs (

            harvest_id,

            action,

            description,

            staff_id,

            ip_address,

            user_agent

        ) VALUES (

            :harvest_id,

            :action,

            :description,

            :staff_id,

            :ip_address,

            :user_agent

        )
    ");

    /*
    |--------------------------------------------------------------------------
    | Continue With Pond Processing...
    |--------------------------------------------------------------------------
    */
        /*
    |--------------------------------------------------------------------------
    | Process Participating Ponds
    |--------------------------------------------------------------------------
    */

    for ($i = 0; $i < $rowCount; $i++) {

        $pondStockingId = (int)$pond_stocking_ids[$i];
        $pondId         = (int)$pond_ids[$i];
        $batchId        = (int)$batch_ids[$i];

        $startTime = trim($harvest_starts[$i]);
        $endTime   = trim($harvest_ends[$i]);

        $pondRemark = trim($pond_remarks[$i] ?? '');

        /*
        ------------------------------------------------------------
        Verify Pond Stocking Record
        ------------------------------------------------------------
        */

        $verify = $pdo->prepare("
            SELECT

                id,
                pond_id,
                batch_id,
                farm_id,
                status,
                current_count

            FROM pond_stocking

            WHERE

                id = ?

            AND farm_id = ?

            LIMIT 1
        ");

        $verify->execute([
            $pondStockingId,
            $farm_id
        ]);

        $stocking = $verify->fetch(PDO::FETCH_ASSOC);

        if (!$stocking) {

            throw new Exception(
                "Invalid pond stocking record."
            );

        }

        if ($stocking['status'] !== 'active') {

            throw new Exception(
                "One or more ponds are no longer active."
            );

        }

        if ((int)$stocking['current_count'] <= 0) {

            throw new Exception(
                "Selected pond has no fish available."
            );

        }

        /*
        ------------------------------------------------------------
        Insert Harvest Pond
        ------------------------------------------------------------
        */

        $insertHarvestPond->execute([

            ':harvest_id'       => $harvest_id,

            ':pond_stocking_id' => $pondStockingId,

            ':pond_id'          => $pondId,

            ':batch_id'         => $batchId,

            ':harvest_start'    => $harvest_date . ' ' . $startTime . ':00',

            ':harvest_end'      => $harvest_date . ' ' . $endTime . ':00',

            ':remarks'          => $pondRemark !== ''
                                    ? $pondRemark
                                    : null

        ]);

        /*
        ------------------------------------------------------------
        Mark Pond As Harvested
        ------------------------------------------------------------
        */

        $updatePondStocking->execute([

            ':id'      => $pondStockingId,

            ':farm_id' => $farm_id

        ]);

    }

    /*
    |--------------------------------------------------------------------------
    | Harvest Audit Log
    |--------------------------------------------------------------------------
    */

    $insertHarvestLog->execute([

        ':harvest_id' => $harvest_id,

        ':action' => 'CREATE',

        ':description' => sprintf(

            'Harvest %s opened for Batch #%d with %d participating pond(s).',

            $harvest_no,

            $fish_batch_id,

            $rowCount

        ),

        ':staff_id' => $staff_id,

        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,

        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null

    ]);

    /*
    |--------------------------------------------------------------------------
    | Continue To Commit...
    |--------------------------------------------------------------------------
    */
        /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Success Message
    |--------------------------------------------------------------------------
    */

    $_SESSION['success'] = sprintf(

        'Harvest %s has been opened successfully.',

        $harvest_no

    );

    /*
    |--------------------------------------------------------------------------
    | Redirect To Harvest View
    |--------------------------------------------------------------------------
    */

    header(

        'Location: view.php?id=' . $harvest_id

    );

    exit;

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback Transaction
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }

    /*
    |--------------------------------------------------------------------------
    | Log Error
    |--------------------------------------------------------------------------
    */

    error_log(sprintf(

        "[Harvest] %s | File: %s | Line: %d",

        $e->getMessage(),

        $e->getFile(),

        $e->getLine()

    ));

    /*
    |--------------------------------------------------------------------------
    | Flash Error
    |--------------------------------------------------------------------------
    */

    $_SESSION['error'] = sprintf(

        "Unable to create harvest.<br>%s",

        htmlspecialchars($e->getMessage())

    );

    /*
    |--------------------------------------------------------------------------
    | Redirect Back
    |--------------------------------------------------------------------------
    */

    header(

        'Location: create.php'

    );

    exit;

}
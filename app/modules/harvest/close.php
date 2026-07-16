<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Harvest Management
 * Close Harvest
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
| POST Requests Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $_SESSION['error'] = 'Invalid request method.';

    header('Location: history.php');

    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Validation
|--------------------------------------------------------------------------
*/

$csrf = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf)
) {

    $_SESSION['error'] = 'Invalid security token.';

    header('Location: history.php');

    exit;
}

/*
|--------------------------------------------------------------------------
| Harvest ID
|--------------------------------------------------------------------------
*/

$harvest_id = filter_input(
    INPUT_POST,
    'harvest_id',
    FILTER_VALIDATE_INT
);

if (!$harvest_id) {

    $_SESSION['error'] = 'Invalid harvest selected.';

    header('Location: history.php');

    exit;
}

/*
|--------------------------------------------------------------------------
| Verify Harvest
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        id,

        harvest_no,

        farm_id,

        fish_batch_id,

        status,

        is_open

    FROM harvests

    WHERE

        id = ?

    AND farm_id = ?

    LIMIT 1
");

$stmt->execute([
    $harvest_id,
    $farm_id
]);

$harvest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$harvest) {

    $_SESSION['error'] = 'Harvest not found.';

    header('Location: history.php');

    exit;
}

if (!(bool)$harvest['is_open']) {

    $_SESSION['error'] = 'Harvest is already closed.';

    header('Location: view.php?id=' . $harvest_id);

    exit;
}

/*
|--------------------------------------------------------------------------
| Continue To Closing Transaction
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Close Harvest Transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Update Harvest
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE harvests
        SET

            status      = 'closed',
            is_open     = 0,
            closed_at   = NOW(),
            closed_by   = :staff_id

        WHERE

            id = :harvest_id
        AND farm_id = :farm_id
        AND is_open = 1

        LIMIT 1
    ");

    $stmt->execute([

        ':staff_id'   => $staff_id,

        ':harvest_id' => $harvest_id,

        ':farm_id'    => $farm_id

    ]);

    if ($stmt->rowCount() === 0) {

        throw new Exception(
            'Unable to close harvest.'
        );

    }

    /*
    |--------------------------------------------------------------------------
    | Check Remaining Fish
    |--------------------------------------------------------------------------
    |
    | If there are no fish left in the batch,
    | mark the batch as harvested.
    |
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT current_count
        FROM fish_batches
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([

        $harvest['fish_batch_id']

    ]);

    $remainingFish = (int)$stmt->fetchColumn();

    if ($remainingFish <= 0) {

        $stmt = $pdo->prepare("
            UPDATE fish_batches
            SET status = 'harvested'
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([

            $harvest['fish_batch_id']

        ]);

    }

    /*
    |--------------------------------------------------------------------------
    | Harvest Audit Log
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO harvest_logs (

            harvest_id,

            action,

            description,

            staff_id,

            ip_address,

            user_agent

        ) VALUES (

            :harvest_id,

            'CLOSE',

            :description,

            :staff_id,

            :ip_address,

            :user_agent

        )
    ");

    $stmt->execute([

        ':harvest_id' => $harvest_id,

        ':description' => sprintf(

            'Harvest %s closed successfully.',

            $harvest['harvest_no']

        ),

        ':staff_id' => $staff_id,

        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,

        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null

    ]);

    /*
    |--------------------------------------------------------------------------
    | Continue To Commit
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

        'Harvest %s has been closed successfully.',

        $harvest['harvest_no']

    );

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(

        'Location: view.php?id=' . $harvest_id

    );

    exit;

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
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

        "[Harvest Close] %s | File: %s | Line: %d",

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

        "Unable to close harvest.<br>%s",

        htmlspecialchars($e->getMessage())

    );

    /*
    |--------------------------------------------------------------------------
    | Redirect Back
    |--------------------------------------------------------------------------
    */

    header(

        'Location: view.php?id=' . $harvest_id

    );

    exit;

}
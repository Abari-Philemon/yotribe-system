<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_context.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';
require_once __DIR__ . '/../../helpers/rbac.php';

/**
 * ============================================================
 * MODULE ACCESS
 * ============================================================
 */

require_permission('mortality');


/**
 * ============================================================
 * FARM CONTEXT
 * ============================================================
 */

$farm_id = (int) farm_id();

if ($farm_id <= 0) {
    http_response_code(403);
    exit('Invalid farm context.');
}


/**
 * ============================================================
 * PAGE CONFIG
 * ============================================================
 */

$page_title = 'Mortality Tracking';

$message = '';
$error   = '';


/**
 * ============================================================
 * CSRF
 * ============================================================
 */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


/**
 * ============================================================
 * HELPERS
 * ============================================================
 */

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/**
 * Redirect after successful POST.
 *
 * POST/Redirect/GET prevents duplicate submissions
 * when the browser page is refreshed.
 */
function redirectToSelf(): never
{
    $url = strtok($_SERVER['REQUEST_URI'], '?');

    header(
        'Location: ' . $url . '?success=1',
        true,
        303
    );

    exit;
}


/**
 * ============================================================
 * HANDLE MORTALITY
 * ============================================================
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /**
         * --------------------------------------------------------
         * CSRF VALIDATION
         * --------------------------------------------------------
         */

        $csrf_token = (string) ($_POST['csrf_token'] ?? '');

        if (
            $csrf_token === '' ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $csrf_token)
        ) {
            throw new RuntimeException(
                'Invalid security token. Please refresh the page and try again.'
            );
        }


        /**
         * --------------------------------------------------------
         * INPUT VALIDATION
         * --------------------------------------------------------
         */

        $stock_id = filter_input(
            INPUT_POST,
            'stock_id',
            FILTER_VALIDATE_INT
        );

        $dead_count = filter_input(
            INPUT_POST,
            'dead_count',
            FILTER_VALIDATE_INT
        );

        $cause = trim(
            (string) ($_POST['suspected_cause'] ?? '')
        );

        $action = trim(
            (string) ($_POST['action_taken'] ?? '')
        );

        $staff_id = (int) ($_SESSION['staff_id'] ?? 0);


        if (!$stock_id || $stock_id <= 0) {
            throw new InvalidArgumentException(
                'Please select a valid pond and batch.'
            );
        }

        if (!$dead_count || $dead_count <= 0) {
            throw new InvalidArgumentException(
                'Mortality count must be greater than zero.'
            );
        }

        if ($staff_id <= 0) {
            throw new RuntimeException(
                'Unable to identify the reporting staff member.'
            );
        }


        /**
         * --------------------------------------------------------
         * START TRANSACTION
         * --------------------------------------------------------
         */

        $pdo->beginTransaction();


        /**
         * --------------------------------------------------------
         * LOCK AND LOAD STOCK
         *
         * We explicitly retrieve batch_id.
         *
         * The selected stocking record belongs to the current farm.
         * FOR UPDATE prevents concurrent mortality transactions
         * from modifying the same stock simultaneously.
         * --------------------------------------------------------
         */

        $stmt = $pdo->prepare("
            SELECT
                ps.id,
                ps.farm_id,
                ps.pond_id,
                ps.batch_id,
                ps.current_count,
                ps.status,

                p.pond_code,

                fb.batch_code

            FROM pond_stocking ps

            INNER JOIN ponds_tanks p
                ON p.id = ps.pond_id

            INNER JOIN fish_batches fb
                ON fb.id = ps.batch_id

            WHERE ps.id = ?
              AND ps.farm_id = ?

            FOR UPDATE
        ");

        $stmt->execute([
            $stock_id,
            $farm_id
        ]);

        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            throw new RuntimeException(
                'The selected pond stock could not be found.'
            );
        }


        /**
         * --------------------------------------------------------
         * VALIDATE BATCH
         * --------------------------------------------------------
         *
         * pond_stocking.batch_id is the authoritative batch
         * relationship for this mortality transaction.
         */

        $batch_id = (int) $stock['batch_id'];

        if ($batch_id <= 0) {
            throw new RuntimeException(
                'The selected stock does not have a valid fish batch.'
            );
        }


        /**
         * --------------------------------------------------------
         * VALIDATE CURRENT STOCK
         * --------------------------------------------------------
         */

        $current_count = (int) $stock['current_count'];

        if ($current_count <= 0) {
            throw new RuntimeException(
                'This stock is already empty.'
            );
        }


        /**
         * --------------------------------------------------------
         * PREVENT EXCESS MORTALITY
         * --------------------------------------------------------
         */

        if ($dead_count > $current_count) {
            throw new RuntimeException(
                "Mortality count ({$dead_count}) cannot exceed " .
                "available fish ({$current_count})."
            );
        }


        /**
         * --------------------------------------------------------
         * REDUCE STOCK ATOMICALLY
         * --------------------------------------------------------
         *
         * The additional current_count >= ? condition protects
         * against an unexpected concurrent modification.
         */

        $stmt = $pdo->prepare("
            UPDATE pond_stocking
            SET current_count = current_count - ?

            WHERE id = ?
              AND farm_id = ?
              AND current_count >= ?
        ");

        $stmt->execute([
            $dead_count,
            $stock_id,
            $farm_id,
            $dead_count
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Stock could not be updated. Please retry the transaction.'
            );
        }


        /**
         * --------------------------------------------------------
         * DETERMINE REMAINING STOCK
         * --------------------------------------------------------
         */

        $remaining_count = $current_count - $dead_count;


        /**
         * --------------------------------------------------------
         * UPDATE STOCK STATUS
         * --------------------------------------------------------
         *
         * Existing schema compatibility:
         * when the stocking record reaches zero, retain the
         * existing "harvested" status convention.
         *
         * If the database later introduces an "empty" or
         * "depleted" status, this can be changed accordingly.
         * --------------------------------------------------------
         */

        if ($remaining_count <= 0) {

            $stmt = $pdo->prepare("
                UPDATE pond_stocking

                SET status = 'harvested'

                WHERE id = ?
                  AND farm_id = ?
            ");

            $stmt->execute([
                $stock_id,
                $farm_id
            ]);
        }


        /**
         * ========================================================
         * INSERT MORTALITY LOG
         * ========================================================
         *
         * IMPORTANT:
         *
         * LIVE DATABASE:
         *
         * mortality_logs.batch_id
         * VARCHAR(50)
         * NOT NULL
         * NO DEFAULT
         *
         * Therefore batch_id MUST be explicitly supplied.
         *
         * The value comes from pond_stocking.batch_id.
         */

        $stmt = $pdo->prepare("
            INSERT INTO mortality_logs
            (
                date,
                farm_id,
                pond_id,
                batch_id,
                dead_count,
                suspected_cause,
                action_taken,
                reported_by
            )

            VALUES
            (
                CURDATE(),
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([
            $farm_id,
            (int) $stock['pond_id'],

            /*
             * mortality_logs.batch_id is VARCHAR(50),
             * so explicitly convert the integer batch ID
             * to a string-compatible value.
             */
            (string) $batch_id,

            $dead_count,

            $cause !== ''
                ? $cause
                : null,

            $action !== ''
                ? $action
                : null,

            $staff_id
        ]);


        /**
         * ========================================================
         * INSERT STOCK MOVEMENT
         * ========================================================
         *
         * stock_movements.batch_id is INT NOT NULL.
         *
         * Therefore we use the integer $batch_id here.
         */

        $stmt = $pdo->prepare("
            INSERT INTO stock_movements
            (
                farm_id,
                type,
                from_pond_id,
                batch_id,
                quantity,
                movement_date,
                note
            )

            VALUES
            (
                ?,
                'mortality',
                ?,
                ?,
                ?,
                CURDATE(),
                ?
            )
        ");

        $stmt->execute([
            $farm_id,
            (int) $stock['pond_id'],
            $batch_id,
            $dead_count,
            $cause !== ''
                ? $cause
                : null
        ]);


        /**
         * --------------------------------------------------------
         * COMMIT
         * --------------------------------------------------------
         *
         * At this point:
         *
         * 1. pond_stocking was reduced
         * 2. mortality_logs was inserted
         * 3. stock_movements was inserted
         *
         * All three succeed or all three roll back.
         */

        $pdo->commit();


        /**
         * --------------------------------------------------------
         * POST/REDIRECT/GET
         * --------------------------------------------------------
         */

        redirectToSelf();


    } catch (Throwable $e) {

        /**
         * --------------------------------------------------------
         * ROLLBACK
         * --------------------------------------------------------
         */

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        /**
         * --------------------------------------------------------
         * LOG TECHNICAL ERROR
         * --------------------------------------------------------
         */

        error_log(
            sprintf(
                '[MORTALITY MODULE] Farm: %d | Error: %s | File: %s | Line: %d',
                $farm_id,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            )
        );


        /**
         * --------------------------------------------------------
         * USER-FACING ERROR
         * --------------------------------------------------------
         */

        $error = $e->getMessage();
    }
}


/**
 * ============================================================
 * SUCCESS MESSAGE
 * ============================================================
 */

if (
    isset($_GET['success']) &&
    $_GET['success'] === '1'
) {
    $message = 'Mortality recorded successfully.';
}


/**
 * ============================================================
 * LOAD ACTIVE STOCK
 * ============================================================
 */

$stmt = $pdo->prepare("
    SELECT
        ps.id,
        ps.pond_id,
        ps.batch_id,
        ps.current_count,
        ps.status,

        p.pond_code,

        fb.batch_code

    FROM pond_stocking ps

    INNER JOIN ponds_tanks p
        ON p.id = ps.pond_id

    INNER JOIN fish_batches fb
        ON fb.id = ps.batch_id

    WHERE ps.farm_id = ?
      AND ps.status = 'active'
      AND ps.current_count > 0

    ORDER BY
        p.pond_code ASC,
        fb.batch_code ASC
");

$stmt->execute([
    $farm_id
]);

$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);


/**
 * ============================================================
 * RECENT MORTALITY LOGS
 * ============================================================
 */

$stmt = $pdo->prepare("
    SELECT
        m.id,
        m.date,
        m.dead_count,
        m.suspected_cause,
        m.action_taken,

        p.pond_code,

        s.full_name

    FROM mortality_logs m

    INNER JOIN ponds_tanks p
        ON p.id = m.pond_id

    INNER JOIN staff s
        ON s.id = m.reported_by

    WHERE m.farm_id = ?

    ORDER BY m.id DESC

    LIMIT 10
");

$stmt->execute([
    $farm_id
]);

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);


/**
 * ============================================================
 * MORTALITY ANALYTICS
 * ============================================================
 *
 * Batch-specific relationship:
 *
 * stock_movements.batch_id
 *          ↓
 * fish_batches.id
 *
 * pond_stocking is joined using BOTH:
 *
 * pond_id + batch_id
 *
 * This prevents a mortality record from being associated
 * with another batch that previously occupied the same pond.
 *
 * ============================================================
 */

$stmt = $pdo->prepare("
    SELECT

        p.id AS pond_id,
        p.pond_code,

        fb.id AS batch_id,
        fb.batch_code,

        SUM(sm.quantity) AS total_dead,

        MAX(ps.current_count) AS current_count,

        (
            SUM(sm.quantity)
            /
            NULLIF(
                SUM(sm.quantity) + MAX(ps.current_count),
                0
            )
        ) * 100 AS mortality_rate

    FROM stock_movements sm

    INNER JOIN ponds_tanks p
        ON p.id = sm.from_pond_id

    INNER JOIN fish_batches fb
        ON fb.id = sm.batch_id

    INNER JOIN pond_stocking ps
        ON ps.pond_id = sm.from_pond_id
       AND ps.batch_id = sm.batch_id

    WHERE sm.type = 'mortality'

      AND sm.farm_id = ?

      AND sm.movement_date >=
          DATE_SUB(CURDATE(), INTERVAL 7 DAY)

    GROUP BY
        p.id,
        p.pond_code,
        fb.id,
        fb.batch_code

    ORDER BY
        mortality_rate DESC,
        total_dead DESC
");

$stmt->execute([
    $farm_id
]);

$analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);


/**
 * ============================================================
 * CRITICAL ALERT
 * ============================================================
 */

$critical_found = false;

foreach ($analytics as $a) {

    if ((float) $a['mortality_rate'] >= 10) {
        $critical_found = true;
        break;
    }
}


/**
 * ============================================================
 * PAGE
 * ============================================================
 */

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

?>

<div class="container-fluid py-3">

    <!-- ======================================================
         PAGE HEADER
         ====================================================== -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h3 class="mb-1">
                Mortality Tracking
            </h3>

            <p class="text-muted mb-0">
                Record fish mortality and monitor batch-level losses.
            </p>

        </div>

    </div>


    <!-- ======================================================
         CRITICAL ALERT
         ====================================================== -->

    <?php if ($critical_found): ?>

        <div class="alert alert-danger">

            <strong>
                🚨 High mortality detected.
            </strong>

            One or more batches have reached a critical mortality level.

        </div>

    <?php endif; ?>


    <!-- ======================================================
         SUCCESS MESSAGE
         ====================================================== -->

    <?php if ($message): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <?= e($message) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close">
            </button>

        </div>

    <?php endif; ?>


    <!-- ======================================================
         ERROR MESSAGE
         ====================================================== -->

    <?php if ($error): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <strong>
                Unable to record mortality.
            </strong>

            <div class="mt-1">
                <?= e($error) ?>
            </div>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close">
            </button>

        </div>

    <?php endif; ?>


    <!-- ======================================================
         MORTALITY FORM
         ====================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            <strong>Record Mortality</strong>
        </div>

        <div class="card-body">

            <?php if (!$stocks): ?>

                <div class="alert alert-info mb-0">

                    No active pond stock is currently available
                    for mortality recording.

                </div>

            <?php else: ?>

                <form
                    method="POST"
                    action=""
                    autocomplete="off"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <div class="row g-3">


                        <!-- Pond + Batch -->

                        <div class="col-md-6">

                            <label
                                for="stock_id"
                                class="form-label"
                            >
                                Pond / Batch
                            </label>

                            <select
                                id="stock_id"
                                name="stock_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Pond + Batch
                                </option>

                                <?php foreach ($stocks as $s): ?>

                                    <option
                                        value="<?= (int) $s['id'] ?>"
                                    >

                                        <?= e($s['pond_code']) ?>

                                        |

                                        <?= e($s['batch_code']) ?>

                                        —

                                        <?= number_format(
                                            (int) $s['current_count']
                                        ) ?>

                                        fish

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Dead Count -->

                        <div class="col-md-6">

                            <label
                                for="dead_count"
                                class="form-label"
                            >
                                Number of Fish Lost
                            </label>

                            <input
                                type="number"
                                id="dead_count"
                                name="dead_count"
                                class="form-control"
                                min="1"
                                step="1"
                                required
                            >

                        </div>


                        <!-- Cause -->

                        <div class="col-md-6">

                            <label
                                for="suspected_cause"
                                class="form-label"
                            >
                                Suspected Cause
                            </label>

                            <input
                                type="text"
                                id="suspected_cause"
                                name="suspected_cause"
                                class="form-control"
                                maxlength="100"
                                placeholder="e.g. disease, poor water quality"
                            >

                        </div>


                        <!-- Action -->

                        <div class="col-md-6">

                            <label
                                for="action_taken"
                                class="form-label"
                            >
                                Action Taken
                            </label>

                            <input
                                type="text"
                                id="action_taken"
                                name="action_taken"
                                class="form-control"
                                maxlength="255"
                                placeholder="e.g. water change, medication"
                            >

                        </div>


                        <!-- Submit -->

                        <div class="col-12">

                            <button
                                type="submit"
                                class="btn btn-danger"
                            >
                                Record Mortality
                            </button>

                        </div>

                    </div>

                </form>

            <?php endif; ?>

        </div>

    </div>


    <!-- ======================================================
         7-DAY ANALYTICS
         ====================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            <strong>
                Mortality Alerts — Last 7 Days
            </strong>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-bordered table-hover mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>Pond</th>
                            <th>Batch</th>
                            <th>Dead</th>
                            <th>Alive</th>
                            <th>Rate</th>
                            <th>Status</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!$analytics): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center text-muted py-4"
                            >
                                No mortality recorded in the last 7 days.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($analytics as $a): ?>

                            <?php

                            $rate = round(
                                (float) $a['mortality_rate'],
                                2
                            );

                            if ($rate >= 10) {

                                $status = 'CRITICAL';
                                $class  = 'danger';

                            } elseif ($rate >= 5) {

                                $status = 'WARNING';
                                $class  = 'warning';

                            } else {

                                $status = 'NORMAL';
                                $class  = 'success';

                            }

                            ?>

                            <tr>

                                <td>
                                    <?= e($a['pond_code']) ?>
                                </td>

                                <td>
                                    <?= e($a['batch_code']) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (int) $a['total_dead']
                                    ) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (int) $a['current_count']
                                    ) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        $rate,
                                        2
                                    ) ?>%
                                </td>

                                <td>

                                    <span
                                        class="badge bg-<?= e($class) ?>"
                                    >
                                        <?= e($status) ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- ======================================================
         RECENT LOGS
         ====================================================== -->

    <div class="card shadow-sm">

        <div class="card-header">

            <strong>
                Recent Mortality Logs
            </strong>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-striped table-hover mb-0">

                    <thead class="table-light">

                        <tr>

                            <th>Date</th>
                            <th>Pond</th>
                            <th>Dead</th>
                            <th>Cause</th>
                            <th>Action</th>
                            <th>Staff</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!$logs): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center text-muted py-4"
                            >
                                No mortality records found.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($logs as $log): ?>

                            <tr>

                                <td>
                                    <?= e($log['date']) ?>
                                </td>

                                <td>
                                    <?= e($log['pond_code']) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (int) $log['dead_count']
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $log['suspected_cause']
                                        ?: '—'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $log['action_taken']
                                        ?: '—'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e($log['full_name']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>
</html>
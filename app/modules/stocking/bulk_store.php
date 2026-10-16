<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/stocking_helper.php';

$farm_id = (int) farm_id();

function bulkStockingFail(string $message): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['error'] = $message;
    }

    header('Location: bulk_create.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bulk_create.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF validation
|--------------------------------------------------------------------------
*/
$postedToken = (string)($_POST['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['csrf_token'] ?? '');

if (
    $postedToken === '' ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $postedToken)
) {
    bulkStockingFail('Your session token is invalid. Please try again.');
}

/*
|--------------------------------------------------------------------------
| Validate input structure
|--------------------------------------------------------------------------
*/
$batch_id = filter_var(
    $_POST['batch_id'] ?? null,
    FILTER_VALIDATE_INT
);

$pond_ids = $_POST['pond_id'] ?? [];
$quantities = $_POST['quantity'] ?? [];

if (!$batch_id || $batch_id < 1) {
    bulkStockingFail('Please select a valid fish batch.');
}

if (!is_array($pond_ids) || !is_array($quantities)) {
    bulkStockingFail('Invalid pond or quantity submission.');
}

if (count($pond_ids) === 0 || count($pond_ids) !== count($quantities)) {
    bulkStockingFail('Add at least one pond and quantity.');
}

if (count($pond_ids) > 100) {
    bulkStockingFail('A single bulk operation cannot contain more than 100 pond rows.');
}

/*
|--------------------------------------------------------------------------
| Normalize submitted rows
|--------------------------------------------------------------------------
*/
$allocations = [];
$seenPonds = [];
$totalQuantity = 0;

foreach ($pond_ids as $index => $rawPondId) {
    $pond_id = filter_var($rawPondId, FILTER_VALIDATE_INT);
    $rawQuantity = $quantities[$index] ?? null;

    $quantity = filter_var(
        $rawQuantity,
        FILTER_VALIDATE_INT
    );

    if (!$pond_id || $pond_id < 1) {
        bulkStockingFail('Every row must have a valid destination pond.');
    }

    if ($quantity === false || $quantity < 1) {
        bulkStockingFail('Every pond quantity must be a positive whole number.');
    }

    if (isset($seenPonds[$pond_id])) {
        bulkStockingFail('A pond was selected more than once. Use one row per pond.');
    }

    $seenPonds[$pond_id] = true;
    $allocations[] = [
        'pond_id' => (int)$pond_id,
        'quantity' => (int)$quantity
    ];

    $totalQuantity += (int)$quantity;

    if ($totalQuantity > PHP_INT_MAX - 1000000) {
        bulkStockingFail('The submitted total quantity is invalid.');
    }
}

if ($totalQuantity < 1) {
    bulkStockingFail('Total stocking quantity must be greater than zero.');
}

/*
|--------------------------------------------------------------------------
| Begin all-or-nothing transaction
|--------------------------------------------------------------------------
*/
try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Lock and validate source batch
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        SELECT
            id,
            batch_code,
            species,
            current_count,
            avg_weight_g,
            status
        FROM fish_batches
        WHERE id = ?
          AND farm_id = ?
        FOR UPDATE
    ");

    $stmt->execute([$batch_id, $farm_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new RuntimeException('The selected fish batch was not found.');
    }

    if ($batch['status'] !== 'active') {
        throw new RuntimeException('The selected fish batch is not active.');
    }

    $available = (int)$batch['current_count'];
    $avgWeight = (float)$batch['avg_weight_g'];

    if ($available < 1) {
        throw new RuntimeException('The selected batch has no fish available.');
    }

    if ($avgWeight < 10) {
        throw new RuntimeException(
            'The batch average weight is invalid. A minimum of 10 g is required.'
        );
    }

    if ($totalQuantity > $available) {
        throw new RuntimeException(
            'Bulk quantity (' . number_format($totalQuantity) .
            ') exceeds batch availability (' . number_format($available) . ').'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Lock all destination pond rows in ascending ID order
    |--------------------------------------------------------------------------
    | Consistent lock ordering helps reduce deadlocks.
    */
    $sortedPondIds = array_column($allocations, 'pond_id');
    sort($sortedPondIds, SORT_NUMERIC);

    $placeholders = implode(',', array_fill(0, count($sortedPondIds), '?'));

    $stmt = $pdo->prepare("
        SELECT id
        FROM ponds_tanks
        WHERE farm_id = ?
          AND status = 'active'
          AND id IN ($placeholders)
        ORDER BY id
        FOR UPDATE
    ");

    $stmt->execute(array_merge([$farm_id], $sortedPondIds));
    $lockedPondIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (count($lockedPondIds) !== count($sortedPondIds)) {
        throw new RuntimeException(
            'One or more selected ponds do not exist, are inactive, or belong to another farm.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate EVERY pond before inserting any stocking record
    |--------------------------------------------------------------------------
    | The existing helper remains the single authority for stage limits,
    | pond capacity and juvenile minimums.
    */
    foreach ($allocations as $allocation) {
        validateStocking(
            $pdo,
            $allocation['pond_id'],
            $farm_id,
            $allocation['quantity'],
            $avgWeight
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Insert stocking records
    |--------------------------------------------------------------------------
    */
    $insert = $pdo->prepare("
        INSERT INTO pond_stocking (
            farm_id,
            pond_id,
            batch_id,
            stocked_count,
            current_count,
            avg_weight_g,
            stocking_date,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'active')
    ");

    foreach ($allocations as $allocation) {
        $insert->execute([
            $farm_id,
            $allocation['pond_id'],
            $batch_id,
            $allocation['quantity'],
            $allocation['quantity'],
            $avgWeight
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Reduce available batch balance exactly once by the total
    |--------------------------------------------------------------------------
    */
    $newAvailable = $available - $totalQuantity;
    $newStatus = $newAvailable === 0 ? 'closed' : 'active';

    $stmt = $pdo->prepare("
        UPDATE fish_batches
        SET current_count = ?,
            status = ?
        WHERE id = ?
          AND farm_id = ?
    ");

    $stmt->execute([
        $newAvailable,
        $newStatus,
        $batch_id,
        $farm_id
    ]);

    /*
    |--------------------------------------------------------------------------
    | Commit only after every validation and write succeeds
    |--------------------------------------------------------------------------
    */
    $pdo->commit();

    $_SESSION['success'] =
        'Bulk stocking completed successfully: ' .
        number_format($totalQuantity) . ' fish allocated to ' .
        count($allocations) . ' ponds.';

    header('Location: index.php?success=1');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        '[Yotribe IFMS Bulk Stocking] Farm ' . $farm_id .
        ', Batch ' . (int)$batch_id .
        ': ' . $e->getMessage()
    );

    /*
     * Show validation errors, but avoid exposing raw database internals.
     */
    $message = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Bulk stocking failed. No changes were saved. Please check the server error log.';

    bulkStockingFail($message);
}

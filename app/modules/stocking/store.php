<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/stocking_helper.php';

$farm_id = (int) farm_id();

/*
|--------------------------------------------------------------------------
| Redirect with an error message
|--------------------------------------------------------------------------
*/
function stockingRedirectError(string $message): void
{
    header(
        'Location: create.php?error=' . rawurlencode($message)
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate the submitted average weight
|--------------------------------------------------------------------------
*/
$pond_id = filter_input(INPUT_POST, 'pond_id', FILTER_VALIDATE_INT);
$batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
$qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

$weightInput = $_POST['avg_weight_g'] ?? null;

if (
    $pond_id === false ||
    $pond_id === null ||
    $pond_id < 1
) {
    stockingRedirectError('Select a valid pond.');
}

if (
    $batch_id === false ||
    $batch_id === null ||
    $batch_id < 1
) {
    stockingRedirectError('Select a valid fish batch.');
}

if (
    $qty === false ||
    $qty === null ||
    $qty < 1
) {
    stockingRedirectError('Quantity must be a positive whole number.');
}

if (
    !is_scalar($weightInput) ||
    !is_numeric((string) $weightInput)
) {
    stockingRedirectError('Enter a valid average fish weight.');
}

$avg_weight_g = (float) $weightInput;

if (
    !is_finite($avg_weight_g) ||
    $avg_weight_g < 10
) {
    stockingRedirectError(
        'Average fish weight must be at least 10 grams.'
    );
}

try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | 1. Lock and validate the batch
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        SELECT
            id,
            farm_id,
            current_count,
            avg_weight_g,
            status
        FROM fish_batches
        WHERE id = ?
          AND farm_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        (int) $batch_id,
        $farm_id
    ]);

    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new RuntimeException('The selected fish batch was not found.');
    }

    if ($batch['status'] !== 'active') {
        throw new RuntimeException('The selected fish batch is not active.');
    }

    $batchAvailable = (int) $batch['current_count'];

    if ($batchAvailable < 1) {
        throw new RuntimeException(
            'The selected batch has no fish available.'
        );
    }

    if ((int) $qty > $batchAvailable) {
        throw new RuntimeException(
            'The batch has only ' .
            number_format($batchAvailable) .
            ' fish available.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Lock and validate the destination pond
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        SELECT
            id,
            farm_id,
            pond_type,
            size_label,
            length_ft,
            width_ft,
            capacity,
            status
        FROM ponds_tanks
        WHERE id = ?
          AND farm_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        (int) $pond_id,
        $farm_id
    ]);

    $pond = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pond) {
        throw new RuntimeException(
            'The selected pond does not belong to this farm.'
        );
    }

    if ($pond['status'] !== 'active') {
        throw new RuntimeException(
            'The selected pond is not active.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Validate using the entered average weight
    |--------------------------------------------------------------------------
    |
    | This applies the helper's stage rules and effective capacity:
    | MIN(database capacity, stage-specific capacity).
    |
    */
    validateStocking(
        $pdo,
        (int) $pond_id,
        $farm_id,
        (int) $qty,
        $avg_weight_g
    );

    /*
    |--------------------------------------------------------------------------
    | 4. Insert the stocking record
    |--------------------------------------------------------------------------
    |
    | Store the weight entered for this stocking operation.
    | Do not silently replace it with the batch's old average weight.
    |
    */
    $stmt = $pdo->prepare("
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

    $stmt->execute([
        $farm_id,
        (int) $pond_id,
        (int) $batch_id,
        (int) $qty,
        (int) $qty,
        $avg_weight_g
    ]);

    /*
    |--------------------------------------------------------------------------
    | 5. Deduct stocked fish from batch inventory
    |--------------------------------------------------------------------------
    |
    | The batch row is already locked. The conditional update provides
    | an additional safeguard against reducing inventory below zero.
    |
    */
    $stmt = $pdo->prepare("
        UPDATE fish_batches
        SET current_count = current_count - ?
        WHERE id = ?
          AND farm_id = ?
          AND status = 'active'
          AND current_count >= ?
    ");

    $stmt->execute([
        (int) $qty,
        (int) $batch_id,
        $farm_id,
        (int) $qty
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Batch inventory could not be updated. No stocking was saved.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Close the batch when no fish remain
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        UPDATE fish_batches
        SET status = 'closed'
        WHERE id = ?
          AND farm_id = ?
          AND current_count = 0
          AND status = 'active'
    ");

    $stmt->execute([
        (int) $batch_id,
        $farm_id
    ]);

    /*
    |--------------------------------------------------------------------------
    | 7. Commit everything together
    |--------------------------------------------------------------------------
    |
    | If any operation fails, the transaction is rolled back.
    |
    */
    $pdo->commit();

    header('Location: index.php?success=1');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Fish stocking error for farm ' .
        $farm_id . ': ' . $e->getMessage()
    );

    stockingRedirectError($e->getMessage());
}

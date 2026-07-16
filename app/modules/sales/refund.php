<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales Management
 * Refund Sale
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';

require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../helpers/permission.php';

require_permission('sales.refund');

$farm_id = farm_id();

$page_title = 'Refund Sale';

$saleId = (int)($_GET['id'] ?? 0);

if ($saleId <= 0) {

    $_SESSION['error'] = 'Invalid sale selected.';

    header('Location: history.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Load Sale
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    s.*,

    h.harvest_no,

    fb.batch_code,

    fb.species,

    st.full_name

FROM sales s

INNER JOIN harvests h
    ON h.id = s.harvest_id

INNER JOIN fish_batches fb
    ON fb.id = h.fish_batch_id

LEFT JOIN staff st
    ON st.id = s.recorded_by

WHERE

    s.id = ?

AND s.farm_id = ?

LIMIT 1

");

$stmt->execute([

    $saleId,

    $farm_id

]);

$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {

    $_SESSION['error'] = 'Sale not found.';

    header('Location: history.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($sale['status'] === 'refunded') {

    $_SESSION['error'] =

        'This sale has already been refunded.';

    header("Location:view.php?id={$saleId}");

    exit;

}

if ($sale['status'] === 'cancelled') {

    $_SESSION['error'] =

        'Cancelled sales cannot be refunded.';

    header("Location:view.php?id={$saleId}");

    exit;

}

if ($sale['status'] !== 'completed') {

    $_SESSION['error'] =

        'Only completed sales can be refunded.';

    header("Location:view.php?id={$saleId}");

    exit;

}
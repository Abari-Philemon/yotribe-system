<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Harvest Management
 * AJAX
 * Load Batch Ponds
 * ============================================================
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../middleware/auth_guard.php';
require_once __DIR__ . '/../../../middleware/farm_guard.php';
require_once __DIR__ . '/../../../middleware/authorize.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../helpers/permission.php';

require_permission('harvest');

$farm_id = farm_id();

/*
|--------------------------------------------------------------------------
| Validate Request
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed.'
    ]);

    exit;
}

$batch_id = filter_input(
    INPUT_POST,
    'fish_batch_id',
    FILTER_VALIDATE_INT
);

if (!$batch_id) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid fish batch.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Verify Batch Belongs To Farm
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM fish_batches
    WHERE id = ?
      AND farm_id = ?
      AND status = 'active'
    LIMIT 1
");

$stmt->execute([
    $batch_id,
    $farm_id
]);

if (!$stmt->fetch()) {

    echo json_encode([
        'success' => false,
        'message' => 'Fish batch not found.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Load Participating Ponds
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        ps.id AS pond_stocking_id,

        ps.pond_id,

        ps.batch_id,

        ps.current_count,

        pt.pond_code

    FROM pond_stocking ps

    INNER JOIN ponds_tanks pt
        ON pt.id = ps.pond_id

    WHERE

        ps.batch_id = ?

    AND ps.farm_id = ?

    AND ps.status = 'active'

    AND ps.current_count > 0

    ORDER BY pt.pond_code ASC
");

$stmt->execute([
    $batch_id,
    $farm_id
]);

$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

echo json_encode([

    'success' => true,

    'count' => count($ponds),

    'data' => $ponds

]);
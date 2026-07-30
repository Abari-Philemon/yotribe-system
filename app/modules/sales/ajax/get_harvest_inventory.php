<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales & Distribution Management
 * AJAX - Harvest Inventory
 * Harvest Inventory V2.0
 * ============================================================
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../middleware/auth_guard.php';
require_once __DIR__ . '/../../../middleware/farm_guard.php';
require_once __DIR__ . '/../../../config/database.php';

try {

    $farmId = farm_id();

    $harvestId = filter_input(
        INPUT_GET,
        'harvest_id',
        FILTER_VALIDATE_INT
    );

    if (!$harvestId) {

        echo json_encode([
            'success' => false,
            'message' => 'Invalid harvest selected.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Harvest Inventory
    |--------------------------------------------------------------------------
    |
    | Inventory Source:
    |
    | harvests
    |      ↓
    | harvest_ponds (Inventory)
    |      ↓
    | sale_items
    |
    */

    $sql = "

        SELECT

            hp.id AS harvest_pond_id,

            hp.pond_stocking_id,

            hp.pond_id,

            pt.pond_code,

            hp.harvested_count,

            hp.average_weight_g,

            hp.harvested_weight_kg,

            hp.available_count,

            hp.available_weight_kg,

            hp.inventory_status,

            COALESCE(

                (

                    SELECT SUM(si.quantity_fish)

                    FROM sale_items si

                    INNER JOIN sales s
                        ON s.id = si.sale_id

                    WHERE
                        si.harvest_pond_id = hp.id
                    AND s.status <> 'cancelled'

                ),

                0

            ) AS sold_fish

        FROM harvest_ponds hp

        INNER JOIN harvests h
            ON h.id = hp.harvest_id

        INNER JOIN ponds_tanks pt
            ON pt.id = hp.pond_id

        WHERE

            hp.harvest_id = ?

        AND h.farm_id = ?

        AND hp.inventory_status <> 'sold_out'

        ORDER BY

            pt.pond_code ASC

    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $harvestId,
        $farmId
    ]);

    $inventory = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $harvestedFish = (int)$row['harvested_count'];

        $availableFish = (int)$row['available_count'];

        $soldFish = (int)$row['sold_fish'];

        $averageWeightKg =
            ((float)$row['average_weight_g']) / 1000;

        $harvestWeight =
            (float)$row['harvested_weight_kg'];

        $availableWeight =
            (float)$row['available_weight_kg'];

        $inventory[] = [

            'harvest_pond_id'  => (int)$row['harvest_pond_id'],

            'pond_stocking_id' => (int)$row['pond_stocking_id'],

            'pond_id'          => (int)$row['pond_id'],

            'pond_code'        => $row['pond_code'],

            'harvested_fish'   => $harvestedFish,

            'sold_fish'        => $soldFish,

            'available_fish'   => $availableFish,

            'average_weight'   => round($averageWeightKg, 3),

            'harvest_weight'   => round($harvestWeight, 2),

            'available_weight' => round($availableWeight, 2),

            'inventory_status' => $row['inventory_status'],

            'status' => ucwords(
                str_replace(
                    '_',
                    ' ',
                    $row['inventory_status']
                )
            )

        ];

    }

    echo json_encode([

        'success' => true,

        'count' => count($inventory),

        'data' => $inventory

    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    http_response_code(500);

    error_log(
        'Harvest Inventory Error: ' . $e->getMessage()
    );

    echo json_encode([

        'success' => false,

        'message' => 'Unable to load harvest inventory.',

        'error' => $e->getMessage()

    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

}
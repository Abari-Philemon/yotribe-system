<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales & Distribution Management
 * AJAX - Harvest Inventory
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
    | Inventory comes from:
    |
    | harvests
    |      ↓
    | harvest_ponds
    |      ↓
    | pond_stocking
    |      ↓
    | sale_items
    |
    */

    $sql = "

    SELECT

        hp.id AS harvest_pond_id,

        ps.id AS pond_stocking_id,

        ps.pond_id,

        pt.pond_code,

        ps.harvested_count,

        ps.avg_weight_g,

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

    INNER JOIN pond_stocking ps
        ON ps.id = hp.pond_stocking_id

    INNER JOIN ponds_tanks pt
        ON pt.id = ps.pond_id

    WHERE

        hp.harvest_id = ?

    AND h.farm_id = ?

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

        $harvestedFish = (int) $row['harvested_count'];
        $soldFish      = (int) $row['sold_fish'];

        $availableFish = max(0, $harvestedFish - $soldFish);

        /*
        |--------------------------------------------------------------------------
        | Average Weight
        |--------------------------------------------------------------------------
        |
        | Stored in grams.
        | Convert to kilograms.
        |
        */

        $averageWeightKg = ((float) $row['avg_weight_g']) / 1000;

        /*
        |--------------------------------------------------------------------------
        | Weight Calculations
        |--------------------------------------------------------------------------
        */

        $harvestWeight = $harvestedFish * $averageWeightKg;

        $availableWeight = $availableFish * $averageWeightKg;

        $inventory[] = [

            'harvest_pond_id'  => (int) $row['harvest_pond_id'],

            'pond_stocking_id' => (int) $row['pond_stocking_id'],

            'pond_id'          => (int) $row['pond_id'],

            'pond_code'        => $row['pond_code'],

            'harvested_fish'   => $harvestedFish,

            'sold_fish'        => $soldFish,

            'available_fish'   => $availableFish,

            'average_weight'   => round($averageWeightKg, 3),

            'harvest_weight'   => round($harvestWeight, 2),

            'available_weight' => round($availableWeight, 2),

            'status' => $availableFish > 0
                ? 'Available'
                : 'Sold Out'

        ];

    }

    echo json_encode([

        'success' => true,

        'count'   => count($inventory),

        'data'    => $inventory

    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    http_response_code(500);

    error_log(
        'Harvest Inventory Error: ' . $e->getMessage()
    );

    echo json_encode([

        'success' => false,

        'message' => 'Unable to load harvest inventory.',

        // Remove this line in production if you don't want
        // exception details returned to the browser.
        'error' => $e->getMessage()

    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

}
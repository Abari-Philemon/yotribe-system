<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales & Distribution Management
 * AJAX - Harvest Inventory
 * ============================================================
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../middleware/auth_guard.php';
require_once __DIR__ . '/../../../middleware/farm_guard.php';
require_once __DIR__ . '/../../../config/database.php';

$farm_id = farm_id();

$harvest_id = filter_input(
    INPUT_GET,
    'harvest_id',
    FILTER_VALIDATE_INT
);

if (!$harvest_id) {

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
| Available fish =
| Harvested Fish - Sold Fish
|
*/

$stmt = $pdo->prepare("

SELECT

    hp.id                    AS harvest_pond_id,

    hp.pond_stocking_id,

    hp.pond_id,

    pt.pond_code,

    hp.harvested_count       AS harvested_fish,

    hp.harvest_weight_kg     AS harvest_weight,

    COALESCE(

        (
            SELECT SUM(si.fish_count)

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

ORDER BY

    pt.pond_code ASC

");

$stmt->execute([

    $harvest_id,

    $farm_id

]);

$rows = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $availableFish = (int)$row['harvested_fish'] - (int)$row['sold_fish'];

    if ($availableFish < 0) {
        $availableFish = 0;
    }

    $harvestWeight = (float)$row['harvest_weight'];

    $availableWeight = $harvestWeight;

    if ((int)$row['harvested_fish'] > 0) {

        $availableWeight =
            ($availableFish / (int)$row['harvested_fish']) * $harvestWeight;

    }

    $rows[] = [

        'harvest_pond_id'  => (int)$row['harvest_pond_id'],

        'pond_stocking_id' => (int)$row['pond_stocking_id'],

        'pond_id'          => (int)$row['pond_id'],

        'pond_code'        => $row['pond_code'],

        'harvested_fish'   => (int)$row['harvested_fish'],

        'sold_fish'        => (int)$row['sold_fish'],

        'available_fish'   => $availableFish,

        'harvest_weight'   => round($harvestWeight, 2),

        'available_weight' => round($availableWeight, 2),

        'status' => $availableFish > 0
            ? 'Available'
            : 'Sold Out'

    ];

}

echo json_encode([

    'success' => true,

    'count' => count($rows),

    'data' => $rows

]);
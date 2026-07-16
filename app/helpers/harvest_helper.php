<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Harvest Helper
 * ============================================================
 */

/**
 * ------------------------------------------------------------
 * Get Harvest
 * ------------------------------------------------------------
 */
function getHarvestById(
    PDO $pdo,
    int $harvestId,
    int $farmId
): ?array {

    $stmt = $pdo->prepare("

        SELECT

            h.*,

            fb.batch_code,

            fb.species,

            fb.source,

            fb.initial_count,

            fb.current_count,

            fb.avg_weight_g,

            f.farm_name

        FROM harvests h

        INNER JOIN fish_batches fb

            ON fb.id = h.fish_batch_id

        INNER JOIN farms f

            ON f.id = h.farm_id

        WHERE

            h.id = ?

        AND h.farm_id = ?

        LIMIT 1

    ");

    $stmt->execute([

        $harvestId,

        $farmId

    ]);

    $harvest = $stmt->fetch(PDO::FETCH_ASSOC);

    return $harvest ?: null;

}

/**
 * ------------------------------------------------------------
 * Get Harvest Ponds
 * ------------------------------------------------------------
 */
function getHarvestPonds(
    PDO $pdo,
    int $harvestId
): array {

    $stmt = $pdo->prepare("

        SELECT

            hp.*,

            pt.pond_code,

            ps.current_count

        FROM harvest_ponds hp

        INNER JOIN ponds_tanks pt

            ON pt.id = hp.pond_id

        INNER JOIN pond_stocking ps

            ON ps.id = hp.pond_stocking_id

        WHERE

            hp.harvest_id = ?

        ORDER BY

            pt.pond_code ASC

    ");

    $stmt->execute([

        $harvestId

    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}
/**
 * ------------------------------------------------------------
 * Get Harvest Activity Logs
 * ------------------------------------------------------------
 */
function getHarvestLogs(
    PDO $pdo,
    int $harvestId
): array {

    $stmt = $pdo->prepare("

        SELECT

            hl.*,

            s.full_name AS staff_name

        FROM harvest_logs hl

        LEFT JOIN staff s

            ON s.id = hl.staff_id

        WHERE

            hl.harvest_id = ?

        ORDER BY

            hl.created_at DESC

    ");

    $stmt->execute([

        $harvestId

    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}


/**
 * ------------------------------------------------------------
 * Check Whether Harvest Is Open
 * ------------------------------------------------------------
 */
function isHarvestOpen(
    array $harvest
): bool {

    return !empty($harvest['is_open']);

}


/**
 * ------------------------------------------------------------
 * Format Harvest Status
 * ------------------------------------------------------------
 */
function formatHarvestStatus(
    string $status
): string {

    return match ($status) {

        'selling'   => 'Selling',

        'closed'    => 'Closed',

        'cancelled' => 'Cancelled',

        default     => ucfirst($status)

    };

}


/**
 * ------------------------------------------------------------
 * Harvest Status Badge
 * ------------------------------------------------------------
 */
function harvestStatusBadge(
    string $status
): string {

    return match ($status) {

        'selling'   => 'success',

        'closed'    => 'secondary',

        'cancelled' => 'danger',

        default     => 'primary'

    };

}


/**
 * ------------------------------------------------------------
 * Count Participating Ponds
 * ------------------------------------------------------------
 */
function harvestPondCount(
    array $ponds
): int {

    return count($ponds);

}


/**
 * ------------------------------------------------------------
 * Can Harvest Be Closed?
 * ------------------------------------------------------------
 *
 * Version 1 Rule:
 * Harvest must still be open.
 *
 * Version 2:
 * Inventory validation will be added.
 *
 * ------------------------------------------------------------
 */
function canCloseHarvest(
    array $harvest
): bool {

    return (bool)$harvest['is_open'];

}


/**
 * ------------------------------------------------------------
 * Harvest Summary
 * ------------------------------------------------------------
 */
function harvestSummary(
    array $ponds
): array {

    $summary = [

        'ponds' => count($ponds),

        'fish' => 0

    ];

    foreach ($ponds as $pond) {

        $summary['fish'] += (int)$pond['current_count'];

    }

    return $summary;

}
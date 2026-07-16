<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Harvest Report Helper
 * Version 1.0
 * ============================================================
 */

/**
 * ------------------------------------------------------------
 * Dashboard Statistics
 * ------------------------------------------------------------
 */
function getHarvestDashboardStats(
    PDO $pdo,
    int $farmId
): array {

    $stmt = $pdo->prepare("

        SELECT

            COUNT(*) AS total_harvests,

            SUM(CASE WHEN is_open = 1 THEN 1 ELSE 0 END) AS open_harvests,

            SUM(CASE WHEN is_open = 0 THEN 1 ELSE 0 END) AS closed_harvests

        FROM harvests

        WHERE farm_id = ?

    ");

    $stmt->execute([$farmId]);

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
    ------------------------------------------------------------
    Participating Ponds
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        SELECT COUNT(*)

        FROM harvest_ponds hp

        INNER JOIN harvests h

            ON h.id = hp.harvest_id

        WHERE h.farm_id = ?

    ");

    $stmt->execute([$farmId]);

    $stats['participating_ponds'] = (int)$stmt->fetchColumn();

    /*
    ------------------------------------------------------------
    Estimated Fish Harvested
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        SELECT COALESCE(SUM(ps.current_count),0)

        FROM harvest_ponds hp

        INNER JOIN pond_stocking ps

            ON ps.id = hp.pond_stocking_id

        INNER JOIN harvests h

            ON h.id = hp.harvest_id

        WHERE h.farm_id = ?

    ");

    $stmt->execute([$farmId]);

    $stats['estimated_fish'] = (int)$stmt->fetchColumn();

    return [

        'total_harvests'      => (int)$stats['total_harvests'],

        'open_harvests'       => (int)$stats['open_harvests'],

        'closed_harvests'     => (int)$stats['closed_harvests'],

        'participating_ponds' => (int)$stats['participating_ponds'],

        'estimated_fish'      => (int)$stats['estimated_fish']

    ];

}
/**
 * ------------------------------------------------------------
 * Monthly Harvest Trend
 * ------------------------------------------------------------
 */
function getMonthlyHarvestTrend(
    PDO $pdo,
    int $farmId,
    int $months = 12
): array {

    $stmt = $pdo->prepare("

        SELECT

            DATE_FORMAT(harvest_date, '%Y-%m') AS month_key,

            DATE_FORMAT(harvest_date, '%b %Y') AS month_name,

            COUNT(*) AS total

        FROM harvests

        WHERE

            farm_id = ?

        AND harvest_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)

        GROUP BY month_key, month_name

        ORDER BY month_key ASC

    ");

    $stmt->execute([

        $farmId,

        $months

    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}


/**
 * ------------------------------------------------------------
 * Harvest Status Summary
 * ------------------------------------------------------------
 */
function getHarvestStatusSummary(
    PDO $pdo,
    int $farmId
): array {

    $stmt = $pdo->prepare("

        SELECT

            status,

            COUNT(*) AS total

        FROM harvests

        WHERE farm_id = ?

        GROUP BY status

        ORDER BY status

    ");

    $stmt->execute([

        $farmId

    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}


/**
 * ------------------------------------------------------------
 * Recent Harvests
 * ------------------------------------------------------------
 */
function getRecentHarvests(
    PDO $pdo,
    int $farmId,
    int $limit = 10
): array {

    $limit = max(1, (int)$limit);

    $stmt = $pdo->prepare("

        SELECT

            h.id,

            h.harvest_no,

            h.harvest_date,

            h.status,

            h.is_open,

            h.created_at,

            fb.batch_code,

            fb.species,

            (
                SELECT COUNT(*)
                FROM harvest_ponds hp
                WHERE hp.harvest_id = h.id
            ) AS ponds

        FROM harvests h

        INNER JOIN fish_batches fb

            ON fb.id = h.fish_batch_id

        WHERE

            h.farm_id = ?

        ORDER BY

            h.harvest_date DESC,
            h.id DESC

        LIMIT {$limit}

    ");

    $stmt->execute([

        $farmId

    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}
/**
 * ------------------------------------------------------------
 * Top Harvest Batches
 * ------------------------------------------------------------
 */
function getTopHarvestBatches(
    PDO $pdo,
    int $farmId,
    int $limit = 10
): array {

    $limit = max(1, min(100, (int)$limit));

    $stmt = $pdo->prepare("

        SELECT

            fb.id,

            fb.batch_code,

            fb.species,

            COUNT(h.id) AS harvests,

            SUM(fb.initial_count) AS stocked_fish

        FROM fish_batches fb

        INNER JOIN harvests h

            ON h.fish_batch_id = fb.id

        WHERE

            fb.farm_id = ?

        GROUP BY

            fb.id,
            fb.batch_code,
            fb.species

        ORDER BY

            harvests DESC,
            stocked_fish DESC

        LIMIT {$limit}

    ");

    $stmt->execute([$farmId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}


/**
 * ------------------------------------------------------------
 * Top Performing Ponds
 * ------------------------------------------------------------
 */
function getTopHarvestPonds(
    PDO $pdo,
    int $farmId,
    int $limit = 10
): array {

    $limit = max(1, min(100, (int)$limit));

    $stmt = $pdo->prepare("

        SELECT

            pt.id,

            pt.pond_code,

            COUNT(hp.id) AS harvests,

            SUM(ps.current_count) AS estimated_fish

        FROM ponds_tanks pt

        INNER JOIN pond_stocking ps

            ON ps.pond_id = pt.id

        INNER JOIN harvest_ponds hp

            ON hp.pond_stocking_id = ps.id

        INNER JOIN harvests h

            ON h.id = hp.harvest_id

        WHERE

            h.farm_id = ?

        GROUP BY

            pt.id,
            pt.pond_code

        ORDER BY

            estimated_fish DESC,
            harvests DESC

        LIMIT {$limit}

    ");

    $stmt->execute([$farmId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}


/**
 * ------------------------------------------------------------
 * Harvest Revenue
 * ------------------------------------------------------------
 *
 * Uses sales linked to harvests.
 *
 * Version 2:
 * Add revenue by product type,
 * customer,
 * payment method,
 * monthly trend.
 *
 * ------------------------------------------------------------
 */
function getHarvestRevenue(
    PDO $pdo,
    int $farmId
): array {

    $stmt = $pdo->prepare("

        SELECT

            COUNT(*) AS transactions,

            COALESCE(SUM(total_amount),0) AS revenue,

            COALESCE(SUM(quantity_kg),0) AS quantity_kg

        FROM sales

        WHERE

            farm_id = ?

        AND product_type = 'table_fish'

    ");

    $stmt->execute([$farmId]);

    return $stmt->fetch(PDO::FETCH_ASSOC);

}


/**
 * ------------------------------------------------------------
 * Staff Share Summary
 * ------------------------------------------------------------
 *
 * Company fish shared with staff.
 *
 * ------------------------------------------------------------
 */
function getStaffShareSummary(
    PDO $pdo,
    int $farmId
): array {

    /*
    ------------------------------------------------------------
    Version 1

    Assumes shares are recorded
    in harvest_distributions.

    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        SELECT

            COUNT(*) AS distributions,

            COALESCE(SUM(quantity_kg),0) AS quantity_kg

        FROM harvest_distributions

        WHERE

            farm_id = ?

        AND distribution_type = 'staff_share'

    ");

    $stmt->execute([$farmId]);

    return $stmt->fetch(PDO::FETCH_ASSOC);

}
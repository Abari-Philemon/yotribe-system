<?php

declare(strict_types=1);

/**
 * ============================================================
 * STOCKING HELPER
 * ============================================================
 *
 * FISH STAGE RULES
 * ------------------------------------------------------------
 * Juvenile : 10g to below 100g
 * Grow-out : 100g and above
 *
 * POND STOCKING LIMITS
 * ------------------------------------------------------------
 * Tank juvenile                  : 500 fish
 * Tank grow-out                  : 100 fish
 * Tarpaulin juvenile             : 5,000 fish
 * Tarpaulin 20ft x 20ft grow-out : 1,200 fish
 * Tarpaulin 12ft x 12ft grow-out : 500 fish
 *
 * EFFECTIVE CAPACITY
 * ------------------------------------------------------------
 * MIN(database pond capacity, stage-specific limit)
 *
 * DATABASE TABLES
 * ------------------------------------------------------------
 * ponds_tanks
 * pond_stocking
 *
 * ============================================================
 */


/**
 * ------------------------------------------------------------
 * 1. JUVENILE LIMITS BY POND TYPE
 * ------------------------------------------------------------
 *
 * Minimums are per stocking operation.
 * Maximums are the agreed juvenile limits.
 */
function getJuvenileLimits($pond_type): ?array
{
    $pond_type = strtolower(trim((string) $pond_type));

    switch ($pond_type) {
        case 'tank':
            return [
                'min' => 30,
                'max' => 500,
            ];

        case 'tarpaulin':
            return [
                'min' => 200,
                'max' => 5000,
            ];

        default:
            return null;
    }
}


/**
 * ------------------------------------------------------------
 * 2. CLASSIFY FISH STAGE BY AVERAGE WEIGHT
 * ------------------------------------------------------------
 *
 * Juvenile: 10g <= weight < 100g
 * Grow-out: weight >= 100g
 *
 * Missing, invalid, or below-10g weights are rejected.
 */
function classifyFishStage($avg_weight_g): string
{
    if (
        $avg_weight_g === null ||
        !is_numeric($avg_weight_g)
    ) {
        throw new InvalidArgumentException(
            'Average fish weight is required to determine stocking capacity.'
        );
    }

    $weight = (float) $avg_weight_g;

    if (!is_finite($weight) || $weight < 10) {
        throw new InvalidArgumentException(
            'Average fish weight must be at least 10g.'
        );
    }

    if ($weight < 100) {
        return 'juvenile';
    }

    return 'grow_out';
}


/**
 * ------------------------------------------------------------
 * 3. GET TARP AULIN GROW-OUT LIMIT
 * ------------------------------------------------------------
 *
 * Supported grow-out tarpaulin sizes:
 *
 * 20ft x 20ft = 1,200 fish
 * 12ft x 12ft = 500 fish
 *
 * Dimensions are checked first.
 * size_label is used as a fallback.
 *
 * Unknown sizes fail safely rather than guessing.
 */
function getTarpaulinGrowOutLimit(array $pond): int
{
    $length = (
        isset($pond['length_ft']) &&
        is_numeric($pond['length_ft'])
    ) ? (float) $pond['length_ft'] : 0.0;

    $width = (
        isset($pond['width_ft']) &&
        is_numeric($pond['width_ft'])
    ) ? (float) $pond['width_ft'] : 0.0;

    // Check physical dimensions.
    if (
        $length > 0 &&
        $width > 0 &&
        abs($length - 20.0) < 0.01 &&
        abs($width - 20.0) < 0.01
    ) {
        return 1200;
    }

    if (
        $length > 0 &&
        $width > 0 &&
        abs($length - 12.0) < 0.01 &&
        abs($width - 12.0) < 0.01
    ) {
        return 500;
    }

    // Fall back to the configured size label.
    $size_label = strtolower(
        trim((string) ($pond['size_label'] ?? ''))
    );

    $normalized_label = preg_replace(
        '/\s+/',
        '',
        str_replace(['×', '*'], 'x', $size_label)
    );

    if (
        $normalized_label !== null &&
        preg_match(
            '/20(?:ft|feet)?x20(?:ft|feet)?/',
            $normalized_label
        )
    ) {
        return 1200;
    }

    if (
        $normalized_label !== null &&
        preg_match(
            '/12(?:ft|feet)?x12(?:ft|feet)?/',
            $normalized_label
        )
    ) {
        return 500;
    }

    throw new RuntimeException(
        'Cannot determine grow-out capacity. Configure this tarpaulin pond with valid 20ft x 20ft or 12ft x 12ft dimensions, or a matching size_label.'
    );
}


/**
 * ------------------------------------------------------------
 * 4. GET FULL POND DATA
 * ------------------------------------------------------------
 *
 * Reads from ponds_tanks directly.
 * Does not require a matching row in sections.
 */
function getPondData(
    PDO $pdo,
    $pond_id,
    $farm_id
): array {
    if (
        !is_numeric($pond_id) ||
        (int) $pond_id < 1 ||
        !is_numeric($farm_id) ||
        (int) $farm_id < 1
    ) {
        throw new InvalidArgumentException(
            'Invalid pond or farm ID.'
        );
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            farm_id,
            section_id,
            sub_section_id,
            section_name,
            pond_code,
            pond_type,
            size_label,
            capacity,
            length_ft,
            width_ft,
            volume_liters,
            status
        FROM ponds_tanks
        WHERE id = ?
          AND farm_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        (int) $pond_id,
        (int) $farm_id,
    ]);

    $pond = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pond) {
        throw new RuntimeException(
            'Pond not found or does not belong to this farm.'
        );
    }

    if (
        strtolower((string) ($pond['status'] ?? '')) !== 'active'
    ) {
        throw new RuntimeException(
            'This pond is not active and cannot receive new stock.'
        );
    }

    $pond_type = strtolower(
        trim((string) ($pond['pond_type'] ?? ''))
    );

    if (!in_array($pond_type, ['tank', 'tarpaulin'], true)) {
        throw new RuntimeException(
            'Invalid pond type. Pond type must be tank or tarpaulin.'
        );
    }

    return $pond;
}


/**
 * ------------------------------------------------------------
 * 5. CALCULATE EFFECTIVE STAGE CAPACITY
 * ------------------------------------------------------------
 *
 * The database capacity is an additional ceiling.
 *
 * Example:
 * Database capacity = 350
 * Tank juvenile limit = 500
 * Effective capacity = 350
 */
function getStageCapacity(
    array $pond,
    $avg_weight_g
): int {
    $stage = classifyFishStage($avg_weight_g);

    $pond_type = strtolower(
        trim((string) ($pond['pond_type'] ?? ''))
    );

    if (
        !isset($pond['capacity']) ||
        !is_numeric($pond['capacity']) ||
        (int) $pond['capacity'] < 1
    ) {
        throw new RuntimeException(
            'Pond capacity must be configured as a positive fish count in the database.'
        );
    }

    $database_capacity = (int) $pond['capacity'];

    if ($pond_type === 'tank') {
        $stage_limit = (
            $stage === 'juvenile'
        ) ? 500 : 100;

    } elseif ($pond_type === 'tarpaulin') {
        if ($stage === 'juvenile') {
            $stage_limit = 5000;
        } else {
            $stage_limit = getTarpaulinGrowOutLimit($pond);
        }

    } else {
        throw new RuntimeException(
            'Unsupported pond type.'
        );
    }

    return min(
        $database_capacity,
        $stage_limit
    );
}


/**
 * ------------------------------------------------------------
 * 6. LEGACY STOCKING RATIO
 * ------------------------------------------------------------
 *
 * Kept for compatibility with existing code.
 *
 * This function is NOT used to calculate the new stocking
 * capacity. Fish weight and stage-specific limits determine it.
 */
function getStockingRatioBySection($sectionName): int
{
    $sectionName = strtolower(
        trim((string) $sectionName)
    );

    if (
        strpos($sectionName, 'juvenile') !== false ||
        strpos($sectionName, 'juv') !== false
    ) {
        return 10;
    }

    if (
        strpos($sectionName, 'grow-out') !== false ||
        strpos($sectionName, 'grow out') !== false
    ) {
        return 8;
    }

    return 10;
}


/**
 * ------------------------------------------------------------
 * 7. GET CURRENT ACTIVE STOCK IN A POND
 * ------------------------------------------------------------
 */
function getCurrentPondStock(
    PDO $pdo,
    $pond_id,
    $farm_id
): int {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(current_count), 0)
        FROM pond_stocking
        WHERE pond_id = ?
          AND farm_id = ?
          AND status = 'active'
    ");

    $stmt->execute([
        (int) $pond_id,
        (int) $farm_id,
    ]);

    return max(
        0,
        (int) $stmt->fetchColumn()
    );
}


/**
 * ------------------------------------------------------------
 * 8. CALCULATE MAX STOCK FOR A POND
 * ------------------------------------------------------------
 *
 * Average weight is mandatory so the function never guesses
 * whether the fish are juveniles or grow-out fish.
 */
function calculateMaxStockBySection(
    PDO $pdo,
    $pond_id,
    $farm_id,
    $avg_weight_g = null
): int {
    if ($avg_weight_g === null) {
        throw new InvalidArgumentException(
            'Average fish weight is required. Pass it as the fourth argument.'
        );
    }

    $pond = getPondData(
        $pdo,
        $pond_id,
        $farm_id
    );

    return getStageCapacity(
        $pond,
        $avg_weight_g
    );
}


/**
 * ------------------------------------------------------------
 * 9. VALIDATE STOCKING
 * ------------------------------------------------------------
 *
 * Checks:
 *   - Quantity is a positive whole number.
 *   - Average fish weight is valid.
 *   - Pond belongs to the selected farm.
 *   - Pond is active.
 *   - Pond type and capacity are configured correctly.
 *   - Current stock plus requested quantity does not exceed
 *     the effective capacity for the fish stage.
 *   - Legacy minimum juvenile stocking quantities are met.
 *
 * IMPORTANT:
 * This function validates a request; it does not lock database
 * rows. Call it inside a transaction after locking the pond row
 * when performing a stock operation.
 */
function validateStocking(
    PDO $pdo,
    $pond_id,
    $farm_id,
    $qty,
    $avg_weight_g = null
): bool {
    if (
        !is_numeric($qty) ||
        !is_finite((float) $qty) ||
        (float) $qty < 1
    ) {
        throw new InvalidArgumentException(
            'Stocking quantity must be a positive whole number.'
        );
    }

    if (
        (float) $qty !== (float) (int) $qty
    ) {
        throw new InvalidArgumentException(
            'Stocking quantity must be a whole number.'
        );
    }

    if ($avg_weight_g === null) {
        throw new InvalidArgumentException(
            'Average fish weight is required to validate stocking.'
        );
    }

    $qty = (int) $qty;

    $pond = getPondData(
        $pdo,
        $pond_id,
        $farm_id
    );

    $stage = classifyFishStage(
        $avg_weight_g
    );

    $max_allowed = getStageCapacity(
        $pond,
        $avg_weight_g
    );

    $current_stock = getCurrentPondStock(
        $pdo,
        $pond_id,
        $farm_id
    );

    if ($current_stock >= $max_allowed) {
        throw new RuntimeException(
            "Pond is already full for {$stage} fish. Maximum capacity: {$max_allowed}; current stock: {$current_stock}."
        );
    }

    $remaining = $max_allowed - $current_stock;

    if ($qty > $remaining) {
        throw new RuntimeException(
            "Pond capacity exceeded. Remaining capacity for {$stage} fish: {$remaining}; requested: {$qty}."
        );
    }

    // Retain the original minimum quantity rule for juveniles.
    if ($stage === 'juvenile') {
        $limits = getJuvenileLimits(
            $pond['pond_type']
        );

        if (
            $limits !== null &&
            $qty < $limits['min']
        ) {
            throw new RuntimeException(
                "Minimum juvenile stocking quantity for this pond type is {$limits['min']} fish."
            );
        }
    }

    return true;
}
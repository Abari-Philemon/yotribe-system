<?php

/**
 * -----------------------------------------
 * JUVENILE LIMITS (BY POND TYPE)
 * -----------------------------------------
 */
function getJuvenileLimits($pond_type)
{
    $pond_type = strtolower(trim($pond_type));

    switch ($pond_type) {
        case 'tank':
            return ['min' => 200, 'max' => 1000];

        case 'tarpaulin':
            return ['min' => 2000, 'max' => 8000];

        default:
            return null;
    }
}


/**
 * -----------------------------------------
 * STOCKING RATIO (LITERS PER FISH)
 * -----------------------------------------
 */
function getStockingRatioBySection($sectionName)
{
    $sectionName = strtolower($sectionName);

    // Grow-out (higher density)
    if (strpos($sectionName, 'grow') !== false || strpos($sectionName, 'go') !== false) {
        return 8; // 800L → 100 fish
    }

    // Juvenile (fallback only)
    if (strpos($sectionName, 'juv') !== false) {
        return 10;
    }

    // Default
    return 10;
}


/**
 * -----------------------------------------
 * GET FULL POND DATA (SAFE)
 * -----------------------------------------
 */
function getPondData($pdo, $pond_id, $farm_id)
{
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.volume_liters,
            p.capacity,
            p.pond_type,
            p.section_id,
            s.name AS section_name
        FROM ponds_tanks p
        JOIN sections s ON s.id = p.section_id
        WHERE p.id = ? AND p.farm_id = ?
    ");

    $stmt->execute([$pond_id, $farm_id]);
    $pond = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pond) {
        throw new Exception("Pond not found");
    }

    return $pond;
}


/**
 * -----------------------------------------
 * CURRENT STOCK IN POND
 * -----------------------------------------
 */
function getCurrentPondStock($pdo, $pond_id, $farm_id)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(current_count),0)
        FROM pond_stocking
        WHERE pond_id = ?
        AND farm_id = ?
        AND status = 'active'
    ");

    $stmt->execute([$pond_id, $farm_id]);

    return (int) $stmt->fetchColumn();
}


/**
 * -----------------------------------------
 * MAX STOCK CALCULATION (CORE ENGINE)
 * -----------------------------------------
 */
function calculateMaxStockBySection($pdo, $pond_id, $farm_id)
{
    $pond = getPondData($pdo, $pond_id, $farm_id);

    $section = strtolower($pond['section_name']);

    /**
     * BASE VOLUME RULE
     */
    $ratio = getStockingRatioBySection($section);
    $max_by_volume = floor($pond['volume_liters'] / $ratio);

    /**
     * JUVENILE RULE (STRICT RANGE)
     */
    if (strpos($section, 'juv') !== false) {

        $limits = getJuvenileLimits($pond['pond_type']);

        if ($limits) {
            return min(
                $max_by_volume,
                (int)$pond['capacity'],
                $limits['max']
            );
        }
    }

    /**
     * DEFAULT (GROW-OUT)
     */
    return min($max_by_volume, (int)$pond['capacity']);
}


/**
 * -----------------------------------------
 * VALIDATE STOCKING (REUSABLE)
 * -----------------------------------------
 */
function validateStocking($pdo, $pond_id, $farm_id, $qty)
{
    if ($qty <= 0) {
        throw new Exception("Invalid quantity");
    }

    $pond = getPondData($pdo, $pond_id, $farm_id);

    $max_allowed   = calculateMaxStockBySection($pdo, $pond_id, $farm_id);
    $current_stock = getCurrentPondStock($pdo, $pond_id, $farm_id);

    /**
     * FULL CHECK
     */
    if ($current_stock >= $max_allowed) {
        throw new Exception("Pond is already full");
    }

    $remaining = $max_allowed - $current_stock;

    if ($qty > $remaining) {
        throw new Exception("Pond limit exceeded. Max allowed: {$remaining}");
    }

    /**
     * MINIMUM (JUVENILE ONLY)
     */
    if (strpos(strtolower($pond['section_name']), 'juv') !== false) {

        $limits = getJuvenileLimits($pond['pond_type']);

        if ($limits && $qty < $limits['min']) {
            throw new Exception("Minimum stocking for this pond is {$limits['min']}");
        }
    }

    return true;
}
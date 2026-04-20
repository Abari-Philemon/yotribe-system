<?php

function generatePondCode(PDO $pdo, int $farm_id, int $section_id, int $sub_section_id): string
{
    // 1. Get section code
    $stmt = $pdo->prepare("SELECT code FROM sections WHERE id = ? AND farm_id = ?");
    $stmt->execute([$section_id, $farm_id]);
    $section = $stmt->fetch();

    if (!$section) {
        throw new Exception("Invalid section");
    }

    // 2. Get sub-section code
    $stmt = $pdo->prepare("
        SELECT code 
        FROM sub_sections 
        WHERE id = ? AND section_id = ? AND farm_id = ?
    ");
    $stmt->execute([$sub_section_id, $section_id, $farm_id]);
    $sub = $stmt->fetch();

    if (!$sub) {
        throw new Exception("Invalid sub-section");
    }

    $prefix = $sub['code']; // e.g GO-03A

    // 3. Find last sequence
    $stmt = $pdo->prepare("
        SELECT pond_code 
        FROM ponds_tanks
        WHERE farm_id = ?
        AND sub_section_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$farm_id, $sub_section_id]);
    $last = $stmt->fetchColumn();

    $next = 1;

    if ($last) {
        // Extract last number
        if (preg_match('/-(\d+)$/', $last, $m)) {
            $next = (int)$m[1] + 1;
        }
    }

    // Format sequence
    $seq = str_pad($next, 2, '0', STR_PAD_LEFT);

    return "{$prefix}-{$seq}";
}
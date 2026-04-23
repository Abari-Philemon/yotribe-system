<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('feeding');

/**
 * LOAD ALL ACTIVE BATCHES
 */
$stmt = $pdo->query("
    SELECT 
        ps.id AS stock_id,
        ps.pond_id,
        ps.batch_id,
        p.pond_code,
        fb.batch_code
    FROM pond_stocking ps
    JOIN ponds_tanks p ON p.id = ps.pond_id
    JOIN fish_batches fb ON fb.id = ps.batch_id
    WHERE ps.status IN ('active','harvested')
");
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<h2>Scientific FCR (Growth-Based)</h2>

<table border="1" cellpadding="8">
<tr>
    <th>Pond</th>
    <th>Batch</th>
    <th>Total Feed (kg)</th>
    <th>Growth Gain (kg)</th>
    <th>Mortality Loss (kg)</th>
    <th>Adjusted Gain (kg)</th>
    <th>FCR</th>
</tr>

<?php foreach ($batches as $b): 

    /**
     * 1. TOTAL FEED
     */
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantity_kg),0)
        FROM feeding_logs
        WHERE pond_id = ? AND batch_id = ?
    ");
    $stmt->execute([$b['pond_id'], $b['batch_id']]);
    $total_feed = (float)$stmt->fetchColumn();

    /**
     * 2. GROWTH TRACKING (TRUE BIOMASS GAIN)
     */
    $stmt = $pdo->prepare("
        SELECT total_count, avg_weight_g
        FROM fish_growth_logs
        WHERE pond_id = ? AND batch_id = ?
        ORDER BY recorded_at ASC
    ");
    $stmt->execute([$b['pond_id'], $b['batch_id']]);
    $growth_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $growth_gain = 0;

    for ($i = 1; $i < count($growth_logs); $i++) {

        $prev = $growth_logs[$i - 1];
        $curr = $growth_logs[$i];

        $prev_biomass = ($prev['total_count'] * $prev['avg_weight_g']) / 1000;
        $curr_biomass = ($curr['total_count'] * $curr['avg_weight_g']) / 1000;

        $diff = $curr_biomass - $prev_biomass;

        if ($diff > 0) {
            $growth_gain += $diff;
        }
    }

    /**
     * 3. MORTALITY BIOMASS LOSS (ACCURATE)
     */
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(dead_count * avg_weight_g),0)
        FROM mortality_logs
        WHERE pond_id = ? AND farm_id = ?
    ");
    $stmt->execute([$b['pond_id'], $_SESSION['farm_id']]);
    $mortality_loss = ((float)$stmt->fetchColumn()) / 1000;

    /**
     * 4. ADJUSTED GAIN
     */
    $adjusted_gain = $growth_gain + $mortality_loss;

    /**
     * 5. FCR
     */
    $fcr = ($adjusted_gain > 0)
        ? $total_feed / $adjusted_gain
        : 0;

?>

<tr>
    <td><?= $b['pond_code'] ?></td>
    <td><?= $b['batch_code'] ?></td>
    <td><?= number_format($total_feed,2) ?></td>
    <td><?= number_format($growth_gain,2) ?></td>
    <td><?= number_format($mortality_loss,2) ?></td>
    <td><?= number_format($adjusted_gain,2) ?></td>
    <td><strong><?= number_format($fcr,2) ?></strong></td>
</tr>

<?php endforeach; ?>

</table>
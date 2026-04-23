<?php
require '../../config/database.php';
require '../../helpers/growth_helper.php';

$pond_id  = (int)$_GET['pond_id'];
$batch_id = (int)$_GET['batch_id'];

/**
 * CURRENT WEIGHT
 */
$stmt = $pdo->prepare("
    SELECT avg_weight_g 
    FROM pond_stocking
    WHERE pond_id = ? AND batch_id = ?
");
$stmt->execute([$pond_id, $batch_id]);

$current = $stmt->fetchColumn();

/**
 * PREDICTION + ALERT
 */
$predicted = predictNextWeight($pdo, $pond_id, $batch_id);
$alert     = growthAlert($pdo, $pond_id, $batch_id);

echo json_encode([
    'current_weight' => $current ? round($current,2) : null,
    'predicted_weight' => $predicted ? round($predicted,2) : null,
    'alert' => $alert
]);
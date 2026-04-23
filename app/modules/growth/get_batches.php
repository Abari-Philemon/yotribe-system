<?php
require '../../config/database.php';
require '../../middleware/farm_guard.php';

$farm_id = farm_id();
$pond_id = (int)$_GET['pond_id'];

$stmt = $pdo->prepare("
    SELECT ps.batch_id, fb.batch_code, ps.current_count
    FROM pond_stocking ps
    JOIN fish_batches fb ON fb.id = ps.batch_id
    WHERE ps.pond_id = ? AND ps.farm_id = ? AND ps.status = 'active'
");
$stmt->execute([$pond_id, $farm_id]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
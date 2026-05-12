<?php 
$from = (int)$_POST['from_pond'];
$to   = (int)$_POST['to_pond'];
$qty  = (int)$_POST['quantity'];
$batch_id = (int)$_POST['batch_id'];

$pdo->beginTransaction();

try {

    // subtract from source
    $pdo->prepare("
        UPDATE pond_inventory
        SET quantity = quantity - ?
        WHERE pond_id = ? AND batch_id = ?
    ")->execute([$qty, $from, $batch_id]);

    // add to destination
    $pdo->prepare("
        INSERT INTO pond_inventory (farm_id, pond_id, batch_id, quantity)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ")->execute([farm_id(), $to, $batch_id, $qty]);

    // log transfer
    $pdo->prepare("
        INSERT INTO transfer_logs (farm_id, from_pond_id, to_pond_id, batch_id, quantity, transfer_date)
        VALUES (?, ?, ?, ?, ?, CURDATE())
    ")->execute([farm_id(), $from, $to, $batch_id, $qty]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
}
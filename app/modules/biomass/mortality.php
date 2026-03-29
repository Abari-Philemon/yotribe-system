<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('biomass');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();

    $pond_id = $_POST['pond_id'];
    $dead = (int)$_POST['dead_count'];
    $avg_weight = (float)$_POST['avg_weight'];

    $loss = $dead * $avg_weight;

    // Log mortality
    $pdo->prepare("
        INSERT INTO mortality_logs
        (date, farm_id, pond_id, dead_count, suspected_cause, action_taken, reported_by)
        VALUES (CURDATE(), ?, ?, ?, ?, ?, ?)
    ")->execute([
        $_POST['farm_id'],
        $pond_id,
        $dead,
        $_POST['cause'],
        $_POST['action'],
        $_SESSION['staff_id']
    ]);

    // Reduce biomass
    $pdo->prepare("
        UPDATE fish_inventory
        SET estimated_weight_kg = estimated_weight_kg - ?
        WHERE pond_id = ?
    ")->execute([$loss, $pond_id]);

    $pdo->commit();
    header("Location: index.php");
    exit;
}

$ponds = $pdo->query("SELECT id, pond_code FROM ponds_tanks")->fetchAll();
?>

<h2>Record Mortality</h2>
<form method="post">
    Farm ID: <input name="farm_id"><br>
    Pond:
    <select name="pond_id">
        <?php foreach ($ponds as $p): ?>
            <option value="<?= $p['id'] ?>"><?= $p['pond_code'] ?></option>
        <?php endforeach; ?>
    </select><br>
    Dead Count: <input type="number" name="dead_count"><br>
    Avg Weight per Fish (kg): <input type="number" step="0.01" name="avg_weight"><br>
    Suspected Cause: <input name="cause"><br>
    Action Taken: <textarea name="action"></textarea><br>
    <button>Save</button>
</form>

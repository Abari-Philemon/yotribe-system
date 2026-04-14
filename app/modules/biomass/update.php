<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';


authorize('biomass');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        UPDATE fish_inventory 
        SET estimated_weight_kg=?, last_updated=NOW()
        WHERE pond_id=?
    ");
    $stmt->execute([$_POST['weight'], $_POST['pond_id']]);
    header("Location: index.php");
    exit;
}

$ponds = $pdo->query("SELECT id, pond_code FROM ponds_tanks")->fetchAll();
?>

<h2>Update Pond Biomass</h2>
<form method="post">
    Pond:
    <select name="pond_id">
        <?php foreach ($ponds as $p): ?>
            <option value="<?= $p['id'] ?>"><?= $p['pond_code'] ?></option>
        <?php endforeach; ?>
    </select><br>
    Estimated Weight (kg):
    <input type="number" step="0.01" name="weight"><br>
    <button>Update</button>
</form>

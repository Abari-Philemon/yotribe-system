<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * LOAD PONDS (ONLY ACTIVE + EMPTY OR AVAILABLE)
 */
$stmt = $pdo->prepare("
    SELECT id, pond_code, capacity
    FROM ponds_tanks
    WHERE farm_id = ?
    AND status = 'active'
    ORDER BY pond_code
");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * HANDLE SUBMIT
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF');
    }

    $pond_id = (int)$_POST['pond_id'];
    $qty     = (int)$_POST['quantity'];
    $date    = $_POST['stocking_date'];

    if ($qty <= 0) {
        die('Invalid quantity');
    }

    $pdo->beginTransaction();

    try {

        // CHECK pond capacity
        $stmt = $pdo->prepare("
            SELECT capacity,
            COALESCE(SUM(current_count),0) AS current_stock
            FROM ponds_tanks p
            LEFT JOIN pond_stocking ps ON ps.pond_id = p.id AND ps.status='active'
            WHERE p.id = ? AND p.farm_id = ?
        ");
        $stmt->execute([$pond_id, $farm_id]);
        $pond = $stmt->fetch();

        if (!$pond) {
            throw new Exception("Invalid pond");
        }

        if (($pond['current_stock'] + $qty) > $pond['capacity']) {
            throw new Exception("Exceeds pond capacity");
        }

        // CREATE BATCH
        $batch_code = 'BATCH-' . time();

        $pdo->prepare("
            INSERT INTO fish_batches 
            (farm_id, batch_code, initial_count, current_count, stocking_date)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$farm_id, $batch_code, $qty, $qty, $date]);

        $batch_id = $pdo->lastInsertId();

        // ASSIGN TO POND
        $pdo->prepare("
            INSERT INTO pond_stocking
            (farm_id, pond_id, batch_id, stocked_count, current_count, stocking_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$farm_id, $pond_id, $batch_id, $qty, $qty, $date]);

        // LOG MOVEMENT
        $pdo->prepare("
            INSERT INTO stock_movements
            (farm_id, type, to_pond_id, batch_id, quantity, movement_date)
            VALUES (?, 'stocking', ?, ?, ?, ?)
        ")->execute([$farm_id, $pond_id, $batch_id, $qty, $date]);

        $pdo->commit();

        header("Location: index.php?success=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Stock Fish</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

<h4>Stock Fish</h4>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="post" class="card p-3">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="mb-3">
<label>Pond</label>
<select name="pond_id" class="form-select" required>
<option value="">Select Pond</option>
<?php foreach ($ponds as $p): ?>
<option value="<?= $p['id'] ?>">
<?= $p['pond_code'] ?> (Cap: <?= $p['capacity'] ?>)
</option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label>Quantity</label>
<input type="number" name="quantity" class="form-control" required>
</div>

<div class="mb-3">
<label>Date</label>
<input type="date" name="stocking_date" class="form-control" required>
</div>

<button class="btn btn-primary">Stock Fish</button>

</form>
</body>
</html>
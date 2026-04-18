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
 * LOAD PONDS
 */
$stmt = $pdo->prepare("
    SELECT id, pond_code 
    FROM ponds_tanks
    WHERE farm_id = ? AND status = 'active'
    ORDER BY pond_code
");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll();

/**
 * HANDLE FORM
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF');
    }

    $from_pond = (int)$_POST['from_pond'];
    $to_pond   = (int)$_POST['to_pond'];
    $batch_id  = (int)$_POST['batch_id'];
    $qty       = (int)$_POST['quantity'];

    if ($from_pond === $to_pond) {
        die("Cannot transfer to same pond");
    }

    if ($qty <= 0) {
        die("Invalid quantity");
    }

    $pdo->beginTransaction();

    try {

        // CHECK available fish in source pond
        $stmt = $pdo->prepare("
            SELECT current_count 
            FROM pond_stocking
            WHERE pond_id = ? AND batch_id = ? AND farm_id = ? AND status='active'
            FOR UPDATE
        ");
        $stmt->execute([$from_pond, $batch_id, $farm_id]);
        $source = $stmt->fetch();

        if (!$source) {
            throw new Exception("Batch not found in source pond");
        }

        if ($qty > $source['current_count']) {
            throw new Exception("Not enough fish in source pond");
        }

        // REDUCE SOURCE
        $pdo->prepare("
            UPDATE pond_stocking 
            SET current_count = current_count - ?
            WHERE pond_id = ? AND batch_id = ?
        ")->execute([$qty, $from_pond, $batch_id]);

        // ADD TO DESTINATION (check if batch already exists there)
        $stmt = $pdo->prepare("
            SELECT id, current_count FROM pond_stocking
            WHERE pond_id = ? AND batch_id = ? AND status='active'
        ");
        $stmt->execute([$to_pond, $batch_id]);
        $dest = $stmt->fetch();

        if ($dest) {
            // UPDATE existing
            $pdo->prepare("
                UPDATE pond_stocking 
                SET current_count = current_count + ?
                WHERE id = ?
            ")->execute([$qty, $dest['id']]);
        } else {
            // INSERT new
            $pdo->prepare("
                INSERT INTO pond_stocking
                (farm_id, pond_id, batch_id, stocked_count, current_count, stocking_date)
                VALUES (?, ?, ?, ?, ?, CURDATE())
            ")->execute([$farm_id, $to_pond, $batch_id, $qty, $qty]);
        }

        // LOG MOVEMENT
        $pdo->prepare("
            INSERT INTO stock_movements
            (farm_id, type, from_pond_id, to_pond_id, batch_id, quantity, movement_date)
            VALUES (?, 'transfer', ?, ?, ?, ?, CURDATE())
        ")->execute([$farm_id, $from_pond, $to_pond, $batch_id, $qty]);

        $pdo->commit();

        header("Location: index.php?success=transfer");
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
<title>Transfer Fish</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

<h4>Transfer Fish</h4>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="post" class="card p-3">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="mb-3">
<label>From Pond</label>
<select name="from_pond" class="form-select" required>
<?php foreach ($ponds as $p): ?>
<option value="<?= $p['id'] ?>"><?= $p['pond_code'] ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label>To Pond</label>
<select name="to_pond" class="form-select" required>
<?php foreach ($ponds as $p): ?>
<option value="<?= $p['id'] ?>"><?= $p['pond_code'] ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label>Batch ID</label>
<input type="number" name="batch_id" class="form-control" required>
</div>

<div class="mb-3">
<label>Quantity</label>
<input type="number" name="quantity" class="form-control" required>
</div>

<button class="btn btn-primary">Transfer</button>

</form>
</body>
</html>
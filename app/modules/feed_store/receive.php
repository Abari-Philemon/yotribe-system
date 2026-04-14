<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';


authorize('feed_store');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feed_type = $_POST['feed_type'];
    $batch = $_POST['batch_no'];
    $qty = (float)$_POST['quantity'];
    $cost = (float)$_POST['cost'];

    $stmt = $pdo->prepare("
        INSERT INTO feed_store (feed_type, batch_no, quantity_kg, cost_per_kg)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            quantity_kg = quantity_kg + VALUES(quantity_kg),
            cost_per_kg = VALUES(cost_per_kg)
    ");
    $stmt->execute([$feed_type, $batch, $qty, $cost]);

    header("Location: index.php");
    exit;
}
?>

<h2>Receive Feed</h2>
<form method="post">
    Feed Type: <input name="feed_type" required><br>
    Batch No: <input name="batch_no" required><br>
    Quantity (kg): <input type="number" step="0.01" name="quantity" required><br>
    Cost per kg: <input type="number" step="0.01" name="cost"><br>
    <button type="submit">Save</button>
</form>

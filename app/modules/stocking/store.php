require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id  = farm_id();
$staff_id = $_SESSION['staff_id'];

$batch_id = (int)$_POST['batch_id'];
$pond_id  = (int)$_POST['pond_id'];
$qty      = (int)$_POST['quantity'];
$weight   = (float)$_POST['avg_weight'];

$pdo->beginTransaction();

try {

    // 1. Insert stocking log
    $stmt = $pdo->prepare("
        INSERT INTO stocking_logs
        (farm_id, batch_id, pond_id, quantity, avg_weight, stocking_date, created_by)
        VALUES (?, ?, ?, ?, ?, CURDATE(), ?)
    ");
    $stmt->execute([$farm_id, $batch_id, $pond_id, $qty, $weight, $staff_id]);

    // 2. Update pond inventory
    $stmt = $pdo->prepare("
        SELECT id, quantity 
        FROM pond_inventory
        WHERE pond_id = ? AND batch_id = ?
    ");
    $stmt->execute([$pond_id, $batch_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE pond_inventory
            SET quantity = quantity + ?
            WHERE id = ?
        ");
        $stmt->execute([$qty, $existing['id']]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO pond_inventory (farm_id, pond_id, batch_id, quantity, avg_weight)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$farm_id, $pond_id, $batch_id, $qty, $weight]);
    }

    // 3. Update batch remaining
    $stmt = $pdo->prepare("
        UPDATE batches
        SET current_quantity = current_quantity - ?
        WHERE id = ?
    ");
    $stmt->execute([$qty, $batch_id]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Stocking failed");
}

header("Location: index.php");
<?php
require '../../middleware/auth_guard.php';
require '../../config/database.php';

// Only manager or owner can access
require_role(['manager','owner']);

$farm_id = $_SESSION['farm_id'];
$message = '';

// Process form
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $pond_id = $_POST['pond_id'];
    $product_type = $_POST['product_type'];
    $quantity_kg = $_POST['quantity_kg'];
    $unit_price = $_POST['unit_price'];
    $payment_method = $_POST['payment_method'];
    $received_into = $_POST['received_into'];
    $customer_name = $_POST['customer_name'];

    $total_amount = $quantity_kg * $unit_price;

    // Insert into sales
    $stmt = $pdo->prepare("INSERT INTO sales (date, farm_id, pond_id, product_type, quantity_kg, unit_price, total_amount, payment_method, received_into, customer_name, recorded_by) VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$farm_id, $pond_id, $product_type, $quantity_kg, $unit_price, $total_amount, $payment_method, $received_into, $customer_name, $_SESSION['staff_id']]);

    // Auto-update fish inventory if table fish
    if($product_type == 'table_fish'){
        $stmt = $pdo->prepare("UPDATE fish_inventory SET estimated_weight_kg = estimated_weight_kg - ? WHERE pond_id = ?");
        $stmt->execute([$quantity_kg, $pond_id]);
    }

    // Update cash ledger
    $stmt = $pdo->prepare("SELECT SUM(total_amount) FROM sales WHERE farm_id = ?");
    $stmt->execute([$farm_id]);
    $balance = $stmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO cash_ledger (date, type, source, reference_id, amount, balance_after) VALUES (CURDATE(), 'inflow', 'sale', LAST_INSERT_ID(), ?, ?)");
    $stmt->execute([$total_amount, $balance]);

    $message = "Sale recorded successfully!";
}

// Fetch ponds for dropdown
$stmt = $pdo->prepare("SELECT * FROM ponds_tanks WHERE farm_id = ?");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll();
?>

<h2>Record Sale</h2>
<?php if($message) echo "<p style='color:green;'>$message</p>"; ?>
<form method="POST">
    <label>Pond:</label>
    <select name="pond_id" required>
        <?php foreach($ponds as $pond): ?>
            <option value="<?= $pond['id'] ?>"><?= $pond['pond_code'] ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Product Type:</label>
    <select name="product_type" required>
        <option value="table_fish">Table Fish</option>
        <option value="juvenile">Juvenile</option>
        <option value="maggot">Maggot</option>
        <option value="feed">Feed</option>
    </select><br><br>

    <label>Quantity (kg):</label>
    <input type="number" step="0.01" name="quantity_kg" required><br><br>

    <label>Unit Price:</label>
    <input type="number" step="0.01" name="unit_price" required><br><br>

    <label>Payment Method:</label>
    <select name="payment_method">
        <option value="cash">Cash</option>
        <option value="transfer">Transfer</option>
        <option value="pos">POS</option>
    </select><br><br>

    <label>Received Into:</label>
    <select name="received_into">
        <option value="cash">Cash</option>
        <option value="bank">Bank</option>
    </select><br><br>

    <label>Customer Name:</label>
    <input type="text" name="customer_name" required><br><br>

    <button type="submit">Record Sale</button>
</form>

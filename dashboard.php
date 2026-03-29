<?php
require 'app/middleware/auth_guard.php';
require 'app/config/database.php';

// Require login for all roles
if(!isset($_SESSION['staff_id'])){
    header("Location: app/auth/login.php");
    exit;
}

// Quick stats
$farm_id = $_SESSION['farm_id'];

// Total fish stock (kg)
$stmt = $pdo->prepare("SELECT SUM(estimated_weight_kg) as total_stock FROM fish_inventory WHERE farm_id = ?");
$stmt->execute([$farm_id]);
$total_stock = $stmt->fetchColumn();

// Total feed in store
$stmt = $pdo->prepare("SELECT SUM(quantity_kg) as total_feed FROM feed_store");
$stmt->execute();
$total_feed = $stmt->fetchColumn();

// Total sales this month
$stmt = $pdo->prepare("SELECT SUM(total_amount) as total_sales FROM sales WHERE farm_id = ? AND MONTH(date) = MONTH(CURDATE())");
$stmt->execute([$farm_id]);
$total_sales = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Yotribe Agro</title>
</head>
<body>
<h1>Welcome, <?= $_SESSION['full_name']; ?>!</h1>
<p>Role: <?= $_SESSION['role']; ?></p>

<h3>Quick Stats:</h3>
<ul>
    <li>Total Fish Stock (kg): <?= $total_stock ?: 0 ?></li>
    <li>Total Feed in Store (kg): <?= $total_feed ?: 0 ?></li>
    <li>Total Sales This Month (₦): <?= $total_sales ?: 0 ?></li>
</ul>

<a href="app/auth/logout.php">Logout</a>

<h3>Modules:</h3>
<ul>
    <li><a href="app/modules/feed_store/index.php">Feed Store</a></li>
    <li><a href="app/modules/feeding/index.php">Feeding Logs</a></li>
    <li><a href="app/modules/sales/index.php">Sales</a></li>
    <li><a href="app/modules/reports/index.php">Reports</a></li>
</ul>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<canvas id="stockChart" width="400" height="150"></canvas>

<script>
let TOTAL_FISH_STOCK = <?= $total_stock ?: 0 ?>;
let TOTAL_FEED_STOCK = <?= $total_feed ?: 0 ?>;
</script>
<script src="public/js/dashboard_charts.js"></script>

</body>
</html>

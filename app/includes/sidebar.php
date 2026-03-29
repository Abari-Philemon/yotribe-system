<?php
require_once __DIR__ . '/../helpers/permission.php';
?>

<ul class="sidebar-menu">

<?php if (canAccess('dashboard')): ?>
<li><a href="/dashboard.php">Dashboard</a></li>
<?php endif; ?>

<?php if (canAccess('staff')): ?>
<li><a href="/app/modules/staff/manage.php">Staff</a></li>
<?php endif; ?>

<?php if (canAccess('inventory')): ?>
<li><a href="/app/modules/inventory/index.php">Inventory</a></li>
<?php endif; ?>

<?php if (canAccess('sales')): ?>
<li><a href="/app/modules/sales/index.php">Sales</a></li>
<?php endif; ?>

<?php if (canAccess('cash')): ?>
<li><a href="/app/modules/cash/index.php">Cash Ledger</a></li>
<?php endif; ?>

<?php if (canAccess('reports')): ?>
<li><a href="/app/modules/reports/index.php">Reports</a></li>
<?php endif; ?>

</ul>

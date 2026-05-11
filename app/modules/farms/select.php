<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * Session
 */
$role     = $_SESSION['role'] ?? '';
$staff_id = $_SESSION['staff_id'] ?? 0;

/**
 * Allowed roles
 */
if (!in_array($role, ['super_admin', 'owner', 'manager', 'production'])) {
    http_response_code(403);
    exit('Unauthorized access');
}

/**
 * Fetch farms based on role
 */
switch ($role) {

    case 'super_admin':
        $stmt = $pdo->query("
            SELECT id, name, location
            FROM farms
            ORDER BY name ASC
        ");
        break;

    case 'owner':
        $stmt = $pdo->prepare("
            SELECT id, name, location
            FROM farms
            WHERE owner_id = ?
            ORDER BY name ASC
        ");
        $stmt->execute([$staff_id]);
        break;

    case 'manager':
        $stmt = $pdo->prepare("
            SELECT f.id, f.name, f.location
            FROM farms f
            INNER JOIN staff s ON s.farm_id = f.id
            WHERE s.id = ?
            ORDER BY f.name ASC
        ");
        $stmt->execute([$staff_id]);
        break;

    case 'production':
        $stmt = $pdo->prepare("
            SELECT f.id, f.name, f.location
            FROM farms f
            INNER JOIN staff s ON s.farm_id = f.id
            WHERE s.id = ?
            ORDER BY f.name ASC
        ");
        $stmt->execute([$staff_id]);
        break;

    default:
        http_response_code(403);
        exit('Unauthorized access');
}

$farms = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Auto-select if only one farm
 */
if (count($farms) === 1) {
    $_SESSION['active_farm_id']   = (int)$farms[0]['id'];
    $_SESSION['active_farm_name'] = $farms[0]['name'];

    header("Location: /yotribe-system/app/modules/dashboard/index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Select Farm | Yotribe Agro</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/yotribe-system/public/css/custom.css">
</head>

<body class="bg-light">

<div class="container mt-5">
<div class="row justify-content-center">
<div class="col-md-6">

<div class="card shadow">
<div class="card-body">

<div class="text-center mb-4">
    <img src="/yotribe-system/public/uploads/logo8.png" style="max-height:120px" class="mb-3">
    <h4>Select Active Farm</h4>
    <p class="text-muted">Choose the farm you want to manage</p>
</div>

<?php if (empty($farms)): ?>
    <div class="alert alert-warning text-center">
        No farms assigned to your account.
    </div>
<?php else: ?>

<form method="post" action="switch.php">

<div class="list-group mb-3">

<?php foreach ($farms as $farm): ?>
    <label class="list-group-item d-flex justify-content-between align-items-center">
        <div>
            <strong><?= htmlspecialchars($farm['name']) ?></strong><br>
            <small class="text-muted"><?= htmlspecialchars($farm['location'] ?? '—') ?></small>
        </div>
        <input type="radio" name="farm_id" value="<?= (int)$farm['id'] ?>" required>
    </label>
<?php endforeach; ?>

</div>

<button class="btn btn-primary w-100">
    Continue to Dashboard
</button>

</form>

<?php endif; ?>

</div>
</div>

</div>
</div>
</div>

</body>
</html>
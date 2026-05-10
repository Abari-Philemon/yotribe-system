<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * FARM CONTEXT
 */
$farm_id   = farm_id();
$farm_name = farm_name();

/**
 * FILTER INPUTS
 */
$section_id     = $_GET['section_id'] ?? '';
$sub_section_id = $_GET['sub_section_id'] ?? '';
$type           = $_GET['type'] ?? '';
$status         = $_GET['status'] ?? '';

/**
 * BASE QUERY
 */
$sql = "
    SELECT 
        p.*,
        s.name  AS section_name,
        ss.name AS sub_section_name
    FROM ponds_tanks p
    LEFT JOIN sections s ON s.id = p.section_id
    LEFT JOIN sub_sections ss ON ss.id = p.sub_section_id
    WHERE p.farm_id = :farm_id
";

$params = ['farm_id' => $farm_id];

/**
 * APPLY FILTERS
 */
if (!empty($section_id)) {
    $sql .= " AND p.section_id = :section_id";
    $params['section_id'] = $section_id;
}

if (!empty($sub_section_id)) {
    $sql .= " AND p.sub_section_id = :sub_section_id";
    $params['sub_section_id'] = $sub_section_id;
}

if (!empty($type)) {
    $sql .= " AND p.pond_type = :type";
    $params['type'] = $type;
}

if (!empty($status)) {
    $sql .= " AND p.status = :status";
    $params['status'] = $status;
}

$sql .= " ORDER BY p.pond_code ASC";

/**
 * EXECUTE
 */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * LOAD FILTER DATA
 */

// Sections (per farm)
$sections = $pdo->prepare("
    SELECT id, name 
    FROM sections
    WHERE farm_id = ?
    ORDER BY name
");
$sections->execute([$farm_id]);
$sections = $sections->fetchAll(PDO::FETCH_ASSOC);

// Sub-sections (per farm)
$sub_sections = $pdo->prepare("
    SELECT id, name 
    FROM sub_sections
    WHERE farm_id = ?
    ORDER BY name
");
$sub_sections->execute([$farm_id]);
$sub_sections = $sub_sections->fetchAll(PDO::FETCH_ASSOC);

// Pond Types (per farm only)
$types = $pdo->prepare("
    SELECT DISTINCT pond_type 
    FROM ponds_tanks 
    WHERE farm_id = ?
    ORDER BY pond_type
");
$types->execute([$farm_id]);
$types = $types->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>


<div class="container mt-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Ponds & Tanks - <?= htmlspecialchars($farm_name) ?></h4>

        <div>
            <a href="/yotribe-system/app/modules/farms/select.php" class="btn btn-outline-secondary btn-sm">
                Switch Farm
            </a>
            <a href="create.php" class="btn btn-primary btn-sm">
                + Add Pond
            </a>
            <a href="bulk_create.php" class="btn btn-primary btn-sm">
                + Create Bulk
            </a>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2">

                <!-- Section -->
                <div class="col-md-3">
                    <select name="section_id" class="form-select">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $section_id == $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Sub-section -->
                <div class="col-md-3">
                    <select name="sub_section_id" class="form-select">
                        <option value="">All Sub-sections</option>
                        <?php foreach ($sub_sections as $ss): ?>
                            <option value="<?= $ss['id'] ?>" <?= $sub_section_id == $ss['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ss['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Type -->
                <div class="col-md-2">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t ?>" <?= $type == $t ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status -->
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="maintenance" <?= $status == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                    </select>
                </div>

                <!-- Submit -->
                <div class="col-md-2 d-grid">
                    <button class="btn btn-dark">Filter</button>
                </div>

            </form>
        </div>
    </div>

    <!-- TABLE -->
    <div class="card shadow">
        <div class="card-body table-responsive">

            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Code</th>
                        <th>Section</th>
                        <th>Sub-section</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Capacity</th>
                        <th>Dimensions</th>
                        <th>Volume (L)</th>
                        <th>Status</th>
                        <th width="140">Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($ponds)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted">No ponds found</td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($ponds as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['pond_code']) ?></strong></td>
                        <td><?= htmlspecialchars($p['section_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['sub_section_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['pond_type']) ?></td>
                        <td><?= htmlspecialchars($p['size_label']) ?></td>
                        <td><?= number_format($p['capacity']) ?></td>

                        <td>
                        <?php if (!empty($p['length_ft']) && !empty($p['width_ft'])): ?>
                            <?= $p['length_ft'] ?> x <?= $p['width_ft'] ?> ft
                        <?php else: ?>
                            —
                        <?php endif; ?>
                        </td>

                        <td><?= number_format($p['volume_liters']) ?></td>

                        <td>
                            <span class="badge bg-<?=
                                $p['status'] === 'active' ? 'success' :
                                ($p['status'] === 'maintenance' ? 'warning' : 'secondary')
                            ?>">
                                <?= ucfirst($p['status']) ?>
                            </span>
                        </td>

                        <td>
                            <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning">Edit</a>

                            <a href="delete.php?id=<?= $p['id'] ?>"
                               onclick="return confirm('Delete this pond?')"
                               class="btn btn-sm btn-danger">
                               Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>
            </table>

        </div>
    </div>

</div>

</body>
</html>
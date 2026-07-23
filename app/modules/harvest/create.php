<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Harvest Management
 * Create Harvest
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

require_permission('harvest');

/*
|--------------------------------------------------------------------------
| Context
|--------------------------------------------------------------------------
*/

$farm_id  = farm_id();
$staff_id = $_SESSION['staff_id'];

$page_title = 'Create Harvest';
$module     = 'harvest';

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}

/*
|--------------------------------------------------------------------------
| Generate Harvest Number
|--------------------------------------------------------------------------
|
| NOTE:
| This will later move into harvest_helper.php
|
|--------------------------------------------------------------------------
*/

$today = date('Ymd');

$stmt = $pdo->prepare("
    SELECT COUNT(*) + 1
    FROM harvests
    WHERE harvest_date = CURDATE()
");

$stmt->execute();

$sequence = str_pad(

    (string)$stmt->fetchColumn(),

    4,

    '0',

    STR_PAD_LEFT

);

$harvest_no = "HV-{$today}-{$sequence}";

/*
|--------------------------------------------------------------------------
| Load Available Fish Batches
|--------------------------------------------------------------------------
|
| Excludes batches that already have an
| active/open harvest.
|
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    fb.id,

    fb.batch_code,

    fb.species,

    fb.source,

    fb.initial_count,

    fb.current_count,

    fb.avg_weight_g,

    fb.stocking_date

FROM fish_batches fb

WHERE

    fb.farm_id = ?

AND fb.status = 'active'

AND NOT EXISTS (

    SELECT 1

    FROM harvests h

    WHERE

        h.fish_batch_id = fb.id

    AND h.is_open = 1

)

ORDER BY

    fb.id DESC

");

$stmt->execute([

    $farm_id

]);

$fish_batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$message = $_SESSION['success']
        ?? $_SESSION['error']
        ?? '';

$alert = isset($_SESSION['success'])

    ? 'success'

    : 'danger';

unset(

    $_SESSION['success'],

    $_SESSION['error']

);

/*
|--------------------------------------------------------------------------
| Pass Data To View
|--------------------------------------------------------------------------
|
| Makes variables available inside
| partials/form.php
|
|--------------------------------------------------------------------------
*/

$formData = [

    'harvest_no'   => $harvest_no,

    'fish_batches' => $fish_batches,

    'farm_id'      => $farm_id,

    'staff_id'     => $staff_id,

    'page_title'   => $page_title,

    'module'       => $module

];

extract(

    $formData,

    EXTR_SKIP

);

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../includes/header.php';

require_once __DIR__ . '/../../includes/sidebar.php';

// View
require_once __DIR__ . '/partials/create.php';

?>
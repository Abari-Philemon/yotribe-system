<?php

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

require_permission('stocking');

$farm_id = farm_id();

$page_title = "Stocking Control Center";


/*
==================================================
KPI SUMMARY
==================================================
*/

$stmt = $pdo->prepare("

SELECT

COALESCE(
SUM(current_count),0
) total_fish,

COUNT(DISTINCT pond_id) active_ponds,

COUNT(*) active_records

FROM pond_stocking

WHERE farm_id=?

AND status='active'

");

$stmt->execute([$farm_id]);

$summary = $stmt->fetch(PDO::FETCH_ASSOC);


/*
==================================================
ACTIVE STOCK TABLE
==================================================
*/

$stmt = $pdo->prepare("

SELECT

ps.id,

p.pond_code,

fb.batch_code,

ps.current_count,

ps.avg_weight_g,

ps.stocking_date,

ps.status,

(

SELECT
COALESCE(
SUM(current_count),
0
)

FROM pond_stocking x

WHERE x.pond_id=p.id

AND x.status='active'

) pond_total,

p.capacity

FROM pond_stocking ps

JOIN ponds_tanks p

ON p.id=ps.pond_id

JOIN fish_batches fb

ON fb.id=ps.batch_id

WHERE ps.farm_id=?

AND ps.status='active'

ORDER BY

p.pond_code,

ps.stocking_date DESC

");

$stmt->execute([$farm_id]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';

?>


<div class="container-fluid py-4">


<h2 class="mb-4">

🐟 Stocking Control Center

</h2>


<?php if(isset($_GET['success'])): ?>

<div class="alert alert-success">

<?php

switch($_GET['success']){

case '1':

echo "Fish stocked successfully";

break;

case 'transfer':

echo "Transfer completed successfully";

break;

case 'mortality':

echo "Mortality recorded successfully";

break;

default:

echo "Operation completed";

}

?>

</div>

<?php endif; ?>


<!-- ACTIONS -->

<div class="mb-4 d-flex flex-wrap gap-2">

<a
href="create.php"
class="btn btn-primary"
>

+ Stock Fish

</a>


<a
href="transfer.php"
class="btn btn-warning"
>

Transfer Fish

</a>


<a
href="mortality.php"
class="btn btn-danger"
>

Record Mortality

</a>

</div>



<!-- KPI -->

<div class="row g-3 mb-4">


<div class="col-md-4">

<div class="card shadow-sm">

<div class="card-body">

<small class="text-muted">

Total Fish

</small>

<h2>

<?= number_format(
$summary['total_fish']
) ?>

</h2>

</div>

</div>

</div>



<div class="col-md-4">

<div class="card shadow-sm">

<div class="card-body">

<small class="text-muted">

Active Ponds

</small>

<h2>

<?= number_format(
$summary['active_ponds']
) ?>

</h2>

</div>

</div>

</div>



<div class="col-md-4">

<div class="card shadow-sm">

<div class="card-body">

<small class="text-muted">

Active Records

</small>

<h2>

<?= number_format(
$summary['active_records']
) ?>

</h2>

</div>

</div>

</div>


</div>




<div class="card shadow-sm">

<div class="card-body">


<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">

<h5>

Current Pond Stocking

</h5>


<input

type="text"

id="searchInput"

class="form-control"

style="max-width:350px"

placeholder="Search pond / batch"

>

</div>



<div class="table-responsive">

<table

class="table table-hover"

id="stockTable"

>

<thead>

<tr>

<th>Pond</th>

<th>Batch</th>

<th>Current Fish</th>

<th>Pond Total</th>

<th>Avg Weight</th>

<th>Stocked Date</th>

<th>Status</th>

<th>Alert</th>

</tr>

</thead>

<tbody>


<?php foreach($rows as $r): ?>

<?php

$alert='OK';

$alertClass='success';


if(

$r['pond_total']

>

$r['capacity']

){

$alert='Over Capacity';

$alertClass='danger';

}

?>


<tr>


<td>

<?= htmlspecialchars(
$r['pond_code']
) ?>

</td>


<td>

<?= htmlspecialchars(
$r['batch_code']
) ?>

</td>


<td>

<?= number_format(
$r['current_count']
) ?>

</td>


<td>

<?= number_format(
$r['pond_total']
) ?>

</td>


<td>

<?= number_format(
$r['avg_weight_g'],
2
) ?>

g

</td>


<td>

<?= htmlspecialchars(
$r['stocking_date']
) ?>

</td>


<td>

<span class="badge bg-success">

<?= ucfirst(
$r['status']
) ?>

</span>

</td>


<td>

<span class="badge bg-<?= $alertClass ?>">

<?= $alert ?>

</span>

</td>


</tr>

<?php endforeach; ?>


</tbody>

</table>

</div>


</div>

</div>


</div>



<script>

document

.getElementById(
'searchInput'
)

.addEventListener(

'keyup',

function(){

const value=

this.value
.toLowerCase();

document

.querySelectorAll(

'#stockTable tbody tr'

)

.forEach(

row=>{

row.style.display=

row.innerText

.toLowerCase()

.includes(value)

?

''

:

'none';

}

);

}

);

</script>


<?php

require_once __DIR__.'/../../includes/footer.php';

?>
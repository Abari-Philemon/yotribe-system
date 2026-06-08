<?php

require_once __DIR__.'/../../middleware/auth_guard.php';
require_once __DIR__.'/../../middleware/farm_guard.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../helpers/permission.php';

require_permission('stocking');

$farm_id=farm_id();

$page_title='Stocking Dashboard';


/*
==================================================
POND SUMMARY
==================================================
*/

$stmt=$pdo->prepare("

SELECT

p.id,
p.pond_code,

COALESCE(
SUM(ps.current_count),
0
) total_fish

FROM ponds_tanks p

LEFT JOIN pond_stocking ps

ON ps.pond_id=p.id
AND ps.status='active'

WHERE p.farm_id=?

GROUP BY p.id

ORDER BY p.pond_code

");

$stmt->execute([$farm_id]);

$ponds=$stmt->fetchAll();


/*
==================================================
TOTALS
==================================================
*/

$stmt=$pdo->prepare("

SELECT

COUNT(*) total_records,

COALESCE(
SUM(current_count),
0
) total_fish

FROM pond_stocking

WHERE farm_id=?
AND status='active'

");

$stmt->execute([$farm_id]);

$summary=$stmt->fetch();


/*
==================================================
STOCKING RECORDS
==================================================
*/

$stmt=$pdo->prepare("

SELECT

ps.*,

p.pond_code,

fb.batch_code

FROM pond_stocking ps

JOIN ponds_tanks p
ON p.id=ps.pond_id

JOIN fish_batches fb
ON fb.id=ps.batch_id

WHERE ps.farm_id=?

ORDER BY ps.id DESC

LIMIT 300

");

$stmt->execute([$farm_id]);

$records=$stmt->fetchAll();


/*
==================================================
MORTALITY RECORDS
==================================================
*/

$stmt=$pdo->prepare("

SELECT

sm.quantity,

sm.movement_date,

p.pond_code,

fb.batch_code

FROM stock_movements sm

LEFT JOIN ponds_tanks p
ON p.id=sm.from_pond_id

LEFT JOIN fish_batches fb
ON fb.id=sm.batch_id

WHERE sm.farm_id=?

AND sm.type='mortality'

ORDER BY sm.id DESC

LIMIT 100

");

$stmt->execute([$farm_id]);

$mortalities=$stmt->fetchAll();

require_once __DIR__.'/../../includes/header.php';

require_once __DIR__.'/../../includes/sidebar.php';

?>


<div class="container-fluid py-4">


<h3 class="mb-4">

Stocking Dashboard

</h3>


<?php if(isset($_GET['success'])): ?>

<div class="alert alert-success">

Operation completed successfully.

</div>

<?php endif; ?>


<!-- QUICK ACTIONS -->

<div class="row g-3 mb-4">

<div class="col-md-3">

<a
href="create.php"
class="card text-decoration-none shadow-sm p-4"
>

<h5>

+ Stock Fish

</h5>

</a>

</div>


<div class="col-md-3">

<a
href="transfer.php"
class="card text-decoration-none shadow-sm p-4"
>

<h5>

Transfer Fish

</h5>

</a>

</div>


<div class="col-md-3">

<a
href="mortality.php"
class="card text-decoration-none shadow-sm p-4"
>

<h5>

Record Mortality

</h5>

</a>

</div>


<div class="col-md-3">

<div class="card shadow-sm p-4">

<h6>Total Fish</h6>

<h3>

<?= number_format(
$summary['total_fish']
) ?>

</h3>

</div>

</div>

</div>



<!-- POND STATUS -->

<div class="card shadow-sm border-0 mb-4">

<div class="card-header">

Pond Status

</div>

<div class="card-body">

<div class="table-responsive">

<table class="table">

<thead>

<tr>

<th>Pond</th>

<th>Current Fish</th>

</tr>

</thead>

<tbody>

<?php foreach($ponds as $p): ?>

<tr>

<td>

<?= htmlspecialchars(
$p['pond_code']
) ?>

</td>

<td>

<?= number_format(
$p['total_fish']
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>




<!-- STOCKING RECORDS -->

<div class="card shadow-sm border-0 mb-4">

<div class="card-header">

<div class="row">

<div class="col-md-4">

<h5>

Stocking Records

</h5>

</div>

<div class="col-md-8">

<div class="row g-2">

<div class="col-md-4">

<input

id="recordSearch"

class="form-control"

placeholder="Search"

>

</div>

<div class="col-md-4">

<select
id="recordStatus"
class="form-select"
>

<option value="">

All Status

</option>

<option value="active">

Active

</option>

<option value="moved">

Moved

</option>

<option value="harvested">

Harvested

</option>

</select>

</div>

<div class="col-md-4">

<input
type="date"
id="recordDate"
class="form-control"
>

</div>

</div>

</div>

</div>

</div>


<div class="card-body p-0">

<div class="table-responsive">

<table
class="table table-hover"
id="recordTable"
>

<thead>

<tr>

<th>Pond</th>

<th>Batch</th>

<th>Stocked</th>

<th>Current</th>

<th>Date</th>

<th>Status</th>

</tr>

</thead>

<tbody>

<?php foreach($records as $r): ?>

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
$r['stocked_count']
) ?>

</td>

<td>

<?= number_format(
$r['current_count']
) ?>

</td>

<td class="record-date">

<?= $r['stocking_date'] ?>

</td>

<td class="record-status">

<?= $r['status'] ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>




<!-- MORTALITY RECORDS -->

<div class="card shadow-sm border-0">

<div class="card-header">

Mortality Records

</div>

<div class="card-body">

<div class="table-responsive">

<table class="table">

<thead>

<tr>

<th>Date</th>

<th>Pond</th>

<th>Batch</th>

<th>Dead Count</th>

</tr>

</thead>

<tbody>

<?php foreach($mortalities as $m): ?>

<tr>

<td>

<?= $m['movement_date'] ?>

</td>

<td>

<?= htmlspecialchars(
$m['pond_code']
) ?>

</td>

<td>

<?= htmlspecialchars(
$m['batch_code']
) ?>

</td>

<td>

<?= number_format(
$m['quantity']
) ?>

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

const search=
document.getElementById(
'recordSearch'
);

const status=
document.getElementById(
'recordStatus'
);

const date=
document.getElementById(
'recordDate'
);

function filterRecords(){

document
.querySelectorAll(
'#recordTable tbody tr'
)

.forEach(row=>{

const text=
row.innerText
.toLowerCase();

const rowStatus=
row.querySelector(
'.record-status'
)

.innerText
.toLowerCase();

const rowDate=
row.querySelector(
'.record-date'
)

.innerText
.trim();

let show=true;

if(
search.value &&
!text.includes(
search.value.toLowerCase()
)
){

show=false;

}

if(
status.value &&
rowStatus!==status.value
){

show=false;

}

if(
date.value &&
rowDate!==date.value
){

show=false;

}

row.style.display=
show ? '' : 'none';

});

}

search.addEventListener(
'keyup',
filterRecords
);

status.addEventListener(
'change',
filterRecords
);

date.addEventListener(
'change',
filterRecords
);

</script>


</body>
</html>
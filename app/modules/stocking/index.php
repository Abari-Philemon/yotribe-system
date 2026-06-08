<?php

require_once __DIR__.'/../../middleware/auth_guard.php';
require_once __DIR__.'/../../middleware/farm_guard.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../helpers/permission.php';

require_permission('stocking');

$farm_id = farm_id();

$page_title='Stocking Dashboard';


/*
==================================================
SUMMARY
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

$summary=$stmt->fetch(PDO::FETCH_ASSOC);



/*
==================================================
POND STATUS BY SECTION
==================================================
*/

$stmt=$pdo->prepare("

SELECT

p.id,

p.section_name,

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

GROUP BY

p.id,
p.section_name,
p.pond_code

ORDER BY

p.section_name,
p.pond_code

");

$stmt->execute([$farm_id]);

$pondSections=[];

while(
$row=$stmt->fetch(PDO::FETCH_ASSOC)
){

$section=

!empty($row['section_name'])

?

$row['section_name']

:

'Unassigned';

$pondSections[$section][]=$row;

}



/*
==================================================
STOCKING RECORDS
==================================================
*/

$stmt=$pdo->prepare("

SELECT

ps.id,

p.pond_code,

p.section_name,

fb.batch_code,

fb.species,

ps.stocked_count,

ps.current_count,

ps.stocking_date,

ps.status,

COALESCE(

(

SELECT SUM(quantity)

FROM stock_movements sm

WHERE sm.batch_id=ps.batch_id

AND sm.type='mortality'

),

0

) mortality_total


FROM pond_stocking ps

JOIN ponds_tanks p

ON p.id=ps.pond_id

JOIN fish_batches fb

ON fb.id=ps.batch_id

WHERE ps.farm_id=?

ORDER BY ps.id DESC

");

$stmt->execute([$farm_id]);

$records=$stmt->fetchAll(PDO::FETCH_ASSOC);



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

$mortalities=$stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__.'/../../includes/header.php';

require_once __DIR__.'/../../includes/sidebar.php';

?>


<div class="container-fluid py-4">


<h3 class="mb-4">

Stocking Dashboard

</h3>


<div class="row g-3 mb-4">


<div class="col-md-3">

<a
href="create.php"
class="card p-4 text-decoration-none shadow-sm"
>

<h5>+ Stock Fish</h5>

</a>

</div>


<div class="col-md-3">

<a
href="transfer.php"
class="card p-4 text-decoration-none shadow-sm"
>

<h5>Transfer Fish</h5>

</a>

</div>


<div class="col-md-3">

<a
href="mortality.php"
class="card p-4 text-decoration-none shadow-sm"
>

<h5>Record Mortality</h5>

</a>

</div>


<div class="col-md-3">

<div class="card p-4 shadow-sm">

<h6>Total Active Fish</h6>

<h3>

<?= number_format(
$summary['total_fish']
) ?>

</h3>

</div>

</div>

</div>



<!-- POND STATUS -->

<div class="card shadow-sm mb-4">

<div class="card-header">

Pond Status By Section

</div>

<div class="card-body">


<div class="accordion" id="pondAccordion">

<?php

$i=0;

foreach(
$pondSections as $section=>$pondList
):

$i++;

?>

<div class="accordion-item">

<h2
class="accordion-header"
id="heading<?= $i ?>"
>

<button

class="accordion-button collapsed"

type="button"

data-bs-toggle="collapse"

data-bs-target="#collapse<?= $i ?>"

>

<?= htmlspecialchars($section) ?>

(

<?= count($pondList) ?>

 ponds)

</button>

</h2>


<div

id="collapse<?= $i ?>"

class="accordion-collapse collapse"

data-bs-parent="#pondAccordion"

>

<div class="accordion-body">


<table class="table">

<thead>

<tr>

<th>Pond</th>

<th>Fish Count</th>

</tr>

</thead>

<tbody>

<?php foreach($pondList as $p): ?>

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

<?php endforeach; ?>

</div>

</div>

</div>



<!-- FILTERS -->

<div class="card shadow-sm mb-4">

<div class="card-header">

Stocking Records

</div>

<div class="card-body">

<input

id="recordSearch"

class="form-control mb-3"

placeholder="Search records..."

>


<div class="table-responsive">

<table
class="table table-hover"
id="recordTable"
>

<thead>

<tr>

<th>Section</th>

<th>Pond</th>

<th>Batch</th>

<th>Species</th>

<th>Stocked</th>

<th>Remaining</th>

<th>Mortality</th>

<th>Estimated Remaining</th>

<th>Status</th>

</tr>

</thead>

<tbody>


<?php foreach($records as $r):

$estimated=
$r['current_count']
-
$r['mortality_total'];

if($estimated<0){

$estimated=0;

}

?>

<tr>

<td>

<?= htmlspecialchars(
$r['section_name']
) ?>

</td>

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

<?= htmlspecialchars(
$r['species']
) ?>

</td>

<td>

<?= number_format(
$r['stocked_count']
) ?>

</td>

<td>

<strong>

<?= number_format(
$r['current_count']
) ?>

</strong>

</td>

<td>

<?= number_format(
$r['mortality_total']
) ?>

</td>

<td>

<span class="badge bg-info">

<?= number_format(
$estimated
) ?>

</span>

</td>

<td>

<?= ucfirst(
$r['status']
) ?>

</td>

</tr>

<?php endforeach; ?>


</tbody>

</table>

</div>

</div>

</div>




<!-- MORTALITY -->

<div class="card shadow-sm">

<div class="card-header">

Mortality Records

</div>

<div class="card-body">

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

<?= $m['pond_code'] ?>

</td>

<td>

<?= $m['batch_code'] ?>

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


<script>

document
.getElementById(
'recordSearch'
)

.addEventListener(
'keyup',
function(){

const value=
this.value.toLowerCase();

document
.querySelectorAll(
'#recordTable tbody tr'
)

.forEach(row=>{

row.style.display=

row.innerText
.toLowerCase()
.includes(value)

?

''

:

'none';

});

});

</script>


</body>

</html>
<?php

require_once __DIR__.'/../../middleware/auth_guard.php';
require_once __DIR__.'/../../middleware/farm_guard.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../helpers/stocking_helper.php';

$farm_id   = farm_id();
$farm_name = farm_name();

$ratio = function_exists('stocking_ratio')
    ? stocking_ratio()
    : 1;


/*
==================================
LOAD PONDS
==================================
*/

$stmt=$pdo->prepare("
SELECT
id,
pond_code,
volume_liters,
capacity

FROM ponds_tanks

WHERE farm_id=?
AND status='active'

ORDER BY pond_code
");

$stmt->execute([$farm_id]);

$ponds=$stmt->fetchAll(PDO::FETCH_ASSOC);


/*
==================================
LOAD ACTIVE BATCHES
==================================
*/

$stmt=$pdo->prepare("
SELECT

id,
batch_code,
current_count,
avg_weight_g,
species,
created_at

FROM fish_batches

WHERE farm_id=?
AND status='active'
AND current_count>0

ORDER BY id DESC
");

$stmt->execute([$farm_id]);

$batches=$stmt->fetchAll(PDO::FETCH_ASSOC);


/*
==================================
CURRENT POND STOCK
==================================
*/

$stmt=$pdo->prepare("
SELECT

pond_id,
SUM(current_count)

FROM pond_stocking

WHERE farm_id=?
AND status='active'

GROUP BY pond_id
");

$stmt->execute([$farm_id]);

$pondStock=$stmt->fetchAll(PDO::FETCH_KEY_PAIR);


require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';

?>


<div class="container-fluid py-4">


<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h3 class="fw-bold">

Stock Fish Into Pond

</h3>

<div class="text-muted">

Farm:

<?= htmlspecialchars($farm_name) ?>

</div>

</div>

<a
href="index.php"
class="btn btn-outline-secondary"
>

Back

</a>

</div>



<div class="card shadow-sm border-0">

<div class="card-body p-4">


<form
method="POST"
action="store.php"
>


<!-- POND -->

<div class="mb-4">

<label class="fw-semibold mb-2">

Select Pond

</label>

<select
name="pond_id"
id="pond"
class="form-select"
required
>

<option value="">

Select Pond

</option>

<?php foreach($ponds as $p): ?>

<option

value="<?= $p['id'] ?>"

data-volume="<?= $p['volume_liters'] ?>"

data-capacity="<?= $p['capacity'] ?>"

data-current="<?= $pondStock[$p['id']] ?? 0 ?>"

>

<?= htmlspecialchars($p['pond_code']) ?>

|

<?= number_format($p['capacity']) ?>

cap

</option>

<?php endforeach; ?>

</select>

</div>



<!-- BATCH -->

<div class="mb-4">

<label class="fw-semibold mb-2">

Select Fish Batch

</label>

<select
name="batch_id"
id="batch"
class="form-select"
required
>

<option value="">

Select Batch

</option>


<?php foreach($batches as $b):

$biomass=
(
($b['current_count'] ?? 0)
*
($b['avg_weight_g'] ?? 0)
)
/1000;

?>

<option

value="<?= $b['id'] ?>"

data-available="<?= $b['current_count'] ?>"

data-weight="<?= $b['avg_weight_g'] ?>"

>

<?= htmlspecialchars($b['batch_code']) ?>

|

<?= htmlspecialchars($b['species']) ?>

|

<?= number_format($b['current_count']) ?>

fish

|

<?= number_format($b['avg_weight_g'],1) ?>

g

|

<?= number_format($biomass,2) ?>

kg biomass

</option>

<?php endforeach; ?>

</select>

</div>



<!-- QUANTITY -->

<div class="mb-4">

<label class="fw-semibold mb-2">

Quantity To Stock

</label>

<input

type="number"

name="quantity"

id="qty"

class="form-control"

required

>

</div>



<!-- INFO BOX -->

<div
class="alert alert-info"
id="infoBox"
>

Select pond and batch

</div>



<button class="btn btn-primary w-100">

Stock Fish

</button>

</form>

</div>

</div>

</div>



<script>

const ratio=
<?= $ratio ?>;

const pondEl=
document.getElementById('pond');

const batchEl=
document.getElementById('batch');

const qtyEl=
document.getElementById('qty');

const infoBox=
document.getElementById('infoBox');



function refreshInfo(){

const pond=
pondEl.selectedOptions[0];

const batch=
batchEl.selectedOptions[0];


if(
!pond.value ||
!batch.value
){

infoBox.innerHTML=
"Select pond and batch";

return;

}


const volume=
parseFloat(
pond.dataset.volume
);

const capacity=
parseInt(
pond.dataset.capacity
);

const current=
parseInt(
pond.dataset.current
);

const available=
parseInt(
batch.dataset.available
);

const weight=
parseFloat(
batch.dataset.weight
);


const maxByVolume=
Math.floor(
volume / ratio
);

const maxAllowed=
Math.min(
capacity,
maxByVolume
);


const remaining=
maxAllowed-current;


infoBox.innerHTML=`

<strong>Pond Capacity:</strong>

${maxAllowed.toLocaleString()} fish

<hr>

<strong>Current Pond Stock:</strong>

${current.toLocaleString()}

<br>

<strong>Remaining Space:</strong>

${remaining.toLocaleString()}

<hr>

<strong>Batch Available:</strong>

${available.toLocaleString()} fish

<br>

<strong>Average Weight:</strong>

${weight} g

`;

}



qtyEl.addEventListener(
'input',
function(){

const pond=
pondEl.selectedOptions[0];

const batch=
batchEl.selectedOptions[0];

if(
!pond.value ||
!batch.value
)return;


const current=
parseInt(
pond.dataset.current
);

const capacity=
parseInt(
pond.dataset.capacity
);

const volume=
parseFloat(
pond.dataset.volume
);

const available=
parseInt(
batch.dataset.available
);

const max=
Math.min(
capacity,
Math.floor(volume/ratio)
);

const remaining=
max-current;

let v=
parseInt(this.value)||0;


if(v>remaining){

this.value=
remaining;

}


if(v>available){

this.value=
available;

}

}
);


pondEl.addEventListener(
'change',
refreshInfo
);

batchEl.addEventListener(
'change',
refreshInfo
);

</script>



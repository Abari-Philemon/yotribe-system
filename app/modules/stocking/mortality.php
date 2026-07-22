<?php

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

/*
=========================================
CSRF
=========================================
*/

if(empty($_SESSION['csrf_token'])){

$_SESSION['csrf_token']=bin2hex(
random_bytes(32)
);

}


/*
=========================================
LOAD ACTIVE STOCKS
=========================================
*/

$stmt=$pdo->prepare("

SELECT

ps.id,

p.pond_code,

fb.batch_code,

ps.batch_id,

ps.current_count

FROM pond_stocking ps

JOIN ponds_tanks p

ON p.id=ps.pond_id

JOIN fish_batches fb

ON fb.id=ps.batch_id

WHERE ps.farm_id=?

AND ps.status='active'

AND ps.current_count>0

ORDER BY p.pond_code

");

$stmt->execute([$farm_id]);

$stocks=$stmt->fetchAll();


/*
=========================================
TOTAL MORTALITY KPI
=========================================
*/

$stmt=$pdo->prepare("

SELECT

COALESCE(
SUM(quantity),
0
)

FROM stock_movements

WHERE farm_id=?

AND type='mortality'

");

$stmt->execute([$farm_id]);

$total_mortality=
$stmt->fetchColumn();


/*
=========================================
LOAD MORTALITY RECORDS
=========================================
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

");

$stmt->execute([$farm_id]);

$records=
$stmt->fetchAll();


/*
=========================================
HANDLE SUBMIT
=========================================
*/

if($_SERVER['REQUEST_METHOD']==='POST'){

if(

!hash_equals(

$_SESSION['csrf_token'],

$_POST['csrf_token']

)

){

die("Invalid CSRF");

}


$stock_id=
(int)$_POST['stock_id'];

$dead=
(int)$_POST['quantity'];

if($dead<=0){

die("Invalid quantity");

}


$pdo->beginTransaction();

try{


$stmt=$pdo->prepare("

SELECT *

FROM pond_stocking

WHERE id=?

AND farm_id=?

FOR UPDATE

");

$stmt->execute([

$stock_id,
$farm_id

]);

$row=
$stmt->fetch();


if(!$row){

throw new Exception(
"Stock not found"
);

}


if(

$dead>

$row['current_count']

){

throw new Exception(

"Mortality exceeds available fish"

);

}


/* UPDATE */

$pdo->prepare("

UPDATE pond_stocking

SET

current_count=

current_count-?

WHERE id=?

")->execute([

$dead,

$stock_id

]);


/* LOG */

$pdo->prepare("

INSERT INTO stock_movements

(

farm_id,

type,

from_pond_id,

batch_id,

quantity,

movement_date

)

VALUES

(

?,

'mortality',

?,

?,

?,

CURDATE()

)

")->execute([

$farm_id,

$row['pond_id'],

$row['batch_id'],

$dead

]);


$pdo->commit();

header(
"Location: mortality.php?success=1"
);

exit;


}catch(Exception $e){

$pdo->rollBack();

$error=
$e->getMessage();

}

}


require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';

?>


<div class="container py-4">

<h3>

Record Mortality

</h3>


<?php if(isset($_GET['success'])): ?>

<div class="alert alert-success">

Mortality recorded successfully

</div>

<?php endif; ?>


<?php if(!empty($error)): ?>

<div class="alert alert-danger">

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>



<div class="row g-4">


<div class="col-md-4">


<div class="card p-4">

<small>Total Mortality</small>

<h2>

<?= number_format(
$total_mortality
) ?>

</h2>

</div>


<form
method="post"
class="card p-4 mt-3"
>

<input
type="hidden"
name="csrf_token"
value="<?= $_SESSION['csrf_token'] ?>"
>


<label>

Select Pond / Batch

</label>

<select
name="stock_id"
class="form-select mb-3"
required
>

<option value="">

Select

</option>

<?php foreach($stocks as $s): ?>

<option value="<?= $s['id'] ?>">

<?= $s['pond_code'] ?>

|

<?= $s['batch_code'] ?>

(

<?= number_format(
$s['current_count']
) ?>

)

</option>

<?php endforeach; ?>

</select>



<label>

Dead Count

</label>

<input

type="number"

name="quantity"

class="form-control mb-3"

required

>


<button class="btn btn-danger w-100">

Record Mortality

</button>

</form>

</div>



<div class="col-md-8">


<div class="card p-4">

<div class="d-flex justify-content-between mb-3">

<h5>

Mortality Records

</h5>

<input

id="search"

class="form-control"

style="max-width:250px"

placeholder="Search"

>

</div>


<div class="table-responsive">

<table

class="table"

id="mortalityTable"

>

<thead>

<tr>

<th>Pond</th>

<th>Batch</th>

<th>Dead Fish</th>

<th>Date</th>

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
$r['quantity']
) ?>

</td>

<td>

<?= htmlspecialchars(
$r['movement_date']
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

</div>


<script>

document
.getElementById(
'search'
)

.addEventListener(

'keyup',

function(){

const value=
this.value
.toLowerCase();

document

.querySelectorAll(
'#mortalityTable tbody tr'
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
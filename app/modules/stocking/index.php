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

$summary=$stmt->fetch();


/*
==================================================
POND STATUS BY SECTION
==================================================
*/

$stmt = $pdo->prepare("

SELECT

p.id,
p.pond_code,
p.section_name,
p.section_id,

COALESCE(
SUM(ps.current_count),
0
) total_fish

FROM ponds_tanks p

LEFT JOIN pond_stocking ps
ON ps.pond_id = p.id
AND ps.status='active'

WHERE p.farm_id=?

GROUP BY p.id

ORDER BY
p.section_name,
p.pond_code

");

$stmt->execute([$farm_id]);

$assignedSections   = [];
$unassignedPonds    = [];

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

    $fishCount = (int)$row['total_fish'];

    /*
    =====================================
    UNASSIGNED
    =====================================
    */

    if(

        empty($row['section_id'])

        ||

        $fishCount <= 0

    ){

        $unassignedPonds[] = $row;

        continue;
    }

    /*
    =====================================
    ASSIGNED
    =====================================
    */

    $section =
    !empty($row['section_name'])
    ? $row['section_name']
    : 'Unnamed Section';

    $assignedSections[$section][] =
    $row;

    $unassignedNoSection = [];
$unassignedEmpty     = [];

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

    $fishCount = (int)$row['total_fish'];

    if(empty($row['section_id'])){

        $unassignedNoSection[] = $row;
        continue;
    }

    if($fishCount <= 0){

        $unassignedEmpty[] = $row;
        continue;
    }

    $section =
    $row['section_name']
    ?: 'Unnamed Section';

    $assignedSections[$section][] = $row;
}
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

fb.batch_code,

fb.species,

ps.stocked_count,

ps.current_count,

ps.avg_weight_g,

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

) mortality_total,


ROUND(

CASE

WHEN ps.stocked_count<=0

THEN ps.current_count

ELSE

ps.current_count-

(

(

COALESCE(

(

SELECT SUM(quantity)

FROM stock_movements sm

WHERE sm.batch_id=ps.batch_id

AND sm.type='mortality'

),

0

)

/

ps.stocked_count

)

*

ps.current_count

)

END

) estimated_remaining


FROM pond_stocking ps

JOIN ponds_tanks p

ON p.id=ps.pond_id

JOIN fish_batches fb

ON fb.id=ps.batch_id

WHERE ps.farm_id=?

ORDER BY ps.id DESC

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


   

?>


<div class="container-fluid py-4">


<h3 class="mb-4">

Stocking Dashboard

</h3>


<?php if(isset($_GET['success'])): ?>

<div class="alert alert-success">

Operation Successful

</div>

<?php endif; ?>


<!-- QUICK ACTIONS -->

<div class="row g-3 mb-4">


<div class="col-md-3">

<a
href="create.php"
class="card p-4 text-decoration-none shadow-sm"
>

<h5>

+ Stock Fish

</h5>

</a>

</div>


<div class="col-md-3">

<a
href="transfer.php"
class="card p-4 text-decoration-none shadow-sm"
>

<h5>

Transfer Fish

</h5>

</a>

</div>


<div class="col-md-3">

<a
href="mortality.php"
class="card p-4 text-decoration-none shadow-sm"
>

<h5>

Record Mortality

</h5>

</a>

</div>


<div class="col-md-3">

<div class="card p-4 shadow-sm">

<h6>

Active Fish

</h6>

<h3>

<?= number_format(
$summary['total_fish']
) ?>

</h3>

</div>

</div>

</div>




<!-- POND STATUS -->
<!-- POND STATUS -->

<div class="card shadow-sm mb-4">

    <div class="card-header">
        Pond Status
    </div>

<div class="card-body">

    <!-- VIEW SELECTOR -->
    <div class="row mb-3">

        <div class="col-md-4">
            <label class="form-label">View</label>

            <select id="pondView" class="form-select">

                <option value="assigned">
                    Assigned Ponds
                </option>

                <option value="unassigned">
                    Unassigned Ponds
                </option>

            </select>
        </div>

        <div class="col-md-4" id="sectionFilterWrap">

            <label class="form-label">
                Section
            </label>

            <select
                id="sectionFilter"
                class="form-select"
            >

                <option value="all">
                    All Sections
                </option>

                <?php foreach($assignedSections as $section => $dummy): ?>

                    <option
                        value="<?= htmlspecialchars($section) ?>"
                    >
                        <?= htmlspecialchars($section) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

    </div>


    <!-- ASSIGNED -->
    <div id="assignedBlock">

        <?php foreach($assignedSections as $section => $pondList): ?>

            <div
                class="section-box"
                data-section="<?= htmlspecialchars($section) ?>"
            >

                <h5 class="mb-3">
                    <?= htmlspecialchars($section) ?>
                </h5>

                <table class="table table-bordered mb-4">

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
                                <?= htmlspecialchars($p['pond_code']) ?>
                            </td>

                            <td>
                                <?= number_format($p['total_fish']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endforeach; ?>

    </div>


    <!-- UNASSIGNED -->
    <div
        id="unassignedBlock"
        style="display:none;"
    >

        <table class="table table-bordered">

            <thead>

            <tr>
                <th>Pond</th>
                <th>Fish Count</th>
            </tr>

            </thead>

            <tbody>

            <?php foreach($unassignedPonds as $p): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($p['pond_code']) ?>
                    </td>

                    <td>
                        <?= number_format($p['total_fish']) ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>
<div class="card shadow-sm mb-4">

    <div class="card-header">
        Stocking Records
    </div>

    <div class="card-body">
</div>

<div class="card-body">


<div class="row g-2 mb-3">

<div class="col-md-4">

<input
id="recordSearch"
class="form-control"
placeholder="Search..."
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



<div class="table-responsive">

<table
class="table table-hover"
id="recordTable"
>

<thead>

<tr>

<th>Pond</th>

<th>Batch</th>

<th>Species</th>

<th>Stocked</th>

<th>Remaining</th>

<th>Mortality</th>

<th>Estimated</th>

<th>Status</th>

</tr>

</thead>

<tbody>


<?php foreach($records as $r): ?>

<tr>

<td><?= $r['pond_code'] ?></td>

<td><?= $r['batch_code'] ?></td>

<td><?= $r['species'] ?></td>

<td><?= number_format($r['stocked_count']) ?></td>

<td>

<strong>

<?= number_format($r['current_count']) ?>

</strong>

</td>

<td>

<?= number_format($r['mortality_total']) ?>

</td>

<td>

<span class="badge bg-info">

<?= number_format($r['estimated_remaining']) ?>

</span>

</td>

<td class="record-status">

<?= $r['status'] ?>

</td>

<td class="record-date d-none">

<?= $r['stocking_date'] ?>

</td>

</tr>

<?php endforeach; ?>


</tbody>

</table>

</div>

</div>

</div>




<!-- MORTALITY TABLE -->

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

<th>Dead</th>

</tr>

</thead>

<tbody>


<?php foreach($mortalities as $m): ?>

<tr>

<td><?= $m['movement_date'] ?></td>

<td><?= $m['pond_code'] ?></td>

<td><?= $m['batch_code'] ?></td>

<td><?= number_format($m['quantity']) ?></td>

</tr>

<?php endforeach; ?>


</tbody>

</table>

</div>

</div>



</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

    const unassignedFilter =
    document.getElementById(
        'unassignedFilter'
    );

    if(unassignedFilter){

        unassignedFilter.addEventListener(
            'change',
            function(){

                document
                .querySelectorAll(
                    '#unassignedBlock tbody tr'
                )
                .forEach(row=>{

                    row.style.display='';

                    if(
                        this.value==='sectionless' &&
                        !row.classList.contains('sectionless-row')
                    ){
                        row.style.display='none';
                    }

                    if(
                        this.value==='empty' &&
                        !row.classList.contains('empty-row')
                    ){
                        row.style.display='none';
                    }

                });

            }
        );

    }

</script>
<script>

document.addEventListener('DOMContentLoaded', function(){

    const pondView = document.getElementById('pondView');

    if(pondView){

        pondView.addEventListener('change', function(){

            const assignedBlock =
                document.getElementById('assignedBlock');

            const unassignedBlock =
                document.getElementById('unassignedBlock');

            if(this.value === 'assigned'){

                assignedBlock.style.display='block';
                unassignedBlock.style.display='none';

            }else{

                assignedBlock.style.display='none';
                unassignedBlock.style.display='block';

            }

        });

    }

});

</script>

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


function filterRows(){

document
.querySelectorAll(
'#recordTable tbody tr'
)

.forEach(row=>{

let show=true;

const text=
row.innerText.toLowerCase();

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
filterRows
);

status.addEventListener(
'change',
filterRows
);

date.addEventListener(
'change',
filterRows
);

</script>


</body>
</html>
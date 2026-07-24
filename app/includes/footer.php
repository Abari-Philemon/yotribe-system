<?php
/**
 * ==========================================================
 * YOTRIBE IFMS
 * Global Footer
 * ==========================================================
 */

$module = $module ?? '';
?>

</div> <!-- END MAIN -->

<!-- ==========================================================
Bootstrap
========================================================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- ==========================================================
Chart.js
========================================================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- ==========================================================
Global JavaScript
========================================================== -->
<script>

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');

    if (sidebar) {
        sidebar.classList.toggle('active');
    }
}

const CSRF_TOKEN = "<?= csrf_token() ?>";

</script>

<!-- ==========================================================
Module JavaScript
========================================================== -->

<?php if ($module === 'harvest'): ?>
<script src="/yotribe-system/app/modules/harvest/assets/harvest.js"></script>
<?php endif; ?>

<?php if ($module === 'dashboard'): ?>

<script>

function loadRealtime() {

    fetch('/yotribe-system/app/modules/dashboard/realtime.php')
    .then(res => res.json())
    .then(d => {

        const biomass = document.getElementById('rt_biomass');
        const feed     = document.getElementById('rt_feed');
        const cost     = document.getElementById('rt_cost');
        const ponds    = document.getElementById('rt_ponds');
        const time     = document.getElementById('rt_time');

        if (!biomass) return;

        biomass.innerText = d.biomass + ' kg';
        feed.innerText    = d.today_feed + ' kg';
        cost.innerText    = '₦' + Number(d.today_cost).toLocaleString();
        ponds.innerText   = d.ponds;
        time.innerText    = 'Updated: ' + d.time;

    });

}

loadRealtime();

setInterval(loadRealtime, 10000);

/*
----------------------------------------------------------
Biomass Chart
----------------------------------------------------------
*/

const biomassChart = document.getElementById('biomassChart');

if (biomassChart) {

    fetch('charts.php?type=biomass')
    .then(r => r.json())
    .then(d => {

        new Chart(biomassChart, {

            type: 'line',

            data: {

                labels: d.labels,

                datasets: [{

                    label: 'Biomass',

                    data: d.values,

                    borderWidth: 2

                }]

            }

        });

    });

}

/*
----------------------------------------------------------
Sales Chart
----------------------------------------------------------
*/

const salesChart = document.getElementById('salesChart');

if (salesChart) {

    fetch('charts.php?type=sales')
    .then(r => r.json())
    .then(d => {

        new Chart(salesChart, {

            type: 'bar',

            data: {

                labels: d.labels,

                datasets: [{

                    label: 'Sales',

                    data: d.values

                }]

            }

        });

    });

}

</script>

<?php endif; ?>

</body>
</html>
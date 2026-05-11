<?php $farm_id = farm_id();?>
</div> <!-- END MAIN -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('active');
}
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const CSRF_TOKEN = "<?= csrf_token() ?>";
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



<script>
    function loadRealtime(){

        fetch('/yotribe-system/app/modules/dashboard/realtime.php')
        .then(res => res.json())
        .then(d => {

            document.getElementById('rt_biomass').innerText = d.biomass + ' kg';
            document.getElementById('rt_feed').innerText = d.today_feed + ' kg';
            document.getElementById('rt_cost').innerText = '₦' + d.today_cost.toLocaleString();
            document.getElementById('rt_ponds').innerText = d.ponds;

            document.getElementById('rt_time').innerText = 'Updated: ' + d.time;
        });
    }

    // AUTO REFRESH EVERY 10s
    setInterval(loadRealtime, 10000);
    loadRealtime();

// Biomass Chart (secure - no farm_id in URL)
fetch('charts.php?type=biomass')
.then(r => r.json())
.then(d => new Chart(document.getElementById('biomassChart'), {
    type: 'line',
    data: {
        labels: d.labels,
        datasets: [{
            label: 'Biomass',
            data: d.values,
            borderWidth: 2
        }]
    }
}));

// Sales Chart
fetch('charts.php?type=sales')
.then(r => r.json())
.then(d => new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels: d.labels,
        datasets: [{
            label: 'Sales',
            data: d.values
        }]
    }
}));
</script>
        

</body>
</html>
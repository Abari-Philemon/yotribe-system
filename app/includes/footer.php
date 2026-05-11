</div> <!-- END MAIN -->
<?php 
$farm_id = farm_id();
$staff_id = $_SESSION['staff_id'];
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('stock_id')
    .addEventListener('change', updatePreview);

    document.getElementById('qty')
        .addEventListener('input', calcCost);

    updatePreview();
    function updatePreview(){

        let stock = document.getElementById('stock_id').selectedOptions[0];

        let count = parseFloat(stock.dataset.count || 0);
        let weight = parseFloat(stock.dataset.weight || 0);

        let biomass = (count * weight) / 1000;

        let rate = 0.05;
        if(weight < 50) rate = 0.10;
        else if(weight < 200) rate = 0.06;

        let recommended = biomass * rate;

        document.getElementById('rt_biomass').innerText = biomass.toFixed(2) + ' kg';
        document.getElementById('rt_feed').innerText = recommended.toFixed(2) + ' kg';

        calcCost();
    }

    function calcCost(){

        let qty = parseFloat(document.getElementById('qty').value) || 0;
        let avg_price = 500;

        let cost = qty * avg_price;

        document.getElementById('rt_cost').innerText = '₦' + cost.toLocaleString();
    }
</script>
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
<script>
// Load farms into dropdown
fetch('/yotribe-system/app/modules/farms/list.php')
.then(res => res.json())
.then(farms => {

    const select = document.getElementById('farmSwitcher');
    select.innerHTML = '';

    farms.forEach(farm => {
        const option = document.createElement('option');
        option.value = farm.id;
        option.text  = farm.name;

        if (farm.id == <?= $farm_id ?>) {
            option.selected = true;
        }

        select.appendChild(option);
    });
});

// Handle farm switch
document.getElementById('farmSwitcher').addEventListener('change', function () {

    fetch('/yotribe-system/app/modules/farms/switch_live.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'farm_id=' + this.value + '&csrf_token=' + CSRF_TOKEN
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            location.reload();
        } else {
            alert(res.message || 'Switch failed');
        }
    });

});
</script>
        

</body>
</html>
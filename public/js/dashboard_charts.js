const ctx = document.getElementById('stockChart').getContext('2d');
const stockChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Fish Stock (kg)', 'Feed Stock (kg)'],
        datasets: [{
            label: 'Current Farm Status',
            data: [TOTAL_FISH_STOCK, TOTAL_FEED_STOCK], // pass these values from PHP
            backgroundColor: ['#36A2EB','#FF6384']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        }
    }
});

src="//cdn.jsdelivr.net/npm/sweetalert2@11"

$('.deleteFeed').click(function(){
  let id = $(this).data('id');
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if(result.isConfirmed){
        window.location.href = 'delete.php?id='+id;
    }
  })
});


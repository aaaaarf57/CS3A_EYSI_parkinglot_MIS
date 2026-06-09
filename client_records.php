<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';

if (empty($_SESSION['username']) || $_SESSION['role'] !== 'employee') {
    header("Location: /unauthorized.php");
    exit;
}

$page_title = "Employee Dashboard";
include '../templates/header.php';
include '../templates/sidebar.php';

// ===== FETCH DATA =====
$totalSlots = 20; // Fixed total number of slots
$occupied   = $conn->query("SELECT COUNT(*) AS count FROM slots WHERE status='occupied'")->fetch_assoc()['count'] ?? 0;
$vacant     = max(0, $totalSlots - $occupied);
$walkins    = $conn->query("SELECT COUNT(*) AS count FROM clients WHERE parking_type='walkin'")->fetch_assoc()['count'] ?? 0;
$reservations = $conn->query("SELECT COUNT(*) AS count FROM clients WHERE parking_type='reservation'")->fetch_assoc()['count'] ?? 0;
$totalCustomers = $conn->query("SELECT COUNT(*) AS count FROM clients")->fetch_assoc()['count'] ?? 0;
?>

<style>
    /* ===================================== UNIVERSAL CONTENT LAYOUT (Eysi MIS) ===================================== */
    .main-content {
        margin-right: 0px;
        margin-top: 0px;
        margin-bottom: 30px;
        padding: 0;
        min-height: 100vh;
        box-sizing: border-box;
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }

    .main-content .right-side-map {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    /* ======================= RESPONSIVE ADJUSTMENTS ======================= */

    
    @media (max-width: 1200px) {
        .main-content {
            margin-left: 10px;
            margin-right: 10px;
            margin-top: 25px;
            margin-bottom: 25px;
        }
        .total-box,
        .status-box{
            margin-left: 0;
        }
        
        .total-car {
            width: 130px;
            height: auto;
            object-fit: contain;
            padding: 0;
        }
    }
    


    @media (max-width: 992px) {
        .main-content {
            width: 90%;
            margin-left: 10px;
            margin-right: 10px;
            margin-top: 25px;
            margin-bottom: 25px;
        }
        .total-box,
        .status-box{
            margin-left: 0px;
            width: 100%;
        }
        
        
    }

    @media (max-width: 768px) {
        .main-content {
            width: 100%;
            margin: 25px;
            display: flex;
            flex-direction: column;
            align-items: center; /* centers all child sections */
            
        }
        
        .right-side-map {
            width: 100%;
            display: flex;
            justify-content: center;
            align-content: center;
        }
        
        .right-side-map iframe,
        .right-side-map canvas {
            max-width: 100%;
            height: auto;
        }
        
        .total-box,
        .status-box {
            margin-left: 0;
            width: 50%;
        }
        
        .left-section-box {
            margin-left: 0;
            width: 80%;
        }
    }
    
        @media (max-width: 600px) {
        .main-content {
            width: 100%;
            margin: 25px;
            display: flex;
            flex-direction: column;
            align-items: center; /* centers all child sections */
            
        }
        
        .right-side-map {
            width: 100%;
            display: flex;
            justify-content: center;
            align-content: center;
        }
        
        .right-side-map iframe,
        .right-side-map canvas {
            max-width: 100%;
            height: auto;
        }
        
        .total-box,
        .status-box {
            margin-left: 0;
            width: 100%;
        }
        
        .left-section-box {
            margin-left: 0;
            width: 100%;
        }
        
        .total-car {
            width: 80px;
            height: auto;
            object-fit: contain;
        }
    }
    
        @media (max-width: 420px) {
        .main-content {
            width: 95%;
            display: flex;
            flex-direction: column;
            align-items: center; /* centers all child sections */
            
        }
        
        .right-side-map {
            width: 100%;
            display: flex;
            justify-content: center;
            align-content: center;
        }
        
        .right-side-map iframe,
        .right-side-map canvas {
            max-width: 100%;
            height: auto;
        }
        
        .total-box,
        .status-box {
            margin-left: 0;
            width: 100%;
        }
        
        .left-section-box {
            margin-left: 0;
            width: 100%;
        }
        
        .total-car {
            display: none;
        }
    }











    /*----------------------------*/
    body {
        background-color: #f2f2f2;
        
    }

    .left-section-box {
        margin-top: 20px;
        height: auto;
        display: flex;
        flex-direction: column;
        gap: 25px;
        flex: 1;
    }
    
    .total-box {
      display: flex;
      align-items: center; /* Vertically center all three items */
      justify-content: space-between;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      position: relative;
      background: linear-gradient(135deg, #f5d300 0%, #f0f0f0 100%);
      border-radius: 16px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      color: #2c2c2c;
      width: 70%;
      
    }
    
    /* Left side text section */
    .text-area {
      flex: 1; /* allow text to take available space */
    }
    
    .text-area h5 {
      margin: 5px 0;
    }
    
    .text-area p {
      font-weight: bold;
      margin-bottom: 10px;
    }
    
    /* Middle chart */
    .chart-area {
      flex: 0 0 180px; /* fixed width for the chart */
      display: flex;
      justify-content: center;
      align-items: center;
      margin: 0 20px; /* spacing between text and car */
    }
    
    .chart-container {
      position: relative;
      width: 140px;
      height: 140px;
      background: #fff;
      border-radius: 50%;
      box-shadow: 0 3px 10px rgba(0,0,0,0.2);
    }
    
    .chart-label {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      text-align: center;
      color: #2c2c2c;
      
     
    }
        /*.chart-label {
        position: relative;
        text-align: center;
        font-size: clamp(10px, 1vw, 14px);
        color: #2c2c2c;
        }*/
    
    
    .chart-label strong {
        display: block;
        font-size: clamp(14px, 1.5vw, 20px);
        color: #000;
    }
    
    /* Right side car */
    .total-car {
      width: 130px;
      height: auto;
      object-fit: contain;
    }

    /*
    .total-car {
        position: absolute;
        right: -60px;
        top: 50%;
        transform: translateY(-40%);
        width: 200px;
        z-index: 10;
        filter: drop-shadow(0 8px 12px rgba(0,0,0,0.3));
        pointer-events: none;
    }*/
    
    /*
    .total-box {
        flex: 60%;
        background: linear-gradient(135deg, #f5d300 0%, #f0f0f0 100%);
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 30px 40px;
        position: relative;
        color: #2c2c2c;
        width: 90%;
    }*/
    
    /*
    .chart-container {
        position: absolute;
        right: 15vw;
        bottom: 9vh;
        width: clamp(120px, 25vw, 170px);
        height: clamp(120px, 25vw, 170px);
        background: #fff;
        border-radius: 50%;
        box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
    }*/

    .chart-container canvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100% !important;
        height: 100% !important;
    }
    /*.chart-label {
        position: relative;
        text-align: center;
        font-size: clamp(10px, 1vw, 14px);
        color: #2c2c2c;
    }

    .chart-label strong {
        display: block;
        font-size: clamp(14px, 1.5vw, 20px);
        color: #000;
    }*/

    .status-box {
        flex: 35%;
        background: linear-gradient(135deg, #f0f0f0 0%, #d9d9d9 100%);
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 25px;
        color: #2c2c2c;
        display: flex;
        flex-direction: column;
        justify-content: center;
        width: 70%;
        
    }

    .status-box h5 {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 15px;
    }

    .status-bar {
        display: flex;
        height: 25px;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid #ccc;
    }

    .status-bar .occupied {
        background-color: #dc3545;
        height: 100%;
    }

    .status-bar .vacant {
        background-color: #28a745;
        height: 100%;
    }

    .status-legend {
        display: flex;
        justify-content: space-between;
        font-weight: 500;
        margin-top: 8px;
    }

    .right-side-map {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin-bottom: 0;
        gap: 40px;
        flex-wrap: wrap;
        width: 50%;
        height: auto;
        margin-top: 20px;
    }

    .top-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin-bottom: 0;
        gap: 40px;
        flex-wrap: wrap;
        width: 100%;
        height: auto;
    }

    .total-box h5 {
        font-size: 15px;
        margin: 0;
        font-weight: 600;
        text-transform: uppercase;
    }

    .total-box p {
        font-size: 22px;
        font-weight: 800;
        margin: 4px 0 15px;
        color: #000;
    }

    /*
    .total-car {
        position: absolute;
        right: -60px;
        top: 50%;
        transform: translateY(-40%);
        width: 200px;
        z-index: 10;
        filter: drop-shadow(0 8px 12px rgba(0,0,0,0.3));
        pointer-events: none;
    }*/

    /* === Embedded Map Adjustments === */
    .map-container {
        width: 100%;
        max-width: 550px;
        height: auto;
        overflow: hidden;
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0 auto;
        padding: 10px;
    }

    .map-container .map-wrapper {
        width: 100%;
        max-width: 100%;
        transform: scale(0.7);
        transform-origin: top center;
        margin: 0;
        padding: 0;
    }

    .map-container .slot .code {
        font-size: 0.75em !important;
    }

    .map-container .slot .meta {
        font-size: 0.65em !important;
    }

    /* === DASHBOARD MAIN LAYOUT === */
    .dashboard-content {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        gap: 40px;
        flex-wrap: wrap;
        padding: 20px;
    }

    .btn-container {
        text-align: center;
        background-color: #f2f2f2;
        margin: 0;
    }
</style>

<div class="main-content">
  <!-- LEFT SECTION: Stats & Chart -->
  <div class="left-section-box">
    <div class="top-container">

      <!-- TOTAL BOX -->
      <div class="total-box">
        <div class="text-area">
          <h5>Total<br>Walk-ins</h5>
          <p id="walkins-count"><?= $walkins ?></p>

          <h5>Total Reservations</h5>
          <p id="reservations-count"><?= $reservations ?></p>

          <h5>Total Customers</h5>
          <p id="customers-count"><?= $totalCustomers ?></p>
        </div>

        <!-- MINI DOUGHNUT CHART -->
        <div class="chart-area">
          <div class="chart-container">
            <canvas id="customerChart"></canvas>
            <div class="chart-label">
              <strong id="walkin-percent">
                <?= round(($walkins / max(1, $totalCustomers)) * 100) ?>%
              </strong>
              <span>Walk-ins</span>
            </div>
          </div>
        </div>

        <!-- Car Image -->
        <img src="/static/car-side.png" alt="Car" class="total-car">
      </div>
      <!-- End total-box -->

      <!-- PARKING STATUS BOX -->
      <div class="status-box">
        <h5>Parking Status (Total)</h5>
        <div class="status-bar">
          <div class="occupied" id="occupied-bar" style="width: <?= ($totalSlots ? ($occupied / $totalSlots * 100) : 0) ?>%;"></div>
          <div class="vacant" id="vacant-bar" style="width: <?= ($totalSlots ? ($vacant / $totalSlots * 100) : 0) ?>%;"></div>
        </div>
        <div class="status-legend">
          <span id="occupied-count">Occupied: <?= $occupied ?></span>
          <span id="vacant-count">Vacant: <?= $vacant ?></span>
        </div>
      </div>
      <!-- End status-box -->

      <!-- BUTTON LINK -->
      <div class="btn-container">
        <a href="parking_map.php" class="btn btn-primary px-4 py-2">Go to Parking Slot Map</a>
      </div>

    </div>
    <!-- End top-container -->
  </div>
  <!-- End left-section-box -->

  <!-- RIGHT SIDE: Embedded Parking Map -->
  <div class="right-side-map">
    <?php include '../employee/parking_map_embed.php'; ?>
  </div>
</div><!-- End main-content -->

<script>
    // ===== MINI CHART (WALKINS VS RESERVATIONS) =====
    const ctx = document.getElementById('customerChart').getContext('2d');
    let chart;

    function renderChart(w, r, t) {
        if (chart) chart.destroy();
        if (t > 0) {
            chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Walk-ins', 'Reservations'],
                    datasets: [{
                        data: [w, r],
                        backgroundColor: ['#f5d300', '#dc3545'],
                        borderWidth: 0
                    }]
                },
                options: {
                    cutout: '75%',
                    plugins: {
                        legend: { display: false }
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }
    }

    renderChart(<?= $walkins ?>, <?= $reservations ?>, <?= $totalCustomers ?>);

    // === Auto-refresh Data (AJAX) ===
    async function refreshData() {
        try {
            const res = await fetch('fetch_employee_data.php');
            if (!res.ok) return;

            const data = await res.json();

            document.getElementById('walkins-count').textContent = data.walkin;
            document.getElementById('reservations-count').textContent = data.reservations;
            document.getElementById('customers-count').textContent = data.totalCustomers;
            document.getElementById('walkin-percent').textContent = Math.round((data.walkin / Math.max(1, data.totalCustomers)) * 100) + '%';

            document.getElementById('occupied-count').textContent = 'Occupied: ' + data.slotStatus.occupied;
            document.getElementById('vacant-count').textContent = 'Vacant: ' + data.slotStatus.vacant;

            const totalSlots = data.slotStatus.vacant + data.slotStatus.occupied + data.slotStatus.reserved;
            document.getElementById('occupied-bar').style.width = ((data.slotStatus.occupied / Math.max(1, totalSlots)) * 100) + '%';
            document.getElementById('vacant-bar').style.width = ((data.slotStatus.vacant / Math.max(1, totalSlots)) * 100) + '%';

            renderChart(data.walkin, data.reservations, data.totalCustomers);
        } catch (e) {
            console.error(e);
        }
    }

    setInterval(refreshData, 10000);
</script>

<?php include '../templates/footer.php'; ?>
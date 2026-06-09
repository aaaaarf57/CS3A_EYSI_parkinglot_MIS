<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';

if (empty($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: /unauthorized.php");
    exit;
}

$page_title = "Admin Dashboard";
include '../templates/header.php';
include '../templates/sidebar.php';

?>

<!-- UNIVERSAL CONTENT CSS -->
<link rel="stylesheet" href="/static/css/content.css">

<?php
// === FETCH DATA ===
$totalSlots = 20; // Fixed total number of slots
$occupied = $conn->query("SELECT COUNT(*) AS count FROM slots WHERE status='occupied'")->fetch_assoc()['count'] ?? 0;
$vacant = $totalSlots - $occupied;
if ($vacant < 0) $vacant = 0;

$walkins = $conn->query("SELECT COUNT(*) AS count FROM clients WHERE parking_type='walkin'")->fetch_assoc()['count'] ?? 0;
$reservations = $conn->query("SELECT COUNT(*) AS count FROM clients WHERE parking_type='reservation'")->fetch_assoc()['count'] ?? 0;
$totalCustomers = $conn->query("SELECT COUNT(*) AS count FROM clients")->fetch_assoc()['count'] ?? 0;

$activeUsers = $conn->query("
  SELECT a.username, a.role, l.time_in, l.time_out
  FROM login_history l
  JOIN accounts a ON l.account_id = a.id
  WHERE l.time_out IS NULL
  ORDER BY l.time_in DESC
");

$pendingUsers = $conn->query("SELECT * FROM accounts WHERE login_status='pending' ORDER BY created_at DESC");

// Handle Approve / Decline actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $userId = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'approve') {
        $conn->query("UPDATE accounts SET login_status = 'approved' WHERE id = $userId");
        echo "<script>alert('✅ User approved for login this session!'); window.location='dashboard.php';</script>";
        exit();
    } elseif ($action === 'decline') {
        $conn->query("UPDATE accounts SET login_status = 'declined' WHERE id = $userId");
        echo "<script>alert('❌ User login request declined.'); window.location='dashboard.php';</script>";
        exit();
    }
}
?>

<style>
body { background-color: #f2f2f2; }

/* Keep only page-specific design here, not universal spacing */
.section-title {
  font-weight: 600; font-size: 20px; color: #2c2c2c;
  margin: 40px 0 15px; border-left: 5px solid #f5d300; padding-left: 10px;
}

.top-container {
  display: flex; justify-content: space-between; align-items: flex-start;
  margin-bottom: 40px; gap: 40px; flex-wrap: wrap;
}

.total-box {
  flex: 1 1 60%; background: linear-gradient(135deg, #f5d300 0%, #f0f0f0 100%);
  border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  padding: 30px 40px 30px 35px; position: relative; color: #2c2c2c;
  overflow: visible !important; min-width: 320px;
}
.total-box h5 { font-size: 15px; margin: 0; font-weight: 600; color: #2c2c2c; text-transform: uppercase; }
.total-box p { font-size: 22px; font-weight: 800; margin: 4px 0 15px; color: #000; }
.total-car {
  position: absolute; right: -60px; top: 50%; transform: translateY(-40%);
  width: 200px; z-index: 10; filter: drop-shadow(0 8px 12px rgba(0,0,0,0.3));
  pointer-events: none;
}

.status-box {
  flex: 0 0 35%; align-self: stretch; background: linear-gradient(135deg, #f0f0f0 0%, #d9d9d9 100%);
  border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 25px;
  color: #2c2c2c; display: flex; flex-direction: column; justify-content: center; min-width: 260px;
}
.status-box h5 { font-size: 18px; font-weight: 700; margin-bottom: 15px; }
.status-bar { display: flex; height: 25px; border-radius: 6px; overflow: hidden; border: 1px solid #ccc; margin-top: 10px; }
.status-bar .occupied { background-color: #dc3545; height: 100%; }
.status-bar .vacant { background-color: #28a745; height: 100%; }
.status-legend { display: flex; justify-content: space-between; font-weight: 500; margin-top: 8px; color: #333; }

@media (max-width: 992px) {
  .top-container { flex-direction: column; gap: 20px; width: 100%; padding: 0; }
  .total-box, .status-box { flex: 1 1 100%; width: 100%; border-radius: 12px; margin: 0; box-sizing: border-box; }
  .total-box { padding: 25px 30px; }
  .status-box { padding: 25px 30px; }
  .total-car { right: -55px; top: 60%; transform: translateY(-50%); width: 200px; }
}

.table-container {
  background: #fff; border-radius: 12px; padding: 20px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.08); margin-bottom: 30px;
}
.table { font-size: 15px; }
.table thead th { background-color: #f9f9f9; color: #2c2c2c; font-weight: 600; }
.table tbody tr:hover { background-color: #fffce6; }

/* Popup */
.popup-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9998;
}
.popup-box {
  position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%);
  background: #fff; padding: 30px 40px; border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.3); text-align: center; z-index: 9999;
}
.popup-box h4 { margin: 0 0 15px; font-weight: 700; color: #333; }
.popup-box a, .popup-box button {
  background: #f5d300; padding: 10px 20px; border-radius: 8px; font-weight: 600;
  text-decoration: none; color: #000; transition: 0.2s; border: none; cursor: pointer;
}
.popup-box a:hover, .popup-box button:hover { background: #d1b900; }

.chart-container {
  position: absolute;
  right: 15vw;            /* use viewport width for flexible horizontal spacing */
  bottom: 9vh;           /* use viewport height for flexible vertical spacing */
  width: clamp(120px, 25vw, 170px);  /* responsive width between 70–120px */
  height: clamp(120px, 25vw, 170px); /* responsive height between 70–120px */
  background: #fff;
  border-radius: 50%;
  box-shadow: 0 3px 10px rgba(0,0,0,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 15;
  transition: all 0.3s ease-in-out;
}

.chart-container canvas {
  position: absolute;
  top: 0;
  left: 0;
  width: 100% !important;
  height: 100% !important;
}

.chart-label {
  position: relative;
  text-align: center;
  font-size: clamp(10px, 1vw, 14px);
  color: #2c2c2c;
}

.chart-label strong {
  display: block;
  font-size: clamp(14px, 1.5vw, 20px);
  color: #000;
}

/* 🧠 Responsive tuning for small screens */
@media (max-width: 992px) {
  .chart-container {
    right: 23vw;
    bottom: 10vh;
    width: clamp(100px, 23vw, 140px);
    height: clamp(100px, 23vw, 140px);
  }
}

@media (max-width: 600px) {
  .chart-container {
    position: relative;   /* becomes inline instead of absolute */
    margin-top: 15px;
    right: auto;
    bottom: auto;
    align-self: center;
  }
}

</style>

<div class="main-content">

  <!-- TOP CONTAINER -->
  <div class="top-container">
    <!-- LEFT BOX -->
    <div class="total-box">
      <h5>Total Walk-ins</h5>
      <p><?= $walkins ?></p>
      <h5>Total Reservations</h5>
      <p><?= $reservations ?></p>
      <h5>Total Customers</h5>
      <p><?= $totalCustomers ?></p>
      <img src="/static/car-side.png" alt="Car" class="total-car">
      
      <!-- Mini Chart -->
<div class="chart-container">
  <canvas id="customerChart"></canvas>
  <div class="chart-label">
    <strong><?= round(($walkins / max(1, $totalCustomers)) * 100) ?>%</strong>
    <span>Walk-ins</span>
  </div>
</div>

    </div>


    <!-- RIGHT BOX -->
    <div class="status-box">
      <h5>Parking Status (Total)</h5>
      <div class="status-bar">
        <div class="occupied" style="width: <?= ($totalSlots ? ($occupied / $totalSlots * 100) : 0) ?>%;"></div>
        <div class="vacant" style="width: <?= ($totalSlots ? ($vacant / $totalSlots * 100) : 0) ?>%;"></div>
      </div>
      <div class="status-legend">
        <span>Occupied: <?= $occupied ?></span>
        <span>Vacant: <?= $vacant ?></span>
      </div>
    </div>
  </div>

  <!-- ACTIVE USERS -->
  <div style="display: flex; justify-content: space-between; align-items: center;">
    <h2 class="section-title">Active Users</h2>
    <a href="login_history.php" class="btn btn-outline-dark" 
       style="background:#f5d300; color:#000; font-weight:600; padding:8px 16px; border-radius:8px; text-decoration:none;">
       View History
    </a>
  </div>

  <div id="active-users-container" class="table-container">
    <p>Loading active users...</p>
  </div>

  <!-- PENDING LOGIN REQUEST -->
  <h2 id="pending-requests" class="section-title">Pending Login Requests</h2>
  <div id="pending-requests-container" class="table-container">
    <p>Loading pending requests...</p>
  </div>

</div>

<script>
// ===== AUTO REFRESH DASHBOARD SECTIONS =====
function loadActiveUsers() {
  fetch('fetch_active_users.php')
    .then(res => res.text())
    .then(html => document.querySelector('#active-users-container').innerHTML = html)
    .catch(err => console.error('Error refreshing active users:', err));
}

function loadPendingRequests() {
  fetch('fetch_pending_requests.php')
    .then(res => res.text())
    .then(html => document.querySelector('#pending-requests-container').innerHTML = html)
    .catch(err => console.error('Error refreshing pending requests:', err));
}

// Popup notifier
let lastPendingCount = 0;
function checkNewRequests() {
  fetch('check_pending.php')
    .then(res => res.text())
    .then(count => {
      const num = parseInt(count);
      if (num > lastPendingCount) showApprovalPopup(num);
      lastPendingCount = num;
    });
}

function showApprovalPopup(count) {
  if (document.querySelector('#approval-popup')) return;
  const popup = document.createElement('div');
  popup.id = 'approval-popup';
  popup.innerHTML = `
    <div class="popup-overlay" onclick="this.parentElement.remove()"></div>
    <div class="popup-box">
      <h4>🔔 ${count} user(s) requesting login approval</h4>
      <button onclick="scrollToPending()">View Requests</button>
    </div>`;
  document.body.appendChild(popup);
}

function scrollToPending() {
  const section = document.getElementById('pending-requests');
  if (section) {
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    section.style.transition = 'background-color 0.6s ease';
    section.style.backgroundColor = '#fff7b3';
    setTimeout(() => section.style.backgroundColor = 'transparent', 2000);
  }
  const popup = document.getElementById('approval-popup');
  if (popup) popup.remove();
}

// Initial load + repeat
loadActiveUsers();
loadPendingRequests();
setInterval(() => {
  loadActiveUsers();
  loadPendingRequests();
  checkNewRequests();
}, 5000);

// ===== MINI CHART (WALKINS VS RESERVATIONS) =====
const ctx = document.getElementById('customerChart').getContext('2d');
const total = <?= $totalCustomers ?>;
const walkins = <?= $walkins ?>;
const reservations = <?= $reservations ?>;

if (total > 0) {
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Walk-ins', 'Reservations'],
      datasets: [{
        data: [walkins, reservations],
        backgroundColor: ['#f5d300', '#dc3545'],
        borderWidth: 0,
      }]
    },
    options: {
      cutout: '75%',
      plugins: { legend: { display: false } },
      animation: { duration: 1000, easing: 'easeOutQuart' }
    }
  });
}

</script>

<?php include '../templates/footer.php'; ?>

<?php
// templates/sidebar.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

$role = $_SESSION['role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Guest';
$profile_photo = '/assets/img/default_profile.png';

// fetch photo from DB
if ($user_id) {
    $stmt = $conn->prepare("SELECT profile_photo FROM accounts WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!empty($row['profile_photo'])) {
        $profile_photo = '/uploads/profile_photos/' . htmlspecialchars($row['profile_photo']);
    }
}

?>

<style>
/* ===== SIDEBAR BASE ===== */
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  height: 100%;
  width: 70px;
  background-color: #2c2c2c;
  background-image: url('/static/eyy.png');
  background-repeat: no-repeat;
  background-position: center 140%;
  background-size: 420px auto;
  color: white;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: width 0.3s ease, background-size 0.5s ease;
  z-index: 200;
}

.sidebar::before {
  content: "";
  position: absolute;
  inset: 0;
  background-color: rgba(44, 44, 44, 0.85);
  transition: background-color 0.4s ease;
  z-index: 0;
}

.sidebar:hover::before { background-color: rgba(44, 44, 44, 0.7); }
.sidebar * { position: relative; z-index: 1; }
.sidebar:hover { width: 240px; background-size: 440px auto; }

/* ===== USER SECTION (NEW) ===== */
.user-section {
  text-align: center;
  padding: 20px 10px 10px;
  border-bottom: 1px solid #3a3a3a;
}

.user-section img {
  width: 55px;
  height: 55px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #f5d300;
  transition: all 0.3s ease;
}

.user-section h4 {
  font-size: 14px;
  font-weight: 600;
  margin: 8px 0 2px;
  opacity: 0;
  white-space: nowrap;
  transition: opacity 0.3s ease;
  color: #f1f1f1;
}

.user-section small {
  font-size: 12px;
  color: #aaa;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.sidebar:hover .user-section h4,
.sidebar:hover .user-section small {
  opacity: 1;
}

/* ===== LOGO SECTION ===== */
.logo-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100px;
  border-bottom: 1px solid #3a3a3a;
  text-align: center;
  margin-top: 5px;
  transition: all 0.3s ease;
}

.sidebar:hover .logo-section {
  flex-direction: row;
  justify-content: flex-start;
  padding-left: 25px;
}

.logo-section img {
  width: 60px;
  height: 60px;
  object-fit: contain;
  transition: all 0.3s ease;
}

.logo-section h2 {
  font-size: 15px;
  font-weight: 600;
  color: #f5d300;
  margin: 0;
  margin-left: 10px;
  opacity: 0;
  white-space: nowrap;
  transition: opacity 0.3s ease;
}

.sidebar:hover .logo-section h2 { opacity: 1; }

/* ===== MENU LINKS ===== */
.sidebar ul {
  list-style: none;
  padding: 0;
  margin-top: 10px;
  flex-grow: 1;
}

.sidebar ul li { margin: 5px 0; }

.sidebar ul li a {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  color: #f1f1f1;
  padding: 12px 20px;
  border-radius: 8px;
  transition: background 0.3s, color 0.3s, padding-left 0.3s;
  white-space: nowrap;
}

.sidebar ul li a:hover {
  background-color: #f5d300;
  color: #000;
}

.sidebar ul li a i {
  font-size: 20px;
  min-width: 25px;
  text-align: center;
}

.sidebar ul li a span {
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.3s ease;
}

.sidebar:hover ul li a span {
  opacity: 1;
  visibility: visible;
}

/* ===== RESPONSIVE (MOBILE) ===== */
@media (max-width: 992px) {
  .sidebar {
    width: 240px !important;
    left: -240px;
    transition: left 0.3s ease;
    box-shadow: 0 0 12px rgba(0, 0, 0, 0.4);
    background-size: 420px auto !important;
  }

  .sidebar.active { left: 0 !important; }
  .sidebar:hover { width: 240px !important; }

  .sidebar .logo-section {
    flex-direction: row !important;
    justify-content: flex-start !important;
    align-items: center !important;
    padding-left: 20px !important;
    height: 80px !important;
    border-bottom: 1px solid #3a3a3a !important;
    margin: 0 !important;
    gap: 10px !important;
  }

  .sidebar .logo-section img { width: 50px !important; height: 50px !important; }
  .sidebar .logo-section h2 { opacity: 1 !important; font-size: 15px !important; }
  .sidebar ul li a span { opacity: 1 !important; visibility: visible !important; }

  .user-section h4, .user-section small { opacity: 1 !important; }
}

/* ===== HIDE HAMBURGER ON DESKTOP ===== */
@media (min-width: 993px) {
  #toggleBtn { display: none !important; }
}
</style>

<!-- ===== SIDEBAR STRUCTURE ===== -->
<aside class="sidebar" id="sidebar">
  <div class="logo-section">
    <img src="/static/logo.png" alt="Logo">
    <h2>Eysi Parking Lot</h2>
  </div>

  <div class="user-section">
    <img src="<?php echo $profile_photo; ?>" alt="Profile">
    <h4><?php echo htmlspecialchars($username); ?></h4>
    <small><?php echo ucfirst(htmlspecialchars($role)); ?></small>
  </div>

<ul>
  <?php if ($role === 'admin'): ?>
    <li><a href="/admin/dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a></li>
    <li><a href="/profile.php"><i class="bi bi-person-circle"></i><span>Profile</span></a></li>
    <li><a href="/admin/user_registration.php"><i class="bi bi-person-plus"></i><span>User Registration</span></a></li>
    <li><a href="/admin/reports.php"><i class="bi bi-bar-chart"></i><span>Reports</span></a></li>
    <li><a href="/admin/parking_slot_map.php"><i class="bi bi-map"></i><span>Parking Slot Map</span></a></li>
    <li><a href="/admin/auth_trail.php"><i class="bi bi-clock-history"></i><span>Auth Trail</span></a></li>

  <?php elseif ($role === 'owner'): ?>
    <li><a href="/owner/dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a></li>
    <li><a href="/owner/reports.php"><i class="bi bi-bar-chart"></i><span>Reports</span></a></li>
    <li><a href="/owner/employee_management.php"><i class="bi bi-people"></i><span>Employees</span></a></li>
    <li><a href="/owner/auth_trail.php"><i class="bi bi-clock-history"></i><span>Auth Trail</span></a></li>
    <li><a href="/profile.php"><i class="bi bi-person-circle"></i><span>Profile</span></a></li>

  <?php elseif ($role === 'employee'): ?>
    <li><a href="/employee/dashboard.php"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a></li>
    <li><a href="/employee/client_records.php"><i class="bi bi-file-earmark-text"></i><span>Client Records</span></a></li>
    <li><a href="/employee/parking_map.php"><i class="bi bi-map"></i><span>Parking Slot Map</span></a></li>
    <li><a href="/profile.php"><i class="bi bi-person-circle"></i><span>Profile</span></a></li>

  <?php elseif ($role === 'security_guard'): ?>
    <li><a href="/security_guard/parking_slot_map.php"><i class="bi bi-map"></i><span>Parking Slot Map</span></a></li>
    <li><a href="/security_guard/client_reports.php"><i class="bi bi-journal-text"></i><span>Client Reports</span></a></li>
    <li><a href="/profile.php"><i class="bi bi-person-circle"></i><span>Profile</span></a></li>

  <?php else: ?>
    <li><a href="/login.php"><i class="bi bi-box-arrow-in-right"></i><span>Login</span></a></li>
  <?php endif; ?>

  <li><a href="/logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></li>
</ul>

</aside>

<div id="overlay"></div>

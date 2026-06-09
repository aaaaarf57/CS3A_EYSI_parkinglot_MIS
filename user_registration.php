<?php
ob_start(); // Prevent accidental output before session start

require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';

// ✅ Only allow admin users
if (empty($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: /unauthorized.php");
    exit;
}

// ✅ Always use Philippine timezone
date_default_timezone_set('Asia/Manila');

$page_title = "Login History";
include '../templates/header.php';
include '../templates/sidebar.php';
?>

<!-- UNIVERSAL CONTENT CSS -->
<link rel="stylesheet" href="/static/css/content.css">

<?php
// 🕓 Get selected date (default: today, based on Manila time)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// 🧠 Adjust query to use Manila time even though Hostinger runs UTC
$stmt = $conn->prepare("
  SELECT a.username, a.role, l.time_in, l.time_out
  FROM login_history l
  JOIN accounts a ON l.account_id = a.id
  WHERE DATE(CONVERT_TZ(l.time_in, '+00:00', '+08:00')) = ?
  ORDER BY l.time_in DESC
");
$stmt->bind_param("s", $selectedDate);
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
/* existing CSS unchanged */
body {
  background: #f4f4f4;
  font-family: 'Poppins', sans-serif;
  margin: 0;
}
h2.section-title {
  font-weight: 600;
  font-size: 22px;
  color: #2c2c2c;
  border-left: 5px solid #f5d300;
  padding-left: 10px;
  margin-bottom: 20px;
}
.date-filter {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 20px;
}
.date-filter form {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}
.date-filter input[type="date"] {
  padding: 8px 12px;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 15px;
  background-color: #fff;
}
.btn-yellow {
  background: #f5d300;
  color: #000;
  font-weight: 600;
  padding: 8px 16px;
  border-radius: 8px;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: 0.2s ease;
}
.btn-yellow:hover { background: #d1b900; }
.back-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
}
.table-container {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
  overflow-x: auto;
  scrollbar-width: thin;
}
.table-container::-webkit-scrollbar { height: 8px; }
.table-container::-webkit-scrollbar-thumb {
  background: #d1b900;
  border-radius: 4px;
}
.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 15px;
  min-width: 600px;
}
.table thead th {
  background-color: #f9f9f9;
  color: #2c2c2c;
  font-weight: 600;
  text-align: left;
  padding: 12px 10px;
  border-bottom: 2px solid #f0f0f0;
  white-space: nowrap;
}
.table tbody td {
  padding: 12px 10px;
  border-bottom: 1px solid #eee;
  vertical-align: middle;
}
.table tbody tr:hover {
  background-color: #fffce6;
  transition: 0.3s ease;
}
@media (max-width: 600px) {
  .date-filter {
    flex-direction: column;
    align-items: flex-start;
  }
  .date-filter form {
    width: 100%;
  }
  .btn-yellow, .back-btn {
    width: 100%;
    text-align: center;
  }
  h2.section-title {
    font-size: 18px;
  }
}
</style>

<div class="main-content">
  <h2 class="section-title">Login History</h2>

  <!-- DATE FILTER + BACK BUTTON -->
  <div class="date-filter">
    <form method="GET">
      <label for="date">Select Date:</label>
      <input type="date" name="date" id="date" value="<?= htmlspecialchars($selectedDate) ?>">
      <button type="submit" class="btn-yellow">Filter</button>
    </form>

    <a href="dashboard.php" class="btn-yellow back-btn">⬅ Back to Dashboard</a>
  </div>

  <!-- LOGIN HISTORY TABLE -->
  <div class="table-container">
    <?php if ($result->num_rows > 0): ?>
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Username</th>
            <th>Role</th>
            <th>Time In</th>
            <th>Time Out</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td><?= ucfirst($row['role']) ?></td>
              <td><?= date('M d, Y h:i A', strtotime($row['time_in'] . ' +8 hours')) ?></td>
              <td>
                <?= $row['time_out'] 
                    ? date('M d, Y h:i A', strtotime($row['time_out'] . ' +8 hours')) 
                    : '<em>— Still Active —</em>' ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="text-muted mb-0">No login activity found for this date.</p>
    <?php endif; ?>
  </div>
</div>

<?php include '../templates/footer.php'; ?>

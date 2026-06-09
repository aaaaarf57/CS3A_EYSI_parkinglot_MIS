<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

if (empty($_SESSION['username']) || !in_array($_SESSION['role'], ['employee', 'admin'])) {
    header("Location: /unauthorized.php");
    exit();
}

$page_title = "Client Records";
include '../templates/header.php';
include '../templates/sidebar.php';

// Get logged-in employee ID
$userStmt = $conn->prepare("SELECT id FROM accounts WHERE username = ?");
$userStmt->bind_param("s", $_SESSION['username']);
$userStmt->execute();
$userRes = $userStmt->get_result()->fetch_assoc();
$created_by = $userRes['id'] ?? null;
$userStmt->close();

// 🧾 Handle Add Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client'])) {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $plate = trim($_POST['plate']);
    $type = trim($_POST['parking_type']);

    if ($name && $type && $created_by) {
        // ✅ Use Manila time even on Hostinger (UTC)
        $stmt = $conn->prepare("
            INSERT INTO clients (name, contact, vehicle_plate, parking_type, time_in, created_by, created_at)
            VALUES (?, ?, ?, ?, (UTC_TIMESTAMP() + INTERVAL 8 HOUR), ?, (UTC_TIMESTAMP() + INTERVAL 8 HOUR))
        ");
        $stmt->bind_param('ssssi', $name, $contact, $plate, $type, $created_by);
        $stmt->execute();
        $newClientId = $stmt->insert_id;
        $stmt->close();

        // ✅ Redirect directly to parking map with the new client preselected
        echo "<script>
            alert('Client added successfully! Redirecting to parking map...');
            window.location.href = '/employee/parking_map.php?client_id={$newClientId}';
        </script>";
        exit();
    } else {
        echo "<script>alert('Error: Missing required fields.');</script>";
    }
}

// 📋 Fetch Clients
$clients = $conn->query("
    SELECT 
        c.id, c.name, c.contact, c.vehicle_plate, c.parking_type, 
        c.time_in, c.time_out, c.total_cost, s.slot_code
    FROM clients c
    LEFT JOIN slots s ON c.slot_id = s.id
    ORDER BY c.time_in DESC
");
?>

<link rel="stylesheet" href="/static/css/content.css">

<div class="content p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Client Records</h2>
    <a href="parking_map.php" class="btn btn-primary">Go to Parking Slot Map</a>
  </div>

  <!-- Add Client Form -->
  <div class="card p-3 mb-4 shadow-sm">
    <h5 class="mb-3">Add New Client</h5>
    <form method="POST" class="row g-2">
      <div class="col-md-3">
        <input type="text" name="name" class="form-control" placeholder="Client Name" required>
      </div>
      <div class="col-md-2">
        <input type="text" name="contact" class="form-control" placeholder="Contact No. (optional)">
      </div>
      <div class="col-md-2">
        <input type="text" name="plate" class="form-control" placeholder="Vehicle Plate" required>
      </div>
      <div class="col-md-3">
        <select name="parking_type" class="form-select" required>
          <option value="">Select Parking Type</option>
          <option value="walkin">Walk-in</option>
          <option value="reservation">Reservation</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" name="add_client" class="btn btn-success w-100">Add Client</button>
      </div>
    </form>
  </div>

  <!-- Client List -->
  <div class="card p-3 shadow-sm">
    <h5>All Clients</h5>
    <div class="table-responsive mt-3">
      <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Contact</th>
            <th>Plate</th>
            <th>Type</th>
            <th>Slot</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Total Cost</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $clients->fetch_assoc()): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['contact'] ?: '-') ?></td>
              <td><?= htmlspecialchars($row['vehicle_plate'] ?: '-') ?></td>
              <td><?= ucfirst($row['parking_type']) ?></td>
              <td><?= htmlspecialchars($row['slot_code'] ?: '-') ?></td>
              <td><?= $row['time_in'] ? date('M d, Y h:i A', strtotime($row['time_in'])) : '-' ?></td>
              <td><?= $row['time_out'] ? date('M d, Y h:i A', strtotime($row['time_out'])) : '-' ?></td>
              <td>₱<?= number_format($row['total_cost'], 2) ?></td>
              <td>
                <?php if ($row['time_out']): ?>
                  <span class="badge bg-secondary">Exited</span>
                <?php else: ?>
                  <span class="badge bg-success">Active</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$row['time_out']): ?>
                  <a href="/employee/mark_exit.php?client_id=<?= $row['id'] ?>" 
                     class="btn btn-danger btn-sm">Mark Exit</a>
                <?php else: ?>
                  <a href="/employee/exit_ticket.php?client_id=<?= $row['id'] ?>" 
                     class="btn btn-secondary btn-sm">Reprint</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../templates/footer.php'; ?>

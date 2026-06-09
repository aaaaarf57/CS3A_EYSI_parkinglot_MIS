<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

// ✅ Always use Philippine timezone
date_default_timezone_set('Asia/Manila');

// ✅ Access control
if (empty($_SESSION['username']) || !in_array($_SESSION['role'], ['employee', 'admin'])) {
    header("Location: /unauthorized.php");
    exit();
}

$client_id = $_GET['client_id'] ?? 0;
if (!$client_id) die("Missing client ID");

// 🧠 Fetch client and slot details
$stmt = $conn->prepare("
    SELECT c.name, c.vehicle_plate, c.time_in, c.time_out, c.total_cost, c.parking_type, s.slot_code
    FROM clients c
    LEFT JOIN slots s ON c.slot_id = s.id
    WHERE c.id = ?
");
$stmt->bind_param('i', $client_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) die("Client not found");

// 🕓 Time calculations
$time_in  = new DateTime($data['time_in'], new DateTimeZone('Asia/Manila'));
$time_out = new DateTime($data['time_out'] ?? 'now', new DateTimeZone('Asia/Manila'));
$diff = $time_in->diff($time_out);
$total_hours = max(1, ceil($diff->h + ($diff->i / 60)));

// 💰 Costing Logic
$parking_type = strtolower($data['parking_type']);
$cost = 0;

if ($parking_type === 'reservation') {
    if ($total_hours <= 1) {
        $cost = 70; // first hour only
    } else {
        $cost = 70 + (($total_hours - 1) * 50); // ₱70 first hr, ₱50 next
    }
} else {
    // Walk-in = ₱50/hr
    $cost = $total_hours * 50;
}

// 🧾 Update database with time_out and total_cost
$update = $conn->prepare("UPDATE clients SET time_out = ?, total_cost = ? WHERE id = ?");
$formatted_time_out = $time_out->format('Y-m-d H:i:s');
$update->bind_param('sdi', $formatted_time_out, $cost, $client_id);
$update->execute();
$update->close();

$employee = htmlspecialchars($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Exit Ticket</title>
<style>
  @page { size: 80mm auto; margin: 0; }
  body { font-family: 'Courier New', monospace; font-size: 12px; padding: 10px; width: 80mm; }
  h2 { font-size: 15px; margin: 0; text-align: center; }
  .line { border-top: 1px dashed #000; margin: 6px 0; }
  .info p { margin: 2px 0; line-height: 1.3em; }
  .footer { margin-top: 8px; font-size: 11px; text-align: center; }
</style>
</head>
<body onload="window.print(); setTimeout(()=>window.close(), 1000);">
  <h2>EYSI PARKING LOT</h2>
  <center><small>Exit Receipt</small></center>
  <div class="line"></div>
  <div class="info">
    <p><b>Client:</b> <?= htmlspecialchars($data['name']) ?></p>
    <p><b>Plate:</b> <?= htmlspecialchars($data['vehicle_plate']) ?></p>
    <p><b>Slot:</b> <?= htmlspecialchars($data['slot_code'] ?: '-') ?></p>
    <p><b>Type:</b> <?= ucfirst($parking_type) ?></p>
    <p><b>Time In:</b> <?= $time_in->format('M d, Y h:i A') ?></p>
    <p><b>Time Out:</b> <?= $time_out->format('M d, Y h:i A') ?></p>
    <p><b>Duration:</b> <?= $total_hours ?> hr(s)</p>
    <p><b>Total:</b> ₱<?= number_format($cost, 2) ?></p>
    <p><b>Processed by:</b> <?= $employee ?></p>
  </div>
  <div class="line"></div>
  <div class="footer">
    Thank you! Drive safely.<br>
    <?= date('M d, Y h:i A') ?>
  </div>
</body>
</html>

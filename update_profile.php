<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

// ✅ Always use Philippine timezone for display
date_default_timezone_set('Asia/Manila');

// Allow employee or admin only
if (empty($_SESSION['username']) || !in_array($_SESSION['role'], ['employee', 'admin'])) {
    header("Location: /unauthorized.php");
    exit();
}

$ticket_id = $_GET['ticket_id'] ?? '';
if (!$ticket_id) die("Invalid ticket ID");

// Extract client_id from ticket format: TICKET-<timestamp>-<client_id>
$parts = explode('-', $ticket_id);
$client_id = end($parts);

// Fetch client and slot info
$stmt = $conn->prepare("
    SELECT 
        c.name AS client_name, 
        c.vehicle_plate, 
        c.parking_type, 
        c.time_in, 
        s.slot_code
    FROM clients c
    LEFT JOIN slots s ON c.slot_id = s.id
    WHERE c.id = ?
");
$stmt->bind_param('i', $client_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) die("Client not found");

$client_name   = htmlspecialchars($data['client_name']);
$vehicle_plate = htmlspecialchars($data['vehicle_plate']);
$slot_code     = htmlspecialchars($data['slot_code']);
$parking_type  = strtoupper($data['parking_type']);

// 🧭 Determine car rotation based on slot position
$rotation = '0deg'; // default facing up

if (in_array($slot_code, ['P1','P2','P3','P4'])) {
    $rotation = '180deg';
} elseif (in_array($slot_code, ['P5','P6','P7','P8','P9','P10','P11','P12'])) {
    $rotation = '90deg';
} elseif (in_array($slot_code, ['P13','P14','P15','P16'])) {
    $rotation = '-90deg';
} elseif (in_array($slot_code, ['P17','P18','P19','P20'])) {
    $rotation = '0deg';
}

// ✅ Format time_in correctly for Asia/Manila
$time_in = new DateTime($data['time_in'], new DateTimeZone('Asia/Manila'));
$employee = htmlspecialchars($_SESSION['username']);
$current_time = date('M d, Y h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Parking Ticket - <?= $ticket_id ?></title>
<style>
  /* 🧾 Thermal Print Layout (80mm width) */
  @page { size: 80mm auto; margin: 0; }
  body {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    margin: 0;
    padding: 10px;
    width: 80mm;
    color: #000;
  }
  .ticket { text-align: center; width: 100%; }
  h2 { font-size: 15px; margin-bottom: 2px; }
  small { font-size: 11px; }
  .line { border-top: 1px dashed #000; margin: 6px 0; }

  .info {
    text-align: left;
    margin-top: 5px;
    position: relative;
  }

  .info p { margin: 2px 0; line-height: 1.2em; padding-left: 5px; }
  .footer { margin-top: 8px; font-size: 11px; text-align: center; }

  /*LOGO*/
  .logo {
    width: 60px;
    height: auto;
    margin-bottom: 5px;
  }

  @media print {
    body { margin: 0; padding: 0; width: 80mm; }
  }

  /* === MINI PARKING MAP (BLACK & WHITE) === */
.mini-print-map {
  zoom: 0.8;
  transform-origin: top center;
  margin-top: 12px;
  margin-bottom: 8px;
}

/* Neutral scheme (white base, black highlight) */
.lot-area {
  background: #ffffff;
  border-radius: 8px;
  padding: 20px;
  box-shadow: inset 0 0 2px rgba(0,0,0,0.1);
}

/* === SLOT BASE STYLE === */
.slot,
.slotmsec,
.slotbot {
  border-radius: 8px;
  background: #fff; /* ✅ white instead of gray */
  border: 1px solid #777;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  margin: 4px;
}

.slot { width: 50px; height: 80px; }
.slotmsec { width: 80px; height: 47px; }
.slotbot { width: 50px; height: 80px; }

/* ✅ Highlighted slot: black with white text */
.slot.highlight,
.slotmsec.highlight,
.slotbot.highlight {
  background: #000;
  border: 2px solid #000;
}

.slot.highlight .code,
.slotmsec.highlight .code,
.slotbot.highlight .code {
  color: #fff;
}

/* Slot text */
.slot .code,
.slotmsec .code,
.slotbot .code {
  position: absolute;
  bottom: 6px;
  font-size: 10px;
  font-weight: bold;
  color: #222;
}

/* Car image */
img.car {
  position: absolute;
  opacity: 0.8;
  filter: grayscale(100%) contrast(1.2);
}

/* Car sizing */
.slot img.car { width: 60px; }
.slotmsec img.car { width: 50px; }
.slotbot img.car { width: 50px; }

/* 🧭 Adjust car position depending on rotation (all sections) */
img.car[style*="rotate(90deg)"] {
  top: -15px;
  left: 15px;
}
img.car[style*="rotate(-90deg)"] {
  top: -15px;
  right: 15px;
}
img.car[style*="rotate(180deg)"] {
  top: -5px;
}
img.car[style*="rotate(0deg)"] {
  top: 1px;
}

/* Layout sections */
.top-row, .bottom-row {
  display: flex;
  justify-content: center;
  gap: 8px;
}
.bottom-row { margin-top: 20px; }
.middle-container {
  display: flex;
  justify-content: space-around;
  align-items: center;
  margin: 10px 0;
}
.left-section, .middle-section, .right-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}
.bottom-left, .bottom-right {
  display: flex;
  gap: 8px;
}
.bottom-left { margin-right: 40px; }
.bottom-right { margin-left: 40px; }
.lane { text-align: center; color: #555; font-size: 10px; margin: 4px 0; }
.up-down-lane { font-size: 9px; color: #555; }

.map-wrapper {
  display: inline-block;
  margin: 0 auto;
  background: #fff;
  padding: 12px;
  border-radius: 10px;
}
/* Center the entire parking map area */
.map-scale-container {
  display: flex;
  justify-content: center;
  align-items: center;
  text-align: center;
  width: 100%;
}

/* Center inner content more neatly */
.map-wrapper {
  display: inline-block;
  margin: 0 auto;
  background: #fff;
  padding: 12px;
  border-radius: 10px;
}

</style>
</head>
<body onload="window.print(); setTimeout(()=>window.close(), 1000);">

<div class="ticket">
  <img src="/static/logo.png" class="logo">
  <h2>EYSI PARKING LOT</h2>
  <small>Official Parking Receipt<br></small>
  <small><?= $current_time ?></small>
  <div class="line"></div>

  <div class="info">
    <p><b>Ticket ID:</b> <?= $ticket_id ?></p>
    <p><b>Client:</b> <?= $client_name ?></p>
    <p><b>Plate:</b> <?= $vehicle_plate ?></p>
    <p><b>Slot:</b> <?= $slot_code ?></p>
    <p><b>Type:</b> <?= $parking_type ?></p>
    <p><b>Time In:</b> <?= $time_in->format('M d, Y h:i A') ?></p>
    <p><b>Handled By:</b> <?= $employee ?></p>
  </div>

  <div class="line"></div>
  <div class="footer">
    Thank you for parking with us!<br>
    Please keep this ticket for exit validation.
  </div>
</div>
<div class="line"></div>
<h3 style="text-align:center; margin:8px 0;">Your Parking Slot</h3>

<div class="map-scale-container mini-print-map">
  <div class="map-wrapper">
    <div class="lot-area">

      <!-- TOP ROW -->
      <div class="top-row">
        <?php foreach (['P1','P2','P3','P4'] as $slot): ?>
          <div class="slot<?= $slot === $slot_code ? ' highlight' : '' ?>">
            <?php if ($slot === $slot_code): ?>
              <img src="/static/images/red_car.png" class="car" style="transform: rotate(<?= $rotation ?>);">
            <?php endif; ?>
            <span class="code"><?= $slot ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="lane">⬅️ ➡️</div>

      <!-- MIDDLE SECTION -->
      <div class="middle-container">
        <div class="left-section">
          <?php foreach (['P5','P6','P7','P8'] as $slot): ?>
            <div class="slotmsec<?= $slot === $slot_code ? ' highlight' : '' ?>">
              <?php if ($slot === $slot_code): ?>
                <img src="/static/images/red_car.png" class="car" style="transform: rotate(<?= $rotation ?>);">
              <?php endif; ?>
              <span class="code"><?= $slot ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="up-down-lane">⬆️⬇️</div>

        <div class="middle-section">
          <?php foreach (['P9','P10','P11','P12'] as $slot): ?>
            <div class="slotmsec<?= $slot === $slot_code ? ' highlight' : '' ?>">
              <?php if ($slot === $slot_code): ?>
                <img src="/static/images/red_car.png" class="car" style="transform: rotate(<?= $rotation ?>);">
              <?php endif; ?>
              <span class="code"><?= $slot ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="up-down-lane">⬆️⬇️</div>

        <div class="right-section">
          <?php foreach (['P13','P14','P15','P16'] as $slot): ?>
            <div class="slotmsec<?= $slot === $slot_code ? ' highlight' : '' ?>">
              <?php if ($slot === $slot_code): ?>
                <img src="/static/images/red_car.png" class="car" style="transform: rotate(<?= $rotation ?>);">
              <?php endif; ?>
              <span class="code"><?= $slot ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="lane">⬅️ ➡️</div>

      <!-- BOTTOM ROW -->
      <div class="bottom-row">
        <div class="bottom-left">
          <?php foreach (['P17','P18'] as $slot): ?>
            <div class="slotbot<?= $slot === $slot_code ? ' highlight' : '' ?>">
              <?php if ($slot === $slot_code): ?>
                <img src="/static/images/red_car.png" class="car" style="transform: rotate(<?= $rotation ?>);">
              <?php endif; ?>
              <span class="code"><?= $slot ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="bottom-right">
          <?php foreach (['P19','P20'] as $slot): ?>
            <div class="slotbot<?= $slot === $slot_code ? ' highlight' : '' ?>">
              <?php if ($slot === $slot_code): ?>
                <img src="/static/images/red_car.png" class="car" style="transform: rotate(<?= $rotation ?>);">
              <?php endif; ?>
              <span class="code"><?= $slot ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </div>
</div>

</body>
</html>

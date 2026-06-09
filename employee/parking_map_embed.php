<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

$ticket_id = $_GET['ticket_id'] ?? '';
if (!$ticket_id) die("Invalid ticket ID");

$parts = explode('-', $ticket_id);
$client_id = end($parts);

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

// 🧭 Rotation based on position
$rotation = '0deg';
if (in_array($slot_code, ['P1','P2','P3','P4'])) $rotation = '180deg';
elseif (in_array($slot_code, ['P5','P6','P7','P8','P9','P10','P11','P12'])) $rotation = '90deg';
elseif (in_array($slot_code, ['P13','P14','P15','P16'])) $rotation = '-90deg';
elseif (in_array($slot_code, ['P17','P18','P19','P20'])) $rotation = '0deg';

$time_in = new DateTime($data['time_in'], new DateTimeZone('Asia/Manila'));
$current_time = date('M d, Y h:i A');
$employee = htmlspecialchars($_SESSION['username'] ?? 'N/A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Preview Parking Ticket - <?= $ticket_id ?></title>
<style>
  body {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    background: #f0f0f0;
    padding: 25px;
    color: #000;
  }
  .ticket { text-align: center; width: 100%; }
  h2 { font-size: 18px; margin-bottom: 4px; }
  small { font-size: 12px; }
  .line { border-top: 1px dashed #000; margin: 6px 0; }
  .info { text-align: left; margin-top: 8px; }
  .info p { margin: 2px 0; line-height: 1.4em; padding-left: 5px; }
  .footer { margin-top: 8px; font-size: 11px; text-align: center; }
  .logo { width: 70px; height: auto; margin-bottom: 6px; }

  /* === MINI PARKING MAP === */
  .mini-print-map {
    zoom: 1.2;
    transform-origin: top center;
    margin-top: 15px;
  }
  .map-wrapper {
    display: inline-block;
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }
  .lot-area {
    background: #f8f8f8;
    border-radius: 8px;
    padding: 20px;
  }
  .slot, .slotmsec, .slotbot {
    border-radius: 8px;
    background: #e0e0e0;
    border: 1px solid #777;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    margin: 4px;
  }
  .slot { width: 55px; height: 85px; }
  .slotmsec { width: 85px; height: 50px; }
  .slotbot { width: 55px; height: 85px; }

  .highlight {
    background: #444;
    border: 2px solid #000;
  }
  .highlight .code { color: #fff; }

  .car {
    position: absolute;
    width: 45px;
    top: 10px;
    opacity: 0.85;
    filter: grayscale(100%) contrast(1.2);
  }

  /* Rotation-based car alignment */
  .car[style*="rotate(90deg)"] { left: 18px; top: -10px; }
  .car[style*="rotate(-90deg)"] { right: 18px; top: -10px; }
  .car[style*="rotate(180deg)"] { top: 7px; }
  .car[style*="rotate(0deg)"] { top: 6px; }

  .code {
    position: absolute;
    bottom: 6px;
    font-size: 11px;
    font-weight: bold;
    color: #222;
  }
  .top-row, .bottom-row { display: flex; justify-content: center; gap: 8px; margin: 20px 0; }
  .middle-container { display: flex; justify-content: space-around; align-items: center; margin: 10px 0; }
  .left-section, .middle-section, .right-section { display: flex; flex-direction: column; align-items: center; gap: 6px; }
  .lane { text-align: center; color: #555; font-size: 10px; margin: 6px 0; }
  .up-down-lane { font-size: 9px; color: #555; }
</style>
</head>
<body>
<div class="ticket">
  <img src="/static/logo.png" class="logo">
  <h2>EYSI PARKING LOT</h2>
  <small>Ticket Preview</small>
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
</div>

<h3 style="text-align:center; margin:10px 0;">Your Parking Slot</h3>

<div class="map-scale-container mini-print-map">
  <div class="map-wrapper">
    <div class="lot-area">

      <!-- TOP -->
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

      <!-- MIDDLE -->
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

      <!-- BOTTOM -->
      <div class="bottom-row">
        <?php foreach (['P17','P18','P19','P20'] as $slot): ?>
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

</body>
</html>

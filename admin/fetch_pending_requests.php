<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login.php");
    exit;
}

$page_title = "Parking Slot Map (View Only)";
include '../templates/header.php';
include '../templates/sidebar.php';
?>

<div class="content">
  <div class="content-header">
    <h2>Parking Slot Map (View Only)</h2>
  </div>
  <div class="content-body" style="padding:20px;">
    <iframe
      src="/employee/parking_map.php?readonly=true"
      style="width:100%;height:85vh;border:none;border-radius:12px;box-shadow:0 5px 20px rgba(0,0,0,0.08);"
    ></iframe>
  </div>
</div>

<?php include '../templates/footer.php'; ?>

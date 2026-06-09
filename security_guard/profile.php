<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';

if (empty($_SESSION['username']) || $_SESSION['role'] !== 'security_guard') {
    header("Location: /unauthorized.php");
    exit;
}

$page_title = "Security Dashboard";
include '../templates/header.php';
include '../templates/sidebar.php';
?>
<div class="main-content">
  
</div><!-- End main-content -->


<?php include '../templates/footer.php'; ?>
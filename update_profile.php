<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

// Always use Philippine timezone
date_default_timezone_set('Asia/Manila');

// ✅ Query for pending login requests
$query = "SELECT COUNT(*) AS count FROM accounts WHERE login_status = 'pending'";
$result = $conn->query($query);

$count = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;

// ✅ Compare with last known count
$previous = $_SESSION['last_pending_count'] ?? 0;
$_SESSION['last_pending_count'] = $count;

// ✅ Respond with the new count if it increased
echo ($count > $previous) ? $count : 0;
?>

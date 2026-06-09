<?php
header('Content-Type: application/json; charset=utf-8');
include 'db_connect.php';

$username = $_GET['username'] ?? '';

if (!$username) {
    echo json_encode(['status' => 'none']);
    exit;
}

$stmt = $conn->prepare("SELECT login_status, role FROM accounts WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'status' => $row['login_status'],
        'role' => $row['role']
    ]);
} else {
    echo json_encode(['status' => 'none']);
}
?>

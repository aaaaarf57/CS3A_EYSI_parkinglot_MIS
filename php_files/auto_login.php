<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

$username = $_GET['username'] ?? '';

if (empty($username)) {
    header("Location: /login.php");
    exit;
}

// 🔍 Check if user is approved
$stmt = $conn->prepare("SELECT * FROM accounts WHERE username = ? AND login_status = 'approved'");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // ✅ Create session
    $_SESSION['username'] = $row['username'];
    $_SESSION['role'] = $row['role'];

    // ✅ Record login history only if not already active
    $accountId = $row['id'];
    $check = $conn->prepare("SELECT id FROM login_history WHERE account_id = ? AND time_out IS NULL");
    $check->bind_param("i", $accountId);
    $check->execute();
    $checkResult = $check->get_result();

    if ($checkResult->num_rows === 0) {
        $logStmt = $conn->prepare("INSERT INTO login_history (account_id, time_in) VALUES (?, NOW())");
        $logStmt->bind_param("i", $accountId);
        $logStmt->execute();
    }

    // ✅ Redirect by role
    switch (strtolower($row['role'])) {
        case 'owner':
            header("Location: /owner/dashboard.php");
            break;
        case 'employee':
            header("Location: /employee/dashboard.php");
            break;
        case 'security_guard':
        case 'security guard':
            header("Location: /security_guard/dashboard.php");
            break;
        case 'admin':
            header("Location: /admin/dashboard.php");
            break;
        default:
            header("Location: /login.php");
            break;
    }
    exit;
}

// ❌ Not approved yet or invalid username
header("Location: /login.php");
exit;
?>

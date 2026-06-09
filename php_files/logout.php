<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Include necessary files
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_connect.php';

// Check if user is logged in
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    
    // Get user ID
    $stmt = $conn->prepare("SELECT id FROM accounts WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        $accountId = $user['id'];
        
        // Update logout time for the most recent session
        $update = $conn->prepare("
            UPDATE login_history 
            SET time_out = NOW() 
            WHERE account_id = ? AND time_out IS NULL 
            ORDER BY time_in DESC 
            LIMIT 1
        ");
        $update->bind_param("i", $accountId);
        $update->execute();
        $update->close();
    }
    
    $stmt->close();
}

if ($user) {
    $accountId = $user['id'];

    // Update logout time for login_history
    $update = $conn->prepare("
        UPDATE login_history 
        SET time_out = NOW() 
        WHERE account_id = ? AND time_out IS NULL 
        ORDER BY time_in DESC 
        LIMIT 1
    ");
    $update->bind_param("i", $accountId);
    $update->execute();
    $update->close();

    // ✅ Mark as offline
    $offline = $conn->prepare("UPDATE accounts SET online_status = 'offline' WHERE id = ?");
    $offline->bind_param("i", $accountId);
    $offline->execute();
    $offline->close();
}

// Properly end session
$_SESSION = [];
session_unset();
session_destroy();

// Redirect to login page
header("Location: /login.php");
exit();
?>

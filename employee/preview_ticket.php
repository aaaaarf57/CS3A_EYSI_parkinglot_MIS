<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

if (empty($_SESSION['username']) || !in_array($_SESSION['role'], ['employee', 'admin'])) {
    header("Location: /unauthorized.php");
    exit();
}

$client_id = $_GET['client_id'] ?? 0;
if (!$client_id) die("Missing client ID");

$conn->begin_transaction();

try {
    // 🧠 Get client info
    $stmt = $conn->prepare("SELECT id, slot_id, name, time_in FROM clients WHERE id=? AND time_out IS NULL");
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$client) throw new Exception('Client not found or already exited.');

    // 🕓 Compute duration
    $time_in = new DateTime($client['time_in']);
    $time_out = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $diff = $time_in->diff($time_out);
    $hours = max(1, ceil($diff->h + ($diff->i / 60)));

    // 💰 Compute cost (₱20 first hour + ₱10 per succeeding hour)
    $rate_first = 20;
    $rate_next = 10;
    $total_cost = $rate_first + max(0, $hours - 1) * $rate_next;

    // ✅ Update client record with correct Manila time
    $update = $conn->prepare("
        UPDATE clients 
        SET time_out=(UTC_TIMESTAMP() + INTERVAL 8 HOUR), total_cost=? 
        WHERE id=?
    ");
    $update->bind_param('di', $total_cost, $client_id);
    $update->execute();
    $update->close();

    // 🚗 Free the slot, also using Manila time
    if ($client['slot_id']) {
        $free = $conn->prepare("
            UPDATE slots 
            SET status='vacant', client_id=NULL, last_updated=(UTC_TIMESTAMP() + INTERVAL 8 HOUR) 
            WHERE id=?
        ");
        $free->bind_param('i', $client['slot_id']);
        $free->execute();
        $free->close();
    }

    // 🧾 Log action
    $username = $_SESSION['username'];
    $userIdQuery = $conn->prepare("SELECT id FROM accounts WHERE username=?");
    $userIdQuery->bind_param('s', $username);
    $userIdQuery->execute();
    $userRes = $userIdQuery->get_result()->fetch_assoc();
    $userId = $userRes['id'] ?? null;
    $userIdQuery->close();

    if ($userId) {
        $log = $conn->prepare("INSERT INTO auth_trail (user_id, action, category, message) VALUES (?, 'mark_exit', 'parking', ?)");
        $msg = "Employee $username marked exit for {$client['name']} (Client ID: {$client_id})";
        $log->bind_param('is', $userId, $msg);
        $log->execute();
        $log->close();
    }

    // ✅ Commit all changes
    $conn->commit();

    header("Location: /employee/exit_ticket.php?client_id=" . urlencode($client_id));
    exit();

} catch (Exception $e) {
    $conn->rollback();
    echo "<h3 style='color:red; text-align:center; margin-top:50px;'>Error: {$e->getMessage()}</h3>";
    exit;
}
?>

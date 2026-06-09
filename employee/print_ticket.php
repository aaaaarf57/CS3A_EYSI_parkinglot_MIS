<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

// ✅ Allow only logged-in employees
if (empty($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'employee') {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$username = $_SESSION['username']; // logged-in employee username

// 🧠 Read raw JSON body
$payload = json_decode(file_get_contents('php://input'), true);
$slot_id = isset($payload['slot_id']) ? (int)$payload['slot_id'] : 0;
$client_id = isset($payload['client_id']) ? (int)$payload['client_id'] : 0;

// 🧠 Determine actual parking type from client record
$getType = $conn->prepare("SELECT parking_type FROM clients WHERE id = ?");
$getType->bind_param("i", $client_id);
$getType->execute();
$typeRes = $getType->get_result()->fetch_assoc();
$getType->close();

// Use client's actual type; fallback to walkin only if missing
$parking_type = $typeRes['parking_type'] ?? 'walkin';

if (!$slot_id || !$client_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$conn->begin_transaction();

try {
    // 🔒 Lock slot to prevent race condition
    $stmt = $conn->prepare("SELECT id, status, client_id, slot_code FROM slots WHERE id=? FOR UPDATE");
    $stmt->bind_param('i', $slot_id);
    $stmt->execute();
    $slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$slot) {
        throw new Exception('Slot not found');
    }

    if ($slot['status'] === 'occupied' || $slot['status'] === 'unavailable') {
        throw new Exception('Slot already occupied or unavailable');
    }

    // ✅ Update slot info (record Manila time)
    $upd = $conn->prepare("
        UPDATE slots 
        SET client_id=?, 
            status='occupied', 
            last_updated=(UTC_TIMESTAMP() + INTERVAL 8 HOUR) 
        WHERE id=?
    ");
    $upd->bind_param('ii', $client_id, $slot_id);
    if (!$upd->execute()) {
        throw new Exception('Failed to update slot');
    }
    $upd->close();

    // ✅ Update client info (assign slot + parking type + time in)
    $upd2 = $conn->prepare("
        UPDATE clients 
        SET slot_id=?, 
            parking_type=?, 
            time_in = COALESCE(time_in, (UTC_TIMESTAMP() + INTERVAL 8 HOUR))
        WHERE id=?
    ");
    $upd2->bind_param('ssi', $slot_id, $parking_type, $client_id);
    if (!$upd2->execute()) {
        throw new Exception('Failed to update client');
    }
    $upd2->close();

    // ✅ Get the employee's numeric user_id for logging
    $getId = $conn->prepare("SELECT id FROM accounts WHERE username = ?");
    $getId->bind_param("s", $username);
    $getId->execute();
    $getIdResult = $getId->get_result();
    $user = $getIdResult->fetch_assoc();
    $getId->close();

    $user_id = $user['id'] ?? null;

    // ✅ Log the action to auth_trail (only if user_id found)
    if ($user_id) {
        $action = 'assign_slot';
        $category = 'parking';
        $message = "Employee {$username} assigned client_id {$client_id} to slot {$slot['slot_code']}";
        $log = $conn->prepare("INSERT INTO auth_trail (user_id, action, category, message) VALUES (?,?,?,?)");
        $log->bind_param('isss', $user_id, $action, $category, $message);
        $log->execute();
        $log->close();
    }

    // ✅ Commit transaction
    $conn->commit();

    // ✅ Generate ticket id for printing
    $ticket_id = 'TICKET-' . time() . '-' . $client_id;

    echo json_encode([
        'success' => true,
        'message' => 'Slot assigned successfully',
        'ticket_id' => $ticket_id
    ]);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>

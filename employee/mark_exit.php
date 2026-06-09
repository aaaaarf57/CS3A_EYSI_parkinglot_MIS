<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';

// ✅ Allow Employee, Admin, and Owner roles
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['employee', 'admin', 'owner', 'security guard'])) {
    header("Location: /login.php");
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once '../db_connect.php';

// Query slots with joined client info if assigned
$sql = "SELECT 
            s.id, 
            s.slot_code, 
            s.status, 
            s.client_id, 
            s.last_updated,
            c.name AS client_name, 
            c.vehicle_plate,
            c.parking_type AS client_parking_type
        FROM slots s
        LEFT JOIN clients c ON s.client_id = c.id
        ORDER BY s.slot_code ASC";

$res = $conn->query($sql);
$rows = [];

if ($res) {
    while ($r = $res->fetch_assoc()) {
        $status = $r['status'] ?? 'vacant';

        if ($r['client_id'] && $r['client_parking_type'] === 'reservation') {
            $status = 'reserved';
        } elseif ($r['client_id'] && $r['client_parking_type'] === 'walkin') {
            $status = 'occupied';
        }

        $rows[] = [
            'id' => (int)$r['id'],
            'slot_code' => $r['slot_code'],
            'status' => $status,
            'client_id' => $r['client_id'] ? (int)$r['client_id'] : null,
            'client_name' => $r['client_name'] ?? null,
            'vehicle_plate' => $r['vehicle_plate'] ?? null,
            'last_updated' => $r['last_updated'] ?? null,
            'parking_type' => $r['client_parking_type'] ?? null
        ];
    }
}

echo json_encode($rows);
?>

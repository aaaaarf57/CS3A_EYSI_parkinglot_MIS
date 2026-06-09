<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
require_once '../db_connect.php';

/*
 Logic:
 - Include clients who are reservation with time_in IS NULL
 - OR clients with no slot assigned (slot_id IS NULL)
 - OR clients with time_out IS NULL (still active)
*/

$sql = "SELECT id, name, vehicle_plate, parking_type, time_in, time_out, slot_id
        FROM clients
        WHERE (parking_type = 'reservation' AND (time_in IS NULL OR time_in = '0000-00-00 00:00:00'))
           OR (slot_id IS NULL)
           OR (time_out IS NULL)
        ORDER BY name ASC
        LIMIT 500";

$res = $conn->query($sql);
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()){
        $rows[] = [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'vehicle_plate' => $r['vehicle_plate'],
            'parking_type' => $r['parking_type'],
            'time_in' => $r['time_in'],
            'time_out' => $r['time_out'],
            'slot_id' => $r['slot_id']
        ];
    }
}

echo json_encode($rows);

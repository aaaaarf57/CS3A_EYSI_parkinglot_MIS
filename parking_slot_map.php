<?php
include '../db_connect.php';

// ✅ Always use Philippine timezone
date_default_timezone_set('Asia/Manila');

// ✅ Convert UTC (Hostinger default) → Manila time in SQL
$query = "
  SELECT 
    a.username, 
    a.role, 
    CONVERT_TZ(l.time_in, '+00:00', '+08:00') AS time_in
  FROM login_history l
  JOIN accounts a ON l.account_id = a.id
  WHERE l.time_out IS NULL
  ORDER BY l.time_in DESC
";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    echo '<table class="table align-middle">
            <thead><tr><th>Name</th><th>Role</th><th>Time In</th></tr></thead>
            <tbody>';
    while ($row = $result->fetch_assoc()) {
        // ✅ Format time_in to display properly in Manila time
        $formatted_time = date('Y-m-d H:i:s', strtotime($row['time_in']));
        echo '<tr>
                <td>' . htmlspecialchars($row['username']) . '</td>
                <td>' . ucfirst($row['role']) . '</td>
                <td>' . $formatted_time . '</td>
              </tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p class="text-muted mb-0">No active users currently signed in.</p>';
}
?>

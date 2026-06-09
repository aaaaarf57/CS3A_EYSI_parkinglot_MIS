<?php
include '../db_connect.php';

// ✅ Always use Philippine timezone
date_default_timezone_set('Asia/Manila');

// ✅ Convert UTC → Manila in SQL
$result = $conn->query("
    SELECT 
        id, 
        username, 
        role, 
        CONVERT_TZ(created_at, '+00:00', '+08:00') AS created_at, 
        login_status 
    FROM accounts 
    WHERE login_status='pending' 
    ORDER BY created_at DESC
");

if ($result->num_rows > 0) {
    echo '<table class="table align-middle">
            <thead>
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Date Registered</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>';
    while ($p = $result->fetch_assoc()) {
        // ✅ Format created_at for display in Manila time
        $formatted_date = date('M d, Y h:i A', strtotime($p['created_at']));
        echo '<tr>
                <td>' . $p['id'] . '</td>
                <td>' . htmlspecialchars($p['username']) . '</td>
                <td>' . ucfirst($p['role']) . '</td>
                <td>' . $formatted_date . '</td>
                <td>
                  <a href="?action=approve&id=' . $p['id'] . '" class="btn btn-success btn-sm">Approve</a>
                  <a href="?action=decline&id=' . $p['id'] . '" class="btn btn-danger btn-sm">Decline</a>
                </td>
              </tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p class="text-muted mb-0">✅ No pending login requests right now.</p>';
}
?>

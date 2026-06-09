<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

$error = "";
$showApprovalPoll = false;

// 🧩 Handle Login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role     = trim($_POST['role']);

    // Get user
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE username = ? AND role = ?");
    $stmt->bind_param("ss", $username, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $stored_password = $user['password'];

        // ✅ Verify password (hashed or plain)
        if ($password === $stored_password || password_verify($password, $stored_password)) {

            // ✅ ADMIN logs in directly
            if ($user['role'] === 'admin') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header("Location: /admin/dashboard.php");
                exit();
            }

            // ✅ NON-ADMIN: send request for approval
            $conn->query("UPDATE accounts SET login_status='pending', last_request=NOW() WHERE id={$user['id']}");

            // Re-check status
            $statusCheck = $conn->prepare("SELECT login_status FROM accounts WHERE id = ?");
            $statusCheck->bind_param("i", $user['id']);
            $statusCheck->execute();
            $status = $statusCheck->get_result()->fetch_assoc()['login_status'];

            // === STATUS LOGIC ===
            if ($status === 'approved') {
                // 🕒 Check working hours for employees & guards
                if (in_array($user['role'], ['employee', 'security_guard'])) {
                    $emp = $conn->prepare("SELECT shift_start, shift_end FROM employees WHERE account_id = ?");
                    $emp->bind_param("i", $user['id']);
                    $emp->execute();
                    $shift = $emp->get_result()->fetch_assoc();
                    $emp->close();

                    if ($shift && $shift['shift_start'] && $shift['shift_end']) {
                        date_default_timezone_set('Asia/Manila');
                        $now = date('H:i:s');
                        $start = $shift['shift_start'];
                        $end   = $shift['shift_end'];

                        $nowTime = strtotime($now);
                        $startTime = strtotime($start);
                        $endTime = strtotime($end);
                        $earlyStart = strtotime('-30 minutes', $startTime);

                        $isWithinShift = false;
                        $isEarly = false;

                        // Handle overnight shifts
                        if ($startTime < $endTime) {
                            if ($nowTime >= $startTime && $nowTime <= $endTime) $isWithinShift = true;
                            elseif ($nowTime >= $earlyStart && $nowTime < $startTime) $isEarly = true;
                        } else {
                            if ($nowTime >= $startTime || $nowTime <= $endTime) $isWithinShift = true;
                            elseif (($nowTime >= $earlyStart && $nowTime < $startTime) || ($nowTime <= $endTime + 1800)) $isEarly = true;
                        }

                        if (!$isWithinShift && !$isEarly) {
                            $error = "⏰ It’s not your duty time yet. Your shift is from ".
                                     date('h:i A', strtotime($start))." to ".date('h:i A', strtotime($end)).".";
                            goto show_form;
                        }

                        if ($isEarly) {
                            $error = "<span class='early'>⚠️ You’re early! Your duty starts at ".
                                     date('h:i A', strtotime($start)).".</span>";
                        }
                    }
                }

                // ✅ Proceed to login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // ✅ Log login history
                $log = $conn->prepare("INSERT INTO login_history (account_id, time_in) VALUES (?, NOW())");
                $log->bind_param("i", $user['id']);
                $log->execute();

                // ✅ Redirect by role
                switch ($user['role']) {
                    case 'owner':
                        header("Location: /owner/dashboard.php");
                        break;
                    case 'employee':
                        header("Location: /employee/dashboard.php");
                        break;
                    case 'security_guard':
                        header("Location: /security_guard/dashboard.php");
                        break;
                    default:
                        header("Location: /login.php");
                        break;
                }
                exit();

            } elseif ($status === 'declined') {
                $error = "❌ Login request declined by admin.";
            } else {
                $error = "⏳ Login request sent to admin for approval. Please wait.";
                $showApprovalPoll = true;
            }

        } else {
            $error = "❌ Incorrect password.";
        }
    } else {
        $error = "❌ Invalid username or role.";
    }
    $stmt->close();
}

show_form:
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - Eysi Parking Lot MIS</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" href="static/favicon.png">
<style>
* { font-family: 'Poppins', sans-serif; box-sizing: border-box; }
body {
  margin: 0; height: 100vh;
  display: flex; justify-content: center; align-items: center;
  background: url('static/header-background.png') no-repeat center center fixed;
  background-size: cover;
}
.overlay { position:absolute;top:0;left:0;width:100%;height:100%;
background:rgba(255,255,255,0.35);backdrop-filter:blur(3px);z-index:1;}
.login-card { position:relative;z-index:2;background:rgba(255,255,255,0.93);
width:400px;padding:45px 40px;border-radius:15px;box-shadow:0 8px 25px rgba(0,0,0,0.25);
text-align:center;animation:fadeIn 0.6s ease;}
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px);} to { opacity: 1; transform: translateY(0);} }
.login-card img { width: 70px; margin-bottom: 10px; }
.login-card h2 { margin-bottom: 25px; font-weight: 600; color: #333; }
.login-card select, .login-card input {
  width: 100%; padding: 12px 14px; margin-bottom: 18px;
  border: 1px solid #ddd; border-radius: 8px; font-size: 15px; transition: all 0.3s ease;
}
.login-card select:focus, .login-card input:focus {
  border-color: #f5d300; box-shadow: 0 0 0 3px rgba(245,211,0,0.3); outline: none;
}
.login-card button {
  width: 100%; padding: 12px;
  background: #f5d300; border: none; border-radius: 8px;
  font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.3s ease;
}
.login-card button:hover { background: #ffb800; color: #000; transform: translateY(-1px); }
p.error, span.warning, span.early {
  display: block; border-radius: 6px; padding: 10px 12px;
  font-weight: 500; margin-top: 10px; text-align: center;
}
p.error { background: #ffe3e3; color: #d00000; }
span.warning { background: #fff5cc; color: #996c00; }
span.early { background: #fffbea; color: #8a6d00; }
footer { position: absolute; bottom: 10px; font-size: 13px; color: #000; z-index: 3; font-weight: 500; }
</style>
</head>
<body>
<div class="overlay"></div>
<div class="login-card">
  <img src="static/logo.png" alt="Logo">
  <h2>Welcome Back</h2>
  <form method="POST">
    <select name="role" required>
      <option value="">Select Role</option>
      <option value="admin">Admin</option>
      <option value="owner">Owner</option>
      <option value="security_guard">Security Guard</option>
      <option value="employee">Employee</option>
    </select>
    <input type="text" name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Login</button>
  </form>
  <?php if ($error != "") echo "<p class='error'>$error</p>"; ?>
</div>
<footer>© <?php echo date('Y'); ?> Eysi Parking Lot MIS. All rights reserved.</footer>

<?php if ($showApprovalPoll): ?>
<script>
// 🔁 Auto-check admin approval every 3 seconds
(function(){
  const username = <?= json_encode($username ?? '') ?>;
  if (!username) return;
  function checkApproval(){
    fetch('check_approval.php?username=' + encodeURIComponent(username), {cache:'no-store'})
      .then(res => res.json())
      .then(data => {
        if (data.status === 'approved') {
          window.location.href = '/auto_login.php?username=' + encodeURIComponent(username);
        } else if (data.status === 'declined') {
          alert('❌ Your login request was declined by the admin.');
          location.reload();
        }
      })
      .catch(err => console.error('Approval check failed', err));
  }
  checkApproval();
  setInterval(checkApproval, 3000);
})();
</script>
<?php endif; ?>
</body>
</html>

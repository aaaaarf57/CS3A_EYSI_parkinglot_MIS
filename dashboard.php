<?php
// ============================================================
// 🧩 ADMIN - USER REGISTRATION (with Modal Form + Editable Shift Management)
// ============================================================

require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: /unauthorized.php");
    exit;
}

date_default_timezone_set('Asia/Manila');
$page_title = "User Registration";

include '../templates/header.php';
include '../templates/sidebar.php';

$message = "";

// ✅ Handle User Registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_user'])) {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $age = $_POST['age'] ?? null;
    $sex = $_POST['sex'] ?? null;
    $contact = $_POST['contact'] ?? null;
    $email = $_POST['email'] ?? null;
    $address = $_POST['address'] ?? null;
    $status = 'active';
    $login_status = ($role === 'admin') ? 'approved' : 'pending';
    $online_status = 'offline';
    $created_at = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO accounts 
        (username, password, role, name, age, sex, contact, email, address, status, login_status, online_status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssissssssss", 
        $username, $password, $role, $name, $age, $sex, $contact, $email, $address, $status, $login_status, $online_status, $created_at);

    if ($stmt->execute()) {
        $account_id = $stmt->insert_id;

        // ✅ Add employee/guard shift data
        if ($role === 'employee' || $role === 'security_guard') {
            $shift_start = $_POST['shift_start'] ?? null;
            $shift_end = $_POST['shift_end'] ?? null;
            $is_active = $_POST['is_active'] ?? 'active';

            $emp_stmt = $conn->prepare("
                INSERT INTO employees (account_id, name, role, shift_start, shift_end, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $emp_stmt->bind_param("isssss", $account_id, $name, $role, $shift_start, $shift_end, $is_active);
            $emp_stmt->execute();
        }

        $message = "<div class='alert alert-success mt-3'>✅ User registered successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger mt-3'>❌ Failed to register user.</div>";
    }
}

// ---------------------------
// Handle Shift Update (UPSERT)
// ---------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_shift'])) {
    $account_id = intval($_POST['update_shift_id']);
    // Normalize time values: empty -> null (so DB gets NULL)
    $shift_start = trim($_POST['shift_start_update']);
    $shift_end   = trim($_POST['shift_end_update']);
    $is_active   = $_POST['is_active_update'] ?? 'active';

    $shift_start_db = $shift_start === '' ? null : $shift_start;
    $shift_end_db   = $shift_end === '' ? null : $shift_end;

    // Basic validation
    if ($account_id <= 0) {
        $message = "<div class='alert alert-danger mt-3'>❌ Invalid account selected.</div>";
    } else {
        try {
            // Check if employees row exists for this account
            $check = $conn->prepare("SELECT id FROM employees WHERE account_id = ? LIMIT 1");
            $check->bind_param("i", $account_id);
            $check->execute();
            $res = $check->get_result();
            $exists = $res->num_rows > 0;
            $check->close();

            if ($exists) {
                // Update existing row
                $update_shift = $conn->prepare("
                    UPDATE employees 
                    SET shift_start = ?, shift_end = ?, is_active = ?
                    WHERE account_id = ?
                ");
                $update_shift->bind_param("sssi", $shift_start_db, $shift_end_db, $is_active, $account_id);
                $ok = $update_shift->execute();
                $update_shift->close();

                if ($ok) {
                    $message = "<div class='alert alert-success mt-3'>✅ Shift updated successfully!</div>";
                } else {
                    $message = "<div class='alert alert-danger mt-3'>❌ Failed to update shift.</div>";
                    error_log("Shift update failed for account_id={$account_id}: " . $conn->error);
                }
            } else {
                // Insert new employees row (since it doesn't exist)
                $insert = $conn->prepare("
                    INSERT INTO employees (account_id, name, role, shift_start, shift_end, is_active, created_at)
                    VALUES (?, (SELECT name FROM accounts WHERE id = ?), (SELECT role FROM accounts WHERE id = ?), ?, ?, ?, NOW())
                ");
                // bind: account_id, account_id, account_id, shift_start, shift_end, is_active
                $insert->bind_param("iiisss", $account_id, $account_id, $account_id, $shift_start_db, $shift_end_db, $is_active);
                $ok = $insert->execute();
                $insert->close();

                if ($ok) {
                    $message = "<div class='alert alert-success mt-3'>✅ Shift saved successfully!</div>";
                } else {
                    $message = "<div class='alert alert-danger mt-3'>❌ Failed to save shift.</div>";
                    error_log("Shift insert failed for account_id={$account_id}: " . $conn->error);
                }
            }
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger mt-3'>❌ Unexpected error.</div>";
            error_log("Exception in update_shift: " . $e->getMessage());
        }
    }
}


// ✅ Handle Activate/Deactivate
if (isset($_GET['toggle_status'])) {
    $id = intval($_GET['toggle_status']);
    $current_status = $_GET['status'] === 'active' ? 'inactive' : 'active';
    $stmt = $conn->prepare("UPDATE accounts SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $current_status, $id);
    $stmt->execute();
    header("Location: user_registration.php");
    exit;
}

// ✅ Fetch users by role
$admins = $conn->query("SELECT * FROM accounts WHERE role='admin'");
$owners = $conn->query("SELECT * FROM accounts WHERE role='owner'");
$guards = $conn->query("
    SELECT a.*, e.shift_start, e.shift_end, e.is_active
    FROM accounts a
    LEFT JOIN employees e ON a.id = e.account_id
    WHERE a.role = 'security_guard'
");
$employees = $conn->query("
    SELECT a.*, e.shift_start, e.shift_end, e.is_active
    FROM accounts a
    LEFT JOIN employees e ON a.id = e.account_id
    WHERE a.role = 'employee'
");
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/static/css/content.css">

<style>
/* 🌕 Eysi Parking Lot — Clean Yellow Theme */
h3 { font-weight: 600; color: #222; letter-spacing: 0.5px; }
.btn-yellow { background: #f5d300; color: #000; font-weight: 600; border: none; border-radius: 8px; padding: 10px 18px; transition: 0.25s ease; box-shadow: 0 3px 10px rgba(245,211,0,0.3);}
.btn-yellow:hover { background: #ffb800; transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,0,0,0.15);}
.card { border: none; border-radius: 12px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 25px;}
.card-header { background: #f9f9f9; color: #000; font-weight: 600; border-bottom: 3px solid #f5d300; padding: 14px 18px; font-size: 15px;}
.table { width: 100%; font-size: 15px;}
.table thead th { background-color: #fffce6; color: #333; font-weight: 600; border-bottom: 2px solid #f5d300; padding: 12px;}
.table tbody tr:hover { background-color: #fffbea;}
.badge { border-radius: 6px; padding: 6px 10px; font-weight: 500; }
.btn-warning { background: #f5d300 !important; color: #000 !important; font-weight: 600; border: none; border-radius: 8px; transition: all 0.25s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.12);}
.btn-warning:hover { background: #ffb800 !important; transform: translateY(-2px);}
.modal-content { border-radius: 15px; border: none; box-shadow: 0 6px 18px rgba(0,0,0,0.15);}
.modal-header { background: #f5d300; color: #000; font-weight: 600; border-top-left-radius: 15px; border-top-right-radius: 15px;}
.modal-footer .btn-primary { background: #f5d300; border: none; color: #000; font-weight: 600; border-radius: 8px;}
.modal-footer .btn-primary:hover { background: #ffb800;}
</style>

<div class="content">
  <?= $message ?>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3>👥 User Management</h3>
    <button class="btn btn-yellow" data-bs-toggle="modal" data-bs-target="#registerUserModal">➕ Add New User</button>
  </div>

  <?php
  $tables = [
      "Admins" => $admins,
      "Owners" => $owners,
      "Security Guards" => $guards,
      "Employees" => $employees
  ];

  foreach ($tables as $title => $result): ?>
  <div class="card mb-4">
      <div class="card-header"><?= $title ?></div>
      <div class="card-body table-responsive">
          <table class="table table-striped align-middle">
              <thead>
                  <tr>
                      <th>#</th>
                      <th>Name</th>
                      <th>Username</th>
                      <?php if (in_array($title, ['Security Guards','Employees'])): ?>
                      <th>Shift Start</th>
                      <th>Shift End</th>
                      <th>Work Status</th>
                      <th>Edit</th>
                      <?php endif; ?>
                      <th>Account</th>
                      <th>Online</th>
                      <th>Created At</th>
                      <th>Action</th>
                  </tr>
              </thead>
              <tbody>
                  <?php $i=1; while($row = $result->fetch_assoc()): ?>
                  <tr>
                      <td><?= $i++ ?></td>
                      <td><?= htmlspecialchars($row['name']) ?></td>
                      <td><?= htmlspecialchars($row['username']) ?></td>
                      <?php if (in_array($title, ['Security Guards','Employees'])): ?>
                      <td><?= $row['shift_start'] ? date('h:i A', strtotime($row['shift_start'])) : '—' ?></td>
                      <td><?= $row['shift_end'] ? date('h:i A', strtotime($row['shift_end'])) : '—' ?></td>
                      <td>
                          <span class="badge bg-<?= $row['is_active'] === 'active' ? 'success' : 'danger' ?>">
                              <?= ucfirst($row['is_active'] ?? 'N/A') ?>
                          </span>
                      </td>
                      <td>
                        <button class="btn btn-sm btn-outline-dark"
                                data-bs-toggle="modal"
                                data-bs-target="#editShiftModal"
                                data-id="<?= $row['id'] ?>"
                                data-name="<?= htmlspecialchars($row['name']) ?>"
                                data-shift-start="<?= $row['shift_start'] ?>"
                                data-shift-end="<?= $row['shift_end'] ?>"
                                data-status="<?= $row['is_active'] ?>">
                            ✏️ Edit
                        </button>
                      </td>
                      <?php endif; ?>
                      <td><span class="badge bg-<?= $row['status'] === 'active' ? 'success' : 'danger' ?>"><?= ucfirst($row['status']) ?></span></td>
                      <td><span class="badge bg-<?= $row['online_status'] === 'online' ? 'success' : 'secondary' ?>"><?= ucfirst($row['online_status']) ?></span></td>
                      <td><?= date("M d, Y h:i A", strtotime($row['created_at'])) ?></td>
                      <td><a href="?toggle_status=<?= $row['id'] ?>&status=<?= $row['status'] ?>" class="btn btn-sm btn-warning"><?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?></a></td>
                  </tr>
                  <?php endwhile; ?>
              </tbody>
          </table>
      </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- 🕒 EDIT SHIFT MODAL -->
<div class="modal fade" id="editShiftModal" tabindex="-1" aria-labelledby="editShiftModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="editShiftModalLabel">Edit Working Hours</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="update_shift_id" id="update_shift_id">
          <div class="mb-3">
            <label>Name</label>
            <input type="text" id="shift_name" class="form-control" readonly>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label>Shift Start</label>
              <input type="time" name="shift_start_update" id="shift_start_update" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label>Shift End</label>
              <input type="time" name="shift_end_update" id="shift_end_update" class="form-control" required>
            </div>
          </div>
          <div class="mb-3">
            <label>Status</label>
            <select name="is_active_update" id="is_active_update" class="form-select">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update_shift" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- REGISTER USER MODAL (unchanged) -->
<div class="modal fade" id="registerUserModal" tabindex="-1" aria-labelledby="registerUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="registerUserModalLabel">Register New User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row mb-3">
            <div class="col-md-6"><label>Name</label><input type="text" name="name" class="form-control" required></div>
            <div class="col-md-6">
              <label>Role</label>
              <select name="role" id="roleSelectModal" class="form-select" required>
                <option value="">Select Role</option>
                <option value="owner">Owner</option>
                <option value="admin">Admin</option>
                <option value="security_guard">Security Guard</option>
                <option value="employee">Employee</option>
              </select>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6"><label>Username</label><input type="text" name="username" class="form-control" required></div>
            <div class="col-md-6"><label>Password</label><input type="password" name="password" class="form-control" required></div>
          </div>
          <div class="row mb-3">
            <div class="col-md-3"><label>Age</label><input type="number" name="age" class="form-control"></div>
            <div class="col-md-3"><label>Sex</label><select name="sex" class="form-select"><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
            <div class="col-md-6"><label>Contact</label><input type="text" name="contact" class="form-control"></div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6"><label>Email</label><input type="email" name="email" class="form-control"></div>
            <div class="col-md-6"><label>Address</label><input type="text" name="address" class="form-control"></div>
          </div>
          <div id="shiftFields" class="row mb-3" style="display:none;">
            <div class="col-md-4"><label>Shift Start</label><input type="time" name="shift_start" class="form-control"></div>
            <div class="col-md-4"><label>Shift End</label><input type="time" name="shift_end" class="form-control"></div>
            <div class="col-md-4"><label>Status</label><select name="is_active" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="register_user" class="btn btn-primary">Register</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('roleSelectModal').addEventListener('change', function() {
    document.getElementById('shiftFields').style.display =
        (this.value === 'employee' || this.value === 'security_guard') ? 'flex' : 'none';
});

// 🕒 Autofill Edit Shift Modal
const editShiftModal = document.getElementById('editShiftModal');
editShiftModal.addEventListener('show.bs.modal', function (event) {
  const button = event.relatedTarget;
  document.getElementById('update_shift_id').value = button.getAttribute('data-id');
  document.getElementById('shift_name').value = button.getAttribute('data-name');
  document.getElementById('shift_start_update').value = button.getAttribute('data-shift-start') || '';
  document.getElementById('shift_end_update').value = button.getAttribute('data-shift-end') || '';
  document.getElementById('is_active_update').value = button.getAttribute('data-status') || 'active';
});
</script>

<?php include '../templates/footer.php'; ?>

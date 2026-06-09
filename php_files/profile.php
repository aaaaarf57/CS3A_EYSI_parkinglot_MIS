<?php
// profile.php - unified profile page for all roles

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<!-- Universal content styling (consistent across pages) -->
<link rel="stylesheet" href="/static/css/content.css">

<?php
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;

if (!$user_id && !$username) {
    echo '<div class="container"><div class="alert alert-danger">No session detected. Please login.</div></div>';
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

$success = '';
$errors = [];

// === Handle profile update ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $age = isset($_POST['age']) ? (int)$_POST['age'] : null;
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($age !== null && ($age < 10 || $age > 120)) $errors[] = "Please provide a realistic age.";
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";

    $profile_photo_filename = null;
    if (!empty($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_photo'];
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $maxSize = 2 * 1024 * 1024;

        if ($file['error'] === UPLOAD_ERR_OK && $file['size'] <= $maxSize) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/uploads/profile_photos';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $profile_photo_filename = uniqid('pf_', true) . '.' . $ext;
                move_uploaded_file($file['tmp_name'], "$uploadDir/$profile_photo_filename");
                $_SESSION['profile_photo'] = $profile_photo_filename;
            } else $errors[] = "Invalid image type.";
        } else $errors[] = "Upload failed or file too large.";
    }

    if (empty($errors)) {
        $updates = [];
        $params = [];
        $types = '';

        if ($age !== null) { $updates[] = "`age`=?"; $params[] = $age; $types .= 'i'; }
        if ($contact !== '') { $updates[] = "`contact`=?"; $params[] = $contact; $types .= 's'; }
        if ($email !== '') { $updates[] = "`email`=?"; $params[] = $email; $types .= 's'; }
        if ($address !== '') { $updates[] = "`address`=?"; $params[] = $address; $types .= 's'; }
        if ($profile_photo_filename) { $updates[] = "`profile_photo`=?"; $params[] = $profile_photo_filename; $types .= 's'; }

        if (!empty($updates)) {
            $sql = "UPDATE accounts SET " . implode(', ', $updates) . " WHERE id=?";
            $params[] = $user_id;
            $types .= 'i';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) $success = "Profile updated successfully.";
            else $errors[] = "DB error: " . $stmt->error;
            $stmt->close();
        } else $success = "No changes to save.";
    }
}

// === Handle username change ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_username'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    if ($new_username === '') {
        $errors[] = "Username cannot be empty.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM accounts WHERE username=? AND id!=?");
        $stmt->bind_param("si", $new_username, $user_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $errors[] = "Username already taken.";
        } else {
            $stmt = $conn->prepare("UPDATE accounts SET username=? WHERE id=?");
            $stmt->bind_param("si", $new_username, $user_id);
            if ($stmt->execute()) {
                $_SESSION['username'] = $new_username;
                $success = "Username updated successfully.";
            } else {
                $errors[] = "Failed to update username.";
            }
            $stmt->close();
        }
    }
}

// === Handle password change (supports plain text + auto-hash new) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM accounts WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            $errors[] = "User not found.";
        } else {
            $stored = $result['password'];
            $verified = false;

            // Support plain text and hashed passwords
            if ($current_password === $stored) {
                $verified = true;
            } elseif (password_verify($current_password, $stored)) {
                $verified = true;
            }

            if (!$verified) {
                $errors[] = "Current password is incorrect.";
            } else {
                // Hash new password securely
                $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE accounts SET password=? WHERE id=?");
                $stmt->bind_param("si", $new_hashed, $user_id);
                if ($stmt->execute()) {
                    $success = "Password changed successfully.";
                } else {
                    $errors[] = "Failed to change password.";
                }
                $stmt->close();
            }
        }
    }
}

// === Fetch user data ===
$stmt = $conn->prepare("SELECT * FROM accounts WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo "<div class='container'><div class='alert alert-warning'>User not found.</div></div>";
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

$photo_path = '/uploads/profile_photos/default.png';
if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/uploads/profile_photos/' . $user['profile_photo'])) {
    $photo_path = '/uploads/profile_photos/' . $user['profile_photo'];
} elseif (!file_exists(__DIR__ . '/uploads/profile_photos/default.png')) {
    $photo_path = '/static/img/default_profile.png';
}
?>

<style>
.profile-label { font-weight: 600; color: #444; }
.editable input, .editable textarea { display: none; }
.editable.edit-mode span { display: none; }
.editable.edit-mode input, .editable.edit-mode textarea { display: inline-block; }
#edit-btn { background: #f5d300; border: none; font-weight: 600; }
#edit-btn:hover { background: #ff8c00; color: #fff; }
/* Unified button layout */
.profile-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 1.5rem;
}

/* Group on right */
.right-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

/* Consistent button design */
.btn-action {
  font-weight: 600;
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  transition: all 0.25s ease-in-out;
  min-width: 190px; /* ensures equal width */
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

/* Yellow button */
.btn-yellow {
  background: #f5d300;
  color: #000;
}

.btn-yellow:hover {
  background: #ffb300;
  color: #fff;
  transform: translateY(-1px);
}

/* Red button */
.btn-red {
  background: #dc3545;
  color: #fff;
}

.btn-red:hover {
  background: #b02a37;
  transform: translateY(-1px);
}

/* Add slight press animation */
.btn-action:active {
  transform: scale(0.97);
}

</style>

<!-- MAIN CONTENT WRAPPER (matches content.css) -->
<main class="main-content py-10 profile-container" style="background-color: #fff;">


      <?php if ($success): ?>
          <div class="alert alert-success" id="alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($errors): ?>
          <div class="alert alert-danger">
              <?php foreach ($errors as $e) echo '<div>' . htmlspecialchars($e) . '</div>'; ?>
          </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" id="profileForm" class="card p-4 shadow-sm profile-card">
          <div class="d-flex align-items-center mb-4">
              <img src="<?= htmlspecialchars($photo_path) ?>" alt="Profile" class="rounded" style="width:120px; height:120px; object-fit:cover; border:2px solid #ccc; margin-right:20px;">
              <div>
                  <h4><?= htmlspecialchars($user['name'] ?: $user['username']) ?></h4>
                  <p class="mb-1"><strong>Role:</strong> <?= htmlspecialchars(ucfirst($user['role'])) ?></p>
                  <p class="mb-1"><strong>Employee ID:</strong> <?= htmlspecialchars($user['id']) ?></p>
                  <input type="file" name="profile_photo" class="form-control mt-2" style="width:250px; display:none;">
              </div>
          </div>

          <div class="row">
              <div class="col-md-6 mb-3 editable">
                  <label class="profile-label">Age</label><br>
                  <span><?= htmlspecialchars($user['age'] ?: '—') ?></span>
                  <input type="number" name="age" class="form-control" value="<?= htmlspecialchars($user['age']) ?>">
              </div>
              <div class="col-md-6 mb-3 editable">
                  <label class="profile-label">Contact</label><br>
                  <span><?= htmlspecialchars($user['contact'] ?: '—') ?></span>
                  <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($user['contact']) ?>">
              </div>
          </div>

          <div class="mb-3 editable">
              <label class="profile-label">Email</label><br>
              <span><?= htmlspecialchars($user['email'] ?: '—') ?></span>
              <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>">
          </div>

          <div class="mb-3 editable">
              <label class="profile-label">Address</label><br>
              <span><?= htmlspecialchars($user['address'] ?: '—') ?></span>
              <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($user['address']) ?></textarea>
          </div>

          <hr>
          <p><strong>Joined:</strong> <?= htmlspecialchars(date('F j, Y', strtotime($user['created_at'] ?? ''))) ?></p>
          <p><strong>Birthdate:</strong> <?= htmlspecialchars($user['birthdate'] ? date('F j, Y', strtotime($user['birthdate'])) : '—') ?></p>
          <p><strong>Sex:</strong> <?= htmlspecialchars($user['sex'] ?: '—') ?></p>
          <p><strong>Assigned Location:</strong> <?= htmlspecialchars($user['assigned_location'] ?: '—') ?></p>

          <div class="profile-actions">
    <button type="button" id="edit-btn" class="btn-action btn-yellow">Edit Info</button>

    <div class="right-actions">
        <button type="button" class="btn-action btn-yellow" data-bs-toggle="modal" data-bs-target="#changeUsernameModal">
            <i class="bi bi-person-badge"></i> Change Username
        </button>
        <button type="button" class="btn-action btn-red" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
            <i class="bi bi-shield-lock"></i> Change Password
        </button>
    </div>
</div>



      </form>
      <!-- Change Username Modal -->
<div class="modal fade" id="changeUsernameModal" tabindex="-1" aria-labelledby="changeUsernameModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="changeUsernameModalLabel">Change Username</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label for="new_username" class="form-label">New Username</label>
          <input type="text" name="new_username" id="new_username" class="form-control" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="change_username" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="current_password" class="form-label">Current Password</label>
            <input type="password" name="current_password" id="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <input type="password" name="new_password" id="new_password" class="form-control" required minlength="6">
          </div>
          <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="6">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="change_password" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const editBtn = document.getElementById('edit-btn');
    const saveBtn = document.getElementById('save-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const editables = document.querySelectorAll('.editable');
    const fileInput = document.querySelector('input[name="profile_photo"]');
    const alertSuccess = document.getElementById('alert-success');

    editBtn.addEventListener('click', () => {
        editables.forEach(e => e.classList.add('edit-mode'));
        editBtn.style.display = 'none';
        saveBtn.style.display = 'inline-block';
        cancelBtn.style.display = 'inline-block';
        fileInput.style.display = 'block';
    });

    cancelBtn.addEventListener('click', () => {
        editables.forEach(e => e.classList.remove('edit-mode'));
        editBtn.style.display = 'inline-block';
        saveBtn.style.display = 'none';
        cancelBtn.style.display = 'none';
        fileInput.style.display = 'none';
    });

    if (alertSuccess) {
        setTimeout(() => alertSuccess.style.display = 'none', 3000);
    }
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>

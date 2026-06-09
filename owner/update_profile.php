<?php
// Include session bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/session_bootstrap.php';

// Start session if not already started (though bootstrap should handle it)
session_start();

// Require database connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

// Check if logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header("Location: /login.php");
    exit();
}

// Use session values
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Prepare and execute query to fetch user data
$query = $conn->prepare("SELECT id, name, username, role, profile_photo FROM accounts WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$user = $query->get_result()->fetch_assoc();

// Determine profile photo path
$photo_path = !empty($user['profile_photo']) ? '/uploads/profile_photos/' . $user['profile_photo'] : '/assets/img/default_profile.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/dashboard.css" rel="stylesheet">
    <style>
        main {
            background: var(--bg);
            min-height: 100vh;
            padding: 40px;
            font-family: var(--font);
        }
        .profile-card {
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
        }
        .profile-card h3 {
            color: #2c3e50;
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
        }
        .btn-update {
            background: var(--slot-border);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 10px;
            transition: 0.3s;
        }
        .btn-update:hover {
            background: #2e8a3d;
        }
        .profile-pic {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--slot-border);
            margin-bottom: 10px;
        }
        .upload-label {
            font-size: 14px;
            color: #555;
            cursor: pointer;
            text-decoration: underline;
        }
        input[type="file"] {
            display: none;
        }
    </style>
</head>
<body>
    <main>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success text-center">✅ Profile updated successfully!</div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="alert alert-danger text-center">❌ Something went wrong. Try again.</div>
        <?php endif; ?>

        <div class="profile-card">
            <h3>My Profile</h3>
            <form action="update_profile.php" method="POST" enctype="multipart/form-data">
                <div class="text-center mb-4">
                    <img src="<?php echo htmlspecialchars($photo_path); ?>" class="profile-pic" id="preview" alt="Profile Picture">
                    <div>
                        <label for="profile_photo" class="upload-label">Change Photo</label>
                        <input type="file" name="profile_photo" id="profile_photo" accept="image/*" onchange="previewImage(event)">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst($user['role'])); ?>" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter new password (optional)">
                </div>
                <div class="text-center">
                    <button type="submit" class="btn-update">Save Changes</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                document.getElementById('preview').src = reader.result;
            };
            reader.readAsDataURL(event.target.files[0]);
        }
    </script>

    <?php include '../templates/footer.php'; ?>
</body>
</html>

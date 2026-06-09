<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

$username = $_SESSION['username'];
$name = trim($_POST['name']);
$new_username = trim($_POST['username']);
$password = trim($_POST['password']);

$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profile_photos/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

$profile_photo = null;

// ✅ Handle photo upload
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $tmp = $_FILES['profile_photo']['tmp_name'];
    $file_name = time() . '_' . basename($_FILES['profile_photo']['name']);
    $target = $upload_dir . $file_name;
    $type = mime_content_type($tmp);
    $allowed = ['image/jpeg','image/png','image/webp'];
    if (in_array($type, $allowed)) move_uploaded_file($tmp, $target);
    $profile_photo = $file_name;
}

// ✅ Build update query dynamically
if (!empty($password)) {
    if ($profile_photo) {
        $stmt = $conn->prepare("UPDATE accounts SET name=?, username=?, password=?, profile_photo=? WHERE username=?");
        $stmt->bind_param("sssss", $name, $new_username, $password, $profile_photo, $username);
    } else {
        $stmt = $conn->prepare("UPDATE accounts SET name=?, username=?, password=? WHERE username=?");
        $stmt->bind_param("ssss", $name, $new_username, $password, $username);
    }
} else {
    if ($profile_photo) {
        $stmt = $conn->prepare("UPDATE accounts SET name=?, username=?, profile_photo=? WHERE username=?");
        $stmt->bind_param("ssss", $name, $new_username, $profile_photo, $username);
    } else {
        $stmt = $conn->prepare("UPDATE accounts SET name=?, username=? WHERE username=?");
        $stmt->bind_param("sss", $name, $new_username, $username);
    }
}

if ($stmt->execute()) {
    $_SESSION['username'] = $new_username;
    header("Location: profile.php?success=1");
    exit();
} else {
    header("Location: profile.php?error=1");
    exit();
}

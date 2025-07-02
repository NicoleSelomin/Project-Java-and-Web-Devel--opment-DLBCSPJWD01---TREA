<?php
session_start();
require_once 'db_connect.php';

function showError($msg) {
    $_SESSION['error'] = $msg;
    header('Location: client-signup.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') showError('Invalid request.');

$name     = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone_number'] ?? '');
$address  = trim($_POST['address'] ?? '');
$password = $_POST['password'] ?? '';

if ($name === '' || $email === '' || $phone === '' || $address === '' || $password === '') showError('All fields are required.');

// Email uniqueness check
$check = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
$check->execute([$email]);
if ($check->fetchColumn()) showError('Email already registered. <a href="user-login.php">Log in</a>.');

$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower(str_replace(' ', '_', $name)));

// Insert into users table
$stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone_number, user_type, address, created_at, updated_at)
                       VALUES (?, ?, ?, ?, 'client', ?, NOW(), NOW())");
$stmt->execute([$name, $email, $hashed_password, $phone, $address]);
$user_id = $pdo->lastInsertId();

// Always create uploads folder
$finalFolder = "uploads/clients/{$user_id}_{$safeName}/";
if (!is_dir($finalFolder) && !mkdir($finalFolder, 0755, true)) showError('Failed to create upload directory.');

// Cropped image (priority)
if (!empty($_POST['cropped_image'])) {
    $img_data = base64_decode(str_replace('data:image/jpeg;base64,', '', $_POST['cropped_image']));
    file_put_contents($finalFolder . "profile.jpg", $img_data);
    $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?")->execute([$finalFolder . "profile.jpg", $user_id]);
}
// Else normal file upload
elseif (!empty($_FILES['profile_picture']['tmp_name']) && is_uploaded_file($_FILES['profile_picture']['tmp_name'])) {
    $allowed_types = ['image/jpeg', 'image/png'];
    if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
        showError('Invalid profile picture file type.');
    }
    if ($_FILES['profile_picture']['size'] > 2*1024*1024) {
        showError('Profile picture too large. Max 2MB.');
    }
    $profilePath = $finalFolder . "profile.jpg";
    move_uploaded_file($_FILES['profile_picture']['tmp_name'], $profilePath);
    $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?")->execute([$profilePath, $user_id]);
}

// Insert into clients table
$pdo->prepare("INSERT INTO clients (client_id, user_id, address) VALUES (?, ?, ?)")->execute([$user_id, $user_id, $address]);

// Session
$_SESSION['client_id'] = $user_id;
$_SESSION['user_id']   = $user_id;
$_SESSION['user_type'] = 'client';
$_SESSION['user_name'] = $name;

// Redirect
$redirect = $_SESSION['redirect_after_login'] ?? 'client-profile.php';
unset($_SESSION['redirect_after_login']);
header("Location: $redirect");
exit;
?>

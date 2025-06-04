<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// A helper function to respond and exit immediately
function respond($success, $message = "", $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, "Invalid request method. POST required.");
}

if (!isset($_FILES['profile_pic'])) {
    respond(false, "No profile picture file uploaded.");
}

if (!isset($_POST['user_id'])) {
    respond(false, "No user_id provided.");
}

$user_id = intval($_POST['user_id']);

// Define upload directory relative to this file
$upload_dir = __DIR__ . '/profile/profile-pics/';

if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        respond(false, "Failed to create upload directory.");
    }
}

$filename = uniqid() . '_' . basename($_FILES['profile_pic']['name']);
$target_path = $upload_dir . $filename;

if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_path)) {
    respond(false, "Failed to move uploaded file.");
}

// Connect to database
require_once(__DIR__ . '/../config/database.php');

if (!$mysqli) {
    respond(false, "Database connection failed.");
}

// Store relative path for DB (change this based on your public URL setup)
$relative_path = '/mmsu-backend/profile/profile-pics/' . $filename;

// Prepare statement to update user profile pic URL
$stmt = $mysqli->prepare("UPDATE USERS SET profile_pic_url = ? WHERE user_id = ?");
if (!$stmt) {
    respond(false, "Prepare statement failed: " . $mysqli->error);
}

$stmt->bind_param("si", $relative_path, $user_id);

if (!$stmt->execute()) {
    respond(false, "Database update failed: " . $stmt->error);
}

if ($stmt->affected_rows > 0) {
    respond(true, "Profile picture updated successfully.", ['profile_pic_url' => $relative_path]);
} else {
    respond(false, "No rows updated. Maybe invalid user_id?");
}

$stmt->close();
$mysqli->close();
?>

<?php
require_once '../config/database.php';
header("Content-Type: application/json");

// Get user_id from query param
$user_id = $_GET['user_id'] ?? null;

if (!$user_id || !is_numeric($user_id)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid user_id"]);
    exit;
}

// Prepare the SQL query
$stmt = $mysqli->prepare("SELECT user_id, username, email, profile_pic_url FROM USERS WHERE user_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to prepare statement"]);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
}

$stmt->close();
$mysqli->close();

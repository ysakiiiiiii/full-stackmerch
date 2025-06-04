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

// Query to get user info and role
$query = "
    SELECT u.user_id, u.username, u.email, u.profile_pic_url, r.role
    FROM USERS u
    LEFT JOIN ROLES r ON u.user_id = r.user_id
    WHERE u.user_id = ?
";

$stmt = $mysqli->prepare($query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to prepare statement"]);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $row['role'] = $row['role'] ?? 'customer'; // Default role fallback
    echo json_encode($row);
} else {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
}

$stmt->close();
$mysqli->close();

<?php
session_start();
require_once '../config/database.php';

header("Content-Type: application/json");

$input = json_decode(file_get_contents('php://input'), true);
$loginInput = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($loginInput) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "Both username/email and password are required"]);
    exit;
}

// Check whether the input is email or username
if (filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
    $stmt = $mysqli->prepare("SELECT user_id, username, password_hash FROM USERS WHERE email = ?");
} else {
    $stmt = $mysqli->prepare("SELECT user_id, username, password_hash FROM USERS WHERE username = ?");
}

if (!$stmt) {
    error_log("❌ SQL prepare failed for login input");
    http_response_code(500);
    echo json_encode(["error" => "Failed to prepare login query"]);
    exit;
}

$stmt->bind_param("s", $loginInput);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if (password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];

        // ✅ Log basic info
        error_log("🔐 Login successful for user_id: {$user['user_id']}, username: {$user['username']}");

        // Fetch role
        $roleStmt = $mysqli->prepare("SELECT role FROM ROLES WHERE user_id = ? LIMIT 1");
        $roleStmt->bind_param("i", $user['user_id']);
        $roleStmt->execute();
        $roleResult = $roleStmt->get_result();

        $roleRow = $roleResult->fetch_assoc();
        $role = $roleRow['role'] ?? 'customer';

        error_log("🔎 Role fetched for user_id {$user['user_id']}: {$role}");

        echo json_encode([
            "message" => "Login successful",
            "user_id" => $user['user_id'],
            "username" => $user['username'],
            "role" => $role
        ]);
    } else {
        http_response_code(401);
        error_log("❌ Invalid password for username/email: {$loginInput}");
        echo json_encode(["error" => "Invalid password"]);
    }
} else {
    http_response_code(404);
    error_log("❌ User not found for: {$loginInput}");
    echo json_encode(["error" => "User not found"]);
}

$stmt->close();
$mysqli->close();

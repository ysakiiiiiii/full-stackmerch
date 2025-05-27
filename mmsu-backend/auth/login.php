<?php
require_once '../config/database.php'; 

$input = json_decode(file_get_contents('php://input'), true);
$loginInput = $input['username'] ?? '';  
$password = $input['password'] ?? '';

if (empty($loginInput) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "Both username/email and password are required"]);
    exit;
}

// Prepare query based on whether input is email or username
if (filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
    $stmt = $mysqli->prepare("SELECT user_id, username, password_hash FROM USERS WHERE email = ?");
} else {
    $stmt = $mysqli->prepare("SELECT user_id, username, password_hash FROM USERS WHERE username = ?");
}

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to prepare statement"]);
    exit;
}

$stmt->bind_param("s", $loginInput);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    $hash = $user['password_hash'];

    if (password_verify($password, $hash)) {
        $_SESSION['user_id'] = $user['user_id'];

        // Fetch user role
        $roleStmt = $mysqli->prepare("SELECT role FROM ROLES WHERE user_id = ? LIMIT 1");
        $roleStmt->bind_param("i", $user['user_id']);
        $roleStmt->execute();
        $roleResult = $roleStmt->get_result();
        $roleRow = $roleResult->fetch_assoc();
        $role = $roleRow['role'] ?? 'customer';

        echo json_encode([
            "message" => "Login successful",
            "user_id" => $user['user_id'],
            "username" => $user['username'],
            "role" => $role
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Invalid password"]);
    }
} else {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
}

$stmt->close();
$mysqli->close();

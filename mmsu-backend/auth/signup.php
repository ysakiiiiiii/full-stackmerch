<?php
require_once '../config/database.php'; 

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);

$username = $input['username'] ?? null;
$email = $input['email'] ?? null;
$password = $input['password'] ?? null;
$repeatPassword = $input['repeatPassword'] ?? null;

// Basic validation
if (!$username || !$email || !$password || !$repeatPassword) {
    http_response_code(400);
    echo json_encode(["error" => "All fields (username, email, password, repeatPassword) are required"]);
    exit;
}

if ($password !== $repeatPassword) {
    http_response_code(400);
    echo json_encode(["error" => "Password and repeat password do not match"]);
    exit;
}

$conn = $mysqli;

// Check for duplicate username or email
$checkStmt = $conn->prepare("SELECT user_id FROM USERS WHERE username = ? OR email = ?");
$checkStmt->bind_param("ss", $username, $email);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    http_response_code(409); // Conflict
    echo json_encode(["error" => "username or email already exists"]);
    $checkStmt->close();
    exit;
}
$checkStmt->close();

// Hash password
$password_hash = password_hash($password, PASSWORD_BCRYPT);

// Create user
$stmt = $conn->prepare("INSERT INTO USERS (username, email, password_hash) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $email, $password_hash);

if ($stmt->execute()) {
    $user_id = $stmt->insert_id;

    // Assign default role: 'customer'
    $roleStmt = $conn->prepare("INSERT INTO ROLES (role, user_id) VALUES ('customer', ?)");
    $roleStmt->bind_param("i", $user_id);
    $roleStmt->execute();
    $roleStmt->close();

    echo json_encode(["message" => "Signup successful", "user_id" => $user_id]);
} else {
    http_response_code(400);
    echo json_encode(["error" => "Signup failed", "details" => $stmt->error]);
}

$stmt->close();
$conn->close();

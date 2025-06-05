<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!isset($data['username']) || !isset($data['first_name']) || !isset($data['last_name'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Sanitize inputs
$username = trim($data['username']);
$firstName = trim($data['first_name']);
$lastName = trim($data['last_name']);

// Basic validation
if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Username cannot be empty']);
    exit;
}

try {
    // Check if username already exists (excluding current user)
    $checkStmt = $mysqli->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
    $checkStmt->bind_param("si", $username, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already taken']);
        exit;
    }
    
    // Update user information
    $updateStmt = $mysqli->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ? WHERE user_id = ?");
    $updateStmt->bind_param("sssi", $username, $firstName, $lastName, $userId);
    
    if ($updateStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
    
    $updateStmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close();
?>
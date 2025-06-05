<?php
session_start();
require_once '../config/database.php';


// Verify user_id is provided
if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

$userId = (int)$_GET['user_id'];

try {
    $stmt = $mysqli->prepare("SELECT username, email, first_name, last_name FROM users WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => $userData
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'User not found',
            'debug' => [
                'received_user_id' => $userId,
                'session' => $_SESSION
            ]
        ]);
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
}

$stmt->close();
$mysqli->close();
?>
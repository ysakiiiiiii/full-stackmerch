<?php
require_once '../config/database.php';

// Get user_id from query parameters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    $stmt = $mysqli->prepare(
        "SELECT country, province, municipality, barangay, zip_code 
         FROM addresses 
         WHERE user_id = ? LIMIT 1"
    );
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $address = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => $address 
        ]);
    } else {
        echo json_encode(['success' => true, 'data' => null]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$mysqli->close();
?>
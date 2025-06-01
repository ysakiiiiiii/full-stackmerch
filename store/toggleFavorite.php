<?php
session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$product_id = intval($data['product_id'] ?? 0);

if ($product_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid product ID"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Prepare statement for checking favorite
$stmt = $mysqli->prepare("SELECT 1 FROM FAVORITES WHERE user_id = ? AND product_id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database prepare failed: " . $mysqli->error]);
    exit;
}

$stmt->bind_param("ii", $user_id, $product_id);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["error" => "Database execute failed: " . $stmt->error]);
    exit;
}

$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Exists - remove it
    $stmt->close();
    $del = $mysqli->prepare("DELETE FROM FAVORITES WHERE user_id = ? AND product_id = ?");
    if (!$del) {
        http_response_code(500);
        echo json_encode(["error" => "Database prepare failed: " . $mysqli->error]);
        exit;
    }
    $del->bind_param("ii", $user_id, $product_id);
    if (!$del->execute()) {
        http_response_code(500);
        echo json_encode(["error" => "Database execute failed: " . $del->error]);
        exit;
    }
    $del->close();
    echo json_encode(["success" => true, "action" => "removed"]);
    exit;
} else {
    // Not exists - add it
    $stmt->close();
    $ins = $mysqli->prepare("INSERT INTO FAVORITES (user_id, product_id) VALUES (?, ?)");
    if (!$ins) {
        http_response_code(500);
        echo json_encode(["error" => "Database prepare failed: " . $mysqli->error]);
        exit;
    }
    $ins->bind_param("ii", $user_id, $product_id);
    if (!$ins->execute()) {
        http_response_code(500);
        echo json_encode(["error" => "Database execute failed: " . $ins->error]);
        exit;
    }
    $ins->close();
    echo json_encode(["success" => true, "action" => "added"]);
    exit;
}

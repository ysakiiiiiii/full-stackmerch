<?php
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
$stmt = $mysqli->prepare("DELETE FROM FAVORITES WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $user_id, $product_id);
$stmt->execute();

echo json_encode(["success" => true]);
$stmt->close();

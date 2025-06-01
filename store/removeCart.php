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
$cart_id = $mysqli->query("SELECT cart_id FROM CARTS WHERE user_id = $user_id")->fetch_assoc()['cart_id'] ?? null;

if (!$cart_id) {
    echo json_encode(["error" => "Cart not found"]);
    exit;
}

$stmt = $mysqli->prepare("DELETE FROM CART_ITEMS WHERE cart_id = ? AND product_id = ?");
$stmt->bind_param("ii", $cart_id, $product_id);
$stmt->execute();

echo json_encode(["success" => true]);
$stmt->close();

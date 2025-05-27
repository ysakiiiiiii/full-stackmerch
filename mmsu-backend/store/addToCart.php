<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$product_id = intval($data['product_id'] ?? 0);
$quantity = intval($data['quantity'] ?? 1);

if ($product_id <= 0 || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid product ID or quantity"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get or create cart
$cart = $mysqli->query("SELECT cart_id FROM CARTS WHERE user_id = $user_id")->fetch_assoc();
if (!$cart) {
    $mysqli->query("INSERT INTO CARTS (user_id) VALUES ($user_id)");
    $cart_id = $mysqli->insert_id;
} else {
    $cart_id = $cart['cart_id'];
}

// Insert or update quantity
$stmt = $mysqli->prepare("
    INSERT INTO CART_ITEMS (cart_id, product_id, quantity)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
");
$stmt->bind_param("iii", $cart_id, $product_id, $quantity);
$stmt->execute();

echo json_encode(["success" => true]);
$stmt->close();

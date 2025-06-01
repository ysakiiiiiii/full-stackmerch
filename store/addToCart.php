<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "User not logged in"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$product_id = intval($data['product_id'] ?? 0);
$quantity = intval($data['quantity'] ?? 1);

if ($product_id <= 0 || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid product ID or quantity"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get existing cart or create a new one
$cartQuery = $mysqli->prepare("SELECT cart_id FROM CARTS WHERE user_id = ?");
$cartQuery->bind_param("i", $user_id);
$cartQuery->execute();
$cartResult = $cartQuery->get_result();

if ($cartRow = $cartResult->fetch_assoc()) {
    $cart_id = $cartRow['cart_id'];
} else {
    $insertCart = $mysqli->prepare("INSERT INTO CARTS (user_id) VALUES (?)");
    $insertCart->bind_param("i", $user_id);
    $insertCart->execute();
    $cart_id = $insertCart->insert_id;
    $insertCart->close();
}
$cartQuery->close();

// Insert new or update quantity
$insertItem = $mysqli->prepare("
    INSERT INTO CART_ITEMS (cart_id, product_id, quantity)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
");
$insertItem->bind_param("iii", $cart_id, $product_id, $quantity);
$success = $insertItem->execute();
$insertItem->close();

if ($success) {
    echo json_encode(["success" => true]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to add item to cart"]);
}
?>

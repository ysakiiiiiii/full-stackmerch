<?php
require_once '../config/database.php'; // Update path if needed
session_start();

header("Content-Type: application/json");

// 1. Make sure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

// Optionally, get additional data like shipping address or payment method
$shippingAddressId = $data['shipping_address_id'] ?? null;
$paymentMethod = $data['payment_method'] ?? null;

// 2. Get items from user's cart
$cartQuery = $mysqli->prepare("SELECT c.product_id, c.quantity, p.price FROM CART c JOIN PRODUCTS p ON c.product_id = p.product_id WHERE c.user_id = ?");
$cartQuery->bind_param("i", $userId);
$cartQuery->execute();
$result = $cartQuery->get_result();
$cartItems = $result->fetch_all(MYSQLI_ASSOC);

if (empty($cartItems)) {
    echo json_encode(["success" => false, "message" => "Cart is empty"]);
    exit;
}

// 3. Calculate total
$totalAmount = 0;
foreach ($cartItems as $item) {
    $totalAmount += $item['price'] * $item['quantity'];
}

// 4. Create the order
$orderQuery = $mysqli->prepare("INSERT INTO ORDERS (user_id, total_amount, shipping_address_id, payment_method) VALUES (?, ?, ?, ?)");
$orderQuery->bind_param("idis", $userId, $totalAmount, $shippingAddressId, $paymentMethod);
$orderQuery->execute();
$orderId = $orderQuery->insert_id;

// 5. Insert items into ORDER_ITEMS
$itemQuery = $mysqli->prepare("INSERT INTO ORDER_ITEMS (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
foreach ($cartItems as $item) {
    $itemQuery->bind_param("iiid", $orderId, $item['product_id'], $item['quantity'], $item['price']);
    $itemQuery->execute();
}

// 6. Clear the user's cart
$clearQuery = $mysqli->prepare("DELETE FROM CART WHERE user_id = ?");
$clearQuery->bind_param("i", $userId);
$clearQuery->execute();

// 7. Return success
echo json_encode(["success" => true, "message" => "Checkout complete", "order_id" => $orderId]);
?>

<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get cart ID for user
$cartQuery = $mysqli->prepare("SELECT cart_id FROM CARTS WHERE user_id = ?");
$cartQuery->bind_param("i", $user_id);
$cartQuery->execute();
$cartResult = $cartQuery->get_result();

$cartItems = [];

if ($cartRow = $cartResult->fetch_assoc()) {
    $cart_id = $cartRow['cart_id'];

    $itemsQuery = $mysqli->prepare("
        SELECT 
            ci.cart_item_id,
            ci.product_id,
            ci.quantity,
            p.name,
            CONCAT('₱', FORMAT(p.price, 2)) AS price,
            pi.image_url AS image
        FROM CART_ITEMS ci
        JOIN PRODUCTS p ON ci.product_id = p.product_id
        LEFT JOIN PRODUCT_IMAGES pi ON pi.product_id = p.product_id 
        WHERE ci.cart_id = ?
    ");
    $itemsQuery->bind_param("i", $cart_id);
    $itemsQuery->execute();
    $itemsResult = $itemsQuery->get_result();

    while ($item = $itemsResult->fetch_assoc()) {
        $cartItems[] = $item;
    }

    $itemsQuery->close();
}

$cartQuery->close();
echo json_encode($cartItems);

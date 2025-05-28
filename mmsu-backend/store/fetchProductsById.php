<?php
// store/fetchProductsById.php

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product ID']);
    exit();
}

$sql = "
    SELECT 
        p.product_id AS id,
        p.name,
        p.description,
        CONCAT('₱', FORMAT(p.price, 2)) AS price,
        c.name AS category,
        pi.image_url AS image,
        p.color AS color
    FROM PRODUCTS p
    LEFT JOIN CATEGORIES c ON p.category_id = c.category_id
    LEFT JOIN PRODUCT_IMAGES pi ON pi.product_id = p.product_id
    WHERE p.product_id = ?
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $product = $result->fetch_assoc()) {
    echo json_encode($product);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found']);
}

$stmt->close();
$mysqli->close();

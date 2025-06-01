<?php
session_start();
require_once '../config/database.php';

$sql = "
    SELECT 
        p.product_id AS id,
        p.name,
        p.description,
        p.price AS price,
        c.name AS category,
        pi.image_url AS image,
        p.color AS color
    FROM PRODUCTS p
    LEFT JOIN CATEGORIES c ON p.category_id = c.category_id
    LEFT JOIN PRODUCT_IMAGES pi ON pi.product_id = p.product_id
";

$result = $mysqli->query($sql);

$products = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

echo json_encode($products);

$mysqli->close();

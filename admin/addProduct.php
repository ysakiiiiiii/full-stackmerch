<?php
require("../config/database.php");
header('Content-Type: application/json');


$name = $_POST['name'] ?? '';
$price = floatval($_POST['price'] ?? 0);
$category_id = intval($_POST['category_id'] ?? 0);
$color = $_POST['color'] ?? '';
$description = $_POST['description'] ?? '';

if (!$name || !$price || !$category_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Image upload
$image_url = '';
if (!empty($_FILES['image']['name'])) {
    $targetDir = __DIR__ . '/../../mmsu-frontend/public/product-image/';
    $filename = basename($_FILES['image']['name']);
    $targetFile = $targetDir . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
        $image_url = "/product-image/" . $filename;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
        exit();
    }
}

// Insert product
$sql = "INSERT INTO PRODUCTS (name, price, category_id, color, description)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("sdiss", $name, $price, $category_id, $color, $description);

if ($stmt->execute()) {
    $product_id = $stmt->insert_id;

    // Insert image if uploaded
    if ($image_url) {
        $img_stmt = $mysqli->prepare("INSERT INTO PRODUCT_IMAGES (product_id, image_url) VALUES (?, ?)");
        $img_stmt->bind_param("is", $product_id, $image_url);
        $img_stmt->execute();
    }

    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $mysqli->error]);
}
?>

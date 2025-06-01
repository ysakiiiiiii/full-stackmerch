<?php
require("../config/database.php");
header('Content-Type: application/json');

$name = $_POST['name'] ?? '';
$price = floatval($_POST['price'] ?? 0);
$category_id = intval($_POST['category_id'] ?? 0);
$color = $_POST['color'] ?? '';
$description = $_POST['description'] ?? '';
$id = intval($_POST['id'] ?? 0);

// Validate input
if (!$id || !$name || !$price || !$category_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Optional image upload
$image_url = '';
if (!empty($_FILES['image']['name'])) {
    $targetDir = __DIR__ . '/../../mmsu-frontend/public/product-image/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true); // create folder if it doesn't exist
    }

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

// Update product data
$update_sql = "UPDATE PRODUCTS SET name=?, price=?, category_id=?, color=?, description=? WHERE product_id=?";
$update_stmt = $mysqli->prepare($update_sql);
$update_stmt->bind_param("sdissi", $name, $price, $category_id, $color, $description, $id);

if ($update_stmt->execute()) {
    // Delete old image (optional - ensures only one image per product)
    $mysqli->query("DELETE FROM PRODUCT_IMAGES WHERE product_id = $id");

    // Insert new image if uploaded
    if ($image_url) {
        $img_stmt = $mysqli->prepare("INSERT INTO PRODUCT_IMAGES (product_id, image_url) VALUES (?, ?)");
        $img_stmt->bind_param("is", $id, $image_url);
        $img_stmt->execute();
    }

    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $mysqli->error]);
}
?>

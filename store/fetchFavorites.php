<?php

require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Default: use logged-in user's ID
$user_id = $_SESSION['user_id'];

if (isset($_GET['username'])) {
    $username = $_GET['username'];

    // Find user ID from username
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $row = $result->fetch_assoc();
    $user_id = $row['id'];
    $stmt->close();
}

// Now fetch favorites for determined user_id
$query = $mysqli->prepare("
    SELECT 
        f.product_id,
        p.name,
        CONCAT('₱', FORMAT(p.price, 2)) AS price,
        pi.image_url AS image
    FROM FAVORITES f
    JOIN PRODUCTS p ON f.product_id = p.product_id
    LEFT JOIN PRODUCT_IMAGES pi ON pi.product_id = p.product_id
    WHERE f.user_id = ?
");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();

$favorites = [];
while ($row = $result->fetch_assoc()) {
    $favorites[] = $row;
}

$query->close();
echo json_encode($favorites);

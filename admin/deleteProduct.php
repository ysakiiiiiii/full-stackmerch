<?php
require_once '../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);

$id = intval($data['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID required']);
    exit();
}

$sql = "DELETE FROM PRODUCTS WHERE product_id=$id";

if ($mysqli->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $mysqli->error]);
}

$mysqli->close();

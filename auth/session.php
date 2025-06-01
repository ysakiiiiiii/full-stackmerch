<?php
session_start();

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");


// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["role" => "guest"]);
    exit;
}

$user_id = $_SESSION['user_id'];

$conn = new mysqli('localhost', 'root', 'root', 'ecommerce_db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$stmt = $conn->prepare("SELECT role FROM ROLES WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$role = "customer";
if ($row = $result->fetch_assoc()) {
    if ($row['role'] === 'admin') {
        $role = "admin";
    }
}

echo json_encode(["role" => $role, "user_id" => $user_id]);

$stmt->close();
$conn->close();

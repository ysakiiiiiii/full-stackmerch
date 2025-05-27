<?php

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: http://localhost:5173");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, OPTIONS, GET");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    http_response_code(200);
    exit();
}

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

session_start();

$host = 'localhost';
$username = 'root';
$password = 'root';
$database = 'ecommerce_db';

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    error_log("Database connection failed: " . $mysqli->connect_error);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

$mysqli->set_charset('utf8mb4');

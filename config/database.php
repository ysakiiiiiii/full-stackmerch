<?php

// Set CORS headers for frontend running at http://localhost:5173 (adjust origin as needed)
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Handle preflight OPTIONS requests and exit early
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection parameters
$host = 'localhost';
$username = 'root';
$password = 'root';
$database = 'ecommerce_db';

// Create a new mysqli connection object
$mysqli = new mysqli($host, $username, $password, $database);

// Check connection
if ($mysqli->connect_errno) {
    error_log("Database connection failed: " . $mysqli->connect_error);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to connect to database'
    ]);
    exit();
}

// Set charset for proper encoding
if (!$mysqli->set_charset("utf8mb4")) {
    error_log("Error loading character set utf8mb4: " . $mysqli->error);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading character set'
    ]);
    exit();
}

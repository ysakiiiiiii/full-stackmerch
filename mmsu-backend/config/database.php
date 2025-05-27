<?php

// Handle preflight requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: http://localhost:5173");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, OPTIONS, GET");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    http_response_code(200);
    exit();
}

// Set CORS headers for actual requests
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Start the session
session_start();

// Database connection parameters
$host = 'localhost';
$username = 'root';
$password = 'root';
$database = 'ecommerce_db';

// Create a new mysqli instance
$mysqli = new mysqli($host, $username, $password, $database);

// Check for connection errors
if ($mysqli->connect_error) {
    error_log("Database connection failed: " . $mysqli->connect_error);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

// Set the character set to utf8mb4 for proper encoding
if (!$mysqli->set_charset('utf8mb4')) {
    error_log("Error loading character set utf8mb4: " . $mysqli->error);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading character set'
    ]);
    exit();
}

// Optionally, you can return the $mysqli object for use in other scripts
return $mysqli;

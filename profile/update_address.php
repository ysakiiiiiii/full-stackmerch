<?php
// CORS headers for React (running at localhost:5173)
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Preflight check
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Include DB connection
require_once("../config/database.php");

// Decode input
$data = json_decode(file_get_contents("php://input"), true);

// ✅ Validate only address fields
if (
    !isset($data["user_id"]) ||
    !isset($data["country"]) ||
    !isset($data["city"]) ||
    !isset($data["zip"])
) {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields"
    ]);
    exit();
}

$user_id = $data["user_id"];
$country = $data["country"];
$city = $data["city"];
$zip = $data["zip"];

// Update address in DB
$query = "UPDATE users SET country = ?, city = ?, zip = ? WHERE user_id = ?";
$stmt = $mysqli->prepare($query);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to prepare statement"
    ]);
    exit();
}

$stmt->bind_param("sssi", $country, $city, $zip, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Address updated successfully"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Database update failed"
    ]);
}

$stmt->close();
$mysqli->close();

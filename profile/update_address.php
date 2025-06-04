<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    $mysqli->begin_transaction();

    // Check if address exists
    $check = $mysqli->prepare("SELECT user_id FROM addresses WHERE user_id = ?");
    $check->bind_param("i", $data['user_id']);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$exists) {
        // Insert new address if none exists
        $stmt = $mysqli->prepare(
            "INSERT INTO addresses 
            (user_id, country, province, municipality, barangay, zip_code) 
            VALUES (?, ?, ?, ?, ?, ?)"
        );
    } else {
        // Update existing address
        $stmt = $mysqli->prepare(
            "UPDATE addresses SET
                country = ?,
                province = ?,
                municipality = ?,
                barangay = ?,
                zip_code = ?
            WHERE user_id = ?"
        );
    }

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }

    // Bind parameters based on operation
    if (!$exists) {
        $stmt->bind_param(
            "isssss",
            $data['user_id'],
            $data['country'],
            $data['province'],
            $data['municipality'],
            $data['barangay'],
            $data['zip_code']
        );
    } else {
        $stmt->bind_param(
            "sssssi",
            $data['country'],
            $data['province'],
            $data['municipality'],
            $data['barangay'],
            $data['zip_code'],
            $data['user_id']
        );
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $mysqli->commit();
    echo json_encode([
        'success' => true,
        'message' => $exists ? 'Address updated successfully' : 'Address created successfully'
    ]);
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
    $mysqli->close();
}
?>
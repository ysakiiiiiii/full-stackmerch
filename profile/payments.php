<?php
// Set CORS headers for frontend running at http://localhost:5173
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, PUT, GET, OPTIONS, DELETE");
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

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get payment ID from URL or query parameters
$paymentId = null;
if (isset($_GET['id'])) {
    $paymentId = (int)$_GET['id'];
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

// If payment ID is in input data, use that
if (isset($input['payment_id'])) {
    $paymentId = (int)$input['payment_id'];
}

try {
    switch ($method) {
        case 'GET':
            // Get payment methods for a user
            if (!isset($_GET['user_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required']);
                exit;
            }
            
            $userId = (int)$_GET['user_id'];
            $query = "SELECT * FROM USER_PAYMENTS WHERE user_id = ?";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $payments = [];
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            
            echo json_encode($payments);
            break;
            
        case 'POST':
            // Add a new payment method
            if (!isset($input['user_id']) || !isset($input['type'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }
            
            $userId = (int)$input['user_id'];
            $type = $input['type'];
            $isDefault = isset($input['is_default']) ? (bool)$input['is_default'] : false;
            
            // Begin transaction
            $mysqli->begin_transaction();
            
            try {
                // If setting as default, first unset any existing defaults
                if ($isDefault) {
                    $updateQuery = "UPDATE USER_PAYMENTS SET is_default = FALSE WHERE user_id = ?";
                    $updateStmt = $mysqli->prepare($updateQuery);
                    $updateStmt->bind_param("i", $userId);
                    $updateStmt->execute();
                }
                
                if ($type === 'card') {
                    if (!isset($input['card_number']) || !isset($input['card_expiry'])) {
                        throw new Exception('Missing card details');
                    }
                    
                    $cardNumber = $input['card_number'];
                    $cardExpiry = $input['card_expiry'];
                    
                    $query = "INSERT INTO USER_PAYMENTS (user_id, type, card_number, card_expiry, is_default) 
                              VALUES (?, ?, ?, ?, ?)";
                    $stmt = $mysqli->prepare($query);
                    $stmt->bind_param("isssi", $userId, $type, $cardNumber, $cardExpiry, $isDefault);
                } else if ($type === 'gcash') {
                    if (!isset($input['gcash_phone'])) {
                        throw new Exception('Missing GCash phone number');
                    }
                    
                    $gcashPhone = $input['gcash_phone'];
                    
                    $query = "INSERT INTO USER_PAYMENTS (user_id, type, gcash_phone, is_default) 
                              VALUES (?, ?, ?, ?)";
                    $stmt = $mysqli->prepare($query);
                    $stmt->bind_param("issi", $userId, $type, $gcashPhone, $isDefault);
                } else {
                    throw new Exception('Invalid payment type');
                }
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to add payment method');
                }
                
                $paymentId = $mysqli->insert_id;
                $mysqli->commit();
                
                // Return the newly created payment method
                $query = "SELECT * FROM USER_PAYMENTS WHERE payment_id = ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("i", $paymentId);
                $stmt->execute();
                $result = $stmt->get_result();
                $newPayment = $result->fetch_assoc();
                
                echo json_encode($newPayment);
            } catch (Exception $e) {
                $mysqli->rollback();
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        case 'PUT':
            // Update a payment method
            if (!$paymentId) {
                http_response_code(400);
                echo json_encode(['error' => 'Payment ID is required']);
                exit;
            }
            
            // Begin transaction
            $mysqli->begin_transaction();
            
            try {
                // First get the current payment method
                $query = "SELECT * FROM USER_PAYMENTS WHERE payment_id = ? FOR UPDATE";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("i", $paymentId);
                $stmt->execute();
                $result = $stmt->get_result();
                $currentPayment = $result->fetch_assoc();
                
                if (!$currentPayment) {
                    throw new Exception('Payment method not found');
                }
                
                $type = $currentPayment['type'];
                $isDefault = isset($input['is_default']) ? (bool)$input['is_default'] : $currentPayment['is_default'];
                
                // If setting as default, first unset any existing defaults
                if ($isDefault && !$currentPayment['is_default']) {
                    $updateQuery = "UPDATE USER_PAYMENTS SET is_default = FALSE WHERE user_id = ?";
                    $updateStmt = $mysqli->prepare($updateQuery);
                    $updateStmt->bind_param("i", $currentPayment['user_id']);
                    $updateStmt->execute();
                }
                
                if ($type === 'card') {
                    $cardNumber = isset($input['card_number']) ? $input['card_number'] : $currentPayment['card_number'];
                    $cardExpiry = isset($input['card_expiry']) ? $input['card_expiry'] : $currentPayment['card_expiry'];
                    
                    $query = "UPDATE USER_PAYMENTS SET 
                              card_number = ?, 
                              card_expiry = ?, 
                              is_default = ? 
                              WHERE payment_id = ?";
                    $stmt = $mysqli->prepare($query);
                    $stmt->bind_param("ssii", $cardNumber, $cardExpiry, $isDefault, $paymentId);
                } else if ($type === 'gcash') {
                    $gcashPhone = isset($input['gcash_phone']) ? $input['gcash_phone'] : $currentPayment['gcash_phone'];
                    
                    $query = "UPDATE USER_PAYMENTS SET 
                              gcash_phone = ?, 
                              is_default = ? 
                              WHERE payment_id = ?";
                    $stmt = $mysqli->prepare($query);
                    $stmt->bind_param("sii", $gcashPhone, $isDefault, $paymentId);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update payment method');
                }
                
                $mysqli->commit();
                
                // Return the updated payment method
                $query = "SELECT * FROM USER_PAYMENTS WHERE payment_id = ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("i", $paymentId);
                $stmt->execute();
                $result = $stmt->get_result();
                $updatedPayment = $result->fetch_assoc();
                
                echo json_encode($updatedPayment);
            } catch (Exception $e) {
                $mysqli->rollback();
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        case 'DELETE':
            // Delete a payment method
            if (!$paymentId) {
                http_response_code(400);
                echo json_encode(['error' => 'Payment ID is required']);
                exit;
            }
            
            // Begin transaction
            $mysqli->begin_transaction();
            
            try {
                // First check if this is the default payment method
                $query = "SELECT user_id, is_default FROM USER_PAYMENTS WHERE payment_id = ? FOR UPDATE";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("i", $paymentId);
                $stmt->execute();
                $result = $stmt->get_result();
                $payment = $result->fetch_assoc();
                
                if (!$payment) {
                    throw new Exception('Payment method not found');
                }
                
                $isDefault = $payment['is_default'];
                $userId = $payment['user_id'];
                
                // Delete the payment method
                $query = "DELETE FROM USER_PAYMENTS WHERE payment_id = ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("i", $paymentId);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to delete payment method');
                }
                
                // If we deleted the default payment, set another one as default
                if ($isDefault) {
                    $query = "SELECT payment_id FROM USER_PAYMENTS WHERE user_id = ? LIMIT 1";
                    $stmt = $mysqli->prepare($query);
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $newDefault = $result->fetch_assoc();
                    
                    if ($newDefault) {
                        $updateQuery = "UPDATE USER_PAYMENTS SET is_default = TRUE WHERE payment_id = ?";
                        $updateStmt = $mysqli->prepare($updateQuery);
                        $updateStmt->bind_param("i", $newDefault['payment_id']);
                        $updateStmt->execute();
                    }
                }
                
                $mysqli->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $mysqli->rollback();
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
} finally {
    if ($mysqli) {
        $mysqli->close();
    }
}
?>
<?php
require_once  '../config/database.php'; // Adjust path as needed


if (!$mysqli) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get payment ID from URL if present
$paymentId = null;
if (isset($_SERVER['PATH_INFO'])) {
    $request = explode('/', trim($_SERVER['PATH_INFO'], '/'));
    $paymentId = isset($request[0]) && is_numeric($request[0]) ? (int)$request[0] : null;
} elseif (isset($_GET['id'])) {
    $paymentId = (int)$_GET['id'];
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
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['user_id']) || !isset($data['type'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }
            
            $userId = (int)$data['user_id'];
            $type = $data['type'];
            $isDefault = isset($data['is_default']) ? (bool)$data['is_default'] : false;
            
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
                    if (!isset($data['card_number']) || !isset($data['card_expiry'])) {
                        throw new Exception('Missing card details');
                    }
                    
                    $cardNumber = $data['card_number'];
                    $cardExpiry = $data['card_expiry'];
                    
                    $query = "INSERT INTO USER_PAYMENTS (user_id, type, card_number, card_expiry, is_default) 
                              VALUES (?, ?, ?, ?, ?)";
                    $stmt = $mysqli->prepare($query);
                    $stmt->bind_param("isssi", $userId, $type, $cardNumber, $cardExpiry, $isDefault);
                } else if ($type === 'gcash') {
                    if (!isset($data['gcash_phone'])) {
                        throw new Exception('Missing GCash phone number');
                    }
                    
                    $gcashPhone = $data['gcash_phone'];
                    
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
                echo json_encode(['error' => 'Invalid payment ID']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
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
                $isDefault = isset($data['is_default']) ? (bool)$data['is_default'] : $currentPayment['is_default'];
                
                // If setting as default, first unset any existing defaults
                if ($isDefault && !$currentPayment['is_default']) {
                    $updateQuery = "UPDATE USER_PAYMENTS SET is_default = FALSE WHERE user_id = ?";
                    $updateStmt = $mysqli->prepare($updateQuery);
                    $updateStmt->bind_param("i", $currentPayment['user_id']);
                    $updateStmt->execute();
                }
                
                if ($type === 'card') {
                    $cardNumber = isset($data['card_number']) ? $data['card_number'] : $currentPayment['card_number'];
                    $cardExpiry = isset($data['card_expiry']) ? $data['card_expiry'] : $currentPayment['card_expiry'];
                    
                    $query = "UPDATE USER_PAYMENTS SET 
                              card_number = ?, 
                              card_expiry = ?, 
                              is_default = ? 
                              WHERE payment_id = ?";
                    $stmt = $mysqli->prepare($query);
                    $stmt->bind_param("ssii", $cardNumber, $cardExpiry, $isDefault, $paymentId);
                } else if ($type === 'gcash') {
                    $gcashPhone = isset($data['gcash_phone']) ? $data['gcash_phone'] : $currentPayment['gcash_phone'];
                    
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
                echo json_encode(['error' => 'Invalid payment ID']);
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
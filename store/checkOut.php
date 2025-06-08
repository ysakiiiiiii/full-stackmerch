<?php
require_once '../config/database.php';

// Helper function for database operations
function prepare_and_execute($mysqli, $query, $types = "", $params = []) {
    $stmt = $mysqli->prepare($query);
    if ($stmt === false) {
        throw new Exception('Database prepare failed: ' . $mysqli->error);
    }
    if ($types && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        throw new Exception('Database execute failed: ' . $stmt->error);
    }
    return $stmt;
}

// GET endpoint - Fetch user data for pre-filling checkout form
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['userId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }

    $userId = intval($_GET['userId']);

    try {
        // Fetch user basic info
        $stmt = prepare_and_execute($mysqli, 
            "SELECT first_name, last_name, email, contact_number 
             FROM USERS WHERE user_id = ?", 
            "i", [$userId]);
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        // Fetch default address
        $stmt = prepare_and_execute($mysqli,
            "SELECT barangay, municipality, province, zip_code 
             FROM ADDRESSES 
             WHERE user_id = ? AND is_default = 1 
             LIMIT 1",
            "i", [$userId]);
        $address = $stmt->get_result()->fetch_assoc();

        // Fetch payment methods
        $stmt = prepare_and_execute($mysqli,
            "SELECT payment_id, type, gcash_phone, card_number, card_expiry 
             FROM USER_PAYMENTS 
             WHERE user_id = ?",
            "i", [$userId]);
        $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Format the response
        $response = [
            'success' => true,
            'customerInfo' => [
                'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'email' => $user['email'],
                'contact' => $user['contact_number'],
                'address' => $address ? implode(', ', [
                    $address['barangay'],
                    $address['municipality'],
                    $address['province'],
                    $address['zip_code']
                ]) : ''
            ],
            'paymentMethods' => array_map(function($payment) {
                return [
                    'id' => $payment['payment_id'],
                    'type' => $payment['type'],
                    'gcashPhone' => $payment['gcash_phone'],
                    'cardNumber' => $payment['card_number'],
                    'cardExpiry' => $payment['card_expiry'],
                    'lastFour' => $payment['type'] === 'gcash' 
                        ? substr($payment['gcash_phone'], -4)
                        : substr($payment['card_number'], -4)
                ];
            }, $payments)
        ];

        echo json_encode($response);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch user data: ' . $e->getMessage()
        ]);
    }
    exit;
}

// POST endpoint - Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['customerInfo']) || empty($input['cartItems']) || empty($input['totalAmount'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    $customerInfo = $input['customerInfo'];
    $cartItems = $input['cartItems'];
    $totalAmount = $input['totalAmount'];
    $paymentMethod = $input['paymentMethod'] ?? 'cod';
    $gcashPhone = $input['gcashPhone'] ?? null;
    $cardNumber = $input['cardNumber'] ?? null;
    $cardExpiry = $input['cardExpiry'] ?? null;
    $userId = $input['userId'] ?? null;
    $useSavedPayment = $input['useSavedPayment'] ?? false;
    $paymentId = $input['paymentId'] ?? null;

    // Start transaction
    $mysqli->begin_transaction();
    
    try {
        // Check if user exists (if userId is provided)
        $userExists = false;
        if ($userId) {
            $stmt = prepare_and_execute($mysqli, "SELECT user_id FROM USERS WHERE user_id = ?", "i", [$userId]);
            $userExists = $stmt->get_result()->num_rows > 0;
        }
        
        // Create user if not exists (guest checkout)
        if (!$userExists) {
            // Generate a random username for guest users
            $guestUsername = 'guest_' . uniqid();
            $nameParts = explode(' ', $customerInfo['name'], 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            
            $stmt = prepare_and_execute(
                $mysqli,
                "INSERT INTO USERS (username, email, first_name, last_name, contact_number) 
                 VALUES (?, ?, ?, ?, ?)",
                "sssss",
                [$guestUsername, $customerInfo['email'], $firstName, $lastName, $customerInfo['contact']]
            );
            $userId = $mysqli->insert_id;
        } else {
            // Update user contact info if changed
            $stmt = prepare_and_execute(
                $mysqli,
                "UPDATE USERS SET 
                    email = ?,
                    contact_number = ?
                 WHERE user_id = ?",
                "ssi",
                [$customerInfo['email'], $customerInfo['contact'], $userId]
            );
        }
        
        // Parse address (assuming format is "Barangay, Municipality, Province, Zip Code")
        $addressParts = array_map('trim', explode(',', $customerInfo['address']));
        $barangay = $addressParts[0] ?? '';
        $municipality = $addressParts[1] ?? '';
        $province = $addressParts[2] ?? '';
        $zipCode = $addressParts[3] ?? '';
        
        // Add/update address
        $stmt = prepare_and_execute(
            $mysqli,
            "INSERT INTO ADDRESSES (user_id, country, province, municipality, barangay, zip_code, is_default) 
             VALUES (?, 'Philippines', ?, ?, ?, ?, 1)",
            "issss",
            [$userId, $province, $municipality, $barangay, $zipCode]
        );
        $addressId = $mysqli->insert_id;
        
        if ($userExists) {
            // Set other addresses as non-default
            prepare_and_execute(
                $mysqli,
                "UPDATE ADDRESSES SET is_default = 0 
                 WHERE user_id = ? AND address_id != ?",
                "ii",
                [$userId, $addressId]
            );
        }
        
        // Handle payment method
        $paymentId = null;
        if ($paymentMethod !== 'cod') {
            if ($useSavedPayment && $input['paymentId']) {
                // Use existing payment method
                $paymentId = intval($input['paymentId']);
            } else {
                // Create new payment method
                $type = $paymentMethod === 'gcash' ? 'gcash' : 'card';
                $stmt = prepare_and_execute(
                    $mysqli,
                    "INSERT INTO USER_PAYMENTS (user_id, type, gcash_phone, card_number, card_expiry, is_default) 
                     VALUES (?, ?, ?, ?, ?, 1)",
                    "issss",
                    [
                        $userId,
                        $type,
                        $paymentMethod === 'gcash' ? $gcashPhone : null,
                        $paymentMethod === 'card' ? $cardNumber : null,
                        $paymentMethod === 'card' ? $cardExpiry : null
                    ]
                );
                $paymentId = $mysqli->insert_id;
                
                if ($userExists) {
                    // Set other payment methods as non-default
                    prepare_and_execute(
                        $mysqli,
                        "UPDATE USER_PAYMENTS SET is_default = 0 
                         WHERE user_id = ? AND payment_id != ?",
                        "ii",
                        [$userId, $paymentId]
                    );
                }
            }
        }
        
        // Create order
        $status = $paymentMethod === 'cod' ? 'pending' : 'processing';
        $stmt = prepare_and_execute(
            $mysqli,
            "INSERT INTO ORDERS (user_id, shipping_address_id, payment_id, total_amount, status, order_date) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            "iiids",
            [$userId, $addressId, $paymentId, $totalAmount, $status]
        );
        $orderId = $mysqli->insert_id;
        
        // Add order items
        foreach ($cartItems as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity']) || !isset($item['price'])) {
                throw new Exception('Invalid cart item format');
            }
            
            prepare_and_execute(
                $mysqli,
                "INSERT INTO ORDER_ITEMS (order_id, product_id, quantity, price) 
                 VALUES (?, ?, ?, ?)",
                "iiid",
                [$orderId, $item['product_id'], $item['quantity'], $item['price']]
            );
            
            // Debug output for each item
            error_log("Inserted order item: OrderID=$orderId, ProductID={$item['product_id']}, Qty={$item['quantity']}, Price={$item['price']}");
        }
        
        // Commit transaction
        $mysqli->commit();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Order placed successfully',
            'orderId' => $orderId,
            'isGuest' => !$userExists
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $mysqli->rollback();
        error_log("Order processing error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Order failed: ' . $e->getMessage(),
            'errorDetails' => $e->getTraceAsString()
        ]);
    }
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);

$mysqli->close();
?>
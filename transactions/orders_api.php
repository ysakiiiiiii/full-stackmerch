<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

function prepare_and_execute($mysqli, $query, $types = "", $params = []) {
    $stmt = $mysqli->prepare($query);
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['message' => 'Database prepare failed: ' . $mysqli->error]);
        exit;
    }
    if ($types && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $order_id = intval($_GET['id']); 
            $stmt = prepare_and_execute($mysqli, 
                "SELECT o.order_id as id, 
                        CONCAT(u.first_name, ' ', u.last_name) as customer, 
                        DATE_FORMAT(o.order_date, '%b %d, %Y') as date, 
                        o.total_amount as amount, 
                        o.status 
                 FROM ORDERS o
                 JOIN USERS u ON o.user_id = u.user_id
                 WHERE o.order_id = ?", 
                 "i", [$order_id]);
            $result = $stmt->get_result();
            $order = $result->fetch_assoc();

            if ($order) {
                // Get order items
                $stmt = prepare_and_execute($mysqli, 
                    "SELECT p.name, p.description, p.price, oi.quantity, 
                            (SELECT image_url FROM PRODUCT_IMAGES WHERE product_id = p.product_id LIMIT 1) as img
                     FROM ORDER_ITEMS oi
                     JOIN PRODUCTS p ON oi.product_id = p.product_id
                     WHERE oi.order_id = ?", 
                     "i", [$order_id]);
                $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                $order['products'] = $items;

                // Get customer details
                $stmt = prepare_and_execute($mysqli, 
                    "SELECT u.email, 
                            (SELECT phone_number FROM USER_CONTACT_NUMBERS WHERE user_id = u.user_id AND is_default = 1 LIMIT 1) as mobile,
                            (SELECT COUNT(*) FROM ORDERS WHERE user_id = u.user_id) as order_count,
                            (SELECT profile_pic_url FROM USERS WHERE user_id = u.user_id LIMIT 1) as avatar
                     FROM USERS u
                     JOIN ORDERS o ON u.user_id = o.user_id
                     WHERE o.order_id = ?", 
                     "i", [$order_id]);
                $customer = $stmt->get_result()->fetch_assoc();

                if ($customer['avatar']) {
                    $customer['avatar'] = 'http://' . $_SERVER['HTTP_HOST'] . $customer['avatar'];
                } else {
                    $customer['avatar'] = 'http://' . $_SERVER['HTTP_HOST'] . '/MMSU/mmsu-backend/profile/profile/profile-pics/user.png';
                }

                $order['customer_details'] = $customer;

                // Get shipping address
                $stmt = prepare_and_execute($mysqli, 
                    "SELECT 
                        a.barangay as line1, 
                        a.municipality as city, 
                        a.province as province,
                        a.zip_code as postal_code, 
                        a.country as country
                     FROM ADDRESSES a
                     JOIN ORDERS o ON a.address_id = o.shipping_address_id
                     WHERE o.order_id = ?", 
                     "i", [$order_id]);
                $address = $stmt->get_result()->fetch_assoc();

                $order['shipping_address'] = $address;

                echo json_encode($order);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Order not found']);
            }
        } else {
            // Get all orders with pagination
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
            $offset = ($page - 1) * $limit;
            $search = isset($_GET['search']) ? $_GET['search'] : '';

            $searchParam = "%$search%";

            // Get total count
            $stmt = prepare_and_execute($mysqli, 
                "SELECT COUNT(*) as total 
                 FROM ORDERS o
                 JOIN USERS u ON o.user_id = u.user_id
                 WHERE o.order_id LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?", 
                 "ss", [$searchParam, $searchParam]);
            $total = $stmt->get_result()->fetch_assoc()['total'];

            // Get orders
            $stmt = prepare_and_execute($mysqli, 
                "SELECT o.order_id as id, 
                        CONCAT(u.first_name, ' ', u.last_name) as customer, 
                        DATE_FORMAT(o.order_date, '%b %d, %Y') as date, 
                        CONCAT('P ', FORMAT(o.total_amount, 2)) as amount, 
                        CASE 
                            WHEN o.status = 'pending' THEN 'Pending'
                            WHEN o.status = 'failed' THEN 'Failed'
                            WHEN o.status = 'out for delivery' THEN 'Out for delivery'
                            WHEN o.status = 'ready to pick up' THEN 'Ready to pick up'
                            WHEN o.status = 'delivered' THEN 'Delivered'
                            ELSE o.status
                        END as status
                 FROM ORDERS o
                 JOIN USERS u ON o.user_id = u.user_id
                 WHERE o.order_id LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?
                 ORDER BY o.order_date DESC
                 LIMIT ? OFFSET ?", 
                 "ssii", [$searchParam, $searchParam, $limit, $offset]);
            $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            echo json_encode([
                'data' => $orders,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]);
        }
        break;

case 'PUT':
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($_GET['id']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Missing order ID or status']);
        break;
    }

    $order_id = intval($_GET['id']);
    $status = trim($data['status']);

    $statusMapping = [
        'Pending' => 'pending',
        'Failed' => 'failed',
        'Out for delivery' => 'out for delivery',
        'Ready to pick up' => 'ready to pick up',
        'Delivered' => 'delivered'
    ];

    if (!array_key_exists($status, $statusMapping)) {
        http_response_code(400);
        echo json_encode([
            'message' => 'Invalid status value',
            'valid_statuses' => array_keys($statusMapping)
        ]);
        break;
    }

    $dbStatus = $statusMapping[$status];

    // Check if order exists first
    $check = $mysqli->prepare("SELECT status FROM ORDERS WHERE order_id = ?");
    $check->bind_param("i", $order_id);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['message' => 'Order not found']);
        break;
    }

    $check->bind_result($currentStatus);
    $check->fetch();
    
    if ($currentStatus === $dbStatus) {
        echo json_encode([
            'success' => true,
            'message' => 'Status was already set to this value',
            'status' => $status
        ]);
        break;
    }

    $stmt = $mysqli->prepare("UPDATE ORDERS SET status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $dbStatus, $order_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully',
            'status' => $status
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to update status']);
    }
    break;

    case 'DELETE':
        if (isset($_GET['id'])) {
            $order_id = intval($_GET['id']);

            // First delete order items
            $stmt = prepare_and_execute($mysqli, 
                "DELETE FROM ORDER_ITEMS WHERE order_id = ?", 
                "i", [$order_id]);

            // Then delete the order
            $stmt = prepare_and_execute($mysqli, 
                "DELETE FROM ORDERS WHERE order_id = ?", 
                "i", [$order_id]);

            if ($stmt->affected_rows > 0) {
                echo json_encode(['message' => 'Order deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Order not found']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Missing order ID']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

$mysqli->close();
?>
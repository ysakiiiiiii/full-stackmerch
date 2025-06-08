<?php
// database_init.php

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'root';
$db_name = 'ecommerce_db';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $db_name";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists\n";
} else {
    echo "Error creating database: " . $conn->error . "\n";
}

// Select the database
$conn->select_db($db_name);

// Function to execute SQL queries with error handling
function execute_query($conn, $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Query executed successfully\n";
    } else {
        echo "Error executing query: " . $conn->error . "\n";
        echo "SQL: " . $sql . "\n";
    }
}

// TABLE CREATION (same as before)
execute_query($conn, "
    CREATE TABLE IF NOT EXISTS USERS (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        first_name VARCHAR(50),
        last_name VARCHAR(50),
        profile_pic_url VARCHAR(255),
        contact_number VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS USER_PAYMENTS (
        payment_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('gcash', 'card') NOT NULL,
        gcash_phone VARCHAR(20),
        card_number VARCHAR(20),
        card_expiry VARCHAR(10),
        is_default BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES USERS(user_id) ON DELETE CASCADE
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS USER_CONTACT_NUMBERS (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        is_default BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (user_id) REFERENCES USERS(user_id)
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS ROLES (
        role_id INT AUTO_INCREMENT PRIMARY KEY,
        role ENUM('admin', 'customer', 'vendor') NOT NULL DEFAULT 'customer',
        user_id INT NOT NULL,
        FOREIGN KEY (user_id) REFERENCES USERS(user_id) ON DELETE CASCADE,
        UNIQUE KEY (user_id, role)
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS CATEGORIES (
        category_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS PRODUCTS (
        product_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        price DECIMAL(10,2) NOT NULL,
        category_id INT,
        color VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES CATEGORIES(category_id) ON DELETE SET NULL
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS PRODUCT_IMAGES (
        image_id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        FOREIGN KEY (product_id) REFERENCES PRODUCTS(product_id) ON DELETE CASCADE
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS ADDRESSES (
        address_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        country VARCHAR(50) NOT NULL,
        province VARCHAR(50) NOT NULL,
        municipality VARCHAR(50) NOT NULL,
        barangay VARCHAR(50) NOT NULL,
        zip_code VARCHAR(10) NOT NULL,
        is_default TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES USERS(user_id) ON DELETE CASCADE
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS CARTS (
        cart_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES USERS(user_id) ON DELETE CASCADE
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS CART_ITEMS (
        cart_item_id INT AUTO_INCREMENT PRIMARY KEY,
        cart_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cart_id) REFERENCES CARTS(cart_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES PRODUCTS(product_id) ON DELETE CASCADE,
        UNIQUE KEY (cart_id, product_id)
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS ORDERS (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'failed', 'out for delivery', 'ready to pick up', 'delivered') DEFAULT 'pending',
        shipping_address_id INT,
        payment_method VARCHAR(50),
        FOREIGN KEY (user_id) REFERENCES USERS(user_id),
        FOREIGN KEY (shipping_address_id) REFERENCES ADDRESSES(address_id)
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS ORDER_ITEMS (
        order_item_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES ORDERS(order_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES PRODUCTS(product_id)
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS ORDER_PAYMENTS (
        payment_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        gcash_number VARCHAR(20),
        card_number VARCHAR(20),
        card_expiry VARCHAR(7),
        is_successful BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (order_id) REFERENCES ORDERS(order_id)
    )
");

execute_query($conn, "
    CREATE TABLE IF NOT EXISTS FAVORITES (
        favorite_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES USERS(user_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES PRODUCTS(product_id) ON DELETE CASCADE,
        UNIQUE KEY (user_id, product_id)
    )
");

// Function to insert data safely
function insert_data($conn, $table, $data) {
    $columns = implode(", ", array_keys($data));
    $placeholders = implode(", ", array_fill(0, count($data), "?"));
    $types = str_repeat("s", count($data));
    $values = array_values($data);
    
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        echo "Inserted into $table successfully\n";
        return $conn->insert_id;
    } else {
        echo "Error inserting into $table: " . $stmt->error . "\n";
        return false;
    }
}

// Check if admin exists and create if not
$result = $conn->query("SELECT * FROM USERS WHERE username = 'admin'");
if ($result && $result->num_rows === 0) {
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $admin_id = insert_data($conn, 'USERS', [
        'username' => 'admin',
        'email' => 'admin@example.com',
        'password_hash' => $password_hash,
        'first_name' => 'System',
        'last_name' => 'Administrator',
        'contact_number' => '09123456789'
    ]);
    
    if ($admin_id) {
        insert_data($conn, 'ROLES', [
            'role' => 'admin',
            'user_id' => $admin_id
        ]);
    }
}

// Insert categories
$categories = ['Caps', 'Accessories', 'Jackets', 'Bags'];
foreach ($categories as $cat) {
    insert_data($conn, 'CATEGORIES', ['name' => $cat]);
}

// Insert products
$products = [
    ['MMSU Cap', 150.00, 'Caps', 'Green', 'Official MMSU baseball cap with embroidered logo. Adjustable strap for perfect fit.'],
    ['MMSU Headband', 74.00, 'Accessories', 'Green', 'Comfortable headband for all-day wear.'],
    ['MMSU Jacket W', 499.99, 'Jackets', 'White', 'Water-resistant MMSU jacket for women with logo embroidery.'],
    ['MMSU Tote Bag', 199.99, 'Bags', 'White', 'Spacious and durable tote bag with MMSU branding.'],
    ['MMSU Hoodie', 599.99, 'Jackets', 'Black', 'Warm and comfortable hoodie with MMSU print.'],
    ['MMSU Keychain', 49.99, 'Accessories', 'Silver', 'Durable metal keychain with MMSU logo.'],
    ['MMSU Lanyard', 89.99, 'Accessories', 'Blue', 'Nylon lanyard with MMSU branding.'],
    ['MMSU Umbrella', 349.99, 'Accessories', 'Red', 'Large automatic umbrella with MMSU logo.'],
];

foreach ($products as $p) {
    list($name, $price, $category, $color, $desc) = $p;
    
    // Get category ID
    $stmt = $conn->prepare("SELECT category_id FROM CATEGORIES WHERE name = ?");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $stmt->bind_result($category_id);
    $stmt->fetch();
    $stmt->close();
    
    // Insert product
    insert_data($conn, 'PRODUCTS', [
        'name' => $name,
        'price' => $price,
        'category_id' => $category_id,
        'color' => $color,
        'description' => $desc
    ]);
}

// Insert product images
$product_images = [
    'MMSU Cap' => ['/product-image/cap2.png', '/product-image/cap2_alt1.png'],
    'MMSU Headband' => ['/product-image/headband.png', '/product-image/headband_alt1.png'],
    'MMSU Jacket W' => ['/product-image/jacket.png', '/product-image/jacket_alt1.png', '/product-image/jacket_alt2.png'],
    'MMSU Tote Bag' => ['/product-image/tote.png', '/product-image/tote_alt1.png'],
    'MMSU Hoodie' => ['/product-image/hoodie.png'],
    'MMSU Keychain' => ['/product-image/keychain.png'],
    'MMSU Lanyard' => ['/product-image/lanyard.png', '/product-image/lanyard_alt1.png'],
    'MMSU Umbrella' => ['/product-image/umbrella.png'],
];

foreach ($product_images as $product_name => $images) {
    // Get product ID
    $stmt = $conn->prepare("SELECT product_id FROM PRODUCTS WHERE name = ?");
    $stmt->bind_param("s", $product_name);
    $stmt->execute();
    $stmt->bind_result($product_id);
    $stmt->fetch();
    $stmt->close();
    
    // Insert images
    foreach ($images as $image_url) {
        insert_data($conn, 'PRODUCT_IMAGES', [
            'product_id' => $product_id,
            'image_url' => $image_url
        ]);
    }
}

// Insert regular users
$users = [
    ['john_doe', 'john.doe@example.com', 'John', 'Doe', '09123456780'],
    ['jane_smith', 'jane.smith@example.com', 'Jane', 'Smith', '09123456781'],
    ['mike_jones', 'mike.jones@example.com', 'Mike', 'Jones', '09123456782'],
    ['sarah_williams', 'sarah.williams@example.com', 'Sarah', 'Williams', '09123456783'],
    ['david_brown', 'david.brown@example.com', 'David', 'Brown', '09123456784'],
];

foreach ($users as $user) {
    list($username, $email, $first_name, $last_name, $contact) = $user;
    $password_hash = password_hash('password123', PASSWORD_DEFAULT);
    
    $user_id = insert_data($conn, 'USERS', [
        'username' => $username,
        'email' => $email,
        'password_hash' => $password_hash,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'contact_number' => $contact
    ]);
    
    if ($user_id) {
        // Assign customer role
        insert_data($conn, 'ROLES', [
            'role' => 'customer',
            'user_id' => $user_id
        ]);
        
        // Add some addresses
        insert_data($conn, 'ADDRESSES', [
            'user_id' => $user_id,
            'country' => 'Philippines',
            'province' => 'Ilocos Norte',
            'municipality' => 'Batac',
            'barangay' => 'Caunayan',
            'zip_code' => '2906',
            'is_default' => 1
        ]);
        
        insert_data($conn, 'ADDRESSES', [
            'user_id' => $user_id,
            'country' => 'Philippines',
            'province' => 'Ilocos Norte',
            'municipality' => 'Laoag',
            'barangay' => 'Balatong',
            'zip_code' => '2900',
            'is_default' => 0
        ]);
        
        // Add payment methods
        insert_data($conn, 'USER_PAYMENTS', [
            'user_id' => $user_id,
            'type' => 'gcash',
            'gcash_phone' => $contact,
            'is_default' => 1
        ]);
        
        insert_data($conn, 'USER_PAYMENTS', [
            'user_id' => $user_id,
            'type' => 'card',
            'card_number' => '4111111111111111',
            'card_expiry' => '12/25',
            'is_default' => 0
        ]);
        
        // Add additional contact numbers
        insert_data($conn, 'USER_CONTACT_NUMBERS', [
            'user_id' => $user_id,
            'phone_number' => '09' . rand(100000000, 999999999),
            'is_default' => 0
        ]);
    }
}

// Insert a vendor user
$password_hash = password_hash('vendor123', PASSWORD_DEFAULT);
$vendor_id = insert_data($conn, 'USERS', [
    'username' => 'mmsu_vendor',
    'email' => 'vendor@mmsu.edu.ph',
    'password_hash' => $password_hash,
    'first_name' => 'MMSU',
    'last_name' => 'Vendor',
    'contact_number' => '09123456799'
]);

if ($vendor_id) {
    insert_data($conn, 'ROLES', [
        'role' => 'vendor',
        'user_id' => $vendor_id
    ]);
    
    insert_data($conn, 'ROLES', [
        'role' => 'customer',
        'user_id' => $vendor_id
    ]);
}

// Create carts for users
$user_ids = [];
$result = $conn->query("SELECT user_id FROM USERS");
while ($row = $result->fetch_assoc()) {
    $user_ids[] = $row['user_id'];
}

foreach ($user_ids as $user_id) {
    $cart_id = insert_data($conn, 'CARTS', ['user_id' => $user_id]);
    
    // Add some items to carts (random products)
    if ($cart_id) {
        $product_count = rand(1, 5);
        $product_ids = [];
        
        $result = $conn->query("SELECT product_id FROM PRODUCTS ORDER BY RAND() LIMIT $product_count");
        while ($row = $result->fetch_assoc()) {
            $product_ids[] = $row['product_id'];
        }
        
        foreach ($product_ids as $product_id) {
            insert_data($conn, 'CART_ITEMS', [
                'cart_id' => $cart_id,
                'product_id' => $product_id,
                'quantity' => rand(1, 3)
            ]);
        }
    }
}

// Create orders for some users
$order_users = array_slice($user_ids, 0, 3); // First 3 users will have orders

foreach ($order_users as $user_id) {
    // Get user's default address
    $stmt = $conn->prepare("SELECT address_id FROM ADDRESSES WHERE user_id = ? AND is_default = 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($address_id);
    $stmt->fetch();
    $stmt->close();
    
    // Create 2-3 orders per user
    for ($i = 0; $i < rand(2, 3); $i++) {
        $statuses = ['pending', 'out for delivery', 'delivered', 'ready to pick up'];
        $status = $statuses[array_rand($statuses)];
        $payment_methods = ['gcash', 'card'];
        $payment_method = $payment_methods[array_rand($payment_methods)];
        
        $order_id = insert_data($conn, 'ORDERS', [
            'user_id' => $user_id,
            'total_amount' => 0, // Will update after adding items
            'status' => $status,
            'shipping_address_id' => $address_id,
            'payment_method' => $payment_method
        ]);
        
        if ($order_id) {
            $total_amount = 0;
            $product_count = rand(1, 4);
            $product_ids = [];
            
            $result = $conn->query("SELECT product_id, price FROM PRODUCTS ORDER BY RAND() LIMIT $product_count");
            while ($row = $result->fetch_assoc()) {
                $product_ids[] = $row['product_id'];
                $quantity = rand(1, 2);
                $unit_price = $row['price'];
                $subtotal = $quantity * $unit_price;
                $total_amount += $subtotal;
                
                insert_data($conn, 'ORDER_ITEMS', [
                    'order_id' => $order_id,
                    'product_id' => $row['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unit_price
                ]);
            }
            
            // Update order total
            $conn->query("UPDATE ORDERS SET total_amount = $total_amount WHERE order_id = $order_id");
            
            // Add payment record
            if ($payment_method == 'gcash') {
                $stmt = $conn->prepare("SELECT gcash_phone FROM USER_PAYMENTS WHERE user_id = ? AND type = 'gcash' LIMIT 1");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->bind_result($gcash_number);
                $stmt->fetch();
                $stmt->close();
                
                insert_data($conn, 'ORDER_PAYMENTS', [
                    'order_id' => $order_id,
                    'payment_method' => 'gcash',
                    'gcash_number' => $gcash_number,
                    'is_successful' => $status != 'failed' ? 1 : 0
                ]);
            } else {
                $stmt = $conn->prepare("SELECT card_number, card_expiry FROM USER_PAYMENTS WHERE user_id = ? AND type = 'card' LIMIT 1");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->bind_result($card_number, $card_expiry);
                $stmt->fetch();
                $stmt->close();
                
                insert_data($conn, 'ORDER_PAYMENTS', [
                    'order_id' => $order_id,
                    'payment_method' => 'card',
                    'card_number' => $card_number,
                    'card_expiry' => $card_expiry,
                    'is_successful' => $status != 'failed' ? 1 : 0
                ]);
            }
        }
    }
}

// Add favorites for users
foreach ($user_ids as $user_id) {
    $favorite_count = rand(1, 5);
    $result = $conn->query("SELECT product_id FROM PRODUCTS ORDER BY RAND() LIMIT $favorite_count");
    
    while ($row = $result->fetch_assoc()) {
        insert_data($conn, 'FAVORITES', [
            'user_id' => $user_id,
            'product_id' => $row['product_id']
        ]);
    }
}

// Close connection
$conn->close();

echo "Database initialization complete with comprehensive mock data!\n";
?>
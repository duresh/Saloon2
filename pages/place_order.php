<?php
session_start();
require_once '../includes/dbcon.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to place an order']);
    exit;
}

// Get POST data
$shipping_address = $_POST['shipping_address'] ?? '';
$phone = $_POST['phone'] ?? '';
$payment_method = $_POST['payment_method'] ?? 'cash_on_delivery';
$notes = $_POST['notes'] ?? '';
$cart_data = $_POST['cart_data'] ?? '';
$total = floatval($_POST['total'] ?? 0);
$save_info = isset($_POST['save_info']) && $_POST['save_info'] == '1';

// Validate
if (empty($shipping_address)) {
    echo json_encode(['success' => false, 'message' => 'Shipping address is required']);
    exit;
}

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    exit;
}

if (empty($cart_data)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    exit;
}

// Decode cart data
$cart = json_decode($cart_data, true);
if (!is_array($cart) || empty($cart)) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart data']);
    exit;
}

try {
    $pdo = getPDOConnection();
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // If user wants to save info, update profile
    if ($save_info) {
        $user_id = $_SESSION['user_id'];
        
        // Check if user profile exists
        $stmt = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing profile
            $stmt = $pdo->prepare("
                UPDATE user_profiles 
                SET phone = ?, shipping_address = ? 
                WHERE user_id = ?
            ");
            $stmt->execute([$phone, $shipping_address, $user_id]);
        } else {
            // Insert new profile
            $stmt = $pdo->prepare("
                INSERT INTO user_profiles (user_id, phone, shipping_address) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user_id, $phone, $shipping_address]);
        }
        
        // Update reg table contact number
        $stmt = $pdo->prepare("UPDATE reg SET contactNo = ? WHERE regID = ?");
        $stmt->execute([$phone, $user_id]);
    }
    
    // Calculate totals
    $total_amount = 0;
    foreach ($cart as $item) {
        $total_amount += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
    }
    
    $discount_amount = 0;
    $tax_amount = $total_amount * 0.10;
    $grand_total = $total_amount + $tax_amount - $discount_amount;
    
    // Generate order number
    $order_number = 'SE-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Insert order
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            order_number, user_id, total_amount, discount_amount, 
            tax_amount, grand_total, payment_method, shipping_address, 
            phone, notes, status, payment_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid')
    ");
    
    $stmt->execute([
        $order_number,
        $_SESSION['user_id'],
        $total_amount,
        $discount_amount,
        $tax_amount,
        $grand_total,
        $payment_method,
        $shipping_address,
        $phone,
        $notes
    ]);
    
    $order_id = $pdo->lastInsertId();
    
    // Insert order items and update stock
    foreach ($cart as $item) {
        $product_id = $item['id'] ?? 0;
        
        if (!$product_id) {
            throw new Exception("Product ID missing");
        }
        
        // Check stock
        $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception("Product not found: " . ($item['name'] ?? 'Unknown'));
        }
        
        if ($product['stock_quantity'] < ($item['quantity'] ?? 0)) {
            throw new Exception("Insufficient stock for: " . ($item['name'] ?? 'Product'));
        }
        
        // Insert order item
        $stmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_name, quantity, price, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $subtotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
        $stmt->execute([
            $order_id,
            $product_id,
            $item['name'] ?? 'Unknown',
            $item['quantity'] ?? 1,
            $item['price'] ?? 0,
            $subtotal
        ]);
        
        // Update stock
        $stmt = $pdo->prepare("
            UPDATE products 
            SET stock_quantity = stock_quantity - ? 
            WHERE id = ?
        ");
        $stmt->execute([$item['quantity'] ?? 0, $product_id]);
    }
    
    // Log order status
    $stmt = $pdo->prepare("
        INSERT INTO order_status_logs (order_id, new_status, changed_by) 
        VALUES (?, 'pending', ?)
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order_number' => $order_number,
        'message' => 'Order placed successfully'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Order placement error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
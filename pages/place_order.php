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
$cart_data = json_decode($_POST['cart_data'] ?? '[]', true);
$total = floatval($_POST['total'] ?? 0);

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

try {
    $pdo = getPDOConnection();
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Calculate totals
    $total_amount = 0;
    foreach ($cart_data as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }
    
    $discount_amount = 0;
    $tax_amount = $total_amount * 0.10; // 10% tax
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
    foreach ($cart_data as $item) {
        // Check stock
        $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stmt->execute([$item['id']]);
        $product = $stmt->fetch();
        
        if (!$product) {
            throw new Exception("Product not found: " . $item['name']);
        }
        
        if ($product['stock_quantity'] < $item['quantity']) {
            throw new Exception("Insufficient stock for: " . $item['name']);
        }
        
        // Insert order item
        $stmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_name, quantity, price, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $subtotal = $item['price'] * $item['quantity'];
        $stmt->execute([
            $order_id,
            $item['id'],
            $item['name'],
            $item['quantity'],
            $item['price'],
            $subtotal
        ]);
        
        // Update stock
        $stmt = $pdo->prepare("
            UPDATE products 
            SET stock_quantity = stock_quantity - ? 
            WHERE id = ?
        ");
        $stmt->execute([$item['quantity'], $item['id']]);
        
        // Log stock movement
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements 
            (product_id, quantity, movement_type, reference_type, reference_id, notes, created_by) 
            VALUES (?, ?, 'subtraction', 'order', ?, ?, ?)
        ");
        $stmt->execute([
            $item['id'],
            -$item['quantity'],
            $order_id,
            "Order #$order_number",
            $_SESSION['user_id']
        ]);
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
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/dbcon.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'No items in order']);
    exit;
}

try {
    $pdo = getPDOConnection();
    $pdo->beginTransaction();
    
    $userId = isset($input['user_id']) && $input['user_id'] ? $input['user_id'] : null;
    $totalAmount = floatval($input['total_amount']);
    $items = $input['items'];
    
    // Generate unique order number
    $orderNumber = 'SE-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Insert order
    $stmt = $pdo->prepare("
        INSERT INTO orders (order_number, user_id, total_amount, grand_total, status, payment_method, payment_status, ordered_at)
        VALUES (?, ?, ?, ?, 'pending', 'cash_on_delivery', 'unpaid', NOW())
    ");
    $stmt->execute([$orderNumber, $userId, $totalAmount, $totalAmount]);
    $orderId = $pdo->lastInsertId();
    
    // Insert order items
    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, quantity, price, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($items as $item) {
        $subtotal = $item['price'] * $item['quantity'];
        $productId = isset($item['id']) ? $item['id'] : null;
        $itemStmt->execute([$orderId, $productId, $item['name'], $item['quantity'], $item['price'], $subtotal]);
        
        // Update stock quantity
        if ($productId) {
            $updateStock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?");
            $updateStock->execute([$item['quantity'], $productId, $item['quantity']]);
        }
    }
    
    // If user is logged in, clear their cart
    if ($userId) {
        $clearCart = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $clearCart->execute([$userId]);
    }
    
    // Log order status
    $logStmt = $pdo->prepare("
        INSERT INTO order_status_logs (order_id, old_status, new_status, changed_by)
        VALUES (?, NULL, 'pending', ?)
    ");
    $logStmt->execute([$orderId, $userId]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'message' => 'Order placed successfully'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Order processing error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to process order: ' . $e->getMessage()]);
}
?>
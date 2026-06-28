<?php
session_start();
require_once '../../includes/dbcon.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit;
}

$order_id = intval($_POST['order_id'] ?? 0);
$reason = $_POST['reason'] ?? 'Cancelled by customer';

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    $pdo = getPDOConnection();
    
    // Verify order belongs to user and is pending
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    if ($order['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Order cannot be cancelled at this stage']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$order_id]);
    
    // Log status change
    $stmt = $pdo->prepare("
        INSERT INTO order_status_logs (order_id, old_status, new_status, changed_by, reason) 
        VALUES (?, 'pending', 'cancelled', ?, ?)
    ");
    $stmt->execute([$order_id, $_SESSION['user_id'], $reason]);
    
    // Restore stock
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    foreach ($items as $item) {
        if ($item['product_id']) {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity + ? 
                WHERE id = ?
            ");
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
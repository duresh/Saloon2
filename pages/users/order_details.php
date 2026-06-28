<?php
session_start();
require_once '../../includes/dbcon.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$order_id = intval($_GET['id'] ?? 0);

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    $pdo = getPDOConnection();
    
    // Verify order belongs to user
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Get order items
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    // Get status logs
    $stmt = $pdo->prepare("
        SELECT * FROM order_status_logs 
        WHERE order_id = ? 
        ORDER BY changed_at ASC
    ");
    $stmt->execute([$order_id]);
    $logs = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => array_merge($order, [
            'items' => $items,
            'logs' => $logs
        ])
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
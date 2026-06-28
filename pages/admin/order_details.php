<?php
session_start();
require_once '../../includes/dbcon.php';

header('Content-Type: application/json');

// Check if user is staff/admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    $pdo = getPDOConnection();
    
    // Get order with customer info
    $stmt = $pdo->prepare("
        SELECT o.*, r.fName as customer_name, r.email, r.contactNo 
        FROM orders o 
        JOIN reg r ON o.user_id = r.regID 
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Get order items
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get status logs
    $stmt = $pdo->prepare("
        SELECT * FROM order_status_logs 
        WHERE order_id = ? 
        ORDER BY changed_at ASC
    ");
    $stmt->execute([$order_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
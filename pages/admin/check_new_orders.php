<?php
session_start();
require_once '../../includes/dbcon.php';

header('Content-Type: application/json');

// Check if user is staff/admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$last_check = isset($_GET['last_check']) ? intval($_GET['last_check']) : 0;

try {
    $pdo = getPDOConnection();
    
    // Get new orders since last check
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.ordered_at, r.fName as customer_name
        FROM orders o
        JOIN reg r ON o.user_id = r.regID
        WHERE o.status = 'pending' 
        AND UNIX_TIMESTAMP(o.ordered_at) > ?
        ORDER BY o.ordered_at DESC
        LIMIT 5
    ");
    $stmt->execute([$last_check]);
    $new_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'new_orders' => $new_orders,
        'timestamp' => time(),
        'count' => count($new_orders)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
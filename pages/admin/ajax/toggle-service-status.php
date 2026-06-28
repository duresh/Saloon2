<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$service_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

if (!$service_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit();
}

if (!in_array($status, ['active', 'inactive'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Check if service exists
    $check_query = "SELECT id, name FROM services WHERE id = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$service_id]);
    $service = $check_stmt->fetch();
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        exit();
    }
    
    // Update service status
    $query = "UPDATE services SET status = ? WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([$status, $service_id]);
    
    if ($result) {
        // Log the action
        $admin_id = $_SESSION['user_id'];
        $log_query = "INSERT INTO admin_action_logs (admin_id, action, details, performed_at) VALUES (?, 'toggle_service_status', ?, NOW())";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([$admin_id, "Changed service '{$service['name']}' status to $status"]);
        
        echo json_encode([
            'success' => true,
            'message' => "Service {$status}ed successfully"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update service status']);
    }
    
} catch (PDOException $e) {
    error_log('Toggle service status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
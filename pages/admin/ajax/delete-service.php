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

if (!$service_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
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
    
    // Check if service has any appointments
    $appointment_check = "SELECT COUNT(*) as count FROM appointments WHERE service_id = ?";
    $appointment_stmt = $pdo->prepare($appointment_check);
    $appointment_stmt->execute([$service_id]);
    $appointment_count = $appointment_stmt->fetch()['count'];
    
    if ($appointment_count > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete this service. It has ' . $appointment_count . ' appointment(s) associated.']);
        exit();
    }
    
    // Delete the service
    $query = "DELETE FROM services WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([$service_id]);
    
    if ($result) {
        // Log the action
        $admin_id = $_SESSION['user_id'];
        $log_query = "INSERT INTO admin_action_logs (admin_id, action, details, performed_at) VALUES (?, 'delete_service', ?, NOW())";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([$admin_id, "Deleted service: {$service['name']} (ID: $service_id)"]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Service deleted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete service']);
    }
    
} catch (PDOException $e) {
    error_log('Delete service error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
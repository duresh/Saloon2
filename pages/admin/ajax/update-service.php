<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
$duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
$category = isset($_POST['category']) ? trim($_POST['category']) : '';

// Validate inputs
if (!$service_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit();
}

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Service name is required']);
    exit();
}

if ($price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid price is required']);
    exit();
}

if ($duration <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid duration is required']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Check if service exists
    $check_query = "SELECT id, name FROM services WHERE id = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$service_id]);
    $old_service = $check_stmt->fetch();
    
    if (!$old_service) {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        exit();
    }
    
    // Check if another service has the same name
    $name_check = "SELECT id FROM services WHERE name = ? AND id != ?";
    $name_stmt = $pdo->prepare($name_check);
    $name_stmt->execute([$name, $service_id]);
    
    if ($name_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Another service with this name already exists']);
        exit();
    }
    
    // Update service
    $query = "
        UPDATE services 
        SET name = ?, description = ?, price = ?, duration = ?, category = ?
        WHERE id = ?
    ";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([$name, $description, $price, $duration, $category, $service_id]);
    
    if ($result) {
        // Log the action
        $admin_id = $_SESSION['user_id'];
        $log_query = "INSERT INTO admin_action_logs (admin_id, action, details, performed_at) VALUES (?, 'update_service', ?, NOW())";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([$admin_id, "Updated service: $name (ID: $service_id)"]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Service updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update service']);
    }
    
} catch (PDOException $e) {
    error_log('Update service error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
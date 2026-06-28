<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
$duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
$category = isset($_POST['category']) ? trim($_POST['category']) : '';

// Validate inputs
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
    
    // Check if service name already exists
    $check_query = "SELECT id FROM services WHERE name = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$name]);
    
    if ($check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'A service with this name already exists']);
        exit();
    }
    
    // Insert new service
    $query = "
        INSERT INTO services (name, description, price, duration, category, status) 
        VALUES (?, ?, ?, ?, ?, 'active')
    ";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([$name, $description, $price, $duration, $category]);
    
    if ($result) {
        $service_id = $pdo->lastInsertId();
        
        // Log the action
        $admin_id = $_SESSION['user_id'];
        $log_query = "INSERT INTO admin_action_logs (admin_id, action, details, performed_at) VALUES (?, 'add_service', ?, NOW())";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([$admin_id, "Added service: $name"]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Service added successfully',
            'service_id' => $service_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add service']);
    }
    
} catch (PDOException $e) {
    error_log('Save service error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
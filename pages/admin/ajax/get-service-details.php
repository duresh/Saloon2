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
    
    // Get service details
    $query = "
        SELECT 
            s.id,
            s.name,
            s.description,
            s.price,
            s.duration,
            s.category,
            s.status,
            COALESCE((SELECT COUNT(*) FROM appointments WHERE service_id = s.id), 0) as total_bookings,
            COALESCE((SELECT COUNT(*) FROM appointments WHERE service_id = s.id AND status = 'completed'), 0) as completed_bookings,
            COALESCE((SELECT SUM(s2.price) FROM appointments a JOIN services s2 ON a.service_id = s2.id WHERE a.service_id = s.id AND a.status = 'completed'), 0) as total_revenue
        FROM services s
        WHERE s.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();
    
    if ($service) {
        echo json_encode([
            'success' => true,
            'service' => $service
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
    }
    
} catch (PDOException $e) {
    error_log('Get service details error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
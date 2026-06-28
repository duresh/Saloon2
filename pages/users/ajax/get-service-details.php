<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
    
    $query = "SELECT * FROM services WHERE id = ? AND status = 'active'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();
    
    if ($service) {
        echo json_encode(['success' => true, 'service' => $service]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
    }
    
} catch (PDOException $e) {
    error_log('Get service details error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
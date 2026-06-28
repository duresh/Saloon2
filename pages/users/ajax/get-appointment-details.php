<?php
header('Content-Type: application/json');
error_reporting(0); // Temporarily disable error reporting
ob_clean(); // Clear any previous output
session_start();
require_once '../../../includes/dbcon.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$appointment_id = $_POST['id'] ?? 0;
$user_id = $_SESSION['user_id'];

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    $query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.price as service_price,
            s.duration as service_duration,
            s.category as service_category,
            u.fName as customer_name,
            u.email as customer_email,
            u.contactNo as customer_phone
        FROM appointments a
        INNER JOIN services s ON a.service_id = s.id
        INNER JOIN reg u ON a.user_id = u.regID
        WHERE a.id = ? AND a.user_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment_id, $user_id]);
    $appointment = $stmt->fetch();
    
    if ($appointment) {
        echo json_encode(['success' => true, 'appointment' => $appointment]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    }
    
} catch (PDOException $e) {
    error_log('Get appointment details error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
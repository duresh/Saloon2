<?php
ob_start();
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../../../includes/dbcon.php';

if (!isset($_POST['id'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    $appointment_id = $_POST['id'];
    $user_id = $_SESSION['user_id'];
    
    $query = "SELECT 
        a.*,
        s.name as service_name,
        s.price,
        s.duration,
        s.description as service_description
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.id = ? AND a.user_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment_id, $user_id]);
    $appointment = $stmt->fetch();
    
    if ($appointment) {
        $response = [
            'service_name' => $appointment['service_name'] ?? 'N/A',
            'date' => date('F d, Y', strtotime($appointment['appointment_date'])),
            'time' => date('h:i A', strtotime($appointment['appointment_time'])),
            'price' => number_format($appointment['price'] ?? 0, 2),
            'duration' => $appointment['duration'] ?? 0,
            'status' => $appointment['status'],
            'notes' => $appointment['notes'] ?? 'No notes'
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode(['error' => 'Appointment not found']);
    }
} catch (PDOException $e) {
    error_log('Get appointment error: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>
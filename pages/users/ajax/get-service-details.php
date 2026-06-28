<?php
session_start();
require_once '../../../includes/dbcon.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit;
}

$appointment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID required']);
    exit;
}

try {
    $pdo = getPDOConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get appointment details with joins
    $query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.price as service_price,
            s.duration as service_duration,
            s.category as service_category,
            st.id as staff_id,
            r.fName as staff_name,
            st.specialization as staff_specialization,
            u.fName as customer_name,
            u.email as customer_email,
            u.contactNo as customer_phone
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN reg r ON st.user_id = r.regID
        INNER JOIN reg u ON a.user_id = u.regID
        WHERE a.id = ? AND a.user_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment_id, $user_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'appointment' => $appointment
    ]);
    
} catch (Exception $e) {
    error_log('Get appointment details error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading appointment details'
    ]);
}
?>
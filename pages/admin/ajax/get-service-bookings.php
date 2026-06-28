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

if (!$service_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Check if service exists
    $service_check = "SELECT id, name FROM services WHERE id = ?";
    $service_stmt = $pdo->prepare($service_check);
    $service_stmt->execute([$service_id]);
    $service = $service_stmt->fetch();
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        exit();
    }
    
    // Get all bookings for this service
    $query = "
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.status as appointment_status,
            a.notes,
            u.fName as customer_name,
            u.email as customer_email,
            u.contactNo as customer_phone,
            s.price,
            st.id as staff_id,
            r.fName as staff_name,
            st.specialization as staff_specialization
        FROM appointments a
        JOIN reg u ON a.user_id = u.regID
        JOIN services s ON a.service_id = s.id
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN reg r ON st.user_id = r.regID
        WHERE a.service_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$service_id]);
    $bookings = $stmt->fetchAll();
    
    // Rename appointment_status to status for frontend compatibility
    foreach ($bookings as &$booking) {
        $booking['status'] = $booking['appointment_status'];
        unset($booking['appointment_status']);
    }
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN a.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
            COALESCE(SUM(s.price), 0) as total_revenue
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.service_id = ?
    ";
    
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$service_id]);
    $stats = $stats_stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'bookings' => $bookings,
        'statistics' => $stats,
        'service_name' => $service['name']
    ]);
    
} catch (PDOException $e) {
    error_log('Get service bookings error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
<?php
session_start();
header('Content-Type: application/json');

require_once '../../../includes/dbcon.php';
require_once '../../../includes/availability_helper.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Get appointment details
    $appointment_query = "
        SELECT a.*, s.name as service_name, s.category, s.duration, 
               s.price, u.fName as customer_name
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg u ON a.user_id = u.regID
        WHERE a.id = ?
    ";
    $appointment_stmt = $pdo->prepare($appointment_query);
    $appointment_stmt->execute([$appointment_id]);
    $appointment = $appointment_stmt->fetch();
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }
    
    // Get available staff for this specific time slot
    $available_staff = getAvailableStaffForSlot(
        $pdo, 
        $appointment['appointment_date'], 
        $appointment['appointment_time'], 
        $appointment['duration'],
        $appointment_id
    );
    
    // Format staff data
    $formatted_staff = [];
    foreach ($available_staff as $staff) {
        $formatted_staff[] = [
            'id' => $staff['id'],
            'name' => $staff['staff_name'],
            'specialization' => $staff['specialization'] ?? 'General',
            'experience' => $staff['experience_years'] ?? 0,
            'email' => $staff['email'],
            'today_appointments' => $staff['total_appointments_today'] ?? 0
        ];
    }
    
    // Check if time slot is at capacity
    $slot_query = "
        SELECT COUNT(*) as count
        FROM appointments
        WHERE appointment_date = ?
        AND appointment_time = ?
        AND status IN ('pending', 'confirmed')
    ";
    $slot_stmt = $pdo->prepare($slot_query);
    $slot_stmt->execute([$appointment['appointment_date'], $appointment['appointment_time']]);
    $slot_count = $slot_stmt->fetch()['count'];
    $is_at_capacity = $slot_count >= 10;
    
    echo json_encode([
        'success' => true,
        'appointment' => [
            'id' => $appointment['id'],
            'service_name' => $appointment['service_name'],
            'category' => $appointment['category'],
            'date' => $appointment['appointment_date'],
            'time' => $appointment['appointment_time'],
            'duration' => $appointment['duration'],
            'customer_name' => $appointment['customer_name'],
            'slot_capacity' => $slot_count,
            'is_at_capacity' => $is_at_capacity
        ],
        'available_staff' => $formatted_staff,
        'total_available' => count($formatted_staff)
    ]);
    
} catch (PDOException $e) {
    error_log('Get available staff error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
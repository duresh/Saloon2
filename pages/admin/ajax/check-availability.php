<?php
session_start();
header('Content-Type: application/json');

require_once '../../../includes/dbcon.php';
require_once '../../../includes/availability_helper.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$date = $_POST['date'] ?? '';
$exclude_appointment = isset($_POST['exclude_appointment']) ? intval($_POST['exclude_appointment']) : 0;
$service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;

if (!$date) {
    echo json_encode(['success' => false, 'message' => 'Date required']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Get service duration if provided
    $duration = 30; // Default duration
    if ($service_id > 0) {
        $service_query = "SELECT duration FROM services WHERE id = ?";
        $service_stmt = $pdo->prepare($service_query);
        $service_stmt->execute([$service_id]);
        $service = $service_stmt->fetch();
        if ($service) {
            $duration = $service['duration'];
        }
    }
    
    // Get available time slots
    $time_slots = getAvailableTimeSlots($pdo, $date, 10);
    
    // Get booked slots for this date (all appointments, not just staff-specific)
    $booked_query = "
        SELECT appointment_time, COUNT(*) as count
        FROM appointments 
        WHERE appointment_date = ? 
        AND id != ?
        AND status IN ('pending', 'confirmed')
        GROUP BY appointment_time
    ";
    $booked_stmt = $pdo->prepare($booked_query);
    $booked_stmt->execute([$date, $exclude_appointment]);
    $booked_slots = [];
    while ($row = $booked_stmt->fetch()) {
        $booked_slots[$date . ' ' . $row['appointment_time']] = [
            'booked' => true,
            'count' => $row['count'],
            'max_capacity' => 10
        ];
    }
    
    // Get available staff for each time slot
    $available_staff_by_slot = [];
    foreach ($time_slots as $slot) {
        if ($slot['available']) {
            $available_staff = getAvailableStaffForSlot($pdo, $date, $slot['time'], $duration, $exclude_appointment);
            $available_staff_by_slot[$slot['time']] = $available_staff;
        }
    }
    
    echo json_encode([
        'success' => true,
        'time_slots' => $time_slots,
        'booked_slots' => $booked_slots,
        'available_staff_by_slot' => $available_staff_by_slot,
        'date' => $date
    ]);
    
} catch (PDOException $e) {
    error_log('Check availability error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
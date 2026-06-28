<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Get appointment details with service duration
    $appointment_query = "
        SELECT a.*, s.name as service_name, s.category, s.duration as service_duration
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.id = ?
    ";
    $appointment_stmt = $pdo->prepare($appointment_query);
    $appointment_stmt->execute([$appointment_id]);
    $appointment = $appointment_stmt->fetch();
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }
    
    // Get all staff
    $all_staff_query = "
        SELECT s.*, r.fName as staff_name
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE r.cStatus = 1
    ";
    $all_staff_stmt = $pdo->query($all_staff_query);
    $all_staff = $all_staff_stmt->fetchAll();
    
    // Find available staff
    $available_staff = [];
    $available_ids = [];
    
    foreach ($all_staff as $staff) {
        if (isStaffAvailable($pdo, $staff['id'], $appointment['appointment_date'], $appointment['appointment_time'], $appointment['service_duration'])) {
            $available_staff[] = $staff;
            $available_ids[] = $staff['id'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'available_staff' => $available_staff,
        'all_staff' => $all_staff,
        'available_ids' => $available_ids,
        'appointment' => [
            'service_name' => $appointment['service_name'],
            'appointment_date' => $appointment['appointment_date'],
            'appointment_time' => $appointment['appointment_time']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log('Get available staff error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function isStaffAvailable($pdo, $staff_id, $date, $time, $duration) {
    $start_time = strtotime($time);
    $end_time = $start_time + ($duration * 60);
    $end_time_str = date('H:i:s', $end_time);
    
    $conflict_query = "
        SELECT COUNT(*) as conflict_count
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.staff_id = ?
        AND a.appointment_date = ?
        AND a.status IN ('confirmed', 'pending')
        AND (
            (a.appointment_time <= ? AND ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) > ?)
            OR
            (? < ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) AND ? >= a.appointment_time)
        )
    ";
    
    $conflict_stmt = $pdo->prepare($conflict_query);
    $conflict_stmt->execute([
        $staff_id,
        $date,
        $time,
        $time,
        $end_time_str,
        $end_time_str
    ]);
    
    $result = $conflict_stmt->fetch();
    return $result['conflict_count'] == 0;
}
?>
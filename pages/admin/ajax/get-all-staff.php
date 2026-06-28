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

try {
    $pdo = getPDOConnection();
    
    // Get all staff
    $staff_query = "
        SELECT s.*, r.fName as staff_name
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE r.cStatus = 1
        ORDER BY s.specialization, r.fName
    ";
    $staff_stmt = $pdo->query($staff_query);
    $all_staff = $staff_stmt->fetchAll();
    
    // If appointment ID provided, check availability
    $available_ids = [];
    if ($appointment_id) {
        $appointment_query = "SELECT appointment_date, appointment_time, duration FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.id = ?";
        $appointment_stmt = $pdo->prepare($appointment_query);
        $appointment_stmt->execute([$appointment_id]);
        $appointment = $appointment_stmt->fetch();
        
        if ($appointment) {
            foreach ($all_staff as $staff) {
                if (isStaffAvailable($pdo, $staff['id'], $appointment['appointment_date'], $appointment['appointment_time'], $appointment['duration'])) {
                    $available_ids[] = $staff['id'];
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'all_staff' => $all_staff,
        'available_ids' => $available_ids
    ]);
    
} catch (PDOException $e) {
    error_log('Get all staff error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

function isStaffAvailable($pdo, $staff_id, $date, $time, $duration) {
    $start_time = strtotime($time);
    $end_time = $start_time + ($duration * 60);
    $end_time_str = date('H:i:s', $end_time);
    
    $conflict_query = "
        SELECT COUNT(*) as conflict_count
        FROM appointments
        WHERE staff_id = ?
        AND appointment_date = ?
        AND status IN ('confirmed', 'pending')
        AND (
            (appointment_time <= ? AND ADDTIME(appointment_time, SEC_TO_TIME(? * 60)) > ?)
            OR
            (? < ADDTIME(appointment_time, SEC_TO_TIME(? * 60)) AND ? >= appointment_time)
        )
    ";
    
    $conflict_stmt = $pdo->prepare($conflict_query);
    $conflict_stmt->execute([
        $staff_id,
        $date,
        $time,
        $duration,
        $time,
        $end_time_str,
        $duration,
        $end_time_str
    ]);
    
    $result = $conflict_stmt->fetch();
    return $result['conflict_count'] == 0;
}
?>
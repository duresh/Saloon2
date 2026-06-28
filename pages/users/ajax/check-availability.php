<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../includes/dbcon.php';

$date = $_POST['date'] ?? '';
$exclude_appointment = isset($_POST['exclude_appointment']) ? intval($_POST['exclude_appointment']) : 0;
$service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : $_SESSION['user_id'];

if (!$date) {
    echo json_encode(['success' => false, 'message' => 'Date required']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Get user's existing appointments for THIS SPECIFIC DATE
    $user_appointments_query = "
        SELECT appointment_time 
        FROM appointments 
        WHERE user_id = ? 
        AND appointment_date = ? 
        AND status IN ('pending', 'confirmed')
        AND id != ?
    ";
    $user_appointments_stmt = $pdo->prepare($user_appointments_query);
    $user_appointments_stmt->execute([$user_id, $date, $exclude_appointment]);
    $user_appointments = $user_appointments_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Create a map of user's booked times for this date
    $user_booked_times = [];
    foreach ($user_appointments as $time) {
        $user_booked_times[$time] = true;
    }
    
    // Get user's appointments for sidebar display
    $all_user_query = "
        SELECT a.appointment_date, a.appointment_time, s.name as service_name
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.user_id = ? 
        AND a.appointment_date >= CURDATE() 
        AND a.status IN ('pending', 'confirmed')
        ORDER BY a.appointment_date, a.appointment_time
        LIMIT 10
    ";
    $all_user_stmt = $pdo->prepare($all_user_query);
    $all_user_stmt->execute([$user_id]);
    $all_user_appointments = $all_user_stmt->fetchAll();
    
    // Generate time slots with availability status
    $time_slots = [];
    $start_time = strtotime('09:00');
    $end_time = strtotime('20:00');
    $interval = 30 * 60;
    
    for ($time = $start_time; $time <= $end_time; $time += $interval) {
        $time_str = date('H:i', $time);
        $is_user_booked = isset($user_booked_times[$time_str]);
        
        $time_slots[] = [
            'time' => $time_str,
            'available' => !$is_user_booked,
            'user_booked' => $is_user_booked,
            'reason' => $is_user_booked ? 'You already have an appointment at this time' : 'Available'
        ];
    }
    
    // Get booked slots for this date (all users)
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
        $booked_slots[$date . '|' . $row['appointment_time']] = [
            'booked' => true,
            'count' => $row['count'],
            'max_capacity' => 10,
            'remaining' => 10 - $row['count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'time_slots' => $time_slots,
        'booked_slots' => $booked_slots,
        'user_booked_times' => $user_booked_times,
        'user_appointments' => $all_user_appointments,
        'date' => $date
    ]);
    
} catch (PDOException $e) {
    error_log('Check availability error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
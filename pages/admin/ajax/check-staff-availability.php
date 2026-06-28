<?php
session_start();
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/Saloon/includes/dbcon.php';

$staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
$date = isset($_POST['date']) ? $_POST['date'] : '';
$time = isset($_POST['time']) ? $_POST['time'] : '';
$service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
$exclude_appointment = isset($_POST['exclude_appointment']) ? intval($_POST['exclude_appointment']) : 0;

if (!$staff_id || !$date || !$time) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Get service duration
    $duration = 30;
    if ($service_id) {
        $stmt = $pdo->prepare("SELECT duration FROM services WHERE id = ?");
        $stmt->execute([$service_id]);
        $row = $stmt->fetch();
        if ($row) {
            $duration = intval($row['duration']);
        }
    }
    
    // Calculate end time
    $start_timestamp = strtotime($time);
    $end_timestamp = $start_timestamp + ($duration * 60);
    $end_time = date('H:i:s', $end_timestamp);
    
    // Check for overlapping appointments
    $query = "
        SELECT COUNT(*) as conflict_count
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.staff_id = ?
        AND a.appointment_date = ?
        AND a.id != ?
        AND a.status IN ('confirmed', 'pending')
        AND (
            (a.appointment_time < ? AND ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) > ?)
            OR
            (? < ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) AND ? > a.appointment_time)
            OR
            (a.appointment_time = ?)
        )
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $staff_id,
        $date,
        $exclude_appointment,
        $end_time,
        $time,
        $end_time,
        $time,
        $time
    ]);
    
    $result = $stmt->fetch();
    $has_conflict = ($result['conflict_count'] > 0);
    
    // Get staff name
    $staff_name = '';
    $name_query = "SELECT r.fName FROM staff s JOIN reg r ON s.user_id = r.regID WHERE s.id = ?";
    $name_stmt = $pdo->prepare($name_query);
    $name_stmt->execute([$staff_id]);
    $staff_row = $name_stmt->fetch();
    if ($staff_row) {
        $staff_name = $staff_row['fName'];
    }
    
    echo json_encode([
        'success' => true,
        'available' => !$has_conflict,
        'message' => !$has_conflict 
            ? "$staff_name is available at this time" 
            : "$staff_name has a conflicting appointment at this time"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'available' => true, // Default to true to allow saving if check fails
        'message' => 'Note: Could not verify availability. You can still save.'
    ]);
}
?>
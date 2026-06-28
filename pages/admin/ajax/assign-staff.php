<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once '../../../includes/dbcon.php';

// Get POST data
$appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
$staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
$auto_assign = isset($_POST['auto_assign']) ? filter_var($_POST['auto_assign'], FILTER_VALIDATE_BOOLEAN) : false;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // First, check if staff_id column exists in appointments table
    $check_column = "SHOW COLUMNS FROM appointments LIKE 'staff_id'";
    $column_stmt = $pdo->query($check_column);
    if ($column_stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Database error: staff_id column missing. Please run database update.']);
        exit();
    }
    
    // Get appointment details with service information including duration
    $appointment_query = "
        SELECT a.*, s.name as service_name, s.category, s.duration as service_duration,
               u.fName as customer_name
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
    
    // If auto_assign is true, find available staff
    if ($auto_assign) {
        $available_staff = findAvailableStaff($pdo, $appointment);
        
        if (empty($available_staff)) {
            echo json_encode([
                'success' => false, 
                'message' => 'No staff members available at this time',
                'require_manual' => true
            ]);
            exit();
        }
        
        // Assign the first available staff member
        $staff_id = $available_staff[0]['id'];
    }
    
    // Validate staff_id if provided
    if (!$auto_assign && $staff_id > 0) {
        $staff_check = "SELECT s.*, r.fName as staff_name FROM staff s JOIN reg r ON s.user_id = r.regID WHERE s.id = ?";
        $staff_stmt = $pdo->prepare($staff_check);
        $staff_stmt->execute([$staff_id]);
        $staff = $staff_stmt->fetch();
        
        if (!$staff) {
            echo json_encode(['success' => false, 'message' => 'Invalid staff member selected']);
            exit();
        }
        
        // Check if staff is available at this time
        $is_available = checkStaffAvailability($pdo, $staff_id, $appointment['appointment_date'], $appointment['appointment_time'], $appointment['service_duration']);
        
        if (!$is_available) {
            echo json_encode([
                'success' => false, 
                'message' => 'Selected staff member is not available at this time',
                'require_manual' => true
            ]);
            exit();
        }
    }
    
    // Update appointment with staff assignment and status
    if ($staff_id > 0) {
        $update_query = "UPDATE appointments SET staff_id = ?, status = 'confirmed', modified_at = NOW() WHERE id = ?";
        $update_stmt = $pdo->prepare($update_query);
        $update_result = $update_stmt->execute([$staff_id, $appointment_id]);
        
        if ($update_result) {
            // Get staff details for response
            $staff_query = "SELECT s.*, r.fName as staff_name FROM staff s JOIN reg r ON s.user_id = r.regID WHERE s.id = ?";
            $staff_stmt = $pdo->prepare($staff_query);
            $staff_stmt->execute([$staff_id]);
            $staff = $staff_stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'message' => 'Staff assigned and appointment confirmed successfully',
                'staff' => [
                    'id' => $staff['id'],
                    'name' => $staff['staff_name'],
                    'specialization' => $staff['specialization']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to assign staff']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No staff member selected']);
    }
    
} catch (PDOException $e) {
    error_log('Assign staff error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Find available staff members for the appointment
 */
function findAvailableStaff($pdo, $appointment) {
    $date = $appointment['appointment_date'];
    $time = $appointment['appointment_time'];
    $duration = $appointment['service_duration'];
    $category = $appointment['category'];
    
    // Calculate end time
    $start_time = strtotime($time);
    $end_time = $start_time + ($duration * 60);
    $end_time_str = date('H:i:s', $end_time);
    
    // Get all active staff
    $staff_query = "
        SELECT s.*, r.fName as staff_name 
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE r.cStatus = 1
    ";
    
    $staff_params = [];
    
    // If category is specified, try to match with specialization
    if ($category && $category != 'General') {
        $staff_query .= " AND (s.specialization = ? OR s.specialization IS NULL)";
        $staff_params[] = $category;
    }
    
    $staff_stmt = $pdo->prepare($staff_query);
    $staff_stmt->execute($staff_params);
    $all_staff = $staff_stmt->fetchAll();
    
    $available_staff = [];
    
    foreach ($all_staff as $staff) {
        // Check if staff has any conflicting appointments
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
            $staff['id'],
            $date,
            $time,
            $time,
            $end_time_str,
            $end_time_str
        ]);
        
        $conflict = $conflict_stmt->fetch();
        
        if ($conflict['conflict_count'] == 0) {
            $available_staff[] = $staff;
        }
    }
    
    return $available_staff;
}

/**
 * Check if a specific staff member is available
 */
function checkStaffAvailability($pdo, $staff_id, $date, $time, $duration) {
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
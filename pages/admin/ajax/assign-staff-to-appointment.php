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
$staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : 'assign';

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Begin transaction
    $pdo->beginTransaction();
    
    if ($action === 'assign' && $staff_id > 0) {
        // Check if staff exists
        $staff_check = "SELECT s.*, r.fName as staff_name FROM staff s JOIN reg r ON s.user_id = r.regID WHERE s.id = ?";
        $staff_stmt = $pdo->prepare($staff_check);
        $staff_stmt->execute([$staff_id]);
        $staff = $staff_stmt->fetch();
        
        if (!$staff) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Staff member not found']);
            exit();
        }
        
        // Get appointment details for availability check
        $apt_query = "SELECT a.*, s.duration FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.id = ?";
        $apt_stmt = $pdo->prepare($apt_query);
        $apt_stmt->execute([$appointment_id]);
        $appointment = $apt_stmt->fetch();
        
        if (!$appointment) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            exit();
        }
        
        // Check staff availability
        if (!isStaffAvailable($pdo, $staff_id, $appointment['appointment_date'], 
                              $appointment['appointment_time'], $appointment['duration'], $appointment_id)) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'Selected staff member is not available at this time',
                'show_available' => true
            ]);
            exit();
        }
        
        // Update appointment with staff
        $update_query = "UPDATE appointments SET staff_id = ?, modified_at = NOW() WHERE id = ?";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([$staff_id, $appointment_id]);
        
        // Log the assignment
        try {
            $log_query = "INSERT INTO appointment_staff_logs (appointment_id, staff_id, action, performed_by, created_at) 
                          VALUES (?, ?, 'assigned', ?, NOW())";
            $log_stmt = $pdo->prepare($log_query);
            $log_stmt->execute([$appointment_id, $staff_id, $_SESSION['user_id']]);
        } catch (Exception $e) {
            // Log table might not exist, ignore
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Staff assigned successfully',
            'staff' => [
                'id' => $staff['id'],
                'name' => $staff['staff_name'],
                'specialization' => $staff['specialization'] ?? 'General'
            ]
        ]);
        
    } elseif ($action === 'remove') {
        // Remove staff assignment
        $update_query = "UPDATE appointments SET staff_id = NULL, modified_at = NOW() WHERE id = ?";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([$appointment_id]);
        
        // Log the removal
        try {
            $log_query = "INSERT INTO appointment_staff_logs (appointment_id, action, performed_by, created_at) 
                          VALUES (?, 'removed', ?, NOW())";
            $log_stmt = $pdo->prepare($log_query);
            $log_stmt->execute([$appointment_id, $_SESSION['user_id']]);
        } catch (Exception $e) {
            // Log table might not exist, ignore
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Staff assignment removed'
        ]);
        
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log('Assign staff error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function isStaffAvailable($pdo, $staff_id, $date, $time, $duration, $exclude_appointment) {
    $start_time = strtotime($time);
    $end_time = $start_time + ($duration * 60);
    $end_time_str = date('H:i:s', $end_time);
    
    $query = "
        SELECT COUNT(*) as conflict_count
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.staff_id = ?
        AND a.appointment_date = ?
        AND a.id != ?
        AND a.status IN ('confirmed', 'pending')
        AND (
            (a.appointment_time <= ? AND ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) > ?)
            OR
            (? < ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) AND ? >= a.appointment_time)
        )
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $staff_id, 
        $date, 
        $exclude_appointment, 
        $time, 
        $time, 
        $end_time_str, 
        $end_time_str
    ]);
    
    $result = $stmt->fetch();
    return $result['conflict_count'] == 0;
}
?>
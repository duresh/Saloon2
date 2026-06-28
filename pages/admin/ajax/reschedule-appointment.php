<?php
// Disable error display - we want clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

// Get POST data
$appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
$new_service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
$new_staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
$new_date = $_POST['date'] ?? '';
$new_time = $_POST['time'] ?? '';
$notes = $_POST['notes'] ?? '';
$reschedule_reason = $_POST['reason'] ?? '';

// Log received data for debugging (write to file instead of output)
error_log("Reschedule request - Appointment ID: $appointment_id, Date: $new_date, Time: $new_time");

// Validate required fields
if (!$appointment_id || !$new_date || !$new_time) {
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields'
    ]);
    exit();
}

try {
    $pdo = getPDOConnection();
    $admin_id = $_SESSION['user_id'];
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get current appointment details with service duration
    $current_query = "
        SELECT a.*, s.duration, s.name as service_name, 
               u.fName as customer_name, u.email as customer_email
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg u ON a.user_id = u.regID
        WHERE a.id = ? FOR UPDATE
    ";
    $current_stmt = $pdo->prepare($current_query);
    $current_stmt->execute([$appointment_id]);
    $current = $current_stmt->fetch();
    
    if (!$current) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }
    
    // Check if the new time slot is available
    $slot_check = "
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE appointment_date = ? 
        AND appointment_time = ? 
        AND id != ?
        AND status IN ('pending', 'confirmed')
    ";
    $slot_stmt = $pdo->prepare($slot_check);
    $slot_stmt->execute([$new_date, $new_time, $appointment_id]);
    $slot_result = $slot_stmt->fetch();
    
    if ($slot_result['count'] > 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Selected time slot is already booked']);
        exit();
    }
    
    // Check staff availability if staff is assigned
    if ($new_staff_id > 0) {
        $staff_check = "
            SELECT COUNT(*) as count 
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            WHERE a.staff_id = ?
            AND a.appointment_date = ?
            AND a.id != ?
            AND a.status IN ('confirmed', 'pending')
            AND (
                (a.appointment_time <= ? AND 
                 ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) > ?)
                OR
                (? < ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) AND 
                 ? >= a.appointment_time)
            )
        ";
        $staff_stmt = $pdo->prepare($staff_check);
        
        $start_time = strtotime($new_time);
        $end_time = $start_time + ($current['duration'] * 60);
        $end_time_str = date('H:i:s', $end_time);
        
        $staff_stmt->execute([
            $new_staff_id,
            $new_date,
            $appointment_id,
            $new_time,
            $new_time,
            $end_time_str,
            $end_time_str
        ]);
        $staff_result = $staff_stmt->fetch();
        
        if ($staff_result['count'] > 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Selected staff member is not available at this time']);
            exit();
        }
    }
    
    // Update appointment
    $update_query = "
        UPDATE appointments 
        SET service_id = ?,
            staff_id = ?,
            appointment_date = ?,
            appointment_time = ?,
            notes = ?,
            modified_at = NOW(),
            reschedule_count = reschedule_count + 1,
            last_reschedule_at = NOW()
        WHERE id = ?
    ";
    
    $update_stmt = $pdo->prepare($update_query);
    $update_result = $update_stmt->execute([
        $new_service_id,
        $new_staff_id ?: null,
        $new_date,
        $new_time,
        $notes,
        $appointment_id
    ]);
    
    if (!$update_result) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update appointment']);
        exit();
    }
    
    // Insert into appointment_reschedules
    $reschedule_query = "
        INSERT INTO appointment_reschedules (
            appointment_id, user_id, 
            old_service_id, new_service_id,
            old_staff_id, new_staff_id,
            old_date, new_date,
            old_time, new_time,
            reschedule_reason, rescheduled_by,
            ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $reschedule_stmt = $pdo->prepare($reschedule_query);
    
    // Prepare values with NULL handling
    $old_staff_id = $current['staff_id'] ?: null;
    $new_staff_id_value = $new_staff_id ?: null;
    $old_service_id = $current['service_id'] ?: null;
    $new_service_id_value = $new_service_id ?: null;
    
    $reschedule_result = $reschedule_stmt->execute([
        $appointment_id,
        $admin_id,
        $old_service_id,
        $new_service_id_value,
        $old_staff_id,
        $new_staff_id_value,
        $current['appointment_date'],
        $new_date,
        $current['appointment_time'],
        $new_time,
        $reschedule_reason,
        'admin',
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    if (!$reschedule_result) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to log reschedule']);
        exit();
    }
    
    $reschedule_id = $pdo->lastInsertId();
    
    // Insert into reschedule_logs
    $log_query = "
        INSERT INTO reschedule_logs (reschedule_id, action, field_name, old_value, new_value) 
        VALUES (?, 'change', ?, ?, ?)
    ";
    $log_stmt = $pdo->prepare($log_query);
    
    // Log service change
    if ($current['service_id'] != $new_service_id) {
        $log_stmt->execute([
            $reschedule_id,
            'service',
            $current['service_name'] ?? 'Unknown',
            getServiceName($pdo, $new_service_id) ?? 'Unknown'
        ]);
    }
    
    // Log staff change
    if (($current['staff_id'] ?? 0) != $new_staff_id) {
        $log_stmt->execute([
            $reschedule_id,
            'staff',
            getStaffName($pdo, $current['staff_id'] ?? null) ?? 'Unassigned',
            getStaffName($pdo, $new_staff_id) ?? 'Unassigned'
        ]);
    }
    
    // Log date change
    if ($current['appointment_date'] != $new_date) {
        $log_stmt->execute([
            $reschedule_id,
            'date',
            $current['appointment_date'],
            $new_date
        ]);
    }
    
    // Log time change
    if ($current['appointment_time'] != $new_time) {
        $log_stmt->execute([
            $reschedule_id,
            'time',
            $current['appointment_time'],
            $new_time
        ]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Appointment rescheduled successfully',
        'reschedule_id' => $reschedule_id,
        'appointment' => [
            'id' => $appointment_id,
            'date' => $new_date,
            'time' => $new_time,
            'customer' => $current['customer_name']
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Reschedule AJAX error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred. Please try again.'
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Reschedule error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again.'
    ]);
}

// Helper functions
function getServiceName($pdo, $service_id) {
    if (!$service_id) return 'Unknown';
    $stmt = $pdo->prepare("SELECT name FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $result = $stmt->fetch();
    return $result ? $result['name'] : 'Unknown';
}

function getStaffName($pdo, $staff_id) {
    if (!$staff_id) return 'Unassigned';
    $stmt = $pdo->prepare("
        SELECT r.fName as staff_name 
        FROM staff s 
        JOIN reg r ON s.user_id = r.regID 
        WHERE s.id = ?
    ");
    $stmt->execute([$staff_id]);
    $result = $stmt->fetch();
    return $result ? $result['staff_name'] : 'Unknown';
}
?>
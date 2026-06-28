<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
header('Content-Type: application/json');

// Log file for debugging
$log_file = '../../logs/reschedule_debug.log';
if (!file_exists('../../logs')) {
    mkdir('../../logs', 0777, true);
}

function debug_log($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

debug_log("=== New Request ===");
debug_log("POST Data: " . print_r($_POST, true));
debug_log("Session: " . print_r($_SESSION, true));

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    debug_log("Unauthorized access");
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

debug_log("Parsed data: appointment_id=$appointment_id, service_id=$new_service_id, staff_id=$new_staff_id, date=$new_date, time=$new_time");

// Validate required fields
if (!$appointment_id || !$new_date || !$new_time) {
    debug_log("Missing required fields");
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields'
    ]);
    exit();
}

try {
    debug_log("Connecting to database...");
    $pdo = getPDOConnection();
    $admin_id = $_SESSION['user_id'];
    
    debug_log("Database connected. Admin ID: $admin_id");
    
    // Begin transaction
    $pdo->beginTransaction();
    debug_log("Transaction started");
    
    // Get current appointment details
    $current_query = "
        SELECT a.*, s.duration, s.name as service_name, 
               u.fName as customer_name, u.email as customer_email
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg u ON a.user_id = u.regID
        WHERE a.id = ? FOR UPDATE
    ";
    debug_log("Executing query: " . $current_query);
    
    $current_stmt = $pdo->prepare($current_query);
    $current_stmt->execute([$appointment_id]);
    $current = $current_stmt->fetch();
    
    if (!$current) {
        debug_log("Appointment not found: $appointment_id");
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }
    
    debug_log("Current appointment data: " . print_r($current, true));
    
    // Check slot availability
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
        debug_log("Time slot already booked: $new_date $new_time");
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Selected time slot is already booked']);
        exit();
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
        debug_log("Update failed");
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update appointment']);
        exit();
    }
    
    debug_log("Appointment updated successfully");
    
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
    
    debug_log("Reschedule values: old_staff_id=$old_staff_id, new_staff_id=$new_staff_id_value");
    
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
        debug_log("Reschedule insert failed");
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to log reschedule']);
        exit();
    }
    
    $reschedule_id = $pdo->lastInsertId();
    debug_log("Reschedule recorded with ID: $reschedule_id");
    
    // Commit transaction
    $pdo->commit();
    debug_log("Transaction committed");
    
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
    debug_log("PDOException: " . $e->getMessage());
    debug_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    debug_log("Exception: " . $e->getMessage());
    debug_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
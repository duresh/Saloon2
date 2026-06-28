<?php
session_start();
require_once '../../includes/dbcon.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized');
}

$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    $pdo = getPDOConnection();
    
    echo "<h2>Reschedule Logs for Appointment #$appointment_id</h2>";
    
    // Check appointment_reschedules
    $reschedules = $pdo->prepare("
        SELECT * FROM appointment_reschedules 
        WHERE appointment_id = ? 
        ORDER BY rescheduled_at DESC
    ");
    $reschedules->execute([$appointment_id]);
    $reschedule_data = $reschedules->fetchAll();
    
    echo "<h3>appointment_reschedules (" . count($reschedule_data) . " records)</h3>";
    echo "<pre>";
    print_r($reschedule_data);
    echo "</pre>";
    
    // Check reschedule_logs
    if (!empty($reschedule_data)) {
        $reschedule_ids = array_column($reschedule_data, 'id');
        $placeholders = implode(',', array_fill(0, count($reschedule_ids), '?'));
        
        $logs = $pdo->prepare("
            SELECT * FROM reschedule_logs 
            WHERE reschedule_id IN ($placeholders)
            ORDER BY changed_at DESC
        ");
        $logs->execute($reschedule_ids);
        $log_data = $logs->fetchAll();
        
        echo "<h3>reschedule_logs (" . count($log_data) . " records)</h3>";
        echo "<pre>";
        print_r($log_data);
        echo "</pre>";
    }
    
    // Check staff_schedule_exceptions
    $exceptions = $pdo->prepare("
        SELECT * FROM staff_schedule_exceptions 
        WHERE staff_id = (SELECT staff_id FROM appointments WHERE id = ?)
        AND exception_date >= CURDATE()
        ORDER BY exception_date
    ");
    $exceptions->execute([$appointment_id]);
    $exception_data = $exceptions->fetchAll();
    
    echo "<h3>staff_schedule_exceptions (" . count($exception_data) . " records)</h3>";
    echo "<pre>";
    print_r($exception_data);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
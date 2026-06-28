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
$appointment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

error_log("Delete appointment - ID: $appointment_id");

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Check if appointment exists
    $check_query = "SELECT id FROM appointments WHERE id = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$appointment_id]);
    
    if (!$check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }
    
    // Delete related logs first (if tables exist)
    try {
        $delete_logs = "DELETE FROM appointment_reschedules WHERE appointment_id = ?";
        $logs_stmt = $pdo->prepare($delete_logs);
        $logs_stmt->execute([$appointment_id]);
    } catch (Exception $e) {
        // Table might not exist, continue
        error_log("Reschedules delete error (non-critical): " . $e->getMessage());
    }
    
    try {
        $delete_status_logs = "DELETE FROM appointment_status_logs WHERE appointment_id = ?";
        $status_logs_stmt = $pdo->prepare($delete_status_logs);
        $status_logs_stmt->execute([$appointment_id]);
    } catch (Exception $e) {
        // Table might not exist, continue
        error_log("Status logs delete error (non-critical): " . $e->getMessage());
    }
    
    // Delete the appointment
    $delete_query = "DELETE FROM appointments WHERE id = ?";
    $delete_stmt = $pdo->prepare($delete_query);
    $delete_result = $delete_stmt->execute([$appointment_id]);
    
    if ($delete_result) {
        echo json_encode([
            'success' => true,
            'message' => 'Appointment deleted successfully',
            'appointment_id' => $appointment_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete appointment']);
    }
    
} catch (PDOException $e) {
    error_log('Delete appointment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Delete appointment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
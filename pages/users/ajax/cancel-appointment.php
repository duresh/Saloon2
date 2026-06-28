<?php
ob_start();
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

require_once '../../includes/dbcon.php';

try {
    $pdo = getPDOConnection();
    
    $appointment_id = $_POST['id'];
    $user_id = $_SESSION['user_id'];
    
    // Verify ownership and status
    $verify_query = "SELECT * FROM appointments WHERE id = ? AND user_id = ? AND status = 'pending'";
    $verify_stmt = $pdo->prepare($verify_query);
    $verify_stmt->execute([$appointment_id, $user_id]);
    
    if ($verify_stmt->rowCount() > 0) {
        // Cancel appointment
        $update_query = "UPDATE appointments SET status = 'cancelled' WHERE id = ?";
        $update_stmt = $pdo->prepare($update_query);
        
        if ($update_stmt->execute([$appointment_id])) {
            echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or cannot be cancelled']);
    }
} catch (PDOException $e) {
    error_log('Cancel appointment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
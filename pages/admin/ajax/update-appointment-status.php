<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Staff can only mark appointments as completed
$is_staff = isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if (!$is_staff && !$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$appointment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

// Staff can only set status to 'completed'
if ($is_staff && $status !== 'completed') {
    echo json_encode(['success' => false, 'message' => 'Staff can only mark appointments as completed']);
    exit();
}

if (!in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    $pdo = getPDOConnection();
    $user_id = $_SESSION['user_id'];
    
    // For staff, verify they are assigned to this appointment
    if ($is_staff) {
        $staff_query = "SELECT id FROM staff WHERE user_id = ?";
        $staff_stmt = $pdo->prepare($staff_query);
        $staff_stmt->execute([$user_id]);
        $staff = $staff_stmt->fetch();
        
        if ($staff) {
            $check_query = "SELECT id FROM appointments WHERE id = ? AND staff_id = ?";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->execute([$appointment_id, $staff['id']]);
            if ($check_stmt->rowCount() == 0) {
                echo json_encode(['success' => false, 'message' => 'You are not authorized to update this appointment']);
                exit();
            }
        }
    }
    
    // Update appointment status
    $update_query = "UPDATE appointments SET status = ?, modified_at = NOW() WHERE id = ?";
    $update_stmt = $pdo->prepare($update_query);
    $update_result = $update_stmt->execute([$status, $appointment_id]);
    
    if ($update_result) {
        echo json_encode([
            'success' => true,
            'message' => 'Appointment status updated successfully',
            'new_status' => $status,
            'appointment_id' => $appointment_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update appointment status']);
    }
    
} catch (PDOException $e) {
    error_log('Update appointment status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
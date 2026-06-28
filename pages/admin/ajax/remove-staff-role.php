<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    $admin_id = $_SESSION['user_id'];
    
    // Check if user exists and is staff
    $user_check = "SELECT role FROM reg WHERE regID = ?";
    $user_stmt = $pdo->prepare($user_check);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    if ($user['role'] != 'staff') {
        echo json_encode(['success' => false, 'message' => 'User is not a staff member']);
        exit();
    }
    
    // Check for upcoming appointments
    $check_appointments = "
        SELECT COUNT(*) as count FROM appointments 
        WHERE staff_id = (SELECT id FROM staff WHERE user_id = ?) 
        AND appointment_date >= CURDATE() 
        AND status IN ('pending', 'confirmed')
    ";
    $appointments_stmt = $pdo->prepare($check_appointments);
    $appointments_stmt->execute([$user_id]);
    $appointments = $appointments_stmt->fetch();
    
    if ($appointments['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot remove staff role. User has upcoming appointments.']);
        exit();
    }
    
    // Update user role to user
    $update_role = "UPDATE reg SET role = 'user', modifyDate = NOW(), updated_by = ? WHERE regID = ?";
    $update_role_stmt = $pdo->prepare($update_role);
    $update_role_stmt->execute([$admin_id, $user_id]);
    
    // Remove from staff table
    $delete_staff = "DELETE FROM staff WHERE user_id = ?";
    $delete_stmt = $pdo->prepare($delete_staff);
    $delete_stmt->execute([$user_id]);
    
    // Log the action
    $log_query = "INSERT INTO user_action_logs (user_id, action, performed_by, performed_at) VALUES (?, 'remove_staff', ?, NOW())";
    $log_stmt = $pdo->prepare($log_query);
    $log_stmt->execute([$user_id, $admin_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Staff role has been removed successfully'
    ]);
    
} catch (PDOException $e) {
    error_log('Remove staff role error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
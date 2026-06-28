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
    
    // Prevent deleting own account
    if ($user_id == $admin_id) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        exit();
    }
    
    // Check for upcoming appointments
    $check_appointments = "
        SELECT COUNT(*) as count FROM appointments 
        WHERE user_id = ? AND appointment_date >= CURDATE() AND status IN ('pending', 'confirmed')
    ";
    $appointments_stmt = $pdo->prepare($check_appointments);
    $appointments_stmt->execute([$user_id]);
    $appointments = $appointments_stmt->fetch();
    
    if ($appointments['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete user. User has upcoming appointments.']);
        exit();
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete from staff if exists
    $delete_staff = "DELETE FROM staff WHERE user_id = ?";
    $delete_stmt = $pdo->prepare($delete_staff);
    $delete_stmt->execute([$user_id]);
    
    // Delete from user_profiles
    $delete_profile = "DELETE FROM user_profiles WHERE user_id = ?";
    $delete_profile_stmt = $pdo->prepare($delete_profile);
    $delete_profile_stmt->execute([$user_id]);
    
    // Delete the user
    $delete_user = "DELETE FROM reg WHERE regID = ?";
    $delete_user_stmt = $pdo->prepare($delete_user);
    $delete_user_stmt->execute([$user_id]);
    
    // Log the action
    $log_query = "INSERT INTO admin_action_logs (admin_id, action, target_user, performed_at) VALUES (?, 'delete_user', ?, NOW())";
    $log_stmt = $pdo->prepare($log_query);
    $log_stmt->execute([$admin_id, $user_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'User account has been permanently deleted'
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log('Delete user error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
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
$new_role = isset($_POST['new_role']) ? $_POST['new_role'] : '';

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

if (!in_array($new_role, ['user', 'staff', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit();
}

try {
    $pdo = getPDOConnection();
    $admin_id = $_SESSION['user_id'];
    
    // Get current user details
    $user_query = "SELECT role FROM reg WHERE regID = ?";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$user_id]);
    $current_user = $user_stmt->fetch();
    
    if (!$current_user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $old_role = $current_user['role'];
    
    // Prevent changing own role if trying to demote from admin
    if ($user_id == $admin_id && $old_role == 'admin' && $new_role != 'admin') {
        echo json_encode(['success' => false, 'message' => 'You cannot change your own admin role']);
        exit();
    }
    
    // Update role
    $query = "UPDATE reg SET role = ?, modifyDate = NOW(), updated_by = ? WHERE regID = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$new_role, $admin_id, $user_id]);
    
    // If changing from staff to something else, delete from staff table
    if ($old_role == 'staff' && $new_role != 'staff') {
        $delete_staff = "DELETE FROM staff WHERE user_id = ?";
        $delete_stmt = $pdo->prepare($delete_staff);
        $delete_stmt->execute([$user_id]);
    }
    
    // If changing to staff, ensure staff record exists
    if ($new_role == 'staff') {
        $check_staff = "SELECT id FROM staff WHERE user_id = ?";
        $check_stmt = $pdo->prepare($check_staff);
        $check_stmt->execute([$user_id]);
        
        if (!$check_stmt->fetch()) {
            $insert_staff = "INSERT INTO staff (user_id, created_at) VALUES (?, NOW())";
            $insert_stmt = $pdo->prepare($insert_staff);
            $insert_stmt->execute([$user_id]);
        }
    }
    
    // Log the action
    $log_query = "INSERT INTO user_action_logs (user_id, action, old_value, new_value, performed_by, performed_at) VALUES (?, 'role_change', ?, ?, ?, NOW())";
    $log_stmt = $pdo->prepare($log_query);
    $log_stmt->execute([$user_id, $old_role, $new_role, $admin_id]);
    
    echo json_encode([
        'success' => true,
        'message' => "User role changed from $old_role to $new_role",
        'old_role' => $old_role,
        'new_role' => $new_role
    ]);
    
} catch (PDOException $e) {
    error_log('Change user role error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
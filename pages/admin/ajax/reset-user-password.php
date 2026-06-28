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
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

if (empty($new_password) || strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit();
}

try {
    $pdo = getPDOConnection();
    $admin_id = $_SESSION['user_id'];
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $query = "UPDATE reg SET password = ?, password_changed = 0, modifyDate = NOW(), updated_by = ? WHERE regID = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$hashed_password, $admin_id, $user_id]);
    
    // Log the action
    $log_query = "INSERT INTO user_action_logs (user_id, action, performed_by, performed_at) VALUES (?, 'password_reset', ?, NOW())";
    $log_stmt = $pdo->prepare($log_query);
    $log_stmt->execute([$user_id, $admin_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password has been reset successfully. User will be prompted to change it on next login.'
    ]);
    
} catch (PDOException $e) {
    error_log('Reset user password error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
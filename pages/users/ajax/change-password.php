<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$user_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate inputs
if (empty($current_password)) {
    echo json_encode(['success' => false, 'message' => 'Current password is required']);
    exit();
}

if (empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'New password is required']);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
    exit();
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Get user's current password
    $user_query = "SELECT password FROM reg WHERE regID = ? AND cStatus = 1";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }
    
    // Check if new password is same as old
    if (password_verify($new_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'New password must be different from the current password']);
        exit();
    }
    
    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $update_query = "UPDATE reg SET password = ?, modifyDate = NOW() WHERE regID = ?";
    $update_stmt = $pdo->prepare($update_query);
    $result = $update_stmt->execute([$hashed_password, $user_id]);
    
    if ($result) {
        // Optional: Log password change
        $log_query = "INSERT INTO password_change_logs (user_id, changed_at, ip_address) VALUES (?, NOW(), ?)";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([$user_id, $_SERVER['REMOTE_ADDR'] ?? null]);
        
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change password. Please try again.']);
    }
    
} catch (PDOException $e) {
    error_log('Change password error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
} catch (Exception $e) {
    error_log('Change password error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
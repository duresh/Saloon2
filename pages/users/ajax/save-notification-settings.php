<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$user_id = $_SESSION['user_id'];
$type = $_POST['type'] ?? '';
$status = $_POST['status'] ?? '';

// Validate inputs
if (empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Notification type is required']);
    exit();
}

if (!in_array($type, ['email', 'sms'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification type']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // First, ensure user_profile exists
    $check_profile = "SELECT id FROM user_profiles WHERE user_id = ?";
    $check_stmt = $pdo->prepare($check_profile);
    $check_stmt->execute([$user_id]);
    
    if ($check_stmt->rowCount() == 0) {
        // Create profile if doesn't exist
        $create_profile = "INSERT INTO user_profiles (user_id, notification_email, notification_sms, created_at) VALUES (?, 1, 0, NOW())";
        $create_stmt = $pdo->prepare($create_profile);
        $create_stmt->execute([$user_id]);
    }
    
    // Update notification setting based on type
    if ($type == 'email') {
        $update = "UPDATE user_profiles SET notification_email = ?, updated_at = NOW() WHERE user_id = ?";
        $value = ($status == 'enabled') ? 1 : 0;
    } else {
        $update = "UPDATE user_profiles SET notification_sms = ?, updated_at = NOW() WHERE user_id = ?";
        $value = ($status == 'enabled') ? 1 : 0;
    }
    
    $stmt = $pdo->prepare($update);
    $result = $stmt->execute([$value, $user_id]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => ucfirst($type) . ' notifications ' . ($status == 'enabled' ? 'enabled' : 'disabled'),
            'type' => $type,
            'status' => $status
        ]);
    } else {
        $errorInfo = $stmt->errorInfo();
        echo json_encode(['success' => false, 'message' => 'Failed to save settings: ' . $errorInfo[2]]);
    }
    
} catch (PDOException $e) {
    error_log('Save notification settings error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Save notification settings error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
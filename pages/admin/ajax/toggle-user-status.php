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
$status = isset($_POST['status']) ? $_POST['status'] : '';

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

if (!in_array($status, ['activate', 'deactivate'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    $pdo = getPDOConnection();
    $admin_id = $_SESSION['user_id'];
    
    // Prevent deactivating own account
    if ($user_id == $admin_id) {
        echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account']);
        exit();
    }
    
    $new_status = ($status == 'activate') ? 1 : 0;
    
    $query = "UPDATE reg SET cStatus = ?, modifyDate = NOW(), updated_by = ? WHERE regID = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$new_status, $admin_id, $user_id]);
    
    // Log the action
    $log_query = "INSERT INTO user_action_logs (user_id, action, performed_by, performed_at) VALUES (?, ?, ?, NOW())";
    $log_stmt = $pdo->prepare($log_query);
    $log_stmt->execute([$user_id, $status, $admin_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'User ' . $status . 'd successfully'
    ]);
    
} catch (PDOException $e) {
    error_log('Toggle user status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
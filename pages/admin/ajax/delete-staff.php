<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../includes/dbcon.php';

$staff_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$staff_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get user_id before deleting
    $user_query = "SELECT user_id FROM staff WHERE id = ?";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$staff_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Staff not found']);
        exit();
    }
    
    // Delete staff record (cascade will handle related tables)
    $delete_staff = "DELETE FROM staff WHERE id = ?";
    $staff_stmt = $pdo->prepare($delete_staff);
    $staff_stmt->execute([$staff_id]);
    
    // Delete user from reg table
    $delete_user = "DELETE FROM reg WHERE regID = ?";
    $user_stmt = $pdo->prepare($delete_user);
    $user_stmt->execute([$user['user_id']]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Staff deleted successfully']);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log('Delete staff error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Cannot delete staff with existing appointments']);
}
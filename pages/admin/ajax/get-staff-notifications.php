<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../includes/dbcon.php';

try {
    $pdo = getPDOConnection();
    $staff_id = getStaffIdByUserId($pdo, $_SESSION['user_id']);
    
    $query = "
        SELECT * FROM staff_notifications 
        WHERE staff_id = ? AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 20
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$staff_id]);
    $notifications = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'notifications' => $notifications]);
    
} catch (PDOException $e) {
    error_log('Get notifications error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

function getStaffIdByUserId($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}
?>
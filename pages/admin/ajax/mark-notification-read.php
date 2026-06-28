<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../includes/dbcon.php';

$notification_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$notification_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    $update = "UPDATE staff_notifications SET is_read = 1 WHERE id = ?";
    $stmt = $pdo->prepare($update);
    $stmt->execute([$notification_id]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log('Mark notification read error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
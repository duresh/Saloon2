<?php
ob_start();
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['hasUpdates' => false]);
    exit();
}

require_once '../../includes/dbcon.php';

try {
    $pdo = getPDOConnection();
    $user_id = $_SESSION['user_id'];
    
    // Check for recent status changes (last 30 seconds)
    $query = "SELECT COUNT(*) as count FROM appointments 
              WHERE user_id = ? 
              AND status_changed_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    echo json_encode(['hasUpdates' => $result['count'] > 0]);
} catch (PDOException $e) {
    echo json_encode(['hasUpdates' => false]);
}
?>
<?php
// ajax/check-appointment-updates.php - Check for appointment updates
session_start();
require_once '../includes/dbcon.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['hasUpdates' => false]);
    exit();
}

if (!isset($_POST['user_id'])) {
    echo json_encode(['hasUpdates' => false]);
    exit();
}

$user_id = intval($_POST['user_id']);
$last_check = isset($_POST['last_check']) ? $_POST['last_check'] : date('Y-m-d H:i:s', strtotime('-1 minute'));

try {
    $pdo = getPDOConnection();
    
    $query = "SELECT COUNT(*) as count FROM appointments 
              WHERE user_id = ? AND modified_at > ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $last_check]);
    $result = $stmt->fetch();
    
    echo json_encode(['hasUpdates' => $result['count'] > 0]);
    
} catch (PDOException $e) {
    error_log('Check updates error: ' . $e->getMessage());
    echo json_encode(['hasUpdates' => false]);
}
?>
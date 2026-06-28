<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
$staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
$user_id = $_SESSION['user_id'];

if (!$appointment_id || !$staff_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating value']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Check if appointment exists and belongs to user
    $check_query = "
        SELECT a.*, s.name as service_name 
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.id = ? AND a.user_id = ? AND a.status = 'completed'
    ";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$appointment_id, $user_id]);
    $appointment = $check_stmt->fetch();
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Invalid appointment or appointment not completed']);
        exit();
    }
    
    // Check if already rated
    $check_rating = "SELECT id FROM staff_ratings WHERE appointment_id = ?";
    $check_rating_stmt = $pdo->prepare($check_rating);
    $check_rating_stmt->execute([$appointment_id]);
    
    if ($check_rating_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already rated this appointment']);
        exit();
    }
    
    // Insert rating
    $insert_query = "
        INSERT INTO staff_ratings (appointment_id, staff_id, user_id, rating, comments, service_name, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ";
    $insert_stmt = $pdo->prepare($insert_query);
    $insert_stmt->execute([$appointment_id, $staff_id, $user_id, $rating, $comments, $appointment['service_name']]);
    
    // Create notification for staff
    $notify_query = "
        INSERT INTO staff_notifications (staff_id, title, message, is_read, created_at)
        VALUES (?, 'New Rating', ?, 0, NOW())
    ";
    $message = "You received a $rating-star rating from customer for service: " . $appointment['service_name'];
    $notify_stmt = $pdo->prepare($notify_query);
    $notify_stmt->execute([$staff_id, $message]);
    
    echo json_encode(['success' => true, 'message' => 'Rating submitted successfully']);
    
} catch (PDOException $e) {
    error_log('Submit rating error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
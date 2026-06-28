<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../includes/dbcon.php';

$appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
$type = $_POST['type'] ?? '';
$message = $_POST['message'] ?? '';

if (!$appointment_id || !$message) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    $pdo = getPDOConnection();
    $staff_id = getStaffIdByUserId($pdo, $_SESSION['user_id']);
    
    // Get appointment and customer details
    $apt_query = "
        SELECT a.*, u.fName, u.email, u.contactNo 
        FROM appointments a
        JOIN reg u ON a.user_id = u.regID
        WHERE a.id = ? AND a.staff_id = ?
    ";
    $apt_stmt = $pdo->prepare($apt_query);
    $apt_stmt->execute([$appointment_id, $staff_id]);
    $appointment = $apt_stmt->fetch();
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }
    
    // Insert notification for customer
    $insert = "INSERT INTO customer_notifications (user_id, appointment_id, type, message, is_read, created_at) 
               VALUES (?, ?, ?, ?, 0, NOW())";
    $stmt = $pdo->prepare($insert);
    $stmt->execute([$appointment['user_id'], $appointment_id, $type, $message]);
    
    // In a real system, you would also send SMS/Email here
    
    echo json_encode(['success' => true, 'message' => 'Notification sent to customer']);
    
} catch (PDOException $e) {
    error_log('Send notification error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

function getStaffIdByUserId($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}
?>
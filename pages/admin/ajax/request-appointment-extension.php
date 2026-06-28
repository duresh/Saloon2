<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../includes/dbcon.php';

$appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
$new_end_time = $_POST['new_end_time'] ?? '';
$reason = $_POST['reason'] ?? '';

if (!$appointment_id || !$new_end_time) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    $pdo = getPDOConnection();
    $staff_id = getStaffIdByUserId($pdo, $_SESSION['user_id']);
    
    // Get appointment details
    $apt_query = "SELECT * FROM appointments WHERE id = ? AND staff_id = ?";
    $apt_stmt = $pdo->prepare($apt_query);
    $apt_stmt->execute([$appointment_id, $staff_id]);
    $appointment = $apt_stmt->fetch();
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }
    
    // Insert extension request
    $insert = "INSERT INTO appointment_extension_requests (appointment_id, staff_id, new_end_time, reason, status, created_at) 
               VALUES (?, ?, ?, ?, 'pending', NOW())";
    $stmt = $pdo->prepare($insert);
    $stmt->execute([$appointment_id, $staff_id, $new_end_time, $reason]);
    
    // Notify admin
    createAdminNotification($pdo, "Staff member requested extension for appointment #$appointment_id", $appointment_id);
    
    echo json_encode(['success' => true, 'message' => 'Extension request submitted']);
    
} catch (PDOException $e) {
    error_log('Extension request error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

function getStaffIdByUserId($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function createAdminNotification($pdo, $message, $appointment_id) {
    // Get admin users
    $admin_query = "SELECT regID FROM reg WHERE role = 'admin' AND cStatus = 1";
    $admins = $pdo->query($admin_query);
    $insert = "INSERT INTO admin_notifications (admin_id, message, appointment_id, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($insert);
    
    while ($admin = $admins->fetch()) {
        $stmt->execute([$admin['regID'], $message, $appointment_id]);
    }
}
?>
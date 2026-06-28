<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../includes/dbcon.php';

$appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
$new_date = $_POST['new_date'] ?? '';
$new_time = $_POST['new_time'] ?? '';
$reason = $_POST['reason'] ?? '';

if (!$appointment_id || !$new_date || !$new_time) {
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
    
    // Check for conflicts at new time
    $conflict_query = "
        SELECT COUNT(*) as count FROM appointments 
        WHERE staff_id = ? AND appointment_date = ? AND appointment_time = ? 
        AND id != ? AND status IN ('confirmed', 'pending')
    ";
    $conflict_stmt = $pdo->prepare($conflict_query);
    $conflict_stmt->execute([$staff_id, $new_date, $new_time, $appointment_id]);
    $conflict = $conflict_stmt->fetch();
    
    if ($conflict['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Time slot conflict with another appointment']);
        exit();
    }
    
    // Insert move request
    $insert = "INSERT INTO appointment_move_requests (appointment_id, staff_id, new_date, new_time, reason, status, created_at) 
               VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    $stmt = $pdo->prepare($insert);
    $stmt->execute([$appointment_id, $staff_id, $new_date, $new_time, $reason]);
    
    // Notify admin
    createAdminNotification($pdo, "Staff member requested to move appointment #$appointment_id to $new_date at $new_time", $appointment_id);
    
    echo json_encode(['success' => true, 'message' => 'Move request submitted']);
    
} catch (PDOException $e) {
    error_log('Move request error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

function getStaffIdByUserId($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function createAdminNotification($pdo, $message, $appointment_id) {
    $admin_query = "SELECT regID FROM reg WHERE role = 'admin' AND cStatus = 1";
    $admins = $pdo->query($admin_query);
    $insert = "INSERT INTO admin_notifications (admin_id, message, appointment_id, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($insert);
    
    while ($admin = $admins->fetch()) {
        $stmt->execute([$admin['regID'], $message, $appointment_id]);
    }
}
?>
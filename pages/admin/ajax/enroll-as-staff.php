<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../includes/dbcon.php';

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : '';
$experience = isset($_POST['experience']) ? floatval($_POST['experience']) : 0;
$joining_date = isset($_POST['joining_date']) ? $_POST['joining_date'] : date('Y-m-d');
$phone = isset($_POST['phone']) ? $_POST['phone'] : '';

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

if (empty($specialization)) {
    echo json_encode(['success' => false, 'message' => 'Specialization is required']);
    exit();
}

if ($experience < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid experience value']);
    exit();
}

try {
    $pdo = getPDOConnection();
    $admin_id = $_SESSION['user_id'];
    
    // Check if user already has staff record
    $check_staff = "SELECT id FROM staff WHERE user_id = ?";
    $check_stmt = $pdo->prepare($check_staff);
    $check_stmt->execute([$user_id]);
    
    if ($check_stmt->fetch()) {
        // Update existing staff record
        $update_query = "
            UPDATE staff 
            SET specialization = ?, 
                experience_years = ?, 
                joining_date = ?,
                phone = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([$specialization, $experience, $joining_date, $phone, $user_id]);
    } else {
        // Create new staff record
        $insert_query = "
            INSERT INTO staff (user_id, specialization, experience_years, joining_date, phone, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ";
        $insert_stmt = $pdo->prepare($insert_query);
        $insert_stmt->execute([$user_id, $specialization, $experience, $joining_date, $phone]);
    }
    
    // Update user role to staff
    $update_role = "UPDATE reg SET role = 'staff', modifyDate = NOW(), updated_by = ? WHERE regID = ?";
    $update_role_stmt = $pdo->prepare($update_role);
    $update_role_stmt->execute([$admin_id, $user_id]);
    
    // Log the action
    $log_query = "INSERT INTO user_action_logs (user_id, action, performed_by, performed_at) VALUES (?, 'enroll_staff', ?, NOW())";
    $log_stmt = $pdo->prepare($log_query);
    $log_stmt->execute([$user_id, $admin_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'User has been enrolled as staff member successfully'
    ]);
    
} catch (PDOException $e) {
    error_log('Enroll as staff error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
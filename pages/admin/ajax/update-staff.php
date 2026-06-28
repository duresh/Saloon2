<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../includes/dbcon.php';

$staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;

if (!$staff_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get user_id from staff record
    $user_query = "SELECT user_id FROM staff WHERE id = ?";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$staff_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Staff not found']);
        exit();
    }
    
    // Update reg table
    $reg_query = "UPDATE reg SET fName = ?, email = ?, contactNo = ? WHERE regID = ?";
    $reg_stmt = $pdo->prepare($reg_query);
    $reg_stmt->execute([
        $_POST['name'],
        $_POST['email'],
        $_POST['phone_primary'],
        $user['user_id']
    ]);
    
    // Update staff table
    $staff_query = "
        UPDATE staff SET 
            phone = ?,
            specialization = ?,
            experience_years = ?,
            qualification = ?,
            joining_date = ?,
            bio = ?,
            address = ?
        WHERE id = ?
    ";
    
    $staff_stmt = $pdo->prepare($staff_query);
    $staff_stmt->execute([
        $_POST['phone_secondary'] ?? null,
        $_POST['specialization'],
        $_POST['experience'],
        $_POST['qualification'] ?? null,
        $_POST['joining_date'] ?? null,
        $_POST['bio'] ?? null,
        $_POST['address'] ?? null,
        $staff_id
    ]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Staff updated successfully']);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log('Update staff error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
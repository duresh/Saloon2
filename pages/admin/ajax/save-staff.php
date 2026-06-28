<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../includes/dbcon.php';

try {
    $pdo = getPDOConnection();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get form data
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone_primary = $_POST['phone_primary'];
    $phone_secondary = $_POST['phone_secondary'] ?? null;
    $specialization = $_POST['specialization'];
    $experience = $_POST['experience'];
    $qualification = $_POST['qualification'] ?? null;
    $joining_date = $_POST['joining_date'] ?? date('Y-m-d');
    $bio = $_POST['bio'] ?? null;
    $address = $_POST['address'] ?? null;
    
    // Set default password (staff@123)
    $default_password = 'staff@123';
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
    
    // Insert into reg table with password_changed = 0
    $reg_query = "INSERT INTO reg (fName, email, contactNo, password, password_changed, role, cStatus, regDate, modifyDate) 
                  VALUES (?, ?, ?, ?, 0, 'staff', 1, NOW(), NOW())";
    $reg_stmt = $pdo->prepare($reg_query);
    $reg_stmt->execute([$name, $email, $phone_primary, $hashed_password]);
    
    $user_id = $pdo->lastInsertId();
    
    // Insert into staff table
    $staff_query = "
        INSERT INTO staff (
            user_id, phone, specialization, experience_years, 
            qualification, joining_date, bio, address, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    
    $staff_stmt = $pdo->prepare($staff_query);
    $staff_stmt->execute([
        $user_id,
        $phone_secondary,
        $specialization,
        $experience,
        $qualification,
        $joining_date,
        $bio,
        $address
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Staff added successfully. Default password: ' . $default_password,
        'default_password' => $default_password
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log('Save staff error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
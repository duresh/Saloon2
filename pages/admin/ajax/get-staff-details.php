<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$staff_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$staff_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    $query = "
        SELECT 
            s.id,
            s.user_id,
            s.phone,
            s.address,
            s.specialization,
            s.qualification,
            s.experience_years,
            s.joining_date,
            s.bio,
            s.profile_image,
            s.emergency_contact,
            s.emergency_name,
            s.created_at,
            r.fName as staff_name,
            r.email,
            r.contactNo,
            r.regDate,
            (
                SELECT COUNT(*) 
                FROM appointments a 
                WHERE a.staff_id = s.id 
                AND a.status = 'completed'
            ) as completed_appointments,
            (
                SELECT COUNT(*) 
                FROM appointments a 
                WHERE a.staff_id = s.id
            ) as total_appointments,
            (
                SELECT COUNT(*) 
                FROM staff_ratings sr 
                WHERE sr.staff_id = s.id
            ) as total_ratings,
            (
                SELECT ROUND(AVG(rating), 2) 
                FROM staff_ratings sr 
                WHERE sr.staff_id = s.id
            ) as avg_rating
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE s.id = ? AND r.cStatus = 1
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($staff) {
        // Calculate completion rate
        $completion_rate = $staff['total_appointments'] > 0 
            ? round(($staff['completed_appointments'] / $staff['total_appointments']) * 100, 2) 
            : 0;
        
        $staff['completion_rate'] = $completion_rate;
        
        echo json_encode(['success' => true, 'staff' => $staff]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Staff not found']);
    }
    
} catch (PDOException $e) {
    error_log('Get staff details error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
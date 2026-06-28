<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$user_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // FIXED: Removed created_by and updated_by references
    $query = "
        SELECT 
            r.*,
            s.id as staff_id,
            s.specialization,
            s.experience_years,
            s.joining_date,
            s.phone as staff_phone,
            s.bio as staff_bio,
            s.address as staff_address,
            s.qualification,
            (SELECT COUNT(*) FROM appointments WHERE user_id = r.regID) as total_appointments,
            (SELECT COUNT(*) FROM appointments WHERE user_id = r.regID AND status = 'completed') as completed_appointments,
            (SELECT COUNT(*) FROM appointments WHERE user_id = r.regID AND status = 'cancelled') as cancelled_appointments,
            (SELECT COUNT(*) FROM appointments WHERE user_id = r.regID AND appointment_date >= CURDATE() AND status IN ('pending', 'confirmed')) as upcoming_appointments
        FROM reg r
        LEFT JOIN staff s ON r.regID = s.user_id
        WHERE r.regID = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Get staff rating information if user is staff
        if ($user['role'] == 'staff' && isset($user['staff_id'])) {
            $rating_query = "
                SELECT 
                    COUNT(*) as total_ratings,
                    ROUND(AVG(rating), 1) as average_rating,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5_count,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4_count,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3_count,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2_count,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1_count
                FROM staff_ratings
                WHERE staff_id = ?
            ";
            $rating_stmt = $pdo->prepare($rating_query);
            $rating_stmt->execute([$user['staff_id']]);
            $ratings = $rating_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ratings && $ratings['total_ratings'] > 0) {
                $user['total_ratings'] = $ratings['total_ratings'];
                $user['average_rating'] = $ratings['average_rating'];
                $user['rating_5_count'] = $ratings['rating_5_count'];
                $user['rating_4_count'] = $ratings['rating_4_count'];
                $user['rating_3_count'] = $ratings['rating_3_count'];
                $user['rating_2_count'] = $ratings['rating_2_count'];
                $user['rating_1_count'] = $ratings['rating_1_count'];
            } else {
                $user['total_ratings'] = 0;
                $user['average_rating'] = 0;
                $user['rating_5_count'] = 0;
                $user['rating_4_count'] = 0;
                $user['rating_3_count'] = 0;
                $user['rating_2_count'] = 0;
                $user['rating_1_count'] = 0;
            }
        }
        
        // Get recent appointments for this user (optional - for more detailed view)
        $recent_appointments_query = "
            SELECT 
                a.id,
                a.appointment_date,
                a.appointment_time,
                a.status,
                s.name as service_name,
                st.specialization as staff_specialization,
                CONCAT(r2.fName, ' ', r2.lName) as staff_name
            FROM appointments a
            LEFT JOIN services s ON a.service_id = s.id
            LEFT JOIN staff st ON a.staff_id = st.id
            LEFT JOIN reg r2 ON st.user_id = r2.regID
            WHERE a.user_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 5
        ";
        $recent_stmt = $pdo->prepare($recent_appointments_query);
        $recent_stmt->execute([$user_id]);
        $user['recent_appointments'] = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    
} catch (PDOException $e) {
    error_log('Get user details error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
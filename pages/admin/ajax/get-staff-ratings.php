<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
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
    
    // Get staff details
    $staff_query = "
        SELECT 
            s.id,
            s.user_id,
            s.specialization,
            s.experience_years,
            r.fName as staff_name,
            r.email
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE s.id = ? AND r.cStatus = 1
    ";
    $staff_stmt = $pdo->prepare($staff_query);
    $staff_stmt->execute([$staff_id]);
    $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        echo json_encode(['success' => false, 'message' => 'Staff member not found']);
        exit();
    }
    
    // Get rating summary
    $summary_query = "
        SELECT 
            COUNT(*) as total_ratings,
            ROUND(COALESCE(AVG(rating), 0), 2) as average_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1,
            ROUND((SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as satisfaction_rate
        FROM staff_ratings
        WHERE staff_id = ?
    ";
    $summary_stmt = $pdo->prepare($summary_query);
    $summary_stmt->execute([$staff_id]);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all ratings for this staff member
    $ratings_query = "
        SELECT 
            sr.id,
            sr.rating,
            sr.comments,
            sr.service_name,
            sr.created_at,
            u.fName as customer_name,
            u.email as customer_email,
            u.contactNo as customer_phone,
            a.appointment_date,
            a.appointment_time
        FROM staff_ratings sr
        JOIN reg u ON sr.user_id = u.regID
        LEFT JOIN appointments a ON sr.appointment_id = a.id
        WHERE sr.staff_id = ?
        ORDER BY sr.created_at DESC
        LIMIT 50
    ";
    $ratings_stmt = $pdo->prepare($ratings_query);
    $ratings_stmt->execute([$staff_id]);
    $ratings = $ratings_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate completion rate for summary
    $total_appointments_query = "
        SELECT COUNT(*) as total
        FROM appointments
        WHERE staff_id = ? AND status = 'completed'
    ";
    $total_appointments_stmt = $pdo->prepare($total_appointments_query);
    $total_appointments_stmt->execute([$staff_id]);
    $total_appointments = $total_appointments_stmt->fetch(PDO::FETCH_ASSOC);
    
    $completion_rate = $total_appointments['total'] > 0 
        ? round(($summary['total_ratings'] / $total_appointments['total']) * 100, 2)
        : 0;
    
    // Prepare response data
    $response = [
        'success' => true,
        'staff' => [
            'id' => $staff['id'],
            'name' => $staff['staff_name'],
            'specialization' => $staff['specialization'] ?? 'General',
            'experience_years' => $staff['experience_years'] ?? 0,
            'email' => $staff['email']
        ],
        'summary' => [
            'total_ratings' => intval($summary['total_ratings'] ?? 0),
            'average_rating' => floatval($summary['average_rating'] ?? 0),
            'rating_5' => intval($summary['rating_5'] ?? 0),
            'rating_4' => intval($summary['rating_4'] ?? 0),
            'rating_3' => intval($summary['rating_3'] ?? 0),
            'rating_2' => intval($summary['rating_2'] ?? 0),
            'rating_1' => intval($summary['rating_1'] ?? 0),
            'satisfaction_rate' => floatval($summary['satisfaction_rate'] ?? 0),
            'completion_rate' => floatval($completion_rate)
        ],
        'ratings' => []
    ];
    
    // Format ratings data
    foreach ($ratings as $rating) {
        $response['ratings'][] = [
            'id' => $rating['id'],
            'rating' => intval($rating['rating']),
            'comments' => $rating['comments'],
            'service_name' => $rating['service_name'],
            'created_at' => $rating['created_at'],
            'customer_name' => $rating['customer_name'],
            'customer_email' => $rating['customer_email'],
            'customer_phone' => $rating['customer_phone'],
            'appointment_date' => $rating['appointment_date'],
            'appointment_time' => $rating['appointment_time']
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log('Get staff ratings error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('Get staff ratings error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
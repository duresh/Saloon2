<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

try {
    $pdo = getPDOConnection();
    
    // Get service statistics
    $query = "
        SELECT 
            COUNT(*) as total_services,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_services,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_services,
            COUNT(DISTINCT category) as total_categories,
            MIN(price) as min_price,
            MAX(price) as max_price,
            ROUND(AVG(price), 2) as avg_price,
            MIN(duration) as min_duration,
            MAX(duration) as max_duration,
            ROUND(AVG(duration), 0) as avg_duration,
            (SELECT COUNT(*) FROM appointments) as total_bookings,
            (SELECT COUNT(*) FROM appointments WHERE status = 'completed') as completed_bookings
        FROM services
    ";
    
    $stmt = $pdo->query($query);
    $stats = $stmt->fetch();
    
    // Get popular services
    $popular_query = "
        SELECT 
            s.id,
            s.name,
            s.category,
            COUNT(a.id) as booking_count
        FROM services s
        LEFT JOIN appointments a ON s.id = a.service_id
        WHERE s.status = 'active'
        GROUP BY s.id
        ORDER BY booking_count DESC
        LIMIT 5
    ";
    
    $popular_stmt = $pdo->query($popular_query);
    $popular_services = $popular_stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'statistics' => $stats,
        'popular_services' => $popular_services
    ]);
    
} catch (PDOException $e) {
    error_log('Get service statistics error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
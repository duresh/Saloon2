<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if user is staff
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
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
    
    // Get availability for this staff member
    $query = "SELECT day_of_week, start_time, end_time, is_available FROM staff_availability WHERE staff_id = ? ORDER BY day_of_week";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$staff_id]);
    $results = $stmt->fetchAll();
    
    // Initialize with default values for all days
    $availability = [];
    for ($i = 0; $i < 7; $i++) {
        $availability[$i] = [
            'start' => '09:00',
            'end' => '18:00',
            'available' => false // Default to not available unless specified
        ];
    }
    
    // Override with actual data from database
    foreach ($results as $row) {
        $availability[$row['day_of_week']] = [
            'start' => substr($row['start_time'], 0, 5),
            'end' => substr($row['end_time'], 0, 5),
            'available' => (bool)$row['is_available']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'availability' => $availability
    ]);
    
} catch (PDOException $e) {
    error_log('Get staff availability error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
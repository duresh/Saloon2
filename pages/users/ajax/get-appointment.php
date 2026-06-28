<?php
// ajax/get-appointment.php - Get appointment details
session_start();
require_once '../../../includes/dbcon.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['id'])) {
    echo json_encode(['error' => 'Appointment ID required']);
    exit();
}

$appointment_id = intval($_POST['id']);
$user_id = $_SESSION['user_id'];

try {
    $pdo = getPDOConnection();
    
    // Debug: Log the query parameters
    error_log("Fetching appointment ID: " . $appointment_id . " for user: " . $user_id);
    
    $query = "SELECT 
        a.id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        a.created_at,
        s.name as service_name,
        s.price,
        s.duration,
        st.id as staff_id,
        st.specialization as staff_specialization,
        r.fName as staff_name,
        r.lName as staff_lname
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN reg r ON st.user_id = r.regID
        WHERE a.id = ? AND a.user_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment_id, $user_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug: Log the result
    error_log("Query result: " . print_r($appointment, true));
    
    if (!$appointment) {
        echo json_encode(['error' => 'Appointment not found']);
        exit();
    }
    
    // Format the response
    $response = [
        'success' => true,
        'id' => $appointment['id'],
        'service_name' => $appointment['service_name'] ?? 'N/A',
        'date' => date('F d, Y', strtotime($appointment['appointment_date'])),
        'time' => date('h:i A', strtotime($appointment['appointment_time'])),
        'status' => $appointment['status'],
        'price' => $appointment['price'] ? number_format($appointment['price'], 2) : 'N/A',
        'duration' => $appointment['duration'] ?? 'N/A',
        'notes' => $appointment['notes'] ?? '',
        'created_at' => $appointment['created_at'] ? date('F d, Y h:i A', strtotime($appointment['created_at'])) : 'N/A',
        'staff_name' => $appointment['staff_name'] ? $appointment['staff_name'] . ' ' . ($appointment['staff_lname'] ?? '') : null,
        'staff_specialization' => $appointment['staff_specialization'] ?? null
    ];
    
    // Return JSON response
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log('Get appointment error: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Get appointment general error: ' . $e->getMessage());
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>
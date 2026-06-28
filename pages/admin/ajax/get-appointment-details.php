<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if user is staff or admin (both can view appointment details)
$is_staff = isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if (!$is_staff && !$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../../../includes/dbcon.php';

$appointment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

try {
    $pdo = getPDOConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get appointment details with related data
    $query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.price as service_price,
            s.duration as service_duration,
            s.category as service_category,
            s.description as service_description,
            u.fName as customer_name,
            u.email as customer_email,
            u.contactNo as customer_phone,
            u.regID as user_id,
            st.id as staff_id,
            r.fName as staff_name,
            st.specialization as staff_specialization
        FROM appointments a
        INNER JOIN services s ON a.service_id = s.id
        INNER JOIN reg u ON a.user_id = u.regID
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN reg r ON st.user_id = r.regID
        WHERE a.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if ($appointment) {
        // For staff, verify they are assigned to this appointment
        if ($is_staff) {
            // Get staff ID from user_id
            $staff_query = "SELECT id FROM staff WHERE user_id = ?";
            $staff_stmt = $pdo->prepare($staff_query);
            $staff_stmt->execute([$user_id]);
            $staff = $staff_stmt->fetch();
            
            if ($staff && $appointment['staff_id'] != $staff['id']) {
                echo json_encode(['success' => false, 'message' => 'You are not authorized to view this appointment']);
                exit();
            }
        }
        
        // Format the data for JSON response
        $response = [
            'success' => true,
            'appointment' => [
                'id' => $appointment['id'],
                'user_id' => $appointment['user_id'],
                'service_id' => $appointment['service_id'],
                'service_name' => $appointment['service_name'],
                'service_price' => $appointment['service_price'],
                'service_duration' => $appointment['service_duration'],
                'service_category' => $appointment['service_category'],
                'service_description' => $appointment['service_description'],
                'customer_name' => $appointment['customer_name'],
                'customer_email' => $appointment['customer_email'],
                'customer_phone' => $appointment['customer_phone'],
                'appointment_date' => $appointment['appointment_date'],
                'appointment_time' => $appointment['appointment_time'],
                'status' => $appointment['status'],
                'notes' => $appointment['notes'],
                'created_at' => $appointment['created_at'],
                'modified_at' => $appointment['modified_at'] ?? null,
                'staff_id' => $appointment['staff_id'],
                'staff_name' => $appointment['staff_name'],
                'staff_specialization' => $appointment['staff_specialization']
            ]
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    }
    
} catch (PDOException $e) {
    error_log('Get appointment details error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
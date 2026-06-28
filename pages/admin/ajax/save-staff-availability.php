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
$availability_json = isset($_POST['availability']) ? $_POST['availability'] : '';

if (!$staff_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
    exit();
}

if (empty($availability_json)) {
    echo json_encode(['success' => false, 'message' => 'No availability data received']);
    exit();
}

try {
    $pdo = getPDOConnection();
    
    // Decode JSON data
    $availability = json_decode($availability_json, true);
    
    if (!is_array($availability)) {
        echo json_encode(['success' => false, 'message' => 'Invalid availability data format']);
        exit();
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    $updated_count = 0;
    $inserted_count = 0;
    
    foreach ($availability as $day_data) {
        $day = isset($day_data['day']) ? intval($day_data['day']) : -1;
        $available = isset($day_data['available']) ? ($day_data['available'] === true || $day_data['available'] === 'true' || $day_data['available'] == 1) : true;
        $start_time = isset($day_data['start']) ? $day_data['start'] . ':00' : '09:00:00';
        $end_time = isset($day_data['end']) ? $day_data['end'] . ':00' : '18:00:00';
        
        if ($day >= 0 && $day <= 6) {
            // Check if record exists
            $check_query = "SELECT id FROM staff_availability WHERE staff_id = ? AND day_of_week = ?";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->execute([$staff_id, $day]);
            $exists = $check_stmt->fetch();
            
            if ($available) {
                // If available, insert or update the record
                if ($exists) {
                    // Update existing record
                    $update_query = "UPDATE staff_availability SET start_time = ?, end_time = ?, is_available = 1, updated_at = NOW() WHERE staff_id = ? AND day_of_week = ?";
                    $update_stmt = $pdo->prepare($update_query);
                    $update_stmt->execute([$start_time, $end_time, $staff_id, $day]);
                    $updated_count++;
                } else {
                    // Insert new record
                    $insert_query = "INSERT INTO staff_availability (staff_id, day_of_week, start_time, end_time, is_available, created_at) VALUES (?, ?, ?, ?, 1, NOW())";
                    $insert_stmt = $pdo->prepare($insert_query);
                    $insert_stmt->execute([$staff_id, $day, $start_time, $end_time]);
                    $inserted_count++;
                }
            } else {
                // If not available, delete the record
                if ($exists) {
                    $delete_query = "DELETE FROM staff_availability WHERE staff_id = ? AND day_of_week = ?";
                    $delete_stmt = $pdo->prepare($delete_query);
                    $delete_stmt->execute([$staff_id, $day]);
                }
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Verify the save by fetching back the data
    $verify_query = "SELECT day_of_week, start_time, end_time, is_available FROM staff_availability WHERE staff_id = ? ORDER BY day_of_week";
    $verify_stmt = $pdo->prepare($verify_query);
    $verify_stmt->execute([$staff_id]);
    $saved_data = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Availability updated successfully',
        'inserted_count' => $inserted_count,
        'updated_count' => $updated_count,
        'saved_data' => $saved_data
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Save staff availability error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Save staff availability error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
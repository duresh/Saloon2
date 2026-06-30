<?php
// ajax/get-staff-history.php - Get staff appointment history via AJAX
session_start();
require_once '../../../includes/dbcon.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$staff_id = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';
$date_from = isset($_POST['date_from']) ? trim($_POST['date_from']) : '';
$date_to = isset($_POST['date_to']) ? trim($_POST['date_to']) : '';
$search = isset($_POST['search']) ? trim($_POST['search']) : '';

$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

try {
    $pdo = getPDOConnection();
    
    // Build WHERE clause - FIXED: Use a.status to avoid ambiguity
    $where_conditions = ["a.staff_id = " . (int)$staff_id];
    $params = [];
    
    if (!empty($status)) {
        $where_conditions[] = "a.status = ?";
        $params[] = $status;
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "a.appointment_date >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "a.appointment_date <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(s.name LIKE ? OR r.fName LIKE ? OR r.lName LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_sql = implode(" AND ", $where_conditions);
    
    // Get total count - FIXED: Use a.status
    $count_query = "
        SELECT COUNT(*) as total
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN reg r ON a.user_id = r.regID
        WHERE $where_sql
    ";
    $count_stmt = $pdo->prepare($count_query);
    if (!empty($params)) {
        $count_stmt->execute($params);
    } else {
        $count_stmt->execute();
    }
    $total_records = $count_stmt->fetch()['total'] ?? 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;
    
    // Get data - FIXED: Use a.status, a.notes, a.created_at
    $query = "
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.notes,
            a.created_at,
            a.reschedule_count,
            s.name as service_name,
            s.price,
            s.duration,
            r.fName as customer_name,
            r.lName as customer_lname,
            r.contactNo as customer_phone
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN reg r ON a.user_id = r.regID
        WHERE $where_sql
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT " . (int)$records_per_page . " OFFSET " . (int)$offset . "
    ";
    
    $stmt = $pdo->prepare($query);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics - FIXED: Use a.status
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN a.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) as no_show,
            COALESCE(SUM(s.price), 0) as total_revenue
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.staff_id = " . (int)$staff_id . "
    ";
    
    // Apply status filter to stats if provided
    if (!empty($status)) {
        $stats_query .= " AND a.status = '" . addslashes($status) . "'";
    }
    
    $stats_stmt = $pdo->query($stats_query);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stats) {
        $stats = [
            'total' => 0,
            'completed' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'cancelled' => 0,
            'no_show' => 0,
            'total_revenue' => 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $appointments,
        'stats' => $stats,
        'total' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $page
    ]);
    
} catch (PDOException $e) {
    error_log("get-staff-history error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
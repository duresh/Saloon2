<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access');
}

require_once '../../includes/dbcon.php';

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

try {
    $pdo = getPDOConnection();
    $admin_id = $_SESSION['user_id'];
    
    // Build query
    $query = "
        SELECT 
            r.regID as ID,
            r.fName as Name,
            r.email as Email,
            r.contactNo as Phone,
            r.role as Role,
            CASE WHEN r.cStatus = 1 THEN 'Active' ELSE 'Inactive' END as Status,
            s.specialization as Specialization,
            s.experience_years as Experience,
            DATE_FORMAT(r.regDate, '%Y-%m-%d') as Registered_Date,
            (SELECT COUNT(*) FROM appointments WHERE user_id = r.regID) as Total_Appointments,
            (SELECT COUNT(*) FROM appointments WHERE user_id = r.regID AND status = 'completed') as Completed_Appointments
        FROM reg r
        LEFT JOIN staff s ON r.regID = s.user_id
        WHERE r.regID != ?
    ";
    
    $params = [$admin_id];
    
    if (!empty($search)) {
        $query .= " AND (r.fName LIKE ? OR r.email LIKE ? OR r.contactNo LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($role_filter)) {
        $query .= " AND r.role = ?";
        $params[] = $role_filter;
    }
    
    if ($status_filter !== '') {
        $query .= " AND r.cStatus = ?";
        $params[] = $status_filter;
    }
    
    $query .= " ORDER BY r.regDate DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Role', 'Status', 'Specialization', 'Experience', 'Registered Date', 'Total Appointments', 'Completed Appointments']);
    
    // Add data rows
    foreach ($users as $user) {
        fputcsv($output, [
            $user['ID'],
            $user['Name'],
            $user['Email'],
            $user['Phone'],
            $user['Role'],
            $user['Status'],
            $user['Specialization'] ?? 'N/A',
            $user['Experience'] ?? 'N/A',
            $user['Registered_Date'],
            $user['Total_Appointments'],
            $user['Completed_Appointments']
        ]);
    }
    
    fclose($output);
    
} catch (PDOException $e) {
    error_log('Export users error: ' . $e->getMessage());
    die('Error exporting data');
}
?>
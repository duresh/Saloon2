<?php
session_start();
require_once '../../includes/dbcon.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die('Unauthorized');
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getPDOConnection();
    
    $query = "
        SELECT 
            a.appointment_date as Date,
            a.appointment_time as Time,
            s.name as Service,
            s.category as Category,
            s.price as Price,
            s.duration as Duration,
            a.status as Status,
            a.notes as Notes,
            a.created_at as BookedOn
        FROM appointments a
        INNER JOIN services s ON a.service_id = s.id
        WHERE a.user_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $appointments = $stmt->fetchAll();
    
    // Create CSV
    $filename = 'appointments_' . date('Y-m-d_His') . '.csv';
    $filepath = '../../temp/' . $filename;
    
    if (!file_exists('../../temp')) {
        mkdir('../../temp', 0777, true);
    }
    
    $fp = fopen($filepath, 'w');
    
    // Add headers
    fputcsv($fp, array_keys($appointments[0]));
    
    // Add data
    foreach ($appointments as $row) {
        fputcsv($fp, $row);
    }
    
    fclose($fp);
    
    echo json_encode([
        'success' => true,
        'file' => 'temp/' . $filename
    ]);
    
} catch (PDOException $e) {
    error_log('Export appointments error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Export failed']);
}
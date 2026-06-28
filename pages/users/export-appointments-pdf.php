<?php
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die('Unauthorized access');
}

// Include TCPDF
require_once '../../includes/tcpdf/tcpdf.php';
require_once '../../includes/dbcon.php';

$user_id = $_SESSION['user_id'];
$Aid = 0;

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $pdo = getPDOConnection();
    
    // Get user details
    $user_query = "SELECT * FROM reg WHERE regID = ? AND cStatus = 1";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        die('User not found');
    }
    
    // Build query - get actual appointment IDs
    $query = "
        SELECT 
            a.id as appointment_id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.notes,
            a.created_at,
            s.name as service_name,
            s.price as service_price,
            s.duration as service_duration,
            s.category as service_category,
            r.fName as staff_name,
            st.specialization as staff_specialization
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN reg r ON st.user_id = r.regID
        WHERE a.user_id = ?
    ";
    
    $params = [$user_id];
    
    if ($status_filter !== 'all') {
        $query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_filter)) {
        $query .= " AND a.appointment_date = ?";
        $params[] = $date_filter;
    }
    
    if (!empty($search)) {
        $query .= " AND (s.name LIKE ? OR a.notes LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN service_price ELSE 0 END) as total_spent
        FROM (
            SELECT a.status, s.price as service_price
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            WHERE a.user_id = ?
        ) as apt_stats
    ";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch();
    
    // Create PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Salon Elegance');
    $pdf->SetAuthor($user['fName']);
    $pdf->SetTitle('My Appointment History');
    $pdf->SetSubject('Appointment Report');
    
    // Remove header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Title
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(111, 66, 193);
    $pdf->Cell(0, 10, 'Salon Elegance', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'My Appointment History', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Customer Info
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Customer Information', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 6, 'Name:', 0, 0);
    $pdf->Cell(0, 6, $user['fName'], 0, 1);
    $pdf->Cell(50, 6, 'Email:', 0, 0);
    $pdf->Cell(0, 6, $user['email'], 0, 1);
    $pdf->Cell(50, 6, 'Phone:', 0, 0);
    $pdf->Cell(0, 6, $user['contactNo'] ?: 'Not provided', 0, 1);
    $pdf->Ln(5);
    
    // Statistics
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Summary Statistics', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 6, 'Total Appointments:', 0, 0);
    $pdf->Cell(0, 6, $stats['total'] ?? 0, 0, 1);
    $pdf->Cell(60, 6, 'Pending:', 0, 0);
    $pdf->Cell(0, 6, $stats['pending'] ?? 0, 0, 1);
    $pdf->Cell(60, 6, 'Confirmed:', 0, 0);
    $pdf->Cell(0, 6, $stats['confirmed'] ?? 0, 0, 1);
    $pdf->Cell(60, 6, 'Completed:', 0, 0);
    $pdf->Cell(0, 6, $stats['completed'] ?? 0, 0, 1);
    $pdf->Cell(60, 6, 'Cancelled:', 0, 0);
    $pdf->Cell(0, 6, $stats['cancelled'] ?? 0, 0, 1);
    $pdf->Cell(60, 6, 'Total Spent:', 0, 0);
    $pdf->Cell(0, 6, 'Rs: ' . number_format($stats['total_spent'] ?? 0, 2), 0, 1);
    $pdf->Ln(5);
    
    // Filter Information
    $filter_text = [];
    if ($status_filter != 'all') $filter_text[] = 'Status: ' . ucfirst($status_filter);
    if (!empty($date_filter)) $filter_text[] = 'Date: ' . date('d M Y', strtotime($date_filter));
    if (!empty($search)) $filter_text[] = 'Search: ' . $search;
    
    if (!empty($filter_text)) {
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->Cell(0, 6, 'Filters Applied: ' . implode(' | ', $filter_text), 0, 1);
        $pdf->Ln(3);
    }
    
    // Generation date
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Generated on: ' . date('F d, Y H:i:s'), 0, 1);
    $pdf->Ln(5);
    
    // Table Header
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(111, 66, 193);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(20, 10, 'ID', 1, 0, 'C', 1);
    $pdf->Cell(45, 10, 'Service', 1, 0, 'C', 1);
    $pdf->Cell(25, 10, 'Date', 1, 0, 'C', 1);
    $pdf->Cell(20, 10, 'Time', 1, 0, 'C', 1);
    $pdf->Cell(20, 10, 'Duration', 1, 0, 'C', 1);
    $pdf->Cell(25, 10, 'Amount', 1, 0, 'C', 1);
    // $pdf->Cell(30, 10, 'Staff', 1, 0, 'C', 1);
    $pdf->Cell(25, 10, 'Status', 1, 1, 'C', 1);
    
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    
    // Display actual appointment IDs
    foreach ($appointments as $apt) {
        // Actual Appointment ID from database
        $pdf->Cell(20, 8, '#' . ++$Aid, 1, 0, 'C');
        $pdf->Cell(45, 8, substr($apt['service_name'], 0, 30), 1, 0, 'L');
        $pdf->Cell(25, 8, date('d M Y', strtotime($apt['appointment_date'])), 1, 0, 'C');
        $pdf->Cell(20, 8, date('g:i A', strtotime($apt['appointment_time'])), 1, 0, 'C');
        $pdf->Cell(20, 8, $apt['service_duration'] . ' min', 1, 0, 'C');
        $pdf->Cell(25, 8, 'Rs: ' . number_format($apt['service_price'], 2), 1, 0, 'R');
        // $pdf->Cell(30, 8, substr($apt['staff_name'] ?? 'Not Assigned', 0, 20), 1, 0, 'L');
        
        // Status with color
        $status_color = [
            'completed' => [40, 167, 69],
            'cancelled' => [220, 53, 69],
            'pending' => [255, 193, 7],
            'confirmed' => [40, 167, 69]
        ][$apt['status']] ?? [108, 117, 125];
        
        $pdf->SetFillColor($status_color[0], $status_color[1], $status_color[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(25, 8, ucfirst($apt['status']), 1, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);
    }
    
    // Total records
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, 'Total Records: ' . count($appointments), 0, 1, 'R');
    
    // Footer
    $pdf->SetY(-20);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'This is a computer-generated document. No signature required.', 0, 0, 'C');
    
    // Output PDF
    $filename = 'appointments_' . $user['fName'] . '_' . date('Y-m-d') . '.pdf';
    $pdf->Output($filename, 'D');
    
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}
?>
<?php
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Include TCPDF library
require_once '../../includes/tcpdf/tcpdf.php';
require_once '../../includes/dbcon.php';

$user_id = $_SESSION['user_id'];

// Get filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
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
    
    // Build query based on filter type
    $params = [$user_id];
    
    if ($filter_type == 'appointments' || $filter_type == 'all') {
        $query = "
            SELECT 
                'Appointment' as type,
                a.id,
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.notes,
                a.created_at as booked_on,
                s.name as service_name,
                s.price,
                s.duration,
                s.category,
                r.fName as staff_name
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            LEFT JOIN staff st ON a.staff_id = st.id
            LEFT JOIN reg r ON st.user_id = r.regID
            WHERE a.user_id = ? 
            AND a.status IN ('completed', 'cancelled')
        ";
        
        if ($filter_type == 'appointments') {
            $query = "
                SELECT 
                    'Appointment' as type,
                    a.id,
                    a.appointment_date,
                    a.appointment_time,
                    a.status,
                    a.notes,
                    a.created_at as booked_on,
                    s.name as service_name,
                    s.price,
                    s.duration,
                    s.category,
                    r.fName as staff_name
                FROM appointments a
                JOIN services s ON a.service_id = s.id
                LEFT JOIN staff st ON a.staff_id = st.id
                LEFT JOIN reg r ON st.user_id = r.regID
                WHERE a.user_id = ? 
                AND a.status IN ('completed', 'cancelled')
            ";
        }
        
        if (!empty($status_filter)) {
            $query .= " AND a.status = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($date_from)) {
            $query .= " AND a.appointment_date >= ?";
            $params[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $query .= " AND a.appointment_date <= ?";
            $params[] = $date_to;
        }
        
        if (!empty($search)) {
            $query .= " AND (s.name LIKE ? OR a.notes LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    }
    
    if ($filter_type == 'reschedules') {
        $query = "
            SELECT 
                'Reschedule' as type,
                ar.id,
                ar.old_date,
                ar.old_time,
                ar.new_date,
                ar.new_time,
                ar.reschedule_reason,
                ar.rescheduled_at,
                ar.rescheduled_by,
                os.name as old_service_name,
                ns.name as new_service_name,
                ostf.staff_name as old_staff_name,
                nstf.staff_name as new_staff_name
            FROM appointment_reschedules ar
            LEFT JOIN services os ON ar.old_service_id = os.id
            LEFT JOIN services ns ON ar.new_service_id = ns.id
            LEFT JOIN (SELECT s.id, r.fName as staff_name FROM staff s JOIN reg r ON s.user_id = r.regID) ostf ON ar.old_staff_id = ostf.id
            LEFT JOIN (SELECT s.id, r.fName as staff_name FROM staff s JOIN reg r ON s.user_id = r.regID) nstf ON ar.new_staff_id = nstf.id
            WHERE ar.user_id = ?
            ORDER BY ar.rescheduled_at DESC
        ";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // Get statistics
    $stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status = 'completed') as completed,
            (SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status = 'cancelled') as cancelled,
            (SELECT COUNT(*) FROM appointment_reschedules WHERE user_id = ?) as reschedules,
            (SELECT SUM(s.price) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.user_id = ? AND a.status = 'completed') as total_spent
    ";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    $stats = $stats_stmt->fetch();
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Salon Elegance');
    $pdf->SetAuthor($user['fName']);
    $pdf->SetTitle('My Appointment History');
    $pdf->SetSubject('Appointment History Report');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Title
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(111, 66, 193);
    $pdf->Cell(0, 10, 'Salon Elegance', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'Appointment History Report', 0, 1, 'C');
    $pdf->Ln(5);
    
    // User Info
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Customer Information', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 6, 'Name:', 0, 0);
    $pdf->Cell(0, 6, $user['fName'], 0, 1);
    $pdf->Cell(50, 6, 'Email:', 0, 0);
    $pdf->Cell(0, 6, $user['email'], 0, 1);
    $pdf->Cell(50, 6, 'Phone:', 0, 0);
    $pdf->Cell(0, 6, $user['contactNo'] ?: 'Not provided', 0, 1);
    $pdf->Ln(5);
    
    // Summary Statistics
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Summary Statistics', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 6, 'Completed Appointments:', 0, 0);
    $pdf->Cell(0, 6, $stats['completed'] ?? 0, 0, 1);
    $pdf->Cell(60, 6, 'Cancelled Appointments:', 0, 0);
    $pdf->Cell(0, 6, $stats['cancelled'] ?? 0, 0, 1);
    $pdf->Cell(60, 6, 'Reschedules:', 0, 0);
    $pdf->Cell(0, 6, $stats['reschedules'] ?? 0, 0, 1);
    $pdf->Cell(60, 6, 'Total Spent:', 0, 0);
    $pdf->Cell(0, 6, 'Rs: ' . number_format($stats['total_spent'] ?? 0, 2), 0, 1);
    $pdf->Ln(5);
    
    // Filter Information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Report Filters', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    $filter_text = [];
    if ($filter_type != 'all') $filter_text[] = 'Type: ' . ucfirst($filter_type);
    if (!empty($status_filter)) $filter_text[] = 'Status: ' . ucfirst($status_filter);
    if (!empty($date_from)) $filter_text[] = 'From: ' . date('d M Y', strtotime($date_from));
    if (!empty($date_to)) $filter_text[] = 'To: ' . date('d M Y', strtotime($date_to));
    if (!empty($search)) $filter_text[] = 'Search: ' . $search;
    
    $pdf->Cell(0, 6, implode(' | ', $filter_text) ?: 'All Records', 0, 1);
    $pdf->Ln(5);
    
    // Report Generation Date
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Generated on: ' . date('F d, Y H:i:s'), 0, 1);
    $pdf->Ln(5);
    
    // Table Header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(111, 66, 193);
    $pdf->SetTextColor(255, 255, 255);
    
    if ($filter_type == 'reschedules') {
        // Reschedule Table
        $pdf->Cell(25, 10, 'Date', 1, 0, 'C', 1);
        $pdf->Cell(25, 10, 'Old Date', 1, 0, 'C', 1);
        $pdf->Cell(25, 10, 'New Date', 1, 0, 'C', 1);
        $pdf->Cell(35, 10, 'Old Service', 1, 0, 'C', 1);
        $pdf->Cell(35, 10, 'New Service', 1, 0, 'C', 1);
        $pdf->Cell(30, 10, 'Reason', 1, 1, 'C', 1);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        
        foreach ($records as $record) {
            $pdf->Cell(25, 8, date('d M Y', strtotime($record['rescheduled_at'])), 1);
            $pdf->Cell(25, 8, date('d M Y', strtotime($record['old_date'])) . ' ' . date('g:i A', strtotime($record['old_time'])), 1);
            $pdf->Cell(25, 8, date('d M Y', strtotime($record['new_date'])) . ' ' . date('g:i A', strtotime($record['new_time'])), 1);
            $pdf->Cell(35, 8, substr($record['old_service_name'] ?? 'N/A', 0, 25), 1);
            $pdf->Cell(35, 8, substr($record['new_service_name'] ?? 'N/A', 0, 25), 1);
            $pdf->Cell(30, 8, substr(str_replace('_', ' ', $record['reschedule_reason'] ?? 'N/A'), 0, 20), 1, 1);
        }
    } else {
        // Appointment Table
        $pdf->Cell(20, 10, 'ID', 1, 0, 'C', 1);
        $pdf->Cell(30, 10, 'Date', 1, 0, 'C', 1);
        $pdf->Cell(25, 10, 'Time', 1, 0, 'C', 1);
        $pdf->Cell(35, 10, 'Service', 1, 0, 'C', 1);
        $pdf->Cell(20, 10, 'Duration', 1, 0, 'C', 1);
        $pdf->Cell(25, 10, 'Amount', 1, 0, 'C', 1);
        $pdf->Cell(25, 10, 'Staff', 1, 0, 'C', 1);
        $pdf->Cell(20, 10, 'Status', 1, 1, 'C', 1);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        
        foreach ($records as $record) {
            $pdf->Cell(20, 8, $record['id'], 1, 0, 'C');
            $pdf->Cell(30, 8, date('d M Y', strtotime($record['appointment_date'])), 1, 0, 'C');
            $pdf->Cell(25, 8, date('g:i A', strtotime($record['appointment_time'])), 1, 0, 'C');
            $pdf->Cell(35, 8, substr($record['service_name'], 0, 25), 1, 0, 'L');
            $pdf->Cell(20, 8, $record['duration'] . ' min', 1, 0, 'C');
            $pdf->Cell(25, 8, 'Rs: ' . number_format($record['price'], 2), 1, 0, 'R');
            $pdf->Cell(25, 8, substr($record['staff_name'] ?? 'N/A', 0, 15), 1, 0, 'L');
            
            // Status with color
            $status_color = $record['status'] == 'completed' ? [40, 167, 69] : [220, 53, 69];
            $pdf->SetFillColor($status_color[0], $status_color[1], $status_color[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(20, 8, ucfirst($record['status']), 1, 1, 'C', 1);
            $pdf->SetTextColor(0, 0, 0);
        }
    }
    
    // Total Records
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, 'Total Records: ' . count($records), 0, 1, 'R');
    
    // Footer Note
    $pdf->SetY(-20);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'This is a computer-generated document. No signature required.', 0, 0, 'C');
    
    // Close and output PDF document
    $filename = 'history_' . $user['fName'] . '_' . date('Y-m-d') . '.pdf';
    $pdf->Output($filename, 'D');
    
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}
?>
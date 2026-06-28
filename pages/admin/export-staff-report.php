<?php
ob_start();
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../../includes/dbcon.php';
require_once '../../includes/tcpdf/tcpdf.php';

$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'pdf'; // pdf or csv

if (!$staff_id) {
    die('Invalid staff ID');
}

try {
    $pdo = getPDOConnection();
    
    // Get staff details
    $staff_query = "
        SELECT 
            s.*,
            r.fName as staff_name,
            r.email,
            r.contactNo,
            r.regDate
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE s.id = ? AND r.cStatus = 1
    ";
    $staff_stmt = $pdo->prepare($staff_query);
    $staff_stmt->execute([$staff_id]);
    $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        die('Staff member not found');
    }
    
    // Get performance summary
    $summary_query = "
        SELECT 
            COUNT(DISTINCT a.id) as total_appointments,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
            SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
            SUM(CASE WHEN a.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_appointments,
            COALESCE(SUM(CASE WHEN a.status = 'completed' THEN srv.price ELSE 0 END), 0) as total_revenue,
            COUNT(DISTINCT sr.id) as total_ratings,
            ROUND(COALESCE(AVG(sr.rating), 0), 2) as avg_rating,
            COUNT(DISTINCT u.regID) as unique_customers
        FROM staff st
        LEFT JOIN appointments a ON st.id = a.staff_id
        LEFT JOIN services srv ON a.service_id = srv.id
        LEFT JOIN staff_ratings sr ON st.id = sr.staff_id
        LEFT JOIN reg u ON a.user_id = u.regID
        WHERE st.id = ?
    ";
    $summary_stmt = $pdo->prepare($summary_query);
    $summary_stmt->execute([$staff_id]);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate completion rate
    $completion_rate = $summary['total_appointments'] > 0 
        ? round(($summary['completed_appointments'] / $summary['total_appointments']) * 100, 2)
        : 0;
    
    // Get monthly performance data
    $monthly_query = "
        SELECT 
            DATE_FORMAT(a.appointment_date, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
            COALESCE(SUM(srv.price), 0) as revenue
        FROM appointments a
        JOIN services srv ON a.service_id = srv.id
        WHERE a.staff_id = ?
        AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(a.appointment_date, '%Y-%m')
        ORDER BY month DESC
    ";
    $monthly_stmt = $pdo->prepare($monthly_query);
    $monthly_stmt->execute([$staff_id]);
    $monthly_data = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent appointments
    $appointments_query = "
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.notes,
            srv.name as service_name,
            srv.price,
            u.fName as customer_name,
            u.email as customer_email,
            u.contactNo as customer_phone
        FROM appointments a
        JOIN services srv ON a.service_id = srv.id
        JOIN reg u ON a.user_id = u.regID
        WHERE a.staff_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 50
    ";
    $appointments_stmt = $pdo->prepare($appointments_query);
    $appointments_stmt->execute([$staff_id]);
    $appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get ratings
    $ratings_query = "
        SELECT 
            sr.rating,
            sr.comments,
            sr.created_at,
            u.fName as customer_name
        FROM staff_ratings sr
        JOIN reg u ON sr.user_id = u.regID
        WHERE sr.staff_id = ?
        ORDER BY sr.created_at DESC
        LIMIT 20
    ";
    $ratings_stmt = $pdo->prepare($ratings_query);
    $ratings_stmt->execute([$staff_id]);
    $ratings = $ratings_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format == 'csv') {
        exportCSV($staff, $summary, $completion_rate, $monthly_data, $appointments, $ratings);
    } else {
        exportPDF($staff, $summary, $completion_rate, $monthly_data, $appointments, $ratings);
    }
    
} catch (PDOException $e) {
    error_log('Export staff report error: ' . $e->getMessage());
    die('Error generating report: ' . $e->getMessage());
}

function exportPDF($staff, $summary, $completion_rate, $monthly_data, $appointments, $ratings) {
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Salon Elegance');
    $pdf->SetAuthor($staff['staff_name']);
    $pdf->SetTitle('Staff Performance Report - ' . $staff['staff_name']);
    $pdf->SetSubject('Staff Performance Report');
    
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
    $pdf->Cell(0, 5, 'Staff Performance Report', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Staff Information
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(111, 66, 193);
    $pdf->Cell(0, 8, 'Staff Information', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->Cell(50, 6, 'Name:', 0, 0);
    $pdf->Cell(0, 6, $staff['staff_name'], 0, 1);
    $pdf->Cell(50, 6, 'Email:', 0, 0);
    $pdf->Cell(0, 6, $staff['email'], 0, 1);
    $pdf->Cell(50, 6, 'Phone:', 0, 0);
    $pdf->Cell(0, 6, $staff['contactNo'], 0, 1);
    $pdf->Cell(50, 6, 'Specialization:', 0, 0);
    $pdf->Cell(0, 6, $staff['specialization'] ?? 'General', 0, 1);
    $pdf->Cell(50, 6, 'Experience:', 0, 0);
    $pdf->Cell(0, 6, ($staff['experience_years'] ?? 0) . ' years', 0, 1);
    $pdf->Cell(50, 6, 'Joined:', 0, 0);
    $pdf->Cell(0, 6, date('F d, Y', strtotime($staff['joining_date'] ?? $staff['regDate'])), 0, 1);
    $pdf->Ln(5);
    
    // Performance Summary
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(111, 66, 193);
    $pdf->Cell(0, 8, 'Performance Summary', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->Cell(70, 6, 'Total Appointments:', 0, 0);
    $pdf->Cell(0, 6, $summary['total_appointments'] ?? 0, 0, 1);
    $pdf->Cell(70, 6, 'Completed Appointments:', 0, 0);
    $pdf->Cell(0, 6, $summary['completed_appointments'] ?? 0, 0, 1);
    $pdf->Cell(70, 6, 'Cancelled Appointments:', 0, 0);
    $pdf->Cell(0, 6, $summary['cancelled_appointments'] ?? 0, 0, 1);
    $pdf->Cell(70, 6, 'Completion Rate:', 0, 0);
    $pdf->Cell(0, 6, $completion_rate . '%', 0, 1);
    $pdf->Cell(70, 6, 'Total Revenue:', 0, 0);
    $pdf->Cell(0, 6, 'Rs: ' . number_format($summary['total_revenue'] ?? 0, 2), 0, 1);
    $pdf->Cell(70, 6, 'Unique Customers:', 0, 0);
    $pdf->Cell(0, 6, $summary['unique_customers'] ?? 0, 0, 1);
    $pdf->Cell(70, 6, 'Average Rating:', 0, 0);
    $pdf->Cell(0, 6, ($summary['avg_rating'] ?? 0) . ' / 5.0', 0, 1);
    $pdf->Ln(5);
    
    // Monthly Performance
    if (!empty($monthly_data)) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(111, 66, 193);
        $pdf->Cell(0, 8, 'Monthly Performance (Last 12 Months)', 0, 1);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(111, 66, 193);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(40, 8, 'Month', 1, 0, 'C', 1);
        $pdf->Cell(40, 8, 'Appointments', 1, 0, 'C', 1);
        $pdf->Cell(40, 8, 'Completed', 1, 0, 'C', 1);
        $pdf->Cell(50, 8, 'Revenue (Rs)', 1, 1, 'C', 1);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        
        foreach ($monthly_data as $month) {
            $month_name = date('F Y', strtotime($month['month'] . '-01'));
            $pdf->Cell(40, 7, $month_name, 1);
            $pdf->Cell(40, 7, $month['total'], 1, 0, 'C');
            $pdf->Cell(40, 7, $month['completed'], 1, 0, 'C');
            $pdf->Cell(50, 7, number_format($month['revenue'], 2), 1, 1, 'R');
        }
        $pdf->Ln(5);
    }
    
    // Recent Appointments
    if (!empty($appointments)) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(111, 66, 193);
        $pdf->Cell(0, 8, 'Recent Appointments (Last 50)', 0, 1);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(111, 66, 193);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(15, 7, 'ID', 1, 0, 'C', 1);
        $pdf->Cell(25, 7, 'Date', 1, 0, 'C', 1);
        $pdf->Cell(20, 7, 'Time', 1, 0, 'C', 1);
        $pdf->Cell(40, 7, 'Customer', 1, 0, 'C', 1);
        $pdf->Cell(35, 7, 'Service', 1, 0, 'C', 1);
        $pdf->Cell(20, 7, 'Amount', 1, 0, 'C', 1);
        $pdf->Cell(25, 7, 'Status', 1, 1, 'C', 1);
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        
        foreach ($appointments as $apt) {
            $pdf->Cell(15, 6, $apt['id'], 1, 0, 'C');
            $pdf->Cell(25, 6, date('M d, Y', strtotime($apt['appointment_date'])), 1);
            $pdf->Cell(20, 6, date('g:i A', strtotime($apt['appointment_time'])), 1);
            $pdf->Cell(40, 6, substr($apt['customer_name'], 0, 20), 1);
            $pdf->Cell(35, 6, substr($apt['service_name'], 0, 20), 1);
            $pdf->Cell(20, 6, number_format($apt['price'], 2), 1, 0, 'R');
            
            // Status with color
            $status_color = [
                'completed' => [40, 167, 69],
                'cancelled' => [220, 53, 69],
                'confirmed' => [40, 167, 69],
                'pending' => [255, 193, 7]
            ][$apt['status']] ?? [108, 117, 125];
            
            $pdf->SetFillColor($status_color[0], $status_color[1], $status_color[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(25, 6, ucfirst($apt['status']), 1, 1, 'C', 1);
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->Ln(5);
    }
    
    // Customer Ratings
    if (!empty($ratings)) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(111, 66, 193);
        $pdf->Cell(0, 8, 'Customer Ratings', 0, 1);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(111, 66, 193);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(20, 7, 'Rating', 1, 0, 'C', 1);
        $pdf->Cell(35, 7, 'Customer', 1, 0, 'C', 1);
        $pdf->Cell(30, 7, 'Date', 1, 0, 'C', 1);
        $pdf->Cell(0, 7, 'Comments', 1, 1, 'C', 1);
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        
        foreach ($ratings as $rating) {
            // Rating stars
            $stars = '';
            for ($i = 1; $i <= 5; $i++) {
                $stars .= $i <= $rating['rating'] ? '★' : '☆';
            }
            
            $pdf->Cell(20, 6, $stars, 1, 0, 'C');
            $pdf->Cell(35, 6, substr($rating['customer_name'], 0, 20), 1);
            $pdf->Cell(30, 6, date('M d, Y', strtotime($rating['created_at'])), 1);
            $pdf->Cell(0, 6, substr($rating['comments'] ?? 'No comments', 0, 60), 1, 1);
        }
        $pdf->Ln(5);
    }
    
    // Footer
    $pdf->SetY(-20);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'Report generated on: ' . date('F d, Y H:i:s'), 0, 0, 'C');
    
    // Output PDF
    $filename = 'staff_report_' . $staff['staff_name'] . '_' . date('Y-m-d') . '.pdf';
    $pdf->Output($filename, 'D');
}

function exportCSV($staff, $summary, $completion_rate, $monthly_data, $appointments, $ratings) {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="staff_report_' . $staff['staff_name'] . '_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Staff Information
    fputcsv($output, ['STAFF PERFORMANCE REPORT']);
    fputcsv($output, ['']);
    fputcsv($output, ['STAFF INFORMATION']);
    fputcsv($output, ['Name', $staff['staff_name']]);
    fputcsv($output, ['Email', $staff['email']]);
    fputcsv($output, ['Phone', $staff['contactNo']]);
    fputcsv($output, ['Specialization', $staff['specialization'] ?? 'General']);
    fputcsv($output, ['Experience', ($staff['experience_years'] ?? 0) . ' years']);
    fputcsv($output, ['Joined', date('F d, Y', strtotime($staff['joining_date'] ?? $staff['regDate']))]);
    fputcsv($output, ['']);
    
    // Performance Summary
    fputcsv($output, ['PERFORMANCE SUMMARY']);
    fputcsv($output, ['Total Appointments', $summary['total_appointments'] ?? 0]);
    fputcsv($output, ['Completed Appointments', $summary['completed_appointments'] ?? 0]);
    fputcsv($output, ['Cancelled Appointments', $summary['cancelled_appointments'] ?? 0]);
    fputcsv($output, ['Completion Rate', $completion_rate . '%']);
    fputcsv($output, ['Total Revenue', 'Rs: ' . number_format($summary['total_revenue'] ?? 0, 2)]);
    fputcsv($output, ['Unique Customers', $summary['unique_customers'] ?? 0]);
    fputcsv($output, ['Average Rating', ($summary['avg_rating'] ?? 0) . ' / 5.0']);
    fputcsv($output, ['']);
    
    // Monthly Performance
    if (!empty($monthly_data)) {
        fputcsv($output, ['MONTHLY PERFORMANCE']);
        fputcsv($output, ['Month', 'Appointments', 'Completed', 'Revenue (Rs)']);
        foreach ($monthly_data as $month) {
            $month_name = date('F Y', strtotime($month['month'] . '-01'));
            fputcsv($output, [$month_name, $month['total'], $month['completed'], number_format($month['revenue'], 2)]);
        }
        fputcsv($output, ['']);
    }
    
    // Recent Appointments
    if (!empty($appointments)) {
        fputcsv($output, ['RECENT APPOINTMENTS']);
        fputcsv($output, ['ID', 'Date', 'Time', 'Customer', 'Service', 'Amount', 'Status']);
        foreach ($appointments as $apt) {
            fputcsv($output, [
                $apt['id'],
                date('Y-m-d', strtotime($apt['appointment_date'])),
                $apt['appointment_time'],
                $apt['customer_name'],
                $apt['service_name'],
                number_format($apt['price'], 2),
                $apt['status']
            ]);
        }
        fputcsv($output, ['']);
    }
    
    // Ratings
    if (!empty($ratings)) {
        fputcsv($output, ['CUSTOMER RATINGS']);
        fputcsv($output, ['Rating', 'Customer', 'Date', 'Comments']);
        foreach ($ratings as $rating) {
            $stars = '';
            for ($i = 1; $i <= 5; $i++) {
                $stars .= $i <= $rating['rating'] ? '★' : '☆';
            }
            fputcsv($output, [
                $stars . ' (' . $rating['rating'] . '/5)',
                $rating['customer_name'],
                date('Y-m-d', strtotime($rating['created_at'])),
                $rating['comments'] ?? ''
            ]);
        }
    }
    
    fclose($output);
}
?>
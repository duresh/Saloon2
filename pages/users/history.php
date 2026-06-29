<?php
// history.php - User History Page
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Check if user is admin (redirect to admin dashboard)
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    header('Location: ../admin/dashboard.php');
    exit();
}

// Check if user is staff member (redirect to staff-dashboard)
if (isset($_SESSION['role']) && $_SESSION['role'] == 'staff') {
    header('Location: ../admin/staff-dashboard.php');
    exit();
}

// Include database connection
require_once '../../includes/dbcon.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Filter variables
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Initialize variables
$history_records = [];
$total_records = 0;
$total_pages = 1;
$monthly_data = [];
$stats = [
    'total_completed' => 0,
    'total_cancelled' => 0,
    'total_appointments' => 0,
    'total_reschedules' => 0,
    'total_spent' => 0,
    'avg_spent' => 0,
    'upcoming' => 0
];

try {
    $pdo = getPDOConnection();
    
    // Get user details
    $user_query = "SELECT * FROM reg WHERE regID = ? AND cStatus = 1";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
    
    // Get user statistics
    $stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status = 'completed') as total_completed,
            (SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status = 'cancelled') as total_cancelled,
            (SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status IN ('completed', 'cancelled')) as total_appointments,
            (SELECT COUNT(*) FROM appointment_reschedules WHERE user_id = ?) as total_reschedules,
            (SELECT COALESCE(SUM(s.price), 0) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.user_id = ? AND a.status = 'completed') as total_spent,
            (SELECT COALESCE(AVG(s.price), 0) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.user_id = ? AND a.status = 'completed') as avg_spent,
            (SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status IN ('pending', 'confirmed') AND appointment_date >= CURDATE()) as upcoming
    ";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $stats = $stats_stmt->fetch();
    
    // Get appointments history (completed and cancelled)
    $appointments_query = "
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
            s.category,
            st.id as staff_id,
            r.fName as staff_name,
            r.lName as staff_lname,
            'appointment' as record_type
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN reg r ON st.user_id = r.regID
        WHERE a.user_id = ? 
        AND a.status IN ('completed', 'cancelled')
    ";
    
    $params = [$user_id];
    
    // Add filters for appointments
    if (!empty($status_filter)) {
        $appointments_query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_from)) {
        $appointments_query .= " AND a.appointment_date >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $appointments_query .= " AND a.appointment_date <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search)) {
        $appointments_query .= " AND (s.name LIKE ? OR a.notes LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $appointments_query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    
    // Get reschedule history separately
    $reschedules_query = "
        SELECT 
            ar.id,
            ar.old_date,
            ar.new_date,
            ar.old_time,
            ar.new_time,
            ar.reschedule_reason,
            ar.rescheduled_by,
            ar.rescheduled_at,
            'reschedule' as record_type
        FROM appointment_reschedules ar
        WHERE ar.user_id = ?
    ";
    
    $reschedule_params = [$user_id];
    
    if (!empty($date_from)) {
        $reschedules_query .= " AND ar.rescheduled_at >= ?";
        $reschedule_params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $reschedules_query .= " AND ar.rescheduled_at <= ?";
        $reschedule_params[] = $date_to;
    }
    
    if (!empty($search)) {
        $reschedules_query .= " AND ar.reschedule_reason LIKE ?";
        $reschedule_params[] = "%$search%";
    }
    
    $reschedules_query .= " ORDER BY ar.rescheduled_at DESC";
    
    // Get appointments
    $appointments_stmt = $pdo->prepare($appointments_query);
    $appointments_stmt->execute($params);
    $appointments_data = $appointments_stmt->fetchAll();
    
    // Get reschedules
    $reschedules_stmt = $pdo->prepare($reschedules_query);
    $reschedules_stmt->execute($reschedule_params);
    $reschedules_data = $reschedules_stmt->fetchAll();
    
    // Merge and sort records based on filter type
    if ($filter_type == 'all') {
        $history_records = array_merge($appointments_data, $reschedules_data);
        // Sort by date (most recent first)
        usort($history_records, function($a, $b) {
            $date_a = $a['record_type'] == 'appointment' ? $a['appointment_date'] : $a['rescheduled_at'];
            $date_b = $b['record_type'] == 'appointment' ? $b['appointment_date'] : $b['rescheduled_at'];
            return strtotime($date_b) - strtotime($date_a);
        });
        $total_records = count($history_records);
        $total_pages = ceil($total_records / $records_per_page);
        // Apply pagination
        $history_records = array_slice($history_records, $offset, $records_per_page);
    } elseif ($filter_type == 'appointments') {
        $history_records = $appointments_data;
        $total_records = count($history_records);
        $total_pages = ceil($total_records / $records_per_page);
        $history_records = array_slice($history_records, $offset, $records_per_page);
    } elseif ($filter_type == 'reschedules') {
        $history_records = $reschedules_data;
        $total_records = count($history_records);
        $total_pages = ceil($total_records / $records_per_page);
        $history_records = array_slice($history_records, $offset, $records_per_page);
    }
    
    // Get monthly summary for chart
    $monthly_query = "
        SELECT 
            DATE_FORMAT(appointment_date, '%Y-%m') as month,
            COUNT(*) as count,
            COALESCE(SUM(s.price), 0) as total
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.user_id = ? 
        AND a.status = 'completed'
        AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ";
    $monthly_stmt = $pdo->prepare($monthly_query);
    $monthly_stmt->execute([$user_id]);
    $monthly_data = $monthly_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('History page error: ' . $e->getMessage());
    $error = 'Unable to load history. Please try again later.';
    $history_records = [];
    $total_records = 0;
    $total_pages = 1;
    $monthly_data = [];
} catch (Exception $e) {
    error_log('History page general error: ' . $e->getMessage());
    $error = 'Unable to load history. Please try again later.';
    $history_records = [];
    $total_records = 0;
    $total_pages = 1;
    $monthly_data = [];
}

include 'header/header.php';
?>

<style>
/* History Page Styles */
.history-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border-left: 4px solid var(--primary-color);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-card .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.stat-card .stat-details {
    flex: 1;
    min-width: 0;
}

.stat-card .stat-value {
    font-size: 20px;
    font-weight: 700;
    line-height: 1.2;
    color: #333;
}

.stat-card .stat-label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 2px;
}

/* Filter Section */
.filter-card {
    background: white;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.filter-card .card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    border-radius: 12px 12px 0 0;
}

.filter-card .card-header h5 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.filter-card .card-body {
    padding: 20px;
}

/* Chart Section */
.chart-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.chart-card .card-header {
    padding: 0 0 15px 0;
    border-bottom: 1px solid #e9ecef;
}

.chart-card .card-header h5 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.chart-container {
    height: 280px;
    margin-top: 15px;
}

/* Records Section */
.records-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.records-card .card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.records-card .card-header h5 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.records-card .card-header .badge {
    font-size: 13px;
    padding: 5px 12px;
}

/* Timeline */
.timeline {
    padding: 25px 25px 10px;
}

.timeline-item {
    position: relative;
    padding-left: 110px;
    padding-bottom: 30px;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 45px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #6f42c1, #e9ecef);
}

.timeline-item:last-child::before {
    bottom: 50%;
}

.timeline-item .timeline-date {
    position: absolute;
    left: 0;
    top: 0;
    width: 85px;
    background: white;
    border-radius: 10px;
    text-align: center;
    padding: 8px 10px;
    border: 1px solid #e9ecef;
    z-index: 1;
}

.timeline-item .timeline-date .day {
    font-size: 22px;
    font-weight: 700;
    color: #6f42c1;
    line-height: 1;
}

.timeline-item .timeline-date .month {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    display: block;
}

.timeline-item .timeline-date .year {
    font-size: 10px;
    color: #adb5bd;
}

.timeline-item .timeline-content {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 18px 20px;
    border-left: 4px solid #6f42c1;
    transition: all 0.3s ease;
}

.timeline-item .timeline-content:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.timeline-item.completed .timeline-content { border-left-color: #28a745; }
.timeline-item.cancelled .timeline-content { border-left-color: #dc3545; }
.timeline-item.reschedule .timeline-content { border-left-color: #ffc107; }

.timeline-item .timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 8px;
}

.timeline-item .timeline-header .header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.timeline-item .timeline-header .header-left i {
    font-size: 16px;
    color: #6f42c1;
}

.timeline-item .timeline-header .header-left strong {
    font-size: 15px;
    color: #333;
}

.timeline-item .status-badge {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.timeline-item .status-badge.completed { background: #d4edda; color: #155724; }
.timeline-item .status-badge.cancelled { background: #f8d7da; color: #721c24; }
.timeline-item .status-badge.reschedule { background: #fff3cd; color: #856404; }

.timeline-item .timeline-details {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 8px;
    margin-bottom: 12px;
}

.timeline-item .detail-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #495057;
}

.timeline-item .detail-row i {
    width: 18px;
    color: #6f42c1;
    font-size: 14px;
}

.timeline-item .detail-row .price {
    font-weight: 700;
    color: #6f42c1;
}

.timeline-item .detail-row.change {
    background: #fff3cd;
    padding: 4px 10px;
    border-radius: 6px;
}

.timeline-item .detail-row .old-value {
    color: #dc3545;
    text-decoration: line-through;
}

.timeline-item .detail-row .new-value {
    color: #28a745;
    font-weight: 500;
}

.timeline-item .timeline-notes {
    background: white;
    padding: 10px 12px;
    border-radius: 6px;
    margin-bottom: 12px;
    font-size: 13px;
    color: #6c757d;
    border-left: 3px solid #6f42c1;
}

.timeline-item .timeline-notes i {
    margin-right: 8px;
    color: #6f42c1;
}

.timeline-item .timeline-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding-top: 10px;
    border-top: 1px solid #e9ecef;
}

.timeline-item .timeline-footer small {
    font-size: 12px;
}

.timeline-item .btn-view-details {
    background: none;
    border: none;
    color: #6f42c1;
    cursor: pointer;
    font-size: 13px;
    padding: 4px 12px;
    border-radius: 6px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.timeline-item .btn-view-details:hover {
    background: #6f42c1;
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    opacity: 0.5;
}

.empty-state h5 {
    color: #333;
    margin-top: 15px;
}

/* Pagination */
.pagination-wrapper {
    padding: 20px;
    border-top: 1px solid #e9ecef;
}

.pagination-wrapper .pagination {
    margin: 0;
}

.pagination-wrapper .page-link {
    color: #6f42c1;
    border: none;
    margin: 0 2px;
    border-radius: 8px !important;
    padding: 8px 14px;
}

.pagination-wrapper .page-item.active .page-link {
    background: #6f42c1;
    color: white;
}

.pagination-wrapper .page-link:hover {
    background: #f3e8ff;
}

/* Details View Modal */
.details-view .detail-row {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.details-view .detail-row:last-child {
    border-bottom: none;
}

.details-view .detail-row .label {
    width: 100px;
    font-weight: 600;
    color: #6c757d;
    flex-shrink: 0;
}

.details-view .detail-row .value {
    flex: 1;
    color: #495057;
}

.details-view .detail-row .value.price {
    color: #6f42c1;
    font-weight: 700;
}

/* Auto-hide alert */
.alert-auto-hide {
    animation: slideDown 0.5s ease forwards;
}

@keyframes slideDown {
    0% { opacity: 1; transform: translateY(0); }
    100% { opacity: 0; transform: translateY(-20px); display: none; }
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .stat-card {
        padding: 12px 15px;
    }
    
    .stat-card .stat-value {
        font-size: 18px;
    }
    
    .timeline {
        padding: 15px 15px 5px;
    }
    
    .timeline-item {
        padding-left: 0;
        padding-bottom: 25px;
    }
    
    .timeline-item::before {
        display: none;
    }
    
    .timeline-item .timeline-date {
        position: relative;
        width: auto;
        display: inline-block;
        margin-bottom: 10px;
        background: #f8f9fa;
        padding: 5px 15px;
    }
    
    .timeline-item .timeline-date .day,
    .timeline-item .timeline-date .month,
    .timeline-item .timeline-date .year {
        display: inline;
        margin-right: 5px;
    }
    
    .timeline-item .timeline-date .day {
        font-size: 16px;
    }
    
    .timeline-item .timeline-details {
        grid-template-columns: 1fr;
    }
    
    .timeline-item .timeline-footer {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    
    .filter-card .card-body .row > div {
        margin-bottom: 10px;
    }
    
    .records-card .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .chart-container {
        height: 200px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    
    .stat-card {
        padding: 10px;
    }
    
    .stat-card .stat-icon {
        width: 36px;
        height: 36px;
        font-size: 16px;
    }
    
    .stat-card .stat-value {
        font-size: 16px;
    }
    
    .stat-card .stat-label {
        font-size: 10px;
    }
}
</style>

<div class="container-fluid">
    <div class="history-container">
        <!-- Page Header -->
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="welcome-text">
                        <h1><i class="fas fa-history me-2"></i> My History</h1>
                        <p class="lead mb-0">View your appointment history, transactions, and activity</p>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-outline-secondary me-2" onclick="location.href='dashboard.php'">
                        <i class="fas fa-arrow-left me-2"></i>Dashboard
                    </button>
                    <button class="btn btn-primary" onclick="exportHistory()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                </div>
            </div>
        </div>

        <!-- Error Message - Only shown when there's an error -->
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show alert-auto-hide" role="alert" id="errorAlert" style="display: block;">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(40, 167, 69, 0.12); color: #28a745;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['total_completed'] ?? 0); ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(220, 53, 69, 0.12); color: #dc3545;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['total_cancelled'] ?? 0); ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.12); color: #ffc107;">
                    <i class="fas fa-redo-alt"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['total_reschedules'] ?? 0); ?></div>
                    <div class="stat-label">Reschedules</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(111, 66, 193, 0.12); color: #6f42c1;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['upcoming'] ?? 0); ?></div>
                    <div class="stat-label">Upcoming</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.12); color: #ffc107;">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value">Rs: <?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(23, 162, 184, 0.12); color: #17a2b8;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value">Rs: <?php echo number_format($stats['avg_spent'] ?? 0, 2); ?></div>
                    <div class="stat-label">Average Spend</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card">
            <div class="card-header">
                <h5><i class="fas fa-filter me-2"></i> Filter History</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filterForm">
                    <div class="row">
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-bold">Record Type</label>
                            <select class="form-select" name="type" id="recordType">
                                <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>All Records</option>
                                <option value="appointments" <?php echo $filter_type == 'appointments' ? 'selected' : ''; ?>>Appointments</option>
                                <option value="reschedules" <?php echo $filter_type == 'reschedules' ? 'selected' : ''; ?>>Reschedules</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" name="status" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-bold">From Date</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-bold">To Date</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Search</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Service name..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-1 mb-3 d-flex align-items-end">
                            <a href="history.php" class="btn btn-outline-secondary w-100" title="Clear Filters">
                                <i class="fas fa-undo"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Monthly Summary Chart -->
        <?php if (!empty($monthly_data)): ?>
        <div class="chart-card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i> Monthly Spending Summary</h5>
            </div>
            <div class="chart-container">
                <canvas id="spendingChart" height="280"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- History Records -->
        <div class="records-card">
            <div class="card-header">
                <h5><i class="fas fa-list me-2"></i> History Records</h5>
                <span class="badge bg-info"><?php echo $total_records; ?> Records Found</span>
            </div>
            
            <div class="timeline">
                <?php if (empty($history_records)): ?>
                <div class="empty-state">
                    <i class="fas fa-history fa-4x text-muted mb-3"></i>
                    <h5>No History Records Found</h5>
                    <p class="text-muted">Try adjusting your filters or book your first appointment.</p>
                    <a href="book-appointment.php" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Book Appointment
                    </a>
                </div>
                <?php else: ?>
                    <?php foreach ($history_records as $record): ?>
                        <?php if ($record['record_type'] == 'appointment'): ?>
                            <!-- Appointment Record -->
                            <div class="timeline-item <?php echo $record['status']; ?>">
                                <div class="timeline-date">
                                    <span class="day"><?php echo date('d', strtotime($record['appointment_date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($record['appointment_date'])); ?></span>
                                    <span class="year"><?php echo date('Y', strtotime($record['appointment_date'])); ?></span>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <div class="header-left">
                                            <i class="fas fa-cut"></i>
                                            <strong><?php echo htmlspecialchars($record['service_name'] ?? 'N/A'); ?></strong>
                                        </div>
                                        <span class="status-badge <?php echo $record['status']; ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                    </div>
                                    <div class="timeline-details">
                                        <div class="detail-row">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span><?php echo date('l, F j, Y', strtotime($record['appointment_date'])); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <i class="fas fa-clock"></i>
                                            <span><?php echo date('g:i A', strtotime($record['appointment_time'])); ?></span>
                                        </div>
                                        <?php if (!empty($record['category'])): ?>
                                        <div class="detail-row">
                                            <i class="fas fa-tag"></i>
                                            <span><?php echo htmlspecialchars($record['category']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="detail-row">
                                            <i class="fas fa-hourglass-half"></i>
                                            <span><?php echo $record['duration'] ?? 'N/A'; ?> minutes</span>
                                        </div>
                                        <?php if (!empty($record['staff_name'])): ?>
                                        <div class="detail-row">
                                            <i class="fas fa-user-tie"></i>
                                            <span><?php echo htmlspecialchars($record['staff_name'] . ' ' . ($record['staff_lname'] ?? '')); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="detail-row">
                                            <i class="fas fa-rupee-sign"></i>
                                            <span class="price">Rs: <?php echo number_format($record['price'] ?? 0, 2); ?></span>
                                        </div>
                                    </div>
                                    <?php if (!empty($record['notes'])): ?>
                                    <div class="timeline-notes">
                                        <i class="fas fa-sticky-note"></i>
                                        <span><?php echo nl2br(htmlspecialchars($record['notes'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="timeline-footer">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Booked on: <?php echo date('M d, Y g:i A', strtotime($record['created_at'] ?? 'now')); ?>
                                        </small>
                                        <button class="btn-view-details" onclick="viewDetails(<?php echo $record['id']; ?>)">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Reschedule Record -->
                            <div class="timeline-item reschedule">
                                <div class="timeline-date">
                                    <span class="day"><?php echo date('d', strtotime($record['rescheduled_at'] ?? 'now')); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($record['rescheduled_at'] ?? 'now')); ?></span>
                                    <span class="year"><?php echo date('Y', strtotime($record['rescheduled_at'] ?? 'now')); ?></span>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <div class="header-left">
                                            <i class="fas fa-redo-alt"></i>
                                            <strong>Appointment Rescheduled</strong>
                                        </div>
                                        <span class="status-badge reschedule">Rescheduled</span>
                                    </div>
                                    <div class="timeline-details">
                                        <?php if (!empty($record['old_date']) && !empty($record['new_date'])): ?>
                                        <div class="detail-row change">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span>
                                                <span class="old-value"><?php echo date('M d, g:i A', strtotime($record['old_date'] . ' ' . ($record['old_time'] ?? '00:00:00'))); ?></span>
                                                <i class="fas fa-arrow-right mx-1"></i>
                                                <span class="new-value"><?php echo date('M d, g:i A', strtotime($record['new_date'] . ' ' . ($record['new_time'] ?? '00:00:00'))); ?></span>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($record['reschedule_reason'])): ?>
                                    <div class="timeline-notes">
                                        <i class="fas fa-question-circle"></i>
                                        <span>Reason: <?php echo ucfirst(str_replace('_', ' ', $record['reschedule_reason'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="timeline-footer">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            Rescheduled by: <?php echo ucfirst($record['rescheduled_by'] ?? 'customer'); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php
                        $query_params = [];
                        if ($filter_type != 'all') $query_params['type'] = $filter_type;
                        if (!empty($status_filter)) $query_params['status'] = $status_filter;
                        if (!empty($date_from)) $query_params['date_from'] = $date_from;
                        if (!empty($date_to)) $query_params['date_to'] = $date_to;
                        if (!empty($search)) $query_params['search'] = $search;
                        
                        $query_string = http_build_query($query_params);
                        $base_url = '?' . ($query_string ? $query_string . '&' : '');
                        ?>
                        
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $base_url; ?>page=1"><i class="fas fa-angle-double-left"></i></a>
                        </li>
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $page-1; ?>"><i class="fas fa-angle-left"></i></a>
                        </li>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $page+1; ?>"><i class="fas fa-angle-right"></i></a>
                        </li>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>"><i class="fas fa-angle-double-right"></i></a>
                        </li>
                    </ul>
                </nav>
                <div class="text-center text-muted mt-2" style="font-size: 14px;">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Auto-hide alert messages after 5 seconds
$(document).ready(function() {
    // Check if error alert exists and auto-hide it
    var errorAlert = document.getElementById('errorAlert');
    if (errorAlert) {
        setTimeout(function() {
            $('#errorAlert').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    <?php if (!empty($monthly_data)): ?>
    initChart();
    <?php endif; ?>
});

function initChart() {
    const monthlyData = <?php echo json_encode(array_reverse($monthly_data)); ?>;
    
    if (monthlyData.length > 0) {
        const ctx = document.getElementById('spendingChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthlyData.map(item => {
                    const [year, month] = item.month.split('-');
                    return new Date(year, month-1).toLocaleString('default', { month: 'short', year: 'numeric' });
                }),
                datasets: [
                    {
                        label: 'Appointments',
                        data: monthlyData.map(item => parseInt(item.count)),
                        backgroundColor: 'rgba(111, 66, 193, 0.6)',
                        borderColor: '#6f42c1',
                        borderWidth: 2,
                        yAxisID: 'y',
                        borderRadius: 4
                    },
                    {
                        label: 'Spent (Rs)',
                        data: monthlyData.map(item => parseFloat(item.total)),
                        backgroundColor: 'rgba(40, 167, 69, 0.4)',
                        borderColor: '#28a745',
                        borderWidth: 2,
                        type: 'line',
                        yAxisID: 'y1',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#28a745'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.label === 'Spent (Rs)') {
                                    label += 'Rs: ' + context.parsed.y.toFixed(2);
                                } else {
                                    label += context.parsed.y;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Number of Appointments',
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Amount (Rs)',
                            font: {
                                size: 12
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
}

function viewDetails(appointmentId) {
    Swal.fire({
        title: 'Loading...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: 'ajax/get-appointment-details.php',
        method: 'POST',
        data: { id: appointmentId },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success && response.appointment) {
                const apt = response.appointment;
                
                let staffHtml = '';
                if (apt.staff_name) {
                    staffHtml = `
                        <div class="detail-row">
                            <span class="label">Staff:</span>
                            <span class="value">${escapeHtml(apt.staff_name)} ${apt.staff_specialization ? '(' + escapeHtml(apt.staff_specialization) + ')' : ''}</span>
                        </div>
                    `;
                }
                
                Swal.fire({
                    title: `Appointment #${apt.id}`,
                    html: `
                        <div class="details-view" style="text-align: left; padding: 10px;">
                            <div class="detail-row">
                                <span class="label">Service:</span>
                                <span class="value">${escapeHtml(apt.service_name)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Date:</span>
                                <span class="value">${formatDate(apt.appointment_date)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Time:</span>
                                <span class="value">${formatTime(apt.appointment_time)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Duration:</span>
                                <span class="value">${apt.service_duration || 'N/A'} minutes</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Amount:</span>
                                <span class="value price">Rs: ${parseFloat(apt.service_price || 0).toFixed(2)}</span>
                            </div>
                            ${staffHtml}
                            <div class="detail-row">
                                <span class="label">Status:</span>
                                <span class="value">
                                    <span class="badge badge-${apt.status}">${apt.status.charAt(0).toUpperCase() + apt.status.slice(1)}</span>
                                </span>
                            </div>
                            ${apt.notes ? `
                            <div class="detail-row">
                                <span class="label">Notes:</span>
                                <span class="value">${escapeHtml(apt.notes)}</span>
                            </div>` : ''}
                            ${apt.created_at ? `
                            <div class="detail-row">
                                <span class="label">Booked:</span>
                                <span class="value">${formatDateTime(apt.created_at)}</span>
                            </div>` : ''}
                        </div>
                    `,
                    showCloseButton: true,
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#6f42c1',
                    width: '550px',
                    customClass: {
                        htmlContainer: 'text-start'
                    }
                });
            } else {
                Swal.fire('Error', response.message || 'Could not load details', 'error');
            }
        },
        error: function() {
            Swal.close();
            Swal.fire('Error', 'Failed to load appointment details', 'error');
        }
    });
}

function exportHistory() {
    const formData = new FormData(document.getElementById('filterForm'));
    const params = new URLSearchParams();
    
    for (let [key, value] of formData.entries()) {
        if (value) {
            params.append(key, value);
        }
    }
    
    Swal.fire({
        title: 'Generating PDF...',
        text: 'Please wait while we create your report.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = 'export-history.php?' + params.toString();
    document.body.appendChild(iframe);
    
    setTimeout(() => {
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'Export Complete!',
            text: 'Your history report has been downloaded.',
            timer: 2000,
            showConfirmButton: false
        });
        
        setTimeout(() => {
            if (document.body.contains(iframe)) {
                document.body.removeChild(iframe);
            }
        }, 1500);
    }, 2000);
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

function formatTime(timeStr) {
    if (!timeStr) return 'N/A';
    const time = new Date('2000-01-01 ' + timeStr);
    return time.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
}

function formatDateTime(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    const div = document.createElement('div');
    div.textContent = unsafe;
    return div.innerHTML;
}
</script>
<script>
// Auto-hide alert messages after 5 seconds
$(document).ready(function() {
    // Auto-hide error alert
    var errorAlert = document.getElementById('headerErrorAlert');
    if (errorAlert) {
        setTimeout(function() {
            $('#headerErrorAlert').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Auto-hide success alert
    var successAlert = document.getElementById('headerSuccessAlert');
    if (successAlert) {
        setTimeout(function() {
            $('#headerSuccessAlert').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    }
});

function bookAppointment() {
    window.location.href = 'book-appointment.php';
}

function refreshDashboard() {
    location.reload();
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>
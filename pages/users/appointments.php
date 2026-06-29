<?php
// myappointments.php - Customer Appointment Management Page
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Initialize variables
$appointments = [];
$stats = ['total' => 0, 'completed' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
$services = [];
$error = null;
$total_appointments = 0;
$total_pages = 1;
$page = 1;
$user = null;

try {
    // Check if database connection exists
    if (!function_exists('getPDOConnection')) {
        throw new Exception('Database connection function not found');
    }
    
    $pdo = getPDOConnection();
    
    // Test connection
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Get user details (required for header)
    $user_query = "SELECT * FROM reg WHERE regID = ? AND cStatus = 1";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

    // Handle cancellation
    if (isset($_POST['cancel_appointment']) && isset($_POST['appointment_id'])) {
        $appointment_id = intval($_POST['appointment_id']);
        $cancel_reason = $_POST['cancel_reason'] ?? '';
        
        // Get current status first
        $check_query = "SELECT status FROM appointments WHERE id = ? AND user_id = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$appointment_id, $user_id]);
        $current = $check_stmt->fetch();
        
        if ($current && in_array($current['status'], ['pending', 'confirmed'])) {
            $update_query = "UPDATE appointments SET 
                             status = 'cancelled',
                             modified_at = NOW() 
                             WHERE id = ? AND user_id = ?";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([$appointment_id, $user_id]);
            
            // Log the cancellation
            $log_query = "INSERT INTO appointment_status_logs 
                          (appointment_id, old_status, new_status, changed_by, changed_at) 
                          VALUES (?, ?, 'cancelled', ?, NOW())";
            $log_stmt = $pdo->prepare($log_query);
            $log_stmt->execute([$appointment_id, $current['status'], $user_id]);
            
            $_SESSION['success_message'] = "Appointment cancelled successfully!";
        } else {
            $_SESSION['error_message'] = "Unable to cancel this appointment.";
        }
        header('Location: appointments.php');
        exit();
    }

    // Handle reschedule
    if (isset($_POST['request_reschedule']) && isset($_POST['appointment_id'])) {
        $appointment_id = intval($_POST['appointment_id']);
        $new_date = $_POST['new_date'];
        $new_time = $_POST['new_time'];
        $reschedule_reason = $_POST['reschedule_reason'] ?? '';
        
        // Get current appointment details
        $current_query = "SELECT appointment_date, appointment_time, service_id FROM appointments 
                          WHERE id = ? AND user_id = ?";
        $current_stmt = $pdo->prepare($current_query);
        $current_stmt->execute([$appointment_id, $user_id]);
        $current_data = $current_stmt->fetch();
        
        if ($current_data) {
            // Insert reschedule record
            $insert_reschedule = "INSERT INTO appointment_reschedules 
                                  (appointment_id, user_id, old_service_id, new_service_id, 
                                   old_date, new_date, old_time, new_time, reschedule_reason, rescheduled_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'customer')";
            $insert_stmt = $pdo->prepare($insert_reschedule);
            $insert_stmt->execute([
                $appointment_id, $user_id, $current_data['service_id'], $current_data['service_id'],
                $current_data['appointment_date'], $new_date,
                $current_data['appointment_time'], $new_time,
                $reschedule_reason
            ]);
            
            // Update appointment
            $update_appointment = "UPDATE appointments SET 
                                   appointment_date = ?,
                                   appointment_time = ?,
                                   status = 'pending',
                                   modified_at = NOW(),
                                   reschedule_count = reschedule_count + 1,
                                   last_reschedule_at = NOW()
                                   WHERE id = ? AND user_id = ?";
            $update_stmt = $pdo->prepare($update_appointment);
            $update_stmt->execute([$new_date, $new_time, $appointment_id, $user_id]);
            
            $_SESSION['success_message'] = "Reschedule request submitted successfully!";
            header('Location: appointments.php');
            exit();
        }
    }

    // Get filter parameters
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
    $search = !empty($search_term) ? '%' . $search_term . '%' : '';
    $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'appointment_date DESC';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $limit;

    // Build WHERE clause with named parameters
    $where_clauses = ["a.user_id = :user_id"];
    $params = [':user_id' => $user_id];
    
    if (!empty($status_filter)) {
        $where_clauses[] = "a.status = :status";
        $params[':status'] = $status_filter;
    }
    
    if (!empty($date_from)) {
        $where_clauses[] = "a.appointment_date >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_clauses[] = "a.appointment_date <= :date_to";
        $params[':date_to'] = $date_to;
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(s.name LIKE :search1 OR s.description LIKE :search2)";
        $params[':search1'] = $search;
        $params[':search2'] = $search;
    }

    $where_sql = implode(" AND ", $where_clauses);

    // Count total appointments
    $count_query = "SELECT COUNT(*) as total FROM appointments a 
                    LEFT JOIN services s ON a.service_id = s.id 
                    WHERE $where_sql";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $count_result = $count_stmt->fetch();
    $total_appointments = $count_result ? (int)$count_result['total'] : 0;
    $total_pages = $total_appointments > 0 ? ceil($total_appointments / $limit) : 1;

    // Get appointments with pagination
    $appointments_query = "SELECT 
        a.id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        a.created_at,
        a.reschedule_count,
        a.last_reschedule_at,
        s.id as service_id,
        s.name as service_name,
        s.price,
        s.duration,
        s.description as service_description,
        st.id as staff_id,
        st.specialization as staff_specialization,
        r.fName as staff_name,
        r.lName as staff_lname
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN reg r ON st.user_id = r.regID
        WHERE $where_sql
        ORDER BY $sort_by
        LIMIT :limit OFFSET :offset";
    
    $appointments_stmt = $pdo->prepare($appointments_query);
    
    // Bind all parameters including limit and offset
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $appointments_stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $appointments_stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    
    // Bind limit and offset as integers
    $appointments_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $appointments_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $appointments_stmt->execute();
    $appointments = $appointments_stmt->fetchAll();

    // Get appointment statistics
    $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments WHERE user_id = ?";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$user_id]);
    $stats_result = $stats_stmt->fetch();
    if ($stats_result) {
        $stats = $stats_result;
    }

    // Get available services for filter
    $services_query = "SELECT id, name FROM services WHERE status = 'active' ORDER BY name";
    $services_stmt = $pdo->query($services_query);
    $services = $services_stmt->fetchAll();

} catch (PDOException $e) {
    // Log the actual error for debugging
    error_log('My Appointments PDO Error: ' . $e->getMessage());
    error_log('Error Code: ' . $e->getCode());
    error_log('Error File: ' . $e->getFile() . ' Line: ' . $e->getLine());
    
    // Show a more specific error message
    $error = "Database error: " . $e->getMessage();
    $appointments = [];
} catch (Exception $e) {
    error_log('My Appointments General Error: ' . $e->getMessage());
    $error = "Error: " . $e->getMessage();
    $appointments = [];
}

// Include header
include 'header/header.php';
?>

<!-- Page specific styles -->
<style>
    .filter-section {
        background: white;
        border-radius: 15px;
        padding: 20px 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        margin-bottom: 25px;
    }
    
    .filter-section .form-label {
        font-weight: 600;
        color: #495057;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }
    
    .filter-section .form-control,
    .filter-section .form-select {
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
    }
    
    .filter-section .form-control:focus,
    .filter-section .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
    }
    
    .btn-filter {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 0.5rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .btn-filter:hover {
        background: #5a32a3;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(111, 66, 193, 0.3);
    }
    
    .btn-export {
        background: #28a745;
        color: white;
        border: none;
        padding: 0.5rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .btn-export:hover {
        background: #218838;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }
    
    .btn-reset {
        background: #6c757d;
        color: white;
        border: none;
        padding: 0.5rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .btn-reset:hover {
        background: #5a6268;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
    }
    
    .table-container {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        overflow-x: auto;
    }
    
    .table-container h4 {
        color: var(--primary-color);
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .badge-pending { background: #fff3cd; color: #856404; }
    .badge-confirmed { background: #d4edda; color: #155724; }
    .badge-completed { background: #d1ecf1; color: #0c5460; }
    .badge-cancelled { background: #f8d7da; color: #721c24; }
    
    .appointment-actions .btn {
        padding: 0.2rem 0.5rem;
        font-size: 0.8rem;
        margin: 0 2px;
    }
    
    .status-filter .btn-group .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.9rem;
    }
    
    .status-filter .btn.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .status-filter .btn:not(.active) {
        background: white;
        color: #6c757d;
        border-color: #dee2e6;
    }
    
    .status-filter .btn:not(.active):hover {
        background: #f8f9fa;
        border-color: var(--primary-color);
        color: var(--primary-color);
    }
    
    .pagination .page-link {
        color: var(--primary-color);
    }
    
    .pagination .page-item.active .page-link {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }
    
    .pagination .page-link:hover {
        color: #5a32a3;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-light);
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #dee2e6;
        margin-bottom: 20px;
    }
    
    .empty-state h4 {
        color: #495057;
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: #6c757d;
        margin-bottom: 20px;
    }
    
    .stats-summary {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 15px;
        padding: 15px 20px;
        margin-bottom: 25px;
    }
    
    .stats-summary .stat-item {
        text-align: center;
        padding: 5px 10px;
    }
    
    .stats-summary .stat-item .number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary-color);
    }
    
    .stats-summary .stat-item .label {
        font-size: 0.85rem;
        color: #6c757d;
    }
    
    @media (max-width: 768px) {
        .filter-section .row > div {
            margin-bottom: 10px;
        }
        
        .filter-section .btn-group {
            display: flex;
            flex-wrap: wrap;
        }
        
        .filter-section .btn-group .btn {
            flex: 1;
            min-width: 60px;
            font-size: 0.8rem;
            padding: 0.3rem 0.5rem;
        }
        
        .stats-summary .stat-item .number {
            font-size: 1.2rem;
        }
    }
    
    /* Animation */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .table-container {
        animation: fadeIn 0.5s ease;
    }
    
    /* Print styles for PDF export - Darker text */
    @media print {
        .no-print {
            display: none !important;
        }
        
        .table-container {
            box-shadow: none !important;
            border: 1px solid #000 !important;
            background: white !important;
        }
        
        .stats-summary {
            background: #f0f0f0 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            border: 1px solid #000 !important;
        }
        
        .stats-summary .stat-item .number {
            color: #000 !important;
        }
        
        .stats-summary .stat-item .label {
            color: #333 !important;
        }
        
        .badge {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            border: 1px solid #000 !important;
            font-weight: bold !important;
        }
        
        .badge-pending { background: #ffc107 !important; color: #000 !important; }
        .badge-confirmed { background: #17a2b8 !important; color: #000 !important; }
        .badge-completed { background: #28a745 !important; color: #000 !important; }
        .badge-cancelled { background: #dc3545 !important; color: #000 !important; }
        
        table {
            border-collapse: collapse !important;
            width: 100% !important;
        }
        
        table th {
            background: #333 !important;
            color: white !important;
            font-weight: bold !important;
            padding: 10px !important;
            border: 1px solid #000 !important;
        }
        
        table td {
            padding: 8px !important;
            border: 1px solid #000 !important;
            color: #000 !important;
        }
        
        table tr:nth-child(even) {
            background: #f5f5f5 !important;
        }
        
        table td strong {
            color: #000 !important;
            font-weight: bold !important;
        }
        
        table td .text-muted {
            color: #333 !important;
            font-weight: 500 !important;
        }
        
        .text-dark {
            color: #000 !important;
        }
        
        .empty-state {
            border: 1px solid #000 !important;
        }
        
        .empty-state i {
            color: #333 !important;
        }
        
        .empty-state h4 {
            color: #000 !important;
        }
        
        .empty-state p {
            color: #333 !important;
        }
        
        .table-container .d-flex .text-muted {
            color: #000 !important;
            font-weight: 500 !important;
        }
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="dashboard-header no-print">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="welcome-text">
                    <h1><i class="fas fa-calendar-alt me-2"></i>My Appointments</h1>
                    <p class="lead mb-0">View and manage all your salon appointments</p>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="d-flex justify-content-end">
                    <button class="btn btn-primary me-2" onclick="bookAppointment()">
                        <i class="fas fa-plus me-2"></i> Book Now
                    </button>
                    <button class="btn btn-outline-secondary" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Summary -->
    <div class="stats-summary row no-print">
        <div class="col-6 col-sm-2 stat-item">
            <div class="number"><?php echo isset($stats['total']) ? $stats['total'] : 0; ?></div>
            <div class="label">Total</div>
        </div>
        <div class="col-6 col-sm-2 stat-item">
            <div class="number" style="color: #ffc107;"><?php echo isset($stats['pending']) ? $stats['pending'] : 0; ?></div>
            <div class="label">Pending</div>
        </div>
        <div class="col-6 col-sm-2 stat-item">
            <div class="number" style="color: #17a2b8;"><?php echo isset($stats['confirmed']) ? $stats['confirmed'] : 0; ?></div>
            <div class="label">Confirmed</div>
        </div>
        <div class="col-6 col-sm-2 stat-item">
            <div class="number" style="color: #28a745;"><?php echo isset($stats['completed']) ? $stats['completed'] : 0; ?></div>
            <div class="label">Completed</div>
        </div>
        <div class="col-6 col-sm-2 stat-item">
            <div class="number" style="color: #dc3545;"><?php echo isset($stats['cancelled']) ? $stats['cancelled'] : 0; ?></div>
            <div class="label">Cancelled</div>
        </div>
        <div class="col-6 col-sm-2 stat-item">
            <div class="number" style="color: #6f42c1;"><?php echo is_array($appointments) ? count($appointments) : 0; ?></div>
            <div class="label">Showing</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section no-print">
        <form method="GET" action="" id="filterForm">
            <div class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo isset($_GET['status']) && $_GET['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo isset($_GET['status']) && $_GET['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                </div>
                
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                </div>
                
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search services..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
                
                <div class="col-md-3 col-sm-12 mb-2">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-filter flex-grow-1">
                            <i class="fas fa-filter me-1"></i>Apply Filters
                        </button>
                        <a href="appointments.php" class="btn btn-reset">
                            <i class="fas fa-undo me-1"></i>Reset
                        </a>
                        <button type="button" class="btn btn-export" onclick="exportPDF()">
                            <i class="fas fa-file-pdf me-1"></i>PDF
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Quick Status Filters -->
            <div class="row mt-2">
                <div class="col-12">
                    <div class="btn-group status-filter" role="group">
                        <button type="button" class="btn <?php echo empty($status_filter) ? 'active' : ''; ?>" onclick="setStatus('')">All</button>
                        <button type="button" class="btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>" onclick="setStatus('pending')">Pending</button>
                        <button type="button" class="btn <?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>" onclick="setStatus('confirmed')">Confirmed</button>
                        <button type="button" class="btn <?php echo $status_filter == 'completed' ? 'active' : ''; ?>" onclick="setStatus('completed')">Completed</button>
                        <button type="button" class="btn <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>" onclick="setStatus('cancelled')">Cancelled</button>
                    </div>
                </div>
            </div>
            
            <!-- Hidden fields for pagination -->
            <input type="hidden" name="sort_by" value="<?php echo isset($_GET['sort_by']) ? htmlspecialchars($_GET['sort_by']) : 'appointment_date DESC'; ?>">
            <input type="hidden" name="limit" value="<?php echo isset($_GET['limit']) ? intval($_GET['limit']) : 20; ?>">
            <input type="hidden" name="page" id="pageInput" value="<?php echo $page; ?>">
        </form>
    </div>

    <!-- Appointments Table -->
    <div class="table-container" id="appointmentsTable">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <span class="text-muted">Showing <?php echo is_array($appointments) ? count($appointments) : 0; ?> of <?php echo $total_appointments; ?> appointments</span>
                <?php if (!empty($_GET['search'])): ?>
                <span class="badge bg-info ms-2">Search: <?php echo htmlspecialchars($_GET['search']); ?></span>
                <?php endif; ?>
            </div>
            <div class="no-print">
                <select class="form-select form-select-sm" style="width: auto; display: inline-block;" onchange="changeLimit(this.value)">
                    <option value="10" <?php echo (isset($_GET['limit']) && $_GET['limit'] == 10) ? 'selected' : ''; ?>>10 per page</option>
                    <option value="20" <?php echo (!isset($_GET['limit']) || $_GET['limit'] == 20) ? 'selected' : ''; ?>>20 per page</option>
                    <option value="50" <?php echo (isset($_GET['limit']) && $_GET['limit'] == 50) ? 'selected' : ''; ?>>50 per page</option>
                    <option value="100" <?php echo (isset($_GET['limit']) && $_GET['limit'] == 100) ? 'selected' : ''; ?>>100 per page</option>
                </select>
            </div>
        </div>
        
        <?php if (!empty($appointments) && is_array($appointments)): ?>
        <div class="table-responsive">
            <table class="table table-hover" id="appointmentTable">
                <thead>
                    <tr>
                        <th>
                            <a href="javascript:void(0)" onclick="sortBy('appointment_date')" class="text-dark text-decoration-none">
                                Date <i class="fas fa-sort"></i>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortBy('appointment_time')" class="text-dark text-decoration-none">
                                Time <i class="fas fa-sort"></i>
                            </a>
                        </th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortBy('service_name')" class="text-dark text-decoration-none">
                                Service <i class="fas fa-sort"></i>
                            </a>
                        </th>
                        <th>Staff</th>
                        <th>
                            <a href="javascript:void(0)" onclick="sortBy('status')" class="text-dark text-decoration-none">
                                Status <i class="fas fa-sort"></i>
                            </a>
                        </th>
                        <th>Price</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td>
                            <strong><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></strong>
                            <br><small class="text-muted"><?php echo date('D', strtotime($appointment['appointment_date'])); ?></small>
                            <?php if ($appointment['reschedule_count'] > 0): ?>
                            <br><span class="badge bg-warning text-dark" style="font-size: 0.65rem;">
                                <i class="fas fa-sync-alt"></i> Rescheduled x<?php echo $appointment['reschedule_count']; ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                            <br><small class="text-muted"><?php echo $appointment['duration'] ?? 'N/A'; ?> mins</small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($appointment['service_name'] ?? 'N/A'); ?></strong>
                            <?php if (!empty($appointment['service_description'])): ?>
                            <br><small class="text-muted"><?php echo substr(htmlspecialchars($appointment['service_description']), 0, 50); ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($appointment['staff_name'])): ?>
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($appointment['staff_name'] . ' ' . ($appointment['staff_lname'] ?? '')); ?>
                            <?php if (!empty($appointment['staff_specialization'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($appointment['staff_specialization']); ?></small>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $appointment['status']; ?>">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                            <?php if ($appointment['status'] == 'pending'): ?>
                            <br><small class="text-muted">Awaiting confirmation</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($appointment['price']) && $appointment['price'] > 0): ?>
                            <strong>$<?php echo number_format($appointment['price'], 2); ?></strong>
                            <?php else: ?>
                            <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td class="appointment-actions no-print">
                            <button class="btn btn-sm btn-outline-info" 
                                    onclick="viewAppointment(<?php echo $appointment['id']; ?>)"
                                    title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <?php if (in_array($appointment['status'], ['pending', 'confirmed'])): ?>
                            <button class="btn btn-sm btn-outline-warning" 
                                    onclick="rescheduleAppointment(<?php echo $appointment['id']; ?>, '<?php echo $appointment['appointment_date']; ?>', '<?php echo $appointment['appointment_time']; ?>')"
                                    title="Reschedule">
                                <i class="fas fa-calendar-alt"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="cancelAppointment(<?php echo $appointment['id']; ?>)"
                                    title="Cancel">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($appointment['status'] == 'completed'): ?>
                            <button class="btn btn-sm btn-outline-success" 
                                    onclick="leaveReview(<?php echo $appointment['id']; ?>)"
                                    title="Leave Review">
                                <i class="fas fa-star"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3 no-print">
            <div class="text-muted small">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="javascript:void(0)" onclick="goToPage(<?php echo $page - 1; ?>)">Previous</a>
                    </li>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="javascript:void(0)" onclick="goToPage(<?php echo $i; ?>)"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="javascript:void(0)" onclick="goToPage(<?php echo $page + 1; ?>)">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h4>No Appointments Found</h4>
            <p>
                <?php if (!empty($_GET) && (isset($_GET['status']) || isset($_GET['date_from']) || isset($_GET['date_to']) || isset($_GET['search']))): ?>
                No appointments match your filter criteria. Try adjusting your filters.
                <?php else: ?>
                You haven't booked any appointments yet.
                <?php endif; ?>
            </p>
            <?php if (!empty($_GET) && (isset($_GET['status']) || isset($_GET['date_from']) || isset($_GET['date_to']) || isset($_GET['search']))): ?>
            <a href="appointments.php" class="btn btn-secondary no-print">
                <i class="fas fa-undo me-2"></i>Clear Filters
            </a>
            <?php else: ?>
            <button class="btn btn-primary no-print" onclick="bookAppointment()">
                <i class="fas fa-plus me-2"></i>Book Your First Appointment
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

<!-- Include required libraries for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
    // Set status filter
    function setStatus(status) {
        document.querySelector('select[name="status"]').value = status;
        document.getElementById('filterForm').submit();
    }

    // Sort by column
    function sortBy(column) {
        const currentSort = document.querySelector('input[name="sort_by"]').value;
        let newSort = column + ' ASC';
        if (currentSort.includes(column)) {
            newSort = column + (currentSort.includes('ASC') ? ' DESC' : ' ASC');
        }
        document.querySelector('input[name="sort_by"]').value = newSort;
        document.getElementById('filterForm').submit();
    }

    // Change items per page
    function changeLimit(value) {
        document.querySelector('input[name="limit"]').value = value;
        document.getElementById('filterForm').submit();
    }

    // Go to page
    function goToPage(page) {
        document.getElementById('pageInput').value = page;
        document.getElementById('filterForm').submit();
    }

    // Book appointment
    function bookAppointment() {
        Swal.fire({
            title: 'Book Appointment',
            text: 'Redirecting to booking page...',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Continue',
            cancelButtonText: 'Later',
            confirmButtonColor: '#6f42c1'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'book-appointment.php';
            }
        });
    }

    // View appointment details - Fixed version
    function viewAppointment(appointmentId) {
        // Show loading
        Swal.fire({
            title: 'Loading...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'ajax/get-appointment.php',
            method: 'POST',
            data: { id: appointmentId },
            dataType: 'json',
            success: function(response) {
                Swal.close();
                
                // Debug: Log the full response
                console.log('Full response:', response);
                
                // Check if there's an error in the response
                if (response.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.error,
                        confirmButtonColor: '#6f42c1'
                    });
                    return;
                }

                // Check if response has the required data
                if (response.id && response.service_name) {
                    // Success - display the appointment details
                    displayAppointmentDetails(response);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Invalid response from server',
                        confirmButtonColor: '#6f42c1'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                console.error('AJAX Error Details:');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);
                console.error('Status Code:', xhr.status);
                
                let errorMsg = 'Failed to load appointment details';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg = response.error;
                    }
                } catch (e) {
                    if (xhr.responseText) {
                        errorMsg = 'Server error: ' + xhr.responseText.substring(0, 200);
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMsg,
                    confirmButtonColor: '#6f42c1',
                    footer: 'Please try again or contact support.'
                });
            }
        });
    }

    // Function to display appointment details
    function displayAppointmentDetails(data) {
        // Debug: Log the data
        console.log('Displaying appointment data:', data);
        
        // Determine badge class for status
        let statusClass = 'badge-';
        switch(data.status) {
            case 'pending':
                statusClass += 'pending';
                break;
            case 'confirmed':
                statusClass += 'confirmed';
                break;
            case 'completed':
                statusClass += 'completed';
                break;
            case 'cancelled':
                statusClass += 'cancelled';
                break;
            default:
                statusClass += 'secondary';
        }

        // Build staff info
        let staffInfo = '';
        if (data.staff_name && data.staff_name.trim() !== '') {
            staffInfo = `<p><strong>Staff:</strong> ${data.staff_name}`;
            if (data.staff_specialization && data.staff_specialization.trim() !== '') {
                staffInfo += ` (${data.staff_specialization})`;
            }
            staffInfo += `</p>`;
        } else {
            staffInfo = '<p><strong>Staff:</strong> Not assigned</p>';
        }

        // Build the appointment details HTML
        const detailsHtml = `
            <div class="text-start">
                <h5 class="text-primary">${data.service_name || 'N/A'}</h5>
                <hr>
                <div class="row mb-2">
                    <div class="col-6">
                        <p class="mb-1"><strong>Date:</strong></p>
                        <p class="mb-2">${data.date || 'N/A'}</p>
                    </div>
                    <div class="col-6">
                        <p class="mb-1"><strong>Time:</strong></p>
                        <p class="mb-2">${data.time || 'N/A'}</p>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-6">
                        <p class="mb-1"><strong>Price:</strong></p>
                        <p class="mb-2">$${data.price || 'N/A'}</p>
                    </div>
                    <div class="col-6">
                        <p class="mb-1"><strong>Duration:</strong></p>
                        <p class="mb-2">${data.duration || 'N/A'} mins</p>
                    </div>
                </div>
                ${staffInfo}
                <p class="mb-1"><strong>Status:</strong></p>
                <p class="mb-2">
                    <span class="badge ${statusClass}">${data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : 'Unknown'}</span>
                </p>
                ${data.notes && data.notes.trim() !== '' ? `<p class="mb-1"><strong>Notes:</strong></p><p class="mb-2">${data.notes}</p>` : ''}
                ${data.created_at && data.created_at !== 'N/A' ? `<p class="mb-0 small text-muted"><strong>Booked on:</strong> ${data.created_at}</p>` : ''}
            </div>
        `;

        // Show the appointment details
        Swal.fire({
            title: 'Appointment Details',
            html: detailsHtml,
            showCloseButton: true,
            confirmButtonText: 'Close',
            confirmButtonColor: '#6f42c1',
            width: '550px',
            customClass: {
                htmlContainer: 'text-start'
            }
        });
    }

    // Cancel appointment
    function cancelAppointment(appointmentId) {
        Swal.fire({
            title: 'Cancel Appointment?',
            text: "Are you sure you want to cancel this appointment?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, cancel it!',
            cancelButtonText: 'No, keep it'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Reason for cancellation (optional)',
                    input: 'textarea',
                    inputPlaceholder: 'Enter reason for cancellation...',
                    inputAttributes: {
                        'aria-label': 'Cancellation reason'
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Confirm Cancellation',
                    cancelButtonText: 'Skip'
                }).then((reasonResult) => {
                    const reason = reasonResult.value || '';
                    // Submit cancellation form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    
                    const inputId = document.createElement('input');
                    inputId.type = 'hidden';
                    inputId.name = 'appointment_id';
                    inputId.value = appointmentId;
                    form.appendChild(inputId);
                    
                    const inputCancel = document.createElement('input');
                    inputCancel.type = 'hidden';
                    inputCancel.name = 'cancel_appointment';
                    inputCancel.value = '1';
                    form.appendChild(inputCancel);
                    
                    if (reason) {
                        const inputReason = document.createElement('input');
                        inputReason.type = 'hidden';
                        inputReason.name = 'cancel_reason';
                        inputReason.value = reason;
                        form.appendChild(inputReason);
                    }
                    
                    document.body.appendChild(form);
                    form.submit();
                });
            }
        });
    }

    // Reschedule appointment
    function rescheduleAppointment(appointmentId, currentDate, currentTime) {
        const minDate = new Date();
        minDate.setDate(minDate.getDate() + 1);
        const minDateStr = minDate.toISOString().split('T')[0];
        
        Swal.fire({
            title: 'Reschedule Appointment',
            html: `
                <div class="text-start">
                    <p><strong>Current Date:</strong> ${currentDate}</p>
                    <p><strong>Current Time:</strong> ${currentTime}</p>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label"><strong>New Date</strong></label>
                        <input type="date" id="newDate" class="form-control" min="${minDateStr}" value="${minDateStr}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>New Time</strong></label>
                        <input type="time" id="newTime" class="form-control" value="09:00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Reason (optional)</strong></label>
                        <textarea id="rescheduleReason" class="form-control" rows="2" placeholder="Why do you need to reschedule?"></textarea>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Submit Reschedule',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#6f42c1',
            preConfirm: () => {
                const newDate = document.getElementById('newDate').value;
                const newTime = document.getElementById('newTime').value;
                const reason = document.getElementById('rescheduleReason').value;
                
                if (!newDate || !newTime) {
                    Swal.showValidationMessage('Please select both date and time');
                    return false;
                }
                
                return { newDate, newTime, reason };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const inputs = [
                    { name: 'appointment_id', value: appointmentId },
                    { name: 'request_reschedule', value: '1' },
                    { name: 'new_date', value: result.value.newDate },
                    { name: 'new_time', value: result.value.newTime },
                    { name: 'reschedule_reason', value: result.value.reason }
                ];
                
                inputs.forEach(({name, value}) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Leave review
    function leaveReview(appointmentId) {
        Swal.fire({
            title: 'Rate Your Experience',
            html: `
                <div class="text-start">
                    <div class="mb-3">
                        <label class="form-label"><strong>Rating</strong></label>
                        <div class="rating-stars text-center" style="font-size: 2rem; color: #ffc107;">
                            <i class="far fa-star" data-value="1" onclick="setRating(1)"></i>
                            <i class="far fa-star" data-value="2" onclick="setRating(2)"></i>
                            <i class="far fa-star" data-value="3" onclick="setRating(3)"></i>
                            <i class="far fa-star" data-value="4" onclick="setRating(4)"></i>
                            <i class="far fa-star" data-value="5" onclick="setRating(5)"></i>
                        </div>
                        <input type="hidden" id="ratingValue" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Review</strong></label>
                        <textarea id="reviewText" class="form-control" rows="3" placeholder="Share your experience..."></textarea>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Submit Review',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#6f42c1',
            preConfirm: () => {
                const rating = document.getElementById('ratingValue').value;
                const review = document.getElementById('reviewText').value;
                
                if (!rating || rating === '0') {
                    Swal.showValidationMessage('Please select a rating');
                    return false;
                }
                
                return { rating, review };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                $.ajax({
                    url: 'ajax/submit-review.php',
                    method: 'POST',
                    data: {
                        appointment_id: appointmentId,
                        rating: result.value.rating,
                        review: result.value.review
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Thank You!',
                                text: 'Your review has been submitted successfully.',
                                confirmButtonColor: '#6f42c1'
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Failed to submit review', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to submit review', 'error');
                    }
                });
            }
        });
    }

    // Set rating for review
    function setRating(value) {
        document.getElementById('ratingValue').value = value;
        const stars = document.querySelectorAll('.rating-stars i');
        stars.forEach(star => {
            const starValue = parseInt(star.dataset.value);
            if (starValue <= value) {
                star.className = 'fas fa-star';
            } else {
                star.className = 'far fa-star';
            }
        });
    }

    // Export as PDF with darker text
    function exportPDF() {
        const element = document.getElementById('appointmentsTable');
        
        // Show loading
        Swal.fire({
            title: 'Generating PDF...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Temporarily add a class for PDF generation
        document.body.classList.add('pdf-generation');
        
        html2canvas(element, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
            onclone: function(clonedDoc) {
                // Ensure all text is dark in the cloned document
                const allElements = clonedDoc.querySelectorAll('*');
                allElements.forEach(el => {
                    const computedStyle = window.getComputedStyle(el);
                    if (computedStyle.color && computedStyle.color.includes('rgb')) {
                        // Force dark text
                        el.style.color = '#000000';
                    }
                });
            }
        }).then((canvas) => {
            const imgData = canvas.toDataURL('image/png');
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('p', 'mm', 'a4');
            
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
            
            pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
            
            // Add footer with dark text
            const pageCount = pdf.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                pdf.setPage(i);
                pdf.setFontSize(8);
                pdf.setTextColor(0, 0, 0); // Pure black
                pdf.text(
                    `Generated on ${new Date().toLocaleString()} | Salon Management System`,
                    pdfWidth / 2,
                    pdf.internal.pageSize.getHeight() - 5,
                    { align: 'center' }
                );
            }
            
            pdf.save('my-appointments.pdf');
            
            // Remove the class
            document.body.classList.remove('pdf-generation');
            
            Swal.fire({
                icon: 'success',
                title: 'PDF Generated!',
                text: 'Your appointments report has been downloaded.',
                confirmButtonColor: '#6f42c1'
            });
        }).catch((error) => {
            console.error('PDF generation error:', error);
            document.body.classList.remove('pdf-generation');
            Swal.fire('Error', 'Failed to generate PDF. Please try again.', 'error');
        });
    }

    // Toast notifications
    <?php if (isset($_SESSION['success_message'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo $_SESSION['success_message']; ?>',
        confirmButtonColor: '#6f42c1',
        timer: 3000,
        timerProgressBar: true
    });
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?php echo $_SESSION['error_message']; ?>',
        confirmButtonColor: '#6f42c1'
    });
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    // Auto-refresh appointments every 60 seconds
    let refreshInterval = setInterval(function() {
        $.ajax({
            url: 'ajax/check-appointment-updates.php',
            method: 'POST',
            data: { 
                user_id: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>,
                last_check: new Date().toISOString().slice(0, 19).replace('T', ' ')
            },
            dataType: 'json',
            success: function(response) {
                try {
                    if (response.hasUpdates) {
                        location.reload();
                    }
                } catch (e) {
                    // Silent fail
                }
            },
            error: function() {
                // Silent fail
            }
        });
    }, 60000);
</script>

</body>
</html>
<?php ob_end_flush(); ?>
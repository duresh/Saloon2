<?php
// staff-history.php - Staff Appointment History
session_start();
require_once '../../includes/dbcon.php';

// Check if user is logged in and has staff/admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../login.php');
    exit;
}

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['fName'] ?? 'Staff';
$role = $_SESSION['role'] ?? 'staff';

// Get staff details
try {
    $pdo = getPDOConnection();
    
    // Get staff information
    $staff_query = "
        SELECT s.*, r.fName, r.lName, r.email, r.contactNo 
        FROM staff s 
        JOIN reg r ON s.user_id = r.regID 
        WHERE s.user_id = ?
    ";
    $staff_stmt = $pdo->prepare($staff_query);
    $staff_stmt->execute([$staff_id]);
    $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        // If staff record doesn't exist, create one
        $insert_stmt = $pdo->prepare("
            INSERT INTO staff (user_id, phone, specialization, joining_date) 
            VALUES (?, ?, 'General', CURDATE())
        ");
        $insert_stmt->execute([$staff_id]);
        
        // Fetch again
        $staff_stmt->execute([$staff_id]);
        $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $staff_db_id = (int)$staff['id'];
    $staff_name = $staff['fName'] ?? $staff_name;
    
    // Get unread notifications count
    $notif_count_query = "
        SELECT COUNT(*) as count FROM staff_notifications 
        WHERE staff_id = ? AND is_read = 0
    ";
    $notif_count_stmt = $pdo->prepare($notif_count_query);
    $notif_count_stmt->execute([$staff_db_id]);
    $unread_count = $notif_count_stmt->fetch()['count'] ?? 0;
    
    // Get notifications for staff (for bell icon)
    $notif_query = "
        SELECT * FROM staff_notifications 
        WHERE staff_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ";
    $notif_stmt = $pdo->prepare($notif_query);
    $notif_stmt->execute([$staff_db_id]);
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Staff history error: " . $e->getMessage());
    $staff = null;
    $staff_db_id = 0;
    $unread_count = 0;
    $notifications = [];
}

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

// Filter variables
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get appointment history for this staff
try {
    $pdo = getPDOConnection();
    
    // Build the query with direct concatenation for staff_id to avoid binding issues
    $where_clauses = ["a.staff_id = " . (int)$staff_db_id];
    $params = [];
    
    // Add filters with proper parameter binding
    if (!empty($status_filter)) {
        $where_clauses[] = "a.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_from)) {
        $where_clauses[] = "a.appointment_date >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where_clauses[] = "a.appointment_date <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(s.name LIKE ? OR r.fName LIKE ? OR r.lName LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_sql = implode(" AND ", $where_clauses);
    
    // Get total count
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
    
    // Get data - use direct concatenation for LIMIT and OFFSET to avoid binding issues
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
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
            COALESCE(SUM(s.price), 0) as total_revenue
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.staff_id = " . (int)$staff_db_id . "
    ";
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
    
} catch (PDOException $e) {
    error_log("Staff history query error: " . $e->getMessage());
    $appointments = [];
    $total_records = 0;
    $total_pages = 1;
    $stats = [
        'total' => 0,
        'completed' => 0,
        'pending' => 0,
        'confirmed' => 0,
        'cancelled' => 0,
        'no_show' => 0,
        'total_revenue' => 0
    ];
    $error = "Unable to load appointment history. Please try again later.";
}

// Include staff header
include 'header/header-staff.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="welcome-text">
                            <h1><i class="fas fa-history me-2"></i>Appointment History</h1>
                            <p class="lead mb-0">View all your past and upcoming appointments</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-light text-dark p-2 me-2">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($staff_name); ?>
                        </span>
                        <button class="btn btn-primary btn-sm" onclick="exportHistory()">
                            <i class="fas fa-file-pdf me-1"></i>Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Debug Info -->
            <?php if (isset($_GET['debug'])): ?>
            <div class="alert alert-info">
                <strong>Debug Info:</strong>
                <p>Staff ID: <?php echo $staff_db_id; ?></p>
                <p>Total Appointments: <?php echo $total_records; ?></p>
                <p>Appointments Found: <?php echo count($appointments); ?></p>
                <?php if (!empty($appointments)): ?>
                <p><strong>First Appointment:</strong></p>
                <pre><?php print_r($appointments[0]); ?></pre>
                <?php else: ?>
                <p class="text-danger">No appointments found for staff ID: <?php echo $staff_db_id; ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2 col-4">
                    <div class="stat-card" style="background: white; border-radius: 12px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border-left: 4px solid #6f42c1;">
                        <div class="stat-icon" style="width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; background: rgba(111, 66, 193, 0.12); color: #6f42c1;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value" style="font-size: 22px; font-weight: 700; color: #333;"><?php echo $stats['total'] ?? 0; ?></div>
                            <div class="stat-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase;">Total</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="stat-card" style="background: white; border-radius: 12px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border-left: 4px solid #28a745;">
                        <div class="stat-icon" style="width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; background: rgba(40, 167, 69, 0.12); color: #28a745;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value" style="font-size: 22px; font-weight: 700; color: #333;"><?php echo $stats['completed'] ?? 0; ?></div>
                            <div class="stat-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase;">Completed</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="stat-card" style="background: white; border-radius: 12px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border-left: 4px solid #ffc107;">
                        <div class="stat-icon" style="width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; background: rgba(255, 193, 7, 0.12); color: #ffc107;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value" style="font-size: 22px; font-weight: 700; color: #333;"><?php echo ($stats['pending'] ?? 0) + ($stats['confirmed'] ?? 0); ?></div>
                            <div class="stat-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase;">Active</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="stat-card" style="background: white; border-radius: 12px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border-left: 4px solid #dc3545;">
                        <div class="stat-icon" style="width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; background: rgba(220, 53, 69, 0.12); color: #dc3545;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value" style="font-size: 22px; font-weight: 700; color: #333;"><?php echo $stats['cancelled'] ?? 0; ?></div>
                            <div class="stat-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase;">Cancelled</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="stat-card" style="background: white; border-radius: 12px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border-left: 4px solid #ffc107;">
                        <div class="stat-icon" style="width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; background: rgba(255, 193, 7, 0.12); color: #ffc107;">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value" style="font-size: 22px; font-weight: 700; color: #333;">Rs: <?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                            <div class="stat-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase;">Total Revenue</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="stat-card" style="background: white; border-radius: 12px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border-left: 4px solid #17a2b8;">
                        <div class="stat-icon" style="width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; background: rgba(23, 162, 184, 0.12); color: #17a2b8;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value" style="font-size: 22px; font-weight: 700; color: #333;">Rs: <?php echo number_format(($stats['total_revenue'] ?? 0) / max($stats['total'] ?? 1, 1), 2); ?></div>
                            <div class="stat-label" style="font-size: 11px; color: #6c757d; text-transform: uppercase;">Avg Revenue</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card filter-card mb-4" style="border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px;">
                <div class="card-header" style="background: #f8f9fa; border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0; padding: 15px 20px;">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter History</h5>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <form method="GET" action="" id="filterForm">
                        <div class="row align-items-end">
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="no_show" <?php echo $status_filter == 'no_show' ? 'selected' : ''; ?>>No Show</option>
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
                                    <input type="text" class="form-control" name="search" placeholder="Customer or service..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-2 mb-3 d-flex align-items-end">
                                <a href="staff-history.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-undo me-1"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Appointments Table -->
            <div class="card table-card" style="border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; overflow: hidden;">
                <div class="card-header d-flex justify-content-between align-items-center" style="background: #f8f9fa; border-bottom: 1px solid #e9ecef; padding: 15px 20px;">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Appointments</h5>
                    <span class="badge bg-primary"><?php echo $total_records; ?> Records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Customer</th>
                                    <th>Service</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted mb-0">No appointments found</p>
                                        <?php if (!empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($search)): ?>
                                        <p class="text-muted small">Try adjusting your filters</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php $count = $offset + 1; ?>
                                    <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo $count++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($appointment['customer_name'] . ' ' . ($appointment['customer_lname'] ?? '')); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['customer_phone'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($appointment['service_name'] ?? 'N/A'); ?>
                                            <br>
                                            <small class="text-muted"><?php echo $appointment['duration'] ?? 'N/A'; ?> mins</small>
                                        </td>
                                        <td>
                                            <div><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></div>
                                            <div class="text-muted small"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></div>
                                            <?php if ($appointment['reschedule_count'] > 0): ?>
                                            <span class="badge bg-warning text-dark" style="font-size: 10px;">
                                                <i class="fas fa-sync-alt"></i> Rescheduled x<?php echo $appointment['reschedule_count']; ?>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-primary">Rs: <?php echo number_format($appointment['price'] ?? 0, 2); ?></strong>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewDetails(<?php echo $appointment['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($appointment['status'] == 'pending' || $appointment['status'] == 'confirmed'): ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="updateStatus(<?php echo $appointment['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer" style="background: #fff; border-top: 1px solid #e9ecef; padding: 15px 20px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                $query_params = [];
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
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <input type="hidden" id="statusAppointmentId">
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select class="form-select" id="newStatus">
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="no_show">No Show</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea class="form-control" id="statusNotes" rows="2" placeholder="Add any notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveStatusUpdate()">
                    <i class="fas fa-save me-2"></i>Update Status
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Store notifications data for bell icon
var notifications = <?php echo json_encode($notifications); ?>;
var unreadCount = <?php echo $unread_count; ?>;

// Show notifications function for bell icon
function showNotifications() {
    var notificationsHtml = '';
    
    if (notifications && notifications.length > 0) {
        notificationsHtml = notifications.map(function(notif) {
            var isRead = parseInt(notif.is_read) === 1;
            var timeAgo = getTimeAgo(notif.created_at);
            return `
                <div class="notification-item ${isRead ? 'read' : 'unread'}" data-id="${notif.id}">
                    <div class="notification-content">
                        <div class="notification-title">${notif.title || 'Notification'}</div>
                        <div class="notification-message">${notif.message}</div>
                        <div class="notification-time">${timeAgo}</div>
                    </div>
                    ${!isRead ? `<button class="btn-mark-read" onclick="markNotificationRead(${notif.id})"><i class="fas fa-check"></i></button>` : ''}
                </div>
            `;
        }).join('');
    } else {
        notificationsHtml = `
            <div class="notification-empty">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">No notifications</p>
            </div>
        `;
    }
    
    Swal.fire({
        title: '<i class="fas fa-bell me-2"></i> Notifications',
        html: `
            <div class="notifications-container">
                <div class="notifications-header">
                    <span class="notification-count">${unreadCount} unread</span>
                    ${unreadCount > 0 ? `<button class="mark-all-read" onclick="markAllNotificationsRead()">Mark all as read</button>` : ''}
                </div>
                <div class="notifications-list">
                    ${notificationsHtml}
                </div>
            </div>
        `,
        showCloseButton: true,
        confirmButtonText: 'Close',
        confirmButtonColor: '#6f42c1',
        width: '450px',
        customClass: {
            popup: 'notification-modal',
            htmlContainer: 'p-0'
        }
    });
}

function getTimeAgo(dateString) {
    var date = new Date(dateString);
    var now = new Date();
    var diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
    return date.toLocaleDateString();
}

function markNotificationRead(notificationId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'mark_notification_read',
            notification_id: notificationId
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(response) {
        if (response.success) {
            unreadCount--;
            var badge = document.querySelector('.notification-badge');
            if (badge) {
                if (unreadCount > 0) {
                    badge.textContent = unreadCount;
                } else {
                    badge.style.display = 'none';
                }
            }
            location.reload();
        }
    })
    .catch(function(error) {
        console.error('Error marking notification as read:', error);
    });
}

function markAllNotificationsRead() {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'mark_notification_read',
            notification_id: 0
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(response) {
        if (response.success) {
            unreadCount = 0;
            var badge = document.querySelector('.notification-badge');
            if (badge) {
                badge.style.display = 'none';
            }
            location.reload();
        }
    })
    .catch(function(error) {
        console.error('Error marking all notifications as read:', error);
    });
}

// View appointment details
function viewDetails(appointmentId) {
    var modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    
    document.getElementById('detailsContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary"></div>
        </div>
    `;
    modal.show();
    
    fetch('ajax/get-appointment-details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            id: appointmentId,
            staff: 1
        })
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(response) {
        if (response.success && response.appointment) {
            var apt = response.appointment;
            
            var html = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6><i class="fas fa-info-circle me-2"></i>Appointment Information</h6>
                        <p><strong>Service:</strong> ${apt.service_name}</p>
                        <p><strong>Date:</strong> ${formatDate(apt.appointment_date)}</p>
                        <p><strong>Time:</strong> ${formatTime(apt.appointment_time)}</p>
                        <p><strong>Duration:</strong> ${apt.service_duration || 'N/A'} minutes</p>
                        <p><strong>Status:</strong> <span class="status-badge ${apt.status}">${apt.status.charAt(0).toUpperCase() + apt.status.slice(1)}</span></p>
                        ${apt.reschedule_count > 0 ? `<p><strong>Rescheduled:</strong> ${apt.reschedule_count} times</p>` : ''}
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-user me-2"></i>Customer Information</h6>
                        <p><strong>Name:</strong> ${apt.customer_name}</p>
                        <p><strong>Email:</strong> ${apt.customer_email || 'N/A'}</p>
                        <p><strong>Phone:</strong> ${apt.customer_phone || 'N/A'}</p>
                        ${apt.notes ? `<p><strong>Notes:</strong> ${apt.notes}</p>` : ''}
                    </div>
                </div>
                <hr>
                <h6><i class="fas fa-credit-card me-2"></i>Payment Information</h6>
                <p><strong>Amount:</strong> <span class="text-primary fw-bold">Rs: ${parseFloat(apt.service_price || 0).toFixed(2)}</span></p>
            `;
            
            document.getElementById('detailsContent').innerHTML = html;
        } else {
            document.getElementById('detailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>${response.message || 'Could not load appointment details'}
                </div>
            `;
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        document.getElementById('detailsContent').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>Failed to load appointment details
            </div>
        `;
    });
}

// Update status
function updateStatus(appointmentId) {
    document.getElementById('statusAppointmentId').value = appointmentId;
    document.getElementById('newStatus').value = 'completed';
    document.getElementById('statusNotes').value = '';
    
    var modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

// Save status update
function saveStatusUpdate() {
    var appointmentId = document.getElementById('statusAppointmentId').value;
    var newStatus = document.getElementById('newStatus').value;
    var notes = document.getElementById('statusNotes').value;
    
    if (!appointmentId) {
        Swal.fire('Error', 'No appointment selected', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Updating Status...',
        allowOutsideClick: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'update_status',
            appointment_id: appointmentId,
            status: newStatus,
            notes: notes
        })
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(response) {
        Swal.close();
        var modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
        if (modal) modal.hide();
        
        if (response.success) {
            Swal.fire('Success!', response.message, 'success').then(function() {
                location.reload();
            });
        } else {
            Swal.fire('Error', response.message || 'Failed to update status', 'error');
        }
    })
    .catch(function(error) {
        Swal.close();
        Swal.fire('Error', 'Failed to update status. Please try again.', 'error');
    });
}

// Export history
function exportHistory() {
    var form = document.getElementById('filterForm');
    var formData = new FormData(form);
    var params = new URLSearchParams();
    
    for (var pair of formData.entries()) {
        if (pair[1]) {
            params.append(pair[0], pair[1]);
        }
    }
    
    Swal.fire({
        title: 'Generating PDF...',
        text: 'Please wait while we create your report.',
        allowOutsideClick: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });
    
    var iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = 'export-staff-history.php?' + params.toString();
    document.body.appendChild(iframe);
    
    setTimeout(function() {
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'Export Complete!',
            text: 'Your history report has been downloaded.',
            timer: 2000,
            showConfirmButton: false
        });
        
        setTimeout(function() {
            if (document.body.contains(iframe)) {
                document.body.removeChild(iframe);
            }
        }, 1500);
    }, 2000);
}

// Helper functions
function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    var date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

function formatTime(timeStr) {
    if (!timeStr) return 'N/A';
    var time = new Date('2000-01-01 ' + timeStr);
    return time.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
}
</script>
</body>
</html>
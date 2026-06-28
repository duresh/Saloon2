<?php
ob_start();
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/dashboard.php');
    exit();
}

// Include database connection
require_once '../../includes/dbcon.php';
// Include helper functions
require_once '../../includes/helpers.php';

// Get status from URL parameter (default to 'all')
$current_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$valid_statuses = ['all', 'pending', 'confirmed', 'completed', 'cancelled', 'today', 'upcoming'];

if (!in_array($current_status, $valid_statuses)) {
    $current_status = 'all';
}

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 5;
$offset = ($page - 1) * $records_per_page;

// Search and filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';
$service_filter = isset($_GET['service']) ? (int)$_GET['service'] : 0;
$staff_filter = isset($_GET['staff']) ? (int)$_GET['staff'] : 0;

try {
    $pdo = getPDOConnection();
    $admin_id = $_SESSION['user_id'];
    
    // Get admin details
    $admin_query = "SELECT * FROM reg WHERE regID = ? AND cStatus = 1 AND role = 'admin'";
    $admin_stmt = $pdo->prepare($admin_query);
    $admin_stmt->execute([$admin_id]);
    $admin = $admin_stmt->fetch();
    
    if (!$admin) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
    
    // Get all services for filter dropdown
    $services_query = "SELECT id, name, category FROM services WHERE status = 'active' ORDER BY category, name";
    $services_stmt = $pdo->query($services_query);
    $services = $services_stmt->fetchAll();
    
    // Get all staff for filter dropdown
    $staff_query = "
        SELECT s.id, r.fName as staff_name, s.specialization 
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE r.cStatus = 1
        ORDER BY r.fName
    ";
    $staff_stmt = $pdo->query($staff_query);
    $all_staff = $staff_stmt->fetchAll();
    
    // Build the count query for pagination
    $count_query = "
        SELECT COUNT(*) as total
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg u ON a.user_id = u.regID
        WHERE 1=1
    ";
    
    $count_params = [];
    
    // Add status filter
    if ($current_status == 'today') {
        $count_query .= " AND a.appointment_date = CURDATE()";
    } elseif ($current_status == 'upcoming') {
        $count_query .= " AND a.appointment_date >= CURDATE() AND a.status IN ('pending', 'confirmed')";
    } elseif ($current_status !== 'all') {
        $count_query .= " AND a.status = ?";
        $count_params[] = $current_status;
    }
    
    // Add search filter
    if (!empty($search)) {
        $count_query .= " AND (u.fName LIKE ? OR u.email LIKE ? OR u.contactNo LIKE ? OR s.name LIKE ?)";
        $search_param = "%$search%";
        $count_params = array_merge($count_params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    // Add date filter
    if (!empty($date_filter)) {
        $count_query .= " AND a.appointment_date = ?";
        $count_params[] = $date_filter;
    }
    
    // Add service filter
    if ($service_filter > 0) {
        $count_query .= " AND a.service_id = ?";
        $count_params[] = $service_filter;
    }
    
    // Add staff filter
    if ($staff_filter > 0) {
        $count_query .= " AND a.staff_id = ?";
        $count_params[] = $staff_filter;
    } elseif ($staff_filter == -1) {
        $count_query .= " AND a.staff_id IS NULL";
    }
    
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Build the main query with pagination
    $query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.price,
            s.duration,
            s.category as service_category,
            u.fName as customer_name,
            u.email as customer_email,
            u.contactNo as customer_phone,
            u.regDate as customer_registered,
            st.id as staff_id,
            r.fName as staff_name,
            st.specialization as staff_specialization,
            st.experience_years as staff_experience
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg u ON a.user_id = u.regID
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN reg r ON st.user_id = r.regID
        WHERE 1=1
    ";
    
    $query_params = [];
    
    // Apply status filter
    if ($current_status == 'today') {
        $query .= " AND a.appointment_date = CURDATE()";
    } elseif ($current_status == 'upcoming') {
        $query .= " AND a.appointment_date >= CURDATE() AND a.status IN ('pending', 'confirmed')";
    } elseif ($current_status !== 'all') {
        $query .= " AND a.status = ?";
        $query_params[] = $current_status;
    }
    
    // Apply search filter
    if (!empty($search)) {
        $query .= " AND (u.fName LIKE ? OR u.email LIKE ? OR u.contactNo LIKE ? OR s.name LIKE ?)";
        $search_param = "%$search%";
        $query_params = array_merge($query_params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    // Apply date filter
    if (!empty($date_filter)) {
        $query .= " AND a.appointment_date = ?";
        $query_params[] = $date_filter;
    }
    
    // Apply service filter
    if ($service_filter > 0) {
        $query .= " AND a.service_id = ?";
        $query_params[] = $service_filter;
    }
    
    // Apply staff filter
    if ($staff_filter > 0) {
        $query .= " AND a.staff_id = ?";
        $query_params[] = $staff_filter;
    } elseif ($staff_filter == -1) {
        $query .= " AND a.staff_id IS NULL";
    }
    
    // Add ordering and pagination
    $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT ? OFFSET ?";
    $query_params[] = $records_per_page;
    $query_params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($query_params);
    $appointments = $stmt->fetchAll();
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments
    ";
    $stats_stmt = $pdo->query($stats_query);
    $stats = $stats_stmt->fetch();
    
    // Get today's count
    $today_query = "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()";
    $today_stmt = $pdo->query($today_query);
    $today_count = $today_stmt->fetch()['count'];
    
    // Get upcoming count
    $upcoming_query = "SELECT COUNT(*) as count FROM appointments WHERE appointment_date >= CURDATE() AND status IN ('pending', 'confirmed')";
    $upcoming_stmt = $pdo->query($upcoming_query);
    $upcoming_count = $upcoming_stmt->fetch()['count'];
    
} catch (PDOException $e) {
    error_log('Manage Appointments error: ' . $e->getMessage());
    $error = "Unable to load appointments. Please try again later.";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

include 'header/header-admin.php';
?>

<!-- Page Header -->
<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-calendar-alt me-2" style="color: #6f42c1;"></i> Manage Appointments</h1>
            <p class="lead mb-0">
                <?php 
                $status_labels = [
                    'all' => 'All Appointments',
                    'today' => "Today's Appointments",
                    'upcoming' => 'Upcoming Appointments',
                    'pending' => 'Pending Approvals',
                    'confirmed' => 'Confirmed Appointments',
                    'completed' => 'Completed Appointments',
                    'cancelled' => 'Cancelled Appointments'
                ];
                echo $status_labels[$current_status] ?? 'All Appointments';
                ?>
            </p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-filter me-2"></i>Status: <?php echo ucfirst($current_status); ?>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item <?php echo $current_status == 'all' ? 'active' : ''; ?>" href="?status=all">All Appointments</a></li>
                    <li><a class="dropdown-item <?php echo $current_status == 'today' ? 'active' : ''; ?>" href="?status=today">Today's Appointments</a></li>
                    <li><a class="dropdown-item <?php echo $current_status == 'upcoming' ? 'active' : ''; ?>" href="?status=upcoming">Upcoming</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?php echo $current_status == 'pending' ? 'active' : ''; ?>" href="?status=pending">Pending</a></li>
                    <li><a class="dropdown-item <?php echo $current_status == 'confirmed' ? 'active' : ''; ?>" href="?status=confirmed">Confirmed</a></li>
                    <li><a class="dropdown-item <?php echo $current_status == 'completed' ? 'active' : ''; ?>" href="?status=completed">Completed</a></li>
                    <li><a class="dropdown-item <?php echo $current_status == 'cancelled' ? 'active' : ''; ?>" href="?status=cancelled">Cancelled</a></li>
                </ul>
            </div>
            <button class="btn btn-success ms-2" onclick="refreshPage()">
                <i class="fas fa-sync-alt me-2"></i>Refresh
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card" onclick="window.location.href='?status=all'">
        <div class="stat-icon" style="background: rgba(111, 66, 193, 0.1); color: #6f42c1;">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['total'] ?? 0); ?></div>
            <div class="stat-label">Total</div>
        </div>
    </div>
    
    <div class="stat-card" onclick="window.location.href='?status=today'">
        <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($today_count); ?></div>
            <div class="stat-label">Today</div>
        </div>
    </div>
    
    <div class="stat-card" onclick="window.location.href='?status=upcoming'">
        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($upcoming_count); ?></div>
            <div class="stat-label">Upcoming</div>
        </div>
    </div>
    
    <div class="stat-card" onclick="window.location.href='?status=pending'">
        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['pending'] ?? 0); ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
    
    <div class="stat-card" onclick="window.location.href='?status=confirmed'">
        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['confirmed'] ?? 0); ?></div>
            <div class="stat-label">Confirmed</div>
        </div>
    </div>
    
    <div class="stat-card" onclick="window.location.href='?status=completed'">
        <div class="stat-icon" style="background: rgba(0, 123, 255, 0.1); color: #007bff;">
            <i class="fas fa-check-double"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['completed'] ?? 0); ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
</div>

<!-- Filters Section -->
<div class="filter-card">
    <div class="filter-header">
        <h5><i class="fas fa-filter me-2"></i> Filter Appointments</h5>
    </div>
    <div class="filter-body">
        <form method="GET" action="" id="filterForm">
            <input type="hidden" name="status" value="<?php echo $current_status; ?>">
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Customer, service, phone..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="col-md-2 mb-3">
                    <label class="form-label fw-bold">Date</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                        <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                </div>
                
                <div class="col-md-2 mb-3">
                    <label class="form-label fw-bold">Service</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-spa"></i></span>
                        <select class="form-select" name="service">
                            <option value="0">All Services</option>
                            <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['id']; ?>" <?php echo $service_filter == $service['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($service['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-2 mb-3">
                    <label class="form-label fw-bold">Staff</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                        <select class="form-select" name="staff">
                            <option value="0">All Staff</option>
                            <option value="-1" <?php echo $staff_filter == -1 ? 'selected' : ''; ?>>Unassigned</option>
                            <?php foreach ($all_staff as $staff): ?>
                            <option value="<?php echo $staff['id']; ?>" <?php echo $staff_filter == $staff['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($staff['staff_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 me-2">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                    <a href="?status=<?php echo $current_status; ?>" class="btn btn-outline-secondary" title="Clear Filters">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Appointments Table -->
<div class="table-card">
    <div class="table-header">
        <h5><i class="fas fa-list me-2"></i> <?php echo $status_labels[$current_status] ?? 'Appointments'; ?></h5>
        <div class="table-actions">
            <span class="badge bg-info"><?php echo $total_records; ?> Records</span>
            <button class="btn btn-sm btn-outline-primary ms-2" onclick="exportTableToExcel()">
                <i class="fas fa-download me-1"></i>Export
            </button>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="appointments-table" id="appointmentsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Date & Time</th>
                    <th>Amount</th>
                    <th>Staff</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="empty-state">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                            <h5>No Appointments Found</h5>
                            <p class="text-muted">Try adjusting your filters or check back later.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php $sno = $offset + 1; ?>
                    <?php foreach ($appointments as $apt): ?>
                    <tr>
                        <td><span class="fw-bold"><?php echo $sno++; ?></span></td>
                        <td>
                            <div class="customer-info">
                                <strong><?php echo htmlspecialchars($apt['customer_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($apt['customer_phone']); ?></small>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($apt['service_name']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($apt['service_category']); ?></small>
                            <br>
                            <small class="text-muted"><i class="far fa-clock me-1"></i><?php echo $apt['duration']; ?> mins</small>
                        </td>
                        <td>
                            <span class="fw-bold"><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></span>
                            <br>
                            <span class="badge bg-light text-dark"><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></span>
                        </td>
                        <td><span class="fw-bold text-primary">Rs: <?php echo number_format($apt['price'], 2); ?></span></td>
                        <td>
    <div class="staff-cell">
        <?php if (!empty($apt['staff_name'])): ?>
            <div class="staff-info">
                <span class="staff-badge">
                    <i class="fas fa-user-tie me-1"></i>
                    <?php echo htmlspecialchars($apt['staff_name']); ?>
                </span>
                <br>
                <small class="text-muted"><?php echo htmlspecialchars($apt['staff_specialization'] ?? 'General'); ?></small>
            </div>
            <div class="staff-actions mt-1">
                <button class="btn-change-staff" onclick="changeStaff(<?php echo $apt['id']; ?>, <?php echo $apt['staff_id']; ?>)" title="Change Staff">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button class="btn-remove-staff" onclick="removeStaff(<?php echo $apt['id']; ?>)" title="Remove Staff">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php else: ?>
            <span class="badge bg-secondary">Not Assigned</span>
            <?php if ($apt['status'] == 'confirmed' || $apt['status'] == 'pending'): ?>
            <br>
            <button class="btn-assign-staff mt-1" onclick="approveAppointment(<?php echo $apt['id']; ?>)">
                <i class="fas fa-user-plus me-1"></i> Assign
            </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</td>
                        <td>
                            <span class="status-badge <?php echo $apt['status']; ?>">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                            <?php if (strtotime($apt['appointment_date']) < strtotime('today') && $apt['status'] == 'pending'): ?>
                            <br><small class="text-danger">Overdue</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <!-- View Button -->
                                <button class="btn-icon" onclick="viewAppointment(<?php echo $apt['id']; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <!-- Approve Button (for pending) -->
                                <?php if ($apt['status'] == 'pending'): ?>
                                <button class="btn-icon success" onclick="approveAppointment(<?php echo $apt['id']; ?>)" title="Approve">
                                    <i class="fas fa-check"></i>
                                </button>
                                <?php endif; ?>
                                
                                <!-- Complete Button (for confirmed) -->
                                <?php if ($apt['status'] == 'confirmed'): ?>
                                <button class="btn-icon info" onclick="completeAppointment(<?php echo $apt['id']; ?>)" title="Mark Completed">
                                    <i class="fas fa-check-double"></i>
                                </button>
                                <?php endif; ?>
                                
                                <!-- Reject Button (for pending) -->
                                <?php if ($apt['status'] == 'pending'): ?>
                                <button class="btn-icon danger" onclick="rejectAppointment(<?php echo $apt['id']; ?>)" title="Reject">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                                
                                <!-- Reschedule Button (for confirmed/completed) -->
                                <?php if (in_array($apt['status'], ['confirmed', 'completed'])): ?>
                                <button class="btn-icon warning" onclick="rescheduleAppointment(<?php echo $apt['id']; ?>)" title="Reschedule">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <?php endif; ?>
                                
                                <!-- Edit Button -->
                                <button class="btn-icon" onclick="editAppointment(<?php echo $apt['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <!-- Print Button -->
                                <button class="btn-icon" onclick="printAppointment(<?php echo $apt['id']; ?>)" title="Print">
                                    <i class="fas fa-print"></i>
                                </button>
                                
                                <!-- Delete Button -->
                                <button class="btn-icon text-danger" onclick="deleteAppointment(<?php echo $apt['id']; ?>)" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="table-footer">
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mb-0">
                <?php
                // Build query string for pagination links
                $query_params = [];
                if ($current_status !== 'all') $query_params['status'] = $current_status;
                if (!empty($search)) $query_params['search'] = $search;
                if (!empty($date_filter)) $query_params['date'] = $date_filter;
                if ($service_filter > 0) $query_params['service'] = $service_filter;
                if ($staff_filter != 0) $query_params['staff'] = $staff_filter;
                
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
        <div class="text-center text-muted mt-2">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[title]').tooltip({ boundary: 'window' });
    
    // Toggle sidebar on mobile
    $('#sidebarToggle').click(function() {
        $('#sidebar').toggleClass('active');
        $('#mainContent').toggleClass('active');
    });

    // Close sidebar when clicking outside on mobile
    $(document).click(function(event) {
        if ($(window).width() <= 768) {
            if (!$(event.target).closest('#sidebar').length && 
                !$(event.target).is('#sidebarToggle') &&
                $('#sidebar').hasClass('active')) {
                $('#sidebar').removeClass('active');
                $('#mainContent').removeClass('active');
            }
        }
    });
});

// ============================================
// APPOINTMENT MANAGEMENT FUNCTIONS
// ============================================

// View appointment details
function viewAppointment(appointmentId) {
    if (!appointmentId) {
        Swal.fire('Error', 'Invalid appointment ID', 'error');
        return;
    }
    
    showLoading();
    
    $.ajax({
        url: 'ajax/get-appointment-details.php',
        method: 'POST',
        data: { id: appointmentId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success && response.appointment) {
                const apt = response.appointment;
                
                let staffHtml = '';
                if (apt.staff_name) {
                    staffHtml = `
                        <div class="detail-row">
                            <span class="label">Staff:</span>
                            <span class="value">${escapeHtml(apt.staff_name)} (${escapeHtml(apt.staff_specialization || 'General')})</span>
                        </div>
                    `;
                }
                
                Swal.fire({
                    title: `Appointment #${apt.id}`,
                    html: `
                        <div class="details-view">
                            <div class="detail-row">
                                <span class="label">Customer:</span>
                                <span class="value">${escapeHtml(apt.customer_name)}<br><small>${escapeHtml(apt.customer_phone)}</small></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Service:</span>
                                <span class="value">${escapeHtml(apt.service_name)} (${apt.service_duration} mins)</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Date & Time:</span>
                                <span class="value">${formatDate(apt.appointment_date)} at ${formatTime(apt.appointment_time)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Amount:</span>
                                <span class="value price">Rs: ${parseFloat(apt.service_price).toFixed(2)}</span>
                            </div>
                            ${staffHtml}
                            <div class="detail-row">
                                <span class="label">Status:</span>
                                <span class="value"><span class="status-badge ${apt.status}">${apt.status}</span></span>
                            </div>
                            ${apt.notes ? `<div class="detail-row"><span class="label">Notes:</span><span class="value">${escapeHtml(apt.notes)}</span></div>` : ''}
                            <div class="detail-row">
                                <span class="label">Booked:</span>
                                <span class="value">${formatDateTime(apt.created_at)}</span>
                            </div>
                        </div>
                    `,
                    showCloseButton: true,
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#6f42c1',
                    width: '600px'
                });
            } else {
                Swal.fire('Error', response.message || 'Could not load details', 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('AJAX Error:', error);
            Swal.fire('Error', 'Failed to load appointment details', 'error');
        }
    });
}

// Update appointment status
function updateAppointmentStatus(appointmentId, status) {
    let actionText = '';
    let confirmColor = '';
    
    if (status === 'confirmed') {
        actionText = 'approve';
        confirmColor = '#28a745';
    } else if (status === 'cancelled') {
        actionText = 'reject';
        confirmColor = '#dc3545';
    } else if (status === 'completed') {
        actionText = 'mark as completed';
        confirmColor = '#17a2b8';
    } else {
        actionText = 'update';
        confirmColor = '#6f42c1';
    }
    
    Swal.fire({
        title: `Confirm ${actionText}`,
        text: `Are you sure you want to ${actionText} this appointment?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, ${actionText}`,
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: 'ajax/update-appointment-status.php',
                method: 'POST',
                data: { id: appointmentId, status: status },
                dataType: 'json',
                timeout: 10000
            }).then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Failed to update status');
                }
                return response;
            }).catch(error => {
                Swal.showValidationMessage(error.message);
                throw error;
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value && result.value.success) {
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: `Appointment ${actionText}ed successfully.`,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    });
}

// Approve appointment with staff selection
function approveAppointment(appointmentId) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-available-staff-for-approval.php',
        method: 'POST',
        data: { appointment_id: appointmentId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                const availableStaff = response.available_staff || [];
                const appointment = response.appointment;
                
                if (availableStaff.length > 0) {
                    let staffHtml = '<div class="staff-selection-list" style="max-height: 400px; overflow-y: auto;">';
                    
                    availableStaff.forEach(staff => {
                        staffHtml += `
                            <div class="staff-option" onclick="selectStaffAndApprove(${appointmentId}, ${staff.id}, '${staff.name}')">
                                <div class="staff-avatar">${staff.name.charAt(0)}</div>
                                <div class="staff-info">
                                    <strong>${staff.name}</strong>
                                    <p class="mb-0 small">${staff.specialization || 'General'}</p>
                                    <p class="mb-0 small text-muted">Experience: ${staff.experience || 0} years</p>
                                </div>
                                <div class="staff-select">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </div>
                        `;
                    });
                    
                    staffHtml += `
                        <div class="text-center mt-3">
                            <button class="btn btn-outline-secondary btn-sm" onclick="approveWithoutStaff(${appointmentId})">
                                Approve Without Staff
                            </button>
                        </div>
                    </div>`;
                    
                    Swal.fire({
                        title: 'Assign Staff & Approve',
                        html: staffHtml,
                        showConfirmButton: false,
                        showCloseButton: true,
                        width: '500px'
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No Staff Available',
                        text: 'No staff members are available at this time. Would you like to approve without staff?',
                        showCancelButton: true,
                        confirmButtonText: 'Approve Without Staff',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#28a745'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            updateAppointmentStatus(appointmentId, 'confirmed');
                        }
                    });
                }
            } else {
                // If can't get staff, just approve directly
                updateAppointmentStatus(appointmentId, 'confirmed');
            }
        },
        error: function() {
            hideLoading();
            updateAppointmentStatus(appointmentId, 'confirmed');
        }
    });
}

// Select staff and approve
function selectStaffAndApprove(appointmentId, staffId, staffName) {
    Swal.close();
    showLoading();
    
    $.ajax({
        url: 'ajax/assign-staff-to-appointment.php',
        method: 'POST',
        data: {
            appointment_id: appointmentId,
            staff_id: staffId,
            action: 'assign'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Now approve the appointment
                $.ajax({
                    url: 'ajax/update-appointment-status.php',
                    method: 'POST',
                    data: { id: appointmentId, status: 'confirmed' },
                    dataType: 'json',
                    success: function(approveResponse) {
                        hideLoading();
                        if (approveResponse.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Approved!',
                                html: `Appointment approved and assigned to <strong>${staffName}</strong>`,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', approveResponse.message || 'Failed to approve', 'error');
                        }
                    },
                    error: function() {
                        hideLoading();
                        Swal.fire('Error', 'Failed to approve appointment', 'error');
                    }
                });
            } else {
                hideLoading();
                Swal.fire('Error', response.message || 'Failed to assign staff', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to assign staff', 'error');
        }
    });
}

// Approve without staff
function approveWithoutStaff(appointmentId) {
    Swal.close();
    updateAppointmentStatus(appointmentId, 'confirmed');
}

// Reject appointment
function rejectAppointment(appointmentId) {
    updateAppointmentStatus(appointmentId, 'cancelled');
}

// Complete appointment
function completeAppointment(appointmentId) {
    updateAppointmentStatus(appointmentId, 'completed');
}

// Reschedule appointment
function rescheduleAppointment(appointmentId) {
    window.location.href = 'reschedule-appointment.php?id=' + appointmentId;
}

// Edit appointment
function editAppointment(appointmentId) {
    window.location.href = 'edit-appointment.php?id=' + appointmentId;
}

// Print appointment
function printAppointment(appointmentId) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-appointment-details.php',
        method: 'POST',
        data: { id: appointmentId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success && response.appointment) {
                const apt = response.appointment;
                printSingleAppointment(apt);
            } else {
                Swal.fire('Error', 'Could not load appointment details', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to load appointment details', 'error');
        }
    });
}

// Print single appointment
function printSingleAppointment(appointment) {
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Appointment #${appointment.id}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #6f42c1; padding-bottom: 20px; }
                .header h1 { color: #6f42c1; margin: 0; }
                .details { margin: 20px 0; }
                .detail-row { display: flex; padding: 8px 0; border-bottom: 1px solid #eee; }
                .label { width: 150px; font-weight: bold; color: #555; }
                .value { flex: 1; }
                .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
                .status-badge.pending { background: #fff3cd; color: #856404; }
                .status-badge.confirmed { background: #d4edda; color: #155724; }
                .status-badge.completed { background: #cce5ff; color: #004085; }
                .status-badge.cancelled { background: #f8d7da; color: #721c24; }
                .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; padding-top: 20px; }
                .price { color: #6f42c1; font-weight: bold; font-size: 16px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Salon Elegance</h1>
                <p>Appointment Details</p>
            </div>
            
            <div class="details">
                <div class="detail-row">
                    <div class="label">Appointment ID:</div>
                    <div class="value">#${appointment.id}</div>
                </div>
                <div class="detail-row">
                    <div class="label">Customer:</div>
                    <div class="value">${escapeHtml(appointment.customer_name)}</div>
                </div>
                <div class="detail-row">
                    <div class="label">Phone:</div>
                    <div class="value">${escapeHtml(appointment.customer_phone)}</div>
                </div>
                <div class="detail-row">
                    <div class="label">Service:</div>
                    <div class="value">${escapeHtml(appointment.service_name)}</div>
                </div>
                <div class="detail-row">
                    <div class="label">Date:</div>
                    <div class="value">${formatDate(appointment.appointment_date)}</div>
                </div>
                <div class="detail-row">
                    <div class="label">Time:</div>
                    <div class="value">${formatTime(appointment.appointment_time)}</div>
                </div>
                <div class="detail-row">
                    <div class="label">Duration:</div>
                    <div class="value">${appointment.service_duration} minutes</div>
                </div>
                <div class="detail-row">
                    <div class="label">Price:</div>
                    <div class="value price">Rs: ${parseFloat(appointment.service_price).toFixed(2)}</div>
                </div>
                ${appointment.staff_name ? `
                <div class="detail-row">
                    <div class="label">Staff:</div>
                    <div class="value">${escapeHtml(appointment.staff_name)}</div>
                </div>
                ` : ''}
                <div class="detail-row">
                    <div class="label">Status:</div>
                    <div class="value"><span class="status-badge ${appointment.status}">${appointment.status}</span></div>
                </div>
                ${appointment.notes ? `
                <div class="detail-row">
                    <div class="label">Notes:</div>
                    <div class="value">${escapeHtml(appointment.notes)}</div>
                </div>
                ` : ''}
            </div>
            
            <div class="footer">
                <p>Thank you for choosing Salon Elegance</p>
                <p>This is a computer-generated document. No signature required.</p>
            </div>
            
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

// Delete appointment
function deleteAppointment(appointmentId) {
    Swal.fire({
        title: 'Delete Appointment?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: 'ajax/delete-appointment.php',
                method: 'POST',
                data: { id: appointmentId },
                dataType: 'json',
                timeout: 10000
            }).then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Failed to delete appointment');
                }
                return response;
            }).catch(error => {
                Swal.showValidationMessage(error.message);
                throw error;
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value && result.value.success) {
            Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: 'Appointment has been deleted.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    });
}

// Change staff
function changeStaff(appointmentId, currentStaffId) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-available-staff-for-approval.php',
        method: 'POST',
        data: { appointment_id: appointmentId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                const availableStaff = response.available_staff || [];
                
                if (availableStaff.length > 0) {
                    let staffOptions = '<select class="form-select" id="changeStaffSelect" style="margin-bottom: 15px;">';
                    staffOptions += '<option value="">-- Select Staff --</option>';
                    
                    availableStaff.forEach(staff => {
                        const selected = staff.id == currentStaffId ? 'selected' : '';
                        staffOptions += `<option value="${staff.id}" ${selected}>${staff.name} (${staff.specialization || 'General'}) - ${staff.experience || 0} yrs exp</option>`;
                    });
                    
                    staffOptions += '</select>';
                    
                    Swal.fire({
                        title: 'Change Staff Assignment',
                        html: staffOptions,
                        showCancelButton: true,
                        confirmButtonText: 'Update Staff',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#6f42c1',
                        preConfirm: () => {
                            const newStaffId = $('#changeStaffSelect').val();
                            if (!newStaffId) {
                                Swal.showValidationMessage('Please select a staff member');
                                return false;
                            }
                            return newStaffId;
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const newStaffId = result.value;
                            const selectedStaff = availableStaff.find(s => s.id == newStaffId);
                            
                            showLoading();
                            
                            $.ajax({
                                url: 'ajax/assign-staff-to-appointment.php',
                                method: 'POST',
                                data: {
                                    appointment_id: appointmentId,
                                    staff_id: newStaffId,
                                    action: 'assign'
                                },
                                dataType: 'json',
                                success: function(response) {
                                    hideLoading();
                                    
                                    if (response.success) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Staff Updated',
                                            text: `Staff changed to ${selectedStaff.name}`,
                                            timer: 1500,
                                            showConfirmButton: false
                                        }).then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        Swal.fire('Error', response.message, 'error');
                                    }
                                },
                                error: function() {
                                    hideLoading();
                                    Swal.fire('Error', 'Failed to update staff', 'error');
                                }
                            });
                        }
                    });
                } else {
                    Swal.fire('Info', 'No other staff available at this time', 'info');
                }
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to load staff', 'error');
        }
    });
}

// Remove staff
function removeStaff(appointmentId) {
    Swal.fire({
        title: 'Remove Staff Assignment?',
        text: 'Are you sure you want to remove the staff assignment?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, remove',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: 'ajax/assign-staff-to-appointment.php',
                method: 'POST',
                data: {
                    appointment_id: appointmentId,
                    action: 'remove'
                },
                dataType: 'json',
                timeout: 10000
            }).then(response => {
                if (!response.success) {
                    throw new Error(response.message);
                }
                return response;
            }).catch(error => {
                Swal.showValidationMessage(error.message);
                throw error;
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value && result.value.success) {
            Swal.fire({
                icon: 'success',
                title: 'Removed',
                text: 'Staff assignment removed',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    });
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
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

function formatDateTime(dateTimeStr) {
    if (!dateTimeStr) return 'N/A';
    const date = new Date(dateTimeStr);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function showLoading() {
    Swal.fire({
        title: 'Loading...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

function hideLoading() {
    Swal.close();
}

function refreshPage() {
    location.reload();
}

function exportTableToExcel() {
    const table = document.getElementById('appointmentsTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    // Headers
    const headers = ['#', 'Customer', 'Service', 'Date & Time', 'Amount', 'Staff', 'Status'];
    csv.push(headers.join(','));
    
    // Data rows
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.querySelectorAll('td');
        if (cells.length > 0) {
            const rowData = [
                cells[0]?.innerText || '',
                cells[1]?.innerText.replace(/,/g, ' ') || '',
                cells[2]?.innerText.replace(/,/g, ' ') || '',
                cells[3]?.innerText.replace(/,/g, ' ') || '',
                cells[4]?.innerText.replace(/,/g, ' ') || '',
                cells[5]?.innerText.replace(/,/g, ' ') || '',
                cells[6]?.innerText.replace(/,/g, ' ') || ''
            ];
            csv.push(rowData.join(','));
        }
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'appointments_<?php echo date('Y-m-d'); ?>.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// Staff Management Functions
function viewStaffDetails(staffId) {
    $.ajax({
        url: 'ajax/get-staff-details.php',
        method: 'POST',
        data: { id: staffId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    title: response.staff_name,
                    html: `
                        <div class="text-start">
                            <p><strong>Email:</strong> ${escapeHtml(response.email)}</p>
                            <p><strong>Specialization:</strong> ${escapeHtml(response.specialization || 'General')}</p>
                            <p><strong>Experience:</strong> ${response.experience_years || 0} years</p>
                            <p><strong>Bio:</strong> ${response.bio ? escapeHtml(response.bio) : 'No bio available'}</p>
                        </div>
                    `,
                    showCloseButton: true,
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#6f42c1'
                });
            } else {
                Swal.fire('Error', 'Could not load staff details', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to load staff details', 'error');
        }
    });
}

function editStaff(staffId) {
    window.location.href = `edit-staff.php?id=${staffId}`;
}
</script>

<style>
/* ============================================
   MANAGE APPOINTMENTS STYLES
   ============================================ */

:root {
    --primary: #6f42c1;
    --primary-light: #9b6fe0;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
    --info: #17a2b8;
    --dark: #343a40;
    --gray: #6c757d;
    --light: #f8f9fa;
    --border: #e9ecef;
}

/* Dashboard Header */
.dashboard-header {
    background: white;
    border-radius: 16px;
    padding: 25px 30px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border-left: 5px solid var(--primary);
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
    padding: 20px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
    transition: all 0.3s;
    border: 1px solid var(--border);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border-color: var(--primary);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.stat-details {
    flex: 1;
}

.stat-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

.stat-label {
    font-size: 12px;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}

.filter-header {
    padding: 15px 20px;
    background: var(--light);
    border-bottom: 1px solid var(--border);
}

.filter-header h5 {
    margin: 0;
    color: var(--dark);
    font-weight: 600;
}

.filter-body {
    padding: 20px;
}

/* Table Card */
.table-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 25px;
}

.table-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
}

.table-header h5 {
    margin: 0;
    color: var(--dark);
    font-weight: 600;
}

.table-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Appointments Table */
.appointments-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
}

.appointments-table th {
    background: var(--light);
    padding: 15px;
    font-weight: 600;
    color: var(--dark);
    font-size: 13px;
    text-align: left;
    white-space: nowrap;
    border-bottom: 2px solid var(--border);
}

.appointments-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.appointments-table tbody tr:hover {
    background: var(--light);
}

/* Customer Info */
.customer-info {
    line-height: 1.4;
}

.customer-info strong {
    font-size: 14px;
    color: var(--dark);
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.confirmed { background: #d4edda; color: #155724; }
.status-badge.completed { background: #cce5ff; color: #004085; }
.status-badge.cancelled { background: #f8d7da; color: #721c24; }

/* Staff Badge */
.staff-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    min-width: 200px;
}

.btn-icon {
    width: 34px;
    height: 34px;
    border: none;
    border-radius: 8px;
    background: var(--light);
    color: var(--gray);
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.btn-icon:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

.btn-icon.success:hover { background: var(--success); }
.btn-icon.danger:hover { background: var(--danger); }
.btn-icon.info:hover { background: var(--info); }
.btn-icon.warning:hover { background: var(--warning); color: var(--dark); }

/* Table Footer */
.table-footer {
    padding: 20px;
    border-top: 1px solid var(--border);
    background: white;
}

/* Pagination */
.pagination {
    margin: 0;
}

.page-link {
    color: var(--primary);
    border: none;
    margin: 0 3px;
    border-radius: 8px !important;
    padding: 8px 12px;
    font-size: 14px;
}

.page-item.active .page-link {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.page-item.disabled .page-link {
    color: var(--gray);
    background: var(--light);
}

/* Details View */
.details-view {
    text-align: left;
    padding: 10px;
}

.detail-row {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}

.detail-row:last-child { border-bottom: none; }

.detail-row .label {
    width: 100px;
    font-weight: 600;
    color: var(--gray);
}

.detail-row .value {
    flex: 1;
    color: var(--dark);
}

.detail-row .value.price {
    color: var(--primary);
    font-weight: 700;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px;
}

.empty-state i {
    font-size: 48px;
    color: var(--border);
    margin-bottom: 15px;
}

.empty-state h5 {
    color: var(--dark);
    margin-bottom: 8px;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-buttons {
        min-width: 160px;
    }
    
    .btn-icon {
        width: 30px;
        height: 30px;
        font-size: 12px;
    }
    
    .pagination .page-link {
        padding: 5px 8px;
        font-size: 12px;
    }
}
/* Staff Selection Modal */
.staff-selection-modal .swal2-html-container {
    max-height: 70vh;
    overflow-y: auto;
    padding: 10px;
}

.staff-category {
    font-size: 14px;
    font-weight: 600;
    margin: 15px 0 10px;
    padding-bottom: 5px;
    border-bottom: 2px solid;
}

.staff-category.available {
    color: #28a745;
    border-bottom-color: #28a745;
}

.staff-category.unavailable {
    color: #dc3545;
    border-bottom-color: #dc3545;
}

.staff-card {
    display: flex;
    align-items: center;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    border: 2px solid transparent;
}

.staff-card.available {
    background: #f8f9fa;
}

.staff-card.available:hover {
    background: #e9ecef;
    border-color: #6f42c1;
    transform: translateX(5px);
}

.staff-card.unavailable {
    background: #f8f9fa;
    opacity: 0.7;
    cursor: not-allowed;
}

.staff-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6f42c1, #9b6fe0);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 20px;
    margin-right: 15px;
}

.staff-details {
    flex: 1;
}

.staff-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 3px;
}

.staff-specialization {
    font-size: 12px;
    color: #6f42c1;
    margin-bottom: 5px;
}

.staff-meta {
    display: flex;
    gap: 15px;
    font-size: 11px;
    color: #6c757d;
}

.staff-meta i {
    margin-right: 3px;
}

.staff-select {
    color: #6f42c1;
    font-size: 18px;
    margin-left: 10px;
}

.staff-status {
    margin-left: 10px;
}

.no-staff {
    text-align: center;
    padding: 20px;
    color: #dc3545;
    background: #f8d7da;
    border-radius: 10px;
}

.appointment-summary {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #6f42c1;
}

/* Staff Cell in Table */
.staff-cell {
    min-width: 150px;
}

.staff-actions {
    display: flex;
    gap: 5px;
    margin-top: 5px;
}

.btn-change-staff, .btn-remove-staff, .btn-assign-staff {
    border: none;
    background: transparent;
    padding: 4px 8px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 11px;
}

.btn-change-staff {
    color: #6f42c1;
}

.btn-change-staff:hover {
    background: #6f42c1;
    color: white;
}

.btn-remove-staff {
    color: #dc3545;
}

.btn-remove-staff:hover {
    background: #dc3545;
    color: white;
}

.btn-assign-staff {
    background: #6f42c1;
    color: white;
    border: none;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
}

.btn-assign-staff:hover {
    background: #5a32a0;
}
</style>
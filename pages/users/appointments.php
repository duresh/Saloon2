<?php
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

// Include database connection
require_once '../../includes/dbcon.php';

// Initialize variables
$error = '';
$success = '';
$appointments = [];
$stats = [];

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

try {
    $pdo = getPDOConnection();
    $user_id = $_SESSION['user_id'];
    
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
    
    // Get filter parameters
    $filter_status = $_GET['status'] ?? 'all';
    $filter_date = $_GET['date'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build count query for pagination
    $count_query = "
        SELECT COUNT(*) as total
        FROM appointments a
        INNER JOIN services s ON a.service_id = s.id
        INNER JOIN reg u ON a.user_id = u.regID
        WHERE a.user_id = ?
    ";
    
    $count_params = [$user_id];
    
    if ($filter_status !== 'all') {
        $count_query .= " AND a.status = ?";
        $count_params[] = $filter_status;
    }
    
    if (!empty($filter_date)) {
        $count_query .= " AND a.appointment_date = ?";
        $count_params[] = $filter_date;
    }
    
    if (!empty($search)) {
        $count_query .= " AND (s.name LIKE ? OR a.notes LIKE ?)";
        $search_param = "%$search%";
        $count_params[] = $search_param;
        $count_params[] = $search_param;
    }
    
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Build main query with pagination
    $query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.price as service_price,
            s.duration as service_duration,
            s.category as service_category,
            u.fName as customer_name,
            u.email as customer_email,
            u.contactNo as customer_phone,
            st.id as staff_id,
            r.fName as staff_name,
            st.specialization as staff_specialization
        FROM appointments a
        INNER JOIN services s ON a.service_id = s.id
        INNER JOIN reg u ON a.user_id = u.regID
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN reg r ON st.user_id = r.regID
        WHERE a.user_id = ?
    ";
    
    $params = [$user_id];
    
    if ($filter_status !== 'all') {
        $query .= " AND a.status = ?";
        $params[] = $filter_status;
    }
    
    if (!empty($filter_date)) {
        $query .= " AND a.appointment_date = ?";
        $params[] = $filter_date;
    }
    
    if (!empty($search)) {
        $query .= " AND (s.name LIKE ? OR a.notes LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT ? OFFSET ?";
    $params[] = $records_per_page;
    $params[] = $offset;
    
    $appointments_stmt = $pdo->prepare($query);
    $appointments_stmt->execute($params);
    $appointments = $appointments_stmt->fetchAll();
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_appointments,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            COUNT(DISTINCT DATE(appointment_date)) as active_days,
            MIN(appointment_date) as first_appointment,
            MAX(appointment_date) as last_appointment
        FROM appointments 
        WHERE user_id = ?
    ";
    
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch();
    
    // Get upcoming appointments count
    $upcoming_query = "
        SELECT COUNT(*) as upcoming_count
        FROM appointments 
        WHERE user_id = ? 
        AND appointment_date >= CURDATE() 
        AND status IN ('pending', 'confirmed')
    ";
    $upcoming_stmt = $pdo->prepare($upcoming_query);
    $upcoming_stmt->execute([$user_id]);
    $upcoming = $upcoming_stmt->fetch();
    $stats['upcoming_count'] = $upcoming['upcoming_count'] ?? 0;
    
    // Handle appointment actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $appointment_id = $_POST['appointment_id'] ?? '';
        
        if ($action === 'cancel' && $appointment_id) {
            try {
                // Check if appointment can be cancelled
                $check_query = "
                    SELECT * FROM appointments 
                    WHERE id = ? AND user_id = ? 
                    AND status IN ('pending', 'confirmed')
                    AND appointment_date >= CURDATE()
                ";
                $check_stmt = $pdo->prepare($check_query);
                $check_stmt->execute([$appointment_id, $user_id]);
                
                if ($check_stmt->rowCount() > 0) {
                    $update_query = "UPDATE appointments SET status = 'cancelled' WHERE id = ?";
                    $update_stmt = $pdo->prepare($update_query);
                    
                    if ($update_stmt->execute([$appointment_id])) {
                        $success = 'Appointment cancelled successfully.';
                        // Refresh appointments
                        header('Location: appointments.php?cancelled=1');
                        exit();
                    } else {
                        $error = 'Failed to cancel appointment.';
                    }
                } else {
                    $error = 'This appointment cannot be cancelled.';
                }
            } catch (PDOException $e) {
                error_log('Cancel appointment error: ' . $e->getMessage());
                $error = 'Failed to cancel appointment.';
            }
        }
        
        if ($action === 'reschedule' && $appointment_id) {
            header("Location: reschedule-appointment.php?id=$appointment_id");
            exit();
        }
    }
    
} catch (PDOException $e) {
    error_log('My appointments page error: ' . $e->getMessage());
    $error = 'Unable to load appointments. Please try again later.';
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

include 'header/headerBooking.php';
?>
<!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-calendar-alt me-2"></i> My Appointments</h1>
                    <p class="lead mb-0">View and manage your salon appointments</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-primary me-2" onclick="goToDashboard()">
                            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                        </button>
                        <button class="btn btn-outline-secondary" onclick="refreshPage()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Success Messages -->
        <?php if (isset($_GET['cancelled'])): ?>
        <div class="alert alert-success alert-custom alert-dismissible fade show success-pulse" role="alert">
            <i class="fas fa-check-circle me-2"></i> Appointment cancelled successfully!
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['booked'])): ?>
        <div class="alert alert-success alert-custom alert-dismissible fade show success-pulse" role="alert">
            <i class="fas fa-check-circle me-2"></i> Appointment booked successfully!
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['rescheduled'])): ?>
        <div class="alert alert-success alert-custom alert-dismissible fade show success-pulse" role="alert">
            <i class="fas fa-check-circle me-2"></i> Appointment rescheduled successfully!
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['total_appointments'] ?? 0; ?></h3>
                        <p>Total Appointments</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['upcoming_count'] ?? 0; ?></h3>
                        <p>Upcoming</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['completed_count'] ?? 0; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['pending_count'] ?? 0; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filters-card mb-4">
            <div class="row align-items-center">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="filter-group">
                        <label class="filter-label">Filter by Status</label>
                        <select class="filter-select" id="statusFilter">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Appointments</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="filter-group">
                        <label class="filter-label">Filter by Date</label>
                        <input type="text" class="filter-select date-filter" id="dateFilter" placeholder="Select Date" value="<?php echo htmlspecialchars($filter_date); ?>">
                    </div>
                </div>
                
                <div class="col-md-5">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <div class="search-wrapper">
                            <input type="text" class="filter-select" id="searchInput" placeholder="Search by service or notes..." value="<?php echo htmlspecialchars($search); ?>">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <button class="btn-apply-filters" id="applyFilters">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                    <button class="btn-reset-filters" id="resetFilters">
                        <i class="fas fa-undo me-2"></i> Reset
                    </button>
                    <button class="btn btn-success ms-2" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-2"></i> Export PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- Appointments List -->
        <div class="appointments-container">
            <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Appointments Found</h3>
                <p><?php echo $filter_status !== 'all' || !empty($filter_date) || !empty($search) ? 'Try adjusting your filters.' : 'Book your first appointment today!'; ?></p>
                <?php if ($filter_status !== 'all' || !empty($filter_date) || !empty($search)): ?>
                <button class="btn-primary mt-3" onclick="resetAllFilters()">
                    <i class="fas fa-undo me-2"></i> Reset Filters
                </button>
                <?php else: ?>
                <a href="book-appointment.php" class="btn-primary mt-3">
                    <i class="fas fa-plus-circle me-2"></i> Book Appointment
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            
            <!-- Timeline View for Upcoming Appointments -->
            <?php 
            $upcoming_appointments = array_filter($appointments, function($app) {
                return in_array($app['status'], ['pending', 'confirmed']) && strtotime($app['appointment_date']) >= strtotime('today');
            });
            
            if (!empty($upcoming_appointments)): 
            ?>
            <div class="timeline-section mb-5">
                <h4 class="section-title">
                    <i class="fas fa-clock me-2"></i> Upcoming Appointments
                </h4>
                <div class="timeline">
                    <?php foreach ($upcoming_appointments as $appointment): ?>
                    <div class="timeline-item <?php echo $appointment['status']; ?>">
                        <div class="timeline-date">
                            <span class="day"><?php echo date('d', strtotime($appointment['appointment_date'])); ?></span>
                            <span class="month"><?php echo date('M', strtotime($appointment['appointment_date'])); ?></span>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h5><?php echo htmlspecialchars($appointment['service_name']); ?></h5>
                                <span class="status-badge <?php echo $appointment['status']; ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>
                            <div class="timeline-details">
                                <span class="time">
                                    <i class="far fa-clock"></i> <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                </span>
                                <span class="duration">
                                    <i class="fas fa-hourglass-half"></i> <?php echo $appointment['service_duration']; ?> mins
                                </span>
                                <span class="price">
                                    <i class="fas fa-tag"></i> Rs: <?php echo number_format($appointment['service_price'], 2); ?>
                                </span>
                                <?php if (!empty($appointment['staff_name'])): ?>
                                <span class="staff">
                                    <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($appointment['staff_name']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($appointment['notes'])): ?>
                            <div class="timeline-notes">
                                <i class="fas fa-sticky-note me-2"></i> <?php echo htmlspecialchars($appointment['notes']); ?>
                            </div>
                            <?php endif; ?>
                            <div class="timeline-actions">
                                <button class="action-btn view-details" onclick="viewDetails(<?php echo $appointment['id']; ?>)">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                                <?php if ($appointment['status'] !== 'cancelled'): ?>
                                <button class="action-btn reschedule" onclick="rescheduleAppointment(<?php echo $appointment['id']; ?>)">
                                    <i class="fas fa-calendar-alt"></i> Reschedule
                                </button>
                                <?php endif; ?>
                                <?php if (in_array($appointment['status'], ['pending', 'confirmed']) && strtotime($appointment['appointment_date']) > strtotime('today')): ?>
                                <button class="action-btn cancel" onclick="cancelAppointment(<?php echo $appointment['id']; ?>)">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Past Appointments -->
            <?php 
            $past_appointments = array_filter($appointments, function($app) {
                return in_array($app['status'], ['completed', 'cancelled']) || strtotime($app['appointment_date']) < strtotime('today');
            });
            
            if (!empty($past_appointments)): 
            ?>
            <div class="past-appointments-section">
                <h4 class="section-title">
                    <i class="fas fa-history me-2"></i> Past Appointments (<?php echo count($past_appointments); ?>)
                </h4>
                
                <div class="table-responsive">
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Service</th>
                                <th>Staff</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($past_appointments as $appointment): ?>
                            <tr>
                                <td><span class="fw-600"><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></span></td>
                                <td><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                <td>
                                    <div class="service-info">
                                        <strong><?php echo htmlspecialchars($appointment['service_name']); ?></strong>
                                        <small class="d-block text-muted"><?php echo $appointment['service_category']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($appointment['staff_name'])): ?>
                                        <span class="staff-badge">
                                            <i class="fas fa-user-tie me-1"></i>
                                            <?php echo htmlspecialchars($appointment['staff_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>Rs: <?php echo number_format($appointment['service_price'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td>
            <?php 
            // Check if already rated
            $rated_query = "SELECT rating, comments FROM staff_ratings WHERE appointment_id = ?";
            $rated_stmt = $pdo->prepare($rated_query);
            $rated_stmt->execute([$appointment['id']]);
            $rating = $rated_stmt->fetch();
            ?>
            <?php if ($rating): ?>
                <div class="rating-display">
                    <div class="stars-display">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= $rating['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <small class="text-muted d-block"><?php echo htmlspecialchars(substr($rating['comments'], 0, 50)); ?></small>
                </div>
            <?php elseif ($appointment['status'] == 'completed'): ?>
                <button class="btn-rate" onclick="rateStaff(<?php echo $appointment['id']; ?>, <?php echo $appointment['staff_id']; ?>, '<?php echo htmlspecialchars($appointment['staff_name']); ?>')">
                    <i class="fas fa-star me-1"></i>Rate Staff
                </button>
            <?php else: ?>
                <span class="text-muted">Not available</span>
            <?php endif; ?>
        </td>
                                <td>
                                    <button class="table-action-btn" onclick="viewDetails(<?php echo $appointment['id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($appointment['status'] === 'completed'): ?>
                                    <button class="table-action-btn" onclick="bookAgain(<?php echo $appointment['service_id']; ?>)" title="Book Again">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($appointment['status'] === 'completed'): ?>
                                    <button class="table-action-btn" onclick="printAppointment(<?php echo $appointment['id']; ?>)" title="Print">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper mt-4">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php
                        $query_params = [];
                        if ($filter_status !== 'all') $query_params['status'] = $filter_status;
                        if (!empty($filter_date)) $query_params['date'] = $filter_date;
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
                <div class="text-center text-muted mt-2">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> appointments
                </div>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions mt-4">
            <a href="book-appointment.php" class="quick-action-btn primary">
                <i class="fas fa-plus-circle"></i>
                <span>Book New</span>
            </a>
            <button class="quick-action-btn success" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i>
                <span>Export PDF</span>
            </button>
            <button class="quick-action-btn info" onclick="printAppointments()">
                <i class="fas fa-print"></i>
                <span>Print</span>
            </button>
            <button class="quick-action-btn warning" onclick="refreshPage()">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh</span>
            </button>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsModalBody">
                    Loading...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printModalBtn">Print</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="mt-3 text-primary fw-bold">Processing...</div>
    </div>

<?php include 'footer/footer.php'; ?>
    
<script>
$(document).ready(function() {
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
    
    // Initialize date filter
    flatpickr(".date-filter", {
        dateFormat: "Y-m-d",
        maxDate: "today",
        allowInput: true
    });
    
    // Apply filters
    $('#applyFilters').click(function() {
        applyFilters();
    });
    
    // Reset filters
    $('#resetFilters').click(function() {
        resetAllFilters();
    });
    
    // Handle enter key in search
    $('#searchInput').keypress(function(e) {
        if (e.which === 13) {
            applyFilters();
        }
    });
    
    // Check for URL parameters on load
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('status') || urlParams.has('date') || urlParams.has('search')) {
        $('#statusFilter').val(urlParams.get('status') || 'all');
        $('#dateFilter').val(urlParams.get('date') || '');
        $('#searchInput').val(urlParams.get('search') || '');
    }
});

// Helper functions
function goToDashboard() {
    window.location.href = 'dashboard.php';
}

// Apply filters function
function applyFilters() {
    const status = $('#statusFilter').val();
    const date = $('#dateFilter').val();
    const search = $('#searchInput').val();
    
    let url = 'appointments.php?';
    let params = [];
    
    if (status && status !== 'all') params.push('status=' + encodeURIComponent(status));
    if (date) params.push('date=' + encodeURIComponent(date));
    if (search) params.push('search=' + encodeURIComponent(search));
    
    window.location.href = url + params.join('&');
}

// Reset all filters
function resetAllFilters() {
    window.location.href = 'appointments.php';
}

// Cancel appointment with confirmation
function cancelAppointment(id) {
    Swal.fire({
        title: 'Cancel Appointment?',
        html: `
            <div class="text-start">
                <p>Are you sure you want to cancel this appointment?</p>
                <p class="text-warning small">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Cancellation must be done at least 24 hours before the appointment.
                </p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Cancel It',
        cancelButtonText: 'Keep It',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'cancel';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'appointment_id';
            idInput.value = id;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Reschedule appointment
function rescheduleAppointment(id) {
    window.location.href = 'reschedule-appointment.php?id=' + id;
}

// View appointment details
let currentAppointment = null;

function viewDetails(id) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-appointment-details.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            if (response.success) {
                currentAppointment = response.appointment;
                showDetailsModal(response.appointment);
            } else {
                Swal.fire('Error', response.message || 'Could not load details', 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('AJAX Error:', status, error);
            Swal.fire('Error', 'Could not load details', 'error');
        }
    });
}

// Show details modal
function showDetailsModal(appointment) {
    const modalBody = $('#detailsModalBody');
    
    const statusClass = appointment.status;
    const statusText = appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1);
    
    const staffHtml = appointment.staff_name ? `
        <div class="detail-group">
            <label>Staff</label>
            <p class="detail-value">${escapeHtml(appointment.staff_name)}</p>
            <small class="text-muted">Specialization: ${escapeHtml(appointment.staff_specialization || 'General')}</small>
        </div>
    ` : '';
    
    const html = `
        <div class="details-container">
            <div class="row mb-4">
                <div class="col-12 text-center">
                    <span class="status-badge ${statusClass} large">${statusText}</span>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Service</label>
                        <p class="detail-value">${escapeHtml(appointment.service_name)}</p>
                        <small class="text-muted">Category: ${escapeHtml(appointment.service_category || 'General')}</small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Date & Time</label>
                        <p class="detail-value">${formatDate(appointment.appointment_date)}</p>
                        <p class="detail-value">${formatTime(appointment.appointment_time)}</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Duration</label>
                        <p class="detail-value">${appointment.service_duration} minutes</p>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Price</label>
                        <p class="detail-value price">Rs: ${parseFloat(appointment.service_price).toFixed(2)}</p>
                    </div>
                </div>
            </div>
            
            ${staffHtml}
            
            ${appointment.notes ? `
            <div class="row">
                <div class="col-12">
                    <div class="detail-group">
                        <label>Notes</label>
                        <p class="detail-value">${escapeHtml(appointment.notes)}</p>
                    </div>
                </div>
            </div>
            ` : ''}
            
            <div class="row mt-3">
                <div class="col-12">
                    <div class="detail-group">
                        <label>Booking Date</label>
                        <p class="detail-value small">${formatDate(appointment.created_at, true)}</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    modalBody.html(html);
    
    // Set up print button
    $('#printModalBtn').off('click').on('click', function() {
        printSingleAppointment(appointment);
    });
    
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    modal.show();
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
                .status-badge.completed { background: #d4edda; color: #155724; }
                .status-badge.cancelled { background: #f8d7da; color: #721c24; }
                .status-badge.pending { background: #fff3cd; color: #856404; }
                .status-badge.confirmed { background: #d4edda; color: #155724; }
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
                    <div class="value">${escapeHtml(appointment.staff_name)} (${escapeHtml(appointment.staff_specialization || 'General')})</div>
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

// Print all appointments
function printAppointments() {
    window.print();
}

// Book again
function bookAgain(serviceId) {
    window.location.href = 'book-appointment.php?service=' + serviceId;
}

// Print appointment (for past appointments)
function printAppointment(appointmentId) {
    viewDetails(appointmentId);
}

// Export to PDF
function exportToPDF() {
    const status = $('#statusFilter').val();
    const date = $('#dateFilter').val();
    const search = $('#searchInput').val();
    
    let url = 'export-appointments-pdf.php?';
    let params = [];
    
    if (status && status !== 'all') params.push('status=' + encodeURIComponent(status));
    if (date) params.push('date=' + encodeURIComponent(date));
    if (search) params.push('search=' + encodeURIComponent(search));
    
    showLoading();
    
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = url + params.join('&');
    document.body.appendChild(iframe);
    
    setTimeout(() => {
        hideLoading();
        Swal.fire({
            icon: 'success',
            title: 'Export Started!',
            text: 'Your PDF report is being generated.',
            timer: 2000,
            showConfirmButton: false
        });
        
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 1000);
    }, 2000);
}

// Helper functions
function formatDate(dateStr, includeTime = false) {
    const date = new Date(dateStr);
    if (includeTime) {
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

function formatTime(timeStr) {
    const time = new Date('2000-01-01 ' + timeStr);
    return time.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
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
    $('#loadingOverlay').fadeIn();
}

function hideLoading() {
    $('#loadingOverlay').fadeOut();
}

function refreshPage() {
    location.reload();
}
</script>
    
<style>
/* Additional styles for appointments page */

/* Statistics Cards */
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 20px;
    position: relative;
    overflow: hidden;
    border-left: 5px solid #6f42c1;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: rgba(111, 66, 193, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #6f42c1;
}

.stat-details h3 {
    font-size: 28px;
    font-weight: 700;
    margin: 0;
    color: #333;
}

.stat-details p {
    margin: 5px 0 0;
    color: #6c757d;
    font-weight: 500;
}

/* Filters Card */
.filters-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border-top: 4px solid #6f42c1;
}

.filter-group {
    margin-bottom: 0;
}

.filter-label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.filter-select {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s;
    background: white;
}

.filter-select:focus {
    border-color: #6f42c1;
    box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.1);
    outline: none;
}

.search-wrapper {
    position: relative;
}

.search-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

.btn-apply-filters {
    background: #6f42c1;
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
    margin-right: 10px;
}

.btn-apply-filters:hover {
    background: #5a32a0;
    transform: translateY(-2px);
}

.btn-reset-filters {
    background: white;
    color: #333;
    border: 2px solid #e9ecef;
    padding: 10px 25px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-reset-filters:hover {
    background: #f8f9fa;
    border-color: #6c757d;
}

/* Section Title */
.section-title {
    color: #333;
    font-weight: 700;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.section-title i {
    color: #6f42c1;
}

/* Timeline */
.timeline {
    position: relative;
    padding-left: 100px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 35px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #6f42c1, #fd7e14);
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
    display: flex;
    gap: 30px;
}

.timeline-date {
    position: absolute;
    left: -80px;
    top: 0;
    width: 70px;
    height: 70px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px solid #6f42c1;
    z-index: 1;
}

.timeline-date .day {
    font-size: 24px;
    font-weight: 700;
    color: #6f42c1;
    line-height: 1;
}

.timeline-date .month {
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
}

.timeline-content {
    flex: 1;
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    margin-left: 0;
    border-left: 4px solid #6f42c1;
}

.timeline-item.confirmed .timeline-content { border-left-color: #28a745; }
.timeline-item.pending .timeline-content { border-left-color: #ffc107; }
.timeline-item.completed .timeline-content { border-left-color: #17a2b8; }
.timeline-item.cancelled .timeline-content { border-left-color: #dc3545; }

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 10px;
}

.timeline-header h5 {
    margin: 0;
    font-weight: 700;
    color: #333;
}

.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.confirmed { background: #d4edda; color: #155724; }
.status-badge.completed { background: #cce5ff; color: #004085; }
.status-badge.cancelled { background: #f8d7da; color: #721c24; }

.status-badge.large {
    padding: 8px 20px;
    font-size: 1rem;
}

.timeline-details {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.timeline-details span {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6c757d;
    font-size: 0.95rem;
}

.timeline-details i {
    color: #6f42c1;
    width: 16px;
}

.timeline-notes {
    background: #f8f9fa;
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    color: #6c757d;
    font-size: 0.95rem;
    border-left: 3px solid #6f42c1;
}

.timeline-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-btn {
    padding: 8px 15px;
    border: none;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.action-btn.view-details {
    background: #e9ecef;
    color: #333;
}

.action-btn.view-details:hover {
    background: #dee2e6;
}

.action-btn.reschedule {
    background: #fff3cd;
    color: #856404;
}

.action-btn.reschedule:hover {
    background: #ffe69c;
}

.action-btn.cancel {
    background: #f8d7da;
    color: #721c24;
}

.action-btn.cancel:hover {
    background: #f1b0b7;
}

/* Appointments Table */
.appointments-table {
    width: 100%;
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.appointments-table thead {
    background: #6f42c1;
    color: white;
}

.appointments-table th {
    padding: 15px;
    font-weight: 600;
    font-size: 0.95rem;
}

.appointments-table td {
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
}

.appointments-table tbody tr:hover {
    background: #f8f9fa;
}

.table-action-btn {
    width: 35px;
    height: 35px;
    border: none;
    border-radius: 8px;
    background: #e9ecef;
    color: #333;
    cursor: pointer;
    transition: all 0.3s;
    margin: 0 3px;
}

.table-action-btn:hover {
    background: #6f42c1;
    color: white;
    transform: translateY(-2px);
}

.staff-badge {
    display: inline-block;
    padding: 4px 10px;
    background: linear-gradient(135deg, #6f42c1, #9b6fe0);
    color: white;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.quick-action-btn {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: none;
    background: white;
    color: #333;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border: 2px solid #e9ecef;
}

.quick-action-btn i {
    font-size: 24px;
}

.quick-action-btn span {
    font-size: 12px;
    font-weight: 600;
}

.quick-action-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.quick-action-btn.primary { border-color: #6f42c1; color: #6f42c1; }
.quick-action-btn.success { border-color: #28a745; color: #28a745; }
.quick-action-btn.info { border-color: #17a2b8; color: #17a2b8; }
.quick-action-btn.warning { border-color: #ffc107; color: #ffc107; }

.quick-action-btn.primary:hover { background: #6f42c1; color: white; }
.quick-action-btn.success:hover { background: #28a745; color: white; }
.quick-action-btn.info:hover { background: #17a2b8; color: white; }
.quick-action-btn.warning:hover { background: #ffc107; color: white; }

/* Pagination */
.pagination-wrapper {
    text-align: center;
}

.pagination {
    margin: 0;
}

.page-link {
    color: #6f42c1;
    border: none;
    margin: 0 3px;
    border-radius: 8px !important;
    padding: 8px 12px;
}

.page-item.active .page-link {
    background: #6f42c1;
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 50px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #333;
    margin-bottom: 10px;
}

.empty-state p {
    color: #6c757d;
    margin-bottom: 20px;
}

/* Details Modal */
.details-container {
    padding: 10px;
}

.detail-group {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 15px;
}

.detail-group label {
    display: block;
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-group .detail-value {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.detail-group .detail-value.price {
    color: #6f42c1;
    font-size: 1.3rem;
}

/* Responsive */
@media (max-width: 768px) {
    .timeline {
        padding-left: 0;
    }
    
    .timeline::before {
        display: none;
    }
    
    .timeline-item {
        flex-direction: column;
    }
    
    .timeline-date {
        position: static;
        margin-bottom: 15px;
        width: 100%;
        height: auto;
        flex-direction: row;
        gap: 5px;
        padding: 10px;
    }
    
    .timeline-date .day {
        font-size: 20px;
    }
    
    .timeline-details {
        flex-direction: column;
        gap: 10px;
    }
    
    .appointments-table {
        font-size: 0.9rem;
    }
    
    .quick-actions {
        gap: 10px;
    }
    
    .quick-action-btn {
        width: 70px;
        height: 70px;
    }
}

/* Print Styles */
@media print {
    .sidebar,
    .mobile-toggle,
    .quick-actions,
    .btn-apply-filters,
    .btn-reset-filters,
    .action-btn,
    .table-action-btn,
    .filters-card,
    .page-header .text-end {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
        padding: 20px !important;
    }
    
    .appointments-table {
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .status-badge {
        border: 1px solid #ddd;
        background: none !important;
        color: black !important;
    }
}
</style>
// Rating modal HTML (add at the end of body)
<div class="modal fade" id="ratingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate Your Experience</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <img src="../../assets/images/default-avatar.png" id="ratingStaffAvatar" class="rounded-circle" width="80" height="80">
                    <h5 id="ratingStaffName" class="mt-2"></h5>
                    <p class="text-muted">How was your experience?</p>
                </div>
                <div class="rating-stars text-center mb-3">
                    <i class="far fa-star fa-2x" data-rating="1"></i>
                    <i class="far fa-star fa-2x" data-rating="2"></i>
                    <i class="far fa-star fa-2x" data-rating="3"></i>
                    <i class="far fa-star fa-2x" data-rating="4"></i>
                    <i class="far fa-star fa-2x" data-rating="5"></i>
                </div>
                <div class="mb-3">
                    <label class="form-label">Your Review (Optional)</label>
                    <textarea class="form-control" id="ratingComments" rows="3" placeholder="Share your experience with this staff member..."></textarea>
                </div>
                <input type="hidden" id="ratingAppointmentId">
                <input type="hidden" id="ratingStaffId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitRating()">Submit Rating</button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedRating = 0;

function rateStaff(appointmentId, staffId, staffName) {
    selectedRating = 0;
    $('#ratingAppointmentId').val(appointmentId);
    $('#ratingStaffId').val(staffId);
    $('#ratingStaffName').text(staffName);
    $('#ratingComments').val('');
    
    // Reset stars
    $('.rating-stars i').removeClass('fas').addClass('far').css('color', '#ddd');
    
    $('#ratingModal').modal('show');
}

$('.rating-stars i').hover(
    function() {
        const rating = $(this).data('rating');
        highlightStars(rating);
    },
    function() {
        highlightStars(selectedRating);
    }
);

$('.rating-stars i').click(function() {
    selectedRating = $(this).data('rating');
    highlightStars(selectedRating);
});

function highlightStars(rating) {
    $('.rating-stars i').each(function(index) {
        const starRating = $(this).data('rating');
        if (starRating <= rating) {
            $(this).removeClass('far').addClass('fas').css('color', '#ffc107');
        } else {
            $(this).removeClass('fas').addClass('far').css('color', '#ddd');
        }
    });
}

function submitRating() {
    if (selectedRating === 0) {
        Swal.fire('Error', 'Please select a rating', 'error');
        return;
    }
    
    const appointmentId = $('#ratingAppointmentId').val();
    const staffId = $('#ratingStaffId').val();
    const comments = $('#ratingComments').val();
    
    showLoading();
    
    $.ajax({
        url: 'ajax/submit-rating.php',
        method: 'POST',
        data: {
            appointment_id: appointmentId,
            staff_id: staffId,
            rating: selectedRating,
            comments: comments
        },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            $('#ratingModal').modal('hide');
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Thank You!',
                    text: 'Your rating has been submitted successfully.',
                    timer: 2000,
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
            Swal.fire('Error', 'Failed to submit rating', 'error');
        }
    });
}
</script>

<style>
.rating-stars i {
    cursor: pointer;
    margin: 0 5px;
    transition: all 0.2s;
}

.rating-stars i:hover {
    transform: scale(1.1);
}

.rating-display {
    text-align: center;
}

.stars-display {
    white-space: nowrap;
}

.stars-display i {
    font-size: 12px;
    margin: 0 1px;
}

.btn-rate {
    background: #6f42c1;
    color: white;
    border: none;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-rate:hover {
    background: #5a32a0;
    transform: translateY(-2px);
}
</style>
</body>
</html>
<?php ob_end_flush(); ?>
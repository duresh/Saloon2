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
require_once '../../includes/helpers.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Filter variables
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all'; // all, appointments, transactions, reschedules
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
            (SELECT SUM(s.price) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.user_id = ? AND a.status = 'completed') as total_spent,
            (SELECT AVG(s.price) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.user_id = ? AND a.status = 'completed') as avg_spent
    ";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $stats = $stats_stmt->fetch();
    
    // Build the query based on filter type
    $query = "";
    $count_query = "";
    $params = [$user_id];
    
    if ($filter_type == 'appointments' || $filter_type == 'all') {
        // Get completed and cancelled appointments
        $query = "
            SELECT 
                a.*,
                s.name as service_name,
                s.price,
                s.duration,
                s.category,
                st.id as staff_id,
                r.fName as staff_name,
                'appointment' as record_type
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            LEFT JOIN staff st ON a.staff_id = st.id
            LEFT JOIN reg r ON st.user_id = r.regID
            WHERE a.user_id = ? 
            AND a.status IN ('completed', 'cancelled')
        ";
        $count_query = "
            SELECT COUNT(*) as total
            FROM appointments a
            WHERE a.user_id = ? 
            AND a.status IN ('completed', 'cancelled')
        ";
    }
    
    if ($filter_type == 'reschedules') {
        // Get reschedule history
        $query = "
            SELECT 
                ar.*,
                os.name as old_service_name,
                ns.name as new_service_name,
                ostf.staff_name as old_staff_name,
                nstf.staff_name as new_staff_name,
                'reschedule' as record_type
            FROM appointment_reschedules ar
            LEFT JOIN services os ON ar.old_service_id = os.id
            LEFT JOIN services ns ON ar.new_service_id = ns.id
            LEFT JOIN (SELECT s.id, r.fName as staff_name FROM staff s JOIN reg r ON s.user_id = r.regID) ostf ON ar.old_staff_id = ostf.id
            LEFT JOIN (SELECT s.id, r.fName as staff_name FROM staff s JOIN reg r ON s.user_id = r.regID) nstf ON ar.new_staff_id = nstf.id
            WHERE ar.user_id = ?
        ";
        $count_query = "
            SELECT COUNT(*) as total
            FROM appointment_reschedules ar
            WHERE ar.user_id = ?
        ";
    }
    
    // Add filters
    if (!empty($status_filter) && $filter_type != 'reschedules') {
        $query .= " AND a.status = ?";
        $count_query .= " AND a.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_from)) {
        $query .= " AND a.appointment_date >= ?";
        $count_query .= " AND a.appointment_date >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND a.appointment_date <= ?";
        $count_query .= " AND a.appointment_date <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search) && $filter_type != 'reschedules') {
        $query .= " AND (s.name LIKE ? OR a.notes LIKE ?)";
        $count_query .= " AND (s.name LIKE ? OR a.notes LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Add ordering and pagination
    if ($filter_type == 'reschedules') {
        $query .= " ORDER BY ar.rescheduled_at DESC LIMIT ? OFFSET ?";
    } else {
        $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT ? OFFSET ?";
    }
    
    // Get total records for pagination
    $count_params = $params;
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Add pagination parameters
    $params[] = $records_per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $history_records = $stmt->fetchAll();
    
    // Get monthly summary for chart
    $monthly_query = "
        SELECT 
            DATE_FORMAT(appointment_date, '%Y-%m') as month,
            COUNT(*) as count,
            SUM(s.price) as total
        FROM appointments a
        JOIN services s ON a.service_id = s.id
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
}

include 'header/headerBooking.php';
?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <div class="history-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-history me-2"></i> My History</h1>
                    <p class="lead mb-0">View your appointment history, transactions, and activity</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                    <button class="btn btn-primary" onclick="exportHistory()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['total_completed'] ?? 0); ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['total_cancelled'] ?? 0); ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(111, 66, 193, 0.1); color: #6f42c1;">
                    <i class="fas fa-redo-alt"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['total_reschedules'] ?? 0); ?></div>
                    <div class="stat-label">Reschedules</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value">Rs: <?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card">
            <div class="filter-header">
                <h5><i class="fas fa-filter me-2"></i> Filter History</h5>
            </div>
            <div class="filter-body">
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
        <div class="chart-card mb-4">
            <div class="chart-header">
                <h5><i class="fas fa-chart-line me-2"></i> Monthly Spending Summary</h5>
            </div>
            <div class="chart-container">
                <canvas id="spendingChart" height="300"></canvas>
            </div>
        </div>

        <!-- History Records -->
        <div class="records-card">
            <div class="records-header">
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
                                            <strong><?php echo htmlspecialchars($record['service_name']); ?></strong>
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
                                        <div class="detail-row">
                                            <i class="fas fa-tag"></i>
                                            <span><?php echo htmlspecialchars($record['category']); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <i class="fas fa-hourglass-half"></i>
                                            <span><?php echo $record['duration']; ?> minutes</span>
                                        </div>
                                        <?php if (!empty($record['staff_name'])): ?>
                                        <div class="detail-row">
                                            <i class="fas fa-user-tie"></i>
                                            <span><?php echo htmlspecialchars($record['staff_name']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="detail-row">
                                            <i class="fas fa-rupee-sign"></i>
                                            <span class="price">Rs: <?php echo number_format($record['price'], 2); ?></span>
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
                                            Booked on: <?php echo date('M d, Y g:i A', strtotime($record['created_at'])); ?>
                                        </small>
                                        <button class="btn-view-details" onclick="viewDetails(<?php echo $record['id']; ?>)">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Reschedule Record -->
                            <div class="timeline-item reschedule">
                                <div class="timeline-date">
                                    <span class="day"><?php echo date('d', strtotime($record['rescheduled_at'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($record['rescheduled_at'])); ?></span>
                                    <span class="year"><?php echo date('Y', strtotime($record['rescheduled_at'])); ?></span>
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
                                        <?php if ($record['old_service_id'] != $record['new_service_id']): ?>
                                        <div class="detail-row change">
                                            <i class="fas fa-spa"></i>
                                            <span>
                                                <span class="old-value"><?php echo htmlspecialchars($record['old_service_name'] ?? 'Unknown'); ?></span>
                                                <i class="fas fa-arrow-right mx-1"></i>
                                                <span class="new-value"><?php echo htmlspecialchars($record['new_service_name'] ?? 'Unknown'); ?></span>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($record['old_staff_id'] != $record['new_staff_id']): ?>
                                        <div class="detail-row change">
                                            <i class="fas fa-user-tie"></i>
                                            <span>
                                                <span class="old-value"><?php echo htmlspecialchars($record['old_staff_name'] ?? 'Unassigned'); ?></span>
                                                <i class="fas fa-arrow-right mx-1"></i>
                                                <span class="new-value"><?php echo htmlspecialchars($record['new_staff_name'] ?? 'Unassigned'); ?></span>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($record['old_date'] != $record['new_date'] || $record['old_time'] != $record['new_time']): ?>
                                        <div class="detail-row change">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span>
                                                <span class="old-value"><?php echo date('M d, g:i A', strtotime($record['old_date'] . ' ' . $record['old_time'])); ?></span>
                                                <i class="fas fa-arrow-right mx-1"></i>
                                                <span class="new-value"><?php echo date('M d, g:i A', strtotime($record['new_date'] . ' ' . $record['new_time'])); ?></span>
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
                                            <i class="fas fa-user"></i>
                                            Rescheduled by: <?php echo ucfirst($record['rescheduled_by']); ?>
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
                <div class="text-center text-muted mt-2">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Initialize charts
$(document).ready(function() {
    initChart();
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
                        data: monthlyData.map(item => item.count),
                        backgroundColor: 'rgba(111, 66, 193, 0.5)',
                        borderColor: '#6f42c1',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Spent (Rs)',
                        data: monthlyData.map(item => item.total),
                        backgroundColor: 'rgba(40, 167, 69, 0.5)',
                        borderColor: '#28a745',
                        borderWidth: 1,
                        type: 'line',
                        yAxisID: 'y1'
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
                        title: {
                            display: true,
                            text: 'Number of Appointments'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Amount (Rs)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    }
}

function viewDetails(appointmentId) {
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
                    staffHtml = `<div class="detail-row"><span class="label">Staff:</span><span class="value">${escapeHtml(apt.staff_name)} (${apt.staff_specialization || 'General'})</span></div>`;
                }
                
                Swal.fire({
                    title: `Appointment #${apt.id}`,
                    html: `
                        <div class="details-view">
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
                                <span class="value">${apt.service_duration} minutes</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Amount:</span>
                                <span class="value price">Rs: ${parseFloat(apt.service_price).toFixed(2)}</span>
                            </div>
                            ${staffHtml}
                            <div class="detail-row">
                                <span class="label">Status:</span>
                                <span class="value">${apt.status}</span>
                            </div>
                            ${apt.notes ? `<div class="detail-row"><span class="label">Notes:</span><span class="value">${escapeHtml(apt.notes)}</span></div>` : ''}
                        </div>
                    `,
                    showCloseButton: true,
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#6f42c1',
                    width: '550px'
                });
            } else {
                Swal.fire('Error', response.message || 'Could not load details', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to load details', 'error');
        }
    });
}

function exportHistory() {
    // Get all filter values from the form
    const formData = new FormData(document.getElementById('filterForm'));
    const params = new URLSearchParams();
    
    // Add all form fields to params
    for (let [key, value] of formData.entries()) {
        if (value) {
            params.append(key, value);
        }
    }
    
    // Show loading
    Swal.fire({
        title: 'Generating PDF...',
        text: 'Please wait while we create your report.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Create a hidden iframe to download PDF
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = 'export-history.php?' + params.toString();
    document.body.appendChild(iframe);
    
    // Close loading after a delay
    setTimeout(() => {
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'Export Complete!',
            text: 'Your history report has been downloaded.',
            timer: 2000,
            showConfirmButton: false
        });
        
        // Remove iframe after download
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 1000);
    }, 2000);
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
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
</script>

<style>
/* History Page Styles */
.history-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s;
    border-left: 4px solid transparent;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 55px;
    height: 55px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
}

.stat-label {
    font-size: 13px;
    color: #6c757d;
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
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.filter-header h5 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.filter-body {
    padding: 20px;
}

/* Chart Card */
.chart-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.chart-header h5 {
    margin: 0 0 15px 0;
    color: #333;
    font-weight: 600;
}

.chart-container {
    height: 300px;
}

/* Records Card */
.records-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}

.records-header {
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.records-header h5 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

/* Timeline */
.timeline {
    padding: 30px;
}

.timeline-item {
    position: relative;
    padding-left: 100px;
    margin-bottom: 30px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 35px;
    top: 0;
    bottom: -30px;
    width: 2px;
    background: linear-gradient(to bottom, #6f42c1, #ffc107);
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-date {
    position: absolute;
    left: 0;
    top: 0;
    width: 80px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    text-align: center;
    padding: 10px;
    border: 1px solid #e9ecef;
    z-index: 1;
}

.timeline-date .day {
    font-size: 24px;
    font-weight: 700;
    color: #6f42c1;
    line-height: 1;
}

.timeline-date .month {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
}

.timeline-date .year {
    font-size: 10px;
    color: #adb5bd;
}

.timeline-content {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    border-left: 4px solid;
}

.timeline-item.completed .timeline-content { border-left-color: #28a745; }
.timeline-item.cancelled .timeline-content { border-left-color: #dc3545; }
.timeline-item.reschedule .timeline-content { border-left-color: #ffc107; }

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 10px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-left i {
    font-size: 18px;
    color: #6f42c1;
}

.timeline-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #495057;
}

.detail-row i {
    width: 20px;
    color: #6f42c1;
}

.detail-row .price {
    font-weight: 700;
    color: #6f42c1;
}

.detail-row.change {
    background: #fff3cd;
    padding: 5px 10px;
    border-radius: 8px;
}

.old-value {
    color: #dc3545;
    text-decoration: line-through;
}

.new-value {
    color: #28a745;
    font-weight: 500;
}

.timeline-notes {
    background: white;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 13px;
    color: #6c757d;
    border-left: 3px solid #6f42c1;
}

.timeline-notes i {
    margin-right: 8px;
    color: #6f42c1;
}

.timeline-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.btn-view-details {
    background: none;
    border: none;
    color: #6f42c1;
    cursor: pointer;
    font-size: 13px;
    padding: 5px 10px;
    border-radius: 6px;
    transition: all 0.3s;
}

.btn-view-details:hover {
    background: #6f42c1;
    color: white;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.completed { background: #d4edda; color: #155724; }
.status-badge.cancelled { background: #f8d7da; color: #721c24; }
.status-badge.reschedule { background: #fff3cd; color: #856404; }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    opacity: 0.5;
}

/* Pagination */
.pagination-wrapper {
    padding: 20px;
    border-top: 1px solid #e9ecef;
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

/* Details View */
.details-view {
    text-align: left;
    padding: 10px;
}

.detail-row {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid #e9ecef;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row .label {
    width: 100px;
    font-weight: 600;
    color: #6c757d;
}

.detail-row .value {
    flex: 1;
    color: #495057;
}

.detail-row .value.price {
    color: #6f42c1;
    font-weight: 700;
}

/* Responsive */
@media (max-width: 768px) {
    .timeline {
        padding: 20px;
    }
    
    .timeline-item {
        padding-left: 0;
        margin-bottom: 40px;
    }
    
    .timeline-item::before {
        display: none;
    }
    
    .timeline-date {
        position: relative;
        width: auto;
        display: inline-block;
        margin-bottom: 10px;
        background: #f8f9fa;
    }
    
    .timeline-date .day,
    .timeline-date .month,
    .timeline-date .year {
        display: inline;
        margin-right: 5px;
    }
    
    .timeline-date .day {
        font-size: 16px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .timeline-details {
        grid-template-columns: 1fr;
    }
}
</style>
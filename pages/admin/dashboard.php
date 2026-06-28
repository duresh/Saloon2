<?php
ob_start();
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../users/dashboard.php');
    exit();
}

// Include database connection
require_once '../../includes/dbcon.php';
// Include helper functions
require_once '../../includes/helpers.php';

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
    
    // Get overall statistics
    $stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM reg WHERE role = 'user') as total_users,
            (SELECT COUNT(*) FROM services) as total_services,
            (SELECT COUNT(*) FROM staff) as total_staff,
            (SELECT COUNT(*) FROM appointments) as total_appointments,
            (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()) as today_appointments,
            (SELECT COUNT(*) FROM appointments WHERE appointment_date >= CURDATE() AND status IN ('pending', 'confirmed')) as upcoming_appointments,
            (SELECT COUNT(*) FROM appointments WHERE status = 'pending') as pending_appointments,
            (SELECT COUNT(*) FROM appointments WHERE status = 'completed' AND MONTH(created_at) = MONTH(CURDATE())) as monthly_completed,
            (SELECT SUM(price) FROM appointments a JOIN services s ON a.service_id = s.id WHERE MONTH(a.created_at) = MONTH(CURDATE())) as monthly_revenue,
            (SELECT COUNT(*) FROM reg WHERE role = 'user' AND MONTH(regDate) = MONTH(CURDATE())) as new_users_this_month
    ";
    
    $stats_stmt = $pdo->query($stats_query);
    $stats = $stats_stmt->fetch();
    
    // Get recent appointments with staff information
    $recent_appointments_query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.price,
            u.fName as customer_name,
            u.email as customer_email,
            u.contactNo as customer_phone,
            st.id as staff_id,
            r.fName as staff_name,
            st.specialization as staff_specialization
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg u ON a.user_id = u.regID
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN reg r ON st.user_id = r.regID
        ORDER BY a.created_at DESC
        LIMIT 10
    ";
    
    $recent_appointments_stmt = $pdo->query($recent_appointments_query);
    $recent_appointments = $recent_appointments_stmt->fetchAll();
    
    // Get popular services
    $popular_services_query = "
        SELECT 
            s.id,
            s.name,
            s.category,
            s.price,
            COUNT(a.id) as booking_count,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count
        FROM services s
        LEFT JOIN appointments a ON s.id = a.service_id
        WHERE s.status = 'active'
        GROUP BY s.id
        ORDER BY booking_count DESC
        LIMIT 5
    ";
    
    $popular_services_stmt = $pdo->query($popular_services_query);
    $popular_services = $popular_services_stmt->fetchAll();
    
    // Get staff performance - FIXED query
    $staff_performance_query = "
        SELECT 
            st.id,
            r.fName as staff_name,
            r.email,
            st.specialization,
            COUNT(a.id) as appointments_handled,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments
        FROM staff st
        JOIN reg r ON st.user_id = r.regID
        LEFT JOIN appointments a ON a.staff_id = st.id
        GROUP BY st.id
        ORDER BY appointments_handled DESC
        LIMIT 5
    ";
    
    $staff_performance_stmt = $pdo->query($staff_performance_query);
    $staff_performance = $staff_performance_stmt->fetchAll();
    
    // Get recent users
    $recent_users_query = "
        SELECT regID, fName, email, contactNo, regDate
        FROM reg
        WHERE role = 'user'
        ORDER BY regDate DESC
        LIMIT 5
    ";
    
    $recent_users_stmt = $pdo->query($recent_users_query);
    $recent_users = $recent_users_stmt->fetchAll();
    
    // Get appointment status breakdown for chart
    $status_breakdown_query = "
        SELECT 
            status,
            COUNT(*) as count
        FROM appointments
        GROUP BY status
    ";
    
    $status_breakdown_stmt = $pdo->query($status_breakdown_query);
    $status_breakdown = $status_breakdown_stmt->fetchAll();
    
    // Get daily appointments for the last 7 days
    $daily_appointments_query = "
        SELECT 
            DATE(appointment_date) as date,
            COUNT(*) as count
        FROM appointments
        WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(appointment_date)
        ORDER BY date DESC
    ";
    
    $daily_appointments_stmt = $pdo->query($daily_appointments_query);
    $daily_appointments = $daily_appointments_stmt->fetchAll();
    
    // Get revenue by service category
    $revenue_by_category_query = "
        SELECT 
            s.category,
            COUNT(a.id) as bookings,
            SUM(s.price) as revenue
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.status = 'completed'
        GROUP BY s.category
        ORDER BY revenue DESC
    ";
    
    $revenue_by_category_stmt = $pdo->query($revenue_by_category_query);
    $revenue_by_category = $revenue_by_category_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Admin Dashboard error: ' . $e->getMessage());
    $error = "Unable to load dashboard data. Please try again later.";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

include 'header/header-admin.php';
?>

<!-- Welcome Banner -->
<!-- <div class="welcome-banner">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h2><i class="fas fa-user-shield me-2"></i> Welcome back, <?php echo htmlspecialchars($admin['fName']); ?>!</h2>
            <p class="mb-0">Here's what's happening with your salon today.</p>
        </div>
        <div class="col-md-4 text-end">
            <span class="current-datetime">
                <i class="fas fa-calendar me-2"></i><?php echo date('l, F j, Y'); ?>
                <br>
                <i class="fas fa-clock me-2"></i><?php echo date('g:i A'); ?>
            </span>
        </div>
    </div>
</div> -->

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card primary" onclick="window.location.href='manage-appointments.php?status=today'">
        <div class="stat-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['today_appointments'] ?? 0); ?></div>
            <div class="stat-label">Today's Appointments</div>
            <div class="stat-trend up">
                <i class="fas fa-arrow-up"></i> vs yesterday
            </div>
        </div>
    </div>
    
    <div class="stat-card success" onclick="window.location.href='manage-appointments.php?status=upcoming'">
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['upcoming_appointments'] ?? 0); ?></div>
            <div class="stat-label">Upcoming</div>
            <div class="stat-trend">
                <i class="fas fa-calendar"></i> Next 30 days
            </div>
        </div>
    </div>
    
    <div class="stat-card info" onclick="window.location.href='manage-users.php'">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
            <div class="stat-label">Total Customers</div>
            <div class="stat-trend up">
                <i class="fas fa-user-plus"></i> +<?php echo $stats['new_users_this_month'] ?? 0; ?> this month
            </div>
        </div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-icon">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value">Rs: <?php echo number_format($stats['monthly_revenue'] ?? 0, 2); ?></div>
            <div class="stat-label">Monthly Revenue</div>
            <div class="stat-trend">
                <i class="fas fa-chart-line"></i> This month
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="quick-actions-grid">
    <button class="quick-action-btn" onclick="location.href='manage-appointments.php'">
        <i class="fas fa-calendar-alt"></i>
        <span>Appointments</span>
    </button>
    <button class="quick-action-btn" onclick="location.href='manage-services.php'">
        <i class="fas fa-spa"></i>
        <span>Services</span>
    </button>
    <button class="quick-action-btn" onclick="location.href='manage-staff.php'">
        <i class="fas fa-users-cog"></i>
        <span>Staff</span>
    </button>
    <button class="quick-action-btn" onclick="location.href='manage-users.php'">
        <i class="fas fa-user-circle"></i>
        <span>Users</span>
    </button>
    <button class="quick-action-btn primary" onclick="location.href='reports.php'">
        <i class="fas fa-chart-bar"></i>
        <span>Reports</span>
    </button>
    <button class="quick-action-btn" onclick="refreshDashboard()">
        <i class="fas fa-sync-alt"></i>
        <span>Refresh</span>
    </button>
</div>

<!-- Main Dashboard Grid -->
<div class="dashboard-grid">
    <!-- Recent Appointments -->
    <div class="grid-item recent-appointments">
        <div class="grid-header">
            <h5><i class="fas fa-history me-2"></i> Recent Appointments</h5>
            <a href="manage-appointments.php" class="view-all-link">View All <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
        
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Date & Time</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Staff</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_appointments as $apt): ?>
                    <tr>
                        <td>
                            <div class="customer-info">
                                <strong><?php echo htmlspecialchars($apt['customer_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($apt['customer_phone']); ?></small>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($apt['service_name']); ?></td>
                        <td>
                            <span class="date"><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></span>
                            <br>
                            <span class="time"><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></span>
                        </td>
                        <td><span class="amount">Rs: <?php echo number_format($apt['price'], 2); ?></span></td>
                        <td>
                            <span class="status-badge <?php echo $apt['status']; ?>">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($apt['staff_name'])): ?>
                                <div class="staff-badge">
                                    <i class="fas fa-user-tie"></i>
                                    <span><?php echo htmlspecialchars($apt['staff_name']); ?></span>
                                </div>
                                <small class="text-muted d-block"><?php echo htmlspecialchars($apt['staff_specialization'] ?? 'General'); ?></small>
                            <?php else: ?>
                                <span class="badge bg-secondary">Not Assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon" onclick="viewAppointmentDetails(<?php echo $apt['id']; ?>)" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon" onclick="editAppointment(<?php echo $apt['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($apt['status'] == 'pending'): ?>
                                <button class="btn-icon success" onclick="approveAppointment(<?php echo $apt['id']; ?>)" title="Approve">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn-icon danger" onclick="rejectAppointment(<?php echo $apt['id']; ?>)" title="Reject">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Sidebar -->
    <div class="grid-item right-sidebar">
        <!-- Pending Approvals -->
        <div class="sidebar-card">
            <div class="sidebar-card-header">
                <h6><i class="fas fa-clock text-warning me-2"></i> Pending Approvals</h6>
                <span class="badge bg-warning"><?php echo $stats['pending_appointments'] ?? 0; ?></span>
            </div>
            <div class="pending-list">
                <?php 
                $pending_count = 0;
                foreach ($recent_appointments as $apt): 
                    if ($apt['status'] == 'pending' && $pending_count < 3): 
                        $pending_count++;
                ?>
                <div class="pending-item">
                    <div class="pending-info">
                        <strong><?php echo htmlspecialchars($apt['customer_name']); ?></strong>
                        <p class="mb-0 small"><?php echo htmlspecialchars($apt['service_name']); ?></p>
                        <p class="mb-0 small text-muted">
                            <?php echo date('M d, g:i A', strtotime($apt['appointment_date'] . ' ' . $apt['appointment_time'])); ?>
                        </p>
                        <?php if (!empty($apt['staff_name'])): ?>
                        <p class="mb-0 small text-success">
                            <i class="fas fa-user-tie me-1"></i> <?php echo htmlspecialchars($apt['staff_name']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="pending-actions">
                        <?php if (empty($apt['staff_name'])): ?>
                        <button class="btn-icon small success" onclick="approveWithStaff(<?php echo $apt['id']; ?>)" title="Assign & Approve">
                            <i class="fas fa-user-check"></i>
                        </button>
                        <?php else: ?>
                        <button class="btn-icon small success" onclick="approveAppointment(<?php echo $apt['id']; ?>)" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <?php endif; ?>
                        <button class="btn-icon small danger" onclick="rejectAppointment(<?php echo $apt['id']; ?>)" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <?php 
                    endif;
                endforeach; 
                
                if ($pending_count == 0):
                ?>
                <div class="empty-state small">
                    <i class="fas fa-check-circle mb-2"></i>
                    <p class="mb-0">No pending approvals</p>
                </div>
                <?php endif; ?>
                
                <?php if (($stats['pending_appointments'] ?? 0) > 3): ?>
                <div class="text-center mt-2">
                    <a href="manage-appointments.php?status=pending" class="view-more-link">
                        View all <?php echo $stats['pending_appointments']; ?> pending
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Popular Services -->
        <div class="sidebar-card">
            <div class="sidebar-card-header">
                <h6><i class="fas fa-star text-warning me-2"></i> Popular Services</h6>
                <a href="manage-services.php" class="view-all-link small">Manage</a>
            </div>
            <div class="services-list">
                <?php foreach ($popular_services as $service): ?>
                <div class="service-item">
                    <div class="service-info">
                        <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                        <p class="mb-0 small text-muted"><?php echo htmlspecialchars($service['category']); ?></p>
                    </div>
                    <div class="service-stats">
                        <span class="booking-count"><?php echo $service['booking_count']; ?> bookings</span>
                        <span class="price">Rs: <?php echo number_format($service['price'], 2); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- New Customers -->
        <div class="sidebar-card">
            <div class="sidebar-card-header">
                <h6><i class="fas fa-user-plus text-success me-2"></i> New Customers</h6>
                <a href="manage-users.php" class="view-all-link small">View All</a>
            </div>
            <div class="users-list">
                <?php foreach ($recent_users as $user): ?>
                <div class="user-item">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['fName'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <strong><?php echo htmlspecialchars($user['fName']); ?></strong>
                        <p class="mb-0 small text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                        <p class="mb-0 small text-muted">
                            <i class="fas fa-clock me-1"></i> <?php echo timeAgo($user['regDate']); ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Charts Grid -->
<div class="charts-grid">
    <!-- Appointment Status Chart -->
    <div class="chart-card">
        <div class="chart-header">
            <h6><i class="fas fa-chart-pie me-2"></i> Appointment Status</h6>
        </div>
        <div class="chart-container">
            <canvas id="statusChart"></canvas>
        </div>
    </div>

    <!-- Daily Appointments Chart -->
    <div class="chart-card">
        <div class="chart-header">
            <h6><i class="fas fa-chart-line me-2"></i> Last 7 Days</h6>
        </div>
        <div class="chart-container">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>

    <!-- Revenue by Category -->
    <div class="chart-card">
        <div class="chart-header">
            <h6><i class="fas fa-chart-bar me-2"></i> Revenue by Category</h6>
        </div>
        <div class="chart-container">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
</div>

<!-- Staff Performance -->
<div class="staff-performance-card">
    <div class="performance-header">
        <h6><i class="fas fa-trophy text-warning me-2"></i> Staff Performance</h6>
        <a href="manage-staff.php" class="view-all-link">Manage Staff <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="table-responsive">
        <table class="performance-table">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Specialization</th>
                    <th>Appointments</th>
                    <th>Completed</th>
                    <th>Performance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff_performance as $staff): 
                    $completion_rate = $staff['appointments_handled'] > 0 
                        ? round(($staff['completed_appointments'] / $staff['appointments_handled']) * 100) 
                        : 0;
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($staff['staff_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($staff['specialization'] ?? 'General'); ?></td>
                    <td><?php echo $staff['appointments_handled'] ?? 0; ?></td>
                    <td><?php echo $staff['completed_appointments'] ?? 0; ?></td>
                    <td>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?php echo $completion_rate; ?>%">
                                <?php echo $completion_rate; ?>%
                            </div>
                        </div>
                    </td>
                    <td>
                        <button class="btn-icon small" onclick="viewStaffDetails(<?php echo $staff['id']; ?>)" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-icon small" onclick="editStaff(<?php echo $staff['id']; ?>)" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function() {
    // Toggle sidebar on mobile
    $('#sidebarToggle').click(function() {
        $('#sidebar').toggleClass('active');
        $('#mainContent').toggleClass('active');
    });

    // Initialize charts
    initCharts();
});

// Initialize Charts
function initCharts() {
    // Status Breakdown Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusData = <?php echo json_encode($status_breakdown); ?>;
    
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
            datasets: [{
                data: statusData.map(item => item.count),
                backgroundColor: ['#ffc107', '#28a745', '#17a2b8', '#dc3545'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Daily Appointments Chart
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    const dailyData = <?php echo json_encode($daily_appointments); ?>;
    
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: dailyData.map(item => item.date),
            datasets: [{
                label: 'Appointments',
                data: dailyData.map(item => item.count),
                borderColor: '#6f42c1',
                backgroundColor: 'rgba(111, 66, 193, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });

    // Revenue by Category Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueData = <?php echo json_encode($revenue_by_category); ?>;
    
    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: revenueData.map(item => item.category),
            datasets: [{
                label: 'Revenue (Rs)',
                data: revenueData.map(item => item.revenue),
                backgroundColor: '#6f42c1',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rs: ' + value;
                        }
                    }
                }
            }
        }
    });
}

// Appointment Management Functions
function updateAppointmentStatus(appointmentId, status) {
    let actionText = status === 'confirmed' ? 'approve' : (status === 'cancelled' ? 'reject' : 'update');
    let confirmColor = status === 'confirmed' ? '#28a745' : (status === 'cancelled' ? '#dc3545' : '#6f42c1');
    
    Swal.fire({
        title: `Confirm ${actionText}`,
        text: `Are you sure you want to ${actionText} this appointment?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, ${actionText} it!`,
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: 'ajax/update-appointment-status.php',
                method: 'POST',
                data: { id: appointmentId, status: status },
                dataType: 'json'
            }).then(response => {
                if (!response.success) throw new Error(response.message);
                return response;
            }).catch(error => {
                Swal.showValidationMessage(error.message);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: `Appointment ${actionText}ed successfully.`,
                timer: 1500,
                showConfirmButton: false
            }).then(() => location.reload());
        }
    });
}

function approveAppointment(appointmentId) {
    updateAppointmentStatus(appointmentId, 'confirmed');
}

function rejectAppointment(appointmentId) {
    updateAppointmentStatus(appointmentId, 'cancelled');
}

function approveWithStaff(appointmentId) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-available-staff.php',
        method: 'POST',
        data: { appointment_id: appointmentId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success && response.available_staff?.length > 0) {
                let staffOptions = response.available_staff.map(staff => `
                    <div class="staff-option" onclick="selectStaffAndApprove(${appointmentId}, ${staff.id}, '${staff.staff_name}')">
                        <div class="staff-avatar">${staff.staff_name.charAt(0)}</div>
                        <div class="staff-info">
                            <strong>${staff.staff_name}</strong>
                            <small>${staff.specialization || 'General'}</small>
                        </div>
                    </div>
                `).join('');
                
                Swal.fire({
                    title: 'Select Staff',
                    html: `<div class="staff-grid">${staffOptions}</div>`,
                    showConfirmButton: false,
                    showCloseButton: true,
                    width: '500px'
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Staff Available',
                    text: 'Approve without staff assignment?',
                    showCancelButton: true,
                    confirmButtonText: 'Approve',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) approveAppointment(appointmentId);
                });
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to check staff availability', 'error');
        }
    });
}

function selectStaffAndApprove(appointmentId, staffId, staffName) {
    Swal.close();
    showLoading();
    
    $.ajax({
        url: 'ajax/assign-staff.php',
        method: 'POST',
        data: { appointment_id: appointmentId, staff_id: staffId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Approved!',
                    text: `Assigned to ${staffName}`,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to assign staff', 'error');
        }
    });
}

function viewAppointmentDetails(appointmentId) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-appointment-details.php',
        method: 'POST',
        data: { id: appointmentId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            if (response.success) {
                const apt = response.appointment;
                Swal.fire({
                    title: `Appointment #${apt.id}`,
                    html: `
                        <div class="details-view">
                            <div class="detail-row">
                                <span class="label">Customer:</span>
                                <span class="value">${apt.customer_name}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Service:</span>
                                <span class="value">${apt.service_name}</span>
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
                                <span class="label">Status:</span>
                                <span class="value status-${apt.status}">${apt.status}</span>
                            </div>
                            ${apt.staff_name ? `
                            <div class="detail-row">
                                <span class="label">Staff:</span>
                                <span class="value">${apt.staff_name}</span>
                            </div>
                            ` : ''}
                        </div>
                    `,
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#6f42c1'
                });
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to load details', 'error');
        }
    });
}

function editAppointment(appointmentId) {
    window.location.href = `edit-appointment.php?id=${appointmentId}`;
}

function deleteAppointment(appointmentId) {
    Swal.fire({
        title: 'Delete Appointment?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Delete',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: 'ajax/delete-appointment.php',
                method: 'POST',
                data: { id: appointmentId },
                dataType: 'json'
            }).then(response => {
                if (!response.success) throw new Error(response.message);
                return response;
            }).catch(error => {
                Swal.showValidationMessage(error.message);
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                timer: 1500,
                showConfirmButton: false
            }).then(() => location.reload());
        }
    });
}

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
                        <div class="details-view">
                            <div class="detail-row">
                                <span class="label">Email:</span>
                                <span class="value">${response.email}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Specialization:</span>
                                <span class="value">${response.specialization || 'General'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Experience:</span>
                                <span class="value">${response.experience_years || 0} years</span>
                            </div>
                        </div>
                    `,
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#6f42c1'
                });
            }
        }
    });
}

function editStaff(staffId) {
    window.location.href = `edit-staff.php?id=${staffId}`;
}

// Utility Functions
function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', { 
        year: 'numeric', month: 'long', day: 'numeric' 
    });
}

function formatTime(timeStr) {
    return new Date('2000-01-01 ' + timeStr).toLocaleTimeString('en-US', { 
        hour: 'numeric', minute: '2-digit', hour12: true 
    });
}

function showLoading() {
    Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
}

function hideLoading() {
    Swal.close();
}

function refreshDashboard() {
    showLoading();
    setTimeout(() => location.reload(), 500);
}
</script>

<style>
/* ============================================
   ADMIN DASHBOARD STYLES
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

/* Welcome Banner */
.welcome-banner {
    background: linear-gradient(135deg, var(--primary) 0%, #5a32a0 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(111, 66, 193, 0.3);
}

.current-datetime {
    background: rgba(255, 255, 255, 0.15);
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 0.95rem;
    display: inline-block;
    backdrop-filter: blur(10px);
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
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s;
    cursor: pointer;
    border-left: 4px solid transparent;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.stat-card.primary { border-left-color: var(--primary); }
.stat-card.success { border-left-color: var(--success); }
.stat-card.info { border-left-color: var(--info); }
.stat-card.warning { border-left-color: var(--warning); }

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    background: rgba(111, 66, 193, 0.1);
    color: var(--primary);
}

.stat-card.success .stat-icon {
    background: rgba(40, 167, 69, 0.1);
    color: var(--success);
}

.stat-card.info .stat-icon {
    background: rgba(23, 162, 184, 0.1);
    color: var(--info);
}

.stat-card.warning .stat-icon {
    background: rgba(255, 193, 7, 0.1);
    color: var(--warning);
}

.stat-details {
    flex: 1;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

.stat-label {
    color: var(--gray);
    font-size: 14px;
    margin-bottom: 5px;
}

.stat-trend {
    font-size: 12px;
    color: var(--gray);
}

.stat-trend.up { color: var(--success); }

/* Quick Actions Grid */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 25px;
    background: white;
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.quick-action-btn {
    background: var(--light);
    border: 1px solid var(--border);
    padding: 12px;
    border-radius: 12px;
    color: var(--dark);
    transition: all 0.3s;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.quick-action-btn:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(111, 66, 193, 0.3);
}

.quick-action-btn.primary {
    background: var(--primary);
    color: white;
}

.quick-action-btn i { font-size: 20px; }
.quick-action-btn span { font-size: 13px; font-weight: 500; }

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
    margin-bottom: 25px;
}

/* Grid Items */
.grid-item {
    background: white;
    border-radius: 16px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.grid-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.grid-header h5 {
    margin: 0;
    color: var(--dark);
    font-weight: 600;
}

.view-all-link {
    color: var(--primary);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}

.view-all-link:hover {
    text-decoration: underline;
}

/* Tables */
.admin-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.admin-table th {
    background: var(--light);
    padding: 15px;
    font-weight: 600;
    color: var(--dark);
    font-size: 13px;
    text-align: left;
    white-space: nowrap;
}

.admin-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.admin-table tbody tr:hover {
    background: var(--light);
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
    font-size: 12px;
    white-space: nowrap;
}

.staff-badge i { font-size: 11px; }

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 8px;
    background: var(--light);
    color: var(--gray);
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

.btn-icon.small {
    width: 28px;
    height: 28px;
    font-size: 12px;
}

.btn-icon.success:hover { background: var(--success); }
.btn-icon.danger:hover { background: var(--danger); }

/* Sidebar Cards */
.sidebar-card {
    padding: 20px;
    border-bottom: 1px solid var(--border);
}

.sidebar-card:last-child { border-bottom: none; }

.sidebar-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.sidebar-card-header h6 {
    margin: 0;
    font-weight: 600;
    color: var(--dark);
}

/* Pending List */
.pending-list { max-height: 300px; overflow-y: auto; }

.pending-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.pending-item:last-child { border-bottom: none; }

.pending-info { flex: 1; }
.pending-info strong { font-size: 14px; }

.pending-actions {
    display: flex;
    gap: 5px;
    margin-left: 10px;
}

/* Services & Users Lists */
.services-list, .users-list {
    max-height: 250px;
    overflow-y: auto;
}

.service-item, .user-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.service-item:last-child, .user-item:last-child { border-bottom: none; }

.service-stats {
    text-align: right;
}

.booking-count {
    display: block;
    font-size: 12px;
    color: var(--gray);
}

.price {
    display: block;
    font-weight: 600;
    color: var(--primary);
}

.user-item { display: flex; align-items: center; }

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-right: 12px;
}

.user-info { flex: 1; }

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.chart-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.chart-header {
    margin-bottom: 15px;
}

.chart-header h6 {
    margin: 0;
    color: var(--dark);
    font-weight: 600;
}

.chart-container {
    height: 250px;
    position: relative;
}

/* Staff Performance */
.staff-performance-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.performance-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.performance-header h6 {
    margin: 0;
    color: var(--dark);
    font-weight: 600;
}

.performance-table {
    width: 100%;
    border-collapse: collapse;
}

.performance-table th {
    background: var(--light);
    padding: 12px;
    font-weight: 600;
    color: var(--dark);
    font-size: 13px;
    text-align: left;
}

.performance-table td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
}

.progress-bar-container {
    width: 120px;
    height: 8px;
    background: var(--border);
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--success), #5cb85c);
    border-radius: 4px;
    font-size: 0;
}

/* Details View */
.details-view {
    text-align: left;
    padding: 10px;
}

.detail-row {
    display: flex;
    padding: 8px 0;
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

/* Staff Selection */
.staff-grid {
    max-height: 400px;
    overflow-y: auto;
}

.staff-option {
    display: flex;
    align-items: center;
    padding: 15px;
    margin-bottom: 8px;
    background: var(--light);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
}

.staff-option:hover {
    border-color: var(--primary);
    transform: translateX(5px);
}

.staff-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 15px;
}

.staff-info { flex: 1; }
.staff-info small { display: block; color: var(--gray); }

/* Empty States */
.empty-state {
    text-align: center;
    padding: 30px;
    color: var(--gray);
}

.empty-state i { font-size: 48px; color: var(--border); margin-bottom: 15px; }
.empty-state.small { padding: 20px; }
.empty-state.small i { font-size: 32px; }

/* Amount Styling */
.amount { font-weight: 600; color: var(--primary); }

.date, .time { font-size: 13px; }

/* Badges */
.badge.bg-warning { background: var(--warning) !important; color: #856404; }
.badge.bg-secondary { background: var(--border) !important; color: var(--gray); }

/* Responsive */
@media (max-width: 1200px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card { padding: 20px; }
    .stat-value { font-size: 24px; }
    .stat-icon { width: 50px; height: 50px; font-size: 20px; }
}

@media (max-width: 576px) {
    .stats-grid { grid-template-columns: 1fr; }
    .quick-actions-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
</body>
</html>
<?php ob_end_flush(); ?>
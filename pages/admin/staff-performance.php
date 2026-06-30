<?php
// staff-performance.php - Staff Performance Dashboard
session_start();
require_once '../../includes/dbcon.php';

// Check if user is logged in and has staff/admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$staff_name = $_SESSION['fName'] ?? 'Staff';
$role = $_SESSION['role'] ?? 'staff';

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
    $staff_stmt->execute([$user_id]);
    $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        die("No staff record found for user_id: " . $user_id);
    }
    
    $staff_db_id = $staff['id'] ?? 0;
    $staff_name = $staff['fName'] ?? $staff_name;
    
    // Get unread notifications count
    $notif_count_query = "SELECT COUNT(*) as count FROM staff_notifications WHERE staff_id = ? AND is_read = 0";
    $notif_count_stmt = $pdo->prepare($notif_count_query);
    $notif_count_stmt->execute([$staff_db_id]);
    $unread_count = $notif_count_stmt->fetch()['count'] ?? 0;
    
    // Get performance data
    $performance_query = "
        SELECT * FROM staff_performance 
        WHERE staff_id = ? 
        ORDER BY year DESC, month DESC 
        LIMIT 12
    ";
    $performance_stmt = $pdo->prepare($performance_query);
    $performance_stmt->execute([$staff_db_id]);
    $performance_data = $performance_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get totals
    $totals_query = "
        SELECT 
            SUM(total_appointments) as total_appointments,
            SUM(completed_appointments) as completed_appointments,
            SUM(cancelled_appointments) as cancelled_appointments,
            AVG(avg_rating) as avg_rating,
            SUM(total_revenue) as total_revenue
        FROM staff_performance 
        WHERE staff_id = ?
    ";
    $totals_stmt = $pdo->prepare($totals_query);
    $totals_stmt->execute([$staff_db_id]);
    $totals = $totals_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current month performance
    $current_month = date('n');
    $current_year = date('Y');
    $current_stmt = $pdo->prepare("
        SELECT * FROM staff_performance 
        WHERE staff_id = ? AND month = ? AND year = ?
    ");
    $current_stmt->execute([$staff_db_id, $current_month, $current_year]);
    $current_performance = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Include staff header
include 'header/header-staff.php';
?>

<style>
.performance-stat {
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.performance-stat .value {
    font-size: 28px;
    font-weight: 700;
    color: #6f42c1;
}
.performance-stat .label {
    font-size: 13px;
    color: #6c757d;
}
.month-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
}
.month-card .month-name {
    font-weight: 600;
    font-size: 16px;
}
.month-card .stat-value {
    font-weight: 600;
    color: #6f42c1;
}
</style>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="welcome-text">
                            <h1><i class="fas fa-chart-line me-2"></i>My Performance</h1>
                            <p class="lead mb-0">View your performance metrics and analytics</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-light text-dark p-2 me-2">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($staff_name); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Current Month Stats -->
            <div class="row mb-4">
                <div class="col-md-3 col-6">
                    <div class="performance-stat">
                        <div class="value"><?php echo $current_performance['total_appointments'] ?? 0; ?></div>
                        <div class="label">This Month's Appointments</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="performance-stat">
                        <div class="value"><?php echo $current_performance['completed_appointments'] ?? 0; ?></div>
                        <div class="label">Completed This Month</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="performance-stat">
                        <div class="value"><?php echo number_format($current_performance['avg_rating'] ?? 0, 1); ?></div>
                        <div class="label">Avg Rating This Month</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="performance-stat">
                        <div class="value">Rs: <?php echo number_format($current_performance['total_revenue'] ?? 0, 2); ?></div>
                        <div class="label">Revenue This Month</div>
                    </div>
                </div>
            </div>

            <!-- Overall Stats -->
            <div class="row mb-4">
                <div class="col-md-3 col-6">
                    <div class="card" style="border-left: 4px solid #6f42c1;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $totals['total_appointments'] ?? 0; ?></h5>
                            <small class="text-muted">Total Appointments</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card" style="border-left: 4px solid #28a745;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $totals['completed_appointments'] ?? 0; ?></h5>
                            <small class="text-muted">Total Completed</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card" style="border-left: 4px solid #ffc107;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo number_format($totals['avg_rating'] ?? 0, 1); ?></h5>
                            <small class="text-muted">Overall Rating</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card" style="border-left: 4px solid #17a2b8;">
                        <div class="card-body">
                            <h5 class="card-title mb-0">Rs: <?php echo number_format($totals['total_revenue'] ?? 0, 2); ?></h5>
                            <small class="text-muted">Total Revenue</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Performance -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Monthly Performance</h5>
                    <span class="badge bg-primary float-end"><?php echo count($performance_data); ?> Months</span>
                </div>
                <div class="card-body">
                    <?php if (empty($performance_data)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-bar fa-4x text-muted mb-3 d-block"></i>
                        <h5>No Performance Data</h5>
                        <p class="text-muted">Performance data will appear as you complete appointments.</p>
                    </div>
                    <?php else: ?>
                        <?php 
                        $month_names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                        foreach ($performance_data as $perf):
                        ?>
                        <div class="month-card">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="month-name">
                                        <?php echo $month_names[$perf['month'] - 1] . ' ' . $perf['year']; ?>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div>
                                        <span class="stat-value"><?php echo $perf['total_appointments']; ?></span>
                                        <small class="text-muted">Appointments</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div>
                                        <span class="stat-value"><?php echo $perf['completed_appointments']; ?></span>
                                        <small class="text-muted">Completed</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div>
                                        <span class="stat-value"><?php echo number_format($perf['avg_rating'], 1); ?></span>
                                        <small class="text-muted">Rating</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div>
                                        <span class="stat-value">Rs: <?php echo number_format($perf['total_revenue'], 2); ?></span>
                                        <small class="text-muted">Revenue</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $perf['total_appointments'] > 0 ? ($perf['completed_appointments'] / $perf['total_appointments']) * 100 : 0; ?>%;"></div>
                                    </div>
                                    <small class="text-muted">Completion Rate</small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show notifications function for bell icon
function showNotifications() {
    Swal.fire({
        title: '<i class="fas fa-bell me-2"></i> Notifications',
        html: `
            <div class="text-start">
                <p><strong>Total:</strong> <?php echo count($performance_data); ?></p>
                <p><strong>Unread:</strong> <?php echo $unread_count ?? 0; ?></p>
                <hr>
                <p class="text-muted small">Click "View All" to see all notifications</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'View All',
        cancelButtonText: 'Close',
        confirmButtonColor: '#6f42c1'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'notifications.php';
        }
    });
}
</script>
</body>
</html>
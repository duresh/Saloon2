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
    
    // Get performance data - using appointments and staff_ratings
    // Get total appointments, completed, cancelled by month
    $performance_query = "
        SELECT 
            MONTH(a.appointment_date) as month,
            YEAR(a.appointment_date) as year,
            COUNT(*) as total_appointments,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
            SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
            ROUND(AVG(sr.rating), 2) as avg_rating,
            COUNT(sr.id) as total_ratings,
            SUM(CASE WHEN sr.rating = 5 THEN 1 ELSE 0 END) as rating_5,
            SUM(CASE WHEN sr.rating = 4 THEN 1 ELSE 0 END) as rating_4,
            SUM(CASE WHEN sr.rating = 3 THEN 1 ELSE 0 END) as rating_3,
            SUM(CASE WHEN sr.rating = 2 THEN 1 ELSE 0 END) as rating_2,
            SUM(CASE WHEN sr.rating = 1 THEN 1 ELSE 0 END) as rating_1,
            SUM(srv.price) as total_revenue
        FROM appointments a
        LEFT JOIN services srv ON a.service_id = srv.id
        LEFT JOIN staff_ratings sr ON a.id = sr.appointment_id AND sr.staff_id = a.staff_id
        WHERE a.staff_id = ?
        GROUP BY YEAR(a.appointment_date), MONTH(a.appointment_date)
        ORDER BY year DESC, month DESC
        LIMIT 12
    ";
    $performance_stmt = $pdo->prepare($performance_query);
    $performance_stmt->execute([$staff_db_id]);
    $performance_data = $performance_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get totals
    $totals_query = "
        SELECT 
            COUNT(*) as total_appointments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
            (SELECT ROUND(AVG(rating), 2) FROM staff_ratings WHERE staff_id = ?) as avg_rating,
            (SELECT COUNT(*) FROM staff_ratings WHERE staff_id = ?) as total_ratings,
            (SELECT SUM(srv.price) FROM appointments a JOIN services srv ON a.service_id = srv.id WHERE a.staff_id = ? AND a.status = 'completed') as total_revenue
        FROM appointments a
        WHERE staff_id = ?
    ";
    $totals_stmt = $pdo->prepare($totals_query);
    $totals_stmt->execute([$staff_db_id, $staff_db_id, $staff_db_id, $staff_db_id]);
    $totals = $totals_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current month performance
    $current_month = date('n');
    $current_year = date('Y');
    $current_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_appointments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
            (SELECT ROUND(AVG(rating), 2) FROM staff_ratings WHERE staff_id = ? AND MONTH(created_at) = ? AND YEAR(created_at) = ?) as avg_rating,
            (SELECT SUM(srv.price) FROM appointments a JOIN services srv ON a.service_id = srv.id WHERE a.staff_id = ? AND a.status = 'completed' AND MONTH(a.appointment_date) = ? AND YEAR(a.appointment_date) = ?) as total_revenue
        FROM appointments a
        WHERE staff_id = ? 
        AND MONTH(appointment_date) = ? 
        AND YEAR(appointment_date) = ?
    ");
    $current_stmt->execute([$staff_db_id, $current_month, $current_year, $staff_db_id, $current_month, $current_year, $staff_db_id, $current_month, $current_year]);
    $current_performance = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no current performance data, set defaults
    if (!$current_performance) {
        $current_performance = [
            'total_appointments' => 0,
            'completed_appointments' => 0,
            'cancelled_appointments' => 0,
            'avg_rating' => 0,
            'total_revenue' => 0
        ];
    }
    
    // If no totals, set defaults
    if (!$totals) {
        $totals = [
            'total_appointments' => 0,
            'completed_appointments' => 0,
            'cancelled_appointments' => 0,
            'avg_rating' => 0,
            'total_ratings' => 0,
            'total_revenue' => 0
        ];
    }
    
    // Calculate rating distribution
    $rating_distribution = [
        5 => 0,
        4 => 0,
        3 => 0,
        2 => 0,
        1 => 0
    ];
    
    $rating_query = "SELECT rating, COUNT(*) as count FROM staff_ratings WHERE staff_id = ? GROUP BY rating";
    $rating_stmt = $pdo->prepare($rating_query);
    $rating_stmt->execute([$staff_db_id]);
    $rating_data = $rating_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rating_data as $rd) {
        $rating_distribution[$rd['rating']] = $rd['count'];
    }
    
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
    transition: all 0.2s;
}
.month-card:hover {
    background: #e9ecef;
}
.month-card .month-name {
    font-weight: 600;
    font-size: 16px;
}
.month-card .stat-value {
    font-weight: 600;
    color: #6f42c1;
}
.rating-bar {
    height: 8px;
    border-radius: 4px;
    background: #e9ecef;
    overflow: hidden;
}
.rating-bar .fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s;
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

            <!-- Rating Distribution -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-star me-2" style="color: #ffc107;"></i>Rating Distribution</h5>
                    <span class="badge bg-primary float-end"><?php echo $totals['total_ratings'] ?? 0; ?> Reviews</span>
                </div>
                <div class="card-body">
                    <?php 
                    $total_ratings = $totals['total_ratings'] ?? 1;
                    $colors = ['#dc3545', '#fd7e14', '#ffc107', '#17a2b8', '#28a745'];
                    ?>
                    <?php for ($i = 5; $i >= 1; $i--): 
                        $count = $rating_distribution[$i] ?? 0;
                        $percentage = $total_ratings > 0 ? ($count / $total_ratings) * 100 : 0;
                    ?>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <span style="width: 60px; font-size: 13px; font-weight: 500;">
                            <?php echo $i; ?> <i class="fas fa-star" style="color: <?php echo $colors[$i-1]; ?>; font-size: 12px;"></i>
                        </span>
                        <div class="flex-grow-1 rating-bar">
                            <div class="fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $colors[$i-1]; ?>;"></div>
                        </div>
                        <span style="width: 40px; font-size: 13px; color: #6c757d; text-align: right;"><?php echo $count; ?></span>
                    </div>
                    <?php endfor; ?>
                    
                    <?php if ($total_ratings <= 1): ?>
                    <div class="text-center py-3 text-muted">
                        <i class="fas fa-info-circle me-1"></i> No ratings yet to display distribution
                    </div>
                    <?php endif; ?>
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
                            $completion_rate = $perf['total_appointments'] > 0 ? round(($perf['completed_appointments'] / $perf['total_appointments']) * 100) : 0;
                        ?>
                        <div class="month-card">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <div class="month-name">
                                        <?php echo $month_names[$perf['month'] - 1] . ' ' . $perf['year']; ?>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div>
                                        <span class="stat-value"><?php echo $perf['total_appointments']; ?></span>
                                        <small class="text-muted d-block">Appointments</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div>
                                        <span class="stat-value"><?php echo $perf['completed_appointments']; ?></span>
                                        <small class="text-muted d-block">Completed</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div>
                                        <span class="stat-value"><?php echo number_format($perf['avg_rating'] ?? 0, 1); ?></span>
                                        <small class="text-muted d-block">Rating</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div>
                                        <span class="stat-value">Rs: <?php echo number_format($perf['total_revenue'] ?? 0, 2); ?></span>
                                        <small class="text-muted d-block">Revenue</small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $completion_rate; ?>%;"></div>
                                        </div>
                                        <small class="text-muted d-block mt-1"><?php echo $completion_rate; ?>% Complete</small>
                                    </div>
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
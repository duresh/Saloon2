<?php
ob_start();
session_start();

// Check if user is logged in and is staff
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../user/dashboard.php');
    exit();
}

require_once '../../includes/dbcon.php';
require_once '../../includes/helpers.php';

$user_id = $_SESSION['user_id'];

try {
    $pdo = getPDOConnection();
    
    // Get staff details
    $staff_query = "
        SELECT s.*, r.fName as staff_name 
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE s.user_id = ? AND r.cStatus = 1
    ";
    $staff_stmt = $pdo->prepare($staff_query);
    $staff_stmt->execute([$user_id]);
    $staff = $staff_stmt->fetch();
    
    if (!$staff) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
    
    $staff_id = $staff['id'];
    
    // Get rating summary
    $summary_query = "
        SELECT 
            COUNT(*) as total_ratings,
            ROUND(AVG(rating), 2) as average_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
        FROM staff_ratings
        WHERE staff_id = ?
    ";
    $summary_stmt = $pdo->prepare($summary_query);
    $summary_stmt->execute([$staff_id]);
    $summary = $summary_stmt->fetch();
    
    // Get recent ratings
    $ratings_query = "
        SELECT 
            sr.*,
            u.fName as customer_name,
            u.email as customer_email,
            a.appointment_date
        FROM staff_ratings sr
        JOIN reg u ON sr.user_id = u.regID
        JOIN appointments a ON sr.appointment_id = a.id
        WHERE sr.staff_id = ?
        ORDER BY sr.created_at DESC
        LIMIT 20
    ";
    $ratings_stmt = $pdo->prepare($ratings_query);
    $ratings_stmt->execute([$staff_id]);
    $ratings = $ratings_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Staff ratings error: ' . $e->getMessage());
    $error = 'Unable to load ratings. Please try again later.';
}

include 'header/header-staff.php';
?>

<div class="main-content" id="mainContent">
    <div class="ratings-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-star me-2" style="color: #ffc107;"></i> My Ratings</h1>
                    <p class="lead mb-0">View customer feedback and ratings</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="staff-dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Rating Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                    <i class="fas fa-star"></i>
                </div>
                <div class="summary-details">
                    <div class="summary-value"><?php echo number_format($summary['average_rating'] ?? 0, 1); ?></div>
                    <div class="summary-label">Average Rating</div>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="summary-details">
                    <div class="summary-value"><?php echo $summary['total_ratings'] ?? 0; ?></div>
                    <div class="summary-label">Total Ratings</div>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="summary-details">
                    <div class="summary-value"><?php echo round(($summary['rating_5'] / max($summary['total_ratings'], 1)) * 100, 1); ?>%</div>
                    <div class="summary-label">5-Star Rate</div>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="summary-details">
                    <div class="summary-value"><?php echo $summary['rating_5'] ?? 0; ?></div>
                    <div class="summary-label">5-Star Reviews</div>
                </div>
            </div>
        </div>

        <!-- Rating Distribution -->
        <div class="distribution-card">
            <h5>Rating Distribution</h5>
            <div class="distribution-bars">
                <div class="distribution-row">
                    <span class="star-label">5 Stars</span>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo ($summary['rating_5'] / max($summary['total_ratings'], 1)) * 100; ?>%"></div>
                    </div>
                    <span class="star-count"><?php echo $summary['rating_5'] ?? 0; ?></span>
                </div>
                <div class="distribution-row">
                    <span class="star-label">4 Stars</span>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo ($summary['rating_4'] / max($summary['total_ratings'], 1)) * 100; ?>%"></div>
                    </div>
                    <span class="star-count"><?php echo $summary['rating_4'] ?? 0; ?></span>
                </div>
                <div class="distribution-row">
                    <span class="star-label">3 Stars</span>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo ($summary['rating_3'] / max($summary['total_ratings'], 1)) * 100; ?>%"></div>
                    </div>
                    <span class="star-count"><?php echo $summary['rating_3'] ?? 0; ?></span>
                </div>
                <div class="distribution-row">
                    <span class="star-label">2 Stars</span>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo ($summary['rating_2'] / max($summary['total_ratings'], 1)) * 100; ?>%"></div>
                    </div>
                    <span class="star-count"><?php echo $summary['rating_2'] ?? 0; ?></span>
                </div>
                <div class="distribution-row">
                    <span class="star-label">1 Star</span>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo ($summary['rating_1'] / max($summary['total_ratings'], 1)) * 100; ?>%"></div>
                    </div>
                    <span class="star-count"><?php echo $summary['rating_1'] ?? 0; ?></span>
                </div>
            </div>
        </div>

        <!-- Ratings List -->
        <div class="ratings-card">
            <div class="card-header">
                <h5><i class="fas fa-list me-2"></i> Customer Reviews</h5>
                <div class="card-actions">
                    <span class="badge bg-info">Latest 20 Reviews</span>
                </div>
            </div>
            
            <div class="ratings-list">
                <?php if (empty($ratings)): ?>
                <div class="empty-state">
                    <i class="fas fa-star fa-4x text-muted mb-3"></i>
                    <h5>No Ratings Yet</h5>
                    <p class="text-muted">Complete appointments to receive customer ratings.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($ratings as $rating): ?>
                    <div class="rating-item">
                        <div class="rating-header">
                            <div class="customer-info">
                                <div class="customer-avatar">
                                    <?php echo strtoupper(substr($rating['customer_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($rating['customer_name']); ?></strong>
                                    <div class="stars">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $rating['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="rating-date">
                                <?php echo date('M d, Y', strtotime($rating['created_at'])); ?>
                            </div>
                        </div>
                        <div class="rating-body">
                            <p><?php echo nl2br(htmlspecialchars($rating['comments'] ?: 'No comments provided.')); ?></p>
                            <small class="text-muted">
                                <i class="fas fa-cut me-1"></i> Service: <?php echo htmlspecialchars($rating['service_name']); ?>
                                <br>
                                <i class="fas fa-calendar me-1"></i> Appointment on: <?php echo date('M d, Y', strtotime($rating['appointment_date'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

<style>
.ratings-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Summary Grid */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-left: 4px solid;
}

.summary-card:first-child { border-left-color: #ffc107; }
.summary-card:nth-child(2) { border-left-color: #28a745; }
.summary-card:nth-child(3) { border-left-color: #17a2b8; }
.summary-card:last-child { border-left-color: #dc3545; }

.summary-icon {
    width: 55px;
    height: 55px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.summary-value {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
}

.summary-label {
    font-size: 13px;
    color: #6c757d;
}

/* Distribution Card */
.distribution-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.distribution-card h5 {
    margin-bottom: 20px;
    color: #333;
    font-weight: 600;
}

.distribution-row {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 12px;
}

.star-label {
    width: 70px;
    font-size: 14px;
}

.progress-bar-container {
    flex: 1;
    height: 10px;
    background: #e9ecef;
    border-radius: 5px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: #ffc107;
    border-radius: 5px;
    transition: width 0.5s;
}

.star-count {
    width: 40px;
    font-size: 13px;
    color: #6c757d;
}

/* Ratings Card */
.ratings-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.card-header {
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
}

.ratings-list {
    padding: 20px;
}

.rating-item {
    border-bottom: 1px solid #e9ecef;
    padding: 20px 0;
}

.rating-item:last-child {
    border-bottom: none;
}

.rating-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 10px;
}

.customer-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.customer-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6f42c1, #9b6fe0);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
}

.stars {
    margin-top: 4px;
}

.stars i {
    font-size: 13px;
    margin-right: 2px;
}

.rating-date {
    font-size: 12px;
    color: #6c757d;
}

.rating-body p {
    margin-bottom: 8px;
    color: #495057;
    line-height: 1.5;
}

/* Responsive */
@media (max-width: 768px) {
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .distribution-row {
        gap: 8px;
    }
    .star-label {
        width: 55px;
        font-size: 12px;
    }
}
</style>
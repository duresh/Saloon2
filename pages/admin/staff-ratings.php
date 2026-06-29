<?php
// staff-ratings.php - COMPLETE STANDALONE VERSION
session_start();
require_once '../../includes/dbcon.php';

// Check if user is logged in and has staff/admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$staff_name = $_SESSION['fName'] ?? 'Staff';

// Get staff details and rating data
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
    $summary_stmt->execute([$staff_db_id]);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$summary) {
        $summary = [
            'total_ratings' => 0,
            'average_rating' => 0,
            'rating_5' => 0,
            'rating_4' => 0,
            'rating_3' => 0,
            'rating_2' => 0,
            'rating_1' => 0
        ];
    }
    
    // Get ratings with customer info
    $ratings_query = "
        SELECT 
            sr.id,
            sr.rating,
            sr.review,
            sr.comments,
            sr.service_name,
            sr.response,
            sr.created_at,
            sr.responded_at,
            sr.responded_by,
            u.fName as customer_name,
            u.lName as customer_lname,
            u.email as customer_email,
            a.appointment_date,
            a.appointment_time,
            a.id as appointment_id
        FROM staff_ratings sr
        JOIN reg u ON sr.user_id = u.regID
        LEFT JOIN appointments a ON sr.appointment_id = a.id
        WHERE sr.staff_id = ?
        ORDER BY sr.created_at DESC
    ";
    $ratings_stmt = $pdo->prepare($ratings_query);
    $ratings_stmt->execute([$staff_db_id]);
    $ratings = $ratings_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Include staff header
include 'header/header-staff.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Debug Info (remove after testing) -->
            <div class="alert alert-info">
                <strong>Staff ID:</strong> <?php echo $staff_db_id; ?> | 
                <strong>Total Ratings:</strong> <?php echo $summary['total_ratings']; ?> | 
                <strong>Ratings Found:</strong> <?php echo count($ratings); ?>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-6">
                    <div class="card" style="border-left: 4px solid #6f42c1;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo number_format($summary['average_rating'] ?? 0, 1); ?></h5>
                            <small class="text-muted">Average Rating</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card" style="border-left: 4px solid #28a745;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $summary['total_ratings'] ?? 0; ?></h5>
                            <small class="text-muted">Total Ratings</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card" style="border-left: 4px solid #17a2b8;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo round(($summary['rating_5'] / max($summary['total_ratings'], 1)) * 100, 1); ?>%</h5>
                            <small class="text-muted">5-Star Rate</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card" style="border-left: 4px solid #ffc107;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $summary['rating_5'] ?? 0; ?></h5>
                            <small class="text-muted">5-Star Reviews</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rating Distribution -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Rating Distribution</h5>
                </div>
                <div class="card-body">
                    <?php 
                    $ratings_data = [
                        5 => ['label' => '5 Stars', 'count' => $summary['rating_5'] ?? 0, 'color' => '#28a745'],
                        4 => ['label' => '4 Stars', 'count' => $summary['rating_4'] ?? 0, 'color' => '#17a2b8'],
                        3 => ['label' => '3 Stars', 'count' => $summary['rating_3'] ?? 0, 'color' => '#ffc107'],
                        2 => ['label' => '2 Stars', 'count' => $summary['rating_2'] ?? 0, 'color' => '#fd7e14'],
                        1 => ['label' => '1 Star', 'count' => $summary['rating_1'] ?? 0, 'color' => '#dc3545']
                    ];
                    $total = $summary['total_ratings'] ?? 1;
                    ?>
                    <?php if ($total > 0): ?>
                        <?php foreach ($ratings_data as $stars => $data): ?>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <span style="width: 80px; font-size: 13px;">
                                <?php echo $stars; ?> <i class="fas fa-star" style="color: <?php echo $data['color']; ?>;"></i>
                            </span>
                            <div class="flex-grow-1" style="height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden;">
                                <div style="height: 100%; width: <?php echo ($data['count'] / $total) * 100; ?>%; background: <?php echo $data['color']; ?>;"></div>
                            </div>
                            <span style="width: 40px; text-align: right;"><?php echo $data['count']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted">No ratings yet</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ratings List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Customer Reviews</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($ratings)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-star fa-4x text-muted mb-3 d-block"></i>
                        <h5>No Ratings Yet</h5>
                        <p class="text-muted">Complete appointments to receive customer ratings.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($ratings as $rating): 
                            $review_text = !empty($rating['review']) ? $rating['review'] : ($rating['comments'] ?? '');
                        ?>
                        <div class="border-bottom py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($rating['customer_name'] . ' ' . ($rating['customer_lname'] ?? '')); ?></strong>
                                    <div>
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $rating['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($rating['created_at'])); ?></small>
                            </div>
                            <?php if (!empty($review_text)): ?>
                            <p class="mt-2 mb-1"><?php echo nl2br(htmlspecialchars($review_text)); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($rating['service_name'])): ?>
                            <small class="text-muted"><i class="fas fa-cut me-1"></i> Service: <?php echo htmlspecialchars($rating['service_name']); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Notification function for bell icon
function showNotifications() {
    Swal.fire({
        title: 'Notifications',
        text: 'No new notifications',
        icon: 'info',
        confirmButtonColor: '#6f42c1'
    });
}
</script>
</body>
</html>
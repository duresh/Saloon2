<?php
ob_start();
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../../includes/dbcon.php';
require_once '../../includes/helpers.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'average_rating';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Initialize variables
$staff_ratings = [];
$total_records = 0;
$total_pages = 0;
$stats = [
    'total_staff' => 0,
    'overall_avg_rating' => 0,
    'high_ratings' => 0,
    'low_ratings' => 0,
    'total_5_star' => 0,
    'total_4_star' => 0,
    'total_3_star' => 0,
    'total_2_star' => 0,
    'total_1_star' => 0,
    'total_ratings_given' => 0
];
$top_performers = [];

try {
    $pdo = getPDOConnection();
    
    // Get all staff with rating summaries
    $query = "
        SELECT 
            s.id,
            s.user_id,
            r.fName as staff_name,
            r.email,
            r.contactNo,
            s.specialization,
            s.experience_years,
            s.joining_date,
            COUNT(DISTINCT sr.id) as total_ratings,
            ROUND(COALESCE(AVG(sr.rating), 0), 2) as average_rating,
            SUM(CASE WHEN sr.rating = 5 THEN 1 ELSE 0 END) as rating_5,
            SUM(CASE WHEN sr.rating = 4 THEN 1 ELSE 0 END) as rating_4,
            SUM(CASE WHEN sr.rating = 3 THEN 1 ELSE 0 END) as rating_3,
            SUM(CASE WHEN sr.rating = 2 THEN 1 ELSE 0 END) as rating_2,
            SUM(CASE WHEN sr.rating = 1 THEN 1 ELSE 0 END) as rating_1,
            COUNT(DISTINCT a.id) as total_appointments,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        LEFT JOIN staff_ratings sr ON s.id = sr.staff_id
        LEFT JOIN appointments a ON s.id = a.staff_id
        WHERE r.cStatus = 1
    ";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (r.fName LIKE ? OR r.email LIKE ? OR s.specialization LIKE ?)";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param];
    }
    
    $query .= " GROUP BY s.id";
    
    // Sorting
    $allowed_sort = ['staff_name', 'specialization', 'total_ratings', 'average_rating', 'total_appointments', 'experience_years'];
    $sort_by = in_array($sort_by, $allowed_sort) ? $sort_by : 'average_rating';
    $sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';
    $query .= " ORDER BY $sort_by $sort_order";
    
    // Get total count
    $count_query = "
        SELECT COUNT(DISTINCT s.id) as total
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE r.cStatus = 1
    ";
    
    if (!empty($search)) {
        $count_query .= " AND (r.fName LIKE ? OR r.email LIKE ? OR s.specialization LIKE ?)";
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->execute([$search_param, $search_param, $search_param]);
    } else {
        $count_stmt = $pdo->query($count_query);
    }
    
    $total_records = $count_stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total_records / $records_per_page);
    
    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $records_per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $staff_ratings = $stmt->fetchAll();
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(DISTINCT s.id) as total_staff,
            ROUND(COALESCE(AVG(sr.rating), 0), 2) as overall_avg_rating,
            COUNT(CASE WHEN sr.rating >= 4 THEN 1 END) as high_ratings,
            COUNT(CASE WHEN sr.rating <= 2 THEN 1 END) as low_ratings,
            SUM(CASE WHEN sr.rating = 5 THEN 1 ELSE 0 END) as total_5_star,
            SUM(CASE WHEN sr.rating = 4 THEN 1 ELSE 0 END) as total_4_star,
            SUM(CASE WHEN sr.rating = 3 THEN 1 ELSE 0 END) as total_3_star,
            SUM(CASE WHEN sr.rating = 2 THEN 1 ELSE 0 END) as total_2_star,
            SUM(CASE WHEN sr.rating = 1 THEN 1 ELSE 0 END) as total_1_star,
            COUNT(DISTINCT sr.id) as total_ratings_given
        FROM staff s
        LEFT JOIN staff_ratings sr ON s.id = sr.staff_id
    ";
    $stats_stmt = $pdo->query($stats_query);
    $stats_data = $stats_stmt->fetch();
    
    if ($stats_data) {
        $stats = array_merge($stats, $stats_data);
    }
    
    // Get top performers
    $top_performers_query = "
        SELECT 
            s.id,
            r.fName as staff_name,
            ROUND(COALESCE(AVG(sr.rating), 0), 2) as average_rating,
            COUNT(sr.id) as total_ratings
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        LEFT JOIN staff_ratings sr ON s.id = sr.staff_id
        WHERE r.cStatus = 1
        GROUP BY s.id
        HAVING total_ratings > 0
        ORDER BY average_rating DESC, total_ratings DESC
        LIMIT 5
    ";
    $top_performers_stmt = $pdo->query($top_performers_query);
    $top_performers = $top_performers_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Admin staff ratings error: ' . $e->getMessage());
    $error = 'Unable to load ratings. Please try again later.';
}

include 'header/header-admin.php';
?>

<!-- Page Header -->
<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-star me-2" style="color: #ffc107;"></i> Staff Performance & Ratings</h1>
            <p class="lead mb-0">Monitor staff performance and customer feedback</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-outline-secondary me-2" onclick="exportRatings()">
                <i class="fas fa-download me-2"></i>Export Report
            </button>
            <button class="btn btn-success" onclick="refreshPage()">
                <i class="fas fa-sync-alt me-2"></i>Refresh
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(111, 66, 193, 0.1); color: #6f42c1;">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['total_staff'] ?? 0); ?></div>
            <div class="stat-label">Total Staff</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['overall_avg_rating'] ?? 0, 1); ?></div>
            <div class="stat-label">Overall Rating</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['high_ratings'] ?? 0); ?></div>
            <div class="stat-label">High Ratings (4-5★)</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['low_ratings'] ?? 0); ?></div>
            <div class="stat-label">Low Ratings (1-2★)</div>
        </div>
    </div>
</div>

<!-- Rating Distribution Overview -->
<div class="filter-card">
    <div class="filter-header">
        <h5><i class="fas fa-chart-pie me-2"></i> Overall Rating Distribution</h5>
    </div>
    <div class="filter-body">
        <?php
        $total_ratings = max($stats['total_ratings_given'] ?? 0, 1);
        ?>
        <div class="distribution-bars">
            <div class="distribution-row">
                <span class="star-label">5 Stars <i class="fas fa-star text-warning ms-1"></i></span>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill rating-5" style="width: <?php echo ($stats['total_5_star'] / $total_ratings) * 100; ?>%"></div>
                </div>
                <span class="star-count"><?php echo number_format($stats['total_5_star'] ?? 0); ?></span>
            </div>
            <div class="distribution-row">
                <span class="star-label">4 Stars <i class="fas fa-star text-warning ms-1"></i></span>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill rating-4" style="width: <?php echo ($stats['total_4_star'] / $total_ratings) * 100; ?>%"></div>
                </div>
                <span class="star-count"><?php echo number_format($stats['total_4_star'] ?? 0); ?></span>
            </div>
            <div class="distribution-row">
                <span class="star-label">3 Stars <i class="fas fa-star text-warning ms-1"></i></span>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill rating-3" style="width: <?php echo ($stats['total_3_star'] / $total_ratings) * 100; ?>%"></div>
                </div>
                <span class="star-count"><?php echo number_format($stats['total_3_star'] ?? 0); ?></span>
            </div>
            <div class="distribution-row">
                <span class="star-label">2 Stars <i class="fas fa-star text-warning ms-1"></i></span>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill rating-2" style="width: <?php echo ($stats['total_2_star'] / $total_ratings) * 100; ?>%"></div>
                </div>
                <span class="star-count"><?php echo number_format($stats['total_2_star'] ?? 0); ?></span>
            </div>
            <div class="distribution-row">
                <span class="star-label">1 Star <i class="fas fa-star text-warning ms-1"></i></span>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill rating-1" style="width: <?php echo ($stats['total_1_star'] / $total_ratings) * 100; ?>%"></div>
                </div>
                <span class="star-count"><?php echo number_format($stats['total_1_star'] ?? 0); ?></span>
            </div>
        </div>
        <div class="total-ratings-note mt-3 text-center">
            <small class="text-muted">Total ratings received: <?php echo number_format($stats['total_ratings_given'] ?? 0); ?></small>
        </div>
    </div>
</div>

<!-- Top Performers -->
<div class="filter-card">
    <div class="filter-header">
        <h5><i class="fas fa-trophy me-2 text-warning"></i> Top Rated Staff</h5>
    </div>
    <div class="filter-body">
        <div class="performers-grid">
            <?php if (!empty($top_performers)): ?>
                <?php foreach ($top_performers as $index => $performer): ?>
                <div class="performer-card" onclick="viewStaffDetails(<?php echo $performer['id']; ?>)">
                    <div class="performer-rank">#<?php echo $index + 1; ?></div>
                    <div class="performer-info">
                        <strong><?php echo htmlspecialchars($performer['staff_name']); ?></strong>
                        <div class="rating">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= floor($performer['average_rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                            <span class="ms-1">(<?php echo number_format($performer['average_rating'], 1); ?>)</span>
                        </div>
                        <small class="text-muted"><?php echo $performer['total_ratings']; ?> reviews</small>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-3 text-muted col-12">No ratings available yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filters Section -->
<div class="filter-card">
    <div class="filter-header">
        <h5><i class="fas fa-filter me-2"></i> Filter Staff Ratings</h5>
    </div>
    <div class="filter-body">
        <form method="GET" action="" id="filterForm">
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label class="form-label fw-bold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Name, email, specialization..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Sort By</label>
                    <select class="form-select" name="sort">
                        <option value="average_rating" <?php echo $sort_by == 'average_rating' ? 'selected' : ''; ?>>Average Rating</option>
                        <option value="total_ratings" <?php echo $sort_by == 'total_ratings' ? 'selected' : ''; ?>>Total Ratings</option>
                        <option value="staff_name" <?php echo $sort_by == 'staff_name' ? 'selected' : ''; ?>>Staff Name</option>
                        <option value="specialization" <?php echo $sort_by == 'specialization' ? 'selected' : ''; ?>>Specialization</option>
                        <option value="total_appointments" <?php echo $sort_by == 'total_appointments' ? 'selected' : ''; ?>>Total Appointments</option>
                        <option value="experience_years" <?php echo $sort_by == 'experience_years' ? 'selected' : ''; ?>>Experience</option>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 me-2">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                    <a href="admin-staff-ratings.php" class="btn btn-outline-secondary" title="Clear Filters">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Staff Ratings Table -->
<div class="table-card">
    <div class="table-header">
        <h5><i class="fas fa-list me-2"></i> Staff Performance List</h5>
        <div class="table-actions">
            <span class="badge bg-info"><?php echo $total_records; ?> Staff Members</span>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Staff Member</th>
                    <th>Specialization</th>
                    <th>Experience</th>
                    <th>Rating</th>
                    <th>Performance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staff_ratings)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="empty-state">
                            <i class="fas fa-star fa-4x text-muted mb-3"></i>
                            <h5>No Staff Found</h5>
                            <p class="text-muted">Try adjusting your filters.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php $sno = $offset + 1; ?>
                    <?php foreach ($staff_ratings as $staff): 
                        $completion_rate = $staff['total_appointments'] > 0 
                            ? round(($staff['completed_appointments'] / $staff['total_appointments']) * 100) 
                            : 0;
                    ?>
                    <tr>
                        <td><span class="fw-bold"><?php echo $sno++; ?></span></td>
                        <td>
                            <div class="staff-info">
                                <div class="staff-avatar-sm">
                                    <?php echo strtoupper(substr($staff['staff_name'], 0, 1)); ?>
                                </div>
                                <div class="staff-details">
                                    <strong><?php echo htmlspecialchars($staff['staff_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($staff['email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="specialization-badge">
                                <?php echo htmlspecialchars($staff['specialization'] ?? 'General'); ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo $staff['experience_years'] ?? 0; ?> years</strong>
                            <br>
                            <small class="text-muted">Joined: <?php echo $staff['joining_date'] ? date('M Y', strtotime($staff['joining_date'])) : 'N/A'; ?></small>
                        </td>
                        <td>
                            <div class="rating-display">
                                <div class="stars-display">
                                    <?php 
                                    $full_stars = floor($staff['average_rating']);
                                    $half_star = ($staff['average_rating'] - $full_stars) >= 0.5;
                                    for($i = 1; $i <= 5; $i++): 
                                        if ($i <= $full_stars):
                                    ?>
                                        <i class="fas fa-star text-warning"></i>
                                    <?php elseif ($half_star && $i == $full_stars + 1): ?>
                                        <i class="fas fa-star-half-alt text-warning"></i>
                                    <?php else: ?>
                                        <i class="far fa-star text-muted"></i>
                                    <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <strong class="ms-1"><?php echo number_format($staff['average_rating'], 1); ?></strong>
                                <br>
                                <small class="text-muted">(<?php echo $staff['total_ratings']; ?> reviews)</small>
                            </div>
                        </td>
                        <td>
                            <div class="performance-stats">
                                <div class="progress" style="height: 5px; width: 100px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $completion_rate; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $staff['completed_appointments'] ?? 0; ?>/<?php echo $staff['total_appointments'] ?? 0; ?> completed</small>
                                <div class="mt-1">
                                    <small><i class="fas fa-calendar-check me-1"></i> <?php echo $staff['total_appointments']; ?> total appointments</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon" onclick="viewStaffRatings(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars($staff['staff_name']); ?>')" title="View Ratings">
                                    <i class="fas fa-star"></i>
                                </button>
                                <button class="btn-icon" onclick="viewStaffDetails(<?php echo $staff['id']; ?>)" title="Staff Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon" onclick="exportStaffReport(<?php echo $staff['id']; ?>)" title="Export Report">
                                    <i class="fas fa-download"></i>
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
                $query_params = [];
                if (!empty($search)) $query_params['search'] = $search;
                if ($sort_by != 'average_rating') $query_params['sort'] = $sort_by;
                if ($sort_order != 'DESC') $query_params['order'] = $sort_order;
                
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
                    <a class->page-link" href="<?php echo $base_url; ?>page=<?php echo $page+1; ?>"><i class="fas fa-angle-right"></i></a>
                </li>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>"><i class="fas fa-angle-double-right"></i></a>
                </li>
            </ul>
        </nav>
        <div class="text-center text-muted mt-2">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> staff members
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Staff Ratings Modal -->
<div class="modal fade" id="staffRatingsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffRatingsModalTitle">Staff Ratings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="staffRatingsModalBody">
                Loading...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

<script>
// View staff ratings
function viewStaffRatings(staffId, staffName) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-staff-ratings.php',
        method: 'POST',
        data: { staff_id: staffId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                const ratings = response.ratings || [];
                const summary = response.summary || {};
                
                let html = `
                    <div class="ratings-modal-content">
                        <div class="summary-stats mb-4">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="stat-box">
                                        <div class="stat-number">${summary.average_rating || 0}</div>
                                        <div class="stat-text">Average Rating</div>
                                        <div class="stars-small mt-1">
                                            ${generateStars(summary.average_rating || 0)}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-box">
                                        <div class="stat-number">${summary.total_ratings || 0}</div>
                                        <div class="stat-text">Total Ratings</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-box">
                                        <div class="stat-number">${summary.completion_rate || 0}%</div>
                                        <div class="stat-text">5-Star Rate</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ratings-list">
                            <h6>Customer Reviews</h6>
                `;
                
                if (ratings.length === 0) {
                    html += '<div class="text-center py-4 text-muted">No ratings yet.</div>';
                } else {
                    ratings.forEach(rating => {
                        html += `
                            <div class="rating-item-modal">
                                <div class="rating-header-modal">
                                    <div>
                                        <strong>${escapeHtml(rating.customer_name)}</strong>
                                        <div class="stars-display">
                                            ${generateStars(rating.rating)}
                                        </div>
                                    </div>
                                    <div class="rating-date">${formatDate(rating.created_at)}</div>
                                </div>
                                <div class="rating-comment">
                                    <p><i class="fas fa-quote-left me-2 text-muted"></i> ${escapeHtml(rating.comments || 'No comments provided.')}</p>
                                    <small class="text-muted">
                                        <i class="fas fa-cut me-1"></i> Service: ${escapeHtml(rating.service_name)}
                                    </small>
                                </div>
                            </div>
                        `;
                    });
                }
                
                html += '</div></div>';
                
                $('#staffRatingsModalTitle').text(`Ratings for ${escapeHtml(staffName)}`);
                $('#staffRatingsModalBody').html(html);
                $('#staffRatingsModal').modal('show');
            } else {
                Swal.fire('Error', response.message || 'Could not load ratings', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to load ratings', 'error');
        }
    });
}

function generateStars(rating) {
    let stars = '';
    const fullStars = Math.floor(rating);
    const halfStar = (rating - fullStars) >= 0.5;
    
    for (let i = 1; i <= 5; i++) {
        if (i <= fullStars) {
            stars += '<i class="fas fa-star text-warning"></i>';
        } else if (halfStar && i === fullStars + 1) {
            stars += '<i class="fas fa-star-half-alt text-warning"></i>';
        } else {
            stars += '<i class="far fa-star text-muted"></i>';
        }
    }
    return stars;
}

// View staff details in SweetAlert modal
function viewStaffDetails(staffId) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-staff-details.php',
        method: 'POST',
        data: { id: staffId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success && response.staff) {
                const s = response.staff;
                
                // Format date
                const joiningDate = s.joining_date ? formatDate(s.joining_date) : 'N/A';
                const registeredDate = formatDate(s.regDate);
                
                // Create rating stars
                const avgRating = parseFloat(s.avg_rating) || 0;
                let ratingStars = '';
                for (let i = 1; i <= 5; i++) {
                    if (i <= Math.floor(avgRating)) {
                        ratingStars += '<i class="fas fa-star text-warning"></i>';
                    } else if (i - 0.5 <= avgRating) {
                        ratingStars += '<i class="fas fa-star-half-alt text-warning"></i>';
                    } else {
                        ratingStars += '<i class="far fa-star text-muted"></i>';
                    }
                }
                
                // Build HTML content
                const html = `
                    <div class="staff-details-modal" style="text-align: left; max-height: 60vh; overflow-y: auto;">
                        <!-- Header with Avatar -->
                        <div class="text-center mb-4">
                            <div class="staff-avatar-large mx-auto">
                                ${s.staff_name.charAt(0).toUpperCase()}
                            </div>
                            <h3 class="mt-2">${escapeHtml(s.staff_name)}</h3>
                            <p class="text-muted">${escapeHtml(s.specialization || 'General')}</p>
                            <div class="rating-stars">
                                ${ratingStars}
                                <span class="ms-2">(${avgRating.toFixed(1)})</span>
                            </div>
                        </div>
                        
                        <!-- Personal Information -->
                        <div class="detail-section">
                            <h6><i class="fas fa-user me-2"></i> Personal Information</h6>
                            <div class="detail-row">
                                <span class="label">Email:</span>
                                <span class="value">${escapeHtml(s.email)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Phone:</span>
                                <span class="value">${escapeHtml(s.contactNo)}</span>
                            </div>
                            ${s.phone ? `
                            <div class="detail-row">
                                <span class="label">Secondary Phone:</span>
                                <span class="value">${escapeHtml(s.phone)}</span>
                            </div>
                            ` : ''}
                            ${s.address ? `
                            <div class="detail-row">
                                <span class="label">Address:</span>
                                <span class="value">${escapeHtml(s.address)}</span>
                            </div>
                            ` : ''}
                        </div>
                        
                        <!-- Professional Information -->
                        <div class="detail-section">
                            <h6><i class="fas fa-briefcase me-2"></i> Professional Information</h6>
                            <div class="detail-row">
                                <span class="label">Specialization:</span>
                                <span class="value">${escapeHtml(s.specialization || 'General')}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Experience:</span>
                                <span class="value">${s.experience_years || 0} years</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Qualification:</span>
                                <span class="value">${escapeHtml(s.qualification || 'N/A')}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Joined:</span>
                                <span class="value">${joiningDate}</span>
                            </div>
                        </div>
                        
                        <!-- Emergency Contact -->
                        ${s.emergency_name ? `
                        <div class="detail-section">
                            <h6><i class="fas fa-ambulance me-2"></i> Emergency Contact</h6>
                            <div class="detail-row">
                                <span class="label">Name:</span>
                                <span class="value">${escapeHtml(s.emergency_name)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Contact:</span>
                                <span class="value">${escapeHtml(s.emergency_contact)}</span>
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- Performance Statistics -->
                        <div class="detail-section">
                            <h6><i class="fas fa-chart-line me-2"></i> Performance Statistics</h6>
                            <div class="row text-center mb-3">
                                <div class="col-md-4">
                                    <div class="stat-box">
                                        <div class="stat-number">${s.total_appointments || 0}</div>
                                        <div class="stat-text">Total Appointments</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-box">
                                        <div class="stat-number">${s.completed_appointments || 0}</div>
                                        <div class="stat-text">Completed</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-box">
                                        <div class="stat-number">${s.completion_rate || 0}%</div>
                                        <div class="stat-text">Completion Rate</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row text-center">
                                <div class="col-md-6">
                                    <div class="stat-box">
                                        <div class="stat-number">${s.total_ratings || 0}</div>
                                        <div class="stat-text">Total Ratings</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stat-box">
                                        <div class="stat-number">${avgRating.toFixed(1)}</div>
                                        <div class="stat-text">Average Rating</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bio -->
                        ${s.bio ? `
                        <div class="detail-section">
                            <h6><i class="fas fa-align-left me-2"></i> Bio</h6>
                            <div class="bio-text">${escapeHtml(s.bio)}</div>
                        </div>
                        ` : ''}
                        
                        <!-- Account Information -->
                        <div class="detail-section">
                            <h6><i class="fas fa-clock me-2"></i> Account Information</h6>
                            <div class="detail-row">
                                <span class="label">Member Since:</span>
                                <span class="value">${registeredDate}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Staff ID:</span>
                                <span class="value">#${s.id}</span>
                            </div>
                        </div>
                    </div>
                `;
                
                Swal.fire({
                    title: `Staff Details: ${escapeHtml(s.staff_name)}`,
                    html: html,
                    width: '700px',
                    showCloseButton: true,
                    showConfirmButton: true,
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#6f42c1',
                    customClass: {
                        container: 'staff-details-swal'
                    }
                });
            } else {
                Swal.fire('Error', response.message || 'Could not load staff details', 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('AJAX Error:', error);
            Swal.fire('Error', 'Failed to load staff details. Please try again.', 'error');
        }
    });
}

function exportStaffReport(staffId) {
    window.location.href = `export-staff-report.php?staff_id=${staffId}`;
}

function exportRatings() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = 'export-staff-ratings.php?' + params.toString();
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
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
</script>

<style>
/* Staff Table Styles - Matching manage-staff.php */
.staff-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
}

.staff-table th {
    background: #f8f9fa;
    padding: 15px;
    font-weight: 600;
    color: #333;
    font-size: 13px;
    text-align: left;
    border-bottom: 2px solid #e9ecef;
}

.staff-table td {
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

.staff-table tbody tr:hover {
    background: #f8f9fa;
}

/* Staff Info */
.staff-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.staff-avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6f42c1, #9b6fe0);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
}

.staff-details {
    line-height: 1.4;
}

/* Specialization Badge */
.specialization-badge {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(111, 66, 193, 0.1);
    color: #6f42c1;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

/* Rating Display */
.rating-display {
    white-space: nowrap;
}

.stars-display {
    display: inline-block;
    white-space: nowrap;
}

.stars-display i {
    font-size: 12px;
    margin-right: 1px;
}

/* Performance Stats */
.performance-stats {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

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
    border-radius: 6px;
    background: #f8f9fa;
    color: #6c757d;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    background: #6f42c1;
    color: white;
    transform: translateY(-2px);
}

.btn-icon.text-danger:hover {
    background: #dc3545;
    color: white;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
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
    border: 1px solid #e9ecef;
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

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #333;
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

/* Distribution Bars */
.distribution-bars {
    max-width: 600px;
    margin: 0 auto;
}

.distribution-row {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 12px;
}

.star-label {
    width: 90px;
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
    border-radius: 5px;
    transition: width 0.5s;
}

.progress-bar-fill.rating-5 { background: #28a745; }
.progress-bar-fill.rating-4 { background: #17a2b8; }
.progress-bar-fill.rating-3 { background: #ffc107; }
.progress-bar-fill.rating-2 { background: #fd7e14; }
.progress-bar-fill.rating-1 { background: #dc3545; }

.star-count {
    width: 50px;
    font-size: 13px;
    color: #6c757d;
    text-align: right;
}

.total-ratings-note {
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

/* Performers Grid */
.performers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 15px;
}

.performer-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    border: 1px solid #e9ecef;
}

.performer-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: #6f42c1;
}

.performer-rank {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #ffc107, #fd7e14);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
}

.performer-info {
    flex: 1;
}

.performer-info strong {
    display: block;
    margin-bottom: 5px;
    color: #333;
}

.performer-info .rating {
    margin: 5px 0;
}

.performer-info .rating i {
    font-size: 12px;
}

/* Table Card */
.table-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}

.table-header {
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h5 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.table-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Table Footer */
.table-footer {
    padding: 20px;
    border-top: 1px solid #e9ecef;
    background: white;
}

/* Pagination */
.pagination {
    margin: 0;
}

.page-link {
    color: #6f42c1;
    border: none;
    margin: 0 3px;
    border-radius: 8px !important;
    padding: 8px 12px;
    font-size: 14px;
}

.page-item.active .page-link {
    background: #6f42c1;
    color: white;
}

.page-item.disabled .page-link {
    color: #adb5bd;
    background: #f8f9fa;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px;
}

.empty-state i {
    font-size: 48px;
    color: #dee2e6;
    margin-bottom: 15px;
}

.empty-state h5 {
    color: #333;
    margin-bottom: 8px;
}

/* Modal */
.ratings-modal-content {
    max-height: 60vh;
    overflow-y: auto;
}

.rating-item-modal {
    border-bottom: 1px solid #e9ecef;
    padding: 15px 0;
}

.rating-item-modal:last-child {
    border-bottom: none;
}

.rating-header-modal {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 10px;
}

.rating-comment p {
    margin-bottom: 8px;
}

.stat-box {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #6f42c1;
}

.stat-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-buttons {
        min-width: 160px;
    }
    
    .distribution-row {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .star-label {
        width: 70px;
        font-size: 12px;
    }
    
    .star-count {
        width: 40px;
        font-size: 11px;
    }
    
    .performers-grid {
        grid-template-columns: 1fr;
    }
}
</style>
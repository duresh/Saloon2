<?php
// services.php - Services Page
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

// Check if user is staff member (redirect to staff-dashboard)
if (isset($_SESSION['role']) && $_SESSION['role'] == 'staff') {
    header('Location: ../admin/staff-dashboard.php');
    exit();
}

// Include database connection
require_once '../../includes/dbcon.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 9;
$offset = ($page - 1) * $records_per_page;

// Filter variables
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';

// Initialize variables
$services = [];
$categories = [];
$popular_services = [];
$stats = [
    'total_services' => 0,
    'total_categories' => 0,
    'min_price' => 0,
    'max_price' => 0,
    'avg_price' => 0,
    'min_duration' => 0,
    'max_duration' => 0
];
$total_records = 0;
$total_pages = 1;

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
    
    // Get unique categories for filter
    $categories_query = "SELECT DISTINCT category FROM services WHERE status = 'active' AND category IS NOT NULL AND category != '' ORDER BY category";
    $categories_stmt = $pdo->query($categories_query);
    $categories = $categories_stmt->fetchAll();
    
    // Build query for services
    $query = "SELECT * FROM services WHERE status = 'active'";
    $count_query = "SELECT COUNT(*) as total FROM services WHERE status = 'active'";
    $params = [];
    
    // Apply category filter
    if (!empty($category_filter)) {
        $query .= " AND category = ?";
        $count_query .= " AND category = ?";
        $params[] = $category_filter;
    }
    
    // Apply search filter
    if (!empty($search)) {
        $query .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)";
        $count_query .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Apply sorting
    switch ($sort_by) {
        case 'price_low':
            $query .= " ORDER BY price ASC";
            break;
        case 'price_high':
            $query .= " ORDER BY price DESC";
            break;
        case 'duration':
            $query .= " ORDER BY duration ASC";
            break;
        case 'popular':
            $query .= " ORDER BY (SELECT COUNT(*) FROM appointments WHERE service_id = services.id) DESC";
            break;
        default:
            $query .= " ORDER BY category, name ASC";
            break;
    }
    
    // Get total records
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $count_result = $count_stmt->fetch();
    $total_records = $count_result ? (int)$count_result['total'] : 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;
    
    // Add pagination
    $query .= " LIMIT :limit OFFSET :offset";
    $params[':limit'] = $records_per_page;
    $params[':offset'] = $offset;
    
    $stmt = $pdo->prepare($query);
    // Bind parameters
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $services = $stmt->fetchAll();
    
    // Get popular services for sidebar
    $popular_query = "
        SELECT s.*, COUNT(a.id) as booking_count
        FROM services s
        LEFT JOIN appointments a ON s.id = a.service_id
        WHERE s.status = 'active'
        GROUP BY s.id
        ORDER BY booking_count DESC
        LIMIT 5
    ";
    $popular_stmt = $pdo->query($popular_query);
    $popular_services = $popular_stmt->fetchAll();
    
    // Get service statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_services,
            COUNT(DISTINCT category) as total_categories,
            COALESCE(MIN(price), 0) as min_price,
            COALESCE(MAX(price), 0) as max_price,
            COALESCE(AVG(price), 0) as avg_price,
            COALESCE(MIN(duration), 0) as min_duration,
            COALESCE(MAX(duration), 0) as max_duration
        FROM services
        WHERE status = 'active'
    ";
    $stats_stmt = $pdo->query($stats_query);
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    error_log('Services page error: ' . $e->getMessage());
    $error = 'Unable to load services. Please try again later.';
    $services = [];
    $popular_services = [];
} catch (Exception $e) {
    error_log('Services page general error: ' . $e->getMessage());
    $error = 'Unable to load services. Please try again later.';
    $services = [];
    $popular_services = [];
}

include 'header/header.php';
?>

<style>
/* Services Page Styles */
.services-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 18px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border-left: 4px solid var(--primary-color);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-card .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.stat-card .stat-details {
    flex: 1;
    min-width: 0;
}

.stat-card .stat-value {
    font-size: 20px;
    font-weight: 700;
    line-height: 1.2;
    color: #333;
}

.stat-card .stat-label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 2px;
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.filter-card .card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    border-radius: 12px 12px 0 0;
}

.filter-card .card-header h5 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.filter-card .card-body {
    padding: 20px;
}

/* Services Grid */
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.service-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(111, 66, 193, 0.12);
}

.service-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), #9b6fe0);
}

.service-category-badge {
    display: inline-block;
    padding: 4px 12px;
    background: rgba(111, 66, 193, 0.1);
    color: var(--primary-color);
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 12px;
}

.service-name {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 10px;
    color: #333;
}

.service-description {
    color: #6c757d;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 15px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.service-features {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.feature {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #6c757d;
}

.feature i {
    color: var(--primary-color);
    width: 16px;
}

.feature .price {
    font-weight: 700;
    color: var(--primary-color);
    font-size: 16px;
}

.service-actions {
    display: flex;
    gap: 10px;
}

.btn-book {
    flex: 1;
    padding: 10px;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.btn-book:hover {
    background: #5a32a0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(111, 66, 193, 0.3);
}

.btn-details {
    padding: 10px 15px;
    background: #f8f9fa;
    color: var(--primary-color);
    border: 1px solid #e9ecef;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.btn-details:hover {
    background: #e9ecef;
}

/* Sidebar */
.services-sidebar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.sidebar-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.sidebar-card .card-title {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
    color: #333;
    font-weight: 600;
    font-size: 16px;
}

/* Popular List */
.popular-list {
    max-height: 400px;
    overflow-y: auto;
}

.popular-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: all 0.3s ease;
}

.popular-item:hover {
    background: #f8f9fa;
    transform: translateX(5px);
}

.popular-item:last-child {
    border-bottom: none;
}

.popular-rank {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, var(--primary-color), #9b6fe0);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
}

.popular-info {
    flex: 1;
}

.popular-info strong {
    display: block;
    font-size: 14px;
    color: #333;
}

.popular-meta {
    display: flex;
    gap: 12px;
    font-size: 11px;
    color: #6c757d;
}

.popular-meta i {
    margin-right: 2px;
}

/* Price Range */
.price-range {
    margin-top: 10px;
}

.range-bar {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 8px;
}

.range-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary-color), #9b6fe0);
    border-radius: 3px;
}

.range-labels {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #6c757d;
}

/* Tips List */
.tips-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.tips-list li {
    padding: 6px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #495057;
}

.tips-list li i {
    color: #28a745;
    width: 18px;
    flex-shrink: 0;
}

/* Help Card */
.help-card {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.help-card .btn-outline-primary {
    color: var(--primary-color);
    border-color: var(--primary-color);
}

.help-card .btn-outline-primary:hover {
    background: var(--primary-color);
    color: white;
}

/* Service Details Modal */
.service-details-modal {
    text-align: left;
}

.service-details-modal .service-category-badge {
    display: inline-block;
    padding: 4px 12px;
    background: rgba(111, 66, 193, 0.1);
    color: var(--primary-color);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 10px;
}

.service-details-modal .service-name {
    font-size: 22px;
    font-weight: 700;
    color: #333;
    margin-bottom: 15px;
}

.service-meta-details {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin: 15px 0 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 500;
}

.meta-item .price {
    color: var(--primary-color);
    font-size: 17px;
}

.service-description-full, .service-benefits {
    margin-top: 20px;
}

.service-description-full h6, .service-benefits h6 {
    color: var(--primary-color);
    margin-bottom: 10px;
    font-weight: 600;
}

.service-benefits ul {
    list-style: none;
    padding: 0;
}

.service-benefits li {
    padding: 5px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.service-benefits li i {
    color: #28a745;
}

/* Pagination */
.pagination-wrapper {
    margin-top: 30px;
    padding: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.pagination {
    margin: 0;
}

.page-link {
    color: var(--primary-color);
    border: none;
    margin: 0 3px;
    border-radius: 8px !important;
    padding: 8px 12px;
}

.page-item.active .page-link {
    background: var(--primary-color);
    color: white;
}

.page-link:hover {
    background: #f3e8ff;
    color: var(--primary-color);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h4 {
    color: #495057;
    margin-bottom: 10px;
}

.empty-state p {
    color: #6c757d;
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 992px) {
    .services-grid {
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .stat-card {
        padding: 12px 15px;
    }
    
    .stat-card .stat-value {
        font-size: 16px;
    }
    
    .services-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .service-card {
        padding: 20px;
    }
    
    .service-meta-details {
        flex-direction: column;
        gap: 10px;
    }
    
    .service-actions {
        flex-direction: column;
    }
    
    .services-sidebar {
        grid-template-columns: 1fr;
    }
    
    .filter-card .card-body .row > div {
        margin-bottom: 10px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    
    .stat-card {
        padding: 10px;
    }
    
    .stat-card .stat-icon {
        width: 36px;
        height: 36px;
        font-size: 16px;
    }
    
    .stat-card .stat-value {
        font-size: 14px;
    }
    
    .stat-card .stat-label {
        font-size: 9px;
    }
}
</style>

<div class="container-fluid">
    <div class="services-container">
        <!-- Page Header -->
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="welcome-text">
                        <h1><i class="fas fa-spa me-2"></i>Our Services</h1>
                        <p class="lead mb-0">Discover our range of professional salon services</p>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-outline-secondary me-2" onclick="location.href='dashboard.php'">
                        <i class="fas fa-arrow-left me-2"></i>Dashboard
                    </button>
                    <button class="btn btn-primary" onclick="location.href='book-appointment.php'">
                        <i class="fas fa-calendar-plus me-2"></i>Book Now
                    </button>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Service Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(111, 66, 193, 0.12); color: #6f42c1;">
                    <i class="fas fa-spa"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['total_services'] ?? 0); ?></div>
                    <div class="stat-label">Services Available</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(23, 162, 184, 0.12); color: #17a2b8;">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['total_categories'] ?? 0); ?></div>
                    <div class="stat-label">Categories</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(40, 167, 69, 0.12); color: #28a745;">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value">Rs: <?php echo number_format($stats['avg_price'] ?? 0, 0); ?></div>
                    <div class="stat-label">Average Price</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.12); color: #ffc107;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo ($stats['min_duration'] ?? 0) . ' - ' . ($stats['max_duration'] ?? 0); ?></div>
                    <div class="stat-label">Duration (mins)</div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filter-card">
            <div class="card-header">
                <h5><i class="fas fa-filter me-2"></i> Filter Services</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Search</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Service name, description..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Category</label>
                            <select class="form-select" name="category" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Sort By</label>
                            <select class="form-select" name="sort" onchange="this.form.submit()">
                                <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
                                <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price (Low to High)</option>
                                <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price (High to Low)</option>
                                <option value="duration" <?php echo $sort_by == 'duration' ? 'selected' : ''; ?>>Duration (Shortest First)</option>
                                <option value="popular" <?php echo $sort_by == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <a href="services.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-undo me-2"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Services Grid -->
        <?php if (empty($services)): ?>
        <div class="empty-state">
            <i class="fas fa-spa"></i>
            <h4>No Services Found</h4>
            <p>Try adjusting your filters or check back later.</p>
            <a href="services.php" class="btn btn-primary">
                <i class="fas fa-undo me-2"></i>Clear Filters
            </a>
        </div>
        <?php else: ?>
        <div class="services-grid">
            <?php foreach ($services as $service): ?>
            <div class="service-card" data-service-id="<?php echo $service['id']; ?>">
                <div class="service-category-badge"><?php echo htmlspecialchars($service['category'] ?? 'General'); ?></div>
                <h5 class="service-name"><?php echo htmlspecialchars($service['name']); ?></h5>
                <p class="service-description"><?php echo htmlspecialchars($service['description'] ?: 'Professional service by our expert stylists.'); ?></p>
                
                <div class="service-features">
                    <div class="feature">
                        <i class="fas fa-clock"></i>
                        <span><?php echo $service['duration']; ?> mins</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-rupee-sign"></i>
                        <span class="price"><?php echo number_format($service['price'], 2); ?></span>
                    </div>
                </div>
                
                <div class="service-actions">
                    <button class="btn-book" onclick="bookService(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['name']); ?>', <?php echo $service['price']; ?>, <?php echo $service['duration']; ?>)">
                        <i class="fas fa-calendar-check me-2"></i>Book Now
                    </button>
                    <button class="btn-details" onclick="viewDetails(<?php echo $service['id']; ?>)">
                        <i class="fas fa-info-circle"></i>Details
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    $query_params = [];
                    if (!empty($category_filter)) $query_params['category'] = $category_filter;
                    if (!empty($search)) $query_params['search'] = $search;
                    if ($sort_by != 'name') $query_params['sort'] = $sort_by;
                    
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
            <div class="text-center text-muted mt-2" style="font-size: 14px;">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> services
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <!-- Sidebar -->
        <div class="services-sidebar">
            <!-- Popular Services -->
            <?php if (!empty($popular_services)): ?>
            <div class="sidebar-card">
                <h5 class="card-title"><i class="fas fa-fire me-2 text-warning"></i> Most Popular</h5>
                <div class="popular-list">
                    <?php $rank = 1; foreach ($popular_services as $popular): ?>
                    <div class="popular-item" onclick="bookService(<?php echo $popular['id']; ?>, '<?php echo htmlspecialchars($popular['name']); ?>', <?php echo $popular['price']; ?>, <?php echo $popular['duration']; ?>)">
                        <div class="popular-rank">#<?php echo $rank++; ?></div>
                        <div class="popular-info">
                            <strong><?php echo htmlspecialchars($popular['name']); ?></strong>
                            <div class="popular-meta">
                                <span><i class="fas fa-clock me-1"></i><?php echo $popular['duration']; ?> mins</span>
                                <span><i class="fas fa-rupee-sign me-1"></i><?php echo number_format($popular['price'], 2); ?></span>
                                <span><i class="fas fa-users me-1"></i><?php echo $popular['booking_count']; ?> bookings</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Price Range -->
            <div class="sidebar-card">
                <h5 class="card-title"><i class="fas fa-chart-line me-2 text-success"></i> Price Range</h5>
                <div class="price-range">
                    <div class="range-bar">
                        <div class="range-fill" style="width: 100%;"></div>
                    </div>
                    <div class="range-labels">
                        <span>Rs: <?php echo number_format($stats['min_price'] ?? 0, 0); ?></span>
                        <span>Rs: <?php echo number_format($stats['max_price'] ?? 0, 0); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Booking Tips -->
            <div class="sidebar-card">
                <h5 class="card-title"><i class="fas fa-lightbulb me-2 text-warning"></i> Booking Tips</h5>
                <ul class="tips-list">
                    <li><i class="fas fa-check-circle"></i> Book at least 24 hours in advance</li>
                    <li><i class="fas fa-check-circle"></i> Arrive 15 minutes early</li>
                    <li><i class="fas fa-check-circle"></i> Cancel 24 hours before to avoid fees</li>
                    <li><i class="fas fa-check-circle"></i> Bring reference photos for styling</li>
                    <li><i class="fas fa-check-circle"></i> Consult with our experts for recommendations</li>
                </ul>
            </div>
            
            <!-- Need Help -->
            <div class="sidebar-card help-card">
                <h5 class="card-title"><i class="fas fa-headset me-2 text-primary"></i> Need Help?</h5>
                <p class="small mb-3">Our team is here to assist you with your selection.</p>
                <a href="tel:0718059219" class="btn btn-outline-primary w-100">
                    <i class="fas fa-phone me-2"></i>Call Us Now
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Service Details Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="serviceModalTitle">Service Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="serviceModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="modalBookBtn">Book Now</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentService = null;

$(document).ready(function() {
    // Animate service cards on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });
    
    document.querySelectorAll('.service-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.5s ease';
        observer.observe(card);
    });
});

function bookService(serviceId, serviceName, price, duration) {
    Swal.fire({
        title: 'Book This Service?',
        html: `
            <div class="text-start">
                <div class="alert alert-light border">
                    <h6 class="mb-2">${serviceName}</h6>
                    <p class="mb-1"><i class="fas fa-clock me-2"></i>${duration} minutes</p>
                    <p class="mb-0"><i class="fas fa-rupee-sign me-2"></i>Rs: ${parseFloat(price).toFixed(2)}</p>
                </div>
                <p class="text-muted small mt-2">You'll be redirected to the booking page to select date and time.</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Continue to Booking',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#6f42c1'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'book-appointment.php?service=' + serviceId;
        }
    });
}

function viewDetails(serviceId) {
    // Show loading in modal
    $('#serviceModalBody').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `);
    $('#serviceModal').modal('show');
    
    $.ajax({
        url: 'ajax/get-service-details.php',
        method: 'POST',
        data: { id: serviceId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.service) {
                currentService = response.service;
                
                const html = `
                    <div class="service-details-modal">
                        <div class="service-category-badge">${escapeHtml(currentService.category || 'General')}</div>
                        <h5 class="service-name">${escapeHtml(currentService.name)}</h5>
                        <div class="service-meta-details">
                            <div class="meta-item">
                                <i class="fas fa-clock"></i>
                                <span>${currentService.duration} minutes</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-rupee-sign"></i>
                                <span class="price">Rs: ${parseFloat(currentService.price).toFixed(2)}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-tag"></i>
                                <span>${escapeHtml(currentService.category || 'General')}</span>
                            </div>
                        </div>
                        <div class="service-description-full">
                            <h6>Description</h6>
                            <p>${escapeHtml(currentService.description || 'No description available.')}</p>
                        </div>
                        <div class="service-benefits">
                            <h6>Why Choose This Service?</h6>
                            <ul>
                                <li><i class="fas fa-check-circle"></i> Professional service by expert stylists</li>
                                <li><i class="fas fa-check-circle"></i> Premium quality products used</li>
                                <li><i class="fas fa-check-circle"></i> Relaxing salon environment</li>
                                <li><i class="fas fa-check-circle"></i> Personalized consultation included</li>
                            </ul>
                        </div>
                    </div>
                `;
                
                $('#serviceModalBody').html(html);
                $('#serviceModalTitle').text(currentService.name);
                $('#modalBookBtn').off('click').on('click', function() {
                    $('#serviceModal').modal('hide');
                    bookService(currentService.id, currentService.name, currentService.price, currentService.duration);
                });
            } else {
                $('#serviceModalBody').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>${response.message || 'Could not load service details'}
                    </div>
                `);
            }
        },
        error: function() {
            $('#serviceModalBody').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>Failed to load service details. Please try again.
                </div>
            `);
        }
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
</script>
</body>
</html>
<?php ob_end_flush(); ?>
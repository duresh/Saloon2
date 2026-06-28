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
$records_per_page = 9;
$offset = ($page - 1) * $records_per_page;

// Filter variables
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';

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
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
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
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $records_per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
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
            MIN(price) as min_price,
            MAX(price) as max_price,
            AVG(price) as avg_price,
            MIN(duration) as min_duration,
            MAX(duration) as max_duration
        FROM services
        WHERE status = 'active'
    ";
    $stats_stmt = $pdo->query($stats_query);
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    error_log('Services page error: ' . $e->getMessage());
    $error = 'Unable to load services. Please try again later.';
}

include 'header/headerBooking.php';
?>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <div class="services-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-spa me-2"></i> Our Services</h1>
                    <p class="lead mb-0">Discover our range of professional salon services</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                    <a href="book-appointment.php" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-2"></i>Book Now
                    </a>
                </div>
            </div>
        </div>

        <!-- Service Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(111, 66, 193, 0.1); color: #6f42c1;">
                    <i class="fas fa-spa"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['total_services'] ?? 0); ?></div>
                    <div class="stat-label">Services Available</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo number_format($stats['total_categories'] ?? 0); ?></div>
                    <div class="stat-label">Categories</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value">Rs: <?php echo number_format($stats['avg_price'] ?? 0, 0); ?></div>
                    <div class="stat-label">Average Price</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['min_duration'] ?? 0; ?> - <?php echo $stats['max_duration'] ?? 0; ?></div>
                    <div class="stat-label">Duration (mins)</div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filter-card">
            <div class="filter-header">
                <h5><i class="fas fa-filter me-2"></i> Filter Services</h5>
            </div>
            <div class="filter-body">
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
        <div class="services-grid">
            <?php if (empty($services)): ?>
            <div class="empty-state">
                <i class="fas fa-spa fa-4x text-muted mb-3"></i>
                <h5>No Services Found</h5>
                <p class="text-muted">Try adjusting your filters or check back later.</p>
            </div>
            <?php else: ?>
                <?php foreach ($services as $service): ?>
                <div class="service-card" data-service-id="<?php echo $service['id']; ?>">
                    <div class="service-category-badge"><?php echo htmlspecialchars($service['category'] ?? 'General'); ?></div>
                    <h3 class="service-name"><?php echo htmlspecialchars($service['name']); ?></h3>
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
            <?php endif; ?>
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
            <div class="text-center text-muted mt-2">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> services
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Right Sidebar -->
    <div class="services-sidebar">
        <!-- Popular Services -->
        <div class="sidebar-card">
            <h5><i class="fas fa-fire me-2 text-warning"></i> Most Popular</h5>
            <div class="popular-list">
                <?php foreach ($popular_services as $popular): ?>
                <div class="popular-item" onclick="bookService(<?php echo $popular['id']; ?>, '<?php echo htmlspecialchars($popular['name']); ?>', <?php echo $popular['price']; ?>, <?php echo $popular['duration']; ?>)">
                    <div class="popular-rank">#<?php echo array_search($popular, $popular_services) + 1; ?></div>
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
        
        <!-- Price Range -->
        <div class="sidebar-card">
            <h5><i class="fas fa-chart-line me-2 text-success"></i> Price Range</h5>
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
            <h5><i class="fas fa-lightbulb me-2 text-warning"></i> Booking Tips</h5>
            <ul class="tips-list">
                <li><i class="fas fa-check-circle text-success"></i> Book at least 24 hours in advance</li>
                <li><i class="fas fa-check-circle text-success"></i> Arrive 15 minutes early</li>
                <li><i class="fas fa-check-circle text-success"></i> Cancel 24 hours before to avoid fees</li>
                <li><i class="fas fa-check-circle text-success"></i> Bring reference photos for styling</li>
                <li><i class="fas fa-check-circle text-success"></i> Consult with our experts for recommendations</li>
            </ul>
        </div>
        
        <!-- Need Help -->
        <div class="sidebar-card help-card">
            <h5><i class="fas fa-headset me-2 text-primary"></i> Need Help?</h5>
            <p class="small">Our team is here to assist you with your selection.</p>
            <a href="tel:0718059219" class="btn btn-outline-primary w-100">
                <i class="fas fa-phone me-2"></i>Call Us Now
            </a>
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
                Loading...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="modalBookBtn">Book Now</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

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
    // Store in session storage for booking page
    sessionStorage.setItem('selected_service_id', serviceId);
    sessionStorage.setItem('selected_service_name', serviceName);
    sessionStorage.setItem('selected_service_price', price);
    sessionStorage.setItem('selected_service_duration', duration);
    
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
    showLoading();
    
    $.ajax({
        url: 'ajax/get-service-details.php',
        method: 'POST',
        data: { id: serviceId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                currentService = response.service;
                
                const html = `
                    <div class="service-details-modal">
                        <div class="service-category-badge">${currentService.category || 'General'}</div>
                        <h3 class="service-name">${escapeHtml(currentService.name)}</h3>
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
                                <span>${currentService.category || 'General'}</span>
                            </div>
                        </div>
                        <div class="service-description-full">
                            <h6>Description</h6>
                            <p>${escapeHtml(currentService.description || 'No description available.')}</p>
                        </div>
                        <div class="service-benefits">
                            <h6>Benefits</h6>
                            <ul>
                                <li><i class="fas fa-check-circle text-success"></i> Professional service by expert stylists</li>
                                <li><i class="fas fa-check-circle text-success"></i> Premium quality products used</li>
                                <li><i class="fas fa-check-circle text-success"></i> Relaxing salon environment</li>
                                <li><i class="fas fa-check-circle text-success"></i> Personalized consultation included</li>
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
                
                $('#serviceModal').modal('show');
            } else {
                Swal.fire('Error', response.message || 'Could not load service details', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to load service details', 'error');
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
/* Services Page Styles */

.page-header {
grid-column: 1 / -1;
margin-bottom: 30px;
}
.services-container {
    max-width: 1400px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 25px;
}

/* Stats Grid */
.stats-grid {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
    grid-column: 1 / -1;
    background: white;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}

.filter-header {
    grid-column: 1 / -1;
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

/* Services Grid */
.services-grid {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 25px;
}

.service-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(111, 66, 193, 0.15);
}

.service-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #6f42c1, #9b6fe0);
}

.service-category-badge {
    display: inline-block;
    padding: 4px 12px;
    background: rgba(111, 66, 193, 0.1);
    color: #6f42c1;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 15px;
}

.service-name {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 12px;
    color: #333;
}

.service-description {
    color: #6c757d;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 20px;
}

.service-features {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.feature {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #6c757d;
}

.feature i {
    color: #6f42c1;
}

.feature .price {
    font-weight: 700;
    color: #6f42c1;
    font-size: 18px;
}

.service-actions {
    display: flex;
    gap: 12px;
}

.btn-book {
    flex: 1;
    padding: 10px;
    background: #6f42c1;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-book:hover {
    background: #5a32a0;
    transform: translateY(-2px);
}

.btn-details {
    padding: 10px 15px;
    background: #f8f9fa;
    color: #6f42c1;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-details:hover {
    background: #e9ecef;
}

/* Sidebar */
.services-sidebar {
    grid-column: 2;
}

.sidebar-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.sidebar-card h5 {
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
    color: #333;
    font-weight: 600;
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
    padding: 12px 0;
    border-bottom: 1px solid #e9ecef;
    cursor: pointer;
    transition: all 0.3s;
}

.popular-item:hover {
    background: #f8f9fa;
    transform: translateX(5px);
}

.popular-rank {
    width: 35px;
    height: 35px;
    background: linear-gradient(135deg, #6f42c1, #9b6fe0);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.popular-info {
    flex: 1;
}

.popular-info strong {
    display: block;
    margin-bottom: 5px;
}

.popular-meta {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #6c757d;
}

.popular-meta i {
    margin-right: 2px;
}

/* Price Range */
.price-range {
    margin-top: 15px;
}

.range-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}

.range-fill {
    height: 100%;
    background: linear-gradient(90deg, #6f42c1, #9b6fe0);
    border-radius: 4px;
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
    padding: 8px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #495057;
}

.tips-list li i {
    width: 18px;
}

/* Help Card */
.help-card {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

/* Service Details Modal */
.service-details-modal {
    text-align: left;
}

.service-meta-details {
    display: flex;
    gap: 20px;
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 12px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 500;
}

.meta-item .price {
    color: #6f42c1;
    font-size: 18px;
}

.service-description-full, .service-benefits {
    margin-top: 20px;
}

.service-description-full h6, .service-benefits h6 {
    color: #6f42c1;
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
}

/* Pagination */
.pagination-wrapper {
    grid-column: 1 / -1;
    margin-top: 30px;
    padding: 20px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
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
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 16px;
}

/* Responsive */
@media (max-width: 992px) {
    .services-container {
        grid-template-columns: 1fr;
    }
    
    .services-sidebar {
        grid-column: 1;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .services-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .service-meta-details {
        flex-direction: column;
        gap: 10px;
    }
    
    .service-actions {
        flex-direction: column;
    }
}
</style>
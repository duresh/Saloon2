<?php
ob_start();
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/dashboard.php');
    exit();
}

// Include database connection
require_once '../../includes/dbcon.php';
require_once '../../includes/helpers.php';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

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
    
    // Get unique categories for filter
    $cat_query = "SELECT DISTINCT category FROM services WHERE category IS NOT NULL AND category != '' ORDER BY category";
    $cat_stmt = $pdo->query($cat_query);
    $categories = $cat_stmt->fetchAll();
    
    // Build WHERE clause based on filters
    $where_clauses = [];
    $params = [];
    
    if (!empty($search)) {
        $where_clauses[] = "(name LIKE ? OR description LIKE ? OR category LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($category_filter)) {
        $where_clauses[] = "category = ?";
        $params[] = $category_filter;
    }
    
    if (!empty($status_filter)) {
        $where_clauses[] = "status = ?";
        $params[] = $status_filter;
    }
    
    $where_sql = empty($where_clauses) ? "" : "WHERE " . implode(" AND ", $where_clauses);
    
    // First, get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM services $where_sql";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Get services with pagination
    $services_query = "
        SELECT 
            id,
            name,
            description,
            price,
            duration,
            category,
            status
        FROM services
        $where_sql
        ORDER BY category ASC, name ASC
        LIMIT ? OFFSET ?
    ";
    
    $query_params = $params;
    $query_params[] = $records_per_page;
    $query_params[] = $offset;
    
    $stmt = $pdo->prepare($services_query);
    $stmt->execute($query_params);
    $services = $stmt->fetchAll();
    
    // Get booking statistics for each service
    foreach ($services as &$service) {
        // Get total bookings count
        $bookings_query = "SELECT COUNT(*) as count FROM appointments WHERE service_id = ?";
        $bookings_stmt = $pdo->prepare($bookings_query);
        $bookings_stmt->execute([$service['id']]);
        $service['total_bookings'] = $bookings_stmt->fetch()['count'];
        
        // Get completed bookings count
        $completed_query = "SELECT COUNT(*) as count FROM appointments WHERE service_id = ? AND status = 'completed'";
        $completed_stmt = $pdo->prepare($completed_query);
        $completed_stmt->execute([$service['id']]);
        $service['completed_bookings'] = $completed_stmt->fetch()['count'];
        
        // Get total revenue (join with services table to get price)
        $revenue_query = "
            SELECT COALESCE(SUM(s.price), 0) as total 
            FROM appointments a 
            JOIN services s ON a.service_id = s.id 
            WHERE a.service_id = ? AND a.status = 'completed'
        ";
        $revenue_stmt = $pdo->prepare($revenue_query);
        $revenue_stmt->execute([$service['id']]);
        $service['total_revenue'] = $revenue_stmt->fetch()['total'];
    }
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_services,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_services,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_services,
            COUNT(DISTINCT category) as total_categories,
            MIN(price) as min_price,
            MAX(price) as max_price,
            ROUND(AVG(price), 2) as avg_price,
            MIN(duration) as min_duration,
            MAX(duration) as max_duration,
            ROUND(AVG(duration), 0) as avg_duration
        FROM services
    ";
    $stats_stmt = $pdo->query($stats_query);
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    error_log('Manage Services error: ' . $e->getMessage());
    $error = "Unable to load services data. Please try again later.";
    $services = [];
    $stats = [
        'total_services' => 0,
        'active_services' => 0,
        'inactive_services' => 0,
        'total_categories' => 0,
        'min_price' => 0,
        'max_price' => 0,
        'avg_price' => 0,
        'min_duration' => 0,
        'max_duration' => 0,
        'avg_duration' => 0
    ];
}

include 'header/header-admin.php';
?>

<!-- Page Header -->
<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-spa me-2" style="color: #6f42c1;"></i> Manage Services</h1>
            <p class="lead mb-0">View, add, edit, and manage salon services</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary" onclick="addService()">
                <i class="fas fa-plus-circle me-2"></i>Add New Service
            </button>
            <button class="btn btn-success ms-2" onclick="refreshPage()">
                <i class="fas fa-sync-alt me-2"></i>Refresh
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(111, 66, 193, 0.1); color: #6f42c1;">
            <i class="fas fa-spa"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['total_services'] ?? 0); ?></div>
            <div class="stat-label">Total Services</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo number_format($stats['active_services'] ?? 0); ?></div>
            <div class="stat-label">Active Services</div>
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
        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
            <i class="fas fa-rupee-sign"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value">Rs: <?php echo number_format($stats['avg_price'] ?? 0, 0); ?></div>
            <div class="stat-label">Average Price</div>
        </div>
    </div>
</div>

<!-- Filters Section -->
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
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Service name, description..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Category</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-tag"></i></span>
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                    <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-2 mb-3">
                    <label class="form-label fw-bold">Status</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-circle"></i></span>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 me-2">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                    <a href="manage-services.php" class="btn btn-outline-secondary" title="Clear Filters">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Services Table -->
<div class="table-card">
    <div class="table-header">
        <h5><i class="fas fa-list me-2"></i> Services List</h5>
        <div class="table-actions">
            <span class="badge bg-info"><?php echo $total_records; ?> Services Found</span>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="services-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 25%;">Service Name</th>
                    <th style="width: 15%;">Category</th>
                    <th style="width: 10%;">Duration</th>
                    <th style="width: 10%;">Price</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 10%;">Bookings</th>
                    <th style="width: 15%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($services)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="empty-state">
                            <i class="fas fa-spa fa-4x text-muted mb-3"></i>
                            <h5>No Services Found</h5>
                            <p class="text-muted">Click "Add New Service" to create one.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php $sno = $offset + 1; ?>
                    <?php foreach ($services as $service): ?>
                    <tr class="service-row" data-service-id="<?php echo $service['id']; ?>">
                        <td class="text-center"><?php echo $sno++; ?></td>
                        <td>
                            <div class="service-info">
                                <div class="service-icon">
                                    <i class="fas fa-spa"></i>
                                </div>
                                <div class="service-details">
                                    <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars(substr($service['description'] ?? '', 0, 60)); ?><?php echo strlen($service['description'] ?? '') > 60 ? '...' : ''; ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="category-badge">
                                <i class="fas fa-tag me-1"></i>
                                <?php echo htmlspecialchars($service['category'] ?? 'General'); ?>
                            </span>
                        </td>
                        <td>
                            <span class="duration-badge">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo $service['duration']; ?> mins
                            </span>
                        </td>
                        <td>
                            <span class="price-badge">
                                Rs: <?php echo number_format($service['price'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <div class="status-toggle">
                                <div class="form-check form-switch">
                                    <input class="form-check-input status-switch" type="checkbox" 
                                           id="status_<?php echo $service['id']; ?>" 
                                           data-service="<?php echo $service['id']; ?>"
                                           <?php echo $service['status'] == 'active' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="status_<?php echo $service['id']; ?>">
                                        <span class="status-badge <?php echo $service['status']; ?>">
                                            <?php echo ucfirst($service['status']); ?>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="booking-stats">
                                <div class="stat-number"><?php echo $service['total_bookings'] ?? 0; ?></div>
                                <div class="stat-label">Total Bookings</div>
                                <?php if (($service['total_bookings'] ?? 0) > 0): ?>
                                <div class="stat-revenue mt-1">
                                    <small>Rs: <?php echo number_format($service['total_revenue'] ?? 0, 2); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon" onclick="viewService(<?php echo $service['id']; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon" onclick="editService(<?php echo $service['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon" onclick="viewServiceBookings(<?php echo $service['id']; ?>)" title="View Bookings">
                                    <i class="fas fa-calendar-alt"></i>
                                </button>
                                <button class="btn-icon text-danger" onclick="deleteService(<?php echo $service['id']; ?>)" title="Delete">
                                    <i class="fas fa-trash"></i>
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
                if (!empty($category_filter)) $query_params['category'] = $category_filter;
                if (!empty($status_filter)) $query_params['status'] = $status_filter;
                
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

<!-- Add/Edit Service Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="serviceModalTitle">Add New Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="serviceForm" onsubmit="saveService(event)">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Service Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Category</label>
                            <input type="text" class="form-control" name="category" placeholder="e.g., Hair, Nails, Facial">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Duration (minutes) *</label>
                            <input type="number" class="form-control" name="duration" min="15" step="15" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Price *</label>
                            <input type="number" class="form-control" name="price" min="0" step="100" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Describe the service..."></textarea>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Service Details Modal -->
<div class="modal fade" id="viewServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Service Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewServiceModalBody">
                Loading...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editFromView()">Edit Service</button>
            </div>
        </div>
    </div>
</div>

<!-- Bookings Modal -->
<div class="modal fade" id="bookingsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Service Bookings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bookingsModalBody">
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
// Service Management Functions
let currentServiceId = null;

function addService() {
    $('#serviceModalTitle').text('Add New Service');
    $('#serviceForm')[0].reset();
    new bootstrap.Modal(document.getElementById('serviceModal')).show();
}

function editService(serviceId) {
    showLoading();
    currentServiceId = serviceId;
    
    $.ajax({
        url: 'ajax/get-service-details.php',
        method: 'POST',
        data: { id: serviceId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                const s = response.service;
                $('#serviceModalTitle').text('Edit Service');
                $('#serviceModalBody').html(`
                    <form id="serviceForm" onsubmit="updateService(event, ${serviceId})">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Service Name *</label>
                                <input type="text" class="form-control" name="name" value="${escapeHtml(s.name)}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Category</label>
                                <input type="text" class="form-control" name="category" value="${escapeHtml(s.category || '')}">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Duration (minutes) *</label>
                                <input type="number" class="form-control" name="duration" value="${s.duration}" min="15" step="15" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Price *</label>
                                <input type="number" class="form-control" name="price" value="${s.price}" min="0" step="100" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea class="form-control" name="description" rows="3">${escapeHtml(s.description || '')}</textarea>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Service</button>
                        </div>
                    </form>
                `);
                
                new bootstrap.Modal(document.getElementById('serviceModal')).show();
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

function saveService(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    showLoading();
    
    $.ajax({
        url: 'ajax/save-service.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Service added successfully',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    $('#serviceModal').modal('hide');
                    location.reload();
                });
            } else {
                Swal.fire('Error', response.message || 'Failed to save service', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to save service', 'error');
        }
    });
}

function updateService(event, serviceId) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.append('service_id', serviceId);
    
    showLoading();
    
    $.ajax({
        url: 'ajax/update-service.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Service updated successfully',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    $('#serviceModal').modal('hide');
                    location.reload();
                });
            } else {
                Swal.fire('Error', response.message || 'Failed to update service', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to update service', 'error');
        }
    });
}

function viewService(serviceId) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-service-details.php',
        method: 'POST',
        data: { id: serviceId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                const s = response.service;
                const completion_rate = s.total_bookings > 0 
                    ? Math.round((s.completed_bookings / s.total_bookings) * 100) 
                    : 0;
                
                const html = `
                    <div class="service-details-view">
                        <div class="detail-section">
                            <h6>Service Information</h6>
                            <div class="detail-row">
                                <span class="label">Name:</span>
                                <span class="value">${escapeHtml(s.name)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Category:</span>
                                <span class="value">${escapeHtml(s.category || 'General')}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Duration:</span>
                                <span class="value">${s.duration} minutes</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Price:</span>
                                <span class="value price">Rs: ${parseFloat(s.price).toFixed(2)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Status:</span>
                                <span class="value"><span class="status-badge ${s.status}">${s.status}</span></span>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h6>Description</h6>
                            <div class="description">${escapeHtml(s.description || 'No description available.')}</div>
                        </div>
                        
                        <div class="detail-section">
                            <h6>Performance Statistics</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="stat-box">
                                        <div class="stat-number">${s.total_bookings || 0}</div>
                                        <div class="stat-text">Total Bookings</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-box">
                                        <div class="stat-number">${s.completed_bookings || 0}</div>
                                        <div class="stat-text">Completed</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-box">
                                        <div class="stat-number">${completion_rate}%</div>
                                        <div class="stat-text">Completion Rate</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <strong>Total Revenue:</strong> Rs: ${parseFloat(s.total_revenue || 0).toFixed(2)}
                            </div>
                        </div>
                    </div>
                `;
                
                $('#viewServiceModalBody').html(html);
                currentServiceId = serviceId;
                $('#viewServiceModal').modal('show');
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

function editFromView() {
    $('#viewServiceModal').modal('hide');
    editService(currentServiceId);
}

function viewServiceBookings(serviceId) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-service-bookings.php',
        method: 'POST',
        data: { service_id: serviceId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                const bookings = response.bookings || [];
                
                if (bookings.length === 0) {
                    $('#bookingsModalBody').html('<div class="text-center py-4 text-muted">No bookings for this service yet.</div>');
                } else {
                    let html = `
                        <div class="table-responsive">
                            <table class="bookings-table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Staff</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    bookings.forEach(booking => {
                        html += `
                            <tr>
                                <td><strong>${escapeHtml(booking.customer_name)}</strong><br><small>${escapeHtml(booking.customer_phone)}</small></td>
                                <td>${formatDate(booking.appointment_date)}</td>
                                <td>${formatTime(booking.appointment_time)}</td>
                                <td>${escapeHtml(booking.staff_name || 'Not Assigned')}</td>
                                <td><span class="status-badge ${booking.status}">${booking.status}</span></td>
                                <td>Rs: ${parseFloat(booking.price).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                    
                    $('#bookingsModalBody').html(html);
                }
                
                $('#bookingsModal').modal('show');
            } else {
                Swal.fire('Error', response.message || 'Could not load bookings', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to load bookings', 'error');
        }
    });
}

// Toggle service status
$(document).ready(function() {
    $('.status-switch').change(function() {
        const serviceId = $(this).data('service');
        const isActive = $(this).is(':checked');
        const status = isActive ? 'active' : 'inactive';
        
        Swal.fire({
            title: `Confirm ${status === 'active' ? 'Activate' : 'Deactivate'}`,
            text: `Are you sure you want to ${status} this service?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: isActive ? '#28a745' : '#dc3545',
            confirmButtonText: `Yes, ${status}`,
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: 'ajax/toggle-service-status.php',
                    method: 'POST',
                    data: { id: serviceId, status: status },
                    dataType: 'json'
                }).then(response => {
                    if (!response.success) {
                        throw new Error(response.message);
                    }
                    return response;
                }).catch(error => {
                    Swal.showValidationMessage(error.message);
                });
            }
        }).then((result) => {
            if (result.isConfirmed && result.value.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Updated!',
                    text: `Service ${status}ed successfully.`,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else if (result.dismiss) {
                $(this).prop('checked', !isActive);
            }
        }).catch(() => {
            $(this).prop('checked', !isActive);
        });
    });
});

function deleteService(serviceId) {
    Swal.fire({
        title: 'Delete Service?',
        text: "This action cannot be undone! All related bookings will be affected.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: 'ajax/delete-service.php',
                method: 'POST',
                data: { id: serviceId },
                dataType: 'json'
            }).then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Failed to delete service');
                }
                return response;
            }).catch(error => {
                Swal.showValidationMessage(error.message);
            });
        }
    }).then((result) => {
        if (result.isConfirmed && result.value.success) {
            Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: 'Service has been deleted.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    });
}

function refreshPage() {
    location.reload();
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatTime(timeStr) {
    const time = new Date('2000-01-01 ' + timeStr);
    return time.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
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
/* Services Table Styles */
.services-table {
    width: 100%;
    border-collapse: collapse;
}

.services-table th {
    background: #f8f9fa;
    padding: 15px;
    font-weight: 600;
    color: #333;
    font-size: 13px;
    text-align: left;
    border-bottom: 2px solid #e9ecef;
}

.services-table td {
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

.services-table tbody tr:hover {
    background: #f8f9fa;
}

/* Service Info */
.service-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.service-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6f42c1, #9b6fe0);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.service-details {
    line-height: 1.4;
}

.service-details strong {
    font-size: 14px;
    color: #333;
}

.service-details small {
    font-size: 12px;
    color: #6c757d;
}

/* Badges */
.category-badge {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(111, 66, 193, 0.1);
    color: #6f42c1;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.duration-badge {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(23, 162, 184, 0.1);
    color: #17a2b8;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.price-badge {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.active {
    background: #d4edda;
    color: #155724;
}

.status-badge.inactive {
    background: #f8d7da;
    color: #721c24;
}

/* Booking Stats */
.booking-stats {
    text-align: center;
}

.stat-number {
    font-size: 18px;
    font-weight: 700;
    color: #6f42c1;
}

.stat-label {
    font-size: 11px;
    color: #6c757d;
}

.stat-revenue {
    font-size: 11px;
    color: #28a745;
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
    margin-top: 5px;
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

/* Dashboard Header */
.dashboard-header {
    background: white;
    border-radius: 16px;
    padding: 25px 30px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border-left: 5px solid #6f42c1;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .action-buttons {
        min-width: 140px;
    }
    
    .service-info {
        min-width: 200px;
    }
    
    .dashboard-header {
        padding: 20px;
    }
    
    .dashboard-header h1 {
        font-size: 24px;
    }
    
    .table-header {
        padding: 15px;
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
}
</style>
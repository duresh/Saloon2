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
// Include helper functions
require_once '../../includes/helpers.php';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Search and filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$specialization_filter = isset($_GET['specialization']) ? $_GET['specialization'] : '';
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
    
    // Get unique specializations for filter
    $spec_query = "SELECT DISTINCT specialization FROM staff WHERE specialization IS NOT NULL AND specialization != '' ORDER BY specialization";
    $spec_stmt = $pdo->query($spec_query);
    $specializations = $spec_stmt->fetchAll();
    
    // Build count query
    $count_query = "
        SELECT COUNT(*) as total 
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE r.cStatus = 1
    ";
    
    $count_params = [];
    
    if (!empty($search)) {
        $count_query .= " AND (r.fName LIKE ? OR r.email LIKE ? OR s.specialization LIKE ?)";
        $search_param = "%$search%";
        $count_params = array_merge($count_params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($specialization_filter)) {
        $count_query .= " AND s.specialization = ?";
        $count_params[] = $specialization_filter;
    }
    
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Build main query
    $query = "
        SELECT 
            s.*,
            r.fName as staff_name,
            r.email,
            r.contactNo,
            r.regDate,
            (
                SELECT COUNT(*) 
                FROM appointments a 
                WHERE a.staff_id = s.id 
                AND a.status = 'completed'
            ) as completed_appointments,
            (
                SELECT COUNT(*) 
                FROM appointments a 
                WHERE a.staff_id = s.id
            ) as total_appointments,
            (
                SELECT COUNT(*) 
                FROM staff_leave sl 
                WHERE sl.staff_id = s.id 
                AND sl.leave_date >= CURDATE() 
                AND sl.status = 'approved'
            ) as upcoming_leaves
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE r.cStatus = 1
    ";
    
    $query_params = [];
    
    if (!empty($search)) {
        $query .= " AND (r.fName LIKE ? OR r.email LIKE ? OR s.specialization LIKE ?)";
        $search_param = "%$search%";
        $query_params = array_merge($query_params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($specialization_filter)) {
        $query .= " AND s.specialization = ?";
        $query_params[] = $specialization_filter;
    }
    
    $query .= " ORDER BY s.joining_date DESC, r.fName ASC LIMIT ? OFFSET ?";
    $query_params[] = $records_per_page;
    $query_params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($query_params);
    $staff_members = $stmt->fetchAll();
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_staff,
            COUNT(DISTINCT specialization) as total_specializations,
            SUM(CASE WHEN s.experience_years >= 5 THEN 1 ELSE 0 END) as senior_staff,
            AVG(s.experience_years) as avg_experience
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE r.cStatus = 1
    ";
    $stats_stmt = $pdo->query($stats_query);
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    error_log('Manage Staff error: ' . $e->getMessage());
    $error = "Unable to load staff data. Please try again later.";
}

include 'header/header-admin.php';
?>

<!-- Page Header -->
<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-users-cog me-2" style="color: #6f42c1;"></i> Manage Staff</h1>
            <p class="lead mb-0">View and manage all staff members</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary" onclick="addStaff()">
                <i class="fas fa-plus-circle me-2"></i>Add New Staff
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
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $stats['total_staff'] ?? 0; ?></div>
            <div class="stat-label">Total Staff</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
            <i class="fas fa-tag"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $stats['total_specializations'] ?? 0; ?></div>
            <div class="stat-label">Specializations</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $stats['senior_staff'] ?? 0; ?></div>
            <div class="stat-label">Senior Staff (5+ yrs)</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo round($stats['avg_experience'] ?? 0, 1); ?> yrs</div>
            <div class="stat-label">Avg Experience</div>
        </div>
    </div>
</div>

<!-- Filters Section -->
<div class="filter-card">
    <div class="filter-header">
        <h5><i class="fas fa-filter me-2"></i> Filter Staff</h5>
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
                    <label class="form-label fw-bold">Specialization</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-tag"></i></span>
                        <select class="form-select" name="specialization">
                            <option value="">All Specializations</option>
                            <?php foreach ($specializations as $spec): ?>
                            <option value="<?php echo htmlspecialchars($spec['specialization']); ?>" 
                                    <?php echo $specialization_filter == $spec['specialization'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec['specialization']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 me-2">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                    <a href="manage-staff.php" class="btn btn-outline-secondary" title="Clear Filters">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Staff Table -->
<div class="table-card">
    <div class="table-header">
        <h5><i class="fas fa-list me-2"></i> Staff Members</h5>
        <div class="table-actions">
            <span class="badge bg-info"><?php echo $total_records; ?> Records</span>
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
                    <th>Contact</th>
                    <th>Performance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staff_members)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="empty-state">
                            <i class="fas fa-users-slash fa-4x text-muted mb-3"></i>
                            <h5>No Staff Members Found</h5>
                            <p class="text-muted">Click "Add New Staff" to create one.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php $sno = $offset + 1; ?>
                    <?php foreach ($staff_members as $staff): 
                        $completion_rate = $staff['total_appointments'] > 0 
                            ? round(($staff['completed_appointments'] / $staff['total_appointments']) * 100) 
                            : 0;
                    ?>
                    <tr>
                        <td><span class="fw-bold"><?php echo $sno++; ?></span></td>
                        <td>
                            <div class="staff-info">
                                <div class="staff-avatar-sm">
                                    <?php 
                                    $initial = strtoupper(substr($staff['staff_name'], 0, 1));
                                    echo $initial;
                                    ?>
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
                            <small><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($staff['contactNo']); ?></small>
                            <br>
                            <small><i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></small>
                        </td>
                        <td>
                            <div class="performance-indicator">
                                <div class="progress" style="height: 5px; width: 100px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $completion_rate; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $staff['completed_appointments'] ?? 0; ?>/<?php echo $staff['total_appointments'] ?? 0; ?> completed</small>
                            </div>
                        </td>
                        <td>
                            <?php if ($staff['upcoming_leaves'] > 0): ?>
                            <span class="badge bg-warning">On Leave Soon</span>
                            <?php else: ?>
                            <span class="badge bg-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon" onclick="viewStaff(<?php echo $staff['id']; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon" onclick="editStaff(<?php echo $staff['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon" onclick="manageAvailability(<?php echo $staff['id']; ?>)" title="Availability">
                                    <i class="fas fa-clock"></i>
                                </button>
                                <button class="btn-icon" onclick="viewSchedule(<?php echo $staff['id']; ?>)" title="Schedule">
                                    <i class="fas fa-calendar-alt"></i>
                                </button>
                                <button class="btn-icon text-danger" onclick="deleteStaff(<?php echo $staff['id']; ?>)" title="Delete">
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
                if (!empty($specialization_filter)) $query_params['specialization'] = $specialization_filter;
                
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
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> staff members
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Staff Modal -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffModalTitle">Add New Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="staffModalBody">
                <!-- Form will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Availability Modal -->
<div class="modal fade" id="availabilityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Availability</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="availabilityModalBody">
                <!-- Availability form will be loaded here -->
            </div>
        </div>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

<script>
// Staff Management Functions
function addStaff() {
    $('#staffModalTitle').text('Add New Staff');
    $('#staffModalBody').html(`
        <form id="staffForm" onsubmit="saveStaff(event)">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                New staff will receive a default password: <strong>staff@123</strong>
                <br><small>They will be required to change it on first login.</small>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone (Primary) *</label>
                    <input type="text" class="form-control" name="phone_primary" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone (Secondary)</label>
                    <input type="text" class="form-control" name="phone_secondary">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Specialization *</label>
                    <input type="text" class="form-control" name="specialization" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Experience (Years) *</label>
                    <input type="number" class="form-control" name="experience" min="0" step="0.5" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Joining Date</label>
                    <input type="date" class="form-control" name="joining_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Qualification</label>
                    <input type="text" class="form-control" name="qualification" placeholder="e.g., Diploma, Certificate">
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea class="form-control" name="address" rows="2"></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Bio / Description</label>
                <textarea class="form-control" name="bio" rows="3"></textarea>
            </div>
            
            <div class="text-end">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Staff</button>
            </div>
        </form>
    `);
    
    new bootstrap.Modal(document.getElementById('staffModal')).show();
}

function editStaff(staffId) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-staff-details.php',
        method: 'POST',
        data: { id: staffId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                const s = response.staff;
                $('#staffModalTitle').text('Edit Staff');
                $('#staffModalBody').html(`
                    <form id="staffForm" onsubmit="updateStaff(event, ${staffId})">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="name" value="${escapeHtml(s.staff_name)}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" value="${escapeHtml(s.email)}" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone (Primary) *</label>
                                <input type="text" class="form-control" name="phone_primary" value="${escapeHtml(s.contactNo)}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone (Secondary)</label>
                                <input type="text" class="form-control" name="phone_secondary" value="${escapeHtml(s.phone || '')}">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Specialization *</label>
                                <input type="text" class="form-control" name="specialization" value="${escapeHtml(s.specialization || '')}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Experience (Years) *</label>
                                <input type="number" class="form-control" name="experience" value="${s.experience_years || 0}" min="0" step="0.5" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Joining Date</label>
                                <input type="date" class="form-control" name="joining_date" value="${s.joining_date || ''}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Qualification</label>
                                <input type="text" class="form-control" name="qualification" value="${escapeHtml(s.qualification || '')}">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2">${escapeHtml(s.address || '')}</textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Bio / Description</label>
                            <textarea class="form-control" name="bio" rows="3">${escapeHtml(s.bio || '')}</textarea>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Staff</button>
                        </div>
                    </form>
                `);
                
                new bootstrap.Modal(document.getElementById('staffModal')).show();
            } else {
                Swal.fire('Error', response.message || 'Could not load staff details', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to load staff details', 'error');
        }
    });
}

function saveStaff(event) {
    event.preventDefault();
    const form = $(event.target);
    const formData = new FormData(event.target);
    
    showLoading();
    
    $.ajax({
        url: 'ajax/save-staff.php',
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
                    title: 'Staff Added Successfully',
                    html: `
                        Staff member has been added.<br>
                        <strong>Default Password: ${response.default_password}</strong><br>
                        <small>Please share this password with the staff member. They will be required to change it on first login.</small>
                    `,
                    confirmButtonColor: '#6f42c1',
                    confirmButtonText: 'OK'
                }).then(() => {
                    $('#staffModal').modal('hide');
                    location.reload();
                });
            } else {
                Swal.fire('Error', response.message || 'Failed to save staff', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to save staff', 'error');
        }
    });
}

function updateStaff(event, staffId) {
    event.preventDefault();
    const form = $(event.target);
    const formData = new FormData(event.target);
    formData.append('staff_id', staffId);
    
    showLoading();
    
    $.ajax({
        url: 'ajax/update-staff.php',
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
                    text: 'Staff member updated successfully',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    $('#staffModal').modal('hide');
                    location.reload();
                });
            } else {
                Swal.fire('Error', response.message || 'Failed to update staff', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to update staff', 'error');
        }
    });
}

function viewStaff(staffId) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-staff-details.php',
        method: 'POST',
        data: { id: staffId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                const s = response.staff;
                const completion_rate = s.total_appointments > 0 
                    ? Math.round((s.completed_appointments / s.total_appointments) * 100) 
                    : 0;
                
                Swal.fire({
                    title: s.staff_name,
                    html: `
                        <div class="staff-details-view">
                            <div class="detail-row">
                                <span class="label">Email:</span>
                                <span class="value">${escapeHtml(s.email)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Phone:</span>
                                <span class="value">${escapeHtml(s.contactNo)} ${s.phone ? ' / ' + escapeHtml(s.phone) : ''}</span>
                            </div>
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
                                <span class="value">${s.joining_date ? formatDate(s.joining_date) : 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Performance:</span>
                                <span class="value">${s.completed_appointments || 0}/${s.total_appointments || 0} (${completion_rate}%)</span>
                            </div>
                            ${s.bio ? `
                            <div class="detail-row">
                                <span class="label">Bio:</span>
                                <span class="value">${escapeHtml(s.bio)}</span>
                            </div>
                            ` : ''}
                            ${s.address ? `
                            <div class="detail-row">
                                <span class="label">Address:</span>
                                <span class="value">${escapeHtml(s.address)}</span>
                            </div>
                            ` : ''}
                        </div>
                    `,
                    showCloseButton: true,
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#6f42c1',
                    width: '600px'
                });
            } else {
                Swal.fire('Error', response.message || 'Could not load staff details', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to load staff details', 'error');
        }
    });
}

function manageAvailability(staffId) {
    showLoading();
    
    $.ajax({
        url: 'ajax/get-staff-availability.php',
        method: 'POST',
        data: { staff_id: staffId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                let daysHtml = '';
                const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                
                days.forEach((day, index) => {
                    const availability = response.availability[index] || { start: '09:00', end: '18:00', available: true };
                    daysHtml += `
                        <div class="availability-day mb-3">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <strong>${day}</strong>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="day_${index}" 
                                               ${availability.available ? 'checked' : ''} 
                                               onchange="toggleDay(${index})">
                                        <label class="form-check-label" for="day_${index}">Working</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <input type="time" class="form-control form-control-sm" id="start_${index}" 
                                           value="${availability.start}" ${!availability.available ? 'disabled' : ''}>
                                </div>
                                <div class="col-md-1 text-center">to</div>
                                <div class="col-md-3">
                                    <input type="time" class="form-control form-control-sm" id="end_${index}" 
                                           value="${availability.end}" ${!availability.available ? 'disabled' : ''}>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                $('#availabilityModalBody').html(`
                    <form id="availabilityForm" onsubmit="saveAvailability(event, ${staffId})">
                        ${daysHtml}
                        <hr>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Availability</button>
                        </div>
                    </form>
                `);
                
                new bootstrap.Modal(document.getElementById('availabilityModal')).show();
            } else {
                Swal.fire('Error', response.message || 'Could not load availability', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to load availability', 'error');
        }
    });
}

function toggleDay(index) {
    const checked = $(`#day_${index}`).is(':checked');
    $(`#start_${index}`).prop('disabled', !checked);
    $(`#end_${index}`).prop('disabled', !checked);
}

function saveAvailability(event, staffId) {
    event.preventDefault();
    
    const availability = [];
    for (let i = 0; i < 7; i++) {
        availability.push({
            day: i,
            available: $(`#day_${i}`).is(':checked'),
            start: $(`#start_${i}`).val(),
            end: $(`#end_${i}`).val()
        });
    }
    
    showLoading();
    
    $.ajax({
        url: 'ajax/save-staff-availability.php',
        method: 'POST',
        data: {
            staff_id: staffId,
            availability: JSON.stringify(availability)
        },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Availability updated successfully',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    $('#availabilityModal').modal('hide');
                });
            } else {
                Swal.fire('Error', response.message || 'Failed to save availability', 'error');
            }
        },
        error: function() {
            hideLoading();
            Swal.fire('Error', 'Failed to save availability', 'error');
        }
    });
}

function viewSchedule(staffId) {
    window.location.href = `staff-schedule.php?staff_id=${staffId}`;
}

function deleteStaff(staffId) {
    Swal.fire({
        title: 'Delete Staff Member?',
        text: "This action cannot be undone! All related data will be affected.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: 'ajax/delete-staff.php',
                method: 'POST',
                data: { id: staffId },
                dataType: 'json'
            }).then(response => {
                if (!response.success) {
                    throw new Error(response.message || 'Failed to delete staff');
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
                text: 'Staff member has been deleted.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        }
    });
}

// Utility Functions
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
/* Staff Table Styles */
.staff-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
}

.staff-table th {
    background: var(--light);
    padding: 15px;
    font-weight: 600;
    color: var(--dark);
    font-size: 13px;
    text-align: left;
    border-bottom: 2px solid var(--border);
}

.staff-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.staff-table tbody tr:hover {
    background: var(--light);
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

/* Performance Indicator */
.performance-indicator {
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
    background: var(--light);
    color: var(--gray);
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

/* Staff Details View */
.staff-details-view {
    text-align: left;
    padding: 10px;
}

.detail-row {
    display: flex;
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row .label {
    width: 120px;
    font-weight: 600;
    color: var(--gray);
}

.detail-row .value {
    flex: 1;
    color: var(--dark);
}

/* Availability Form */
.availability-day {
    padding: 10px;
    background: var(--light);
    border-radius: 8px;
    margin-bottom: 10px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    border: 1px solid var(--border);
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
    color: var(--dark);
    line-height: 1.2;
}

.stat-label {
    font-size: 13px;
    color: var(--gray);
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-buttons {
        min-width: 160px;
    }
}
</style>
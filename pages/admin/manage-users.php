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
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

// Filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
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
    
    // Build count query
    $count_query = "SELECT COUNT(*) as total FROM reg WHERE regID != ?";
    $count_params = [$admin_id];
    
    if (!empty($search)) {
        $count_query .= " AND (fName LIKE ? OR email LIKE ? OR contactNo LIKE ?)";
        $search_param = "%$search%";
        $count_params = array_merge($count_params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($role_filter)) {
        $count_query .= " AND role = ?";
        $count_params[] = $role_filter;
    }
    
    if ($status_filter !== '') {
        $count_query .= " AND cStatus = ?";
        $count_params[] = $status_filter;
    }
    
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Build main query - FIXED: Removed non-existent columns
    $query = "
        SELECT 
            r.*,
            s.id as staff_id,
            s.specialization,
            s.experience_years,
            s.joining_date,
            s.phone as staff_phone,
            (SELECT COUNT(*) FROM appointments WHERE user_id = r.regID) as total_appointments,
            (SELECT COUNT(*) FROM appointments WHERE user_id = r.regID AND status = 'completed') as completed_appointments,
            (SELECT COUNT(*) FROM appointments WHERE user_id = r.regID AND status = 'cancelled') as cancelled_appointments,
            (SELECT COUNT(*) FROM appointments WHERE user_id = r.regID AND appointment_date >= CURDATE() AND status IN ('pending', 'confirmed')) as upcoming_appointments
        FROM reg r
        LEFT JOIN staff s ON r.regID = s.user_id
        WHERE r.regID != ?
    ";
    
    $query_params = [$admin_id];
    
    if (!empty($search)) {
        $query .= " AND (r.fName LIKE ? OR r.email LIKE ? OR r.contactNo LIKE ?)";
        $search_param = "%$search%";
        $query_params = array_merge($query_params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($role_filter)) {
        $query .= " AND r.role = ?";
        $query_params[] = $role_filter;
    }
    
    if ($status_filter !== '') {
        $query .= " AND r.cStatus = ?";
        $query_params[] = $status_filter;
    }
    
    $query .= " ORDER BY r.regDate DESC LIMIT ? OFFSET ?";
    $query_params[] = $records_per_page;
    $query_params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($query_params);
    $users = $stmt->fetchAll();
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as total_customers,
            SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as total_staff,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
            SUM(CASE WHEN cStatus = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN cStatus = 0 THEN 1 ELSE 0 END) as inactive_users,
            SUM(CASE WHEN MONTH(regDate) = MONTH(CURDATE()) AND YEAR(regDate) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as new_this_month
        FROM reg
        WHERE regID != ?
    ";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$admin_id]);
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    error_log('Manage Users error: ' . $e->getMessage());
    $error = "Unable to load users data. Please try again later.";
}

include 'header/header-admin.php';
?>

<!-- Page Header -->
<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-users me-2" style="color: #6f42c1;"></i> Manage Users</h1>
            <p class="lead mb-0">Manage user accounts, roles, and permissions</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-success" onclick="refreshPage()">
                <i class="fas fa-sync-alt me-2"></i>Refresh
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card" onclick="filterByRole('all')">
        <div class="stat-icon" style="background: rgba(111, 66, 193, 0.1); color: #6f42c1;">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $stats['total_users'] ?? 0; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    
    <div class="stat-card" onclick="filterByRole('user')">
        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
            <i class="fas fa-user"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $stats['total_customers'] ?? 0; ?></div>
            <div class="stat-label">Customers</div>
        </div>
    </div>
    
    <div class="stat-card" onclick="filterByRole('staff')">
        <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $stats['total_staff'] ?? 0; ?></div>
            <div class="stat-label">Staff Members</div>
        </div>
    </div>
    
    <div class="stat-card" onclick="filterByRole('admin')">
        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
            <i class="fas fa-user-shield"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $stats['total_admins'] ?? 0; ?></div>
            <div class="stat-label">Administrators</div>
        </div>
    </div>
    
    <div class="stat-card" onclick="filterByStatus(1)">
        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $stats['active_users'] ?? 0; ?></div>
            <div class="stat-label">Active</div>
        </div>
    </div>
    
    <div class="stat-card" onclick="filterByStatus(0)">
        <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
            <i class="fas fa-ban"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value"><?php echo $stats['inactive_users'] ?? 0; ?></div>
            <div class="stat-label">Inactive</div>
        </div>
    </div>
</div>

<!-- Filters Section -->
<div class="filter-card">
    <div class="filter-header">
        <h5><i class="fas fa-filter me-2"></i> Filter Users</h5>
    </div>
    <div class="filter-body">
        <form method="GET" action="" id="filterForm">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Name, email, phone..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Role</label>
                    <select class="form-select" name="role">
                        <option value="">All Roles</option>
                        <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>Customer</option>
                        <option value="staff" <?php echo $role_filter == 'staff' ? 'selected' : ''; ?>>Staff</option>
                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="1" <?php echo $status_filter == '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $status_filter == '0' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 me-2">
                        <i class="fas fa-filter me-2"></i>Apply
                    </button>
                    <a href="manage-users.php" class="btn btn-outline-secondary" title="Clear Filters">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="table-card">
    <div class="table-header">
        <h5><i class="fas fa-list me-2"></i> User List</h5>
        <div class="table-actions">
            <span class="badge bg-info"><?php echo $total_records; ?> Records</span>
            <button class="btn btn-sm btn-outline-primary ms-2" onclick="exportUsers()">
                <i class="fas fa-download me-1"></i>Export
            </button>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Contact</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Stats</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="empty-state">
                            <i class="fas fa-users-slash fa-4x text-muted mb-3"></i>
                            <h5>No Users Found</h5>
                            <p class="text-muted">Try adjusting your filters or add new users.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): 
                        $completion_rate = $user['total_appointments'] > 0 
                            ? round(($user['completed_appointments'] / $user['total_appointments']) * 100) 
                            : 0;
                    ?>
                    <tr>
                        <td>#<?php echo $user['regID']; ?></td>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar-sm">
                                    <?php echo strtoupper(substr($user['fName'], 0, 1)); ?>
                                </div>
                                <div class="user-details">
                                    <strong><?php echo htmlspecialchars($user['fName'] . ' ' . $user['lName']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($user['contactNo']); ?></div>
                            <?php if ($user['staff_phone']): ?>
                            <div class="text-muted small"><i class="fas fa-phone-alt me-1"></i> Staff: <?php echo htmlspecialchars($user['staff_phone']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <i class="fas <?php echo $user['role'] == 'admin' ? 'fa-user-shield' : ($user['role'] == 'staff' ? 'fa-user-tie' : 'fa-user'); ?> me-1"></i>
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                            <?php if ($user['specialization']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($user['specialization']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="status-toggle">
                                <div class="form-check form-switch">
                                    <input class="form-check-input status-switch" type="checkbox" 
                                           id="status_<?php echo $user['regID']; ?>" 
                                           data-user="<?php echo $user['regID']; ?>"
                                           <?php echo $user['cStatus'] == 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="status_<?php echo $user['regID']; ?>">
                                        <span class="status-badge <?php echo $user['cStatus'] == 1 ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['cStatus'] == 1 ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="stats-info">
                                <div><i class="fas fa-calendar-check me-1"></i> <?php echo $user['total_appointments']; ?> appointments</div>
                                <?php if ($user['role'] == 'staff' && $user['total_appointments'] > 0): ?>
                                <div><i class="fas fa-chart-line me-1"></i> <?php echo $completion_rate; ?>% completion</div>
                                <?php endif; ?>
                                <?php if ($user['upcoming_appointments'] > 0): ?>
                                <div class="text-warning"><i class="fas fa-clock me-1"></i> <?php echo $user['upcoming_appointments']; ?> upcoming</div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div><i class="fas fa-calendar-alt me-1"></i> <?php echo formatDate($user['regDate']); ?></div>
                            <small class="text-muted"><?php echo timeAgo($user['regDate']); ?></small>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon" onclick="viewUser(<?php echo $user['regID']; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon" onclick="changeUserRole(<?php echo $user['regID']; ?>, '<?php echo $user['role']; ?>')" title="Change Role">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                                <?php if ($user['role'] == 'user'): ?>
                                <button class="btn-icon success" onclick="enrollAsStaff(<?php echo $user['regID']; ?>)" title="Enroll as Staff">
                                    <i class="fas fa-user-plus"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($user['role'] == 'staff'): ?>
                                <button class="btn-icon warning" onclick="removeStaffRole(<?php echo $user['regID']; ?>)" title="Remove Staff Role">
                                    <i class="fas fa-user-minus"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn-icon" onclick="resetUserPassword(<?php echo $user['regID']; ?>)" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <button class="btn-icon text-danger" onclick="deleteUser(<?php echo $user['regID']; ?>)" title="Delete User">
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
                if (!empty($role_filter)) $query_params['role'] = $role_filter;
                if ($status_filter !== '') $query_params['status'] = $status_filter;
                
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
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> users
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- User Details Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

<script>
// ============================================
// USER MANAGEMENT FUNCTIONS
// ============================================

$(document).ready(function() {
    // Initialize tooltips
    $('[title]').tooltip();
    
    // Status toggle switch handler
    $('.status-switch').change(function() {
        const userId = $(this).data('user');
        const isActive = $(this).is(':checked');
        toggleUserStatus(userId, isActive);
    });
});

function filterByRole(role) {
    if (role === 'all') {
        window.location.href = 'manage-users.php';
    } else {
        window.location.href = `manage-users.php?role=${role}`;
    }
}

function filterByStatus(status) {
    window.location.href = `manage-users.php?status=${status}`;
}

// Toggle user status
function toggleUserStatus(userId, isActive) {
    const action = isActive ? 'activate' : 'deactivate';
    const actionText = isActive ? 'activate' : 'deactivate';
    
    Swal.fire({
        title: `Confirm ${actionText}`,
        text: `Are you sure you want to ${actionText} this user account?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: isActive ? '#28a745' : '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, ${actionText}`,
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                url: 'ajax/toggle-user-status.php',
                method: 'POST',
                data: { user_id: userId, status: action },
                dataType: 'json'
            }).then(response => {
                if (!response.success) {
                    throw new Error(response.message);
                }
                return response;
            }).catch(error => {
                Swal.showValidationMessage(error.message);
                throw error;
            });
        }
    }).then((result) => {
        if (result.isConfirmed && result.value.success) {
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: `User account ${actionText}d successfully.`,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else if (result.dismiss) {
            // Revert the switch if cancelled
            $(`.status-switch[data-user="${userId}"]`).prop('checked', !isActive);
        }
    }).catch(() => {
        $(`.status-switch[data-user="${userId}"]`).prop('checked', !isActive);
    });
}

// View user details
function viewUser(userId) {
    Swal.fire({
        title: 'Loading...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: 'ajax/get-user-details.php',
        method: 'POST',
        data: { id: userId },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                const u = response.user;
                const completion_rate = u.total_appointments > 0 
                    ? Math.round((u.completed_appointments / u.total_appointments) * 100) 
                    : 0;
                
                const html = `
                    <div class="user-details-view">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-section">
                                    <h6><i class="fas fa-user-circle me-2"></i>Personal Information</h6>
                                    <div class="detail-row">
                                        <span class="label">Full Name:</span>
                                        <span class="value"><strong>${escapeHtml(u.fName)} ${escapeHtml(u.lName || '')}</strong></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">Email:</span>
                                        <span class="value">${escapeHtml(u.email)}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">Phone:</span>
                                        <span class="value">${escapeHtml(u.contactNo)}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">Role:</span>
                                        <span class="value"><span class="role-badge role-${u.role}">${u.role.toUpperCase()}</span></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">Status:</span>
                                        <span class="value"><span class="status-badge ${u.cStatus == 1 ? 'active' : 'inactive'}">${u.cStatus == 1 ? 'Active' : 'Inactive'}</span></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-section">
                                    <h6><i class="fas fa-chart-line me-2"></i>Appointment Statistics</h6>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="stat-box">
                                                <div class="stat-number">${u.total_appointments || 0}</div>
                                                <div class="stat-text">Total Appointments</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="stat-box">
                                                <div class="stat-number">${u.completed_appointments || 0}</div>
                                                <div class="stat-text">Completed</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="stat-box">
                                                <div class="stat-number">${u.upcoming_appointments || 0}</div>
                                                <div class="stat-text">Upcoming</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="stat-box">
                                                <div class="stat-number">${u.cancelled_appointments || 0}</div>
                                                <div class="stat-text">Cancelled</div>
                                            </div>
                                        </div>
                                    </div>
                                    ${u.role == 'staff' && u.total_appointments > 0 ? `
                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small>Completion Rate</small>
                                            <small><strong>${completion_rate}%</strong></small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: ${completion_rate}%"></div>
                                        </div>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                        
                        ${u.specialization ? `
                        <div class="detail-section">
                            <h6><i class="fas fa-briefcase me-2"></i>Staff Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="detail-row">
                                        <span class="label">Specialization:</span>
                                        <span class="value">${escapeHtml(u.specialization)}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">Experience:</span>
                                        <span class="value">${u.experience_years || 0} years</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-row">
                                        <span class="label">Joining Date:</span>
                                        <span class="value">${u.joining_date ? formatDate(u.joining_date) : 'N/A'}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="label">Staff Phone:</span>
                                        <span class="value">${u.staff_phone || 'N/A'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="detail-section">
                            <h6><i class="fas fa-calendar-alt me-2"></i>Account Information</h6>
                            <div class="detail-row">
                                <span class="label">Member Since:</span>
                                <span class="value">${formatDate(u.regDate)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Last Modified:</span>
                                <span class="value">${u.modifyDate ? formatDateTime(u.modifyDate) : 'Never'}</span>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#userModalBody').html(html);
                $('#userModal').modal('show');
            } else {
                Swal.fire('Error', response.message || 'Could not load user details', 'error');
            }
        },
        error: function() {
            Swal.close();
            Swal.fire('Error', 'Failed to load user details', 'error');
        }
    });
}

// Change user role
let currentUserId = null;
let currentUserRole = null;

function changeUserRole(userId, currentRole) {
    currentUserId = userId;
    currentUserRole = currentRole;
    
    const roleOptions = `
        <div class="text-start">
            <div class="mb-3">
                <label class="form-label fw-bold">Select New Role</label>
                <select class="form-select" id="newRole">
                    <option value="user" ${currentRole == 'user' ? 'selected' : ''}>👤 Customer</option>
                    <option value="staff" ${currentRole == 'staff' ? 'selected' : ''}>👔 Staff Member</option>
                    <option value="admin" ${currentRole == 'admin' ? 'selected' : ''}>🛡️ Administrator</option>
                </select>
            </div>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Note:</strong> Changing user role affects their permissions in the system.
            </div>
        </div>
    `;
    
    Swal.fire({
        title: 'Change User Role',
        html: roleOptions,
        showCancelButton: true,
        confirmButtonText: 'Update Role',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#6f42c1',
        preConfirm: () => {
            const newRole = $('#newRole').val();
            if (newRole === currentRole) {
                Swal.showValidationMessage('Please select a different role');
                return false;
            }
            return { newRole };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Updating...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: 'ajax/change-user-role.php',
                method: 'POST',
                data: {
                    user_id: currentUserId,
                    new_role: result.value.newRole
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Role Updated!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to update role', 'error');
                }
            });
        }
    });
}

// Enroll user as staff
function enrollAsStaff(userId) {
    Swal.fire({
        title: 'Enroll as Staff Member',
        html: `
            <div class="text-start">
                <p class="mb-3">Please provide staff details:</p>
                <div class="mb-3">
                    <label class="form-label fw-bold">Specialization *</label>
                    <input type="text" class="form-control" id="staffSpecialization" placeholder="e.g., Hair Stylist, Makeup Artist" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Experience (Years) *</label>
                    <input type="number" class="form-control" id="staffExperience" min="0" step="0.5" placeholder="0" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Joining Date</label>
                    <input type="date" class="form-control" id="staffJoiningDate" value="${new Date().toISOString().split('T')[0]}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Secondary Phone (Optional)</label>
                    <input type="tel" class="form-control" id="staffPhone" placeholder="Alternative contact number">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Qualifications (Optional)</label>
                    <textarea class="form-control" id="staffQualification" rows="2" placeholder="List relevant qualifications"></textarea>
                </div>
            </div>
        `,
        width: '600px',
        showCancelButton: true,
        confirmButtonText: 'Enroll Staff',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#28a745',
        preConfirm: () => {
            const specialization = $('#staffSpecialization').val().trim();
            const experience = $('#staffExperience').val();
            
            if (!specialization) {
                Swal.showValidationMessage('Specialization is required');
                return false;
            }
            if (!experience || experience < 0) {
                Swal.showValidationMessage('Valid experience is required');
                return false;
            }
            
            return {
                specialization: specialization,
                experience: experience,
                joining_date: $('#staffJoiningDate').val(),
                phone: $('#staffPhone').val(),
                qualification: $('#staffQualification').val()
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Enrolling...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: 'ajax/enroll-as-staff.php',
                method: 'POST',
                data: {
                    user_id: userId,
                    specialization: result.value.specialization,
                    experience: result.value.experience,
                    joining_date: result.value.joining_date,
                    phone: result.value.phone,
                    qualification: result.value.qualification
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Enrolled!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to enroll staff', 'error');
                }
            });
        }
    });
}

// Remove staff role
function removeStaffRole(userId) {
    Swal.fire({
        title: 'Remove Staff Role?',
        html: `
            <div class="text-start">
                <p>This will remove staff privileges and the user will become a regular customer.</p>
                <p class="text-danger"><strong>Warning:</strong> This action will change user role to "Customer".</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        confirmButtonText: 'Yes, Remove Staff Role',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Removing...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: 'ajax/remove-staff-role.php',
                method: 'POST',
                data: { user_id: userId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Removed!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to remove staff role', 'error');
                }
            });
        }
    });
}

// Reset user password
function resetUserPassword(userId) {
    Swal.fire({
        title: 'Reset User Password',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label fw-bold">New Password</label>
                    <input type="password" class="form-control" id="newPassword" placeholder="Enter new password">
                    <small class="text-muted">Minimum 6 characters</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Confirm Password</label>
                    <input type="password" class="form-control" id="confirmPassword" placeholder="Confirm new password">
                </div>
                <div id="passwordMatchMsg" class="small"></div>
            </div>
        `,
        width: '500px',
        showCancelButton: true,
        confirmButtonText: 'Reset Password',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#6f42c1',
        didOpen: () => {
            $('#newPassword, #confirmPassword').on('keyup', function() {
                const newPass = $('#newPassword').val();
                const confirmPass = $('#confirmPassword').val();
                if (confirmPass.length > 0) {
                    if (newPass === confirmPass) {
                        $('#passwordMatchMsg').html('<span class="text-success">✓ Passwords match</span>');
                    } else {
                        $('#passwordMatchMsg').html('<span class="text-danger">✗ Passwords do not match</span>');
                    }
                } else {
                    $('#passwordMatchMsg').html('');
                }
            });
        },
        preConfirm: () => {
            const newPassword = $('#newPassword').val();
            const confirmPassword = $('#confirmPassword').val();
            
            if (!newPassword || !confirmPassword) {
                Swal.showValidationMessage('Please enter and confirm password');
                return false;
            }
            if (newPassword.length < 6) {
                Swal.showValidationMessage('Password must be at least 6 characters');
                return false;
            }
            if (newPassword !== confirmPassword) {
                Swal.showValidationMessage('Passwords do not match');
                return false;
            }
            return { newPassword };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Resetting...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: 'ajax/reset-user-password.php',
                method: 'POST',
                data: {
                    user_id: userId,
                    new_password: result.value.newPassword
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Password Reset!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to reset password', 'error');
                }
            });
        }
    });
}

// Delete user
function deleteUser(userId) {
    Swal.fire({
        title: 'Delete User Account?',
        html: `
            <div class="text-start">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning! This action cannot be undone.</strong>
                </div>
                <p>Type <strong class="text-danger">DELETE USER</strong> to confirm:</p>
                <input type="text" class="form-control" id="deleteConfirm" placeholder="Type DELETE USER here">
            </div>
        `,
        width: '550px',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Permanently Delete',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const confirmText = $('#deleteConfirm').val();
            if (confirmText !== 'DELETE USER') {
                Swal.showValidationMessage('Please type DELETE USER to confirm');
                return false;
            }
            return true;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: 'ajax/delete-user.php',
                method: 'POST',
                data: { user_id: userId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'User account has been permanently deleted.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to delete user', 'error');
                }
            });
        }
    });
}

// Export users
function exportUsers() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = 'export-users.php?' + params.toString();
}

// Utility functions
function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateTimeStr) {
    if (!dateTimeStr) return 'N/A';
    const date = new Date(dateTimeStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
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

function refreshPage() {
    location.reload();
}
</script>

<style>
/* User Management Styles */
.users-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
}

.users-table th {
    background: var(--light);
    padding: 15px;
    font-weight: 600;
    color: var(--dark);
    font-size: 13px;
    text-align: left;
    border-bottom: 2px solid var(--border);
}

.users-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.users-table tbody tr:hover {
    background: rgba(111, 66, 193, 0.03);
}

/* User Info */
.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar-sm {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6f42c1, #9b6fe0);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
}

.user-details strong {
    font-size: 14px;
}

/* Role Badges */
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.role-user {
    background: rgba(23, 162, 184, 0.1);
    color: #17a2b8;
}

.role-staff {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.role-admin {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.status-badge.active {
    background: #d4edda;
    color: #155724;
}

.status-badge.inactive {
    background: #f8d7da;
    color: #721c24;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-icon {
    width: 34px;
    height: 34px;
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

.btn-icon.success:hover {
    background: #28a745;
}

.btn-icon.warning:hover {
    background: #ffc107;
    color: var(--dark);
}

.btn-icon.text-danger:hover {
    background: #dc3545;
    color: white;
}

/* Stats Info */
.stats-info {
    font-size: 12px;
    line-height: 1.6;
}

.stats-info i {
    width: 18px;
    color: var(--primary);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
    transition: all 0.3s;
    border: 1px solid var(--border);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
}

.stat-icon {
    width: 55px;
    height: 55px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
}

.stat-label {
    font-size: 13px;
    color: var(--gray);
    margin-top: 5px;
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.filter-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border);
    background: var(--light);
    border-radius: 12px 12px 0 0;
}

.filter-header h5 {
    margin: 0;
    color: var(--primary);
}

.filter-body {
    padding: 20px;
}

/* Table Card */
.table-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.table-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.table-header h5 {
    margin: 0;
    color: var(--primary);
}

/* Detail Section */
.detail-section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.detail-section h6 {
    color: var(--primary);
    margin-bottom: 15px;
    font-weight: 600;
}

.detail-row {
    display: flex;
    padding: 8px 0;
}

.detail-row .label {
    width: 140px;
    font-weight: 600;
    color: var(--gray);
}

.detail-row .value {
    flex: 1;
}

/* Stat Box */
.stat-box {
    text-align: center;
    padding: 12px;
    background: var(--light);
    border-radius: 10px;
}

.stat-number {
    font-size: 22px;
    font-weight: 700;
    color: var(--primary);
}

.stat-text {
    font-size: 11px;
    color: var(--gray);
    margin-top: 5px;
}

/* Table Footer */
.table-footer {
    padding: 20px;
    border-top: 1px solid var(--border);
    background: var(--light);
}

.pagination {
    gap: 5px;
}

.page-link {
    border-radius: 8px !important;
    color: var(--primary);
}

.page-item.active .page-link {
    background: var(--primary);
    border-color: var(--primary);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px;
}

.empty-state i {
    color: var(--border);
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .stat-value {
        font-size: 22px;
    }
    
    .action-buttons {
        min-width: 180px;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
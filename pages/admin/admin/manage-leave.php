<?php
// admin/manage-leave.php - Admin Manage All Staff Leave
session_start();
require_once '../../includes/dbcon.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin'])) {
    header('Location: ../login.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['fName'] ?? 'Admin';

try {
    $pdo = getPDOConnection();
    
    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = 20;
    $offset = ($page - 1) * $records_per_page;
    
    // Filter
    $staff_filter = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Build query
    $query = "
        SELECT sl.*, s.user_id, r.fName as staff_name, r.lName as staff_lname
        FROM staff_leave sl
        JOIN staff s ON sl.staff_id = s.id
        JOIN reg r ON s.user_id = r.regID
        WHERE 1=1
    ";
    $count_query = "
        SELECT COUNT(*) as total
        FROM staff_leave sl
        JOIN staff s ON sl.staff_id = s.id
        JOIN reg r ON s.user_id = r.regID
        WHERE 1=1
    ";
    $params = [];
    
    if ($staff_filter > 0) {
        $query .= " AND sl.staff_id = ?";
        $count_query .= " AND sl.staff_id = ?";
        $params[] = $staff_filter;
    }
    
    if (!empty($status_filter)) {
        $query .= " AND sl.status = ?";
        $count_query .= " AND sl.status = ?";
        $params[] = $status_filter;
    }
    
    // Get total
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'] ?? 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;
    
    // Get data
    $query .= " ORDER BY sl.leave_date DESC, sl.created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $leave_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all staff for filter
    $staff_list = $pdo->query("
        SELECT s.id, r.fName, r.lName 
        FROM staff s 
        JOIN reg r ON s.user_id = r.regID 
        ORDER BY r.fName
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle status update
if (isset($_GET['action']) && isset($_GET['id'])) {
    $leave_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $new_status = $action == 'approve' ? 'approved' : 'rejected';
    
    $stmt = $pdo->prepare("UPDATE staff_leave SET status = ?, approved_at = NOW(), approved_by = ? WHERE id = ?");
    $stmt->execute([$new_status, $admin_id, $leave_id]);
    
    header('Location: manage-leave.php?updated=1');
    exit;
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM staff_leave WHERE id = ?");
    $stmt->execute([$delete_id]);
    header('Location: manage-leave.php?deleted=1');
    exit;
}

// Include admin header
include 'header/header-admin.php';
?>

<style>
.leave-status {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
}
.leave-status.pending { background: #fff3cd; color: #856404; }
.leave-status.approved { background: #d4edda; color: #155724; }
.leave-status.rejected { background: #f8d7da; color: #721c24; }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="welcome-text">
                            <h1><i class="fas fa-calendar-alt me-2"></i>Manage Leave</h1>
                            <p class="lead mb-0">View and manage all staff leave requests</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-light text-dark p-2 me-2">
                            <i class="fas fa-user-shield me-2"></i>
                            <?php echo htmlspecialchars($admin_name); ?>
                        </span>
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Leave</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Staff Member</label>
                            <select name="staff_id" class="form-select">
                                <option value="0">All Staff</option>
                                <?php foreach ($staff_list as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo $staff_filter == $staff['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($staff['fName'] . ' ' . ($staff['lName'] ?? '')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            <a href="manage-leave.php" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i>Reset
                            </a>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <span class="badge bg-primary"><?php echo $total_records; ?> Records</span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Leave List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Leave Requests</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($leave_records)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="fas fa-calendar-plus fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted">No leave requests found</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($leave_records as $leave): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($leave['staff_name'] . ' ' . ($leave['staff_lname'] ?? '')); ?></strong>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($leave['leave_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo ucfirst($leave['leave_type']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($leave['reason'] ?: '—'); ?></td>
                                        <td>
                                            <span class="leave-status <?php echo $leave['status']; ?>">
                                                <?php echo ucfirst($leave['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($leave['status'] == 'pending'): ?>
                                            <a href="manage-leave.php?action=approve&id=<?php echo $leave['id']; ?>" 
                                               class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="manage-leave.php?action=reject&id=<?php echo $leave['id']; ?>" 
                                               class="btn btn-sm btn-danger">
                                                <i class="fas fa-times"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="manage-leave.php?delete=<?php echo $leave['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Delete this leave request?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                $query_params = [];
                                if ($staff_filter > 0) $query_params['staff_id'] = $staff_filter;
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
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer/footer.php'; ?>
</body>
</html>
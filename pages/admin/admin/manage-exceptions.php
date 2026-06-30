<?php
// admin/manage-exceptions.php - Admin Manage All Staff Exceptions
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
    $date_filter = isset($_GET['date']) ? $_GET['date'] : '';
    
    // Build query
    $query = "
        SELECT se.*, s.user_id, r.fName as staff_name, r.lName as staff_lname
        FROM staff_schedule_exceptions se
        JOIN staff s ON se.staff_id = s.id
        JOIN reg r ON s.user_id = r.regID
        WHERE 1=1
    ";
    $count_query = "
        SELECT COUNT(*) as total
        FROM staff_schedule_exceptions se
        JOIN staff s ON se.staff_id = s.id
        JOIN reg r ON s.user_id = r.regID
        WHERE 1=1
    ";
    $params = [];
    
    if ($staff_filter > 0) {
        $query .= " AND se.staff_id = ?";
        $count_query .= " AND se.staff_id = ?";
        $params[] = $staff_filter;
    }
    
    if (!empty($date_filter)) {
        $query .= " AND se.exception_date = ?";
        $count_query .= " AND se.exception_date = ?";
        $params[] = $date_filter;
    }
    
    // Get total
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'] ?? 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;
    
    // Get data
    $query .= " ORDER BY se.exception_date DESC, se.staff_id LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    
    // Bind all parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $exceptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM staff_schedule_exceptions WHERE id = ?");
    $stmt->execute([$delete_id]);
    header('Location: manage-exceptions.php?deleted=1');
    exit;
}

// Include admin header
include 'header/header-admin.php';
?>

<style>
.exception-row {
    transition: background 0.2s;
}
.exception-row:hover {
    background: #f8f9fa;
}
.exception-badge {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="welcome-text">
                            <h1><i class="fas fa-calendar-exclamation me-2"></i>Manage Exceptions</h1>
                            <p class="lead mb-0">View and manage all staff schedule exceptions</p>
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
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Exceptions</h5>
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
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            <a href="manage-exceptions.php" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i>Reset
                            </a>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <span class="badge bg-primary"><?php echo $total_records; ?> Records</span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Exceptions List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Exceptions</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Time</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($exceptions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="fas fa-calendar-check fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted">No exceptions found</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($exceptions as $exception): ?>
                                    <tr class="exception-row">
                                        <td>
                                            <strong><?php echo htmlspecialchars($exception['staff_name'] . ' ' . ($exception['staff_lname'] ?? '')); ?></strong>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($exception['exception_date'])); ?></td>
                                        <td>
                                            <span class="exception-badge <?php echo $exception['is_working_day'] ? 'bg-success text-white' : 'bg-danger text-white'; ?>">
                                                <?php echo $exception['is_working_day'] ? 'Working Day' : 'Day Off'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($exception['start_time'] && $exception['end_time']): ?>
                                            <?php echo substr($exception['start_time'], 0, 5); ?> - <?php echo substr($exception['end_time'], 0, 5); ?>
                                            <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($exception['reason'] ?? '—'); ?></td>
                                        <td>
                                            <a href="manage-exceptions.php?delete=<?php echo $exception['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Delete this exception?')">
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
                                if (!empty($date_filter)) $query_params['date'] = $date_filter;
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
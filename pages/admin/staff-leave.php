<?php
// staff-leave.php - Staff Leave Management
session_start();
require_once '../../includes/dbcon.php';

// Check if user is logged in and has staff/admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$staff_name = $_SESSION['fName'] ?? 'Staff';
$role = $_SESSION['role'] ?? 'staff';

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
    
    // Get unread notifications count
    $notif_count_query = "SELECT COUNT(*) as count FROM staff_notifications WHERE staff_id = ? AND is_read = 0";
    $notif_count_stmt = $pdo->prepare($notif_count_query);
    $notif_count_stmt->execute([$staff_db_id]);
    $unread_count = $notif_count_stmt->fetch()['count'] ?? 0;
    
    // Get leave records with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = 15;
    $offset = ($page - 1) * $records_per_page;
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM staff_leave WHERE staff_id = ?";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute([$staff_db_id]);
    $total_records = $count_stmt->fetch()['total'] ?? 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;
    
    // Get paginated leave records - FIXED: Use bindValue with PDO::PARAM_INT
    $leave_query = "
        SELECT * FROM staff_leave 
        WHERE staff_id = ? 
        ORDER BY leave_date DESC 
        LIMIT ? OFFSET ?
    ";
    $leave_stmt = $pdo->prepare($leave_query);
    $leave_stmt->bindValue(1, $staff_db_id, PDO::PARAM_INT);
    $leave_stmt->bindValue(2, $records_per_page, PDO::PARAM_INT);
    $leave_stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $leave_stmt->execute();
    $leave_records = $leave_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM staff_leave 
        WHERE staff_id = ?
    ";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$staff_db_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    try {
        $pdo = getPDOConnection();
        
        // Add leave request
        if ($action === 'add_leave') {
            $leave_date = $_POST['leave_date'] ?? '';
            $leave_type = $_POST['leave_type'] ?? 'casual';
            $reason = $_POST['reason'] ?? '';
            
            if (empty($leave_date)) {
                echo json_encode(['success' => false, 'message' => 'Date is required']);
                exit;
            }
            
            $insert_stmt = $pdo->prepare("
                INSERT INTO staff_leave (staff_id, leave_date, leave_type, reason, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $insert_stmt->execute([$staff_db_id, $leave_date, $leave_type, $reason]);
            
            echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully']);
            exit;
        }
        
        // Delete leave request
        if ($action === 'delete_leave') {
            $leave_id = intval($_POST['leave_id'] ?? 0);
            
            $delete_stmt = $pdo->prepare("DELETE FROM staff_leave WHERE id = ? AND staff_id = ? AND status = 'pending'");
            $delete_stmt->execute([$leave_id, $staff_db_id]);
            
            if ($delete_stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Leave request deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Cannot delete approved/rejected leave']);
            }
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Include staff header
include 'header/header-staff.php';
?>

<style>
.leave-item {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    border-left: 4px solid;
}
.leave-item.pending { border-left-color: #ffc107; background: #fff8e1; }
.leave-item.approved { border-left-color: #28a745; background: #e8f5e9; }
.leave-item.rejected { border-left-color: #dc3545; background: #ffebee; }
.leave-item .date { font-weight: 600; font-size: 16px; }
.leave-item .status-badge { font-size: 12px; padding: 3px 10px; border-radius: 20px; }
</style>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="welcome-text">
                            <h1><i class="fas fa-calendar-alt me-2"></i>Leave Management</h1>
                            <p class="lead mb-0">Manage your leave requests</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-light text-dark p-2 me-2">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($staff_name); ?>
                        </span>
                        <button class="btn btn-primary btn-sm" onclick="showAddLeaveModal()">
                            <i class="fas fa-plus me-1"></i>Request Leave
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-6">
                    <div class="card" style="border-left: 4px solid #6f42c1;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $stats['total'] ?? 0; ?></h5>
                            <small class="text-muted">Total Requests</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card" style="border-left: 4px solid #ffc107;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $stats['pending'] ?? 0; ?></h5>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card" style="border-left: 4px solid #28a745;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $stats['approved'] ?? 0; ?></h5>
                            <small class="text-muted">Approved</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card" style="border-left: 4px solid #dc3545;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $stats['rejected'] ?? 0; ?></h5>
                            <small class="text-muted">Rejected</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Leave Requests</h5>
                    <span class="badge bg-primary float-end"><?php echo $total_records; ?> Records</span>
                </div>
                <div class="card-body">
                    <?php if (empty($leave_records)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-plus fa-4x text-muted mb-3 d-block"></i>
                        <h5>No Leave Requests</h5>
                        <p class="text-muted">Submit a leave request to get started.</p>
                        <button class="btn btn-primary" onclick="showAddLeaveModal()">
                            <i class="fas fa-plus me-2"></i>Request Leave
                        </button>
                    </div>
                    <?php else: ?>
                        <?php foreach ($leave_records as $leave): ?>
                        <div class="leave-item <?php echo $leave['status']; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="date">
                                        <?php echo date('D, M d, Y', strtotime($leave['leave_date'])); ?>
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge bg-<?php echo $leave['status'] == 'approved' ? 'success' : ($leave['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                            <?php echo ucfirst($leave['status']); ?>
                                        </span>
                                        <span class="badge bg-secondary ms-1"><?php echo ucfirst($leave['leave_type']); ?></span>
                                        <?php if ($leave['reason']): ?>
                                        <span class="text-muted ms-2">
                                            <i class="fas fa-comment me-1"></i> <?php echo htmlspecialchars($leave['reason']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($leave['status'] == 'pending'): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteLeave(<?php echo $leave['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> requests
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=1"><i class="fas fa-angle-double-left"></i></a>
                                </li>
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>"><i class="fas fa-angle-left"></i></a>
                                </li>
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>"><i class="fas fa-angle-right"></i></a>
                                </li>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>"><i class="fas fa-angle-double-right"></i></a>
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

<!-- Add Leave Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Request Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="leaveForm">
                    <div class="mb-3">
                        <label class="form-label">Leave Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="leave_date" id="leaveDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="leave_type" id="leaveType">
                            <option value="casual">Casual</option>
                            <option value="sick">Sick</option>
                            <option value="annual">Annual</option>
                            <option value="emergency">Emergency</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" id="leaveReason" rows="3" placeholder="Please provide a reason for your leave..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveLeave()">
                    <i class="fas fa-paper-plane me-2"></i>Submit Request
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Show add leave modal
function showAddLeaveModal() {
    document.getElementById('leaveDate').value = '';
    document.getElementById('leaveType').value = 'casual';
    document.getElementById('leaveReason').value = '';
    $('#leaveModal').modal('show');
}

// Save leave request
function saveLeave() {
    const form = document.getElementById('leaveForm');
    const formData = new FormData(form);
    
    const data = {
        action: 'add_leave',
        leave_date: formData.get('leave_date'),
        leave_type: formData.get('leave_type'),
        reason: formData.get('reason') || ''
    };
    
    if (!data.leave_date) {
        Swal.fire('Error', 'Please select a date', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Submitting...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data)
    })
    .then(response => response.json())
    .then(response => {
        Swal.close();
        $('#leaveModal').modal('hide');
        if (response.success) {
            Swal.fire('Success!', response.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', response.message || 'Failed to submit request', 'error');
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire('Error', 'Failed to submit request', 'error');
    });
}

// Delete leave request
function deleteLeave(leaveId) {
    Swal.fire({
        title: 'Delete Leave Request?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'delete_leave',
                    leave_id: leaveId
                })
            })
            .then(response => response.json())
            .then(response => {
                Swal.close();
                if (response.success) {
                    Swal.fire('Deleted!', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', response.message || 'Failed to delete request', 'error');
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire('Error', 'Failed to delete request', 'error');
            });
        }
    });
}

// Show notifications function for bell icon
function showNotifications() {
    Swal.fire({
        title: '<i class="fas fa-bell me-2"></i> Notifications',
        html: `
            <div class="text-start">
                <p><strong>Total:</strong> <?php echo $total_records; ?></p>
                <p><strong>Unread:</strong> <?php echo $unread_count ?? 0; ?></p>
                <hr>
                <p class="text-muted small">Click "View All" to see all notifications</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'View All',
        cancelButtonText: 'Close',
        confirmButtonColor: '#6f42c1'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'notifications.php';
        }
    });
}
</script>
</body>
</html>
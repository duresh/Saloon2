<?php
// staff-exceptions.php - Staff Schedule Exceptions Management
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

// Get staff details
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
    
    // Get exceptions with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = 15;
    $offset = ($page - 1) * $records_per_page;
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM staff_schedule_exceptions WHERE staff_id = ?";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute([$staff_db_id]);
    $total_records = $count_stmt->fetch()['total'] ?? 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;
    
    // Get paginated exceptions - FIXED: Use bindValue with PDO::PARAM_INT
    $exceptions_query = "
        SELECT * FROM staff_schedule_exceptions 
        WHERE staff_id = ? 
        ORDER BY exception_date DESC 
        LIMIT ? OFFSET ?
    ";
    $exceptions_stmt = $pdo->prepare($exceptions_query);
    $exceptions_stmt->bindValue(1, $staff_db_id, PDO::PARAM_INT);
    $exceptions_stmt->bindValue(2, $records_per_page, PDO::PARAM_INT);
    $exceptions_stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $exceptions_stmt->execute();
    $exceptions = $exceptions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    try {
        $pdo = getPDOConnection();
        
        // Add exception
        if ($action === 'add_exception') {
            $exception_date = $_POST['exception_date'] ?? '';
            $start_time = $_POST['start_time'] ?? null;
            $end_time = $_POST['end_time'] ?? null;
            $is_working_day = isset($_POST['is_working_day']) ? 1 : 0;
            $reason = $_POST['reason'] ?? '';
            
            if (empty($exception_date)) {
                echo json_encode(['success' => false, 'message' => 'Date is required']);
                exit;
            }
            
            // Check if exists
            $check_stmt = $pdo->prepare("SELECT id FROM staff_schedule_exceptions WHERE staff_id = ? AND exception_date = ?");
            $check_stmt->execute([$staff_db_id, $exception_date]);
            $exists = $check_stmt->fetch();
            
            if ($exists) {
                $update_stmt = $pdo->prepare("
                    UPDATE staff_schedule_exceptions 
                    SET start_time = ?, end_time = ?, is_working_day = ?, reason = ?, updated_at = NOW()
                    WHERE staff_id = ? AND exception_date = ?
                ");
                $update_stmt->execute([$start_time, $end_time, $is_working_day, $reason, $staff_db_id, $exception_date]);
                echo json_encode(['success' => true, 'message' => 'Exception updated successfully']);
            } else {
                $insert_stmt = $pdo->prepare("
                    INSERT INTO staff_schedule_exceptions (staff_id, exception_date, start_time, end_time, is_working_day, reason) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([$staff_db_id, $exception_date, $start_time, $end_time, $is_working_day, $reason]);
                echo json_encode(['success' => true, 'message' => 'Exception added successfully']);
            }
            exit;
        }
        
        // Delete exception
        if ($action === 'delete_exception') {
            $exception_id = intval($_POST['exception_id'] ?? 0);
            
            $delete_stmt = $pdo->prepare("DELETE FROM staff_schedule_exceptions WHERE id = ? AND staff_id = ?");
            $delete_stmt->execute([$exception_id, $staff_db_id]);
            echo json_encode(['success' => true, 'message' => 'Exception deleted successfully']);
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
.exception-card { margin-bottom: 20px; }
.exception-item { 
    padding: 15px; 
    border-radius: 8px; 
    margin-bottom: 10px;
    border-left: 4px solid;
}
.exception-item.working { border-left-color: #28a745; background: #f8f9fa; }
.exception-item.off { border-left-color: #dc3545; background: #f8f9fa; }
.exception-item .date { font-weight: 600; font-size: 16px; }
.exception-item .type-badge { font-size: 12px; padding: 3px 10px; border-radius: 20px; }
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
                            <h1><i class="fas fa-calendar-exclamation me-2"></i>Schedule Exceptions</h1>
                            <p class="lead mb-0">Manage your special working days and days off</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-light text-dark p-2 me-2">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($staff_name); ?>
                        </span>
                        <button class="btn btn-primary btn-sm" onclick="showAddExceptionModal()">
                            <i class="fas fa-plus me-1"></i>Add Exception
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4 col-6">
                    <div class="card" style="border-left: 4px solid #6f42c1;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $total_records; ?></h5>
                            <small class="text-muted">Total Exceptions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="card" style="border-left: 4px solid #28a745;">
                        <div class="card-body">
                            <h5 class="card-title mb-0">
                                <?php 
                                $working = array_filter($exceptions, function($e) { return $e['is_working_day'] == 1; });
                                echo count($working);
                                ?>
                            </h5>
                            <small class="text-muted">Working Days</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="card" style="border-left: 4px solid #dc3545;">
                        <div class="card-body">
                            <h5 class="card-title mb-0">
                                <?php 
                                $off = array_filter($exceptions, function($e) { return $e['is_working_day'] == 0; });
                                echo count($off);
                                ?>
                            </h5>
                            <small class="text-muted">Days Off</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Exceptions List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Exceptions</h5>
                    <span class="badge bg-primary float-end"><?php echo $total_records; ?> Records</span>
                </div>
                <div class="card-body">
                    <?php if (empty($exceptions)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-check fa-4x text-muted mb-3 d-block"></i>
                        <h5>No Schedule Exceptions</h5>
                        <p class="text-muted">Add exceptions for special working days or days off.</p>
                        <button class="btn btn-primary" onclick="showAddExceptionModal()">
                            <i class="fas fa-plus me-2"></i>Add Exception
                        </button>
                    </div>
                    <?php else: ?>
                        <?php foreach ($exceptions as $exception): ?>
                        <div class="exception-item <?php echo $exception['is_working_day'] ? 'working' : 'off'; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="date">
                                        <?php echo date('D, M d, Y', strtotime($exception['exception_date'])); ?>
                                    </div>
                                    <div class="mt-1">
                                        <?php if ($exception['is_working_day']): ?>
                                        <span class="type-badge bg-success text-white">Working Day</span>
                                        <?php if ($exception['start_time'] && $exception['end_time']): ?>
                                        <span class="text-muted ms-2">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo substr($exception['start_time'], 0, 5); ?> - <?php echo substr($exception['end_time'], 0, 5); ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="type-badge bg-danger text-white">Day Off</span>
                                        <?php endif; ?>
                                        <?php if ($exception['reason']): ?>
                                        <span class="text-muted ms-2">
                                            <i class="fas fa-comment me-1"></i> <?php echo htmlspecialchars($exception['reason']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-danger" onclick="deleteException(<?php echo $exception['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
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
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> exceptions
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

<!-- Add Exception Modal -->
<div class="modal fade" id="exceptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Schedule Exception</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="exceptionForm">
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="exception_date" id="exceptionDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="is_working_day" id="exceptionType">
                            <option value="1">Working Day (Special Hours)</option>
                            <option value="0">Day Off</option>
                        </select>
                    </div>
                    <div class="mb-3" id="exceptionTimeFields">
                        <label class="form-label">Working Hours</label>
                        <div class="row">
                            <div class="col-6">
                                <input type="time" class="form-control" name="start_time" id="exceptionStart" value="09:00">
                            </div>
                            <div class="col-6">
                                <input type="time" class="form-control" name="end_time" id="exceptionEnd" value="17:00">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason (optional)</label>
                        <input type="text" class="form-control" name="reason" id="exceptionReason" placeholder="e.g., Holiday, Training, etc.">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveException()">
                    <i class="fas fa-save me-2"></i>Save Exception
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Show add exception modal
function showAddExceptionModal() {
    // Set default date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('exceptionDate').value = tomorrow.toISOString().split('T')[0];
    document.getElementById('exceptionType').value = '1';
    document.getElementById('exceptionStart').value = '09:00';
    document.getElementById('exceptionEnd').value = '17:00';
    document.getElementById('exceptionReason').value = '';
    
    $('#exceptionModal').modal('show');
}

// Toggle time fields based on exception type
$(document).on('change', '#exceptionType', function() {
    if ($(this).val() === '1') {
        $('#exceptionTimeFields').show();
    } else {
        $('#exceptionTimeFields').hide();
    }
});

// Save exception
function saveException() {
    const form = document.getElementById('exceptionForm');
    const formData = new FormData(form);
    
    const data = {
        action: 'add_exception',
        exception_date: formData.get('exception_date'),
        is_working_day: parseInt(formData.get('is_working_day')),
        start_time: formData.get('is_working_day') === '1' ? formData.get('start_time') + ':00' : null,
        end_time: formData.get('is_working_day') === '1' ? formData.get('end_time') + ':00' : null,
        reason: formData.get('reason') || ''
    };
    
    if (!data.exception_date) {
        Swal.fire('Error', 'Please select a date', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Saving...',
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
        $('#exceptionModal').modal('hide');
        if (response.success) {
            Swal.fire('Success!', response.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', response.message || 'Failed to save exception', 'error');
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire('Error', 'Failed to save exception', 'error');
    });
}

// Delete exception
function deleteException(exceptionId) {
    Swal.fire({
        title: 'Delete Exception?',
        text: 'Are you sure you want to remove this schedule exception?',
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
                    action: 'delete_exception',
                    exception_id: exceptionId
                })
            })
            .then(response => response.json())
            .then(response => {
                Swal.close();
                if (response.success) {
                    Swal.fire('Deleted!', response.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', response.message || 'Failed to delete exception', 'error');
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire('Error', 'Failed to delete exception', 'error');
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
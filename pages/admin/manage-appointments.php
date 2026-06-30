<?php
// manage-appointments.php - Admin Manage All Appointments
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
    
    // Filters
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $staff_filter = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build query
    $query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.price,
            s.duration,
            u.fName as customer_name,
            u.lName as customer_lname,
            u.email as customer_email,
            u.contactNo as customer_phone,
            st.id as staff_id,
            r.fName as staff_name,
            r.lName as staff_lname,
            r.email as staff_email,
            (SELECT COUNT(*) FROM appointment_status_logs WHERE appointment_id = a.id) as status_logs_count
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN reg u ON a.user_id = u.regID
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN reg r ON st.user_id = r.regID
        WHERE 1=1
    ";
    
    $count_query = "
        SELECT COUNT(*) as total
        FROM appointments a
        WHERE 1=1
    ";
    
    $params = [];
    $count_params = [];
    
    // Apply status filter
    if (!empty($status_filter)) {
        $query .= " AND a.status = ?";
        $count_query .= " AND a.status = ?";
        $params[] = $status_filter;
        $count_params[] = $status_filter;
    }
    
    // Apply staff filter
    if ($staff_filter > 0) {
        $query .= " AND a.staff_id = ?";
        $count_query .= " AND a.staff_id = ?";
        $params[] = $staff_filter;
        $count_params[] = $staff_filter;
    }
    
    // Apply date filters
    if (!empty($date_from)) {
        $query .= " AND DATE(a.appointment_date) >= ?";
        $count_query .= " AND DATE(a.appointment_date) >= ?";
        $params[] = $date_from;
        $count_params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND DATE(a.appointment_date) <= ?";
        $count_query .= " AND DATE(a.appointment_date) <= ?";
        $params[] = $date_to;
        $count_params[] = $date_to;
    }
    
    // Apply search
    if (!empty($search)) {
        $query .= " AND (s.name LIKE ? OR u.fName LIKE ? OR a.notes LIKE ? OR a.id LIKE ?)";
        $count_query .= " AND (s.name LIKE ? OR u.fName LIKE ? OR a.notes LIKE ? OR a.id LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $count_params[] = $search_param;
        $count_params[] = $search_param;
        $count_params[] = $search_param;
        $count_params[] = $search_param;
    }
    
    // Get total count
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetch()['total'] ?? 0;
    $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 1;
    
    // Get data with pagination
    $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);
    
    // Bind all parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    $param_count = count($params);
    $stmt->bindValue($param_count + 1, $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue($param_count + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today,
            SUM(CASE WHEN DATE(appointment_date) > CURDATE() AND status IN ('pending', 'confirmed') THEN 1 ELSE 0 END) as upcoming
        FROM appointments
    ";
    $stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
    
    // Get staff list for filter
    $staff_list = $pdo->query("
        SELECT s.id, r.fName, r.lName 
        FROM staff s 
        JOIN reg r ON s.user_id = r.regID 
        WHERE r.cStatus = 1
        ORDER BY r.fName
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $staff_id = intval($_POST['staff_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    try {
        $pdo = getPDOConnection();
        $pdo->beginTransaction();
        
        // Get current appointment
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            exit;
        }
        
        $old_status = $appointment['status'];
        $user_id = $appointment['user_id'];
        $service_id = $appointment['service_id'];
        $old_staff_id = $appointment['staff_id'];
        
        switch ($action) {
            case 'update_status':
                $update_fields = [];
                $params = [];
                
                $update_fields[] = "status = ?";
                $params[] = $status;
                $update_fields[] = "modified_at = NOW()";
                
                if ($status == 'completed' || $status == 'cancelled') {
                    $update_fields[] = "modified_at = NOW()";
                }
                
                // If status is cancelled, log the reason
                if ($status == 'cancelled' && !empty($reason)) {
                    $update_fields[] = "notes = CONCAT(IFNULL(notes, ''), '\nCancelled: ', ?)";
                    $params[] = $reason;
                }
                
                $params[] = $appointment_id;
                $query = "UPDATE appointments SET " . implode(', ', $update_fields) . " WHERE id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                
                // Log status change
                $log_stmt = $pdo->prepare("
                    INSERT INTO appointment_status_logs (appointment_id, old_status, new_status, changed_by, changed_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $log_stmt->execute([$appointment_id, $old_status, $status, $admin_id]);
                
                $message = "Appointment status updated successfully";
                break;
                
            case 'assign_staff':
                if ($staff_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Please select a staff member']);
                    exit;
                }
                
                $update_stmt = $pdo->prepare("
                    UPDATE appointments SET staff_id = ?, modified_at = NOW() WHERE id = ?
                ");
                $update_stmt->execute([$staff_id, $appointment_id]);
                
                // Log staff assignment
                $log_stmt = $pdo->prepare("
                    INSERT INTO appointment_staff_logs (appointment_id, staff_id, action, performed_by, created_at) 
                    VALUES (?, ?, 'assigned', ?, NOW())
                ");
                $log_stmt->execute([$appointment_id, $staff_id, $admin_id]);
                
                $message = "Staff assigned successfully";
                break;
                
            case 'reschedule':
                $new_date = $_POST['new_date'] ?? '';
                $new_time = $_POST['new_time'] ?? '';
                
                if (empty($new_date) || empty($new_time)) {
                    echo json_encode(['success' => false, 'message' => 'Date and time are required']);
                    exit;
                }
                
                $update_stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET appointment_date = ?, appointment_time = ?, 
                        modified_at = NOW(), 
                        reschedule_count = reschedule_count + 1,
                        last_reschedule_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$new_date, $new_time, $appointment_id]);
                
                // Log reschedule
                $log_stmt = $pdo->prepare("
                    INSERT INTO appointment_reschedules 
                    (appointment_id, user_id, old_date, new_date, old_time, new_time, rescheduled_by, rescheduled_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'admin', NOW())
                ");
                $log_stmt->execute([
                    $appointment_id, 
                    $user_id, 
                    $appointment['appointment_date'], 
                    $new_date, 
                    $appointment['appointment_time'], 
                    $new_time
                ]);
                
                $message = "Appointment rescheduled successfully";
                break;
                
            case 'delete':
                $delete_stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
                $delete_stmt->execute([$appointment_id]);
                
                $message = "Appointment deleted successfully";
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
        
        $pdo->commit();
        
        // Send notification to customer for status changes
        if (in_array($action, ['update_status', 'assign_staff', 'reschedule']) && isset($user_id)) {
            try {
                $notif_title = "Appointment Update";
                $notif_message = "Your appointment has been updated by admin.";
                
                if ($action == 'update_status' && $status == 'confirmed') {
                    $notif_title = "Appointment Confirmed";
                    $notif_message = "Your appointment has been confirmed.";
                } elseif ($action == 'update_status' && $status == 'cancelled') {
                    $notif_title = "Appointment Cancelled";
                    $notif_message = "Your appointment has been cancelled. Reason: " . ($reason ?: 'Not specified');
                } elseif ($action == 'assign_staff') {
                    $staff_name = $pdo->query("SELECT r.fName FROM staff s JOIN reg r ON s.user_id = r.regID WHERE s.id = $staff_id")->fetchColumn();
                    $notif_title = "Staff Assigned";
                    $notif_message = "Staff member " . ($staff_name ?: '') . " has been assigned to your appointment.";
                } elseif ($action == 'reschedule') {
                    $notif_title = "Appointment Rescheduled";
                    $notif_message = "Your appointment has been rescheduled to " . date('M d, Y', strtotime($new_date)) . " at " . date('g:i A', strtotime($new_time));
                }
                
                $notif_stmt = $pdo->prepare("
                    INSERT INTO customer_notifications (user_id, appointment_id, type, title, message, created_at) 
                    VALUES (?, ?, 'appointment_update', ?, ?, NOW())
                ");
                $notif_stmt->execute([$user_id, $appointment_id, $notif_title, $notif_message]);
            } catch (Exception $e) {
                // Silent fail for notifications
            }
        }
        
        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle quick actions via GET
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    try {
        if ($action == 'confirm') {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'confirmed', modified_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: manage-appointments.php?success=confirmed');
        } elseif ($action == 'complete') {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed', modified_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: manage-appointments.php?success=completed');
        } elseif ($action == 'cancel') {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', modified_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: manage-appointments.php?success=cancelled');
        } elseif ($action == 'delete') {
            $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: manage-appointments.php?success=deleted');
        }
    } catch (Exception $e) {
        header('Location: manage-appointments.php?error=' . urlencode($e->getMessage()));
    }
    exit;
}

// Include admin header
include 'header/header-admin.php';
?>

<style>
.appointment-row {
    transition: background 0.2s;
}
.appointment-row:hover {
    background: #f8f9fa;
}
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.confirmed { background: #d4edda; color: #155724; }
.status-badge.completed { background: #cce5ff; color: #004085; }
.status-badge.cancelled { background: #f8d7da; color: #721c24; }
.quick-actions .btn { padding: 2px 8px; font-size: 12px; }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="welcome-text">
                            <h1><i class="fas fa-calendar-check me-2"></i>Manage Appointments</h1>
                            <p class="lead mb-0">View and manage all appointments</p>
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

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2 col-4">
                    <div class="card" style="border-left: 4px solid #6f42c1;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $stats['total'] ?? 0; ?></h5>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="card" style="border-left: 4px solid #17a2b8;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $stats['today'] ?? 0; ?></h5>
                            <small class="text-muted">Today</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="card" style="border-left: 4px solid #ffc107;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $stats['pending'] ?? 0; ?></h5>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="card" style="border-left: 4px solid #28a745;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $stats['confirmed'] ?? 0; ?></h5>
                            <small class="text-muted">Confirmed</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="card" style="border-left: 4px solid #28a745;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $stats['completed'] ?? 0; ?></h5>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="card" style="border-left: 4px solid #dc3545;">
                        <div class="card-body">
                            <h5 class="card-title mb-0"><?php echo $stats['cancelled'] ?? 0; ?></h5>
                            <small class="text-muted">Cancelled</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Appointments</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Staff</label>
                            <select name="staff_id" class="form-select">
                                <option value="0">All Staff</option>
                                <?php foreach ($staff_list as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo $staff_filter == $staff['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($staff['fName'] . ' ' . ($staff['lName'] ?? '')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Customer, service..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <a href="manage-appointments.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-undo"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Appointments List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Appointments</h5>
                    <span class="badge bg-primary float-end"><?php echo $total_records; ?> Records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Service</th>
                                    <th>Date & Time</th>
                                    <th>Staff</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3 d-block"></i>
                                        <p class="text-muted">No appointments found</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($appointments as $appointment): ?>
                                    <tr class="appointment-row">
                                        <td>
                                            <span class="badge bg-secondary">#<?php echo $appointment['id']; ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($appointment['customer_name'] . ' ' . ($appointment['customer_lname'] ?? '')); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['customer_phone'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($appointment['service_name'] ?? 'N/A'); ?>
                                            <br>
                                            <small class="text-muted"><?php echo $appointment['duration'] ?? 'N/A'; ?> mins | Rs: <?php echo number_format($appointment['price'] ?? 0, 2); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></small>
                                            <?php if ($appointment['reschedule_count'] > 0): ?>
                                            <br>
                                            <span class="badge bg-warning text-dark" style="font-size: 9px;">
                                                <i class="fas fa-sync-alt"></i> x<?php echo $appointment['reschedule_count']; ?>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($appointment['staff_name'])): ?>
                                            <i class="fas fa-user-tie me-1"></i>
                                            <?php echo htmlspecialchars($appointment['staff_name'] . ' ' . ($appointment['staff_lname'] ?? '')); ?>
                                            <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="viewAppointment(<?php echo $appointment['id']; ?>)">
                                                            <i class="fas fa-eye me-2"></i>View Details
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="editAppointment(<?php echo $appointment['id']; ?>)">
                                                            <i class="fas fa-edit me-2"></i>Edit
                                                        </a>
                                                    </li>
                                                    <?php if ($appointment['status'] == 'pending'): ?>
                                                    <li>
                                                        <a class="dropdown-item text-success" href="manage-appointments.php?action=confirm&id=<?php echo $appointment['id']; ?>">
                                                            <i class="fas fa-check me-2"></i>Confirm
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="cancelAppointment(<?php echo $appointment['id']; ?>)">
                                                            <i class="fas fa-times me-2"></i>Cancel
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <?php if ($appointment['status'] == 'confirmed' || $appointment['status'] == 'pending'): ?>
                                                    <li>
                                                        <a class="dropdown-item text-info" href="#" onclick="rescheduleAppointment(<?php echo $appointment['id']; ?>)">
                                                            <i class="fas fa-calendar-alt me-2"></i>Reschedule
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-warning" href="#" onclick="assignStaff(<?php echo $appointment['id']; ?>)">
                                                            <i class="fas fa-user-plus me-2"></i>Assign Staff
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <?php if ($appointment['status'] == 'confirmed'): ?>
                                                    <li>
                                                        <a class="dropdown-item text-success" href="manage-appointments.php?action=complete&id=<?php echo $appointment['id']; ?>">
                                                            <i class="fas fa-check-double me-2"></i>Mark Complete
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="deleteAppointment(<?php echo $appointment['id']; ?>)">
                                                            <i class="fas fa-trash me-2"></i>Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
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
                                if (!empty($status_filter)) $query_params['status'] = $status_filter;
                                if ($staff_filter > 0) $query_params['staff_id'] = $staff_filter;
                                if (!empty($date_from)) $query_params['date_from'] = $date_from;
                                if (!empty($date_to)) $query_params['date_to'] = $date_to;
                                if (!empty($search)) $query_params['search'] = $search;
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

<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="appointmentDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Staff Modal -->
<div class="modal fade" id="assignStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Assign Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assignAppointmentId">
                <div class="mb-3">
                    <label class="form-label">Select Staff Member</label>
                    <select class="form-select" id="assignStaffSelect">
                        <option value="">Select Staff...</option>
                        <?php foreach ($staff_list as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>">
                            <?php echo htmlspecialchars($staff['fName'] . ' ' . ($staff['lName'] ?? '')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAssignStaff()">
                    <i class="fas fa-check me-2"></i>Assign
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reschedule Modal -->
<div class="modal fade" id="rescheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calendar-alt me-2"></i>Reschedule Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rescheduleAppointmentId">
                <div class="mb-3">
                    <label class="form-label">New Date</label>
                    <input type="date" class="form-control" id="rescheduleDate">
                </div>
                <div class="mb-3">
                    <label class="form-label">New Time</label>
                    <input type="time" class="form-control" id="rescheduleTime">
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason (optional)</label>
                    <textarea class="form-control" id="rescheduleReason" rows="2" placeholder="Reason for reschedule..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitReschedule()">
                    <i class="fas fa-calendar-check me-2"></i>Reschedule
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-times-circle me-2"></i>Cancel Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cancelAppointmentId">
                <div class="mb-3">
                    <label class="form-label">Reason for cancellation</label>
                    <textarea class="form-control" id="cancelReason" rows="3" placeholder="Please provide a reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitCancel()">
                    <i class="fas fa-times me-2"></i>Cancel Appointment
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// View appointment details
function viewAppointment(id) {
    $('#appointmentDetails').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary"></div>
        </div>
    `);
    $('#appointmentModal').modal('show');
    
    $.ajax({
        url: 'ajax/get-appointment-details.php',
        method: 'POST',
        data: { id: id, admin: 1 },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.appointment) {
                var apt = response.appointment;
                var html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-user me-2"></i>Customer</h6>
                            <p><strong>Name:</strong> ${apt.customer_name}</p>
                            <p><strong>Email:</strong> ${apt.customer_email || 'N/A'}</p>
                            <p><strong>Phone:</strong> ${apt.customer_phone || 'N/A'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-cut me-2"></i>Service</h6>
                            <p><strong>Name:</strong> ${apt.service_name}</p>
                            <p><strong>Price:</strong> Rs: ${parseFloat(apt.service_price || 0).toFixed(2)}</p>
                            <p><strong>Duration:</strong> ${apt.service_duration || 'N/A'} mins</p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-calendar me-2"></i>Appointment</h6>
                            <p><strong>Date:</strong> ${formatDate(apt.appointment_date)}</p>
                            <p><strong>Time:</strong> ${formatTime(apt.appointment_time)}</p>
                            <p><strong>Status:</strong> <span class="status-badge ${apt.status}">${apt.status.charAt(0).toUpperCase() + apt.status.slice(1)}</span></p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-user-tie me-2"></i>Staff</h6>
                            <p><strong>Name:</strong> ${apt.staff_name || 'Not assigned'}</p>
                            <p><strong>Specialization:</strong> ${apt.staff_specialization || 'N/A'}</p>
                            ${apt.reschedule_count > 0 ? `<p><strong>Rescheduled:</strong> ${apt.reschedule_count} times</p>` : ''}
                        </div>
                    </div>
                    ${apt.notes ? `
                    <hr>
                    <h6><i class="fas fa-sticky-note me-2"></i>Notes</h6>
                    <p>${apt.notes}</p>
                    ` : ''}
                `;
                $('#appointmentDetails').html(html);
            } else {
                $('#appointmentDetails').html(`<div class="alert alert-danger">${response.message || 'Could not load details'}</div>`);
            }
        },
        error: function() {
            $('#appointmentDetails').html(`<div class="alert alert-danger">Failed to load details</div>`);
        }
    });
}

// Edit appointment
function editAppointment(id) {
    window.location.href = 'edit-appointment.php?id=' + id;
}

// Assign staff
function assignStaff(id) {
    $('#assignAppointmentId').val(id);
    $('#assignStaffSelect').val('');
    $('#assignStaffModal').modal('show');
}

function submitAssignStaff() {
    var appointmentId = $('#assignAppointmentId').val();
    var staffId = $('#assignStaffSelect').val();
    
    if (!staffId) {
        Swal.fire('Error', 'Please select a staff member', 'error');
        return;
    }
    
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: {
            action: 'assign_staff',
            appointment_id: appointmentId,
            staff_id: staffId
        },
        dataType: 'json',
        beforeSend: function() {
            Swal.fire({ title: 'Assigning...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        },
        success: function(response) {
            Swal.close();
            $('#assignStaffModal').modal('hide');
            if (response.success) {
                Swal.fire('Success', response.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.close();
            Swal.fire('Error', 'Failed to assign staff', 'error');
        }
    });
}

// Reschedule appointment
function rescheduleAppointment(id) {
    $('#rescheduleAppointmentId').val(id);
    $('#rescheduleDate').val('');
    $('#rescheduleTime').val('');
    $('#rescheduleReason').val('');
    $('#rescheduleModal').modal('show');
}

function submitReschedule() {
    var appointmentId = $('#rescheduleAppointmentId').val();
    var newDate = $('#rescheduleDate').val();
    var newTime = $('#rescheduleTime').val();
    var reason = $('#rescheduleReason').val();
    
    if (!newDate || !newTime) {
        Swal.fire('Error', 'Please select date and time', 'error');
        return;
    }
    
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: {
            action: 'reschedule',
            appointment_id: appointmentId,
            new_date: newDate,
            new_time: newTime,
            reason: reason
        },
        dataType: 'json',
        beforeSend: function() {
            Swal.fire({ title: 'Rescheduling...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        },
        success: function(response) {
            Swal.close();
            $('#rescheduleModal').modal('hide');
            if (response.success) {
                Swal.fire('Success', response.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.close();
            Swal.fire('Error', 'Failed to reschedule', 'error');
        }
    });
}

// Cancel appointment
function cancelAppointment(id) {
    $('#cancelAppointmentId').val(id);
    $('#cancelReason').val('');
    $('#cancelModal').modal('show');
}

function submitCancel() {
    var appointmentId = $('#cancelAppointmentId').val();
    var reason = $('#cancelReason').val();
    
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: {
            action: 'update_status',
            appointment_id: appointmentId,
            status: 'cancelled',
            reason: reason
        },
        dataType: 'json',
        beforeSend: function() {
            Swal.fire({ title: 'Cancelling...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        },
        success: function(response) {
            Swal.close();
            $('#cancelModal').modal('hide');
            if (response.success) {
                Swal.fire('Success', response.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.close();
            Swal.fire('Error', 'Failed to cancel', 'error');
        }
    });
}

// Delete appointment
function deleteAppointment(id) {
    Swal.fire({
        title: 'Delete Appointment?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'delete',
                    appointment_id: id
                },
                dataType: 'json',
                beforeSend: function() {
                    Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                },
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire('Deleted', response.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire('Error', 'Failed to delete', 'error');
                }
            });
        }
    });
}

// Helper functions
function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    var date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatTime(timeStr) {
    if (!timeStr) return 'N/A';
    var time = new Date('2000-01-01 ' + timeStr);
    return time.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
}
</script>

<?php include 'footer/footer.php'; ?>
</body>
</html>
<?php
// my-schedule.php - Staff Schedule Management
session_start();
require_once '../../includes/dbcon.php';

// Check if user is logged in and has staff/admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    header('Location: ../login.php');
    exit;
}

$staff_id = $_SESSION['user_id'];
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
    $staff_stmt->execute([$staff_id]);
    $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        // If staff record doesn't exist, create one
        $insert_stmt = $pdo->prepare("
            INSERT INTO staff (user_id, phone, specialization, joining_date) 
            VALUES (?, ?, 'General', CURDATE())
        ");
        $insert_stmt->execute([$staff_id]);
        
        // Fetch again
        $staff_stmt->execute([$staff_id]);
        $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $staff_db_id = $staff['id'] ?? 0;
    
    // Get unread notifications count for staff
    $notif_count_query = "
        SELECT COUNT(*) as count FROM staff_notifications 
        WHERE staff_id = ? AND is_read = 0
    ";
    $notif_count_stmt = $pdo->prepare($notif_count_query);
    $notif_count_stmt->execute([$staff_db_id]);
    $unread_count = $notif_count_stmt->fetch()['count'] ?? 0;
    
    // Get notifications for staff
    $notif_query = "
        SELECT * FROM staff_notifications 
        WHERE staff_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ";
    $notif_stmt = $pdo->prepare($notif_query);
    $notif_stmt->execute([$staff_db_id]);
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Staff schedule error: " . $e->getMessage());
    $staff = null;
    $staff_db_id = 0;
    $unread_count = 0;
    $notifications = [];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    try {
        $pdo = getPDOConnection();
        
        switch ($action) {
            case 'save_availability':
                $day_of_week = intval($_POST['day_of_week'] ?? 0);
                $start_time = $_POST['start_time'] ?? '09:00:00';
                $end_time = $_POST['end_time'] ?? '17:00:00';
                $is_available = isset($_POST['is_available']) ? intval($_POST['is_available']) : 0;
                
                // Check if exists
                $check_stmt = $pdo->prepare("
                    SELECT id FROM staff_availability 
                    WHERE staff_id = ? AND day_of_week = ?
                ");
                $check_stmt->execute([$staff_db_id, $day_of_week]);
                $exists = $check_stmt->fetch();
                
                if ($exists) {
                    $update_stmt = $pdo->prepare("
                        UPDATE staff_availability 
                        SET start_time = ?, end_time = ?, is_available = ?, updated_at = NOW()
                        WHERE staff_id = ? AND day_of_week = ?
                    ");
                    $update_stmt->execute([$start_time, $end_time, $is_available, $staff_db_id, $day_of_week]);
                    $response = ['success' => true, 'message' => 'Schedule updated successfully'];
                } else {
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO staff_availability (staff_id, day_of_week, start_time, end_time, is_available) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $insert_stmt->execute([$staff_db_id, $day_of_week, $start_time, $end_time, $is_available]);
                    $response = ['success' => true, 'message' => 'Schedule added successfully'];
                }
                break;
                
            case 'save_exception':
                $exception_date = $_POST['exception_date'] ?? '';
                $start_time = $_POST['start_time'] ?? null;
                $end_time = $_POST['end_time'] ?? null;
                $is_working_day = isset($_POST['is_working_day']) ? 1 : 0;
                $reason = $_POST['reason'] ?? '';
                
                if (empty($exception_date)) {
                    $response = ['success' => false, 'message' => 'Date is required'];
                    break;
                }
                
                // Check if exists
                $check_stmt = $pdo->prepare("
                    SELECT id FROM staff_schedule_exceptions 
                    WHERE staff_id = ? AND exception_date = ?
                ");
                $check_stmt->execute([$staff_db_id, $exception_date]);
                $exists = $check_stmt->fetch();
                
                if ($exists) {
                    $update_stmt = $pdo->prepare("
                        UPDATE staff_schedule_exceptions 
                        SET start_time = ?, end_time = ?, is_working_day = ?, reason = ?, updated_at = NOW()
                        WHERE staff_id = ? AND exception_date = ?
                    ");
                    $update_stmt->execute([$start_time, $end_time, $is_working_day, $reason, $staff_db_id, $exception_date]);
                    $response = ['success' => true, 'message' => 'Exception updated successfully'];
                } else {
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO staff_schedule_exceptions 
                        (staff_id, exception_date, start_time, end_time, is_working_day, reason) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $insert_stmt->execute([$staff_db_id, $exception_date, $start_time, $end_time, $is_working_day, $reason]);
                    $response = ['success' => true, 'message' => 'Exception added successfully'];
                }
                break;
                
            case 'delete_exception':
                $exception_id = intval($_POST['exception_id'] ?? 0);
                
                $delete_stmt = $pdo->prepare("
                    DELETE FROM staff_schedule_exceptions 
                    WHERE id = ? AND staff_id = ?
                ");
                $delete_stmt->execute([$exception_id, $staff_db_id]);
                $response = ['success' => true, 'message' => 'Exception deleted successfully'];
                break;
                
            case 'update_profile':
                $phone = $_POST['phone'] ?? '';
                $specialization = $_POST['specialization'] ?? '';
                $experience_years = intval($_POST['experience_years'] ?? 0);
                $bio = $_POST['bio'] ?? '';
                
                $update_stmt = $pdo->prepare("
                    UPDATE staff 
                    SET phone = ?, specialization = ?, experience_years = ?, bio = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$phone, $specialization, $experience_years, $bio, $staff_db_id]);
                $response = ['success' => true, 'message' => 'Profile updated successfully'];
                break;
                
            case 'mark_notification_read':
                $notification_id = intval($_POST['notification_id'] ?? 0);
                
                if ($notification_id) {
                    $update_stmt = $pdo->prepare("
                        UPDATE staff_notifications 
                        SET is_read = 1 
                        WHERE id = ? AND staff_id = ?
                    ");
                    $update_stmt->execute([$notification_id, $staff_db_id]);
                    $response = ['success' => true, 'message' => 'Notification marked as read'];
                } else {
                    // Mark all as read
                    $update_stmt = $pdo->prepare("
                        UPDATE staff_notifications 
                        SET is_read = 1 
                        WHERE staff_id = ? AND is_read = 0
                    ");
                    $update_stmt->execute([$staff_db_id]);
                    $response = ['success' => true, 'message' => 'All notifications marked as read'];
                }
                break;
        }
        
    } catch (PDOException $e) {
        error_log("Schedule action error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// Get staff availability
try {
    $pdo = getPDOConnection();
    
    $availability_query = "
        SELECT * FROM staff_availability 
        WHERE staff_id = ? 
        ORDER BY day_of_week
    ";
    $availability_stmt = $pdo->prepare($availability_query);
    $availability_stmt->execute([$staff_db_id]);
    $availability = $availability_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get exceptions
    $exceptions_query = "
        SELECT * FROM staff_schedule_exceptions 
        WHERE staff_id = ? 
        AND exception_date >= CURDATE() 
        ORDER BY exception_date
    ";
    $exceptions_stmt = $pdo->prepare($exceptions_query);
    $exceptions_stmt->execute([$staff_db_id]);
    $exceptions = $exceptions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get today's appointments
    $today_appointments_query = "
        SELECT a.*, s.name as service_name, 
               r.fName as customer_name, r.lName as customer_lname
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg r ON a.user_id = r.regID
        WHERE a.staff_id = ? AND a.appointment_date = CURDATE()
        AND a.status IN ('pending', 'confirmed')
        ORDER BY a.appointment_time
    ";
    $today_appointments_stmt = $pdo->prepare($today_appointments_query);
    $today_appointments_stmt->execute([$staff_db_id]);
    $today_appointments = $today_appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming appointments (next 7 days)
    $upcoming_query = "
        SELECT a.*, s.name as service_name, 
               r.fName as customer_name, r.lName as customer_lname
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg r ON a.user_id = r.regID
        WHERE a.staff_id = ? 
        AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND a.status IN ('pending', 'confirmed')
        ORDER BY a.appointment_date, a.appointment_time
    ";
    $upcoming_stmt = $pdo->prepare($upcoming_query);
    $upcoming_stmt->execute([$staff_db_id]);
    $upcoming_appointments = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Staff schedule data error: " . $e->getMessage());
    $availability = [];
    $exceptions = [];
    $today_appointments = [];
    $upcoming_appointments = [];
}

// Create a lookup array for availability by day
$availability_lookup = [];
foreach ($availability as $avail) {
    $availability_lookup[$avail['day_of_week']] = $avail;
}

$days = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday'
];

// Include staff header
include 'header/header-staff.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Header -->
    <div class="dashboard-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="welcome-text">
                    <h1><i class="fas fa-calendar-alt me-2"></i>My Schedule</h1>
                    <p class="lead mb-0">Manage your working hours and view appointments</p>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <span class="badge bg-light text-dark p-2 me-2">
                    <i class="fas fa-user me-2"></i>
                    <?php echo htmlspecialchars($staff_name); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(111, 66, 193, 0.12); color: #6f42c1;">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo count($today_appointments); ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(40, 167, 69, 0.12); color: #28a745;">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo count($upcoming_appointments); ?></div>
                    <div class="stat-label">This Week</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(23, 162, 184, 0.12); color: #17a2b8;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo count(array_filter($availability, function($a) { return $a['is_available'] == 1; })); ?></div>
                    <div class="stat-label">Working Days</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.12); color: #ffc107;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo count($exceptions); ?></div>
                    <div class="stat-label">Exceptions</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-6">
            <!-- Weekly Schedule -->
            <div class="card schedule-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clock me-2 text-primary"></i>Weekly Schedule</h5>
                    <button class="btn btn-sm btn-outline-danger" onclick="resetSchedule()">
                        <i class="fas fa-undo me-1"></i>Reset
                    </button>
                </div>
                <div class="card-body">
                    <div id="weeklySchedule">
                        <?php foreach ($days as $day_num => $day_name): 
                            $day_data = $availability_lookup[$day_num] ?? null;
                            $is_available = $day_data ? (int)$day_data['is_available'] : 0;
                            $start_time = $day_data ? substr($day_data['start_time'], 0, 5) : '09:00';
                            $end_time = $day_data ? substr($day_data['end_time'], 0, 5) : '17:00';
                        ?>
                        <div class="day-schedule <?php echo $is_available ? '' : 'off'; ?>" data-day="<?php echo $day_num; ?>" data-available="<?php echo $is_available; ?>">
                            <div class="day-name">
                                <i class="fas fa-<?php echo $is_available ? 'check-circle text-success' : 'times-circle text-danger'; ?> me-2"></i>
                                <?php echo $day_name; ?>
                            </div>
                            <div class="time-slot">
                                <input type="time" class="form-control form-control-sm time-slot-input start-time" 
                                       value="<?php echo $start_time; ?>" <?php echo $is_available ? '' : 'disabled'; ?>>
                                <span class="mx-1">to</span>
                                <input type="time" class="form-control form-control-sm time-slot-input end-time" 
                                       value="<?php echo $end_time; ?>" <?php echo $is_available ? '' : 'disabled'; ?>>
                            </div>
                            <div class="status-badge">
                                <div class="form-check form-switch">
                                    <input class="form-check-input availability-toggle" type="checkbox" 
                                           data-day="<?php echo $day_num; ?>"
                                           <?php echo $is_available ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <button class="btn btn-primary" id="saveScheduleBtn">
                            <i class="fas fa-save me-2"></i>Save Schedule
                        </button>
                    </div>
                </div>
            </div>

            <!-- Update Profile -->
            <div class="card schedule-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2 text-primary"></i>Update Profile</h5>
                </div>
                <div class="card-body">
                    <form id="profileForm" onsubmit="return updateProfile(event)">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($staff['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Specialization</label>
                                <input type="text" class="form-control" name="specialization" 
                                       value="<?php echo htmlspecialchars($staff['specialization'] ?? 'General'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Experience (Years)</label>
                                <input type="number" class="form-control" name="experience_years" 
                                       value="<?php echo htmlspecialchars($staff['experience_years'] ?? 0); ?>">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Bio / About</label>
                                <textarea class="form-control" name="bio" rows="2"><?php echo htmlspecialchars($staff['bio'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-6">
            <!-- Today's Appointments -->
            <div class="card schedule-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-day me-2 text-success"></i>Today's Appointments</h5>
                    <span class="badge bg-primary"><?php echo count($today_appointments); ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($today_appointments)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-check fa-3x mb-3 d-block text-muted"></i>
                        <p class="mb-0">No appointments scheduled for today</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($today_appointments as $apt): ?>
                        <div class="appointment-item <?php echo $apt['status']; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="time"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></span>
                                    <span class="customer"><?php echo htmlspecialchars($apt['customer_name'] . ' ' . ($apt['customer_lname'] ?? '')); ?></span>
                                    <br>
                                    <span class="service"><?php echo htmlspecialchars($apt['service_name']); ?></span>
                                </div>
                                <span class="badge bg-<?php echo $apt['status'] === 'pending' ? 'warning' : 'info'; ?>">
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="card schedule-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-week me-2 text-info"></i>This Week's Appointments</h5>
                    <span class="badge bg-primary"><?php echo count($upcoming_appointments); ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_appointments)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-alt fa-3x mb-3 d-block text-muted"></i>
                        <p class="mb-0">No upcoming appointments this week</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_appointments as $apt): ?>
                        <div class="appointment-item <?php echo $apt['status']; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="time"><?php echo date('D, M d', strtotime($apt['appointment_date'])); ?></span>
                                    <span class="time ms-2"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></span>
                                    <br>
                                    <span class="customer"><?php echo htmlspecialchars($apt['customer_name'] . ' ' . ($apt['customer_lname'] ?? '')); ?></span>
                                    <span class="service ms-2">- <?php echo htmlspecialchars($apt['service_name']); ?></span>
                                </div>
                                <span class="badge bg-<?php echo $apt['status'] === 'pending' ? 'warning' : 'info'; ?>">
                                    <?php echo ucfirst($apt['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Exceptions -->
            <div class="card schedule-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Schedule Exceptions</h5>
                    <button class="btn btn-sm btn-primary" onclick="addException()">
                        <i class="fas fa-plus me-1"></i>Add
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($exceptions)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-check-circle fa-3x mb-3 d-block text-success"></i>
                        <p class="mb-0">No schedule exceptions</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($exceptions as $exception): ?>
                        <div class="exception-item <?php echo $exception['is_working_day'] ? 'working' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="date"><?php echo date('D, M d, Y', strtotime($exception['exception_date'])); ?></span>
                                    <br>
                                    <small>
                                        <?php if ($exception['is_working_day']): ?>
                                            <span class="text-success">Working Day</span>
                                            <?php if ($exception['start_time'] && $exception['end_time']): ?>
                                                (<?php echo substr($exception['start_time'], 0, 5); ?> - <?php echo substr($exception['end_time'], 0, 5); ?>)
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-danger">Day Off</span>
                                        <?php endif; ?>
                                        <?php if ($exception['reason']): ?>
                                            - <?php echo htmlspecialchars($exception['reason']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <button class="btn btn-sm btn-danger" onclick="deleteException(<?php echo $exception['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
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

<style>
/* Schedule Page Styles */
.schedule-card {
    margin-bottom: 25px;
    border: none;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    border-radius: 16px;
}

.schedule-card .card-header {
    background: white;
    border-bottom: 2px solid #f0f0f0;
    border-radius: 16px 16px 0 0;
    padding: 20px 25px;
}

.schedule-card .card-body {
    padding: 25px;
}

/* Day Schedule */
.day-schedule {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    margin-bottom: 8px;
    border-radius: 10px;
    background: #f8f9fa;
    transition: all 0.3s;
}

.day-schedule:hover {
    background: #e9ecef;
}

.day-schedule .day-name {
    width: 120px;
    font-weight: 600;
    color: #333;
}

.day-schedule .time-slot {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
}

.day-schedule .time-slot-input {
    max-width: 120px;
    display: inline-block;
}

.day-schedule .status-badge {
    margin-left: auto;
}

.day-schedule.off .day-name { color: #dc3545; }
.day-schedule.off .time-slot { color: #dc3545; }

.day-schedule .form-check-input:checked {
    background-color: #6f42c1;
    border-color: #6f42c1;
}

/* Appointment Item */
.appointment-item {
    padding: 12px 15px;
    margin-bottom: 8px;
    border-radius: 10px;
    border-left: 4px solid #6f42c1;
    background: #f8f9fa;
    transition: all 0.3s;
}

.appointment-item:hover {
    background: #e9ecef;
}

.appointment-item.pending { border-left-color: #ffc107; }
.appointment-item.confirmed { border-left-color: #17a2b8; }

.appointment-item .time {
    font-weight: 600;
    color: #6f42c1;
    font-size: 14px;
}

.appointment-item .customer {
    font-weight: 500;
    color: #333;
}

.appointment-item .service {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Exception Item */
.exception-item {
    padding: 12px 15px;
    margin-bottom: 8px;
    border-radius: 10px;
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    transition: all 0.3s;
}

.exception-item:hover {
    transform: translateX(5px);
}

.exception-item.working { 
    background: #d4edda; 
    border-left-color: #28a745;
}

.exception-item .date {
    font-weight: 600;
    color: #333;
}

/* Stat Cards */
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px 25px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.stat-card .stat-icon {
    width: 55px;
    height: 55px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.stat-card .stat-details {
    flex: 1;
}

.stat-card .stat-value {
    font-size: 26px;
    font-weight: 700;
    color: #343a40;
    line-height: 1.2;
}

.stat-card .stat-label {
    color: #6c757d;
    font-size: 13px;
    margin-top: 2px;
}

/* Dashboard Header */
.dashboard-header {
    background: white;
    border-radius: 16px;
    padding: 25px 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    border-left: 5px solid #6f42c1;
}

.welcome-text h1 {
    color: #6f42c1;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .day-schedule {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .day-schedule .day-name {
        width: 100%;
    }
    
    .day-schedule .time-slot {
        width: 100%;
        flex-wrap: wrap;
    }
    
    .day-schedule .time-slot-input {
        max-width: 100px;
    }
    
    .day-schedule .status-badge {
        margin-left: 0;
    }
    
    .stat-card .stat-value {
        font-size: 20px;
    }
}
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Store the saved availability data from PHP
var savedAvailability = <?php echo json_encode($availability_lookup); ?>;
var unreadCount = <?php echo $unread_count; ?>;

console.log('Saved Availability Data:', savedAvailability);
console.log('Unread Notifications:', unreadCount);

// Function to apply saved schedule
function applySavedSchedule() {
    console.log('Applying saved schedule...');
    
    // Loop through each day (0-6)
    for (var day = 0; day <= 6; day++) {
        var dayData = savedAvailability[day];
        
        // Find the day row
        var dayRow = document.querySelector('.day-schedule[data-day="' + day + '"]');
        if (!dayRow) {
            console.log('Day row not found for day:', day);
            continue;
        }
        
        // Get the saved availability value (0 or 1)
        var isAvailable = dayData ? parseInt(dayData.is_available) : 0;
        
        // Set data-available attribute
        dayRow.setAttribute('data-available', isAvailable);
        
        // Get saved times or use defaults
        var startTime = dayData ? dayData.start_time.substring(0, 5) : '09:00';
        var endTime = dayData ? dayData.end_time.substring(0, 5) : '17:00';
        
        // Update toggle checkbox - TRUE if isAvailable = 1, FALSE if isAvailable = 0
        var toggle = dayRow.querySelector('.availability-toggle');
        if (toggle) {
            toggle.checked = (isAvailable === 1);
            console.log('Day ' + day + ' toggle set to: ' + toggle.checked + ' (isAvailable=' + isAvailable + ')');
        }
        
        // Update time inputs
        var startInput = dayRow.querySelector('.start-time');
        var endInput = dayRow.querySelector('.end-time');
        
        if (startInput) {
            startInput.value = startTime;
            startInput.disabled = (isAvailable !== 1);
        }
        if (endInput) {
            endInput.value = endTime;
            endInput.disabled = (isAvailable !== 1);
        }
        
        // Update UI state (off class and icon)
        if (isAvailable === 1) {
            dayRow.classList.remove('off');
            var icon = dayRow.querySelector('.fa-times-circle');
            if (icon) {
                icon.className = 'fas fa-check-circle text-success';
            }
        } else {
            dayRow.classList.add('off');
            var icon = dayRow.querySelector('.fa-check-circle');
            if (icon) {
                icon.className = 'fas fa-times-circle text-danger';
            }
        }
        
        console.log('Applied day ' + day + ': available=' + isAvailable + ' (checked=' + (isAvailable === 1) + '), start=' + startTime + ', end=' + endTime);
    }
}

// Show notifications modal
function showNotifications() {
    // Create notification list HTML
    var notificationsHtml = '';
    var notifList = <?php echo json_encode($notifications); ?>;
    
    if (notifList && notifList.length > 0) {
        notificationsHtml = notifList.map(function(notif) {
            var isRead = parseInt(notif.is_read) === 1;
            var timeAgo = getTimeAgo(notif.created_at);
            return `
                <div class="notification-item ${isRead ? 'read' : 'unread'}" data-id="${notif.id}">
                    <div class="notification-content">
                        <div class="notification-title">${notif.title || 'Notification'}</div>
                        <div class="notification-message">${notif.message}</div>
                        <div class="notification-time">${timeAgo}</div>
                    </div>
                    ${!isRead ? `<button class="btn-mark-read" onclick="markNotificationRead(${notif.id})"><i class="fas fa-check"></i></button>` : ''}
                </div>
            `;
        }).join('');
    } else {
        notificationsHtml = `
            <div class="notification-empty">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">No notifications</p>
            </div>
        `;
    }
    
    Swal.fire({
        title: '<i class="fas fa-bell me-2"></i> Notifications',
        html: `
            <div class="notifications-container">
                <div class="notifications-header">
                    <span class="notification-count">${unreadCount} unread</span>
                    ${unreadCount > 0 ? `<button class="btn btn-sm btn-link mark-all-read" onclick="markAllNotificationsRead()">Mark all as read</button>` : ''}
                </div>
                <div class="notifications-list">
                    ${notificationsHtml}
                </div>
            </div>
        `,
        showCloseButton: true,
        confirmButtonText: 'Close',
        confirmButtonColor: '#6f42c1',
        width: '450px',
        customClass: {
            popup: 'notification-modal',
            htmlContainer: 'p-0'
        }
    });
}

// Get time ago string
function getTimeAgo(dateString) {
    var date = new Date(dateString);
    var now = new Date();
    var diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
    return date.toLocaleDateString();
}

// Mark notification as read
function markNotificationRead(notificationId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'mark_notification_read',
            notification_id: notificationId
        })
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(response) {
        if (response.success) {
            unreadCount--;
            // Update notification badge
            var badge = document.querySelector('.notification-badge');
            if (badge) {
                if (unreadCount > 0) {
                    badge.textContent = unreadCount;
                } else {
                    badge.style.display = 'none';
                }
            }
            // Refresh notifications
            showNotifications();
        }
    })
    .catch(function(error) {
        console.error('Error marking notification as read:', error);
    });
}

// Mark all notifications as read
function markAllNotificationsRead() {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'mark_notification_read',
            notification_id: 0
        })
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(response) {
        if (response.success) {
            unreadCount = 0;
            var badge = document.querySelector('.notification-badge');
            if (badge) {
                badge.style.display = 'none';
            }
            showNotifications();
        }
    })
    .catch(function(error) {
        console.error('Error marking all notifications as read:', error);
    });
}

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded - applying saved schedule');
    
    // Apply saved availability to the UI
    applySavedSchedule();
    
    // Initialize toggle handlers
    initToggleHandlers();
    
    // Add save button event listener
    var saveBtn = document.getElementById('saveScheduleBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            saveSchedule();
        });
    }
});

// Initialize toggle handlers
function initToggleHandlers() {
    // Toggle availability - live update visual
    document.querySelectorAll('.availability-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var day = this.dataset.day;
            var isChecked = this.checked;
            var dayRow = this.closest('.day-schedule');
            var startTime = dayRow.querySelector('.start-time');
            var endTime = dayRow.querySelector('.end-time');
            
            // Update data-available attribute (1 for checked, 0 for unchecked)
            dayRow.setAttribute('data-available', isChecked ? 1 : 0);
            
            if (isChecked) {
                dayRow.classList.remove('off');
                if (startTime) startTime.disabled = false;
                if (endTime) endTime.disabled = false;
                var icon = dayRow.querySelector('.fa-times-circle');
                if (icon) {
                    icon.className = 'fas fa-check-circle text-success';
                }
            } else {
                dayRow.classList.add('off');
                if (startTime) startTime.disabled = true;
                if (endTime) endTime.disabled = true;
                var icon = dayRow.querySelector('.fa-check-circle');
                if (icon) {
                    icon.className = 'fas fa-times-circle text-danger';
                }
            }
            
            console.log('Day ' + day + ' toggle changed to: ' + isChecked + ' (data-available=' + dayRow.getAttribute('data-available') + ')');
        });
    });
    
    // Toggle time fields based on exception type
    var exceptionType = document.getElementById('exceptionType');
    if (exceptionType) {
        exceptionType.addEventListener('change', function() {
            if (this.value === '1') {
                document.getElementById('exceptionTimeFields').style.display = 'block';
            } else {
                document.getElementById('exceptionTimeFields').style.display = 'none';
            }
        });
    }
}

// Save schedule
function saveSchedule() {
    console.log('saveSchedule function called');
    
    if (typeof Swal === 'undefined') {
        alert('SweetAlert2 is not loaded. Please refresh the page.');
        return;
    }
    
    var days = [];
    var hasError = false;
    
    document.querySelectorAll('.day-schedule').forEach(function(dayRow) {
        var day = parseInt(dayRow.dataset.day);
        var isAvailable = dayRow.querySelector('.availability-toggle').checked ? 1 : 0;
        var startTime = dayRow.querySelector('.start-time').value;
        var endTime = dayRow.querySelector('.end-time').value;
        
        // Validate: if working day (isAvailable=1), time must be set
        if (isAvailable === 1 && (!startTime || !endTime)) {
            hasError = true;
            dayRow.classList.add('border', 'border-danger');
            return;
        } else {
            dayRow.classList.remove('border', 'border-danger');
        }
        
        days.push({
            day_of_week: day,
            is_available: isAvailable,
            start_time: startTime ? startTime + ':00' : '09:00:00',
            end_time: endTime ? endTime + ':00' : '17:00:00'
        });
        
        console.log('Day ' + day + ': isAvailable=' + isAvailable + ', start=' + startTime + ', end=' + endTime);
    });
    
    if (hasError) {
        Swal.fire('Error', 'Please set time for all working days.', 'error');
        return;
    }
    
    if (days.length === 0) {
        Swal.fire('Error', 'No schedule data to save.', 'error');
        return;
    }
    
    console.log('Saving days:', days);
    
    Swal.fire({
        title: 'Saving Schedule...',
        allowOutsideClick: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });
    
    var completed = 0;
    var total = days.length;
    var successCount = 0;
    var errorCount = 0;
    
    days.forEach(function(dayData) {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'save_availability',
                day_of_week: dayData.day_of_week,
                start_time: dayData.start_time,
                end_time: dayData.end_time,
                is_available: dayData.is_available
            })
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(response) {
            console.log('Response for day ' + dayData.day_of_week + ':', response);
            completed++;
            if (response.success) {
                successCount++;
            } else {
                errorCount++;
                console.error('Error saving day ' + dayData.day_of_week + ':', response.message);
            }
            if (completed === total) {
                Swal.close();
                if (errorCount > 0 && successCount > 0) {
                    Swal.fire('Partial Success', successCount + ' days saved, ' + errorCount + ' failed.', 'warning');
                } else if (errorCount === total) {
                    Swal.fire('Error', 'Failed to save schedule. Please try again.', 'error');
                } else {
                    Swal.fire('Success!', 'Your schedule has been saved successfully.', 'success');
                }
            }
        })
        .catch(function(error) {
            console.error('Fetch Error for day ' + dayData.day_of_week + ':', error);
            completed++;
            errorCount++;
            if (completed === total) {
                Swal.close();
                if (errorCount === total) {
                    Swal.fire('Error', 'Failed to save schedule. Please check console for details.', 'error');
                } else {
                    Swal.fire('Partial Success', successCount + ' days saved, ' + errorCount + ' failed.', 'warning');
                }
            }
        });
    });
}

// Reset schedule to default
function resetSchedule() {
    if (typeof Swal === 'undefined') {
        alert('SweetAlert2 is not loaded. Please refresh the page.');
        return;
    }
    
    Swal.fire({
        title: 'Reset Schedule?',
        text: 'This will set all days to default working hours (9:00 AM - 5:00 PM).',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, reset',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545'
    }).then(function(result) {
        if (result.isConfirmed) {
            document.querySelectorAll('.day-schedule').forEach(function(dayRow) {
                var toggle = dayRow.querySelector('.availability-toggle');
                var startTime = dayRow.querySelector('.start-time');
                var endTime = dayRow.querySelector('.end-time');
                
                if (toggle) toggle.checked = true;
                if (startTime) startTime.value = '09:00';
                if (endTime) endTime.value = '17:00';
                
                // Trigger change event to update UI
                if (toggle) {
                    var event = new Event('change');
                    toggle.dispatchEvent(event);
                }
            });
            
            Swal.fire({
                title: 'Saving...',
                allowOutsideClick: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
            
            setTimeout(function() {
                saveSchedule();
            }, 500);
        }
    });
}

// Add exception
function addException() {
    var tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    var dateStr = tomorrow.toISOString().split('T')[0];
    document.getElementById('exceptionDate').value = dateStr;
    document.getElementById('exceptionType').value = '1';
    document.getElementById('exceptionStart').value = '09:00';
    document.getElementById('exceptionEnd').value = '17:00';
    document.getElementById('exceptionReason').value = '';
    
    var modal = new bootstrap.Modal(document.getElementById('exceptionModal'));
    modal.show();
}

// Save exception
function saveException() {
    var form = document.getElementById('exceptionForm');
    var formData = new FormData(form);
    
    var data = {
        action: 'save_exception',
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
        didOpen: function() {
            Swal.showLoading();
        }
    });
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(data)
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(response) {
        Swal.close();
        var modal = bootstrap.Modal.getInstance(document.getElementById('exceptionModal'));
        if (modal) modal.hide();
        
        if (response.success) {
            Swal.fire('Success!', response.message, 'success').then(function() {
                location.reload();
            });
        } else {
            Swal.fire('Error', response.message || 'Failed to save exception', 'error');
        }
    })
    .catch(function(error) {
        Swal.close();
        Swal.fire('Error', 'Failed to save exception. Please try again.', 'error');
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
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'delete_exception',
                    exception_id: exceptionId
                })
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire('Deleted!', response.message, 'success').then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message || 'Failed to delete exception', 'error');
                }
            })
            .catch(function(error) {
                Swal.close();
                Swal.fire('Error', 'Failed to delete exception. Please try again.', 'error');
            });
        }
    });
}

// Update profile
function updateProfile(event) {
    event.preventDefault();
    
    var form = document.getElementById('profileForm');
    var formData = new FormData(form);
    
    var data = {
        action: 'update_profile',
        phone: formData.get('phone') || '',
        specialization: formData.get('specialization') || 'General',
        experience_years: parseInt(formData.get('experience_years')) || 0,
        bio: formData.get('bio') || ''
    };
    
    Swal.fire({
        title: 'Updating Profile...',
        allowOutsideClick: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(data)
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(response) {
        Swal.close();
        if (response.success) {
            Swal.fire('Success!', response.message, 'success');
        } else {
            Swal.fire('Error', response.message || 'Failed to update profile', 'error');
        }
    })
    .catch(function(error) {
        Swal.close();
        Swal.fire('Error', 'Failed to update profile. Please try again.', 'error');
    });
    
    return false;
}

// CSS for notifications modal
var notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    .notification-modal .swal2-popup {
        padding: 0;
    }
    .notification-modal .swal2-html-container {
        padding: 0;
        margin: 0;
    }
    .notifications-container {
        max-height: 400px;
        overflow: hidden;
    }
    .notifications-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }
    .notification-count {
        font-size: 14px;
        color: #6c757d;
    }
    .mark-all-read {
        font-size: 13px;
        color: #6f42c1;
        text-decoration: none;
        cursor: pointer;
        background: none;
        border: none;
    }
    .mark-all-read:hover {
        text-decoration: underline;
    }
    .notifications-list {
        max-height: 320px;
        overflow-y: auto;
        padding: 5px 0;
    }
    .notification-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 20px;
        border-bottom: 1px solid #f8f9fa;
        transition: background 0.2s;
    }
    .notification-item:hover {
        background: #f8f9fa;
    }
    .notification-item.unread {
        background: #f0e8ff;
        border-left: 3px solid #6f42c1;
    }
    .notification-item.unread:hover {
        background: #e8d8ff;
    }
    .notification-content {
        flex: 1;
        margin-right: 10px;
    }
    .notification-title {
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }
    .notification-message {
        color: #6c757d;
        font-size: 13px;
        margin-top: 2px;
    }
    .notification-time {
        color: #adb5bd;
        font-size: 11px;
        margin-top: 4px;
    }
    .btn-mark-read {
        background: none;
        border: none;
        color: #28a745;
        cursor: pointer;
        padding: 5px 8px;
        border-radius: 50%;
        transition: all 0.2s;
    }
    .btn-mark-read:hover {
        background: #d4edda;
    }
    .notification-empty {
        text-align: center;
        padding: 40px 20px;
    }
    .notification-empty i {
        opacity: 0.5;
    }
    .notification-empty p {
        margin: 0;
    }
`;
document.head.appendChild(notificationStyles);
</script>
</body>
</html>
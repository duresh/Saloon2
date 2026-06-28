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

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$appointment_id) {
    header('Location: manage-appointments.php?error=invalid_id');
    exit();
}

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
    
    // Get appointment details with all related information
    $appointment_query = "
        SELECT 
            a.*,
            s.id as service_id,
            s.name as service_name,
            s.price as service_price,
            s.duration as service_duration,
            s.category as service_category,
            u.fName as customer_name,
            u.email as customer_email,
            u.contactNo as customer_phone,
            u.regID as user_id,
            st.id as staff_id,
            r.fName as staff_name,
            st.specialization as staff_specialization,
            st.experience_years as staff_experience
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN reg u ON a.user_id = u.regID
        LEFT JOIN staff st ON a.staff_id = st.id
        LEFT JOIN reg r ON st.user_id = r.regID
        WHERE a.id = ?
    ";
    
    $appointment_stmt = $pdo->prepare($appointment_query);
    $appointment_stmt->execute([$appointment_id]);
    $appointment = $appointment_stmt->fetch();
    
    if (!$appointment) {
        header('Location: manage-appointments.php?error=not_found');
        exit();
    }
    
    // Get all active services
    $services_query = "SELECT * FROM services WHERE status = 'active' ORDER BY category, name";
    $services_stmt = $pdo->query($services_query);
    $services = $services_stmt->fetchAll();
    
    // Get all staff members
    $staff_query = "
        SELECT s.*, r.fName as staff_name 
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE r.cStatus = 1
        ORDER BY s.specialization, r.fName
    ";
    $staff_stmt = $pdo->query($staff_query);
    $all_staff = $staff_stmt->fetchAll();
    
    // Generate time slots (9 AM to 8 PM, 30-minute intervals)
    $time_slots = [];
    $start_time = strtotime('09:00');
    $end_time = strtotime('20:00');
    $interval = 30 * 60;
    
    for ($time = $start_time; $time <= $end_time; $time += $interval) {
        $time_slots[] = date('H:i', $time);
    }
    
    // Get booked slots for future dates (excluding current appointment)
    $booked_slots = [];
    $booked_query = "
        SELECT appointment_date, appointment_time 
        FROM appointments 
        WHERE appointment_date >= CURDATE() 
        AND id != ?
        AND status IN ('pending', 'confirmed')
    ";
    $booked_stmt = $pdo->prepare($booked_query);
    $booked_stmt->execute([$appointment_id]);
    $booked_results = $booked_stmt->fetchAll();
    
    foreach ($booked_results as $slot) {
        $booked_slots[$slot['appointment_date'] . ' ' . $slot['appointment_time']] = true;
    }
    
    // Handle form submission
    // Handle form submission - UPDATED VERSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_service_id = $_POST['service_id'] ?? $appointment['service_id'];
    $new_staff_id = $_POST['staff_id'] ?? $appointment['staff_id'];
    $new_date = $_POST['appointment_date'] ?? '';
    $new_time = $_POST['appointment_time'] ?? '';
    $notes = $_POST['notes'] ?? $appointment['notes'];
    $reschedule_reason = $_POST['reschedule_reason'] ?? '';
    
    $errors = [];
    
    // Validate inputs
    if (empty($new_date)) {
        $errors[] = 'Please select a new date';
    }
    
    if (empty($new_time)) {
        $errors[] = 'Please select a new time';
    }
    
    if (strtotime($new_date) < strtotime('today')) {
        $errors[] = 'Appointment date cannot be in the past';
    }
    
    // Check if there are actual changes
    $has_changes = (
        $new_service_id != $appointment['service_id'] ||
        $new_staff_id != $appointment['staff_id'] ||
        $new_date != $appointment['appointment_date'] ||
        $new_time != $appointment['appointment_time'] ||
        $notes != $appointment['notes']
    );
    
    if (!$has_changes) {
        $errors[] = 'No changes detected. Please modify at least one field.';
    }
    
    // Check slot availability
    $slot_key = $new_date . ' ' . $new_time;
    if (isset($booked_slots[$slot_key]) && 
        ($new_date != $appointment['appointment_date'] || $new_time != $appointment['appointment_time'])) {
        $errors[] = 'Selected time slot is already booked';
    }
    
    // Check staff availability if changed
    if ($new_staff_id && $new_staff_id != $appointment['staff_id']) {
        $duration = getServiceDuration($pdo, $new_service_id);
        if (!isStaffAvailable($pdo, $new_staff_id, $new_date, $new_time, $duration, $appointment_id)) {
            $errors[] = 'Selected staff member is not available at this time';
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Store old values for logging
            $old_values = [
                'service_id' => $appointment['service_id'],
                'staff_id' => $appointment['staff_id'],
                'date' => $appointment['appointment_date'],
                'time' => $appointment['appointment_time'],
                'notes' => $appointment['notes']
            ];
            
            // Update appointment
            $update_query = "
                UPDATE appointments 
                SET service_id = ?,
                    staff_id = ?,
                    appointment_date = ?,
                    appointment_time = ?,
                    notes = ?,
                    status = 'pending',
                    modified_at = NOW(),
                    reschedule_count = reschedule_count + 1,
                    last_reschedule_at = NOW()
                WHERE id = ?
            ";
            
            $update_stmt = $pdo->prepare($update_query);
            $update_result = $update_stmt->execute([
                $new_service_id,
                $new_staff_id ?: null,
                $new_date,
                $new_time,
                $notes,
                $appointment_id
            ]);
            
            if ($update_result) {
                // 1. Insert into appointment_reschedules
                $reschedule_query = "
                    INSERT INTO appointment_reschedules (
                        appointment_id, 
                        user_id, 
                        old_service_id, 
                        new_service_id,
                        old_staff_id, 
                        new_staff_id,
                        old_date, 
                        new_date,
                        old_time, 
                        new_time,
                        reschedule_reason, 
                        rescheduled_by,
                        ip_address,
                        user_agent
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $reschedule_stmt = $pdo->prepare($reschedule_query);
                $reschedule_result = $reschedule_stmt->execute([
                    $appointment_id,
                    $admin_id,
                    $old_values['service_id'],
                    $new_service_id,
                    $old_values['staff_id'],
                    $new_staff_id ?: null,
                    $old_values['date'],
                    $new_date,
                    $old_values['time'],
                    $new_time,
                    $reschedule_reason,
                    'admin',
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
                
                if ($reschedule_result) {
                    $reschedule_id = $pdo->lastInsertId();
                    
                    // 2. Insert into reschedule_logs for each changed field
                    $log_query = "
                        INSERT INTO reschedule_logs (
                            reschedule_id, 
                            action, 
                            field_name, 
                            old_value, 
                            new_value,
                            changed_at
                        ) VALUES (?, 'change', ?, ?, ?, NOW())
                    ";
                    $log_stmt = $pdo->prepare($log_query);
                    
                    // Log service change
                    if ($old_values['service_id'] != $new_service_id) {
                        $old_service_name = getServiceName($pdo, $old_values['service_id']);
                        $new_service_name = getServiceName($pdo, $new_service_id);
                        $log_stmt->execute([
                            $reschedule_id,
                            'service',
                            $old_service_name,
                            $new_service_name
                        ]);
                    }
                    
                    // Log staff change
                    if ($old_values['staff_id'] != $new_staff_id) {
                        $old_staff_name = $old_values['staff_id'] ? getStaffName($pdo, $old_values['staff_id']) : 'Unassigned';
                        $new_staff_name = $new_staff_id ? getStaffName($pdo, $new_staff_id) : 'Unassigned';
                        $log_stmt->execute([
                            $reschedule_id,
                            'staff',
                            $old_staff_name,
                            $new_staff_name
                        ]);
                    }
                    
                    // Log date change
                    if ($old_values['date'] != $new_date) {
                        $log_stmt->execute([
                            $reschedule_id,
                            'date',
                            $old_values['date'],
                            $new_date
                        ]);
                    }
                    
                    // Log time change
                    if ($old_values['time'] != $new_time) {
                        $log_stmt->execute([
                            $reschedule_id,
                            'time',
                            $old_values['time'],
                            $new_time
                        ]);
                    }
                    
                    // Log notes change
                    if ($old_values['notes'] != $notes) {
                        $log_stmt->execute([
                            $reschedule_id,
                            'notes',
                            $old_values['notes'] ?? '',
                            $notes ?? ''
                        ]);
                    }
                }
                
                // 3. Check if we need to update staff_schedule_exceptions
                // If staff changed or date/time changed, we might need to handle exceptions
                if ($old_values['staff_id'] != $new_staff_id || 
                    $old_values['date'] != $new_date || 
                    $old_values['time'] != $new_time) {
                    
                    // Check if the new time falls outside regular working hours
                    if ($new_staff_id) {
                        $is_working_hours = checkWorkingHours($pdo, $new_staff_id, $new_date, $new_time, $appointment['service_duration']);
                        
                        if (!$is_working_hours) {
                            // Add to schedule exceptions
                            $exception_query = "
                                INSERT INTO staff_schedule_exceptions (
                                    staff_id,
                                    exception_date,
                                    start_time,
                                    end_time,
                                    is_working_day,
                                    reason,
                                    created_at
                                ) VALUES (?, ?, ?, ?, 1, 'Rescheduled appointment', NOW())
                                ON DUPLICATE KEY UPDATE
                                    start_time = VALUES(start_time),
                                    end_time = VALUES(end_time),
                                    updated_at = NOW()
                            ";
                            
                            $end_time = date('H:i:s', strtotime($new_time) + ($appointment['service_duration'] * 60));
                            
                            $exception_stmt = $pdo->prepare($exception_query);
                            $exception_stmt->execute([
                                $new_staff_id,
                                $new_date,
                                $new_time,
                                $end_time
                            ]);
                        }
                    }
                }
                
                $pdo->commit();
                
                $_SESSION['success'] = 'Appointment rescheduled successfully';
                header('Location: manage-appointments.php?rescheduled=1');
                exit();
                
            } else {
                $pdo->rollBack();
                $error = 'Failed to update appointment';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Reschedule error: ' . $e->getMessage());
            $error = 'Database error occurred. Please try again. Error: ' . $e->getMessage();
        }
    }
}
    
} catch (PDOException $e) {
    error_log('Reschedule page error: ' . $e->getMessage());
    $error = 'Unable to load appointment details';
}

// Helper function to check if time falls within working hours
function checkWorkingHours($pdo, $staff_id, $date, $time, $duration) {
    $day_of_week = date('w', strtotime($date)); // 0 = Sunday, 1 = Monday, etc.
    
    // Check regular availability
    $query = "
        SELECT start_time, end_time 
        FROM staff_availability 
        WHERE staff_id = ? AND day_of_week = ? AND is_available = 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$staff_id, $day_of_week]);
    $availability = $stmt->fetch();
    
    if (!$availability) {
        return false; // Not a working day
    }
    
    $appointment_start = strtotime($time);
    $appointment_end = $appointment_start + ($duration * 60);
    
    $work_start = strtotime($availability['start_time']);
    $work_end = strtotime($availability['end_time']);
    
    return ($appointment_start >= $work_start && $appointment_end <= $work_end);
}

// Helper function to get service name
function getServiceName($pdo, $service_id) {
    if (!$service_id) return 'Unknown';
    $stmt = $pdo->prepare("SELECT name FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $result = $stmt->fetch();
    return $result ? $result['name'] : 'Unknown';
}

// Helper function to get staff name
function getStaffName($pdo, $staff_id) {
    if (!$staff_id) return 'Unassigned';
    $stmt = $pdo->prepare("
        SELECT r.fName as staff_name 
        FROM staff s 
        JOIN reg r ON s.user_id = r.regID 
        WHERE s.id = ?
    ");
    $stmt->execute([$staff_id]);
    $result = $stmt->fetch();
    return $result ? $result['staff_name'] : 'Unknown';
}
// Helper functions
function isStaffAvailable($pdo, $staff_id, $date, $time, $duration, $exclude_appointment_id) {
    $start_time = strtotime($time);
    $end_time = $start_time + ($duration * 60);
    $end_time_str = date('H:i:s', $end_time);
    
    $query = "
        SELECT COUNT(*) as conflict_count
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.staff_id = ?
        AND a.appointment_date = ?
        AND a.id != ?
        AND a.status IN ('confirmed', 'pending')
        AND (
            (a.appointment_time <= ? AND ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) > ?)
            OR
            (? < ADDTIME(a.appointment_time, SEC_TO_TIME(s.duration * 60)) AND ? >= a.appointment_time)
        )
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$staff_id, $date, $exclude_appointment_id, $time, $time, $end_time_str, $end_time_str]);
    $result = $stmt->fetch();
    
    return $result['conflict_count'] == 0;
}

function getServiceDuration($pdo, $service_id) {
    $stmt = $pdo->prepare("SELECT duration FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $result = $stmt->fetch();
    return $result ? $result['duration'] : 30;
}

include 'header/header-admin.php';
?>

<!-- Page Header -->
<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-calendar-alt me-2" style="color: #6f42c1;"></i> Reschedule Appointment</h1>
            <p class="lead mb-0">Appointment #<?php echo $appointment_id; ?> - <?php echo htmlspecialchars($appointment['customer_name']); ?></p>
        </div>
        <div class="col-md-4 text-end">
            <a href="manage-appointments.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Appointments
            </a>
        </div>
    </div>
</div>

<!-- Messages -->
<?php if (isset($error)): ?>
<div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-custom">
    <i class="fas fa-exclamation-circle me-2"></i> Please fix the following errors:
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $err): ?>
        <li><?php echo $err; ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Current Appointment Card -->
<div class="current-appointment-card">
    <div class="row">
        <div class="col-12">
            <h4 class="card-title">
                <i class="fas fa-info-circle me-2 text-warning"></i>Current Appointment Details
            </h4>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-md-3">
            <div class="detail-box">
                <span class="detail-label">Customer</span>
                <span class="detail-value"><?php echo htmlspecialchars($appointment['customer_name']); ?></span>
                <small class="text-muted d-block"><?php echo htmlspecialchars($appointment['customer_phone']); ?></small>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="detail-box">
                <span class="detail-label">Service</span>
                <span class="detail-value"><?php echo htmlspecialchars($appointment['service_name']); ?></span>
                <small class="text-muted d-block"><?php echo htmlspecialchars($appointment['service_category'] ?? 'General'); ?></small>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="detail-box">
                <span class="detail-label">Date</span>
                <span class="detail-value"><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></span>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="detail-box">
                <span class="detail-label">Time</span>
                <span class="detail-value"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></span>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="detail-box">
                <span class="detail-label">Staff</span>
                <span class="detail-value">
                    <?php if (!empty($appointment['staff_name'])): ?>
                        <span class="staff-badge">
                            <i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($appointment['staff_name']); ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Not Assigned</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="row mt-2">
        <div class="col-md-3">
            <div class="detail-box">
                <span class="detail-label">Duration</span>
                <span class="detail-value"><?php echo $appointment['service_duration']; ?> minutes</span>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="detail-box">
                <span class="detail-label">Price</span>
                <span class="detail-value price">Rs: <?php echo number_format($appointment['service_price'], 2); ?></span>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="detail-box">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge <?php echo $appointment['status']; ?>">
                        <?php echo ucfirst($appointment['status']); ?>
                    </span>
                </span>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="detail-box">
                <span class="detail-label">Reschedule Count</span>
                <span class="detail-value"><?php echo $appointment['reschedule_count'] ?? 0; ?> times</span>
            </div>
        </div>
    </div>
    
    <?php if (!empty($appointment['notes'])): ?>
    <div class="row mt-3">
        <div class="col-12">
            <div class="notes-box">
                <strong><i class="fas fa-sticky-note me-2"></i>Notes:</strong>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Reschedule Form -->
<div class="reschedule-form-card">
    <div class="row">
        <div class="col-12">
            <h4 class="card-title">
                <i class="fas fa-edit me-2" style="color: #6f42c1;"></i>Reschedule Details
            </h4>
        </div>
    </div>
    
    <form method="POST" action="" id="rescheduleForm" class="mt-4">
        <!-- Service Selection -->
        <div class="form-section">
            <div class="section-header">
                <span class="step-number">1</span>
                <h5>Select Service (Optional)</h5>
            </div>
            <p class="text-muted mb-3">You can keep the same service or choose a different one.</p>
            
            <div class="service-grid">
                <?php foreach ($services as $service): ?>
                <div class="service-card <?php echo ($service['id'] == $appointment['service_id']) ? 'current' : ''; ?>" 
                     data-service-id="<?php echo $service['id']; ?>"
                     data-service-name="<?php echo htmlspecialchars($service['name']); ?>"
                     data-service-price="<?php echo $service['price']; ?>"
                     data-service-duration="<?php echo $service['duration']; ?>">
                    
                    <?php if ($service['id'] == $appointment['service_id']): ?>
                    <span class="current-badge">Current Service</span>
                    <?php endif; ?>
                    
                    <div class="service-category"><?php echo htmlspecialchars($service['category'] ?? 'General'); ?></div>
                    <h6><?php echo htmlspecialchars($service['name']); ?></h6>
                    
                    <div class="service-meta">
                        <span><i class="far fa-clock me-1"></i><?php echo $service['duration']; ?> mins</span>
                        <span class="service-price">Rs: <?php echo number_format($service['price'], 2); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="service_id" id="selectedService" value="<?php echo $appointment['service_id']; ?>">
        </div>
        
        <!-- Staff Selection -->
        <div class="form-section">
            <div class="section-header">
                <span class="step-number">2</span>
                <h5>Select Staff (Optional)</h5>
            </div>
            <p class="text-muted mb-3">Choose a different staff member or keep the current one.</p>
            
            <select class="form-select form-select-lg" name="staff_id" id="staffSelect">
                <option value="">-- Keep Current Staff (<?php echo $appointment['staff_name'] ?? 'Not Assigned'; ?>) --</option>
                <?php foreach ($all_staff as $staff): ?>
                <option value="<?php echo $staff['id']; ?>" 
                        <?php echo ($staff['id'] == $appointment['staff_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($staff['staff_name']); ?> 
                    (<?php echo htmlspecialchars($staff['specialization'] ?? 'General'); ?>) 
                    - <?php echo $staff['experience_years'] ?? 0; ?> years exp.
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Date and Time Selection -->
        <div class="form-section">
            <div class="section-header">
                <span class="step-number">3</span>
                <h5>Select New Date & Time</h5>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <label class="form-label fw-bold">Appointment Date</label>
                    <div class="date-picker-wrapper">
                        <i class="fas fa-calendar-alt input-icon"></i>
                        <input type="date" class="form-control form-control-lg" name="appointment_date" id="appointmentDate" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>"
                               value="<?php echo $appointment['appointment_date']; ?>">
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <label class="form-label fw-bold">Appointment Time</label>
                    <div class="time-slots-wrapper">
                        <div class="time-slots-grid" id="timeSlots">
                            <?php foreach ($time_slots as $time): ?>
                            <div class="time-slot <?php echo ($time == $appointment['appointment_time']) ? 'current' : ''; ?>" 
                                 data-time="<?php echo $time; ?>">
                                <?php echo date('g:i A', strtotime($time)); ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <input type="hidden" name="appointment_time" id="selectedTime" value="<?php echo $appointment['appointment_time']; ?>">
                </div>
            </div>
        </div>
        
        <!-- Additional Information -->
        <div class="form-section">
            <div class="section-header">
                <span class="step-number">4</span>
                <h5>Additional Information</h5>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Reschedule Reason</label>
                    <select class="form-select" name="reschedule_reason">
                        <option value="">Select a reason (optional)</option>
                        <option value="customer_request">Customer Request</option>
                        <option value="staff_unavailable">Staff Unavailable</option>
                        <option value="schedule_conflict">Schedule Conflict</option>
                        <option value="emergency">Emergency</option>
                        <option value="double_booking">Double Booking</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Additional Notes</label>
                    <textarea class="form-control" name="notes" rows="3" placeholder="Any additional information..."><?php echo htmlspecialchars($appointment['notes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <button type="button" class="btn btn-secondary btn-lg" onclick="window.location.href='manage-appointments.php'">
                <i class="fas fa-times me-2"></i>Cancel
            </button>
            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                <i class="fas fa-calendar-check me-2"></i>Confirm Reschedule
            </button>
        </div>
    </form>
</div>

<?php include 'footer/footer.php'; ?>

<script>
$(document).ready(function() {
    // Service selection
    $('.service-card').click(function() {
        $('.service-card').removeClass('selected');
        $(this).addClass('selected');
        $('#selectedService').val($(this).data('service-id'));
    });
    
    // Time slot selection
    $('.time-slot').click(function() {
        if (!$(this).hasClass('booked') && !$(this).hasClass('current')) {
            $('.time-slot').removeClass('selected');
            $(this).addClass('selected');
            $('#selectedTime').val($(this).data('time'));
        }
    });
    
    // Date change - check availability
    $('#appointmentDate').change(function() {
        checkAvailability($(this).val());
    });
    
    // Mark booked slots
    markBookedSlots();
    
    // Current service selected
    const currentService = '<?php echo $appointment['service_id']; ?>';
    if (currentService) {
        $(`.service-card[data-service-id="${currentService}"]`).addClass('selected');
    }
    
// Form submission with SweetAlert
$('#rescheduleForm').submit(function(e) {
    e.preventDefault();
    
    const selectedDate = $('#appointmentDate').val();
    const selectedTime = $('#selectedTime').val();
    const selectedService = $('#selectedService').val();
    const selectedStaff = $('#staffSelect').val();
    const newNotes = $('#notes').val();
    
    // Validate form
    if (!selectedDate || !selectedTime) {
        Swal.fire({
            icon: 'warning',
            title: 'Incomplete Form',
            text: 'Please select both date and time',
            confirmButtonColor: '#6f42c1',
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    // Check if time slot is still available
    const slotKey = selectedDate + ' ' + selectedTime;
    const bookedSlots = <?php echo json_encode($booked_slots); ?>;
    const currentDate = '<?php echo $appointment['appointment_date']; ?>';
    const currentTime = '<?php echo $appointment['appointment_time']; ?>';
    
    if (bookedSlots[slotKey] && (selectedDate != currentDate || selectedTime != currentTime)) {
        Swal.fire({
            icon: 'error',
            title: 'Time Slot Unavailable',
            text: 'This time slot has been taken. Please select another time.',
            confirmButtonColor: '#6f42c1',
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    // Check if any changes were made
    const currentService = '<?php echo $appointment['service_id']; ?>';
    const currentStaff = '<?php echo $appointment['staff_id'] ?? ''; ?>';
    const currentNotes = '<?php echo addslashes($appointment['notes'] ?? ''); ?>';
    
    const hasChanges = (
        selectedDate != currentDate ||
        selectedTime != currentTime ||
        selectedService != currentService ||
        selectedStaff != currentStaff ||
        newNotes != currentNotes
    );
    
    if (!hasChanges) {
        Swal.fire({
            icon: 'info',
            title: 'No Changes Detected',
            text: 'Please modify at least one field to reschedule.',
            confirmButtonColor: '#6f42c1',
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    // Show confirmation dialog
    Swal.fire({
        title: 'Confirm Reschedule',
        html: `
            <div class="text-start">
                <p class="mb-2">Are you sure you want to reschedule this appointment?</p>
                <div class="alert alert-warning p-2 small">
                    <strong>Changes:</strong><br>
                    ${selectedDate != currentDate ? `• Date: ${formatDate(currentDate)} → ${formatDate(selectedDate)}<br>` : ''}
                    ${selectedTime != currentTime ? `• Time: ${formatTime(currentTime)} → ${formatTime(selectedTime)}<br>` : ''}
                    ${selectedService != currentService ? '• Service changed<br>' : ''}
                    ${selectedStaff != currentStaff ? '• Staff changed<br>' : ''}
                </div>
                <p class="text-muted small mb-0">This action can be tracked in reschedule history.</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Reschedule',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return new Promise((resolve, reject) => {
                // Show loading
                Swal.showLoading();
                
                // Prepare data
                const formData = new FormData();
                formData.append('appointment_id', <?php echo $appointment_id; ?>);
                formData.append('service_id', selectedService);
                formData.append('staff_id', selectedStaff || '');
                formData.append('date', selectedDate);
                formData.append('time', selectedTime);
                formData.append('notes', newNotes);
                formData.append('reason', $('#reschedule_reason').val());
                
                // Submit via AJAX
                $.ajax({
                    url: 'ajax/reschedule-appointment.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    timeout: 30000,
                    success: function(response) {
                        if (response.success) {
                            resolve(response);
                        } else {
                            reject(new Error(response.message || 'Failed to reschedule'));
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMsg = 'Connection error. Please try again.';
                        
                        console.log('AJAX Error Details:', {
                            status: status,
                            error: error,
                            responseText: xhr.responseText,
                            statusCode: xhr.status
                        });
                        
                        // Check if response contains HTML (PHP error)
                        if (xhr.responseText && xhr.responseText.trim().startsWith('<')) {
                            errorMsg = 'Server error. Please check the error log.';
                            console.error('HTML response received:', xhr.responseText);
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        } else if (status === 'timeout') {
                            errorMsg = 'Request timed out. Please try again.';
                        } else if (error) {
                            errorMsg = error;
                        }
                        
                        reject(new Error(errorMsg));
                    }
                });
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value.success) {
            Swal.fire({
                icon: 'success',
                title: 'Rescheduled Successfully!',
                html: `
                    <div class="text-center">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p>The appointment has been rescheduled.</p>
                        <p class="small text-muted">Reschedule ID: #${result.value.reschedule_id || 'N/A'}</p>
                    </div>
                `,
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonColor: '#6f42c1',
                confirmButtonText: 'View Appointments'
            }).then(() => {
                window.location.href = 'manage-appointments.php';
            });
        }
    }).catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Reschedule Failed',
            text: error.message || 'An unexpected error occurred',
            confirmButtonColor: '#6f42c1',
            confirmButtonText: 'OK'
        });
    });
});

// Helper function to format date for display
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        year: 'numeric'
    });
}

// Helper function to format time for display
function formatTime(timeStr) {
    const time = new Date('2000-01-01 ' + timeStr);
    return time.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
}
});

function checkAvailability(date) {
    if (!date) return;
    
    $.ajax({
        url: 'ajax/check-availability.php',
        method: 'POST',
        data: { 
            date: date,
            exclude_appointment: <?php echo $appointment_id; ?>
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('.time-slot').removeClass('booked');
                
                // Mark booked slots
                $('.time-slot').each(function() {
                    const time = $(this).data('time');
                    const slotKey = date + ' ' + time;
                    if (response.bookedSlots && response.bookedSlots[slotKey]) {
                        $(this).addClass('booked');
                    }
                });
                
                // Keep current time highlighted
                const currentTime = '<?php echo $appointment['appointment_time']; ?>';
                $(`.time-slot[data-time="${currentTime}"]`).addClass('current');
            }
        }
    });
}

function markBookedSlots() {
    const bookedSlots = <?php echo json_encode($booked_slots); ?>;
    const currentDate = $('#appointmentDate').val();
    
    $('.time-slot').each(function() {
        const time = $(this).data('time');
        const slotKey = currentDate + ' ' + time;
        if (bookedSlots[slotKey]) {
            $(this).addClass('booked');
        }
    });
}
</script>

<style>
/* Page Header */
.dashboard-header {
    background: white;
    border-radius: 16px;
    padding: 25px 30px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border-left: 5px solid #6f42c1;
}

.dashboard-header h1 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 5px;
}

/* Current Appointment Card */
.current-appointment-card {
    background: linear-gradient(135deg, #fff3cd, #fff9e6);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    border-left: 5px solid #ffc107;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.current-appointment-card .card-title {
    color: #856404;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #ffe69c;
}

.detail-box {
    background: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    padding: 15px;
    height: 100%;
    border: 1px solid #ffe69c;
}

.detail-label {
    display: block;
    font-size: 12px;
    color: #856404;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.detail-value {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: #856404;
}

.detail-value.price {
    color: #6f42c1;
    font-size: 18px;
}

.notes-box {
    background: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    padding: 15px;
    border: 1px solid #ffe69c;
}

/* Reschedule Form Card */
.reschedule-form-card {
    background: white;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border-top: 4px solid #6f42c1;
}

.reschedule-form-card .card-title {
    color: #6f42c1;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

/* Form Sections */
.form-section {
    margin-bottom: 35px;
    padding-bottom: 25px;
    border-bottom: 2px solid #f0f0f0;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
    background: #6f42c1;
    color: white;
    border-radius: 50%;
    font-weight: 700;
    margin-right: 15px;
    font-size: 1rem;
}

.section-header h5 {
    margin: 0;
    color: #343a40;
    font-weight: 600;
}

/* Service Grid */
.service-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.service-card {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
    height: 100%;
}

.service-card:hover {
    transform: translateY(-3px);
    border-color: #6f42c1;
    box-shadow: 0 5px 20px rgba(111, 66, 193, 0.1);
}

.service-card.selected {
    border-color: #6f42c1;
    background: linear-gradient(135deg, rgba(111, 66, 193, 0.05), transparent);
}

.service-card.current {
    border-color: #ffc107;
    background: #fff3cd;
}

.service-category {
    font-size: 12px;
    color: #6f42c1;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.service-card h6 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 12px;
    color: #343a40;
}

.service-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #6c757d;
}

.service-price {
    font-weight: 700;
    color: #6f42c1;
}

.current-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ffc107;
    color: #856404;
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
}

/* Time Slots */
.time-slots-wrapper {
    max-height: 350px;
    overflow-y: auto;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 15px;
    background: #f8f9fa;
}

.time-slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
}

.time-slot {
    padding: 12px 5px;
    text-align: center;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 500;
    font-size: 13px;
}

.time-slot:hover {
    border-color: #6f42c1;
    background: rgba(111, 66, 193, 0.05);
    transform: scale(1.05);
}

.time-slot.selected {
    background: #6f42c1;
    color: white;
    border-color: #6f42c1;
}

.time-slot.booked {
    background: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
    cursor: not-allowed;
    opacity: 0.6;
}

.time-slot.current {
    background: #fff3cd;
    color: #856404;
    border-color: #ffc107;
    position: relative;
}

.time-slot.current::after {
    content: 'Current';
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ffc107;
    color: #856404;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 8px;
    font-weight: 600;
}

/* Date Picker */
.date-picker-wrapper {
    position: relative;
}

.date-picker-wrapper .input-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #6f42c1;
    z-index: 10;
}

.date-picker-wrapper input {
    padding-left: 45px;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #e9ecef;
}

.form-actions .btn {
    padding: 12px 35px;
    font-weight: 600;
}

/* Staff Badge */
.staff-badge {
    display: inline-block;
    padding: 4px 12px;
    background: linear-gradient(135deg, #6f42c1, #9b6fe0);
    color: white;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.confirmed { background: #d4edda; color: #155724; }
.status-badge.completed { background: #cce5ff; color: #004085; }
.status-badge.cancelled { background: #f8d7da; color: #721c24; }

/* Form Controls */
.form-select-lg, .form-control-lg {
    font-size: 1rem;
    padding: 12px 15px;
    border-radius: 10px;
    border: 2px solid #e9ecef;
}

.form-select:focus, .form-control:focus {
    border-color: #6f42c1;
    box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.1);
}

/* Alert Custom */
.alert-custom {
    border-radius: 12px;
    border: none;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    padding: 15px 20px;
    margin-bottom: 25px;
}

/* Responsive */
@media (max-width: 768px) {
    .service-grid {
        grid-template-columns: 1fr;
    }
    
    .time-slots-grid {
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
    
    .detail-box {
        margin-bottom: 10px;
    }
}
/* SweetAlert Custom Styles */
.swal2-popup {
    border-radius: 16px !important;
    padding: 20px !important;
}

.swal2-title {
    color: #333 !important;
    font-weight: 600 !important;
}

.swal2-html-container {
    margin: 15px 0 !important;
}

.swal2-confirm {
    border-radius: 8px !important;
    padding: 10px 25px !important;
    font-weight: 500 !important;
}

.swal2-cancel {
    border-radius: 8px !important;
    padding: 10px 25px !important;
    font-weight: 500 !important;
}

.swal2-timer-progress-bar {
    background: #6f42c1 !important;
}

/* Success Animation */
@keyframes successCheck {
    0% { transform: scale(0); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

.swal2-success-ring {
    animation: successCheck 0.5s ease-in-out !important;
}

/* Error Animation */
@keyframes errorShake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

.swal2-icon.swal2-error {
    animation: errorShake 0.5s ease-in-out !important;
}

/* Warning Animation */
@keyframes warningPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.swal2-icon.swal2-warning {
    animation: warningPulse 1s ease-in-out !important;
}
</style>
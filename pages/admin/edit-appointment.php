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

$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$appointment_id) {
    header('Location: manage-appointments.php?error=invalid_id');
    exit();
}

$error = '';
$success = '';

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
    
    // Get appointment details
    $appointment_query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.price,
            s.duration,
            s.category as service_category,
            u.fName as customer_name,
            u.email as customer_email,
            u.contactNo as customer_phone,
            u.regID as customer_id,
            st.id as staff_id,
            r.fName as staff_name,
            st.specialization as staff_specialization
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg u ON a.user_id = u.regID
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
    
    // Get all customers
    $customers_query = "
        SELECT regID, fName, email, contactNo
        FROM reg
        WHERE role = 'user' AND cStatus = 1
        ORDER BY fName
    ";
    $customers_stmt = $pdo->query($customers_query);
    $customers = $customers_stmt->fetchAll();
    
    // Generate time slots
    $time_slots = [];
    $start_time = strtotime('09:00');
    $end_time = strtotime('20:00');
    $interval = 30 * 60;
    
    for ($time = $start_time; $time <= $end_time; $time += $interval) {
        $time_slots[] = date('H:i', $time);
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $customer_id = $_POST['customer_id'] ?? '';
        $service_id = $_POST['service_id'] ?? '';
        $staff_id = $_POST['staff_id'] ?? '';
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $status = $_POST['status'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $change_reason = $_POST['change_reason'] ?? '';
        
        $errors = [];
        
        // Validate inputs
        if (empty($customer_id)) $errors[] = 'Please select a customer';
        if (empty($service_id)) $errors[] = 'Please select a service';
        if (empty($appointment_date)) $errors[] = 'Please select a date';
        if (empty($appointment_time)) $errors[] = 'Please select a time';
        if (empty($status)) $errors[] = 'Please select a status';
        if (strtotime($appointment_date) < strtotime('today')) $errors[] = 'Appointment date cannot be in the past';
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Update appointment
                $update_query = "
                    UPDATE appointments 
                    SET user_id = ?,
                        service_id = ?,
                        staff_id = ?,
                        appointment_date = ?,
                        appointment_time = ?,
                        status = ?,
                        notes = ?,
                        modified_at = NOW()
                    WHERE id = ?
                ";
                
                $update_stmt = $pdo->prepare($update_query);
                $update_stmt->execute([
                    $customer_id,
                    $service_id,
                    $staff_id ?: null,
                    $appointment_date,
                    $appointment_time,
                    $status,
                    $notes,
                    $appointment_id
                ]);
                
                $pdo->commit();
                
                $_SESSION['success'] = 'Appointment updated successfully';
                header('Location: manage-appointments.php?updated=1');
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('Update appointment error: ' . $e->getMessage());
                $error = 'Database error occurred. Please try again.';
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
    
} catch (PDOException $e) {
    error_log('Edit appointment page error: ' . $e->getMessage());
    $error = 'Unable to load appointment details. Please try again later.';
}

include 'header/header-admin.php';
?>

<!-- Page Header -->
<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-edit me-2" style="color: #6f42c1;"></i> Edit Appointment</h1>
            <p class="lead mb-0">Appointment #<?php echo $appointment_id; ?> - <?php echo htmlspecialchars($appointment['customer_name']); ?></p>
        </div>
        <div class="col-md-4 text-end">
            <a href="manage-appointments.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Appointments
            </a>
        </div>
    </div>
</div>

<!-- Current Appointment Summary -->
<div class="current-appointment-card">
    <h5><i class="fas fa-info-circle me-2"></i> Current Appointment Details</h5>
    <div class="row">
        <div class="col-md-3">
            <div class="info-box">
                <span class="label">Customer</span>
                <span class="value"><?php echo htmlspecialchars($appointment['customer_name']); ?></span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="label">Service</span>
                <span class="value"><?php echo htmlspecialchars($appointment['service_name']); ?></span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="info-box">
                <span class="label">Date</span>
                <span class="value"><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="info-box">
                <span class="label">Time</span>
                <span class="value"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="info-box">
                <span class="label">Status</span>
                <span class="value status-<?php echo $appointment['status']; ?>"><?php echo ucfirst($appointment['status']); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Edit Form -->
<div class="edit-form-card">
    <h5><i class="fas fa-edit me-2"></i> Edit Appointment Details</h5>
    
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="editForm" class="mt-4">
        <div class="row">
            <!-- Customer Selection -->
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Customer *</label>
                <select class="form-select" name="customer_id" id="customer_id" required>
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo $customer['regID']; ?>" 
                            data-phone="<?php echo htmlspecialchars($customer['contactNo']); ?>"
                            data-email="<?php echo htmlspecialchars($customer['email']); ?>"
                            <?php echo $customer['regID'] == $appointment['customer_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($customer['fName']); ?> (<?php echo htmlspecialchars($customer['email']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Customer Details Display -->
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Customer Contact</label>
                <div class="customer-details-box">
                    <div><i class="fas fa-phone me-2"></i> <span id="customer_phone"><?php echo htmlspecialchars($appointment['customer_phone']); ?></span></div>
                    <div><i class="fas fa-envelope me-2"></i> <span id="customer_email"><?php echo htmlspecialchars($appointment['customer_email']); ?></span></div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Service Selection -->
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Service *</label>
                <select class="form-select" name="service_id" id="service_id" required>
                    <option value="">-- Select Service --</option>
                    <?php foreach ($services as $service): ?>
                    <option value="<?php echo $service['id']; ?>" 
                            data-price="<?php echo $service['price']; ?>"
                            data-duration="<?php echo $service['duration']; ?>"
                            <?php echo $service['id'] == $appointment['service_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($service['name']); ?> (<?php echo $service['duration']; ?> mins) - Rs: <?php echo number_format($service['price'], 2); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Service Details -->
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Service Details</label>
                <div class="service-details-box">
                    <div><i class="fas fa-clock me-2"></i> Duration: <span id="service_duration"><?php echo $appointment['duration']; ?></span> minutes</div>
                    <div><i class="fas fa-tag me-2"></i> Category: <span id="service_category"><?php echo htmlspecialchars($appointment['service_category']); ?></span></div>
                    <div><i class="fas fa-rupee-sign me-2"></i> Price: Rs: <span id="service_price"><?php echo number_format($appointment['price'], 2); ?></span></div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Staff Selection -->
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Staff Member</label>
                <select class="form-select" name="staff_id" id="staff_id">
                    <option value="">-- Not Assigned --</option>
                    <?php foreach ($all_staff as $staff): ?>
                    <option value="<?php echo $staff['id']; ?>" 
                            data-specialization="<?php echo htmlspecialchars($staff['specialization'] ?? 'General'); ?>"
                            <?php echo $staff['id'] == $appointment['staff_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($staff['staff_name']); ?> (<?php echo htmlspecialchars($staff['specialization'] ?? 'General'); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Staff Details -->
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Staff Details</label>
                <div class="staff-details-box">
                    <div><i class="fas fa-user-tie me-2"></i> Specialization: <span id="staff_specialization"><?php echo htmlspecialchars($appointment['staff_specialization'] ?? 'Not Assigned'); ?></span></div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Date Selection -->
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Appointment Date *</label>
                <input type="date" class="form-control" name="appointment_date" id="appointment_date" 
                       value="<?php echo $appointment['appointment_date']; ?>" 
                       min="<?php echo date('Y-m-d'); ?>" 
                       max="<?php echo date('Y-m-d', strtotime('+90 days')); ?>" required>
            </div>
            
            <!-- Time Selection -->
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Appointment Time *</label>
                <div class="time-slots-container">
                    <div class="time-slots-grid" id="timeSlotsGrid">
                        <?php foreach ($time_slots as $time): 
                            $formattedTime = date('g:i A', strtotime($time));
                            $is_selected = ($time == $appointment['appointment_time']);
                        ?>
                        <div class="time-slot <?php echo $is_selected ? 'selected' : ''; ?>" data-time="<?php echo $time; ?>">
                            <?php echo $formattedTime; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" name="appointment_time" id="selected_time" value="<?php echo $appointment['appointment_time']; ?>">
            </div>
        </div>
        
        <div class="row">
            <!-- Status Selection -->
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Status *</label>
                <select class="form-select" name="status" id="status" required>
                    <option value="pending" <?php echo $appointment['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <!-- Change Reason -->
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Reason for Change (Optional)</label>
                <select class="form-select" name="change_reason">
                    <option value="">-- Select Reason --</option>
                    <option value="customer_request">Customer Request</option>
                    <option value="staff_unavailable">Staff Unavailable</option>
                    <option value="schedule_conflict">Schedule Conflict</option>
                    <option value="data_correction">Data Correction</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Notes</label>
            <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes..."><?php echo htmlspecialchars($appointment['notes'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='manage-appointments.php'">
                <i class="fas fa-times me-2"></i>Cancel
            </button>
            <button type="submit" class="btn btn-primary" id="submitBtn">
                <i class="fas fa-save me-2"></i>Save Changes
            </button>
        </div>
    </form>
</div>

<?php include 'footer/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Customer selection - update contact info
    $('#customer_id').change(function() {
        const selectedOption = $(this).find('option:selected');
        const phone = selectedOption.data('phone') || 'N/A';
        const email = selectedOption.data('email') || 'N/A';
        $('#customer_phone').text(phone);
        $('#customer_email').text(email);
    });
    
    // Service selection - update details
    $('#service_id').change(function() {
        const selectedOption = $(this).find('option:selected');
        const price = selectedOption.data('price') || 0;
        const duration = selectedOption.data('duration') || 0;
        $('#service_price').text(parseFloat(price).toFixed(2));
        $('#service_duration').text(duration);
    });
    
    // Staff selection - update details
    $('#staff_id').change(function() {
        const selectedOption = $(this).find('option:selected');
        const specialization = selectedOption.data('specialization') || 'Not Assigned';
        $('#staff_specialization').text(specialization);
    });
    
    // Time slot selection
    $('.time-slot').click(function() {
        $('.time-slot').removeClass('selected');
        $(this).addClass('selected');
        $('#selected_time').val($(this).data('time'));
    });
    
    // Form submission with SweetAlert confirmation
    $('#editForm').on('submit', function(e) {
        e.preventDefault();
        
        // Get form values
        const customerId = $('#customer_id').val();
        const serviceId = $('#service_id').val();
        const staffId = $('#staff_id').val();
        const date = $('#appointment_date').val();
        const time = $('#selected_time').val();
        const status = $('#status').val();
        
        // Validate required fields
        if (!customerId || !serviceId || !date || !time || !status) {
            Swal.fire({
                icon: 'warning',
                title: 'Incomplete Form',
                text: 'Please fill in all required fields.',
                confirmButtonColor: '#6f42c1'
            });
            return false;
        }
        
        // Get original values
        const originalCustomerId = '<?php echo $appointment['customer_id']; ?>';
        const originalServiceId = '<?php echo $appointment['service_id']; ?>';
        const originalStaffId = '<?php echo $appointment['staff_id']; ?>';
        const originalDate = '<?php echo $appointment['appointment_date']; ?>';
        const originalTime = '<?php echo $appointment['appointment_time']; ?>';
        const originalStatus = '<?php echo $appointment['status']; ?>';
        
        // Check if any changes were made
        const hasChanges = (
            customerId != originalCustomerId ||
            serviceId != originalServiceId ||
            staffId != originalStaffId ||
            date != originalDate ||
            time != originalTime ||
            status != originalStatus
        );
        
        if (!hasChanges) {
            Swal.fire({
                icon: 'info',
                title: 'No Changes Detected',
                text: 'You haven\'t made any changes to the appointment.',
                confirmButtonColor: '#6f42c1'
            });
            return false;
        }
        
        // Build changes summary
        let changesHtml = '<div class="text-start"><strong>Changes to be applied:</strong><ul class="mt-2 mb-0">';
        
        if (customerId != originalCustomerId) {
            const newCustomer = $(`#customer_id option[value="${customerId}"]`).text();
            changesHtml += `<li><span class="text-danger">Customer:</span> → <span class="text-success">${escapeHtml(newCustomer)}</span></li>`;
        }
        
        if (serviceId != originalServiceId) {
            const newService = $(`#service_id option[value="${serviceId}"]`).text();
            changesHtml += `<li><span class="text-danger">Service:</span> → <span class="text-success">${escapeHtml(newService)}</span></li>`;
        }
        
        if (staffId != originalStaffId) {
            const newStaff = $(`#staff_id option[value="${staffId}"]`).text() || 'Not Assigned';
            changesHtml += `<li><span class="text-danger">Staff:</span> → <span class="text-success">${escapeHtml(newStaff)}</span></li>`;
        }
        
        if (date != originalDate) {
            changesHtml += `<li><span class="text-danger">Date:</span> ${originalDate} → <span class="text-success">${date}</span></li>`;
        }
        
        if (time != originalTime) {
            changesHtml += `<li><span class="text-danger">Time:</span> ${formatTimeDisplay(originalTime)} → <span class="text-success">${formatTimeDisplay(time)}</span></li>`;
        }
        
        if (status != originalStatus) {
            changesHtml += `<li><span class="text-danger">Status:</span> ${originalStatus} → <span class="text-success">${status}</span></li>`;
        }
        
        changesHtml += '</ul></div>';
        
        // Show confirmation dialog
        Swal.fire({
            title: 'Confirm Changes',
            html: changesHtml,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Save Changes',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Saving Changes...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit the form
                $('#editForm').off('submit').submit();
            }
        });
    });
});

function formatTimeDisplay(timeStr) {
    if (!timeStr) return '';
    const time = new Date('2000-01-01 ' + timeStr);
    return time.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<style>
/* Current Appointment Card */
.current-appointment-card {
    background: linear-gradient(135deg, #e8f4fd, #d1ecf1);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    border-left: 5px solid #17a2b8;
}

.current-appointment-card h5 {
    color: #0c5460;
    margin-bottom: 20px;
    font-weight: 600;
}

.info-box {
    background: white;
    border-radius: 12px;
    padding: 12px 15px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.info-box .label {
    display: block;
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.info-box .value {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: #0c5460;
}

.info-box .value.status-pending { color: #856404; }
.info-box .value.status-confirmed { color: #155724; }
.info-box .value.status-completed { color: #004085; }
.info-box .value.status-cancelled { color: #721c24; }

/* Edit Form Card */
.edit-form-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.edit-form-card h5 {
    color: #6f42c1;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
}

/* Details Boxes */
.customer-details-box,
.service-details-box,
.staff-details-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 12px 15px;
    height: 100%;
    border: 1px solid #e9ecef;
}

.customer-details-box div,
.service-details-box div,
.staff-details-box div {
    margin-bottom: 5px;
    font-size: 14px;
}

/* Time Slots */
.time-slots-container {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid #e9ecef;
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
    padding: 10px;
    text-align: center;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 13px;
    font-weight: 500;
}

.time-slot:hover {
    border-color: #6f42c1;
    background: rgba(111, 66, 193, 0.05);
    transform: scale(1.02);
}

.time-slot.selected {
    background: #6f42c1;
    color: white;
    border-color: #6f42c1;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 2px solid #e9ecef;
}

.form-actions .btn {
    padding: 10px 30px;
    font-weight: 500;
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
@media (max-width: 768px) {
    .time-slots-grid {
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
    
    .info-box {
        margin-bottom: 10px;
    }
}
</style>
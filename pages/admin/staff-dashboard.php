<?php
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Check if user is staff
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../user/dashboard.php');
    exit();
}

// Add this check - verify password has been changed
// Include database connection
require_once '../../includes/dbcon.php';
$pdo = getPDOConnection();
$user_id = $_SESSION['user_id'];
$check_query = "SELECT password_changed FROM reg WHERE regID = ?";
$check_stmt = $pdo->prepare($check_query);
$check_stmt->execute([$user_id]);
$user_data = $check_stmt->fetch();

if ($user_data && $user_data['password_changed'] == 0) {
    // Force password change
    $_SESSION['temp_user_id'] = $user_id;
    $_SESSION['temp_user_name'] = $_SESSION['fName'] ?? '';
    header('Location: force-password-change.php');
    exit();
}

require_once '../../includes/helpers.php';

$user_id = $_SESSION['user_id'];

try {
    $pdo = getPDOConnection();
    
    // Get staff details
    $staff_query = "
        SELECT s.*, r.fName as staff_name, r.email, r.contactNo 
        FROM staff s
        JOIN reg r ON s.user_id = r.regID
        WHERE s.user_id = ? AND r.cStatus = 1
    ";
    $staff_stmt = $pdo->prepare($staff_query);
    $staff_stmt->execute([$user_id]);
    $staff = $staff_stmt->fetch();
    
    if (!$staff) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
    
    $staff_id = $staff['id'];
    $current_time = date('H:i:s');
    
    // Get today's appointments (from current time onwards)
    $today_appointments_query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.duration,
            s.price,
            u.fName as customer_name,
            u.email as customer_email,
            u.contactNo as customer_phone
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg u ON a.user_id = u.regID
        WHERE a.staff_id = ? 
        AND a.appointment_date = CURDATE()
        AND a.status IN ('confirmed', 'pending')
        AND a.appointment_time >= ?
        ORDER BY a.appointment_time ASC
    ";
    $today_appointments_stmt = $pdo->prepare($today_appointments_query);
    $today_appointments_stmt->execute([$staff_id, $current_time]);
    $today_appointments = $today_appointments_stmt->fetchAll();
    
    // Get all today's appointments for calendar
    $all_today_query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.duration,
            u.fName as customer_name
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg u ON a.user_id = u.regID
        WHERE a.staff_id = ? 
        AND a.appointment_date = CURDATE()
        AND a.status IN ('confirmed', 'pending')
    ";
    $all_today_stmt = $pdo->prepare($all_today_query);
    $all_today_stmt->execute([$staff_id]);
    $all_today_appointments = $all_today_stmt->fetchAll();
    
    // Get upcoming appointments
    $upcoming_query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.duration,
            s.price,
            u.fName as customer_name
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg u ON a.user_id = u.regID
        WHERE a.staff_id = ? 
        AND a.appointment_date > CURDATE()
        AND a.status IN ('confirmed', 'pending')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 20
    ";
    $upcoming_stmt = $pdo->prepare($upcoming_query);
    $upcoming_stmt->execute([$staff_id]);
    $upcoming_appointments = $upcoming_stmt->fetchAll();
    
    // Get history appointments
    $history_query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.duration,
            s.price,
            u.fName as customer_name
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN reg u ON a.user_id = u.regID
        WHERE a.staff_id = ? 
        AND (a.appointment_date < CURDATE() OR a.status IN ('completed', 'cancelled'))
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 30
    ";
    $history_stmt = $pdo->prepare($history_query);
    $history_stmt->execute([$staff_id]);
    $history_appointments = $history_stmt->fetchAll();
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_today,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_today
        FROM appointments
        WHERE staff_id = ? AND appointment_date = CURDATE() AND status IN ('confirmed', 'pending')
    ";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$staff_id]);
    $stats = $stats_stmt->fetch();
    
    // Get total completed
    $total_completed_query = "
        SELECT COUNT(*) as total FROM appointments 
        WHERE staff_id = ? AND status = 'completed'
    ";
    $total_completed_stmt = $pdo->prepare($total_completed_query);
    $total_completed_stmt->execute([$staff_id]);
    $total_completed = $total_completed_stmt->fetch()['total'];
    
    $upcoming_count = count($upcoming_appointments);
    
// Get staff availability
$availability_query = "
    SELECT day_of_week, start_time, end_time, is_available 
    FROM staff_availability 
    WHERE staff_id = ?
    ORDER BY day_of_week
";
$availability_stmt = $pdo->prepare($availability_query);
$availability_stmt->execute([$staff_id]);
$availability = $availability_stmt->fetchAll();

// Initialize with default values (all days not available by default)
$availability_map = [];
for ($i = 0; $i < 7; $i++) {
    $availability_map[$i] = [
        'start' => '09:00', 
        'end' => '18:00', 
        'available' => false
    ];
}

// Override with actual data from database
foreach ($availability as $avail) {
    $availability_map[$avail['day_of_week']] = [
        'start' => substr($avail['start_time'], 0, 5),
        'end' => substr($avail['end_time'], 0, 5),
        'available' => $avail['is_available'] == 1
    ];
}
    
    for ($i = 0; $i < 7; $i++) {
        if (!isset($availability_map[$i])) {
            $availability_map[$i] = ['start' => '09:00', 'end' => '18:00', 'available' => true];
        }
    }
    
    // Get notifications count
    $notifications_query = "
        SELECT COUNT(*) as count FROM staff_notifications 
        WHERE staff_id = ? AND is_read = 0
    ";
    $notifications_stmt = $pdo->prepare($notifications_query);
    $notifications_stmt->execute([$staff_id]);
    $unread_count = $notifications_stmt->fetch()['count'];
    
    // Prepare calendar events
    $calendar_events = [];
    
    foreach ($all_today_appointments as $apt) {
        $calendar_events[] = [
            'id' => $apt['id'],
            'title' => $apt['customer_name'] . ' - ' . $apt['service_name'],
            'start' => $apt['appointment_date'] . 'T' . $apt['appointment_time'],
            'color' => '#28a745',
            'extendedProps' => ['appointment_id' => $apt['id'], 'type' => 'today']
        ];
    }
    
    foreach ($upcoming_appointments as $apt) {
        $calendar_events[] = [
            'id' => $apt['id'],
            'title' => $apt['customer_name'] . ' - ' . $apt['service_name'],
            'start' => $apt['appointment_date'] . 'T' . $apt['appointment_time'],
            'color' => '#17a2b8',
            'extendedProps' => ['appointment_id' => $apt['id'], 'type' => 'upcoming']
        ];
    }
    
    foreach ($history_appointments as $apt) {
        $color = $apt['status'] == 'completed' ? '#6c757d' : '#dc3545';
        $calendar_events[] = [
            'id' => $apt['id'],
            'title' => $apt['customer_name'] . ' - ' . $apt['service_name'],
            'start' => $apt['appointment_date'] . 'T' . $apt['appointment_time'],
            'color' => $color,
            'extendedProps' => ['appointment_id' => $apt['id'], 'type' => 'past']
        ];
    }
    
} catch (PDOException $e) {
    error_log('Staff dashboard error: ' . $e->getMessage());
}

include 'header/header-staff.php';
?>

<!-- Main Content Wrapper - NO ALERT DIVS -->
<div class="main-content-inner">
    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2><i class="fas fa-user-md me-2"></i> Welcome, <?php echo htmlspecialchars($staff['staff_name']); ?>!</h2>
                <p class="mb-0">Manage your appointments and availability</p>
            </div>
            <div class="col-md-4 text-end">
                <span class="current-datetime">
                    <i class="fas fa-calendar me-2"></i><?php echo date('l, F j, Y'); ?>
                    <br>
                    <i class="fas fa-clock me-2"></i><?php echo date('g:i A'); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?php echo count($today_appointments); ?></div>
                <div class="stat-label">Today's Appointments</div>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?php echo $stats['completed_today'] ?? 0; ?></div>
                <div class="stat-label">Completed Today</div>
            </div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?php echo $upcoming_count; ?></div>
                <div class="stat-label">Upcoming</div>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-history"></i>
            </div>
            <div class="stat-details">
                <div class="stat-value"><?php echo $total_completed; ?></div>
                <div class="stat-label">Total Completed</div>
            </div>
        </div>
    </div>

    <!-- Today's Schedule -->
    <div class="schedule-card">
        <div class="card-header">
            <h5><i class="fas fa-calendar-day me-2"></i> Today's Schedule</h5>
            <span class="badge bg-primary"><?php echo date('F j, Y'); ?></span>
        </div>
        
        <div class="timeline-container">
            <?php if (empty($today_appointments)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                <h5>No more appointments today</h5>
                <p class="text-muted">You're all done for the day!</p>
            </div>
            <?php else: ?>
                <div class="appointments-list">
                    <?php foreach ($today_appointments as $apt): ?>
                    <div class="appointment-card" onclick="viewAppointment(<?php echo $apt['id']; ?>)">
                        <div class="appointment-time">
                            <i class="far fa-clock"></i> <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?>
                        </div>
                        <div class="appointment-info">
                            <div class="customer-info">
                                <strong><?php echo htmlspecialchars($apt['customer_name']); ?></strong>
                            </div>
                            <div class="service-info">
                                <span class="service-name"><?php echo htmlspecialchars($apt['service_name']); ?></span>
                            </div>
                            <div class="meta-info">
                                <span class="duration"><i class="far fa-clock"></i> <?php echo $apt['duration']; ?> mins</span>
                                <span class="price"><i class="fas fa-rupee-sign"></i> <?php echo number_format($apt['price'], 2); ?></span>
                            </div>
                        </div>
                        <div class="appointment-actions">
                            <button class="btn-extend" onclick="event.stopPropagation(); extendAppointment(<?php echo $apt['id']; ?>, '<?php echo $apt['appointment_time']; ?>', <?php echo $apt['duration']; ?>)" title="Extend Time">
                                <i class="fas fa-clock"></i> Extend
                            </button>
                            <button class="btn-move" onclick="event.stopPropagation(); moveAppointment(<?php echo $apt['id']; ?>)" title="Move Appointment">
                                <i class="fas fa-exchange-alt"></i> Move
                            </button>
                            <button class="btn-complete" onclick="event.stopPropagation(); completeAppointment(<?php echo $apt['id']; ?>)" title="Mark Complete">
                                <i class="fas fa-check"></i> Complete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Calendar View -->
    <div class="calendar-card">
        <div class="card-header">
            <h5><i class="fas fa-calendar-alt me-2"></i> Appointments Calendar</h5>
            <div class="header-actions">
                <button class="btn-month" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                <span id="currentMonth" class="mx-2"><?php echo date('F Y'); ?></span>
                <button class="btn-month" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        <div class="calendar-container">
            <div id="calendar"></div>
        </div>
        <div class="legend">
            <span class="legend-dot today"></span><small>Today</small>
            <span class="legend-dot upcoming ms-3"></span><small>Upcoming</small>
            <span class="legend-dot completed ms-3"></span><small>Completed</small>
            <span class="legend-dot cancelled ms-3"></span><small>Cancelled</small>
        </div>
    </div>

    <!-- Availability Settings -->
    <div class="availability-card">
        <div class="card-header">
            <h5><i class="fas fa-clock me-2"></i> My Availability</h5>
            <button class="btn-save-availability" onclick="saveAvailability()">
                <i class="fas fa-save me-2"></i>Save Changes
            </button>
        </div>
        
        <div class="availability-grid">
            <?php
            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($days as $index => $day):
                $avail = $availability_map[$index];
            ?>
            <div class="availability-row">
                <div class="day-name"><?php echo $day; ?></div>
                <div class="day-toggle">
                    <div class="form-check form-switch">
                        <input class="form-check-input avail-toggle" type="checkbox" id="day_<?php echo $index; ?>" 
                               data-day="<?php echo $index; ?>" <?php echo $avail['available'] ? 'checked' : ''; ?>>
                    </div>
                </div>
                <div class="day-hours">
                    <input type="time" class="form-control start-time" id="start_<?php echo $index; ?>" 
                           value="<?php echo $avail['start']; ?>" <?php echo !$avail['available'] ? 'disabled' : ''; ?>>
                    <span>to</span>
                    <input type="time" class="form-control end-time" id="end_<?php echo $index; ?>" 
                           value="<?php echo $avail['end']; ?>" <?php echo !$avail['available'] ? 'disabled' : ''; ?>>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modals (same as before) -->
<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="appointmentModalBody">
                <div class="text-center py-4">Loading...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="notifyCustomerBtn">Notify Customer</button>
            </div>
        </div>
    </div>
</div>

<!-- Extend Appointment Modal -->
<div class="modal fade" id="extendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Extend Appointment Time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Current End Time</label>
                    <input type="text" class="form-control" id="currentEndTime" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">New End Time</label>
                    <input type="time" class="form-control" id="newEndTime">
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <textarea class="form-control" id="extendReason" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitExtension()">Submit</button>
            </div>
        </div>
    </div>
</div>

<!-- Move Appointment Modal -->
<div class="modal fade" id="moveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Move Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Current</label>
                    <input type="text" class="form-control" id="currentDateTime" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Date</label>
                    <input type="date" class="form-control" id="newDate" min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">New Time</label>
                    <input type="time" class="form-control" id="newTime">
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <textarea class="form-control" id="moveReason" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitMove()">Submit</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<script>
let calendar = null;
let currentMonth = new Date();
let calendarEvents = <?php echo json_encode($calendar_events); ?>;

$(document).ready(function() {
    // Mobile sidebar toggle
    $('#sidebarToggle').click(function() {
        $('#sidebar').toggleClass('active');
        $('#mainContent').toggleClass('active');
    });
    
    initializeCalendar();
    
    $('.avail-toggle').change(function() {
        const day = $(this).data('day');
        const isChecked = $(this).is(':checked');
        $(`#start_${day}`).prop('disabled', !isChecked);
        $(`#end_${day}`).prop('disabled', !isChecked);
    });
});

function initializeCalendar() {
    const calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            initialDate: currentMonth,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek'
            },
            events: calendarEvents,
            eventClick: function(info) {
                viewAppointment(info.event.extendedProps.appointment_id);
            },
            height: 'auto',
            dayMaxEvents: true,
            nowIndicator: true,
            eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: 'short' }
        });
        calendar.render();
    }
}

function changeMonth(direction) {
    if (direction === 1) {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 1);
    } else {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1, 1);
    }
    $('#currentMonth').text(currentMonth.toLocaleString('default', { month: 'long', year: 'numeric' }));
    if (calendar) {
        calendar.gotoDate(currentMonth);
    }
}

function viewAppointment(appointmentId) {
    $.ajax({
        url: 'ajax/get-appointment-details.php',
        method: 'POST',
        data: { id: appointmentId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.appointment) {
                const apt = response.appointment;
                const html = `
                    <div class="appointment-details">
                        <div class="detail-section">
                            <h6>Customer Information</h6>
                            <div><strong>Name:</strong> ${escapeHtml(apt.customer_name)}</div>
                            <div><strong>Phone:</strong> ${escapeHtml(apt.customer_phone)}</div>
                            <div><strong>Email:</strong> ${escapeHtml(apt.customer_email)}</div>
                        </div>
                        <div class="detail-section mt-3">
                            <h6>Service Information</h6>
                            <div><strong>Service:</strong> ${escapeHtml(apt.service_name)}</div>
                            <div><strong>Duration:</strong> ${apt.service_duration} mins</div>
                            <div><strong>Price:</strong> Rs: ${parseFloat(apt.service_price).toFixed(2)}</div>
                        </div>
                        <div class="detail-section mt-3">
                            <h6>Appointment Details</h6>
                            <div><strong>Date:</strong> ${formatDate(apt.appointment_date)}</div>
                            <div><strong>Time:</strong> ${formatTime(apt.appointment_time)}</div>
                            <div><strong>Status:</strong> <span class="status-badge ${apt.status}">${apt.status}</span></div>
                            ${apt.notes ? `<div><strong>Notes:</strong> ${escapeHtml(apt.notes)}</div>` : ''}
                        </div>
                    </div>
                `;
                $('#appointmentModalBody').html(html);
                $('#notifyCustomerBtn').off('click').on('click', function() {
                    notifyCustomer(apt.id);
                });
                $('#appointmentModal').modal('show');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to load details', 'error');
        }
    });
}

let extendAppointmentId = null;

function extendAppointment(appointmentId, startTime, duration) {
    extendAppointmentId = appointmentId;
    const [hours, minutes] = startTime.split(':');
    const endDateTime = new Date();
    endDateTime.setHours(parseInt(hours), parseInt(minutes) + duration);
    $('#currentEndTime').val(endDateTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }));
    $('#newEndTime').val(endDateTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }));
    $('#extendModal').modal('show');
}

function submitExtension() {
    const newEndTime = $('#newEndTime').val();
    if (!newEndTime) {
        Swal.fire('Error', 'Please select new end time', 'error');
        return;
    }
    
    $.ajax({
        url: 'ajax/request-appointment-extension.php',
        method: 'POST',
        data: {
            appointment_id: extendAppointmentId,
            new_end_time: newEndTime,
            reason: $('#extendReason').val()
        },
        dataType: 'json',
        success: function(response) {
            $('#extendModal').modal('hide');
            if (response.success) {
                Swal.fire('Success', 'Extension request sent', 'success');
                $('#extendReason').val('');
            } else {
                Swal.fire('Error', response.message || 'Failed to submit', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to submit', 'error');
        }
    });
}

let moveAppointmentId = null;

function moveAppointment(appointmentId) {
    moveAppointmentId = appointmentId;
    $('#currentDateTime').val('');
    $('#newDate').val('');
    $('#newTime').val('');
    $('#moveModal').modal('show');
}

function submitMove() {
    const newDate = $('#newDate').val();
    const newTime = $('#newTime').val();
    
    if (!newDate || !newTime) {
        Swal.fire('Error', 'Please select new date and time', 'error');
        return;
    }
    
    $.ajax({
        url: 'ajax/request-appointment-move.php',
        method: 'POST',
        data: {
            appointment_id: moveAppointmentId,
            new_date: newDate,
            new_time: newTime,
            reason: $('#moveReason').val()
        },
        dataType: 'json',
        success: function(response) {
            $('#moveModal').modal('hide');
            if (response.success) {
                Swal.fire('Success', 'Move request sent', 'success');
                $('#moveReason').val('');
            } else {
                Swal.fire('Error', response.message || 'Failed to submit', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to submit', 'error');
        }
    });
}

function completeAppointment(appointmentId) {
    Swal.fire({
        title: 'Complete Appointment?',
        text: 'Mark this appointment as completed?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/update-appointment-status.php',
                method: 'POST',
                data: { id: appointmentId, status: 'completed' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Success', 'Appointment completed', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Swal.fire('Error', response.message || 'Failed to complete', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to complete', 'error');
                }
            });
        }
    });
}

function notifyCustomer(appointmentId) {
    Swal.fire({
        title: 'Send Notification',
        input: 'textarea',
        inputPlaceholder: 'Enter your message here...',
        showCancelButton: true,
        confirmButtonText: 'Send',
        preConfirm: (message) => {
            if (!message) {
                Swal.showValidationMessage('Please enter a message');
                return false;
            }
            return message;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/send-customer-notification.php',
                method: 'POST',
                data: {
                    appointment_id: appointmentId,
                    type: 'reminder',
                    message: result.value
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Success', 'Notification sent', 'success');
                    } else {
                        Swal.fire('Error', response.message || 'Failed to send', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to send', 'error');
                }
            });
        }
    });
}

// Save availability - Updated version
function saveAvailability() {
    const availability = [];
    
    for (let i = 0; i < 7; i++) {
        const isChecked = $(`#day_${i}`).is(':checked');
        const startTime = $(`#start_${i}`).val();
        const endTime = $(`#end_${i}`).val();
        
        availability.push({
            day: i,
            available: isChecked,
            start: startTime,
            end: endTime
        });
    }
    
    // Show loading
    Swal.fire({
        title: 'Saving...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: 'ajax/save-staff-availability.php',
        method: 'POST',
        data: {
            staff_id: <?php echo $staff_id; ?>,
            availability: JSON.stringify(availability)
        },
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Saved!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    // Reload the page to show updated settings
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || 'Failed to save availability',
                    confirmButtonColor: '#6f42c1'
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Response:', xhr.responseText);
            let errorMsg = 'Failed to save availability. Please try again.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMsg,
                confirmButtonColor: '#6f42c1'
            });
        }
    });
}

// Function to reload availability data without page refresh
function reloadAvailability() {
    $.ajax({
        url: 'ajax/get-staff-availability.php',
        method: 'POST',
        data: { staff_id: <?php echo $staff_id; ?> },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.availability) {
                // Update the UI with loaded data
                for (let i = 0; i < 7; i++) {
                    const avail = response.availability[i];
                    if (avail) {
                        $(`#day_${i}`).prop('checked', avail.available);
                        $(`#start_${i}`).val(avail.start).prop('disabled', !avail.available);
                        $(`#end_${i}`).val(avail.end).prop('disabled', !avail.available);
                    }
                }
            }
        },
        error: function() {
            console.error('Failed to reload availability');
        }
    });
}

function showNotifications() {
    $.ajax({
        url: 'ajax/get-staff-notifications.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.notifications && response.notifications.length > 0) {
                let html = '<div style="max-height: 400px; overflow-y: auto;">';
                response.notifications.forEach(notif => {
                    html += `<div class="notification-item p-3 border-bottom" onclick="markAsRead(${notif.id})">
                                <strong>${escapeHtml(notif.title || 'Notification')}</strong>
                                <p class="mb-0 small mt-1">${escapeHtml(notif.message)}</p>
                                <small class="text-muted">${formatDateTime(notif.created_at)}</small>
                            </div>`;
                });
                html += '</div>';
                Swal.fire({ title: 'Notifications', html: html, width: '500px' });
            } else {
                Swal.fire('Info', 'No notifications', 'info');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to load notifications', 'error');
        }
    });
}

function markAsRead(notificationId) {
    $.ajax({
        url: 'ajax/mark-notification-read.php',
        method: 'POST',
        data: { id: notificationId },
        dataType: 'json',
        success: function() {
            location.reload();
        }
    });
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatTime(timeStr) {
    const time = new Date('2000-01-01 ' + timeStr);
    return time.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
}

function formatDateTime(dateTimeStr) {
    const date = new Date(dateTimeStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}
</script>

<style>
/* Main Content Inner */
.main-content-inner {
    width: 100%;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-left: 4px solid;
}

.stat-card.primary { border-left-color: #6f42c1; }
.stat-card.success { border-left-color: #28a745; }
.stat-card.info { border-left-color: #17a2b8; }
.stat-card.warning { border-left-color: #ffc107; }

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(111,66,193,0.1);
    color: #6f42c1;
    font-size: 20px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
}

.stat-label {
    font-size: 13px;
    color: #6c757d;
}

/* Schedule Card */
.schedule-card, .calendar-card, .availability-card {
    background: white;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
}

.timeline-container {
    padding: 20px;
    max-height: 500px;
    overflow-y: auto;
}

.appointments-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.appointment-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s;
    border-left: 4px solid #6f42c1;
}

.appointment-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.appointment-time {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 8px;
}

.appointment-info {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 10px;
}

.customer-info strong {
    font-size: 16px;
    color: #333;
}

.service-name {
    color: #6f42c1;
    font-weight: 500;
}

.meta-info {
    display: flex;
    gap: 15px;
}

.duration, .price {
    font-size: 12px;
    background: #e9ecef;
    padding: 3px 10px;
    border-radius: 12px;
}

.appointment-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.btn-extend, .btn-move, .btn-complete {
    border: none;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-extend {
    background: #fff3cd;
    color: #856404;
}
.btn-extend:hover { background: #ffc107; color: #212529; }

.btn-move {
    background: #d1ecf1;
    color: #0c5460;
}
.btn-move:hover { background: #17a2b8; color: white; }

.btn-complete {
    background: #d4edda;
    color: #155724;
}
.btn-complete:hover { background: #28a745; color: white; }

/* Calendar */
.calendar-container {
    padding: 15px;
    min-height: 450px;
}

.legend {
    padding: 10px 15px;
    border-top: 1px solid #e9ecef;
    text-align: center;
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 2px;
    display: inline-block;
}
.legend-dot.today { background: #28a745; }
.legend-dot.upcoming { background: #17a2b8; }
.legend-dot.completed { background: #6c757d; }
.legend-dot.cancelled { background: #dc3545; }

/* Availability */
.availability-grid {
    padding: 20px;
}

.availability-row {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e9ecef;
}

.day-name {
    width: 100px;
    font-weight: 600;
}

.day-toggle {
    width: 80px;
}

.day-hours {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
}

.day-hours input {
    width: 100px;
}

.btn-save-availability {
    background: #6f42c1;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
}

.btn-save-availability:hover {
    background: #5a32a0;
}

/* Welcome Banner */
.welcome-banner {
    background: linear-gradient(135deg, #6f42c1, #5a32a0);
    color: white;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.current-datetime {
    background: rgba(255,255,255,0.2);
    padding: 8px 15px;
    border-radius: 8px;
    font-size: 13px;
    display: inline-block;
}

.empty-state {
    text-align: center;
    padding: 40px;
}

.btn-month {
    background: none;
    border: none;
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
}
.btn-month:hover { background: #f8f9fa; }

.header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}
.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.confirmed { background: #d4edda; color: #155724; }
.status-badge.completed { background: #cce5ff; color: #004085; }
.status-badge.cancelled { background: #f8d7da; color: #721c24; }

/* Modal */
.detail-section {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e9ecef;
}
.detail-section h6 {
    color: #6f42c1;
    margin-bottom: 10px;
    font-weight: 600;
}
.detail-section div {
    margin-bottom: 5px;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    display: none;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #6f42c1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .availability-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    .day-hours {
        width: 100%;
    }
    .appointment-info {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
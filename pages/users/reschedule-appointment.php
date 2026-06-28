<?php
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Check if user is admin (redirect to admin dashboard)
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    header('Location: ../admin/dashboard.php');
    exit();
}

// Include database connection
require_once '../../includes/dbcon.php';

// Initialize variables
$error = '';
$success = '';
$appointment = null;
$time_slots = [];
$booked_slots = [];
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$appointment_id) {
    header('Location: appointments.php?error=invalid');
    exit();
}

try {
    $pdo = getPDOConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get user details
    $user_query = "SELECT * FROM reg WHERE regID = ? AND cStatus = 1";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
    
    // Get the appointment to reschedule
    $appointment_query = "
        SELECT 
            a.*,
            s.name as service_name,
            s.price as service_price,
            s.duration as service_duration,
            s.category as service_category,
            s.status as service_status
        FROM appointments a
        INNER JOIN services s ON a.service_id = s.id
        WHERE a.id = ? AND a.user_id = ? 
        AND a.status IN ('pending', 'confirmed')
        AND a.appointment_date >= CURDATE()
    ";
    
    $appointment_stmt = $pdo->prepare($appointment_query);
    $appointment_stmt->execute([$appointment_id, $user_id]);
    $appointment = $appointment_stmt->fetch();
    
    if (!$appointment) {
        header('Location: appointments.php?error=notfound');
        exit();
    }
    
    // Check if service is still active
    if ($appointment['service_status'] !== 'active') {
        $error = 'This service is no longer available. Please choose a different service or contact support.';
    }
    
    // Get all active services (for possible service change)
    $services_query = "SELECT * FROM services WHERE status = 'active' ORDER BY category, name";
    $services_stmt = $pdo->query($services_query);
    $services = $services_stmt->fetchAll();
    
    // Generate time slots (9 AM to 8 PM, 30-minute intervals)
    $start_time = strtotime('09:00');
    $end_time = strtotime('20:00');
    $interval = 30 * 60; // 30 minutes in seconds
    
    for ($time = $start_time; $time <= $end_time; $time += $interval) {
        $time_slots[] = date('H:i', $time);
    }
    
    // Get booked slots for future dates (excluding current appointment)
    $today = date('Y-m-d');
    $booked_query = "
        SELECT appointment_date, appointment_time 
        FROM appointments 
        WHERE appointment_date >= ? 
        AND id != ?
        AND status IN ('pending', 'confirmed')
        ORDER BY appointment_date, appointment_time
    ";
    $booked_stmt = $pdo->prepare($booked_query);
    $booked_stmt->execute([$today, $appointment_id]);
    $booked_results = $booked_stmt->fetchAll();
    
    foreach ($booked_results as $slot) {
        $booked_slots[$slot['appointment_date'] . ' ' . $slot['appointment_time']] = true;
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_service_id = $_POST['service_id'] ?? $appointment['service_id'];
        $new_appointment_date = $_POST['appointment_date'] ?? '';
        $new_appointment_time = $_POST['appointment_time'] ?? '';
        $notes = $_POST['notes'] ?? $appointment['notes'];
        
        // Validation
        $valid = true;
        
        if (empty($new_appointment_date)) {
            $error = 'Please select a new date';
            $valid = false;
        }
        
        if (empty($new_appointment_time)) {
            $error = 'Please select a new time';
            $valid = false;
        }
        
        if ($valid && strtotime($new_appointment_date) < strtotime('today')) {
            $error = 'Appointment date cannot be in the past';
            $valid = false;
        }
        
        // Check if it's the same date and time
        $is_same_slot = ($new_appointment_date == $appointment['appointment_date'] && 
                         $new_appointment_time == $appointment['appointment_time'] &&
                         $new_service_id == $appointment['service_id']);
        
        if ($valid && $is_same_slot) {
            $error = 'No changes detected. Please select a different date, time, or service to reschedule.';
            $valid = false;
        }
        
        if ($valid) {
            try {
                // Check if service exists and is active
                $service_check = $pdo->prepare("SELECT * FROM services WHERE id = ? AND status = 'active'");
                $service_check->execute([$new_service_id]);
                $service = $service_check->fetch();
                
                if (!$service) {
                    $error = 'Selected service is not available';
                } else {
                    // Check if the new time slot is available (excluding current appointment)
                    $slot_key = $new_appointment_date . ' ' . $new_appointment_time;
                    if (isset($booked_slots[$slot_key])) {
                        $error = 'This time slot is already booked. Please choose another time.';
                    } else {
                        // Begin transaction
                        $pdo->beginTransaction();
                        
                        // Update the appointment
                        $update_query = "
                            UPDATE appointments 
                            SET service_id = ?,
                                appointment_date = ?,
                                appointment_time = ?,
                                notes = ?,
                                status = 'pending',
                                modified_at = NOW()
                            WHERE id = ? AND user_id = ?
                        ";
                        
                        $update_stmt = $pdo->prepare($update_query);
                        $update_result = $update_stmt->execute([
                            $new_service_id,
                            $new_appointment_date,
                            $new_appointment_time,
                            $notes,
                            $appointment_id,
                            $user_id
                        ]);
                        
                        if ($update_result) {
                            // Log the reschedule (optional - you could create a reschedule history table)
                            $log_query = "
                                INSERT INTO appointment_reschedules 
                                (appointment_id, old_date, old_time, new_date, new_time, rescheduled_at)
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ";
                            // You would need to create this table first if you want to track reschedules
                            
                            $pdo->commit();
                            
                            $success = 'Appointment rescheduled successfully!';
                            
                            // Redirect after 2 seconds
                            header('refresh:2;url=appointments.php?rescheduled=1');
                        } else {
                            $pdo->rollBack();
                            $error = 'Failed to reschedule appointment. Please try again.';
                        }
                    }
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('Appointment reschedule error: ' . $e->getMessage());
                $error = 'Failed to reschedule appointment. Please try again.';
            }
        }
    }
    
} catch (PDOException $e) {
    error_log('Reschedule page error: ' . $e->getMessage());
    $error = 'Unable to load appointment details. Please try again later.';
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

include 'header/headerBooking.php';
?>
<!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-calendar-plus me-2"></i> Reschedule My Appointments</h1>
                    <p class="lead mb-0">View and manage your salon appointments</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-primary me-2" onclick="goToDashboard()">
                            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                        </button>
                        <button class="btn btn-outline-secondary" onclick="refreshPage()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Messages -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-custom alert-dismissible fade show success-pulse" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <div class="mt-3">
                <a href="appointments.php" class="btn btn-light me-2">
                    <i class="fas fa-calendar-alt me-2"></i> View My Appointments
                </a>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($appointment && !$success): ?>
        <!-- Original Appointment Summary -->
        <div class="original-appointment-card mb-4">
            <h4><i class="fas fa-history me-2"></i> Original Appointment Details</h4>
            <div class="row">
                <div class="col-md-3">
                    <div class="detail-label">Service</div>
                    <div class="detail-value"><?php echo htmlspecialchars($appointment['service_name']); ?></div>
                </div>
                <div class="col-md-2">
                    <div class="detail-label">Date</div>
                    <div class="detail-value"><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></div>
                </div>
                <div class="col-md-2">
                    <div class="detail-label">Time</div>
                    <div class="detail-value"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></div>
                </div>
                <div class="col-md-2">
                    <div class="detail-label">Duration</div>
                    <div class="detail-value"><?php echo $appointment['service_duration']; ?> mins</div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Price</div>
                    <div class="detail-value price">Rs: <?php echo number_format($appointment['service_price'], 2); ?></div>
                </div>
            </div>
            <?php if (!empty($appointment['notes'])): ?>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="detail-label">Notes</div>
                    <div class="detail-value"><?php echo htmlspecialchars($appointment['notes']); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="row">
            <!-- Left Column - Reschedule Form -->
            <div class="col-lg-8">
                <form method="POST" action="" id="rescheduleForm">
                    <div class="booking-card">
                        <!-- Step 1: Select Service (Optional) -->
                        <div class="section-header">
                            <span class="step-number">1</span>
                            <h3>Change Service (Optional)</h3>
                        </div>
                        <p class="text-muted mb-3">You can keep the same service or choose a different one.</p>
                        
                        <?php if (empty($services)): ?>
                        <div class="empty-state">
                            <i class="fas fa-spa"></i>
                            <h4>No Services Available</h4>
                            <p>Check back later for available services.</p>
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php 
                            $categories = [];
                            foreach ($services as $service) {
                                $category = $service['category'] ?: 'General';
                                $categories[$category][] = $service;
                            }
                            
                            foreach ($categories as $category => $category_services): 
                            ?>
                            <div class="col-12 mb-4">
                                <div class="category-badge">
                                    <i class="fas fa-star me-2"></i><?php echo htmlspecialchars($category); ?>
                                </div>
                                <div class="row">
                                    <?php foreach ($category_services as $service): ?>
                                    <div class="col-md-6">
                                        <div class="service-card <?php echo ($service['id'] == $appointment['service_id']) ? 'current-service' : ''; ?>" 
                                             data-service-id="<?php echo $service['id']; ?>"
                                             data-service-name="<?php echo htmlspecialchars($service['name']); ?>"
                                             data-service-price="<?php echo number_format($service['price'], 2); ?>"
                                             data-service-duration="<?php echo $service['duration']; ?>">
                                            <?php if ($service['id'] == $appointment['service_id']): ?>
                                            <span class="current-badge">Current Service</span>
                                            <?php endif; ?>
                                            <div class="service-category"><?php echo htmlspecialchars($category); ?></div>
                                            <h5 class="service-title"><?php echo htmlspecialchars($service['name']); ?></h5>
                                            <p class="service-description">
                                                <?php echo htmlspecialchars($service['description'] ?: 'Professional service by our experts'); ?>
                                            </p>
                                            <div class="service-meta">
                                                <span class="service-duration">
                                                    <i class="fas fa-clock"></i> <?php echo $service['duration']; ?> mins
                                                </span>
                                                <span class="service-price">
                                                    Rs: <?php echo number_format($service['price'], 2); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="service_id" id="selectedServiceId" value="<?php echo $appointment['service_id']; ?>">
                        <?php endif; ?>
                        
                        <!-- Step 2: Select New Date & Time -->
                        <div class="section-header mt-5">
                            <span class="step-number">2</span>
                            <h3>Select New Date & Time</h3>
                        </div>
                        
                        <div class="datetime-selector">
                            <div class="row">
                                <!-- Date Selection -->
                                <div class="col-md-6 mb-4">
                                    <label for="appointment_date" class="form-label">Select New Date</label>
                                    <div class="date-picker-container">
                                        <input type="text" 
                                               class="form-control flatpickr-input" 
                                               id="appointment_date" 
                                               name="appointment_date" 
                                               value=""
                                               placeholder="Choose your preferred date"
                                               required
                                               readonly>
                                    </div>
                                    <small class="text-muted mt-2 d-block">
                                        <i class="fas fa-info-circle"></i> Available Monday to Saturday
                                    </small>
                                </div>
                                
                                <!-- Time Selection -->
                                <div class="col-md-6">
                                    <label class="form-label">Select New Time</label>
                                    <div class="time-slots-container">
                                        <div class="time-slots-grid" id="timeSlotsContainer">
                                            <?php foreach ($time_slots as $time): 
                                                $formattedTime = date('g:i A', strtotime($time));
                                            ?>
                                            <div class="time-slot <?php echo ($time == $appointment['appointment_time']) ? 'current-time' : ''; ?>" data-time="<?php echo $time; ?>">
                                                <?php echo $formattedTime; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="appointment_time" id="selectedTime" value="">
                                        <small class="text-muted mt-2 d-block">
                                            <i class="fas fa-clock"></i> 30-minute intervals, 9 AM - 8 PM
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 3: Update Notes -->
                        <div class="section-header mt-5">
                            <span class="step-number">3</span>
                            <h3>Update Notes (Optional)</h3>
                        </div>
                        
                        <div class="notes-container">
                            <label for="notes" class="form-label">Special Requests</label>
                            <textarea class="form-control" 
                                      id="notes" 
                                      name="notes" 
                                      rows="4" 
                                      placeholder="Any special instructions, allergies, or preferences for our staff..."><?php echo htmlspecialchars($appointment['notes']); ?></textarea>
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-lightbulb"></i> Let us know how we can make your experience better
                            </small>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <button type="button" class="btn-cancel" onclick="cancelReschedule()">
                                <i class="fas fa-times me-2"></i> Cancel
                            </button>
                            <button type="button" class="btn-reschedule" id="submitBtn" disabled>
                                <i class="fas fa-calendar-check me-2"></i> Confirm Reschedule
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Right Column - Summary -->
            <div class="col-lg-4">
                <div class="summary-sidebar">
                    <div class="summary-header">
                        <h4><i class="fas fa-sync-alt me-2"></i> Reschedule Summary</h4>
                        <p class="text-muted mb-0">Review your changes</p>
                    </div>
                    
                    <div id="summaryContent">
                        <div class="summary-item">
                            <span class="summary-label">Service:</span>
                            <span class="summary-value" id="summaryService"><?php echo htmlspecialchars($appointment['service_name']); ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">New Date:</span>
                            <span class="summary-value" id="summaryDate">Not selected</span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">New Time:</span>
                            <span class="summary-value" id="summaryTime">Not selected</span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Duration:</span>
                            <span class="summary-value" id="summaryDuration"><?php echo $appointment['service_duration']; ?> mins</span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Price:</span>
                            <span class="summary-value" id="summaryPrice">Rs: <?php echo number_format($appointment['service_price'], 2); ?></span>
                        </div>
                        
                        <div class="summary-total">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Total Amount:</span>
                                <span class="value" id="summaryTotal">Rs: <?php echo number_format($appointment['service_price'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reschedule Policy -->
                    <div class="policy-section mt-4">
                        <h5><i class="fas fa-info-circle"></i> Reschedule Policy</h5>
                        <ul class="policy-list">
                            <li><i class="fas fa-check-circle"></i> Free reschedule up to 24 hours before</li>
                            <li><i class="fas fa-exclamation-circle"></i> Late reschedule may incur a fee</li>
                            <li><i class="fas fa-clock"></i> New time must be at least 2 hours from now</li>
                            <li><i class="fas fa-sync-alt"></i> You can reschedule up to 3 times</li>
                        </ul>
                    </div>
                    
                    <!-- Contact Info -->
                    <div class="mt-4 text-center">
                        <p class="text-muted mb-2">
                            <i class="fas fa-headset me-2"></i> Need help?
                        </p>
                        <a href="tel:0718059219" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-phone me-2"></i> Call Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif (!$success): ?>
        <div class="empty-state">
            <i class="fas fa-exclamation-circle"></i>
            <h3>Appointment Not Found</h3>
            <p>The appointment you're trying to reschedule doesn't exist or cannot be rescheduled.</p>
            <a href="appointments.php" class="btn-primary mt-3">
                <i class="fas fa-arrow-left me-2"></i> Back to Appointments
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="mt-3 text-primary fw-bold">Processing your reschedule...</div>
    </div>

    <?php include 'footer/footer.php'; ?>
    
    <script>
        // Global variables
        let selectedService = {
            id: <?php echo $appointment ? $appointment['service_id'] : 0; ?>,
            name: '<?php echo $appointment ? addslashes($appointment['service_name']) : ''; ?>',
            price: <?php echo $appointment ? $appointment['service_price'] : 0; ?>,
            duration: <?php echo $appointment ? $appointment['service_duration'] : 0; ?>
        };
        let selectedDate = null;
        let selectedTime = null;
        let bookedSlots = <?php echo json_encode($booked_slots); ?>;
        const originalDate = '<?php echo $appointment ? $appointment['appointment_date'] : ''; ?>';
        const originalTime = '<?php echo $appointment ? $appointment['appointment_time'] : ''; ?>';
        
        $(document).ready(function() {
            // Toggle sidebar on mobile
            $('#sidebarToggle').click(function() {
                $('#sidebar').toggleClass('active');
                $('#mainContent').toggleClass('active');
            });

            // Close sidebar when clicking outside on mobile
            $(document).click(function(event) {
                if ($(window).width() <= 768) {
                    if (!$(event.target).closest('#sidebar').length && 
                        !$(event.target).is('#sidebarToggle') &&
                        $('#sidebar').hasClass('active')) {
                        $('#sidebar').removeClass('active');
                        $('#mainContent').removeClass('active');
                    }
                }
            });
            
            // Initialize date picker
            const datePicker = flatpickr("#appointment_date", {
                minDate: "today",
                maxDate: new Date().fp_incr(60),
                disable: [
                    function(date) {
                        // Disable Sundays (optional)
                        // return date.getDay() === 1;
                    }
                ],
                onChange: function(selectedDates, dateStr, instance) {
                    selectedDate = dateStr;
                    updateDateSummary(dateStr);
                    checkAvailability(dateStr);
                    updateSubmitButton();
                }
            });
            
            // Service selection
            $(document).on('click', '.service-card', function() {
                $('.service-card').removeClass('selected');
                $(this).addClass('selected');
                
                selectedService = {
                    id: $(this).data('service-id'),
                    name: $(this).data('service-name'),
                    price: $(this).data('service-price'),
                    duration: $(this).data('service-duration')
                };
                
                $('#selectedServiceId').val(selectedService.id);
                updateServiceSummary();
                updateSubmitButton();
            });
            
            // Time slot selection
            $(document).on('click', '.time-slot:not(.booked)', function() {
                $('.time-slot').removeClass('selected');
                $(this).addClass('selected');
                
                selectedTime = $(this).data('time');
                $('#selectedTime').val(selectedTime);
                updateTimeSummary();
                updateSubmitButton();
            });
            
            // Check availability for selected date
            function checkAvailability(date) {
                if (!date) return;
                
                showLoading();
                
                $.ajax({
                    url: 'ajax/check-availability.php',
                    method: 'POST',
                    data: { 
                        date: date,
                        current_time: selectedTime,
                        exclude_appointment: <?php echo $appointment_id; ?>
                    },
                    success: function(response) {
                        hideLoading();
                        try {
                            const data = JSON.parse(response);
                            if (data.success) {
                                bookedSlots = data.bookedSlots || {};
                                updateTimeSlots();
                            }
                        } catch (e) {
                            console.error('Error parsing availability data:', e);
                        }
                    },
                    error: function() {
                        hideLoading();
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Unable to check availability. Please try again.',
                            confirmButtonColor: '#6f42c1'
                        });
                    }
                });
            }
            
            // Update time slots based on availability
            function updateTimeSlots() {
                $('.time-slot').each(function() {
                    const time = $(this).data('time');
                    const slotKey = selectedDate + ' ' + time;
                    
                    // Don't mark the original time as booked for this appointment
                    if (selectedDate === originalDate && time === originalTime) {
                        $(this).removeClass('booked').removeClass('selected');
                    } else if (bookedSlots[slotKey]) {
                        $(this).addClass('booked').removeClass('selected');
                    } else {
                        $(this).removeClass('booked');
                    }
                });
                
                // If currently selected time is now booked, deselect it
                if (selectedTime && bookedSlots[selectedDate + ' ' + selectedTime]) {
                    $('.time-slot.selected').removeClass('selected');
                    selectedTime = null;
                    $('#selectedTime').val('');
                    updateTimeSummary();
                    updateSubmitButton();
                    
                    Swal.fire({
                        icon: 'warning',
                        title: 'Time Slot Unavailable',
                        text: 'The selected time has been booked. Please choose another time.',
                        confirmButtonColor: '#6f42c1'
                    });
                }
            }
            
            // Update submit button state
            function updateSubmitButton() {
                const hasChanges = hasRescheduleChanges();
                $('#submitBtn').prop('disabled', !hasChanges);
            }
            
            // Check if there are actual changes
            function hasRescheduleChanges() {
                if (!selectedDate || !selectedTime) return false;
                
                const serviceChanged = selectedService.id !== <?php echo $appointment ? $appointment['service_id'] : 0; ?>;
                const dateChanged = selectedDate !== originalDate;
                const timeChanged = selectedTime !== originalTime;
                
                return serviceChanged || dateChanged || timeChanged;
            }
            
            // Update service summary
            function updateServiceSummary() {
                if (selectedService) {
                    $('#summaryService').text(selectedService.name);
                    $('#summaryDuration').text(selectedService.duration + ' minutes');
                    $('#summaryPrice').text('Rs: ' + selectedService.price);
                    $('#summaryTotal').text('Rs: ' + selectedService.price);
                }
            }
            
            // Update date summary
            function updateDateSummary(date) {
                if (date) {
                    const formattedDate = formatDate(date);
                    $('#summaryDate').text(formattedDate);
                } else {
                    $('#summaryDate').text('Not selected');
                }
            }
            
            // Update time summary
            function updateTimeSummary() {
                if (selectedTime) {
                    $('#summaryTime').text(formatTime(selectedTime));
                } else {
                    $('#summaryTime').text('Not selected');
                }
            }
            
            // Format date for display
            function formatDate(dateStr) {
                const date = new Date(dateStr);
                return date.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
            }
            
            // Format time for display
            function formatTime(timeStr) {
                const time = new Date('2000-01-01 ' + timeStr);
                return time.toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit',
                    hour12: true 
                });
            }
            
            // Submit button click handler
            $('#submitBtn').click(function(e) {
                e.preventDefault();
                
                if (!selectedDate || !selectedTime) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Incomplete Form',
                        text: 'Please select a new date and time to continue.',
                        confirmButtonColor: '#6f42c1'
                    });
                    return false;
                }
                
                // Check if there are actual changes
                if (!hasRescheduleChanges()) {
                    Swal.fire({
                        icon: 'info',
                        title: 'No Changes',
                        text: 'You haven\'t made any changes to reschedule.',
                        confirmButtonColor: '#6f42c1'
                    });
                    return false;
                }
                
                // Double-check availability
                const slotKey = selectedDate + ' ' + selectedTime;
                if (bookedSlots[slotKey] && !(selectedDate === originalDate && selectedTime === originalTime)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Time Slot Unavailable',
                        text: 'This time slot has just been booked. Please select another time.',
                        confirmButtonColor: '#6f42c1'
                    }).then(() => {
                        checkAvailability(selectedDate);
                    });
                    return false;
                }
                
                // Show confirmation dialog
                let changes = [];
                if (selectedService.id !== <?php echo $appointment ? $appointment['service_id'] : 0; ?>) {
                    changes.push('• Service changed to ' + selectedService.name);
                }
                if (selectedDate !== originalDate) {
                    changes.push('• Date changed to ' + formatDate(selectedDate));
                }
                if (selectedTime !== originalTime) {
                    changes.push('• Time changed to ' + formatTime(selectedTime));
                }
                
                Swal.fire({
                    title: 'Confirm Reschedule',
                    html: `
                        <div class="text-start p-3">
                            <div class="alert alert-warning border">
                                <h6 class="mb-2 text-warning">Changes to be made:</h6>
                                ${changes.map(change => `<p class="mb-1">${change}</p>`).join('')}
                            </div>
                            <p class="text-muted mt-3 small">
                                <i class="fas fa-info-circle"></i> Your appointment will be updated immediately.
                            </p>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Reschedule',
                    cancelButtonText: 'Review Changes',
                    confirmButtonColor: '#6f42c1',
                    cancelButtonColor: '#6c757d',
                    reverseButtons: true,
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        showLoading();
                        
                        // Submit the form
                        $('#selectedServiceId').val(selectedService.id);
                        $('#appointment_date').val(selectedDate);
                        $('#selectedTime').val(selectedTime);
                        $('#rescheduleForm').get(0).submit();
                    }
                });
                
                return false;
            });
            
            // Cancel reschedule
            window.cancelReschedule = function() {
                Swal.fire({
                    title: 'Cancel Reschedule?',
                    text: 'Any changes will be lost. Continue?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Go Back',
                    cancelButtonText: 'Stay Here',
                    confirmButtonColor: '#6f42c1'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'appointments.php';
                    }
                });
            };
            
            // Mark original time
            $('.time-slot[data-time="<?php echo $appointment ? $appointment['appointment_time'] : ''; ?>"]').addClass('original-time').attr('title', 'Your current appointment time');
            
            // Initial check for any pre-selected values
            initializeForm();
        });
        
        // Initialize form
        function initializeForm() {
            // Mark current service as selected
            $('.service-card.current-service').addClass('selected');
        }
        
        // Loading functions
        function showLoading() {
            $('#loadingOverlay').fadeIn();
        }
        
        function hideLoading() {
            $('#loadingOverlay').fadeOut();
        }
        
        // Prevent form submission on enter key
        $(document).keypress(function(e) {
            if (e.which === 13) {
                e.preventDefault();
                return false;
            }
        });
    </script>
    
    <style>
        /* Additional styles for reschedule page */
        
        .original-appointment-card {
            background: linear-gradient(135deg, #fff3cd, #fff9e6);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 5px solid #ffc107;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .original-appointment-card h4 {
            color: #856404;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .detail-label {
            font-size: 0.85rem;
            color: #856404;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #856404;
        }
        
        .detail-value.price {
            color: #6f42c1;
            font-size: 1.2rem;
        }
        
        .service-card.current-service {
            border-color: #ffc107;
            background: linear-gradient(to right, rgba(255, 193, 7, 0.05), transparent);
        }
        
        .current-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #ffc107;
            color: #856404;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 1;
        }
        
        .service-card.current-service::after {
            content: '✓ Current';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ffc107;
            color: #856404;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .time-slot.current-time {
            background: #fff3cd;
            color: #856404;
            border-color: #ffc107;
            position: relative;
        }
        
        .time-slot.current-time::after {
            content: 'Current';
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ffc107;
            color: #856404;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .time-slot.original-time {
            background: #e2e3e5;
            color: #383d41;
            border-color: #d6d8db;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-cancel {
            background: white;
            color: #dc3545;
            border: 2px solid #dc3545;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            background: #dc3545;
            color: white;
        }
        
        .btn-reschedule {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(111, 66, 193, 0.2);
            flex: 1;
        }
        
        .btn-reschedule:hover:not(:disabled) {
            background: #5a32a0;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(111, 66, 193, 0.3);
        }
        
        .btn-reschedule:disabled {
            background: #adb5bd;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .policy-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
            border: 1px solid #e9ecef;
        }
        
        .policy-section h5 {
            color: var(--dark-bg);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .policy-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .policy-list li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-light);
            font-size: 0.95rem;
        }
        
        .policy-list li i.fa-check-circle {
            color: #28a745;
        }
        
        .policy-list li i.fa-exclamation-circle {
            color: #ffc107;
        }
        
        .policy-list li i.fa-clock {
            color: #17a2b8;
        }
        
        .policy-list li i.fa-sync-alt {
            color: #6f42c1;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .original-appointment-card .row > div {
                margin-bottom: 15px;
            }
            
            .detail-label {
                font-size: 0.8rem;
            }
            
            .detail-value {
                font-size: 1rem;
            }
        }
    </style>
</body>
</html>
<?php ob_end_flush(); ?>
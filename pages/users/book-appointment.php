<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
$services = [];
$time_slots = [];
$booked_slots = [];
$user_booked_slots = [];
$all_user_appointments = [];

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
    
    // Get all active services
    $services_query = "SELECT * FROM services WHERE status = 'active' ORDER BY category, name";
    $services_stmt = $pdo->query($services_query);
    $services = $services_stmt->fetchAll();
    
    // Generate time slots (9 AM to 8 PM, 30-minute intervals)
    $start_time = strtotime('09:00');
    $end_time = strtotime('20:00');
    $interval = 30 * 60;
    
    for ($time = $start_time; $time <= $end_time; $time += $interval) {
        $time_slots[] = date('H:i', $time);
    }
    
    // Get user's existing appointments for ALL future dates
    $user_appointments_query = "
        SELECT appointment_date, appointment_time, service_id 
        FROM appointments 
        WHERE user_id = ? 
        AND appointment_date >= CURDATE() 
        AND status IN ('pending', 'confirmed')
        ORDER BY appointment_date, appointment_time
    ";
    $user_appointments_stmt = $pdo->prepare($user_appointments_query);
    $user_appointments_stmt->execute([$user_id]);
    $user_appointments = $user_appointments_stmt->fetchAll();
    
    // Store user's booked slots for quick lookup - KEY FIX: Store by date and time
    foreach ($user_appointments as $apt) {
        $slot_key = $apt['appointment_date'] . '|' . $apt['appointment_time']; // Use pipe separator
        $user_booked_slots[$slot_key] = [
            'date' => $apt['appointment_date'],
            'time' => $apt['appointment_time'],
            'service_id' => $apt['service_id']
        ];
    }
    
    // Get user's existing appointments for display in sidebar (with service names)
    $user_display_query = "
        SELECT a.appointment_date, a.appointment_time, s.name as service_name
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.user_id = ? 
        AND a.appointment_date >= CURDATE() 
        AND a.status IN ('pending', 'confirmed')
        ORDER BY a.appointment_date, a.appointment_time
        LIMIT 10
    ";
    $user_display_stmt = $pdo->prepare($user_display_query);
    $user_display_stmt->execute([$user_id]);
    $all_user_appointments = $user_display_stmt->fetchAll();
    
    // Get booked slots with capacity information for all users
    $today = date('Y-m-d');
    $booked_query = "
        SELECT appointment_date, appointment_time, COUNT(*) as count 
        FROM appointments 
        WHERE appointment_date >= ? 
        AND status IN ('pending', 'confirmed')
        GROUP BY appointment_date, appointment_time
        ORDER BY appointment_date, appointment_time";
    $booked_stmt = $pdo->prepare($booked_query);
    $booked_stmt->execute([$today]);
    $booked_results = $booked_stmt->fetchAll();
    
    $max_capacity = 10;
    
    foreach ($booked_results as $slot) {
        $booked_slots[$slot['appointment_date'] . '|' . $slot['appointment_time']] = [
            'booked' => true,
            'count' => $slot['count'],
            'max_capacity' => $max_capacity,
            'remaining' => $max_capacity - $slot['count']
        ];
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $service_id = $_POST['service_id'] ?? '';
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        // Validation
        $valid = true;
        
        if (empty($service_id)) {
            $error = 'Please select a service';
            $valid = false;
        }
        
        if (empty($appointment_date)) {
            $error = 'Please select a date';
            $valid = false;
        }
        
        if (empty($appointment_time)) {
            $error = 'Please select a time';
            $valid = false;
        }
        
        if ($valid && strtotime($appointment_date) < strtotime('today')) {
            $error = 'Appointment date cannot be in the past';
            $valid = false;
        }
        
        if ($valid) {
            try {
                // Check if service exists
                $service_check = $pdo->prepare("SELECT * FROM services WHERE id = ? AND status = 'active'");
                $service_check->execute([$service_id]);
                $service = $service_check->fetch();
                
                if (!$service) {
                    $error = 'Selected service is not available';
                    $valid = false;
                }
                
                // CRITICAL FIX: Check if user already has an appointment at this exact date and time
                $slot_key = $appointment_date . '|' . $appointment_time;
                
                if (isset($user_booked_slots[$slot_key])) {
                    $existing = $user_booked_slots[$slot_key];
                    $error = 'You already have an appointment scheduled at this time!';
                    $error .= '<br><small>Existing: ' . date('M d, Y', strtotime($existing['date'])) . 
                               ' at ' . date('g:i A', strtotime($existing['time'])) . '</small>';
                    $valid = false;
                }
                // Check if time slot is at capacity
                else if (isset($booked_slots[$slot_key]) && $booked_slots[$slot_key]['count'] >= $max_capacity) {
                    $error = 'This time slot is fully booked. Please choose another time.';
                    $valid = false;
                }
            } catch (PDOException $e) {
                error_log('Appointment validation error: ' . $e->getMessage());
                $error = 'Failed to validate appointment. Please try again.';
                $valid = false;
            }
            
            if ($valid) {
                try {
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Double-check one more time to prevent race condition
                    $check_slot = "SELECT COUNT(*) as count FROM appointments 
                                   WHERE user_id = ? AND appointment_date = ? AND appointment_time = ? 
                                   AND status IN ('pending', 'confirmed')";
                    $check_stmt = $pdo->prepare($check_slot);
                    $check_stmt->execute([$user_id, $appointment_date, $appointment_time]);
                    $existing_count = $check_stmt->fetch()['count'];
                    
                    if ($existing_count > 0) {
                        $pdo->rollBack();
                        $error = 'You already have an appointment at this time. Please choose a different time slot.';
                        $valid = false;
                    } else {
                        // Insert the appointment
                        $insert_query = "
                            INSERT INTO appointments 
                            (user_id, service_id, appointment_date, appointment_time, notes, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                        ";
                        
                        $insert_stmt = $pdo->prepare($insert_query);
                        if ($insert_stmt->execute([
                            $user_id,
                            $service_id,
                            $appointment_date,
                            $appointment_time,
                            $notes
                        ])) {
                            $appointment_id = $pdo->lastInsertId();
                            $pdo->commit();
                            
                            $success = 'Appointment booked successfully! Your appointment ID is #' . $appointment_id;
                            
                            // Clear form
                            $service_id = '';
                            $appointment_date = '';
                            $appointment_time = '';
                            $notes = '';
                            
                            // Refresh user's booked slots
                            $user_appointments_stmt->execute([$user_id]);
                            $user_booked_slots = [];
                            foreach ($user_appointments_stmt->fetchAll() as $apt) {
                                $slot_key = $apt['appointment_date'] . '|' . $apt['appointment_time'];
                                $user_booked_slots[$slot_key] = [
                                    'date' => $apt['appointment_date'],
                                    'time' => $apt['appointment_time'],
                                    'service_id' => $apt['service_id']
                                ];
                            }
                            
                            // Refresh user display appointments
                            $user_display_stmt->execute([$user_id]);
                            $all_user_appointments = $user_display_stmt->fetchAll();
                            
                            // Refresh booked slots
                            $booked_stmt->execute([$today]);
                            $booked_slots = [];
                            foreach ($booked_stmt->fetchAll() as $slot) {
                                $booked_slots[$slot['appointment_date'] . '|' . $slot['appointment_time']] = [
                                    'booked' => true,
                                    'count' => $slot['count'],
                                    'max_capacity' => $max_capacity,
                                    'remaining' => $max_capacity - $slot['count']
                                ];
                            }
                        } else {
                            $pdo->rollBack();
                            $error = 'Failed to book appointment. Please try again.';
                        }
                    }
                } catch (PDOException $e) {
                    if (isset($pdo) && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Appointment booking error: ' . $e->getMessage());
                    $error = 'Failed to book appointment. Please try again.';
                }
            }
        }
    }
    
} catch (PDOException $e) {
    error_log('Book appointment page error: ' . $e->getMessage());
    $error = 'Unable to load booking form. Please try again later.';
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Make sure all_user_appointments is defined
if (!isset($all_user_appointments)) {
    $all_user_appointments = [];
}

include 'header/headerBooking.php';
?>
<!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-calendar-plus me-2"></i> Book Your Appointment</h1>
                    <p class="lead mb-0">Schedule your salon appointment with ease</p>
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

        <!-- User's Existing Appointments Warning -->
        <?php if (!empty($user_booked_slots) && empty($success)): ?>
        <div class="alert alert-info alert-custom alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i> <strong>Your existing appointments:</strong>
            <ul class="mb-0 mt-2">
                <?php 
                $displayed = 0;
                foreach ($user_booked_slots as $slot_key => $slot_info):
                    if ($displayed < 5):
                        $date = $slot_info['date'];
                        $time = $slot_info['time'];
                ?>
                <li><?php echo date('l, M d, Y', strtotime($date)); ?> at <?php echo date('g:i A', strtotime($time)); ?></li>
                <?php 
                        $displayed++;
                    endif;
                endforeach;
                if (count($user_booked_slots) > 5):
                ?>
                <li>And <?php echo count($user_booked_slots) - 5; ?> more...</li>
                <?php endif; ?>
            </ul>
            <hr class="my-2">
            <small class="text-muted d-block">
                <i class="fas fa-exclamation-triangle text-warning me-1"></i> 
                <strong>Important:</strong> You cannot book multiple appointments at the same time slot.
            </small>
        </div>
        <?php endif; ?>

        <!-- Messages -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-custom alert-dismissible fade show success-pulse" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <div class="mt-3">
                <a href="dashboard.php" class="btn btn-light me-2">
                    <i class="fas fa-tachometer-alt me-2"></i> Go to Dashboard
                </a>
                <a href="book-appointment.php" class="btn btn-outline-light">
                    <i class="fas fa-plus me-2"></i> Book Another
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

        <?php if (!$success): ?>
        <div class="row">
            <!-- Left Column - Booking Form -->
            <div class="col-lg-8">
                <form method="POST" action="" id="bookingForm">
                    <div class="booking-card">
                        <!-- Step 1: Select Service -->
                        <div class="section-header">
                            <span class="step-number">1</span>
                            <h3>Choose Your Service</h3>
                        </div>
                        
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
                                        <div class="service-card" 
                                             data-service-id="<?php echo $service['id']; ?>"
                                             data-service-name="<?php echo htmlspecialchars($service['name']); ?>"
                                             data-service-price="<?php echo number_format($service['price'], 2); ?>"
                                             data-service-duration="<?php echo $service['duration']; ?>">
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
                        <input type="hidden" name="service_id" id="selectedServiceId" value="<?php echo isset($_POST['service_id']) ? htmlspecialchars($_POST['service_id']) : ''; ?>">
                        <?php endif; ?>
                        
                        <!-- Step 2: Select Date & Time -->
                        <div class="section-header mt-5">
                            <span class="step-number">2</span>
                            <h3>Select Date & Time</h3>
                        </div>
                        
                        <div class="datetime-selector">
                            <div class="row">
                                <!-- Date Selection -->
                                <div class="col-md-6 mb-4">
                                    <label for="appointment_date" class="form-label">Select Date</label>
                                    <div class="date-picker-container">
                                        <input type="text" 
                                               class="form-control flatpickr-input" 
                                               id="appointment_date" 
                                               name="appointment_date" 
                                               value="<?php echo isset($_POST['appointment_date']) ? htmlspecialchars($_POST['appointment_date']) : ''; ?>"
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
                                    <label class="form-label">Select Time</label>
                                    <div class="time-slots-container">
                                        <div class="time-slots-grid" id="timeSlotsContainer">
                                            <?php foreach ($time_slots as $time): 
                                                $formattedTime = date('g:i A', strtotime($time));
                                            ?>
                                            <div class="time-slot" data-time="<?php echo $time; ?>">
                                                <?php echo $formattedTime; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="appointment_time" id="selectedTime" value="<?php echo isset($_POST['appointment_time']) ? htmlspecialchars($_POST['appointment_time']) : ''; ?>">
                                        <small class="text-muted mt-2 d-block">
                                            <i class="fas fa-clock"></i> 30-minute intervals, 9 AM - 8 PM
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 3: Additional Notes -->
                        <div class="section-header mt-5">
                            <span class="step-number">3</span>
                            <h3>Additional Information</h3>
                        </div>
                        
                        <div class="notes-container">
                            <label for="notes" class="form-label">Special Requests (Optional)</label>
                            <textarea class="form-control" 
                                      id="notes" 
                                      name="notes" 
                                      rows="4" 
                                      placeholder="Any special instructions, allergies, or preferences for our staff..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-lightbulb"></i> Let us know how we can make your experience better
                            </small>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <button type="button" class="btn-reset" id="resetBtn">
                                <i class="fas fa-redo me-2"></i> Reset Form
                            </button>
                            <button type="button" class="btn-book" id="submitBtn" disabled>
                                <i class="fas fa-calendar-check me-2"></i> Book Appointment Now
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Right Column - Summary -->
            <div class="col-lg-4">
                <div class="summary-sidebar">
                    <div class="summary-header">
                        <h4><i class="fas fa-receipt me-2"></i> Booking Summary</h4>
                        <p class="text-muted mb-0">Review your appointment details</p>
                    </div>
                    
                    <div id="summaryContent">
                        <div class="summary-item">
                            <span class="summary-label">Service:</span>
                            <span class="summary-value" id="summaryService">Not selected</span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Date:</span>
                            <span class="summary-value" id="summaryDate">Not selected</span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Time:</span>
                            <span class="summary-value" id="summaryTime">Not selected</span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Duration:</span>
                            <span class="summary-value" id="summaryDuration">-</span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Price:</span>
                            <span class="summary-value" id="summaryPrice">Rs: 0.00</span>
                        </div>
                        
                        <div class="summary-total">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Total Amount:</span>
                                <span class="value" id="summaryTotal">Rs: 0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User's Existing Appointments Summary -->
                    <?php if (!empty($user_booked_slots)): ?>
                    <div class="existing-appointments-summary mt-3">
                        <div class="alert alert-warning p-2 small">
                            <i class="fas fa-calendar-alt me-1"></i> 
                            <strong>You have existing appointments:</strong>
                            <ul class="mb-0 mt-1 ps-3">
                                <?php 
                                $displayed = 0;
                                foreach ($user_booked_slots as $slot_key => $slot_info):
                                    if ($displayed < 3):
                                        $date = $slot_info['date'];
                                        $time = $slot_info['time'];
                                ?>
                                <li><?php echo date('M d', strtotime($date)); ?> at <?php echo date('g:i A', strtotime($time)); ?></li>
                                <?php 
                                        $displayed++;
                                    endif;
                                endforeach;
                                if (count($user_booked_slots) > 3):
                                ?>
                                <li>And <?php echo count($user_booked_slots) - 3; ?> more...</li>
                                <?php endif; ?>
                            </ul>
                            <hr class="my-1">
                            <small class="text-danger">
                                <i class="fas fa-ban me-1"></i> Cannot book multiple appointments at the same time
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Capacity Info -->
                    <div class="capacity-info mt-3">
                        <small class="text-muted">
                            <i class="fas fa-users me-1"></i> 
                            Maximum <strong>10</strong> appointments per time slot
                        </small>
                    </div>
                    
                    <!-- Tips Section -->
                    <div class="tips-section">
                        <h5><i class="fas fa-lightbulb"></i> Booking Tips</h5>
                        <ul class="tips-list">
                            <li><i class="fas fa-check-circle"></i> Arrive 15 minutes early</li>
                            <li><i class="fas fa-check-circle"></i> Cancel 24 hours in advance</li>
                            <li><i class="fas fa-check-circle"></i> <strong class="text-warning">One appointment per time slot</strong></li>
                            <li><i class="fas fa-check-circle"></i> Free consultation included</li>
                        </ul>
                    </div>
                    
                    <!-- Contact Info -->
                    <div class="mt-4 text-center">
                        <p class="text-muted mb-2">
                            <i class="fas fa-headset me-2"></i> Need assistance?
                        </p>
                        <a href="tel:0718059219" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-phone me-2"></i> Call Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="mt-3 text-primary fw-bold">Processing your booking...</div>
    </div>

    <?php include 'footer/footer.php'; ?>
    
 <script>
    // Global variables
    let selectedService = null;
    let selectedDate = null;
    let selectedTime = null;
    let bookedSlotsData = {};
    let userBookedTimes = {}; // User's booked times for selected date
    let userAppointments = <?php echo json_encode($all_user_appointments ?? []); ?>;
    let maxCapacity = 10;
    let availableStaffCount = {};
    let timeSlotsData = [];
    
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
            
            // Re-check availability if date is selected
            if (selectedDate) {
                checkAvailability(selectedDate);
            }
        });
        
        // Time slot selection - FIXED to prevent selection of user-booked slots
$(document).on('click', '.time-slot', function() {
    // Check if this time slot is already booked by the user
    if ($(this).hasClass('user-booked')) {
        const time = $(this).data('time');
        Swal.fire({
            icon: 'error',
            title: 'Cannot Book Multiple Appointments!',
            html: `
                <div class="text-start">
                    <p><strong>You already have an appointment at ${formatTime(time)} on ${formatDate(selectedDate)}</strong></p>
                    <div class="alert alert-warning mt-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        You can only book ONE appointment per time slot.
                    </div>
                    <p class="text-muted mt-2">Please choose a different time or date.</p>
                </div>
            `,
            confirmButtonText: 'Choose Different Time',
            confirmButtonColor: '#6f42c1'
        });
        return false;
    }
    
    // Check if slot is full
    if ($(this).hasClass('full')) {
        Swal.fire({
            icon: 'warning',
            title: 'Time Slot Full',
            text: 'This time slot is fully booked. Please choose another time.',
            confirmButtonColor: '#6f42c1'
        });
        return false;
    }
    
    // Check if slot is unavailable
    if ($(this).hasClass('unavailable')) {
        Swal.fire({
            icon: 'info',
            title: 'Not Available',
            text: 'No staff available at this time.',
            confirmButtonColor: '#6f42c1'
        });
        return false;
    }
    
    // Select the time slot
    $('.time-slot').removeClass('selected');
    $(this).addClass('selected');
    
    selectedTime = $(this).data('time');
    $('#selectedTime').val(selectedTime);
    updateTimeSummary();
    updateSubmitButton();
});

// Submit button click handler with final duplicate check
$('#submitBtn').click(function(e) {
    e.preventDefault();
    
    if (!selectedService || !selectedDate || !selectedTime) {
        Swal.fire({
            icon: 'warning',
            title: 'Incomplete Form',
            text: 'Please select a service, date, and time to continue.',
            confirmButtonColor: '#6f42c1'
        });
        return false;
    }
    
    // CRITICAL: Final check - prevent duplicate booking
    if (userBookedTimes[selectedTime]) {
        Swal.fire({
            icon: 'error',
            title: 'Duplicate Booking Detected!',
            html: `
                <div class="text-start">
                    <div class="alert alert-danger">
                        <i class="fas fa-ban me-2"></i>
                        <strong>You already have an appointment at ${formatTime(selectedTime)} on ${formatDate(selectedDate)}</strong>
                    </div>
                    <p class="mt-2">You cannot book multiple appointments at the same time slot.</p>
                    <div class="alert alert-info mt-2 small">
                        <i class="fas fa-lightbulb"></i>
                        Please choose a different time for this service.
                    </div>
                </div>
            `,
            confirmButtonText: 'Choose Different Time',
            confirmButtonColor: '#6f42c1'
        });
        return false;
    }
    
    // Check if time slot is at capacity
    const slotKey = selectedDate + '|' + selectedTime;
    const slotInfo = bookedSlotsData[slotKey];
    if (slotInfo && slotInfo.count >= maxCapacity) {
        Swal.fire({
            icon: 'error',
            title: 'Time Slot Full',
            text: `This time slot is fully booked (${slotInfo.count}/${maxCapacity}). Please select another time.`,
            confirmButtonColor: '#6f42c1'
        }).then(() => {
            checkAvailability(selectedDate);
        });
        return false;
    }
    
    // Show confirmation dialog
    const remainingSpots = slotInfo ? maxCapacity - slotInfo.count : maxCapacity;
    const capacityWarning = remainingSpots <= 2 ? 
        `<div class="alert alert-warning mt-2 small">
            <i class="fas fa-exclamation-triangle"></i> Only ${remainingSpots} spot${remainingSpots > 1 ? 's' : ''} remaining!
        </div>` : '';
    
    Swal.fire({
        title: 'Confirm Your Booking',
        html: `
            <div class="text-start p-3">
                <div class="alert alert-light border">
                    <h6 class="mb-2 text-primary">Appointment Details:</h6>
                    <p class="mb-1"><strong>Service:</strong> ${selectedService.name}</p>
                    <p class="mb-1"><strong>Date:</strong> ${formatDate(selectedDate)}</p>
                    <p class="mb-1"><strong>Time:</strong> ${formatTime(selectedTime)}</p>
                    <p class="mb-1"><strong>Duration:</strong> ${selectedService.duration} minutes</p>
                    <p class="mb-0"><strong>Total:</strong> Rs: ${selectedService.price}</p>
                </div>
                ${capacityWarning}
                <div class="alert alert-warning mt-2 small">
                    <i class="fas fa-ban"></i>
                    <strong>Remember:</strong> You cannot book another appointment at this same time.
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Book Now!',
        cancelButtonText: 'Review Details',
        confirmButtonColor: '#6f42c1',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            $('#selectedServiceId').val(selectedService.id);
            $('#appointment_date').val(selectedDate);
            $('#selectedTime').val(selectedTime);
            $('#bookingForm').get(0).submit();
        }
    });
    
    return false;
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
                    service_id: selectedService ? selectedService.id : 0,
                    user_id: <?php echo $user_id; ?>
                },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    if (response.success) {
                        // Store data
                        timeSlotsData = response.time_slots;
                        bookedSlotsData = response.booked_slots;
                        userBookedTimes = response.user_booked_times;
                        availableStaffCount = response.available_staff_by_slot;
                        
                        // Update user appointments display
                        if (response.user_appointments && response.user_appointments.length > 0) {
                            updateUserAppointmentsDisplay(response.user_appointments);
                        }
                        
                        // Update time slots UI
                        updateTimeSlotsDisplay();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Unable to check availability',
                            confirmButtonColor: '#6f42c1'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Availability check error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Unable to check availability. Please try again.',
                        confirmButtonColor: '#6f42c1'
                    });
                }
            });
        }
        
        // Update time slots display based on availability
        function updateTimeSlotsDisplay() {
            $('.time-slot').each(function() {
                const time = $(this).data('time');
                const slotKey = selectedDate + ' ' + time;
                const slotInfo = bookedSlotsData[slotKey];
                const timeSlotInfo = timeSlotsData.find(slot => slot.time === time);
                const isUserBooked = userBookedTimes[time] === true;
                
                // Remove existing badges and classes
                $(this).find('.capacity-badge, .staff-badge, .user-badge').remove();
                $(this).removeClass('booked full partial unavailable user-booked selected');
                
                // Check if user already has an appointment at this time
                if (isUserBooked) {
                    $(this).addClass('user-booked');
                    $(this).attr('title', 'You already have an appointment at this time');
                    $(this).append('<span class="user-badge">Already Booked</span>');
                }
                // Check if slot is full
                else if (slotInfo && slotInfo.count >= maxCapacity) {
                    $(this).addClass('full');
                    $(this).attr('title', `Fully booked (${slotInfo.count}/${maxCapacity})`);
                    $(this).append('<span class="capacity-badge full">Full</span>');
                }
                // Check if slot has some bookings
                else if (slotInfo && slotInfo.count > 0) {
                    $(this).addClass('partial');
                    $(this).attr('title', `${slotInfo.remaining} spots remaining`);
                    $(this).append(`<span class="capacity-badge">${slotInfo.remaining} left</span>`);
                }
                // Check if no staff available
                else if (timeSlotInfo && !timeSlotInfo.available) {
                    $(this).addClass('unavailable');
                    $(this).attr('title', timeSlotInfo.reason);
                    $(this).append('<span class="capacity-badge">No staff</span>');
                }
                // Available slot
                else {
                    const staffCount = availableStaffCount[time] ? availableStaffCount[time].length : 0;
                    if (staffCount > 0) {
                        $(this).attr('title', `${staffCount} staff available`);
                        $(this).append(`<span class="staff-badge">${staffCount} staff</span>`);
                    }
                }
            });
        }
        
        // Update user appointments display in sidebar
        function updateUserAppointmentsDisplay(appointments) {
            if (appointments && appointments.length > 0) {
                let html = '<div class="existing-appointments-summary mt-3">';
                html += '<div class="alert alert-warning p-2 small">';
                html += '<i class="fas fa-calendar-alt me-1"></i> ';
                html += '<strong>Your existing appointments:</strong>';
                html += '<ul class="mb-0 mt-1 ps-3">';
                
                appointments.slice(0, 5).forEach(apt => {
                    html += `<li>${formatDate(apt.appointment_date)} at ${formatTime(apt.appointment_time)} - ${apt.service_name}</li>`;
                });
                
                if (appointments.length > 5) {
                    html += `<li>And ${appointments.length - 5} more...</li>`;
                }
                
                html += '</ul>';
                html += '<hr class="my-1">';
                html += '<small class="text-danger">';
                html += '<i class="fas fa-ban me-1"></i>';
                html += '<strong>Cannot book multiple appointments at the same time</strong>';
                html += '</small>';
                html += '</div></div>';
                
                // Update or create the existing appointments section
                if ($('.existing-appointments-summary').length) {
                    $('.existing-appointments-summary').replaceWith(html);
                } else {
                    $('.summary-sidebar .capacity-info').after(html);
                }
            }
        }
        
        // Update submit button state
        function updateSubmitButton() {
            const isValid = selectedService && selectedDate && selectedTime;
            $('#submitBtn').prop('disabled', !isValid);
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
                $('#summaryDate').text(formatDate(date));
            } else {
                $('#summaryDate').text('Not selected');
            }
        }
        
        // Update time summary
        function updateTimeSummary() {
            if (selectedTime) {
                $('#summaryTime').text(formatTime(selectedTime));
                
                // Check if user already has this time booked
                if (userBookedTimes[selectedTime]) {
                    $('#summaryTime').append('<br><small class="text-danger">You already have an appointment at this time!</small>');
                }
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
            
            if (!selectedService || !selectedDate || !selectedTime) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Incomplete Form',
                    text: 'Please select a service, date, and time to continue.',
                    confirmButtonColor: '#6f42c1'
                });
                return false;
            }
            
            // CRITICAL: Final check - prevent duplicate booking
            if (userBookedTimes[selectedTime]) {
                Swal.fire({
                    icon: 'error',
                    title: 'Duplicate Booking Not Allowed!',
                    html: `
                        <div class="text-start">
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>You already have an appointment at this time!</strong>
                            </div>
                            <p><strong>Time:</strong> ${formatTime(selectedTime)} on ${formatDate(selectedDate)}</p>
                            <p class="text-muted">You cannot book multiple appointments at the same time slot.</p>
                            <div class="alert alert-info mt-2 small">
                                <i class="fas fa-lightbulb"></i>
                                Please choose a different time or date for this service.
                            </div>
                        </div>
                    `,
                    confirmButtonText: 'Choose Different Time',
                    confirmButtonColor: '#6f42c1'
                });
                return false;
            }
            
            // Check if time slot is at capacity
            const slotKey = selectedDate + ' ' + selectedTime;
            const slotInfo = bookedSlotsData[slotKey];
            if (slotInfo && slotInfo.count >= maxCapacity) {
                Swal.fire({
                    icon: 'error',
                    title: 'Time Slot Full',
                    text: `This time slot is fully booked (${slotInfo.count}/${maxCapacity}). Please select another time.`,
                    confirmButtonColor: '#6f42c1'
                }).then(() => {
                    checkAvailability(selectedDate);
                });
                return false;
            }
            
            // Show confirmation dialog
            const remainingSpots = slotInfo ? maxCapacity - slotInfo.count : maxCapacity;
            const capacityWarning = remainingSpots <= 2 ? 
                `<div class="alert alert-warning mt-2 small">
                    <i class="fas fa-exclamation-triangle"></i> Only ${remainingSpots} spot${remainingSpots > 1 ? 's' : ''} remaining!
                </div>` : '';
            
            Swal.fire({
                title: 'Confirm Your Booking',
                html: `
                    <div class="text-start p-3">
                        <div class="alert alert-light border">
                            <h6 class="mb-2 text-primary">Appointment Details:</h6>
                            <p class="mb-1"><strong>Service:</strong> ${selectedService.name}</p>
                            <p class="mb-1"><strong>Date:</strong> ${formatDate(selectedDate)}</p>
                            <p class="mb-1"><strong>Time:</strong> ${formatTime(selectedTime)}</p>
                            <p class="mb-1"><strong>Duration:</strong> ${selectedService.duration} minutes</p>
                            <p class="mb-0"><strong>Total:</strong> Rs: ${selectedService.price}</p>
                        </div>
                        ${capacityWarning}
                        <div class="alert alert-info mt-2 small">
                            <i class="fas fa-info-circle"></i>
                            <strong>Important:</strong> You cannot book another appointment at this same time.
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Book Now!',
                cancelButtonText: 'Review Details',
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
                    $('#bookingForm').get(0).submit();
                }
            });
            
            return false;
        });
        
        // Reset form
        $('#resetBtn').click(function() {
            Swal.fire({
                title: 'Reset Form?',
                text: 'This will clear all your selections.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Reset',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#6f42c1'
            }).then((result) => {
                if (result.isConfirmed) {
                    resetForm();
                }
            });
        });
        
        // Initialize form
        initializeForm();
    });
    
    // Initialize form with POST data
    function initializeForm() {
        const serviceId = $('#selectedServiceId').val();
        const date = $('#appointment_date').val();
        const time = $('#selectedTime').val();
        
        if (serviceId) {
            const serviceCard = $(`.service-card[data-service-id="${serviceId}"]`);
            if (serviceCard.length) {
                serviceCard.click();
            }
        }
        
        if (date) {
            selectedDate = date;
            updateDateSummary(date);
            checkAvailability(date);
        }
        
        if (time) {
            selectedTime = time;
            $(`.time-slot[data-time="${time}"]`).click();
        }
    }
    
    // Reset form function
    function resetForm() {
        selectedService = null;
        selectedDate = null;
        selectedTime = null;
        userBookedTimes = {};
        
        $('.service-card').removeClass('selected');
        $('#selectedServiceId').val('');
        $('#appointment_date').val('');
        $('.time-slot').removeClass('selected');
        $('#selectedTime').val('');
        $('#notes').val('');
        
        $('#summaryService').text('Not selected');
        $('#summaryDate').text('Not selected');
        $('#summaryTime').text('Not selected');
        $('#summaryDuration').text('-');
        $('#summaryPrice').text('Rs: 0.00');
        $('#summaryTotal').text('Rs: 0.00');
        
        $('#submitBtn').prop('disabled', true);
        
        // Clear all slot classes
        $('.time-slot').removeClass('booked full partial unavailable user-booked selected');
        $('.time-slot .capacity-badge, .time-slot .staff-badge, .time-slot .user-badge').remove();
    }
    
    // Loading functions
    function showLoading() {
        $('#loadingOverlay').fadeIn();
    }
    
    function hideLoading() {
        $('#loadingOverlay').fadeOut();
    }
    
    // Helper functions
    function goToDashboard() {
        window.location.href = 'dashboard.php';
    }
    
    function refreshPage() {
        location.reload();
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
        /* Additional styles for capacity indicators */
        .time-slot {
            position: relative;
        }
        
        .time-slot.partial {
            background: #fff3cd;
            border-color: #ffc107;
        }
        
        .time-slot.full {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .time-slot.unavailable {
            background: #e9ecef;
            color: #6c757d;
            border-color: #dee2e6;
            cursor: not-allowed;
        }
        
        .time-slot.user-booked {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
            cursor: not-allowed;
            opacity: 0.9;
        }
        
        .time-slot .capacity-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #6f42c1;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .time-slot .capacity-badge.full {
            background: #dc3545;
        }
        
        .time-slot .staff-badge {
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            background: #28a745;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .time-slot .user-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #17a2b8;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .time-slot.partial .capacity-badge {
            background: #ffc107;
            color: #856404;
        }
        
        .time-slot.selected .capacity-badge,
        .time-slot.selected .staff-badge,
        .time-slot.selected .user-badge {
            background: white;
            color: #6f42c1;
        }
        
        .capacity-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }
        
        .existing-appointments-summary .alert {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .existing-appointments-summary .alert-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
    </style>
</body>
</html>
<?php ob_end_flush(); ?>
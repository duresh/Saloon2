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

// Check if user is staff member (redirect to staff-dashboard)
if (isset($_SESSION['role']) && $_SESSION['role'] == 'staff') {
    header('Location: ../admin/staff-dashboard.php');
    exit();
}

// Include database connection
require_once '../../includes/dbcon.php';

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
    
    // Get user appointments
    $appointments_query = "SELECT 
        a.id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.notes,
        a.created_at,
        s.name as service_name,
        s.price,
        s.duration,
        s.description as service_description
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.user_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5";
    
    $appointments_stmt = $pdo->prepare($appointments_query);
    $appointments_stmt->execute([$user_id]);
    $appointments = $appointments_stmt->fetchAll();
    
    // Get appointment statistics
    $stats_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments WHERE user_id = ?";
    
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch();
    
    // Get total services count
    $services_query = "SELECT COUNT(*) as total FROM services WHERE status = 'active'";
    $services_stmt = $pdo->query($services_query);
    $services_count = $services_stmt->fetch()['total'];
    
} catch (PDOException $e) {
    error_log('Dashboard query error: ' . $e->getMessage());
    $error = "Unable to load dashboard data. Please try again later.";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}
include 'header/header.php';
?>


        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon icon-1">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="value"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="label">Total Appointments</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon icon-2">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="value"><?php echo $stats['pending'] ?? 0; ?></div>
                    <div class="label">Pending</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon icon-3">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="value"><?php echo $stats['completed'] ?? 0; ?></div>
                    <div class="label">Completed</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon icon-4">
                        <i class="fas fa-spa"></i>
                    </div>
                    <div class="value"><?php echo $services_count; ?></div>
                    <div class="label">Services Available</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Appointments -->
            <div class="col-lg-8">
                <div class="table-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4><i class="fas fa-history me-2"></i> Recent Appointments</h4>
                        <a href="appointments.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    
                    <?php if (!empty($appointments)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($appointment['service_name'] ?? 'N/A'); ?></strong>
                                        <?php if ($appointment['price']): ?>
                                        <br><small class="text-muted">$<?php echo number_format($appointment['price'], 2); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                    </td>
                                    <td>
                                        <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info me-1" 
                                                onclick="viewAppointment(<?php echo $appointment['id']; ?>)"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($appointment['status'] == 'pending'): ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="cancelAppointment(<?php echo $appointment['id']; ?>)"
                                                title="Cancel">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h4>No Appointments Yet</h4>
                        <p>You haven't booked any appointments yet.</p>
                        <button class="btn btn-primary" onclick="bookAppointment()">
                            <i class="fas fa-plus me-2"></i>Book Your First Appointment
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="quick-actions">
                    <h4 class="mb-4"><i class="fas fa-bolt me-2"></i> Quick Actions</h4>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <div class="action-card" onclick="bookAppointment()">
                                <i class="fas fa-calendar-plus"></i>
                                <h6>Book Now</h6>
                                <p class="small mb-0">Schedule appointment</p>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="action-card" onclick="location.href='services.php'">
                                <i class="fas fa-spa"></i>
                                <h6>Services</h6>
                                <p class="small mb-0">View all services</p>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="action-card" onclick="location.href='profile.php'">
                                <i class="fas fa-user-edit"></i>
                                <h6>Profile</h6>
                                <p class="small mb-0">Update information</p>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="action-card" onclick="showHelp()">
                                <i class="fas fa-question-circle"></i>
                                <h6>Help</h6>
                                <p class="small mb-0">Need assistance?</p>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Appointment -->
                    <?php 
                    $upcoming = array_filter($appointments, function($apt) {
                        return $apt['status'] == 'confirmed' && 
                               strtotime($apt['appointment_date']) >= strtotime('today');
                    });
                    $upcoming = array_slice($upcoming, 0, 1);
                    ?>
                    
                    <?php if (!empty($upcoming)): 
                    $next = reset($upcoming); ?>
                    <div class="mt-4 p-3" style="background: #e8f4fd; border-radius: 10px;">
                        <h6><i class="fas fa-clock me-2"></i> Next Appointment</h6>
                        <div class="mt-2">
                            <p class="mb-1"><strong><?php echo htmlspecialchars($next['service_name']); ?></strong></p>
                            <p class="mb-1 small">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('D, M d', strtotime($next['appointment_date'])); ?>
                            </p>
                            <p class="mb-1 small">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo date('h:i A', strtotime($next['appointment_time'])); ?>
                            </p>
                            <button class="btn btn-sm btn-primary mt-2 w-100" 
                                    onclick="viewAppointment(<?php echo $next['id']; ?>)">
                                View Details
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mt-4 p-3 text-center" style="background: #f8f9fa; border-radius: 10px;">
                        <i class="fas fa-calendar-day text-muted mb-2" style="font-size: 2rem;"></i>
                        <p class="mb-2">No upcoming appointments</p>
                        <button class="btn btn-sm btn-outline-primary" onclick="bookAppointment()">
                            Book Now
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

   <?php include 'footer/footer.php'; ?>
    
    <script>
        // Toggle sidebar on mobile
        $(document).ready(function() {
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
        });

        // Dashboard Functions
        function bookAppointment() {
            Swal.fire({
                title: 'Book Appointment',
                text: 'Redirecting to booking page...',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Continue',
                cancelButtonText: 'Later',
                confirmButtonColor: '#6f42c1'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'book-appointment.php';
                }
            });
        }

        function viewAppointment(appointmentId) {
            // AJAX call to get appointment details
            $.ajax({
                url: 'ajax/get-appointment.php',
                method: 'POST',
                data: { id: appointmentId },
                beforeSend: function() {
                    Swal.fire({
                        title: 'Loading...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                },
                success: function(response) {
                    Swal.close();
                    try {
                        const data = JSON.parse(response);
                        Swal.fire({
                            title: 'Appointment Details',
                            html: `
                                <div class="text-start">
                                    <h5>${data.service_name}</h5>
                                    <div class="row">
                                        <div class="col-6">
                                            <p class="mb-1"><strong>Date:</strong><br>${data.date}</p>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-1"><strong>Time:</strong><br>${data.time}</p>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-1"><strong>Price:</strong><br>Rs: ${data.price}</p>
                                        </div>
                                        <div class="col-6">
                                            <p class="mb-1"><strong>Duration:</strong><br>${data.duration} mins</p>
                                        </div>
                                    </div>
                                    <p class="mb-1"><strong>Status:</strong> 
                                        <span class="badge badge-${data.status}">${data.status}</span>
                                    </p>
                                    ${data.notes ? `<p class="mb-0"><strong>Notes:</strong><br>${data.notes}</p>` : ''}
                                </div>
                            `,
                            showCloseButton: true,
                            confirmButtonText: 'Close',
                            confirmButtonColor: '#6f42c1',
                            width: '500px'
                        });
                    } catch (e) {
                        Swal.fire('Error', 'Failed to load appointment details', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to load appointment details', 'error');
                }
            });
        }

        function cancelAppointment(appointmentId) {
            Swal.fire({
                title: 'Cancel Appointment?',
                text: "Are you sure you want to cancel this appointment?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, cancel it!',
                cancelButtonText: 'No, keep it'
            }).then((result) => {
                if (result.isConfirmed) {
                    // AJAX call to cancel appointment
                    $.ajax({
                        url: 'ajax/cancel-appointment.php',
                        method: 'POST',
                        data: { 
                            id: appointmentId
                        },
                        beforeSend: function() {
                            Swal.fire({
                                title: 'Processing...',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                        },
                        success: function(response) {
                            const result = JSON.parse(response);
                            if (result.success) {
                                Swal.fire({
                                    title: 'Cancelled!',
                                    text: 'Your appointment has been cancelled.',
                                    icon: 'success',
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#6f42c1'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error!', result.message || 'Failed to cancel appointment', 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error!', 'Failed to cancel appointment. Please try again.', 'error');
                        }
                    });
                }
            });
        }

        function showHelp() {
            Swal.fire({
                title: 'Need Help?',
                html: `
                    <div class="text-start">
                        <h6>Contact Information</h6>
                        <p><i class="fas fa-phone me-2"></i> (071) 805-9219</p>
                        <p><i class="fas fa-envelope me-2"></i> support@salonelegance.com</p>
                        
                        <h6 class="mt-3">Business Hours</h6>
                        <p>Monday - Friday: 9:00 AM - 8:00 PM</p>
                        <p>Saturday: 9:00 AM - 6:00 PM</p>
                        <p>Sunday: 10:00 AM - 4:00 PM</p>
                    </div>
                `,
                showCloseButton: true,
                confirmButtonText: 'Got it!',
                confirmButtonColor: '#6f42c1',
                width: '500px'
            });
        }

        function refreshDashboard() {
            Swal.fire({
                title: 'Refreshing...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            setTimeout(() => {
                location.reload();
            }, 500);
        }

        // Auto refresh appointments every 30 seconds
        setInterval(function() {
            $.ajax({
                url: 'ajax/check-updates.php',
                method: 'POST',
                data: { user_id: <?php echo $user_id; ?> },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.hasUpdates) {
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                            didOpen: (toast) => {
                                toast.addEventListener('mouseenter', Swal.stopTimer);
                                toast.addEventListener('mouseleave', Swal.resumeTimer);
                            }
                        });

                        Toast.fire({
                            icon: 'info',
                            title: 'Appointment status updated!'
                        });
                    }
                }
            });
        }, 30000);
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
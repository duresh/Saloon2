<?php
// staff-history.php - Staff Appointment History
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
    
    $staff_db_id = (int)$staff['id'];
    $staff_name = $staff['fName'] ?? $staff_name;
    
    // Get unread notifications count
    $notif_count_query = "
        SELECT COUNT(*) as count FROM staff_notifications 
        WHERE staff_id = ? AND is_read = 0
    ";
    $notif_count_stmt = $pdo->prepare($notif_count_query);
    $notif_count_stmt->execute([$staff_db_id]);
    $unread_count = $notif_count_stmt->fetch()['count'] ?? 0;
    
    // Get notifications for staff (for bell icon)
    $notif_query = "
        SELECT * FROM staff_notifications 
        WHERE staff_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ";
    $notif_stmt = $pdo->prepare($notif_query);
    $notif_stmt->execute([$staff_db_id]);
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get initial stats
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
            COALESCE(SUM(s.price), 0) as total_revenue
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.staff_id = " . (int)$staff_db_id . "
    ";
    $stats_stmt = $pdo->query($stats_query);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stats) {
        $stats = [
            'total' => 0,
            'completed' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'cancelled' => 0,
            'no_show' => 0,
            'total_revenue' => 0
        ];
    }
    
} catch (PDOException $e) {
    error_log("Staff history error: " . $e->getMessage());
    $staff = null;
    $staff_db_id = 0;
    $unread_count = 0;
    $notifications = [];
    $stats = [
        'total' => 0,
        'completed' => 0,
        'pending' => 0,
        'confirmed' => 0,
        'cancelled' => 0,
        'no_show' => 0,
        'total_revenue' => 0
    ];
}

// Include staff header
include 'header/header-staff.php';
?>

<style>
/* History Page Styles */
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 15px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-card .stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.stat-card .stat-details {
    flex: 1;
    min-width: 0;
}

.stat-card .stat-value {
    font-size: 22px;
    font-weight: 700;
    line-height: 1.2;
    color: #333;
}

.stat-card .stat-label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 2px;
}

.filter-card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-radius: 12px;
}

.filter-card .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    border-radius: 12px 12px 0 0;
    padding: 15px 20px;
}

.table-card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-radius: 12px;
    overflow: hidden;
}

.table-card .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 15px 20px;
}

.table-card .table {
    margin-bottom: 0;
}

.table-card .table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
    font-size: 13px;
    border-bottom: 2px solid #e9ecef;
    padding: 12px 15px;
    white-space: nowrap;
}

.table-card .table td {
    padding: 12px 15px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.confirmed { background: #d4edda; color: #155724; }
.status-badge.completed { background: #cce5ff; color: #004085; }
.status-badge.cancelled { background: #f8d7da; color: #721c24; }
.status-badge.no_show { background: #e2e3e5; color: #383d41; }

.dashboard-header {
    background: white;
    border-radius: 12px;
    padding: 20px 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-left: 5px solid #6f42c1;
}

.welcome-text h1 {
    color: #6f42c1;
    font-weight: 600;
}

.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #e9ecef;
    border-top: 3px solid #6f42c1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Notification Modal Styles */
.notification-modal .swal2-popup {
    padding: 0;
}
.notification-modal .swal2-html-container {
    padding: 0;
    margin: 0;
}
.notifications-container {
    max-height: 450px;
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
    max-height: 360px;
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

/* Responsive */
@media (max-width: 768px) {
    .stat-card {
        padding: 12px 15px;
    }
    .stat-card .stat-value {
        font-size: 18px;
    }
    .stat-card .stat-icon {
        width: 38px;
        height: 38px;
        font-size: 16px;
    }
    
    .table-card .table {
        font-size: 13px;
    }
    .table-card .table th,
    .table-card .table td {
        padding: 8px 10px;
    }
}
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
                            <h1><i class="fas fa-history me-2"></i>Appointment History</h1>
                            <p class="lead mb-0">View all your past and upcoming appointments</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-light text-dark p-2 me-2">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($staff_name); ?>
                        </span>
                        <button class="btn btn-primary btn-sm" onclick="exportHistory()">
                            <i class="fas fa-file-pdf me-1"></i>Export
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="loadAppointments()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2 col-4">
                    <div class="stat-card" style="border-left: 4px solid #6f42c1;">
                        <div class="stat-icon" style="background: rgba(111, 66, 193, 0.12); color: #6f42c1;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value" id="statTotal"><?php echo $stats['total'] ?? 0; ?></div>
                            <div class="stat-label">Total</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="stat-card" style="border-left: 4px solid #28a745;">
                        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.12); color: #28a745;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value" id="statCompleted"><?php echo $stats['completed'] ?? 0; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="stat-card" style="border-left: 4px solid #ffc107;">
                        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.12); color: #ffc107;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value" id="statActive"><?php echo ($stats['pending'] ?? 0) + ($stats['confirmed'] ?? 0); ?></div>
                            <div class="stat-label">Active</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="stat-card" style="border-left: 4px solid #dc3545;">
                        <div class="stat-icon" style="background: rgba(220, 53, 69, 0.12); color: #dc3545;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value" id="statCancelled"><?php echo $stats['cancelled'] ?? 0; ?></div>
                            <div class="stat-label">Cancelled</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="stat-card" style="border-left: 4px solid #ffc107;">
                        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.12); color: #ffc107;">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value" id="statRevenue">Rs: <?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-4">
                    <div class="stat-card" style="border-left: 4px solid #17a2b8;">
                        <div class="stat-icon" style="background: rgba(23, 162, 184, 0.12); color: #17a2b8;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-value" id="statAvgRevenue">Rs: <?php echo number_format(($stats['total_revenue'] ?? 0) / max($stats['total'] ?? 1, 1), 2); ?></div>
                            <div class="stat-label">Avg Revenue</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card filter-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter History</h5>
                </div>
                <div class="card-body">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" name="status" id="filterStatus">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="no_show">No Show</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">From Date</label>
                            <input type="date" class="form-control" name="date_from" id="filterDateFrom">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">To Date</label>
                            <input type="date" class="form-control" name="date_to" id="filterDateTo">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Search</label>
                            <input type="text" class="form-control" name="search" id="filterSearch" placeholder="Customer or service...">
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                                <i class="fas fa-undo me-1"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Appointments Table -->
            <div class="card table-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Appointments</h5>
                    <span class="badge bg-primary" id="recordCount">0 Records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Customer</th>
                                    <th>Service</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="appointmentsBody">
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="loading-spinner"></div>
                                        <p class="mt-2 text-muted">Loading appointments...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer" id="paginationContainer" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small" id="paginationInfo">Showing 0 to 0 of 0 records</div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="paginationLinks"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
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

<!-- Update Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <input type="hidden" id="statusAppointmentId">
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select class="form-select" id="newStatus">
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="no_show">No Show</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea class="form-control" id="statusNotes" rows="2" placeholder="Add any notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveStatusUpdate()">
                    <i class="fas fa-save me-2"></i>Update Status
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Store notifications data for bell icon
var notifications = <?php echo json_encode($notifications); ?>;
var unreadCount = <?php echo $unread_count; ?>;
var currentPage = 1;
var totalRecords = 0;
var totalPages = 1;

// Show notifications function for bell icon
function showNotifications() {
    var notificationsHtml = '';
    
    if (notifications && notifications.length > 0) {
        notificationsHtml = notifications.map(function(notif) {
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
                    ${unreadCount > 0 ? `<button class="mark-all-read" onclick="markAllNotificationsRead()">Mark all as read</button>` : ''}
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

function markNotificationRead(notificationId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'mark_notification_read',
            notification_id: notificationId
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(response) {
        if (response.success) {
            unreadCount--;
            var badge = document.querySelector('.notification-badge');
            if (badge) {
                if (unreadCount > 0) {
                    badge.textContent = unreadCount;
                } else {
                    badge.style.display = 'none';
                }
            }
            location.reload();
        }
    })
    .catch(function(error) {
        console.error('Error marking notification as read:', error);
    });
}

function markAllNotificationsRead() {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'mark_notification_read',
            notification_id: 0
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(response) {
        if (response.success) {
            unreadCount = 0;
            var badge = document.querySelector('.notification-badge');
            if (badge) {
                badge.style.display = 'none';
            }
            location.reload();
        }
    })
    .catch(function(error) {
        console.error('Error marking all notifications as read:', error);
    });
}

// Load appointments via AJAX
function loadAppointments(page) {
    page = page || 1;
    currentPage = page;
    
    var status = $('#filterStatus').val();
    var date_from = $('#filterDateFrom').val();
    var date_to = $('#filterDateTo').val();
    var search = $('#filterSearch').val();
    
    // Show loading
    $('#appointmentsBody').html(`
        <tr>
            <td colspan="7" class="text-center py-5">
                <div class="loading-spinner"></div>
                <p class="mt-2 text-muted">Loading appointments...</p>
            </td>
        </tr>
    `);
    
    $.ajax({
        url: 'ajax/get-staff-history.php',
        method: 'POST',
        data: {
            page: page,
            status: status,
            date_from: date_from,
            date_to: date_to,
            search: search,
            staff_id: <?php echo $staff_db_id; ?>
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayAppointments(response.data);
                updateStats(response.stats);
                
                totalRecords = response.total;
                totalPages = response.total_pages;
                
                if (totalPages > 1) {
                    renderPagination(page, totalPages);
                    $('#paginationContainer').show();
                } else {
                    $('#paginationContainer').hide();
                }
                
                $('#recordCount').text(totalRecords + ' Records');
            } else {
                $('#appointmentsBody').html(`
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="fas fa-exclamation-circle fa-2x text-danger mb-2 d-block"></i>
                            <p class="text-danger">${response.message || 'Failed to load appointments'}</p>
                        </td>
                    </tr>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            $('#appointmentsBody').html(`
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2 d-block"></i>
                        <p class="text-danger">Failed to load appointments. Please try again.</p>
                        <button class="btn btn-primary btn-sm" onclick="loadAppointments()">
                            <i class="fas fa-sync-alt me-1"></i>Retry
                        </button>
                    </td>
                </tr>
            `);
        }
    });
}

// Display appointments in table
function displayAppointments(appointments) {
    if (!appointments || appointments.length === 0) {
        $('#appointmentsBody').html(`
            <tr>
                <td colspan="7" class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3 d-block"></i>
                    <p class="text-muted mb-0">No appointments found</p>
                    <p class="text-muted small">Try adjusting your filters</p>
                </td>
            </tr>
        `);
        return;
    }
    
    var html = '';
    var count = (currentPage - 1) * 15 + 1;
    
    appointments.forEach(function(apt) {
        var statusClass = apt.status;
        var statusLabel = apt.status.charAt(0).toUpperCase() + apt.status.slice(1);
        
        html += `
            <tr>
                <td>${count++}</td>
                <td>
                    <strong>${escapeHtml(apt.customer_name)}</strong>
                    <br>
                    <small class="text-muted">${escapeHtml(apt.customer_phone || 'N/A')}</small>
                </td>
                <td>
                    ${escapeHtml(apt.service_name || 'N/A')}
                    <br>
                    <small class="text-muted">${apt.duration || 'N/A'} mins</small>
                </td>
                <td>
                    <div>${formatDate(apt.appointment_date)}</div>
                    <div class="text-muted small">${formatTime(apt.appointment_time)}</div>
                    ${apt.reschedule_count > 0 ? `
                    <span class="badge bg-warning text-dark" style="font-size: 10px;">
                        <i class="fas fa-sync-alt"></i> Rescheduled x${apt.reschedule_count}
                    </span>
                    ` : ''}
                </td>
                <td>
                    <span class="status-badge ${statusClass}">${statusLabel}</span>
                </td>
                <td>
                    <strong class="text-primary">Rs: ${parseFloat(apt.price || 0).toFixed(2)}</strong>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-info" onclick="viewDetails(${apt.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${apt.status === 'pending' || apt.status === 'confirmed' ? `
                    <button class="btn btn-sm btn-outline-success" onclick="updateStatus(${apt.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    ` : ''}
                </td>
            </tr>
        `;
    });
    
    $('#appointmentsBody').html(html);
}

// Update statistics
function updateStats(stats) {
    if (!stats) return;
    
    $('#statTotal').text(stats.total || 0);
    $('#statCompleted').text(stats.completed || 0);
    $('#statActive').text((stats.pending || 0) + (stats.confirmed || 0));
    $('#statCancelled').text(stats.cancelled || 0);
    $('#statRevenue').text('Rs: ' + parseFloat(stats.total_revenue || 0).toFixed(2));
    
    var avgRevenue = (stats.total || 0) > 0 ? (stats.total_revenue || 0) / stats.total : 0;
    $('#statAvgRevenue').text('Rs: ' + avgRevenue.toFixed(2));
}

// Render pagination
function renderPagination(currentPage, totalPages) {
    var html = '';
    var startPage = Math.max(1, currentPage - 2);
    var endPage = Math.min(totalPages, currentPage + 2);
    
    // Previous
    html += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadAppointments(${currentPage - 1}); return false;">
            <i class="fas fa-angle-left"></i>
        </a>
    </li>`;
    
    // First page
    if (startPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadAppointments(1); return false;">1</a></li>`;
        if (startPage > 2) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Page numbers
    for (var i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="loadAppointments(${i}); return false;">${i}</a>
        </li>`;
    }
    
    // Last page
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadAppointments(${totalPages}); return false;">${totalPages}</a></li>`;
    }
    
    // Next
    html += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadAppointments(${currentPage + 1}); return false;">
            <i class="fas fa-angle-right"></i>
        </a>
    </li>`;
    
    $('#paginationLinks').html(html);
    
    // Update pagination info
    var start = (currentPage - 1) * 15 + 1;
    var end = Math.min(currentPage * 15, totalRecords);
    $('#paginationInfo').text(`Showing ${start} to ${end} of ${totalRecords} records`);
}

// Apply filters
function applyFilters() {
    loadAppointments(1);
}

// Reset filters
function resetFilters() {
    $('#filterStatus').val('');
    $('#filterDateFrom').val('');
    $('#filterDateTo').val('');
    $('#filterSearch').val('');
    loadAppointments(1);
}

// View appointment details
function viewDetails(appointmentId) {
    var modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    
    document.getElementById('detailsContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary"></div>
        </div>
    `;
    modal.show();
    
    $.ajax({
        url: 'ajax/get-appointment-details.php',
        method: 'POST',
        data: { id: appointmentId, staff: 1 },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.appointment) {
                var apt = response.appointment;
                var html = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6><i class="fas fa-info-circle me-2"></i>Appointment Information</h6>
                            <p><strong>Service:</strong> ${escapeHtml(apt.service_name)}</p>
                            <p><strong>Date:</strong> ${formatDate(apt.appointment_date)}</p>
                            <p><strong>Time:</strong> ${formatTime(apt.appointment_time)}</p>
                            <p><strong>Duration:</strong> ${apt.service_duration || 'N/A'} minutes</p>
                            <p><strong>Status:</strong> <span class="status-badge ${apt.status}">${apt.status.charAt(0).toUpperCase() + apt.status.slice(1)}</span></p>
                            ${apt.reschedule_count > 0 ? `<p><strong>Rescheduled:</strong> ${apt.reschedule_count} times</p>` : ''}
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-user me-2"></i>Customer Information</h6>
                            <p><strong>Name:</strong> ${escapeHtml(apt.customer_name)}</p>
                            <p><strong>Email:</strong> ${escapeHtml(apt.customer_email || 'N/A')}</p>
                            <p><strong>Phone:</strong> ${escapeHtml(apt.customer_phone || 'N/A')}</p>
                            ${apt.notes ? `<p><strong>Notes:</strong> ${escapeHtml(apt.notes)}</p>` : ''}
                        </div>
                    </div>
                    <hr>
                    <h6><i class="fas fa-credit-card me-2"></i>Payment Information</h6>
                    <p><strong>Amount:</strong> <span class="text-primary fw-bold">Rs: ${parseFloat(apt.service_price || 0).toFixed(2)}</span></p>
                `;
                document.getElementById('detailsContent').innerHTML = html;
            } else {
                document.getElementById('detailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>${response.message || 'Could not load appointment details'}
                    </div>
                `;
            }
        },
        error: function() {
            document.getElementById('detailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>Failed to load appointment details
                </div>
            `;
        }
    });
}

// Update status
function updateStatus(appointmentId) {
    document.getElementById('statusAppointmentId').value = appointmentId;
    document.getElementById('newStatus').value = 'completed';
    document.getElementById('statusNotes').value = '';
    
    var modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

// Save status update
function saveStatusUpdate() {
    var appointmentId = document.getElementById('statusAppointmentId').value;
    var newStatus = document.getElementById('newStatus').value;
    var notes = document.getElementById('statusNotes').value;
    
    if (!appointmentId) {
        Swal.fire('Error', 'No appointment selected', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Updating Status...',
        allowOutsideClick: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: {
            action: 'update_status',
            appointment_id: appointmentId,
            status: newStatus,
            notes: notes
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            var modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
            if (modal) modal.hide();
            
            if (response.success) {
                Swal.fire('Success!', response.message, 'success').then(function() {
                    loadAppointments(currentPage);
                });
            } else {
                Swal.fire('Error', response.message || 'Failed to update status', 'error');
            }
        },
        error: function() {
            Swal.close();
            Swal.fire('Error', 'Failed to update status. Please try again.', 'error');
        }
    });
}

// Export history
function exportHistory() {
    Swal.fire({
        title: 'Generating PDF...',
        text: 'Please wait while we create your report.',
        allowOutsideClick: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });
    
    var status = $('#filterStatus').val();
    var date_from = $('#filterDateFrom').val();
    var date_to = $('#filterDateTo').val();
    var search = $('#filterSearch').val();
    
    var params = new URLSearchParams();
    if (status) params.append('status', status);
    if (date_from) params.append('date_from', date_from);
    if (date_to) params.append('date_to', date_to);
    if (search) params.append('search', search);
    
    var iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = 'export-staff-history.php?' + params.toString();
    document.body.appendChild(iframe);
    
    setTimeout(function() {
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'Export Complete!',
            text: 'Your history report has been downloaded.',
            timer: 2000,
            showConfirmButton: false
        });
        
        setTimeout(function() {
            if (document.body.contains(iframe)) {
                document.body.removeChild(iframe);
            }
        }, 1500);
    }, 2000);
}

// Helper functions
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    var date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}

function formatTime(timeStr) {
    if (!timeStr) return 'N/A';
    var time = new Date('2000-01-01 ' + timeStr);
    return time.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
}

// Load appointments on page load
$(document).ready(function() {
    loadAppointments(1);
});
</script>
</body>
</html>